package com.nexvary.cx

import com.nexvary.cx.data.CredentialRepository
import com.nexvary.cx.data.MobileApiService
import com.nexvary.cx.data.PairingClient
import com.nexvary.cx.data.PairingException
import com.nexvary.cx.data.SipCredentials
import com.nexvary.cx.di.NetworkModule
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.Json
import okhttp3.OkHttpClient
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import okhttp3.tls.HandshakeCertificates
import okhttp3.tls.HeldCertificate
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * Integration tests for the full pairing → authenticated API request pipeline.
 *
 * Uses the **real** [PairingClient] and the same authenticated OkHttp interceptor
 * logic used by [AppModule] — both wired to local HTTPS MockWebServers with a
 * test-only certificate. All credentials are fake.
 *
 * These tests verify that:
 * 1. After pairing server B, the next authenticated status request reaches B
 *    with **only** B's newly issued device token, not any old token or origin.
 * 2. A rejected (401/403) exchange leaves the credential store unchanged so
 *    the original server and token remain active.
 * 3. A redirect target receives zero requests.
 */
class PairingIntegrationTest {

    private lateinit var serverA: MockWebServer
    private lateinit var serverB: MockWebServer
    private lateinit var heldCert: HeldCertificate
    private lateinit var clientHandshake: HandshakeCertificates
    private val json = Json { ignoreUnknownKeys = true; isLenient = true }

    // Minimal valid status response body matching ServerStatus
    private val statusBody = """
        {
          "server":   {"ram_percent": 20, "disk_percent": 30},
          "services": {"postgresql": "up", "freeswitch": "up"},
          "pbx":      {"active_calls": 0, "registrations": 0},
          "checked_at": "2026-01-01T00:00:00Z"
        }
    """.trimIndent()

    @Before
    fun setUp() {
        // Both servers on localhost — one cert with localhost SAN covers both ports.
        heldCert = HeldCertificate.Builder().addSubjectAlternativeName("localhost").build()
        val serverHandshake = HandshakeCertificates.Builder().heldCertificate(heldCert).build()
        clientHandshake    = HandshakeCertificates.Builder().addTrustedCertificate(heldCert.certificate).build()

        serverA = MockWebServer()
        serverA.useHttps(serverHandshake.sslSocketFactory(), false)
        serverA.start()

        serverB = MockWebServer()
        serverB.useHttps(serverHandshake.sslSocketFactory(), false)
        serverB.start()
    }

