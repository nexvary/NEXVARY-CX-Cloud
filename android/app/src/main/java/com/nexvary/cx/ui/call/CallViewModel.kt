package com.nexvary.cx.ui.call

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.nexvary.cx.sip.SipEngine
import com.nexvary.cx.sip.SipState
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class CallUiState(
    val callNumber: String = "",
    val timerFormatted: String = "00:00:00",
    val muted: Boolean = false,
    val speakerOn: Boolean = false,
    val hideMyNumber: Boolean = false,
    val sipState: SipState = SipState(),
)

@HiltViewModel
class CallViewModel @Inject constructor(
    private val sipEngine: SipEngine,
) : ViewModel() {

    private val _uiState = MutableStateFlow(CallUiState())
    val uiState: StateFlow<CallUiState> = _uiState.asStateFlow()

    private var elapsedSeconds = 0L

    init {
        viewModelScope.launch {
            sipEngine.state.collect { state ->
                _uiState.value = _uiState.value.copy(
                    callNumber = state.callNumber,
                    muted = state.muted,
                    speakerOn = state.speakerOn,
                    hideMyNumber = state.hideMyNumber,
                    sipState = state,
                )
            }
        }
        viewModelScope.launch {
            while (true) {
                delay(1000L)
                if (_uiState.value.sipState.inCall) {
                    elapsedSeconds++
                    _uiState.value = _uiState.value.copy(timerFormatted = formatTimer(elapsedSeconds))
                } else {
                    elapsedSeconds = 0L
                }
            }
        }
    }

    fun hangUp() = sipEngine.hangUp()
    fun toggleMute() = sipEngine.mute(!_uiState.value.muted)
    fun toggleSpeaker() = sipEngine.setSpeaker(!_uiState.value.speakerOn)

    private fun formatTimer(seconds: Long): String {
        val h = seconds / 3600
        val m = (seconds % 3600) / 60
        val s = seconds % 60
        return "%02d:%02d:%02d".format(h, m, s)
    }
}
