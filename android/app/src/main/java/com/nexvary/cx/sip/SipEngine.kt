package com.nexvary.cx.sip

import kotlinx.coroutines.flow.StateFlow

data class SipConfig(
    val serverUrl: String,
    val extension: String,
    val password: String,
    val port: Int = 5061,
)

data class SipState(
    val registered: Boolean = false,
    val extension: String = "",
    val inCall: Boolean = false,
    val callNumber: String = "",
    val durationSeconds: Long = 0L,
    val muted: Boolean = false,
    val speakerOn: Boolean = false,
    val hideMyNumber: Boolean = false,
)

interface SipEngine {
    val state: StateFlow<SipState>
    fun configure(config: SipConfig)
    fun makeCall(number: String, hideNumber: Boolean = false)
    fun answer()
    fun hangUp()
    fun mute(muted: Boolean)
    fun setSpeaker(enabled: Boolean)
}
