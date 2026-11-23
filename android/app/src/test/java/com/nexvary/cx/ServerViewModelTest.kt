package com.nexvary.cx

import com.nexvary.cx.data.CredentialRepository
import com.nexvary.cx.data.PairingClient
import com.nexvary.cx.data.PairingException
import com.nexvary.cx.data.SipCredentials
import com.nexvary.cx.data.MobileApiService
import com.nexvary.cx.data.model.PairExchangeRequest
import com.nexvary.cx.data.model.PairExchangeResponse
import com.nexvary.cx.data.model.PbxInfo
import com.nexvary.cx.data.model.ServerInfo
import com.nexvary.cx.data.model.ServerStatus
import com.nexvary.cx.data.model.ServicesInfo
import com.nexvary.cx.sip.SipConfig
import com.nexvary.cx.sip.SipEngine
import com.nexvary.cx.sip.SipState
import com.nexvary.cx.ui.server.ServerUiState
import com.nexvary.cx.ui.server.ServerViewModel
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import kotlinx.serialization.json.Json
import org.junit.After
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import java.io.IOException

// ── Fakes ─────────────────────────────────────────────────────────────────────

class FakeCredentialRepository(initial: SipCredentials? = null) : CredentialRepository {
    private val _credentials = MutableStateFlow(initial)
    override val credentials: StateFlow<SipCredentials?> = _credentials.asStateFlow()
    override fun isConfigured() = _credentials.value != null
    override fun save(credentials: SipCredentials) { _credentials.value = credentials }
    override fun clear() { _credentials.value = null }
}

class FakeMobileApiService(
    private val exception: Exception? = null,
) : MobileApiService {
    override suspend fun serverStatus(): ServerStatus {
        exception?.let { throw it }
        return ServerStatus(
            server = ServerInfo(ramPercent = 20, diskPercent = 30),
            services = ServicesInfo(postgresql = "up", freeswitch = "up"),
            pbx = PbxInfo(activeCalls = 0, registrations = 0),
            checkedAt = "2026-01-01T00:00:00Z",
        )
    }

    override suspend fun exchangePairingToken(request: PairExchangeRequest): PairExchangeResponse =
        PairExchangeResponse(
            token = "tok-123",
            expiresAt = "2027-01-01T00:00:00Z",
            serverUrl = "https://cx.example.com",
            apiVersion = "v1",
        )
}

/**
 * A test double for [PairingClient]. Subclasses [PairingClient] (which is open)
 * so it matches the type expected by [ServerViewModel] without needing an
 * interface extraction.
 *
 * PairingClientTest covers the real HTTP behavior using MockWebServer.
 */
