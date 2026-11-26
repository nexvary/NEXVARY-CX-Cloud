package com.nexvary.cx.ui.server

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.nexvary.cx.data.CredentialRepository
import com.nexvary.cx.data.MobileApiService
import com.nexvary.cx.data.PairingClient
import com.nexvary.cx.data.PairingErrors
import com.nexvary.cx.data.PairingException
import com.nexvary.cx.data.SipCredentials
import com.nexvary.cx.data.StorageUnavailableException
import com.nexvary.cx.data.model.ServerStatus
import com.nexvary.cx.sip.toSipConfig
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
    /** Encrypted credential storage could not initialise. No pairing or save is possible
     *  until a successful cold restart. The user can call [ServerViewModel.clearForRecovery]
     *  to delete the corrupted file and then restart the app. */
    object StorageUnavailable : ServerUiState()
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
        when {
            !credentialStore.isStorageAvailable -> _serverState.value = ServerUiState.StorageUnavailable
            credentialStore.isConfigured() -> refresh()
        }
    }

    /**
     * Delete the encrypted preferences file and direct the user to restart.
     * Only available when [isStorageAvailable] is false (i.e. the keystore is
     * inaccessible). Has no effect and no data is read if storage IS available.
     * [ServerUiState] does not change — the UI should communicate that a cold
     * restart is required before normal operation resumes.
     */
    fun clearForRecovery() {
        credentialStore.clearForRecovery()
    }

    fun saveSipAccount(host: String, port: String, extension: String, password: String): String? {
        val current = credentials.value ?: return "اربط التطبيق بالخادم أولًا"
        if (sipState.value.inCall) return "أنهِ المكالمة قبل تغيير حساب SIP"
        val sipHost = host.trim()
        val sipPort = port.trim().toIntOrNull()
        val number = extension.trim()
        if (!Regex("[A-Za-z0-9](?:[A-Za-z0-9.-]{0,251}[A-Za-z0-9])?").matches(sipHost)) {
            return "أدخل اسم خادم SIP أو عنوان IPv4 دون رابط أو منفذ"
        }
        if (sipPort == null || sipPort !in 1..65535 || !Regex("[0-9]{1,20}").matches(number) || password.isEmpty()) {
            return "أدخل امتدادًا رقميًا وكلمة المرور ومنفذ TLS صالحًا"
        }
        val updated = current.copy(extension = number, sipPassword = password, sipHost = sipHost, sipPort = sipPort)
        return try {
            sipEngine.configure(checkNotNull(updated.toSipConfig()))
            credentialStore.save(updated)
            null
        } catch (_: Exception) {
            runCatching {
                sipEngine.reset()
                current.toSipConfig()?.let(sipEngine::configure)
            }
            PairingErrors.SIP_CONFIG_ERROR
        }
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
     * The pairing contract provides no account identifier, so a matching server
     * URL is not a trusted binding. Every successful exchange clears existing SIP
     * credentials and resets the Linphone engine. The user must configure SIP
     * settings separately after any pairing.
     *
     * On failure the existing credentials and SIP configuration are left untouched.
     */
    fun pair(qrPayload: String) {
        viewModelScope.launch {
            // Guard before any network call: refuse immediately if encrypted storage
            // is unavailable so we do not consume a single-use pairing token that
            // cannot be persisted.
            if (!credentialStore.isStorageAvailable) {
                _serverState.value = ServerUiState.Error(PairingErrors.STORAGE_UNAVAILABLE)
                return@launch
            }
            _serverState.value = ServerUiState.Loading
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
                // The pairing contract (QR + exchange response) contains no account
                // identifier, so URL equality cannot bind the exchange to a specific
                // SIP account. Always clear SIP credentials and reset the engine so a
                // stale registration is never carried into a new pairing session.
                val newCreds = SipCredentials(
                    serverUrl   = serverUrl,
                    extension   = "",
                    sipPassword = "",
                    deviceToken = response.token,
                )
                credentialStore.save(newCreds)
                sipEngine.reset()

                refresh()

            } catch (e: PairingException.MalformedPayload) {
                _serverState.value = ServerUiState.Error(PairingErrors.MALFORMED_QR)
            } catch (e: PairingException.InsecureUrl) {
                _serverState.value = ServerUiState.Error(PairingErrors.HTTPS_REQUIRED)
            } catch (e: PairingException.TokenExpiredOrRejected) {
                _serverState.value = ServerUiState.Error(PairingErrors.TOKEN_EXPIRED)
            } catch (e: PairingException.ConnectionFailed) {
                _serverState.value = ServerUiState.Error(PairingErrors.CONNECTION_FAILED)
            } catch (e: StorageUnavailableException) {
                // save() threw mid-pairing (key invalidated after startup). Exchange
                // consumed the token but nothing was written — no credentials changed.
                _serverState.value = ServerUiState.Error(PairingErrors.STORAGE_UNAVAILABLE)
            } catch (e: Exception) {
                _serverState.value = ServerUiState.Error(PairingErrors.UNEXPECTED)
            }
        }
    }

}
