package com.nexvary.cx.ui.server

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.nexvary.cx.data.CredentialRepository
import com.nexvary.cx.data.MobileApiService
import com.nexvary.cx.data.PairingClient
import com.nexvary.cx.data.PairingException
import com.nexvary.cx.data.SipCredentials
import com.nexvary.cx.data.model.ServerStatus
import com.nexvary.cx.sip.SipConfig
import com.nexvary.cx.sip.SipEngine
import com.nexvary.cx.sip.SipState
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import java.util.UUID
import javax.inject.Inject

sealed class ServerUiState {
    object Loading : ServerUiState()
    data class Success(val status: ServerStatus) : ServerUiState()
    data class Error(val message: String) : ServerUiState()
    object NotConfigured : ServerUiState()
}

@HiltViewModel
class ServerViewModel @Inject constructor(
    private val apiService: MobileApiService,
    private val credentialStore: CredentialRepository,
    private val sipEngine: SipEngine,
    private val pairingClient: PairingClient,
    private val json: Json,
) : ViewModel() {

    private val _serverState = MutableStateFlow<ServerUiState>(ServerUiState.NotConfigured)
    val serverState: StateFlow<ServerUiState> = _serverState.asStateFlow()

    val sipState: StateFlow<SipState> = sipEngine.state
    val credentials: StateFlow<SipCredentials?> = credentialStore.credentials

    init {
        if (credentialStore.isConfigured()) refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            _serverState.value = ServerUiState.Loading
            try {
                val status = apiService.serverStatus()
                _serverState.value = ServerUiState.Success(status)
            } catch (e: Exception) {
                _serverState.value = ServerUiState.Error(e.message ?: "Connection failed")
            }
        }
    }

    /**
     * Initiates QR pairing using a dedicated HTTP client that bypasses the
     * global OkHttp interceptor.
     *
     * The global interceptor is correct for authenticated API calls (it rewrites
     * the destination to the saved server and attaches the stored device token).
     * Both are wrong here: the exchange must reach the **QR server** and carry
     * the **QR pairing token**, not the stored device token.
     *
     * On failure the existing credentials are left untouched.
     */
    fun pair(qrPayload: String) {
        viewModelScope.launch {
            _serverState.value = ServerUiState.Loading
            val existing = credentialStore.credentials.value

            try {
                // ── Validate QR payload before any network request ────────────────
                val parsed = try {
                    json.parseToJsonElement(qrPayload).jsonObject
                } catch (e: Exception) {
                    throw PairingException.MalformedPayload("not valid JSON")
                }
                val pairingToken = parsed["pairing_token"]?.jsonPrimitive?.content
                    ?: throw PairingException.MalformedPayload("missing pairing_token")
                val serverUrl = parsed["server_url"]?.jsonPrimitive?.content
                    ?: throw PairingException.MalformedPayload("missing server_url")

                // ── Exchange ──────────────────────────────────────────────────────
                val deviceId = UUID.randomUUID().toString()
                val response = pairingClient.exchange(serverUrl, pairingToken, deviceId)

                // ── Persist only after a successful exchange ──────────────────────
                val newCreds = SipCredentials(
                    serverUrl = serverUrl,
                    extension = existing?.extension ?: "",
                    sipPassword = existing?.sipPassword ?: "",
                    deviceToken = response.token,
                )
                credentialStore.save(newCreds)

                sipEngine.configure(
                    SipConfig(
                        serverUrl = serverUrl,
                        extension = newCreds.extension,
                        password = newCreds.sipPassword,
                    )
                )
                refresh()

            } catch (e: PairingException.MalformedPayload) {
                _serverState.value = ServerUiState.Error(e.message ?: "رمز QR غير صالح")
            } catch (e: PairingException.InsecureUrl) {
                _serverState.value = ServerUiState.Error(e.message ?: "يلزم HTTPS")
            } catch (e: PairingException.TokenExpiredOrRejected) {
                _serverState.value = ServerUiState.Error(e.message ?: "انتهت صلاحية رمز الإقران")
            } catch (e: PairingException.ConnectionFailed) {
                _serverState.value = ServerUiState.Error(e.message ?: "فشل الاتصال")
            } catch (e: Exception) {
                _serverState.value = ServerUiState.Error("فشل الإقران: ${e.message}")
            }
        }
    }
}
