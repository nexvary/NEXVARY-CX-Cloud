package com.nexvary.cx.ui.server

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.nexvary.cx.data.CredentialStore
import com.nexvary.cx.data.MobileApiService
import com.nexvary.cx.data.SipCredentials
import com.nexvary.cx.data.model.PairExchangeRequest
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
    private val credentialStore: CredentialStore,
    private val sipEngine: SipEngine,
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

    fun pair(qrPayload: String) {
        viewModelScope.launch {
            try {
                val parsed = json.parseToJsonElement(qrPayload).jsonObject
                val pairingToken = parsed["pairing_token"]?.jsonPrimitive?.content ?: return@launch
                val serverUrl = parsed["server_url"]?.jsonPrimitive?.content ?: return@launch

                val deviceId = UUID.randomUUID().toString()
                val response = apiService.exchangePairingToken(
                    PairExchangeRequest(deviceName = "NEXVARY CX Android", deviceId = deviceId)
                )

                val existing = credentialStore.credentials.value
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
            } catch (e: Exception) {
                _serverState.value = ServerUiState.Error("Pairing failed: ${e.message}")
            }
        }
    }
}
