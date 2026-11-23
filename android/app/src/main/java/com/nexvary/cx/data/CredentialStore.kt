package com.nexvary.cx.data

import android.content.Context
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import javax.inject.Inject
import javax.inject.Singleton

data class SipCredentials(
    val serverUrl: String,
    val extension: String,
    val sipPassword: String,
    val deviceToken: String,
)

@Singleton
class CredentialStore @Inject constructor(
    @ApplicationContext private val context: Context,
) {
    private val prefs by lazy {
        val masterKey = MasterKey.Builder(context)
            .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
            .build()
        EncryptedSharedPreferences.create(
            context,
            "nexvary_credentials",
            masterKey,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
        )
    }

    private val _credentials = MutableStateFlow(load())
    val credentials: StateFlow<SipCredentials?> = _credentials.asStateFlow()

    private fun load(): SipCredentials? {
        val serverUrl = prefs.getString(KEY_SERVER_URL, null) ?: return null
        val extension = prefs.getString(KEY_EXTENSION, null) ?: return null
        val sipPassword = prefs.getString(KEY_SIP_PASSWORD, null) ?: return null
        val deviceToken = prefs.getString(KEY_DEVICE_TOKEN, null) ?: return null
        return SipCredentials(serverUrl, extension, sipPassword, deviceToken)
    }

    fun save(credentials: SipCredentials) {
        prefs.edit()
            .putString(KEY_SERVER_URL, credentials.serverUrl)
            .putString(KEY_EXTENSION, credentials.extension)
            .putString(KEY_SIP_PASSWORD, credentials.sipPassword)
            .putString(KEY_DEVICE_TOKEN, credentials.deviceToken)
            .apply()
        _credentials.value = credentials
    }

    fun clear() {
        prefs.edit().clear().apply()
        _credentials.value = null
    }

    fun isConfigured(): Boolean = _credentials.value != null

    companion object {
        private const val KEY_SERVER_URL = "server_url"
        private const val KEY_EXTENSION = "extension"
        private const val KEY_SIP_PASSWORD = "sip_password"
        private const val KEY_DEVICE_TOKEN = "device_token"
    }
}
