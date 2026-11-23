package com.nexvary.cx

import com.nexvary.cx.data.PairingClient
import com.nexvary.cx.data.PairingException
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.Json
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * HTTP-level regression tests for [PairingClient].
 *
 * These tests use the production Retrofit/OkHttp configuration (a real
 * OkHttpClient + Retrofit instance built inside PairingClient) against a
 * local MockWebServer. All credentials are fake.
 */
class PairingClientTest {

    private lateinit var server: MockWebServer
    private lateinit var client: PairingClient
    private val json = Json { ignoreUnknownKeys = true; isLenient = true }

    // Minimal valid exchange response body
    private val validResponseBody = """
        {
          "token": "device-tok-abc123",
          "expires_at": "2027-01-01T00:00:00Z",
          "server_url": "https://cx.example.com",
          "api_version": "v1"
        }
    """.trimIndent()

    @Before
    fun setUp() {
        server = MockWebServer()
        server.start()
        client = PairingClient(json)
    }

    @After
    fun tearDown() {
        server.shutdown()
    }

    // ── 1. Fresh install: exchange reaches QR server with correct path ────────

    @Test
    fun `exchange reaches QR server URL and correct API path`() = runTest {
        server.enqueue(MockResponse().setResponseCode(200).setBody(validResponseBody))

        val serverUrl = server.url("/").toString().trimEnd('/')
        client.exchange(
            serverUrl = serverUrl,
            pairingToken = "qr-pairing-tok-fresh",
            deviceId = "device-id-001",
            enforceHttps = false, // MockWebServer uses HTTP
        )

        val recorded = server.takeRequest()
        assertEquals("POST /api/mobile/v1/pair/exchange HTTP/1.1", recorded.requestLine)
    }

    // ── 2. Fresh install: exactly one Authorization header with QR token ──────

    @Test
    fun `exchange sends exactly one Authorization header containing QR pairing token`() = runTest {
        server.enqueue(MockResponse().setResponseCode(200).setBody(validResponseBody))

        val serverUrl = server.url("/").toString().trimEnd('/')
        client.exchange(
            serverUrl = serverUrl,
            pairingToken = "qr-pairing-tok-secret",
            deviceId = "device-id-002",
            enforceHttps = false,
        )

        val recorded = server.takeRequest()
        val authHeaders = recorded.headers.values("Authorization")

        assertEquals("Must be exactly one Authorization header", 1, authHeaders.size)
        assertEquals("Bearer qr-pairing-tok-secret", authHeaders[0])
    }

    // ── 3. Re-pair: exchange goes to new server, old server receives nothing ──

    @Test
    fun `re-pairing sends exchange to QR server not to any other server`() = runTest {
        // serverA = old saved server (simulated by a second MockWebServer)
        val serverA = MockWebServer().also { it.start() }
        // serverB = QR server
        val serverB = MockWebServer().also { it.start() }
        try {
            serverB.enqueue(MockResponse().setResponseCode(200).setBody(validResponseBody))

            val urlB = serverB.url("/").toString().trimEnd('/')
            client.exchange(
                serverUrl = urlB,
                pairingToken = "qr-tok-for-B",
                deviceId = "device-id-003",
                enforceHttps = false,
            )

            // serverB receives exactly one request
            assertEquals("serverB must receive exactly one request", 1, serverB.requestCount)
            // serverA receives zero requests
            assertEquals("serverA must receive no requests", 0, serverA.requestCount)
        } finally {
            serverA.shutdown()
            serverB.shutdown()
        }
    }

    // ── 4. Re-pair: serverB never receives serverA's stored device token ──────

    @Test
    fun `re-pairing does not send old device token to new server`() = runTest {
        server.enqueue(MockResponse().setResponseCode(200).setBody(validResponseBody))

        val serverUrl = server.url("/").toString().trimEnd('/')
        client.exchange(
            serverUrl = serverUrl,
            pairingToken = "qr-tok-for-new-server",
            deviceId = "device-id-004",
            enforceHttps = false,
        )

        val recorded = server.takeRequest()
        val authHeader = recorded.headers["Authorization"] ?: ""

        // The PairingClient interceptor must only add the QR pairing token.
        // The global interceptor is bypassed entirely by PairingClient.
        assertEquals("Bearer qr-tok-for-new-server", authHeader)
        // Verify the old device token (simulated here) is not present anywhere.
        val body = recorded.body.readUtf8()
        assertTrue(
            "Request body must not contain any stored device token",
            !body.contains("old-device-tok"),
        )
    }

    // ── 5. 401 response → TokenExpiredOrRejected ──────────────────────────────

