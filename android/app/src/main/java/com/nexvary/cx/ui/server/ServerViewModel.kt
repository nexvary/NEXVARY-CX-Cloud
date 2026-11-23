package com.nexvary.cx.ui.server

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.nexvary.cx.data.CredentialRepository
import com.nexvary.cx.data.MobileApiService
import com.nexvary.cx.data.PairingClient
import com.nexvary.cx.data.PairingErrors
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
     * SIP credentials (extension + password) are server-specific and are only
     * carried forward when re-pairing with the **same server URL**. A different
     * server URL always starts with empty SIP credentials — the user must supply
     * SIP settings for the new account separately.
     *
     * On failure the existing credentials and SIP configuration are left untouched.
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

                // ── SIP credential isolation ──────────────────────────────────────
                // Carry SIP credentials only when pairing the same server. A
                // matching hostname alone is not enough — the SIP account may differ.
                val isSameServer = existing?.serverUrl != null &&
                    normalizeUrl(existing.serverUrl) == normalizeUrl(serverUrl)
                val newCreds = SipCredentials(
                    serverUrl = serverUrl,
                    extension  = if (isSameServer) existing?.extension  ?: "" else "",
                    sipPassword = if (isSameServer) existing?.sipPassword ?: "" else "",
                    deviceToken = response.token,
                )
                credentialStore.save(newCreds)

                // ── SIP state reset ───────────────────────────────────────────────
                // When switching to a different server, explicitly clear the stale
                // Linphone account so the UI shows unregistered immediately — the
                // old registration is no longer valid.
                if (!isSameServer) sipEngine.reset()

                // ── SIP registration ──────────────────────────────────────────────
                // Only configure Linphone when we actually have SIP credentials for
                // this server. A fresh cross-server pairing leaves extension empty;
                // the user will supply SIP settings via the manual setup flow.
                if (newCreds.extension.isNotEmpty() && newCreds.sipPassword.isNotEmpty()) {
                    try {
                        sipEngine.configure(
                            SipConfig(
                                serverUrl = serverUrl,
                                extension = newCreds.extension,
                                password  = newCreds.sipPassword,
                            )
                        )
                    } catch (e: Exception) {
                        // API pairing succeeded — report SIP failure separately so the
                        // UI shows the correct paired-but-sip-error state.
                        _serverState.value = ServerUiState.Error(PairingErrors.SIP_CONFIG_ERROR)
                        return@launch
                    }
                }
                refresh()

            } catch (e: PairingException.MalformedPayload) {
                _serverState.value = ServerUiState.Error(PairingErrors.MALFORMED_QR)
            } catch (e: PairingException.InsecureUrl) {
                _serverState.value = ServerUiState.Error(PairingErrors.HTTPS_REQUIRED)
            } catch (e: PairingException.TokenExpiredOrRejected) {
                _serverState.value = ServerUiState.Error(PairingErrors.TOKEN_EXPIRED)
            } catch (e: PairingException.ConnectionFailed) {
                _serverState.value = ServerUiState.Error(PairingErrors.CONNECTION_FAILED)
            } catch (e: Exception) {
                _serverState.value = ServerUiState.Error(PairingErrors.UNEXPECTED)
            }
        }
    }

    private fun normalizeUrl(url: String): String = url.trimEnd('/').lowercase()
}
