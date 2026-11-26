package com.nexvary.cx

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import com.nexvary.cx.ui.NavGraph
import com.nexvary.cx.ui.theme.NexvaryTheme
import dagger.hilt.android.AndroidEntryPoint
import com.nexvary.cx.data.CredentialRepository
import com.nexvary.cx.sip.SipEngine
import com.nexvary.cx.sip.toSipConfig
import javax.inject.Inject

@AndroidEntryPoint
class MainActivity : ComponentActivity() {
    @Inject lateinit var credentials: CredentialRepository
    @Inject lateinit var sipEngine: SipEngine

    override fun onCreate(savedInstanceState: Bundle?) {
        installSplashScreen()
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        if (savedInstanceState == null) {
            credentials.credentials.value?.toSipConfig()?.let { config ->
                runCatching { sipEngine.configure(config) }
            }
        }
        setContent {
            NexvaryTheme {
                NavGraph()
            }
        }
    }
}
