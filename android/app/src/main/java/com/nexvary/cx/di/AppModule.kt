package com.nexvary.cx.di

import com.jakewharton.retrofit2.converter.kotlinx.serialization.asConverterFactory
import com.nexvary.cx.data.CredentialRepository
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
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull
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

    @Binds
    @Singleton
    abstract fun bindCredentialRepository(impl: CredentialStore): CredentialRepository
}

@Module
@InstallIn(SingletonComponent::class)
object NetworkModule {

    @Provides
    @Singleton
    fun provideJson(): Json = Json { ignoreUnknownKeys = true; isLenient = true }

    @Provides
    @Singleton
    fun provideOkHttp(credentialStore: CredentialRepository): OkHttpClient =
        OkHttpClient.Builder()
            .addInterceptor { chain ->
                val creds = credentialStore.credentials.value
                var request = chain.request()

                // Rewrite the host/scheme to the saved server URL on every request so
                // the singleton Retrofit instance works correctly after pairing.
                val serverUrl = creds?.serverUrl?.trimEnd('/')
                if (!serverUrl.isNullOrBlank()) {
                    val parsed = serverUrl.toHttpUrlOrNull()
                    if (parsed != null) {
                        val newUrl = request.url.newBuilder()
                            .scheme(parsed.scheme)
                            .host(parsed.host)
                            .port(parsed.port)
                            .build()
                        request = request.newBuilder().url(newUrl).build()
                    }
                }

                if (creds?.deviceToken != null) {
                    request = request.newBuilder()
                        .addHeader("Authorization", "Bearer ${creds.deviceToken}")
                        .addHeader("Accept", "application/json")
                        .build()
                }
                chain.proceed(request)
            }
            .addInterceptor(HttpLoggingInterceptor().apply {
                level = HttpLoggingInterceptor.Level.BASIC
            })
            .build()

    @Provides
    @Singleton
    fun provideRetrofit(okHttpClient: OkHttpClient, json: Json): Retrofit {
        // The base URL is a placeholder; the OkHttp interceptor above rewrites
        // the host to the current server URL from CredentialRepository on every
        // request. Using a static placeholder prevents the crash that occurred
        // when no server was configured: `null + "/"` produced "null/" which
        // Retrofit.Builder().baseUrl() rejects with IllegalArgumentException.
        return Retrofit.Builder()
            .baseUrl("https://localhost/")
            .client(okHttpClient)
            .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
            .build()
    }

    @Provides
    @Singleton
    fun provideMobileApiService(retrofit: Retrofit): MobileApiService =
        retrofit.create(MobileApiService::class.java)
}
