package com.nexvary.cx.ui.server

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.nexvary.cx.ui.theme.*

private enum class ScanMode { None, Camera, ManualPaste }

@Composable
fun ServerScreen(
    onBack: () -> Unit,
    viewModel: ServerViewModel = hiltViewModel(),
) {
    val serverState by viewModel.serverState.collectAsState()
    val sipState by viewModel.sipState.collectAsState()
    val credentials by viewModel.credentials.collectAsState()
    var scanMode by remember { mutableStateOf(ScanMode.None) }

    Box(modifier = Modifier.fillMaxSize()) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(NxBg)
            .systemBarsPadding()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 20.dp),
    ) {
        Spacer(Modifier.height(24.dp))

        // Header
        Row(verticalAlignment = Alignment.CenterVertically) {
            IconButton(onClick = onBack) {
                Icon(Icons.Default.ArrowBack, contentDescription = "Back", tint = NxSilver)
            }
            Spacer(Modifier.width(8.dp))
            Text("الخادم والاتصال", color = NxSilver, fontSize = 20.sp)
            Spacer(Modifier.weight(1f))
            // Golden seal
            Box(
                modifier = Modifier
                    .size(36.dp)
                    .clip(CircleShape)
                    .background(NxGold.copy(alpha = 0.15f)),
                contentAlignment = Alignment.Center,
            ) {
                Text("N", color = NxGold, fontSize = 16.sp)
            }
        }

        Spacer(Modifier.height(24.dp))

        // Server card
        InfoCard(
            icon = Icons.Default.Dns,
            iconTint = NxBlue,
            title = "الخادم",
            value = credentials?.serverUrl ?: "غير مهيأ",
        )

        Spacer(Modifier.height(12.dp))

        // Connection card
        InfoCard(
            icon = Icons.Default.Cable,
            iconTint = if (sipState.registered) NxGreen else NxSilver,
            title = "الاتصال",
            value = if (sipState.registered) "متصل" else "غير متصل",
            badgeText = if (sipState.registered) "متصل" else null,
            badgeColor = NxGreen,
        )

        Spacer(Modifier.height(12.dp))

        // SIP extension card
        InfoCard(
            icon = Icons.Default.Phone,
            iconTint = if (sipState.registered) NxGreen else NxSilver,
            title = "الامتداد",
            value = credentials?.extension ?: "---",
            badgeText = if (sipState.registered) "مسجّل" else null,
            badgeColor = NxGreen,
        )

        Spacer(Modifier.height(24.dp))

        // QR Pairing button
        Button(
            onClick = { scanMode = ScanMode.Camera },
            modifier = Modifier.fillMaxWidth().height(52.dp),
            colors = ButtonDefaults.buttonColors(containerColor = NxBlue),
            shape = RoundedCornerShape(14.dp),
        ) {
            Icon(Icons.Default.QrCodeScanner, contentDescription = null, tint = Color.White)
            Spacer(Modifier.width(8.dp))
            Text("ربط عبر QR", color = Color.White, fontSize = 15.sp)
        }

        Spacer(Modifier.height(12.dp))

        // Service status row
        OutlinedButton(
            onClick = { viewModel.refresh() },
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(14.dp),
            colors = ButtonDefaults.outlinedButtonColors(contentColor = NxSilver),
        ) {
            Text("حالة الخدمات", modifier = Modifier.weight(1f))
            Icon(Icons.Default.ChevronRight, contentDescription = null)
        }

        // Service statuses
        when (val s = serverState) {
            is ServerUiState.Success -> {
                Spacer(Modifier.height(16.dp))
                ServiceStatusRow("FreeSWITCH", s.status.services.freeswitch == "up")
                ServiceStatusRow("PostgreSQL", s.status.services.postgresql == "up")
            }
            is ServerUiState.Error -> {
                Spacer(Modifier.height(8.dp))
                Text(s.message, color = NxError, fontSize = 13.sp)
            }
            else -> Unit
        }

        // Manual-paste dialog (shown when user chooses paste from camera screen,
        // or when camera permission is unavailable)
        if (scanMode == ScanMode.ManualPaste) {
            QrInputDialog(
                onDismiss = { scanMode = ScanMode.None },
                onConfirm = { payload ->
                    viewModel.pair(payload)
                    scanMode = ScanMode.None
                },
            )
        }

        Spacer(Modifier.height(32.dp))
    } // end Column

    // Camera scanner — full-screen overlay rendered on top of the Column
    if (scanMode == ScanMode.Camera) {
        CameraQrScanner(
            onScanned = { payload ->
                viewModel.pair(payload)
                scanMode = ScanMode.None
            },
            onDismiss = { scanMode = ScanMode.None },
            onManualEntry = { scanMode = ScanMode.ManualPaste },
        )
    }
    } // end Box
}

@Composable
private fun InfoCard(
    icon: ImageVector,
    iconTint: Color,
    title: String,
    value: String,
    badgeText: String? = null,
    badgeColor: Color = NxGreen,
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .background(NxBgCard, RoundedCornerShape(14.dp))
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            modifier = Modifier
                .size(44.dp)
                .background(iconTint.copy(alpha = 0.12f), RoundedCornerShape(12.dp)),
            contentAlignment = Alignment.Center,
        ) {
            Icon(icon, contentDescription = null, tint = iconTint, modifier = Modifier.size(22.dp))
        }
        Spacer(Modifier.width(14.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(title, color = Color(0xFF8890A0), fontSize = 12.sp)
            Text(value, color = Color.White, fontSize = 15.sp)
        }
        if (badgeText != null) {
            Text(
                text = badgeText,
                color = badgeColor,
                fontSize = 12.sp,
                modifier = Modifier
                    .background(badgeColor.copy(alpha = 0.12f), RoundedCornerShape(8.dp))
                    .padding(horizontal = 10.dp, vertical = 4.dp),
            )
        }
    }
}

@Composable
private fun ServiceStatusRow(name: String, up: Boolean) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            modifier = Modifier
                .size(8.dp)
                .background(if (up) NxGreen else NxError, CircleShape),
        )
        Spacer(Modifier.width(10.dp))
        Text(name, color = NxSilver, fontSize = 14.sp)
        Spacer(Modifier.weight(1f))
        Text(if (up) "يعمل" else "متوقف", color = if (up) NxGreen else NxError, fontSize = 13.sp)
    }
}

@Composable
private fun QrInputDialog(onDismiss: () -> Unit, onConfirm: (String) -> Unit) {
    var text by remember { mutableStateOf("") }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("ربط الجهاز") },
        text = {
            Column {
                Text("الصق رمز الإقران من واجهة الويب:", color = NxSilver, fontSize = 13.sp)
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(
                    value = text,
                    onValueChange = { text = it },
                    placeholder = { Text("{\"pairing_token\":\"...\",\"server_url\":\"...\"}") },
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        },
        confirmButton = {
            TextButton(onClick = { if (text.isNotBlank()) onConfirm(text) }) {
                Text("ربط", color = NxBlue)
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("إلغاء") }
        },
        containerColor = NxBgCard,
    )
}
