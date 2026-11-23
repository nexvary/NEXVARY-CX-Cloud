package com.nexvary.cx.data

import kotlinx.coroutines.flow.StateFlow

interface CredentialRepository {
    val credentials: StateFlow<SipCredentials?>
    fun isConfigured(): Boolean
    fun save(credentials: SipCredentials)
    fun clear()
}
