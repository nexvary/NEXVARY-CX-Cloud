package com.nexvary.cx.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

val NxGreen = Color(0xFF00E676)
val NxPink = Color(0xFFFF2D78)
val NxBlue = Color(0xFF0A7FFF)
val NxGold = Color(0xFFC8A550)
val NxBg = Color(0xFF080810)
val NxBgElevated = Color(0xFF0F0F1A)
val NxBgCard = Color(0xFF13131F)
val NxSilver = Color(0xFFB8C0CC)
val NxError = Color(0xFFE02020)

private val NexvaryColorScheme = darkColorScheme(
    primary = NxGreen,
    onPrimary = Color.Black,
    secondary = NxPink,
    onSecondary = Color.White,
    tertiary = NxBlue,
    onTertiary = Color.White,
    background = NxBg,
    onBackground = NxSilver,
    surface = NxBgCard,
    onSurface = NxSilver,
    surfaceVariant = NxBgElevated,
    onSurfaceVariant = Color(0xFF8890A0),
    error = NxError,
    onError = Color.White,
)

@Composable
fun NexvaryTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = NexvaryColorScheme,
        content = content,
    )
}
