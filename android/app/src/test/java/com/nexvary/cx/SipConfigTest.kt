package com.nexvary.cx

import com.nexvary.cx.sip.SipConfig
import com.nexvary.cx.sip.SipState
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class SipConfigTest {

    @Test
    fun `SipConfig stores extension correctly`() {
        val config = SipConfig(
            serverUrl = "https://cx.example.com",
            extension = "1001",
            password = "secret",
        )
        assertEquals("1001", config.extension)
    }

    @Test
    fun `SipConfig default port is 5061`() {
        val config = SipConfig(serverUrl = "https://cx.example.com", extension = "1001", password = "pw")
        assertEquals(5061, config.port)
    }

    @Test
    fun `SipConfig custom port is preserved`() {
        val config = SipConfig(serverUrl = "https://cx.example.com", extension = "1001", password = "pw", port = 5080)
        assertEquals(5080, config.port)
    }

    @Test
    fun `SipState default is not registered and not in call`() {
        val state = SipState()
        assertFalse(state.registered)
        assertFalse(state.inCall)
        assertEquals("", state.callNumber)
        assertEquals(0L, state.durationSeconds)
    }

    @Test
    fun `SipState copy preserves unmodified fields`() {
        val base = SipState(registered = true, extension = "1002")
        val updated = base.copy(inCall = true, callNumber = "1003")
        assertTrue(updated.registered)
        assertEquals("1002", updated.extension)
        assertEquals("1003", updated.callNumber)
    }

    @Test
    fun `SipState hideMyNumber defaults to false`() {
        val state = SipState()
        assertFalse(state.hideMyNumber)
    }

    @Test
    fun `SipConfig serverUrl trailing slash is preserved`() {
        val config = SipConfig(serverUrl = "https://cx.example.com/", extension = "1001", password = "pw")
        assertEquals("https://cx.example.com/", config.serverUrl)
    }
}
