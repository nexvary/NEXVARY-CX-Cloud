package com.nexvary.cx.data

/**
 * User-facing error strings for the pairing flow.
 *
 * Arabic primary; English in parentheses for mixed-locale environments.
 * See also res/values/strings.xml (and res/values-ar/strings.xml) for the
 * corresponding Android string resources usable in XML layouts and context-
 * aware code.
 *
 * Raw exception messages (stack trace fragments, server URLs, internal errors)
 * must never reach these strings — always pick the specific constant here.
 */
object PairingErrors {
    const val MALFORMED_QR      = "رمز QR غير صالح (malformed QR payload)"
    const val HTTPS_REQUIRED    = "يلزم HTTPS للإقران (HTTPS required for pairing)"
    const val TOKEN_EXPIRED     = "انتهت صلاحية رمز الإقران (pairing token expired or rejected)"
    const val CONNECTION_FAILED = "فشل الاتصال بالخادم (connection failed)"
    const val SIP_CONFIG_ERROR       = "تم الإقران — خطأ في إعداد SIP (paired; SIP config error)"
    const val STORAGE_UNAVAILABLE    = "لا يمكن حفظ بيانات الاعتماد — أعد تشغيل التطبيق (storage unavailable — restart the app)"
    const val UNEXPECTED             = "حدث خطأ غير متوقع — يرجى المحاولة مجدداً (unexpected error)"
}
