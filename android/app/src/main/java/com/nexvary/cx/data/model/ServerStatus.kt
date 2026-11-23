package com.nexvary.cx.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class ServerStatus(
    val server: ServerInfo,
    val services: ServicesInfo,
    val pbx: PbxInfo,
    @SerialName("checked_at") val checkedAt: String,
)

@Serializable
data class ServerInfo(
    @SerialName("ram_percent") val ramPercent: Int,
    @SerialName("disk_percent") val diskPercent: Int,
    @SerialName("load_avg") val loadAvg: List<Double>? = null,
    val uptime: Long? = null,
)

@Serializable
data class ServicesInfo(
    val postgresql: String,
    val freeswitch: String,
    @SerialName("freeswitch_version") val freeswitchVersion: String? = null,
)

@Serializable
data class PbxInfo(
    @SerialName("active_calls") val activeCalls: Int,
    val registrations: Int,
)

@Serializable
data class PairExchangeRequest(
    @SerialName("device_name") val deviceName: String,
    @SerialName("device_id") val deviceId: String,
)

@Serializable
data class PairExchangeResponse(
    val token: String,
    @SerialName("expires_at") val expiresAt: String,
    @SerialName("server_url") val serverUrl: String,
    @SerialName("api_version") val apiVersion: String,
)