class FakePairingClient(
    private val response: PairExchangeResponse? = PairExchangeResponse(
        token = "new-device-tok-from-server",
        expiresAt = "2027-01-01T00:00:00Z",
        serverUrl = "https://cx.example.com",
        apiVersion = "v1",
    ),
    private val exception: PairingException? = null,
    private val captureRequest: ((serverUrl: String, pairingToken: String) -> Unit)? = null,
) : PairingClient(Json { ignoreUnknownKeys = true }) {

    override suspend fun exchange(
        serverUrl: String,
        pairingToken: String,
        deviceId: String,
        enforceHttps: Boolean,
    ): PairExchangeResponse {
        captureRequest?.invoke(serverUrl, pairingToken)
        exception?.let { throw it }
        return response ?: throw PairingException.ConnectionFailed(RuntimeException("no response"))
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

@OptIn(ExperimentalCoroutinesApi::class)
class ServerViewModelTest {

    private val testDispatcher = StandardTestDispatcher()
    private val testJson = Json { ignoreUnknownKeys = true; isLenient = true }

    @Before
    fun setUp() { Dispatchers.setMain(testDispatcher) }

    @After
    fun tearDown() { Dispatchers.resetMain() }

    private fun makeVm(
        apiService: MobileApiService = FakeMobileApiService(),
        credentialStore: CredentialRepository = FakeCredentialRepository(),
        sipEngine: SipEngine = NoOpSipEngine(),
        pairingClient: PairingClient = FakePairingClient(),
    ) = ServerViewModel(
        apiService = apiService,
        credentialStore = credentialStore,
        sipEngine = sipEngine,
        pairingClient = pairingClient,
        json = testJson,
    )

    @Test
    fun `Server tab does not crash when no credentials are stored`() {
        // Regression: previously, Retrofit.Builder().baseUrl("null/") was called
        // when no server was configured, causing an IllegalArgumentException on
        // first navigation to the Server screen.
        val vm = makeVm(credentialStore = FakeCredentialRepository(null))
        assertTrue(
            "Expected NotConfigured state on fresh launch",
            vm.serverState.value is ServerUiState.NotConfigured,
        )
    }

    @Test
    fun `credentials flow is null when not configured`() {
        val vm = makeVm(credentialStore = FakeCredentialRepository(null))
        assertNull("No credentials should be present before pairing", vm.credentials.value)
    }

    @Test
    fun `SIP state is not registered on fresh launch`() {
        val vm = makeVm(credentialStore = FakeCredentialRepository(null))
        assertTrue("SIP should be unregistered before configuration", !vm.sipState.value.registered)
    }

    @Test
    fun `refresh sets Error state when API throws`() = runTest {
        val vm = makeVm(
            apiService = FakeMobileApiService(exception = IOException("unreachable")),
            credentialStore = FakeCredentialRepository(
                SipCredentials(
                    serverUrl = "https://cx.example.com",
                    extension = "1001",
                    sipPassword = "pw",
                    deviceToken = "tok",
                )
            ),
        )
        // init triggers refresh() because isConfigured() == true
        advanceUntilIdle()
        assertTrue(
            "Expected Error state after failed API call",
            vm.serverState.value is ServerUiState.Error,
        )
    }

    @Test
    fun `refresh sets Success state when API succeeds`() = runTest {
        val vm = makeVm(
            credentialStore = FakeCredentialRepository(
                SipCredentials(
                    serverUrl = "https://cx.example.com",
                    extension = "1001",
                    sipPassword = "pw",
                    deviceToken = "tok",
                )
            ),
        )
        advanceUntilIdle()
        assertTrue(
            "Expected Success state after successful API call",
            vm.serverState.value is ServerUiState.Success,
        )
    }

    // ── Re-pairing / server-address change ───────────────────────────────────

    @Test
    fun `re-pairing to a new server does not send old device token to the new server`() = runTest {
        var capturedPairingToken: String? = null
        var capturedServerUrl: String? = null

        val pairingClient = FakePairingClient(captureRequest = { url, token ->
            capturedServerUrl = url
            capturedPairingToken = token
        })

        val store = FakeCredentialRepository(
            SipCredentials(
                serverUrl = "https://old.example.com",
                extension = "1001",
                sipPassword = "s3cret",
                deviceToken = "old-device-secret-token",
            )
        )

        val vm = makeVm(credentialStore = store, pairingClient = pairingClient)

        val qrPayload = """{"pairing_token":"qr-tok-999","server_url":"https://new.example.com"}"""
        vm.pair(qrPayload)
        advanceUntilIdle()

        // The PairingClient must receive the QR token, not the old device token.
        assertTrue("PairingClient must have been called", capturedPairingToken != null)
        assertTrue(
            "PairingClient must receive the QR pairing token, not the old device token",
            capturedPairingToken == "qr-tok-999",
        )
        assertTrue(
            "PairingClient must target the QR server, not the old server",
            capturedServerUrl == "https://new.example.com",
        )
        // After success, stored token must come from the new server.
        val saved = store.credentials.value
        assertTrue("Stored serverUrl must be new server", saved?.serverUrl == "https://new.example.com")
        assertTrue("Stored token must be from new server", saved?.deviceToken == "new-device-tok-from-server")
    }

    @Test
    fun `malformed QR payload sets Error state without calling PairingClient`() = runTest {
        var pairingClientCalled = false
        val pairingClient = FakePairingClient(captureRequest = { _, _ -> pairingClientCalled = true })

        val vm = makeVm(
            credentialStore = FakeCredentialRepository(null),
            pairingClient = pairingClient,
        )

        vm.pair("not-valid-json!!")
        advanceUntilIdle()

        assertTrue("State must be Error for malformed QR", vm.serverState.value is ServerUiState.Error)
        assertTrue("PairingClient must not be called for invalid QR", !pairingClientCalled)
    }

    @Test
    fun `failed pairing leaves existing credentials intact`() = runTest {
        val originalCreds = SipCredentials(
            serverUrl = "https://old.example.com",
            extension = "1001",
            sipPassword = "pw",
            deviceToken = "original-device-tok",
        )
        val store = FakeCredentialRepository(originalCreds)
        val vm = makeVm(
            credentialStore = store,
            pairingClient = FakePairingClient(
                response = null,
                exception = PairingException.TokenExpiredOrRejected(),
            ),
        )

        val qrPayload = """{"pairing_token":"expired-tok","server_url":"https://new.example.com"}"""
        vm.pair(qrPayload)
        advanceUntilIdle()

        // Error state shown to user
        assertTrue("State must be Error", vm.serverState.value is ServerUiState.Error)
        // Original credentials untouched
        assertTrue("Old credentials must survive a failed re-pair",
            store.credentials.value?.deviceToken == "original-device-tok")
        assertTrue("Old server must survive a failed re-pair",
            store.credentials.value?.serverUrl == "https://old.example.com")
    }

    @Test
    fun `Server screen shows NotConfigured state on first launch`() {
        // Regression guard: ensure the ViewModel starts in NotConfigured (not
        // Loading or Error) so the screen can render without attempting any
        // network call.
        val vm = makeVm(credentialStore = FakeCredentialRepository(null))
        assertTrue(
            "Screen must render as NotConfigured before any server is paired",
            vm.serverState.value is ServerUiState.NotConfigured,
        )
    }
}

// Minimal SipEngine that never touches Android or Linphone internals
private class NoOpSipEngine : SipEngine {
    private val _state = MutableStateFlow(SipState())
    override val state: StateFlow<SipState> = _state.asStateFlow()
    override fun configure(config: SipConfig) {}
    override fun makeCall(number: String, hideNumber: Boolean) {}
    override fun answer() {}
    override fun hangUp() {}
    override fun mute(muted: Boolean) {}
    override fun setSpeaker(enabled: Boolean) {}
}
