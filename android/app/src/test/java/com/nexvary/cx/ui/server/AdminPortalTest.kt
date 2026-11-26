package com.nexvary.cx.ui.server

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

class AdminPortalTest {
    @Test
    fun `opens known admin paths on the paired HTTPS origin`() {
        assertEquals("https://cx.example.com/dashboard", adminPortalUrl("https://cx.example.com/", AdminPage.Dashboard))
        assertEquals("https://cx.example.com:8443/settings/integrations",
            adminPortalUrl("https://cx.example.com:8443", AdminPage.Telephony))
    }

    @Test
    fun `rejects invalid origins and credentials in URLs`() {
        listOf("http://cx.example.com", "https://user:password@cx.example.com",
            "https://cx.example.com/?token=test", "javascript:alert(1)").forEach {
            assertNull(adminPortalUrl(it, AdminPage.Users))
        }
    }
}
