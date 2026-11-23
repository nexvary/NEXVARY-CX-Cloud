package com.nexvary.cx.data

import kotlinx.coroutines.flow.StateFlow

/** Thrown by [CredentialRepository.save] when encrypted storage is not available. */
class StorageUnavailableException :
    Exception("Encrypted credential storage is not available on this device")

interface CredentialRepository {
    /** True only when encrypted storage was initialised successfully at startup. */
    val isStorageAvailable: Boolean
    val credentials: StateFlow<SipCredentials?>
    fun isConfigured(): Boolean
    /**
     * Persist [credentials] to encrypted storage.
     * @throws StorageUnavailableException if the keystore or encrypted file is unavailable.
     */
    fun save(credentials: SipCredentials)
    fun clear()
    /**
     * Delete the encrypted preferences file so the next cold start can create a
     * fresh one. This is an explicit user-initiated recovery action — no data is
     * read before deletion. [isStorageAvailable] does not change in this process;
     * normal operation resumes only after a cold restart.
     */
    fun clearForRecovery()
}
