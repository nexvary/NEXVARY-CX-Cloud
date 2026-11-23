package com.nexvary.cx

import com.nexvary.cx.data.PairingClient
import com.nexvary.cx.data.PairingException
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.Json
import okhttp3.OkHttpClient
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import okhttp3.tls.HandshakeCertificates
import okhttp3.tls.HeldCertificate
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Before
import org.junit.Test

/**
 * HTTP**S**-level regression tests for [PairingClient].
 *
 * Complements [PairingClientTest] (which uses `enforceHttps=false` with plain
 * HTTP). Every test here exercises the HTTPS path used by the production app:
 * `enforceHttps=true`, real TLS handshake, standard hostname verification.
 *
 * The MockWebServer is started with a self-signed certificate whose private key
 * is generated fresh for the test run. Only the test client trusts it; the
 * production OkHttpClient built inside [PairingClient] never sees this cert.
 *
 * [PairingClient.buildHttpClient] is overridden via an anonymous subclass to
 * inject the test trust store without altering any production code path.
 */
class PairingClientHttpsTest {

    private lateinit var server: MockWebServer
    private lateinit var client: PairingClient
    private lateinit var heldCertificate: HeldCertificate
    private val json = Json { ignoreUnknownKeys = true; isLenient = true }

    private val validResponseBody = """
        {
          "token": "https-device-tok-xyz",
          "expires_at": "2027-01-01T00:00:00Z",
          "server_url": "https://cx.example.com",
          "api_version": "v1"
        }
    """.trimIndent()

    @Before
    fun setUp() {
        heldCertificate = HeldCertificate.Builder()
            .addSubjectAlternativeName("localhost")
            .build()

        val serverHandshake = HandshakeCertificates.Builder()
            .heldCertificate(heldCertificate)
            .build()

        val clientHandshake = HandshakeCertificates.Builder()
            .addTrustedCertificate(heldCertificate.certificate)
            .build()

        server = MockWebServer()
        server.useHttps(serverHandshake.sslSocketFactory(), false)
        server.start()

        client = object : PairingClient(json) {
            override fun buildHttpClient(): OkHttpClient.Builder =
                super.buildHttpClient()
                    .sslSocketFactory(
                        clientHandshake.sslSocketFactory(),
                        clientHandshake.trustManager,
                    )
        }
    }

    @After
    fun tearDown() { server.shutdown() }

    // ── 1. HTTPS exchange reaches the QR server at the correct path ───────────

    @Test
    fun `HTTPS exchange reaches QR server at correct API path`() = runTest {
        server.enqueue(MockResponse().setResponseCode(200).setBody(validResponseBody))

        val serverUrl = server.url("/").toString().trimEnd('/')
        // enforceHttps=true: production default — MockWebServer returns https:// URL
        client.exchange(
            serverUrl      = serverUrl,
            pairingToken   = "qr-tok-https-001",
            deviceId       = "device-https-001",
            enforceHttps   = true,
        )

        val recorded = server.takeRequest()
        assertEquals("POST /api/mobile/v1/pair/exchange HTTP/1.1", recorded.requestLine)
    }

    // ── 2. Exactly one Authorization header over HTTPS ────────────────────────

    @Test
    fun `HTTPS exchange carries exactly one Authorization header with QR token`() = runTest {
        server.enqueue(MockResponse().setResponseCode(200).setBody(validResponseBody))

        val serverUrl = server.url("/").toString().trimEnd('/')
        client.exchange(
            serverUrl      = serverUrl,
            pairingToken   = "qr-tok-https-002",
            deviceId       = "device-https-002",
            enforceHttps   = true,
        )

        val recorded    = server.takeRequest()
        val authHeaders = recorded.headers.values("Authorization")
        assertEquals("Must be exactly one Authorization header over HTTPS", 1, authHeaders.size)
        assertEquals("Bearer qr-tok-https-002", authHeaders[0])
    }

    // ── 3. Two-origin: exchange goes to QR server B, old server A gets nothing ─

    @Test
    fun `HTTPS exchange to QR server B does not reach old server A`() = runTest {
        // serverA = previously-paired server (no queued response — must stay at 0 requests)
        val serverA = server

        // serverB = server whose QR code was scanned
        val heldCertB = HeldCertificate.Builder()
            .addSubjectAlternativeName("localhost")
            .build()
        val serverBHandshake = HandshakeCertificates.Builder()
            .heldCertificate(heldCertB)
            .build()
        val clientTrustingBoth = HandshakeCertificates.Builder()
            .addTrustedCertificate(heldCertificate.certificate) // serverA cert
            .addTrustedCertificate(heldCertB.certificate)       // serverB cert
            .build()

        val serverB = MockWebServer()
        serverB.useHttps(serverBHandshake.sslSocketFactory(), false)
        serverB.start()

        val multiTrustClient = object : PairingClient(json) {
            override fun buildHttpClient(): OkHttpClient.Builder =
                super.buildHttpClient()
                    .sslSocketFactory(
                        clientTrustingBoth.sslSocketFactory(),
                        clientTrustingBoth.trustManager,
                    )
        }

        try {
            serverB.enqueue(MockResponse().setResponseCode(200).setBody(validResponseBody))

            val urlB = serverB.url("/").toString().trimEnd('/')
            multiTrustClient.exchange(
                serverUrl    = urlB,
                pairingToken = "qr-tok-B-https",
                deviceId     = "device-https-003",
                enforceHttps = true,
            )

            assertEquals("serverB must receive exactly one HTTPS request", 1, serverB.requestCount)
            assertEquals("serverA must receive no HTTPS requests",         0, serverA.requestCount)
        } finally {
            serverB.shutdown()
        }
    }

    // ── 4. HTTPS redirect is not followed ─────────────────────────────────────

    @Test
    fun `HTTPS redirect response is not followed and throws`() = runTest {
        server.enqueue(
            MockResponse()
                .setResponseCode(301)
                .addHeader("Location", "https://evil.example.com/api/mobile/v1/pair/exchange"),
        )

        val serverUrl = server.url("/").toString().trimEnd('/')
        try {
            client.exchange(
                serverUrl    = serverUrl,
                pairingToken = "sensitive-tok-https",
                deviceId     = "device-https-004",
                enforceHttps = true,
            )
            assert(false) { "Expected an exception — HTTPS redirect must not be followed" }
        } catch (e: PairingException) {
            // expected
        }
        assertEquals("Must not follow HTTPS redirect to external origin", 1, server.requestCount)
    }

    // ── 5. HTTPS response token matches what the QR server issued ─────────────

    @Test
    fun `HTTPS response token is what the QR server issued`() = runTest {
        val uniqueToken = "https-server-issued-token-abc789"
        server.enqueue(
            MockResponse().setResponseCode(200).setBody(
                """
                {
                  "token": "$uniqueToken",
                  "expires_at": "2027-06-01T00:00:00Z",
                  "server_url": "https://cx.example.com",
                  "api_version": "v1"
                }
                """.trimIndent()
            )
        )

        val serverUrl = server.url("/").toString().trimEnd('/')
        val response  = client.exchange(
            serverUrl    = serverUrl,
            pairingToken = "qr-tok-https-005",
            deviceId     = "device-https-005",
            enforceHttps = true,
        )

        assertEquals(
            "Token stored after HTTPS pairing must be the one issued by the QR server",
            uniqueToken,
            response.token,
        )
    }
}
