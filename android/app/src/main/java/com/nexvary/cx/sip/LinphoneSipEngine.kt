package com.nexvary.cx.sip

import android.content.Context
import android.content.Intent
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import org.linphone.core.Account
import org.linphone.core.Call
import org.linphone.core.Core
import org.linphone.core.CoreListenerStub
import org.linphone.core.Factory
import org.linphone.core.GlobalState
import org.linphone.core.LogCollectionState
import org.linphone.core.MediaEncryption
import org.linphone.core.Privacy
import org.linphone.core.RegistrationState
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class LinphoneSipEngine @Inject constructor(
    @ApplicationContext private val context: Context,
) : SipEngine {

    private val _state = MutableStateFlow(SipState())
    override val state: StateFlow<SipState> = _state.asStateFlow()

    private var core: Core? = null
    private var currentCall: Call? = null

    private val coreListener = object : CoreListenerStub() {
        override fun onAccountRegistrationStateChanged(
            core: Core,
            account: Account,
            state: RegistrationState?,
            message: String,
        ) {
            _state.value = _state.value.copy(
                registered = state == RegistrationState.Ok,
            )
        }

        override fun onCallStateChanged(core: Core, call: Call, state: Call.State?, message: String) {
            when (state) {
                Call.State.Connected, Call.State.StreamsRunning -> {
                    currentCall = call
                    _state.value = _state.value.copy(
                        inCall = true,
                        callNumber = call.remoteAddress?.username ?: "",
                    )
                    startService()
                }
                Call.State.End, Call.State.Released, Call.State.Error -> {
                    currentCall = null
                    _state.value = _state.value.copy(
                        inCall = false,
                        callNumber = "",
                        durationSeconds = 0L,
                        muted = false,
                        speakerOn = false,
                    )
                    stopService()
                }
                Call.State.IncomingReceived -> {
                    currentCall = call
                    _state.value = _state.value.copy(
                        inCall = true,
                        callNumber = call.remoteAddress?.username ?: "",
                    )
                    startService()
                }
                else -> Unit
            }
        }
    }

    private fun ensureCore(): Core {
        return core ?: run {
            val factory = Factory.instance()
            factory.enableLogCollection(LogCollectionState.Disabled)
            val c = factory.createCore(null, null, context)
            c.addListener(coreListener)
            core = c
            c
        }
    }

    override fun configure(config: SipConfig) {
        val c = ensureCore()
        c.clearAccounts()
        c.clearAllAuthInfo()

        val factory = Factory.instance()
        val host = extractHost(config.serverUrl)

        val identity = factory.createAddress("sip:${config.extension}@$host")
        val serverAddr = factory.createAddress("sip:$host:${config.port};transport=tls")

        val authInfo = factory.createAuthInfo(
            config.extension, null, config.password, null, null, host,
        )
        c.addAuthInfo(authInfo)

        val params = c.createAccountParams()
        params.setIdentityAddress(identity)
        params.setServerAddress(serverAddr)
        params.setRegisterEnabled(true)
        params.setPublishEnabled(false)

        val account = c.createAccount(params)
        c.addAccount(account)
        c.setDefaultAccount(account)

        // Enforce SRTP at the core level — calls that cannot negotiate SRTP are rejected
        c.setMediaEncryption(MediaEncryption.SRTP)
        c.setMediaEncryptionMandatory(true)

        if (c.globalState != GlobalState.On) c.start()
    }

    override fun makeCall(number: String, hideNumber: Boolean) {
        val c = core ?: return
        val address = c.interpretUrl(number) ?: return
        val params = c.createCallParams(null) ?: return
        params.mediaEncryption = MediaEncryption.SRTP
        if (hideNumber) params.privacy = Privacy.Id.toInt()
        _state.value = _state.value.copy(hideMyNumber = hideNumber)
        c.inviteAddressWithParams(address, params)
    }

    override fun answer() {
        val call = currentCall ?: return
        val params = core?.createCallParams(call) ?: return
        params.mediaEncryption = MediaEncryption.SRTP
        call.acceptWithParams(params)
    }

    override fun hangUp() {
        currentCall?.terminate()
        core?.terminateAllCalls()
    }

    override fun mute(muted: Boolean) {
        core?.isMicEnabled = !muted
        _state.value = _state.value.copy(muted = muted)
    }

    override fun setSpeaker(enabled: Boolean) {
        currentCall?.speakerMuted = !enabled
        _state.value = _state.value.copy(speakerOn = enabled)
    }

    private fun extractHost(serverUrl: String): String =
        serverUrl.removePrefix("https://").removePrefix("http://").trimEnd('/')

    private fun startService() {
        val intent = Intent(context, SipCallService::class.java)
            .setAction(SipCallService.ACTION_START)
        context.startForegroundService(intent)
    }

    private fun stopService() {
        val intent = Intent(context, SipCallService::class.java)
            .setAction(SipCallService.ACTION_STOP)
        context.startService(intent)
    }
}
