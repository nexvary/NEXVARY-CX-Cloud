package com.nexvary.cx.data

import android.content.Context
import android.content.SharedPreferences
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
    val sipHost: String = "",
    val sipPort: Int = 5061,
)

@Singleton
class CredentialStore @Inject constructor(
    @ApplicationContext private val context: Context,
) : CredentialRepository {

    // Remove any plaintext file written by the broken 3ad756d build (before this
    // commit).  Never read its contents — treat it as untrusted data to wipe.
    init {
        context.deleteSharedPreferences("nexvary_credentials_plain")
    }

    // Attempt encrypted init once at construction time. If the Android Keystore
    // is unavailable or the existing file is irrecoverable, prefs stays null and
    // isStorageAvailable is false for the lifetime of this process.  The encrypted
    // file is preserved so a later cold start (after a device unlock or OS repair)
    // can recover it — it is never deleted here.
    private val prefs: SharedPreferences? = try {
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
    } catch (_: Exception) {
        null
    }

    override val isStorageAvailable: Boolean = prefs != null

    private val _credentials = MutableStateFlow(if (isStorageAvailable) load() else null)
    override val credentials: StateFlow<SipCredentials?> = _credentials.asStateFlow()

    private fun load(): SipCredentials? {
        val p = prefs ?: return null
        return try {
            val serverUrl   = p.getString(KEY_SERVER_URL,   null) ?: return null
            val extension   = p.getString(KEY_EXTENSION,    null) ?: return null
            val sipPassword = p.getString(KEY_SIP_PASSWORD, null) ?: return null
            val deviceToken = p.getString(KEY_DEVICE_TOKEN, null) ?: return null
            SipCredentials(serverUrl, extension, sipPassword, deviceToken,
                p.getString("sip_host", "").orEmpty(), p.getInt("sip_port", 5061))
        } catch (_: Exception) {
            // Key may have been invalidated by a security event mid-session;
            // return null so the caller treats this as NotConfigured.
            null
        }
    }

    override fun save(credentials: SipCredentials) {
        val p = prefs ?: throw StorageUnavailableException()
        try {
            p.edit()
                .putString(KEY_SERVER_URL,   credentials.serverUrl)
                .putString(KEY_EXTENSION,    credentials.extension)
                .putString(KEY_SIP_PASSWORD, credentials.sipPassword)
                .putString(KEY_DEVICE_TOKEN, credentials.deviceToken)
                .putString("sip_host", credentials.sipHost)
                .putInt("sip_port", credentials.sipPort)
                .apply()
            _credentials.value = credentials
        } catch (_: Exception) {
            // Write failed (e.g. key invalidated after init succeeded at startup).
            throw StorageUnavailableException()
        }
    }

    override fun clear() {
        prefs?.edit()?.clear()?.apply()
        _credentials.value = null
    }

    /**
     * Deletes the encrypted preferences file so the next cold start creates a
     * fresh one. Only call this as an explicit user-initiated recovery action
     * when [isStorageAvailable] is false and the file is confirmed corrupted.
     * No data is read before deletion.
     */
    override fun clearForRecovery() {
        _credentials.value = null
        context.deleteSharedPreferences("nexvary_credentials")
    }

    override fun isConfigured(): Boolean = _credentials.value != null

    companion object {
        private const val KEY_SERVER_URL   = "server_url"
        private const val KEY_EXTENSION    = "extension"
        private const val KEY_SIP_PASSWORD = "sip_password"
        private const val KEY_DEVICE_TOKEN = "device_token"
    }
}
