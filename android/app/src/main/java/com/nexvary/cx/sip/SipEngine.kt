package com.nexvary.cx.sip

import kotlinx.coroutines.flow.StateFlow
import com.nexvary.cx.data.SipCredentials

data class SipConfig(
    val serverUrl: String,
    val extension: String,
    val password: String,
    val port: Int = 5061,
)

fun SipCredentials.toSipConfig(): SipConfig? {
    if (extension.isBlank() || sipPassword.isEmpty()) return null
    val host = sipHost.ifBlank { runCatching { java.net.URI(serverUrl).host }.getOrNull().orEmpty() }
    if (host.isBlank()) return null
    return SipConfig(host, extension, sipPassword, sipPort)
}

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
    /** Clear all accounts and authentication state without touching call state. */
    fun reset()
    fun makeCall(number: String, hideNumber: Boolean = false)
    fun answer()
    fun hangUp()
    fun mute(muted: Boolean)
    fun setSpeaker(enabled: Boolean)
}
