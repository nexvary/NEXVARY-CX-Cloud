package com.nexvary.cx.ui.server

import java.net.URI

internal enum class AdminPage(val path: String) {
    Dashboard("/dashboard"),
    Users("/settings/users"),
    Telephony("/settings/integrations"),
}

internal fun adminPortalUrl(pairedServer: String, page: AdminPage): String? {
    val server = runCatching { URI(pairedServer) }.getOrNull() ?: return null
    if (!server.scheme.equals("https", ignoreCase = true) || server.host.isNullOrBlank()
        || server.rawUserInfo != null || server.rawQuery != null || server.rawFragment != null
        || (server.port != -1 && server.port !in 1..65535)
    ) return null

    return URI("https", null, server.host, server.port, page.path, null, null).toASCIIString()
}
