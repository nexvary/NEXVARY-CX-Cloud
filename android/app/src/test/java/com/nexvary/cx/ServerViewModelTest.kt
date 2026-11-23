package com.nexvary.cx

import com.nexvary.cx.data.CredentialRepository
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

// ── Tests ─────────────────────────────────────────────────────────────────────

@OptIn(ExperimentalCoroutinesApi::class)
class ServerViewModelTest {

    private val testDispatcher = StandardTestDispatcher()
    private val testJson = Json { ignoreUnknownKeys = true; isLenient = true }

    @Before
    fun setUp() { Dispatchers.setMain(testDispatcher) }

    @After
    fun tearDown() { Dispatchers.resetMain() }

    @Test
    fun `Server tab does not crash when no credentials are stored`() {
        // Regression: previously, Retrofit.Builder().baseUrl("null/") was called
        // when no server was configured, causing an IllegalArgumentException on
        // first navigation to the Server screen.
        val vm = ServerViewModel(
            apiService = FakeMobileApiService(),
            credentialStore = FakeCredentialRepository(null),
            sipEngine = NoOpSipEngine(),
            json = testJson,
        )
        assertTrue(
            "Expected NotConfigured state on fresh launch",
            vm.serverState.value is ServerUiState.NotConfigured,
        )
    }

    @Test
    fun `credentials flow is null when not configured`() {
        val vm = ServerViewModel(
            apiService = FakeMobileApiService(),
            credentialStore = FakeCredentialRepository(null),
            sipEngine = NoOpSipEngine(),
            json = testJson,
        )
        assertNull("No credentials should be present before pairing", vm.credentials.value)
    }

    @Test
    fun `SIP state is not registered on fresh launch`() {
        val vm = ServerViewModel(
            apiService = FakeMobileApiService(),
            credentialStore = FakeCredentialRepository(null),
            sipEngine = NoOpSipEngine(),
            json = testJson,
        )
        assertTrue("SIP should be unregistered before configuration", !vm.sipState.value.registered)
    }

    @Test
    fun `refresh sets Error state when API throws`() = runTest {
        val vm = ServerViewModel(
            apiService = FakeMobileApiService(exception = IOException("unreachable")),
            credentialStore = FakeCredentialRepository(
                SipCredentials(
                    serverUrl = "https://cx.example.com",
                    extension = "1001",
                    sipPassword = "pw",
                    deviceToken = "tok",
                )
            ),
            sipEngine = NoOpSipEngine(),
            json = testJson,
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
        val vm = ServerViewModel(
            apiService = FakeMobileApiService(),
            credentialStore = FakeCredentialRepository(
                SipCredentials(
                    serverUrl = "https://cx.example.com",
                    extension = "1001",
                    sipPassword = "pw",
                    deviceToken = "tok",
                )
            ),
            sipEngine = NoOpSipEngine(),
            json = testJson,
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
        // Capture the PairExchangeRequest the ViewModel sends to the API.
        var capturedRequest: PairExchangeRequest? = null
        val capturingApi = object : MobileApiService {
            override suspend fun serverStatus(): ServerStatus =
                FakeMobileApiService().serverStatus()
            override suspend fun exchangePairingToken(request: PairExchangeRequest): PairExchangeResponse {
                capturedRequest = request
                return PairExchangeResponse(
                    token = "new-server-token",
                    expiresAt = "2027-01-01T00:00:00Z",
                    serverUrl = "https://new.example.com",
                    apiVersion = "v1",
                )
            }
        }
        val vm = ServerViewModel(
            apiService = capturingApi,
            credentialStore = FakeCredentialRepository(
                SipCredentials(
                    serverUrl = "https://old.example.com",
                    extension = "1001",
                    sipPassword = "s3cret",
                    deviceToken = "old-device-secret-token",
                )
            ),
            sipEngine = NoOpSipEngine(),
            json = testJson,
        )

        val qrPayload = """{"pairing_token":"qr-tok-999","server_url":"https://new.example.com"}"""
        vm.pair(qrPayload)
        advanceUntilIdle()

        // The exchange request must only carry device identity, never the old token.
        val req = capturedRequest
        assertTrue("exchangePairingToken must have been called", req != null)
        assertTrue(
            "PairExchangeRequest must not contain the old device token",
            !req.toString().contains("old-device-secret-token"),
        )
        // After pairing, the stored token must be the one the new server issued.
        val saved = vm.credentials.value
        assertTrue("Saved serverUrl must be the new server", saved?.serverUrl == "https://new.example.com")
        assertTrue("Saved token must be the one from the new server, not the old one",
            saved?.deviceToken == "new-server-token")
    }

    @Test
    fun `Server screen shows NotConfigured state on first launch`() {
        // Regression guard: ensure the ViewModel starts in NotConfigured (not
        // Loading or Error) so the screen can render without attempting any
        // network call.
        val vm = ServerViewModel(
            apiService = FakeMobileApiService(),
            credentialStore = FakeCredentialRepository(null),
            sipEngine = NoOpSipEngine(),
            json = testJson,
        )
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