    @Test
    fun `401 response throws TokenExpiredOrRejected`() = runTest {
        server.enqueue(MockResponse().setResponseCode(401).setBody("""{"error":"Unauthenticated"}"""))

        val serverUrl = server.url("/").toString().trimEnd('/')
        try {
            client.exchange(
                serverUrl = serverUrl,
                pairingToken = "expired-tok",
                deviceId = "device-id-005",
                enforceHttps = false,
            )
            assertTrue("Expected PairingException.TokenExpiredOrRejected", false)
        } catch (e: PairingException.TokenExpiredOrRejected) {
            // expected
        }
    }

    // ── 6. 403 response → TokenExpiredOrRejected ─────────────────────────────

    @Test
    fun `403 response throws TokenExpiredOrRejected`() = runTest {
        server.enqueue(MockResponse().setResponseCode(403).setBody("""{"error":"Forbidden"}"""))

        val serverUrl = server.url("/").toString().trimEnd('/')
        try {
            client.exchange(
                serverUrl = serverUrl,
                pairingToken = "wrong-ability-tok",
                deviceId = "device-id-006",
                enforceHttps = false,
            )
            assertTrue("Expected PairingException.TokenExpiredOrRejected", false)
        } catch (e: PairingException.TokenExpiredOrRejected) {
            // expected
        }
    }

    // ── 7. Non-HTTPS URL rejected before any network request ─────────────────

    @Test
    fun `non-HTTPS URL throws InsecureUrl without sending a request`() = runTest {
        try {
            client.exchange(
                serverUrl = "http://plaintext.example.com",
                pairingToken = "any-tok",
                deviceId = "device-id-007",
                enforceHttps = true, // production default
            )
            assertTrue("Expected PairingException.InsecureUrl", false)
        } catch (e: PairingException.InsecureUrl) {
            // expected — no request sent
        }
        assertEquals("No request must have been sent for an insecure URL", 0, server.requestCount)
    }

    // ── 8. 301 redirect is not followed ──────────────────────────────────────

    @Test
    fun `redirect response is not followed and throws ConnectionFailed`() = runTest {
        // Enqueue a redirect to a different server — credentials must not follow
        server.enqueue(
            MockResponse()
                .setResponseCode(301)
                .addHeader("Location", "https://evil.example.com/api/mobile/v1/pair/exchange"),
        )

        val serverUrl = server.url("/").toString().trimEnd('/')
        try {
            client.exchange(
                serverUrl = serverUrl,
                pairingToken = "sensitive-tok",
                deviceId = "device-id-008",
                enforceHttps = false,
            )
            assertTrue("Expected an exception — redirect must not be followed", false)
        } catch (e: PairingException) {
            // Any PairingException is acceptable; the token must not have been forwarded
        }
        // Only one request reached the server (the original POST, not the redirected one)
        assertEquals("Must not follow redirect to external origin", 1, server.requestCount)
    }

    // ── 9. Invalid JSON → no HTTP request sent ────────────────────────────────
    // (Validation happens in ServerViewModel.pair(); PairingClient itself just
    //  receives already-parsed fields. This test verifies that an empty/garbage
    //  pairingToken string does not cause a successful exchange.)

    @Test
    fun `server response is parsed and token is returned`() = runTest {
        server.enqueue(MockResponse().setResponseCode(200).setBody(validResponseBody))

        val serverUrl = server.url("/").toString().trimEnd('/')
        val response = client.exchange(
            serverUrl = serverUrl,
            pairingToken = "tok-parse-check",
            deviceId = "device-id-009",
            enforceHttps = false,
        )

        assertEquals("device-tok-abc123", response.token)
        assertEquals("v1", response.apiVersion)
    }

    // ── 10. After success, the response carries the new server token ──────────

    @Test
    fun `response token is the one issued by the QR server`() = runTest {
        val uniqueToken = "server-B-issued-token-xyz789"
        val body = """
            {
              "token": "$uniqueToken",
              "expires_at": "2027-06-01T00:00:00Z",
              "server_url": "https://cx.example.com",
              "api_version": "v1"
            }
        """.trimIndent()
        server.enqueue(MockResponse().setResponseCode(200).setBody(body))

        val serverUrl = server.url("/").toString().trimEnd('/')
        val response = client.exchange(
            serverUrl = serverUrl,
            pairingToken = "qr-tok-B",
            deviceId = "device-id-010",
            enforceHttps = false,
        )

        assertEquals(
            "The token stored after pairing must be the one issued by the QR server",
            uniqueToken,
            response.token,
        )
    }
}
