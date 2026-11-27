package com.nexvary.cx.ui.dialer

import androidx.lifecycle.ViewModel
import com.nexvary.cx.sip.SipEngine
import com.nexvary.cx.sip.SipState
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import javax.inject.Inject

data class DialerUiState(
    val dialedNumber: String = "",
    val hideMyNumber: Boolean = false,
    val errorMessage: String? = null,
    val sipState: SipState = SipState(),
)

@HiltViewModel
class DialerViewModel @Inject constructor(
    private val sipEngine: SipEngine,
) : ViewModel() {

    private val _uiState = MutableStateFlow(DialerUiState())
    val uiState: StateFlow<DialerUiState> = _uiState.asStateFlow()

    val sipState: StateFlow<SipState> = sipEngine.state

    fun appendDigit(c: Char) {
        _uiState.value = _uiState.value.copy(
            dialedNumber = _uiState.value.dialedNumber + c,
            errorMessage = null,
        )
    }

    fun backspace() {
        val current = _uiState.value.dialedNumber
        if (current.isNotEmpty()) {
            _uiState.value = _uiState.value.copy(dialedNumber = current.dropLast(1))
        }
    }

    fun toggleHideNumber() {
        _uiState.value = _uiState.value.copy(hideMyNumber = !_uiState.value.hideMyNumber)
    }

    fun dial(): Boolean {
        val number = _uiState.value.dialedNumber.trim()
        if (number.isEmpty()) {
            _uiState.value = _uiState.value.copy(errorMessage = "أدخل رقماً للاتصال")
            return false
        }
        sipEngine.makeCall(number, _uiState.value.hideMyNumber)
        return true
    }
}
