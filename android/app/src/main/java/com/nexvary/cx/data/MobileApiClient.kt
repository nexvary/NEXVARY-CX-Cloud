package com.nexvary.cx.data

import com.nexvary.cx.data.model.PairExchangeRequest
import com.nexvary.cx.data.model.PairExchangeResponse
import com.nexvary.cx.data.model.ServerStatus
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST

interface MobileApiService {
    @GET("api/mobile/v1/status")
    suspend fun serverStatus(): ServerStatus

    @POST("api/mobile/v1/pair/exchange")
    suspend fun exchangePairingToken(@Body request: PairExchangeRequest): PairExchangeResponse
}
