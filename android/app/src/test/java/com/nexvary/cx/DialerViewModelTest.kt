package com.nexvary.cx

import com.nexvary.cx.sip.SipConfig
import com.nexvary.cx.sip.SipEngine
import com.nexvary.cx.sip.SipState
import com.nexvary.cx.ui.dialer.DialerViewModel
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.test.StandardTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/** Fake SipEngine — no Android or linphone dependencies */
class FakeSipEngine : SipEngine {
    private val _state = MutableStateFlow(SipState())
    override val state: StateFlow<SipState> = _state.asStateFlow()

    var lastCalledNumber: String? = null
    var lastHideNumber: Boolean = false
    var configured = false

    override fun configure(config: SipConfig) { configured = true }
    override fun makeCall(number: String, hideNumber: Boolean) {
        lastCalledNumber = number
        lastHideNumber = hideNumber
        _state.value = _state.value.copy(inCall = true, callNumber = number)
    }
    override fun answer() {}
    override fun hangUp() { _state.value = _state.value.copy(inCall = false, callNumber = "") }
    override fun mute(muted: Boolean) { _state.value = _state.value.copy(muted = muted) }
    override fun setSpeaker(enabled: Boolean) { _state.value = _state.value.copy(speakerOn = enabled) }
}

@OptIn(ExperimentalCoroutinesApi::class)
class DialerViewModelTest {

    private val testDispatcher = StandardTestDispatcher()
    private lateinit var fakeSip: FakeSipEngine
    private lateinit var viewModel: DialerViewModel

    @Before
    fun setUp() {
        Dispatchers.setMain(testDispatcher)
        fakeSip = FakeSipEngine()
        viewModel = DialerViewModel(fakeSip)
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    @Test
    fun `empty number does not trigger dial and sets error message`() {
        val result = viewModel.dial()
        assertFalse("dial() should return false for empty number", result)
        assertNotNull("Error message should be set", viewModel.uiState.value.errorMessage)
        assertNull("No call should be placed", fakeSip.lastCalledNumber)
    }

    @Test
    fun `appendDigit builds number correctly`() {
        viewModel.appendDigit('1')
        viewModel.appendDigit('0')
        viewModel.appendDigit('0')
        viewModel.appendDigit('2')
        assertEquals("1002", viewModel.uiState.value.dialedNumber)
    }

    @Test
    fun `toggleHideNumber flips the boolean`() {
        assertFalse(viewModel.uiState.value.hideMyNumber)
        viewModel.toggleHideNumber()
        assertTrue(viewModel.uiState.value.hideMyNumber)
        viewModel.toggleHideNumber()
        assertFalse(viewModel.uiState.value.hideMyNumber)
    }

    @Test
    fun `backspace removes last character`() {
        viewModel.appendDigit('9')
        viewModel.appendDigit('7')
        viewModel.appendDigit('2')
        viewModel.backspace()
        assertEquals("97", viewModel.uiState.value.dialedNumber)
    }

    @Test
    fun `backspace on empty number is a no-op`() {
        viewModel.backspace()
        assertEquals("", viewModel.uiState.value.dialedNumber)
    }

    @Test
    fun `dial with number calls sipEngine and returns true`() {
        viewModel.appendDigit('1')
        viewModel.appendDigit('0')
        viewModel.appendDigit('0')
        viewModel.appendDigit('2')
        val result = viewModel.dial()
        assertTrue("dial() should return true for non-empty number", result)
        assertEquals("1002", fakeSip.lastCalledNumber)
    }

    @Test
    fun `dial passes hideMyNumber flag to sipEngine`() {
        viewModel.appendDigit('1')
        viewModel.appendDigit('0')
        viewModel.appendDigit('0')
        viewModel.appendDigit('1')
        viewModel.toggleHideNumber()
        viewModel.dial()
        assertTrue("hideNumber flag should be true", fakeSip.lastHideNumber)
    }

    @Test
    fun `appendDigit clears error message`() {
        viewModel.dial() // sets error
        assertNotNull(viewModel.uiState.value.errorMessage)
        viewModel.appendDigit('5')
        assertNull("Error should clear on input", viewModel.uiState.value.errorMessage)
    }
}
