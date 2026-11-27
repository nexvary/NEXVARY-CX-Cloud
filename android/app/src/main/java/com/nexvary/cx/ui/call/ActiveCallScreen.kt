package com.nexvary.cx.ui.call

import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.nexvary.cx.ui.theme.*

@Composable
fun ActiveCallScreen(
    onCallEnded: () -> Unit,
    onDialpadTap: () -> Unit,
    viewModel: CallViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsState()

    LaunchedEffect(uiState.sipState.inCall) {
        if (!uiState.sipState.inCall && uiState.sipState.callNumber.isEmpty()) {
            onCallEnded()
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(NxBg)
            .systemBarsPadding()
            .padding(horizontal = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Spacer(Modifier.height(32.dp))

        Text("مكالمة جارية", color = NxSilver, fontSize = 20.sp)

        Spacer(Modifier.height(40.dp))

        PulsingCallCircle()

        Spacer(Modifier.height(24.dp))

        Text(
            text = uiState.callNumber.ifEmpty { "---" },
            color = Color.White,
            fontSize = 28.sp,
            fontFamily = FontFamily.Monospace,
        )

        Spacer(Modifier.height(8.dp))

        Text(
            text = uiState.timerFormatted,
            color = NxGreen,
            fontSize = 18.sp,
            fontFamily = FontFamily.Monospace,
        )

        Spacer(Modifier.height(24.dp))

        // Hide My Number request button
        OutlinedButton(
            onClick = { /* Privacy:id applied at call setup; show indicator */ },
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.outlinedButtonColors(contentColor = NxPink),
            border = androidx.compose.foundation.BorderStroke(1.dp, NxPink.copy(alpha = 0.5f)),
            modifier = Modifier.fillMaxWidth(),
        ) {
            Icon(Icons.Default.VisibilityOff, contentDescription = null, tint = NxPink, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(8.dp))
            Text(
                text = if (uiState.hideMyNumber) "الرقم مخفي ✓" else "طلب إخفاء الرقم",
                color = NxPink,
            )
        }

        Spacer(Modifier.height(24.dp))

        // Three control buttons
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceEvenly,
        ) {
            CallControlButton(
                icon = Icons.Default.DialerSip,
                label = "لوحة الأرقام",
                active = false,
                activeColor = NxBlue,
                onClick = onDialpadTap,
            )
            CallControlButton(
                icon = Icons.Default.VolumeUp,
                label = "مكبر الصوت",
                active = uiState.speakerOn,
                activeColor = NxBlue,
                onClick = { viewModel.toggleSpeaker() },
            )
            CallControlButton(
                icon = if (uiState.muted) Icons.Default.MicOff else Icons.Default.Mic,
                label = "كتم الصوت",
                active = uiState.muted,
                activeColor = NxPink,
                onClick = { viewModel.toggleMute() },
            )
        }

        Spacer(Modifier.weight(1f))

        // End call button
        Button(
            onClick = { viewModel.hangUp() },
            modifier = Modifier
                .fillMaxWidth()
                .height(60.dp),
            colors = ButtonDefaults.buttonColors(containerColor = NxError),
            shape = RoundedCornerShape(18.dp),
        ) {
            Icon(Icons.Default.CallEnd, contentDescription = null, tint = Color.White)
            Spacer(Modifier.width(10.dp))
            Text("إنهاء المكالمة", color = Color.White, fontSize = 16.sp)
        }

        Spacer(Modifier.height(32.dp))
    }
}

@Composable
private fun PulsingCallCircle() {
    val infiniteTransition = rememberInfiniteTransition(label = "pulse")
    val scale by infiniteTransition.animateFloat(
        initialValue = 1f, targetValue = 1.15f,
        animationSpec = infiniteRepeatable(
            animation = tween(800, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse,
        ),
        label = "scale",
    )
    Box(
        modifier = Modifier
            .size(100.dp)
            .scale(scale)
            .background(NxGreen.copy(alpha = 0.15f), CircleShape)
            .border(2.dp, NxGreen, CircleShape),
        contentAlignment = Alignment.Center,
    ) {
        Icon(
            Icons.Default.Call,
            contentDescription = null,
            tint = NxGreen,
            modifier = Modifier.size(42.dp),
        )
    }
}

@Composable
private fun CallControlButton(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    label: String,
    active: Boolean,
    activeColor: Color,
    onClick: () -> Unit,
) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Surface(
            onClick = onClick,
            modifier = Modifier.size(56.dp),
            shape = CircleShape,
            color = if (active) activeColor.copy(alpha = 0.2f) else NxBgCard,
        ) {
            Box(contentAlignment = Alignment.Center) {
                Icon(icon, contentDescription = label, tint = if (active) activeColor else NxSilver)
            }
        }
        Spacer(Modifier.height(6.dp))
        Text(label, color = NxSilver, fontSize = 11.sp)
    }
}