    @After
    fun tearDown() {
        serverA.shutdown()
        serverB.shutdown()
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** PairingClient that trusts the test certificate. */
    private fun buildPairingClient(): PairingClient = object : PairingClient(json) {
        override fun buildHttpClient(): OkHttpClient.Builder =
            super.buildHttpClient()
                .sslSocketFactory(clientHandshake.sslSocketFactory(), clientHandshake.trustManager)
    }

    /**
     * Builds an authenticated API client using the **production** [NetworkModule.provideOkHttp]
     * interceptor so that any regression in the real credential-rewriting or token-attachment
     * logic immediately breaks this test.
     *
     * The only test-specific addition is an SSL socket factory that trusts the local
     * MockWebServer certificate; it does not alter the interceptor chain.
     */
    private fun buildApiClient(credStore: CredentialRepository): MobileApiService {
        // Production client — same interceptor that runs in the real app.
        val productionClient: OkHttpClient = NetworkModule.provideOkHttp(credStore)
        // Overlay test SSL trust without touching any interceptor.
        val testClient = productionClient.newBuilder()
            .sslSocketFactory(clientHandshake.sslSocketFactory(), clientHandshake.trustManager)
            .build()
        val retrofit = NetworkModule.provideRetrofit(testClient, json)
        return NetworkModule.provideMobileApiService(retrofit)
    }

    private fun exchangeBody(token: String): String = """
        {
          "token": "$token",
          "expires_at": "2027-01-01T00:00:00Z",
          "server_url": "https://cx.example.com",
          "api_version": "v1"
        }
    """.trimIndent()

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * After pairing server B:
     * - serverB receives the exchange request at the correct path
     * - The credential store is updated with B's URL and new device token
     * - The next authenticated status request reaches B, with **only** B's token
     * - serverA receives zero requests throughout
     */
    @Test
    fun `after pairing B the next status request reaches B with the new device token`() = runTest {
        val serverAUrl = serverA.url("/").toString().trimEnd('/')
        val credStore  = FakeCredentialRepository(
            SipCredentials(serverUrl = serverAUrl, extension = "", sipPassword = "", deviceToken = "old-tok-A")
        )
        val pairingClient = buildPairingClient()
        val apiClient     = buildApiClient(credStore)

        val serverBUrl = serverB.url("/").toString().trimEnd('/')
        serverB.enqueue(MockResponse().setResponseCode(200).setBody(exchangeBody("new-tok-B")))
        serverB.enqueue(MockResponse().setResponseCode(200).setBody(statusBody))

        // 1. Exchange with server B
        val exchangeResp = pairingClient.exchange(
            serverUrl    = serverBUrl,
            pairingToken = "qr-pairing-tok-integration",
            deviceId     = "device-integration-001",
        )
        // 2. Update the credential store (mirrors ServerViewModel.pair())
        credStore.save(
            SipCredentials(serverUrl = serverBUrl, extension = "", sipPassword = "", deviceToken = exchangeResp.token)
        )

        // 3. Authenticated status request via the real API client
        apiClient.serverStatus()

        // serverB must have received both the exchange and the status request
        assertEquals("serverB must receive exactly 2 requests (exchange + status)", 2, serverB.requestCount)

        val exchangeReq = serverB.takeRequest()
        assertTrue("First request must be the exchange", exchangeReq.path?.contains("pair/exchange") == true)

        val statusReq = serverB.takeRequest()
        assertTrue("Second request must be the status", statusReq.path?.contains("status") == true)
        assertEquals(
            "Status request must carry only the new device token",
            "Bearer new-tok-B",
            statusReq.getHeader("Authorization"),
        )

        // serverA must have received zero requests
        assertEquals("serverA must receive no requests throughout", 0, serverA.requestCount)
    }

    /**
     * When the exchange returns 401 (rejected token), the PairingClient throws
     * [PairingException.TokenExpiredOrRejected] before the credential store is
     * touched. The original pairing and device token remain active.
     */
    @Test
    fun `rejected exchange does not alter the credential store`() = runTest {
        val serverAUrl = serverA.url("/").toString().trimEnd('/')
        val credStore  = FakeCredentialRepository(
            SipCredentials(serverUrl = serverAUrl, extension = "1001", sipPassword = "pw", deviceToken = "tok-A")
        )
        val pairingClient = buildPairingClient()

        serverB.enqueue(MockResponse().setResponseCode(401).setBody("""{"error":"Unauthenticated"}"""))

        val serverBUrl  = serverB.url("/").toString().trimEnd('/')
        var exchangeOk  = false
        try {
            val resp = pairingClient.exchange(serverUrl = serverBUrl, pairingToken = "expired-tok", deviceId = "device-integration-002")
            // Only save if exchange succeeds (mirrors ServerViewModel.pair())
            credStore.save(SipCredentials(serverUrl = serverBUrl, extension = "", sipPassword = "", deviceToken = resp.token))
            exchangeOk = true
        } catch (e: PairingException.TokenExpiredOrRejected) {
            // expected
        }

        assertFalse("Exchange must have failed with 401", exchangeOk)
        val saved = credStore.credentials.value
        assertEquals("serverUrl must remain serverA", serverAUrl, saved?.serverUrl)
        assertEquals("deviceToken must remain tok-A", "tok-A",   saved?.deviceToken)

        assertEquals("serverB receives exactly one request (the failed exchange)", 1, serverB.requestCount)
        assertEquals("serverA receives no requests", 0, serverA.requestCount)
    }

    /**
     * When the exchange returns a 301 redirect, [PairingClient] does not follow
     * it. The redirect target receives zero requests and the credential store
     * is left unchanged.
     */
    @Test
    fun `redirect target receives zero requests and credential store is unchanged`() = runTest {
        val serverAUrl = serverA.url("/").toString().trimEnd('/')
        val credStore  = FakeCredentialRepository(
            SipCredentials(serverUrl = serverAUrl, extension = "", sipPassword = "", deviceToken = "tok-A")
        )
        val pairingClient = buildPairingClient()

        // serverB returns a redirect pointing to serverA (simulating an SSRF-style redirect)
        val serverBUrl = serverB.url("/").toString().trimEnd('/')
        serverB.enqueue(
            MockResponse()
                .setResponseCode(301)
                .addHeader("Location", "$serverAUrl/api/mobile/v1/pair/exchange"),
        )

        var exchangeOk = false
        try {
            val resp = pairingClient.exchange(serverUrl = serverBUrl, pairingToken = "redirect-tok", deviceId = "device-integration-003")
            credStore.save(SipCredentials(serverUrl = serverBUrl, extension = "", sipPassword = "", deviceToken = resp.token))
            exchangeOk = true
        } catch (e: PairingException) {
            // expected — redirect not followed
        }

        assertFalse("Exchange must have failed — redirect not followed", exchangeOk)
        assertEquals("Credential store must retain original token",    "tok-A", credStore.credentials.value?.deviceToken)
        // The redirect points to serverA — it must receive zero requests
        assertEquals("Redirect target (serverA) must receive zero requests", 0, serverA.requestCount)
        assertEquals("serverB receives exactly one request (the redirect response)", 1, serverB.requestCount)
    }
}
