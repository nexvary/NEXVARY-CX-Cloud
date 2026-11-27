package com.nexvary.cx.ui

import androidx.compose.runtime.Composable
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.nexvary.cx.ui.call.ActiveCallScreen
import com.nexvary.cx.ui.dialer.DialerScreen
import com.nexvary.cx.ui.server.ServerScreen

sealed class Route(val path: String) {
    object Dialer : Route("dialer")
    object ActiveCall : Route("active_call")
    object Server : Route("server")
}

@Composable
fun NavGraph() {
    val navController = rememberNavController()
    NavHost(navController = navController, startDestination = Route.Dialer.path) {
        composable(Route.Dialer.path) {
            DialerScreen(
                onCallStarted = { navController.navigate(Route.ActiveCall.path) },
                onServerTap = { navController.navigate(Route.Server.path) },
            )
        }
        composable(Route.ActiveCall.path) {
            ActiveCallScreen(
                onCallEnded = { navController.popBackStack() },
                onDialpadTap = { /* handled inline */ },
            )
        }
        composable(Route.Server.path) {
            ServerScreen(onBack = { navController.popBackStack() })
        }
    }
}
