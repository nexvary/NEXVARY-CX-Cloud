package com.nexvary.cx.data

import com.jakewharton.retrofit2.converter.kotlinx.serialization.asConverterFactory
import com.nexvary.cx.data.model.PairExchangeRequest
import com.nexvary.cx.data.model.PairExchangeResponse
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import retrofit2.HttpException
import retrofit2.Retrofit
import java.io.IOException
import javax.inject.Inject
import javax.inject.Singleton

// ── Typed pairing errors (Arabic primary, English in parens) ─────────────────

sealed class PairingException(message: String, cause: Throwable? = null) : Exception(message, cause) {

    /** The QR JSON was missing, not parseable, or lacked required fields. */
    class MalformedPayload(detail: String) :
        PairingException("رمز QR غير صالح ($detail / malformed QR payload)")

    /** The server URL in the QR payload does not use HTTPS. */
    class InsecureUrl(url: String) :
        PairingException("يلزم HTTPS؛ تم استلام: $url (HTTPS required)")

    /** The server returned 401 or 403 — token expired or was already consumed. */
    class TokenExpiredOrRejected :
        PairingException("انتهت صلاحية رمز الإقران أو تم رفضه (pairing token expired or rejected)")

    /** Any other HTTP or network failure. */
    class ConnectionFailed(cause: Throwable) :
        PairingException("فشل الاتصال: ${cause.message} (connection failed)", cause)
}

// ── PairingClient ─────────────────────────────────────────────────────────────

/**
 * Executes the pairing-token exchange using a **one-shot** OkHttp client that
 * bypasses the global interceptor entirely.
 *
 * The global singleton interceptor is correct for authenticated API calls: it
 * rewrites the destination to the saved server and attaches the stored device
 * token. Both are wrong for the pairing exchange, which must reach the **QR
 * server** and carry exactly one Authorization header containing the
 * **short-lived QR pairing token**.
 *
 * This class is `@Singleton` to avoid recreating the `Json` reference, but each
 * [exchange] call builds a fresh OkHttpClient so there is no shared mutable state.
 */
@Singleton
open class PairingClient @Inject constructor(private val json: Json) {

    private val mediaType = "application/json".toMediaType()

    /**
     * @param serverUrl      The base URL from the QR payload. Must use HTTPS unless
     *                       [enforceHttps] is false (tests only).
     * @param pairingToken   The short-lived pairing bearer token from the QR payload.
     * @param deviceId       A stable, random identifier for this device installation.
     * @param enforceHttps   Set to false only in tests that target a local HTTP server.
     */
    open suspend fun exchange(
        serverUrl: String,
        pairingToken: String,
        deviceId: String,
        enforceHttps: Boolean = true,
    ): PairExchangeResponse {
        val base = serverUrl.trimEnd('/').let {
            if (it.endsWith('/')) it else "$it/"
        }
        if (enforceHttps && !base.startsWith("https://")) {
            throw PairingException.InsecureUrl(base)
        }

        val client = OkHttpClient.Builder()
            // Never follow redirects: a redirect would forward the Authorization
            // header containing the pairing token to a potentially different origin.
            .followRedirects(false)
            .followSslRedirects(false)
            .addInterceptor { chain ->
                // Exactly one Authorization header — the QR pairing token only.
                val request = chain.request().newBuilder()
                    .header("Authorization", "Bearer $pairingToken")
                    .header("Accept", "application/json")
                    .build()
                chain.proceed(request)
            }
            .build()

        val retrofit = Retrofit.Builder()
            .baseUrl(base)
            .client(client)
            .addConverterFactory(json.asConverterFactory(mediaType))
            .build()

        val service = retrofit.create(MobileApiService::class.java)

        return try {
            service.exchangePairingToken(
                PairExchangeRequest(deviceName = "NEXVARY CX Android", deviceId = deviceId),
            )
        } catch (e: HttpException) {
            if (e.code() == 401 || e.code() == 403) {
                throw PairingException.TokenExpiredOrRejected()
            }
            throw PairingException.ConnectionFailed(e)
        } catch (e: IOException) {
            throw PairingException.ConnectionFailed(e)
        }
    }
}
