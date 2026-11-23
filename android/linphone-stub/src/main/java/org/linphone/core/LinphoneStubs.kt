@file:Suppress("unused", "UNUSED_PARAMETER")
package org.linphone.core

import android.content.Context

// Compile-time stubs for the Linphone SDK.
// The linphone.org Maven repository is unavailable; replace this module with the real
// linphone-sdk-android AAR for any build that must actually place SIP calls.

class Factory private constructor() {
    companion object {
        @JvmStatic fun instance(): Factory = INSTANCE
        private val INSTANCE = Factory()
    }
    fun setLogCollectionEnabled(state: LogCollectionState) {}
    fun createCore(configPath: String?, factoryConfigPath: String?, context: Context): Core = Core()
    fun createAddress(address: String): Address = Address(address)
    fun createAuthInfo(
        username: String, userid: String?, passwd: String?,
        ha1: String?, realm: String?, domain: String,
    ): AuthInfo = AuthInfo()
}

open class Core {
    var defaultAccount: Account? = null
    var isMicEnabled: Boolean = true
    val isStarted: Boolean = false

    fun clearAccounts() {}
    fun clearAllAuthInfo() {}
    fun addAuthInfo(authInfo: AuthInfo) {}
    fun createAccountParams(): AccountParams = AccountParams()
    fun createAccount(params: AccountParams): Account = Account()
    fun addAccount(account: Account) {}
    fun addListener(listener: CoreListenerStub) {}
    fun start() {}
    fun interpretUrl(url: String): Address? = null
    fun createCallParams(call: Call?): CallParams? = CallParams()
    fun inviteAddressWithParams(address: Address, params: CallParams): Call? = null
    fun terminateAllCalls() {}
}

open class Account

open class Call {
    var speakerMuted: Boolean = false
    val remoteAddress: Address? = null

    fun acceptWithParams(params: CallParams) {}
    fun terminate() {}

    enum class State {
        Idle, OutgoingInit, OutgoingProgress, OutgoingRinging, OutgoingEarlyMedia,
        Connected, StreamsRunning, Pausing, Paused, Resuming,
        Referred, Error, End, PausedByRemote, UpdatedByRemote, IncomingReceived,
        EarlyUpdatedByRemote, EarlyUpdating, Released,
    }
}

open class CoreListenerStub {
    open fun onAccountRegistrationStateChanged(
        core: Core, account: Account, state: RegistrationState?, message: String,
    ) {}
    open fun onCallStateChanged(
        core: Core, call: Call, state: Call.State?, message: String,
    ) {}
}

class AccountParams {
    var identityAddress: Address? = null
    var serverAddress: Address? = null
    var registerEnabled: Boolean = false
    var publishEnabled: Boolean = false
}

class CallParams {
    var mediaEncryption: MediaEncryption = MediaEncryption.None
    var isMediaEncryptionMandatory: Boolean = false
    var privacy: Int = 0
}

class Address(val uri: String = "") {
    val username: String? get() = uri.substringAfter("sip:").substringBefore("@").takeIf { it.isNotEmpty() }
}

class AuthInfo

enum class RegistrationState { None, Progress, Ok, Cleared, Failed }

enum class MediaEncryption { None, SRTP, ZRTP, DTLS }

enum class Privacy(private val value: Int) {
    None(0), Id(1), Header(2), Session(4), HideVia(8), Critical(16), Default(32);
    fun toInt(): Int = value
}

enum class LogCollectionState { Disabled, Enabled, EnabledWithoutPreviousLogHandler }

enum class TransportType { Udp, Tcp, Tls, Dtls }
