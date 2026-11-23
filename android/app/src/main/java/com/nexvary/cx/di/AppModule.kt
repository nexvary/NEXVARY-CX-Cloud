package com.nexvary.cx.di

import com.jakewharton.retrofit2.converter.kotlinx.serialization.asConverterFactory
import com.nexvary.cx.data.CredentialStore
import com.nexvary.cx.data.MobileApiService
import com.nexvary.cx.sip.LinphoneSipEngine
import com.nexvary.cx.sip.SipEngine
import dagger.Binds
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
abstract class EngineModule {
    @Binds
    @Singleton
    abstract fun bindSipEngine(impl: LinphoneSipEngine): SipEngine
}

@Module
@InstallIn(SingletonComponent::class)
object NetworkModule {

    @Provides
    @Singleton
    fun provideJson(): Json = Json { ignoreUnknownKeys = true; isLenient = true }

    @Provides
    @Singleton
    fun provideOkHttp(credentialStore: CredentialStore): OkHttpClient =
        OkHttpClient.Builder()
            .addInterceptor { chain ->
                val token = credentialStore.credentials.value?.deviceToken
                val request = if (token != null) {
                    chain.request().newBuilder()
                        .addHeader("Authorization", "Bearer $token")
                        .addHeader("Accept", "application/json")
                        .build()
                } else chain.request()
                chain.proceed(request)
            }
            .addInterceptor(HttpLoggingInterceptor().apply {
                level = HttpLoggingInterceptor.Level.BASIC
            })
            .build()

    @Provides
    @Singleton
    fun provideRetrofit(okHttpClient: OkHttpClient, credentialStore: CredentialStore, json: Json): Retrofit {
        val baseUrl = credentialStore.credentials.value?.serverUrl?.trimEnd('/') + "/"
        return Retrofit.Builder()
            .baseUrl(baseUrl.ifBlank { "https://localhost/" })
            .client(okHttpClient)
            .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
            .build()
    }

    @Provides
    @Singleton
    fun provideMobileApiService(retrofit: Retrofit): MobileApiService =
        retrofit.create(MobileApiService::class.java)
}
