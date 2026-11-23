package com.nexvary.cx.ui.dialer

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Backspace
import androidx.compose.material.icons.filled.Call
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.nexvary.cx.ui.theme.*

private val KEYPAD = listOf(
    listOf('1', '2', '3'),
    listOf('4', '5', '6'),
    listOf('7', '8', '9'),
    listOf('*', '0', '#'),
)

@Composable
fun DialerScreen(
    onCallStarted: () -> Unit,
    onServerTap: () -> Unit,
    viewModel: DialerViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsState()
    val sipState by viewModel.sipState.collectAsState()

    LaunchedEffect(sipState.inCall) {
        if (sipState.inCall) onCallStarted()
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(NxBg)
            .systemBarsPadding()
            .padding(horizontal = 24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Spacer(Modifier.height(24.dp))

        Text(
            text = "لوحة الاتصال",
            color = NxSilver,
            fontSize = 20.sp,
            fontFamily = FontFamily.Default,
        )

        Spacer(Modifier.height(24.dp))

        // Number display
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(NxBgCard, RoundedCornerShape(16.dp))
                .padding(horizontal = 20.dp, vertical = 14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                text = uiState.dialedNumber.ifEmpty { " " },
                color = Color.White,
                fontSize = 28.sp,
                fontFamily = FontFamily.Monospace,
                modifier = Modifier.weight(1f),
                textAlign = TextAlign.Center,
            )
            if (uiState.dialedNumber.isNotEmpty()) {
                IconButton(onClick = { viewModel.backspace() }) {
                    Icon(Icons.Default.Backspace, contentDescription = "مسح", tint = NxSilver)
                }
            }
        }

        uiState.errorMessage?.let { err ->
            Spacer(Modifier.height(8.dp))
            Text(err, color = NxError, fontSize = 13.sp)
        }

        Spacer(Modifier.height(20.dp))

        // Keypad
        KEYPAD.forEach { row ->
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceEvenly,
            ) {
                row.forEach { digit ->
                    KeypadButton(digit) { viewModel.appendDigit(it) }
                }
            }
            Spacer(Modifier.height(10.dp))
        }

        Spacer(Modifier.height(16.dp))

        // Hide My Number toggle
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .background(NxBgCard, RoundedCornerShape(12.dp))
                .padding(horizontal = 16.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(
                Icons.Default.VisibilityOff,
                contentDescription = null,
                tint = if (uiState.hideMyNumber) NxPink else NxSilver,
                modifier = Modifier.size(20.dp),
            )
            Spacer(Modifier.width(10.dp))
            Text(
                text = "إخفاء رقمي",
                color = if (uiState.hideMyNumber) NxPink else NxSilver,
                modifier = Modifier.weight(1f),
            )
            Switch(
                checked = uiState.hideMyNumber,
                onCheckedChange = { viewModel.toggleHideNumber() },
                colors = SwitchDefaults.colors(
                    checkedThumbColor = Color.White,
                    checkedTrackColor = NxPink,
                    uncheckedTrackColor = NxBgElevated,
                ),
            )
        }

        Spacer(Modifier.height(20.dp))

        // Call button
        Button(
            onClick = { viewModel.dial() },
            modifier = Modifier
                .fillMaxWidth()
                .height(56.dp),
            colors = ButtonDefaults.buttonColors(containerColor = NxBlue),
            shape = RoundedCornerShape(16.dp),
        ) {
            Icon(Icons.Default.Call, contentDescription = null, tint = Color.White)
            Spacer(Modifier.width(8.dp))
            Text("اتصال", color = Color.White, fontSize = 16.sp)
        }

        Spacer(Modifier.weight(1f))

        // Bottom tab bar
        BottomTabBar(selectedTab = 1, onServerTap = onServerTap)

        Spacer(Modifier.height(16.dp))
    }
}

@Composable
private fun KeypadButton(digit: Char, onClick: (Char) -> Unit) {
    Surface(
        onClick = { onClick(digit) },
        modifier = Modifier.size(72.dp),
        shape = RoundedCornerShape(16.dp),
        color = NxBgCard,
        tonalElevation = 2.dp,
    ) {
        Box(contentAlignment = Alignment.Center) {
            Text(
                text = digit.toString(),
                color = Color.White,
                fontSize = 22.sp,
                fontFamily = FontFamily.Monospace,
            )
        }
    }
}

@Composable
fun BottomTabBar(selectedTab: Int, onServerTap: () -> Unit) {
    val tabs = listOf("السجل", "الاتصال", "الخادم")
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(NxBgCard, RoundedCornerShape(16.dp))
            .padding(vertical = 8.dp),
        horizontalArrangement = Arrangement.SpaceEvenly,
    ) {
        tabs.forEachIndexed { index, label ->
            val isSelected = index == selectedTab
            TextButton(onClick = { if (index == 2) onServerTap() }) {
                Text(
                    text = label,
                    color = if (isSelected) NxGreen else NxSilver,
                    fontSize = 14.sp,
                )
            }
        }
    }
}
