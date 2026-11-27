package com.nexvary.cx.ui.server

import android.Manifest
import android.content.pm.PackageManager
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.ImageProxy
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import com.google.zxing.BinaryBitmap
import com.google.zxing.NotFoundException
import com.google.zxing.PlanarYUVLuminanceSource
import com.google.zxing.common.HybridBinarizer
import com.google.zxing.qrcode.QRCodeReader
import com.nexvary.cx.ui.theme.NxBlue
import com.nexvary.cx.ui.theme.NxGold
import com.nexvary.cx.ui.theme.NxSilver
import java.util.concurrent.Executors
import java.util.concurrent.atomic.AtomicBoolean

private val OverlayColor = Color.Black.copy(alpha = 0.62f)
private val ScannerSize = 268.dp

/**
 * Full-screen camera QR scanner.
 *
 * Uses CameraX [ImageAnalysis] with a ZXing [QRCodeReader] to decode each
 * back-camera frame. An [AtomicBoolean] gate ensures [onScanned] fires at most
 * once per session so a rapid sequence of callbacks cannot trigger duplicate
 * pairing requests.
 *
 * The decoded payload is dispatched to the main thread before [onScanned] is
 * called; the caller may update Compose state safely from the lambda.
 *
 * The pairing token is never logged or rendered in a screenshot-capturable
 * widget inside this composable — it passes opaquely to [onScanned].
 */
@Composable
fun CameraQrScanner(
    onScanned: (String) -> Unit,
    onDismiss: () -> Unit,
    onManualEntry: () -> Unit,
) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current

    var cameraGranted by remember {
        mutableStateOf(
            ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA)
                == PackageManager.PERMISSION_GRANTED,
        )
    }
    var permissionDenied by remember { mutableStateOf(false) }

    val permissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission(),
    ) { granted ->
        cameraGranted = granted
        if (!granted) permissionDenied = true
    }

    LaunchedEffect(Unit) {
        if (!cameraGranted) permissionLauncher.launch(Manifest.permission.CAMERA)
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Color.Black),
    ) {
        when {
            cameraGranted -> {
                CameraPreviewWithAnalyzer(
                    lifecycleOwner = lifecycleOwner,
                    onScanned = onScanned,
                )
                ScannerOverlay(onDismiss = onDismiss, onManualEntry = onManualEntry)
            }

            permissionDenied -> {
                CameraPermissionDenied(onDismiss = onDismiss, onManualEntry = onManualEntry)
            }

            else -> {
                // Waiting for the system permission dialog to return
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    CircularProgressIndicator(color = NxGold)
                }
                IconButton(
                    onClick = onDismiss,
                    modifier = Modifier
                        .align(Alignment.TopStart)
                        .statusBarsPadding()
                        .padding(8.dp),
                ) {
                    Icon(Icons.Default.Close, contentDescription = "إلغاء", tint = Color.White)
                }
            }
        }
    }
}

// ── Camera preview + ZXing analyzer ──────────────────────────────────────────

@Composable
private fun CameraPreviewWithAnalyzer(
    lifecycleOwner: androidx.lifecycle.LifecycleOwner,
    onScanned: (String) -> Unit,
) {
    val scanned = remember { AtomicBoolean(false) }
    val analyzerExecutor = remember { Executors.newSingleThreadExecutor() }
    val qrReader = remember { QRCodeReader() }

    DisposableEffect(Unit) {
        onDispose { analyzerExecutor.shutdown() }
    }

    AndroidView(
        factory = { ctx ->
            val previewView = PreviewView(ctx)
            val mainExecutor = ContextCompat.getMainExecutor(ctx)
            val cameraProviderFuture = ProcessCameraProvider.getInstance(ctx)

            cameraProviderFuture.addListener({
                val cameraProvider = cameraProviderFuture.get()

                val preview = Preview.Builder().build().also {
                    it.setSurfaceProvider(previewView.surfaceProvider)
                }

                val analysis = ImageAnalysis.Builder()
                    .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                    .setOutputImageFormat(ImageAnalysis.OUTPUT_IMAGE_FORMAT_YUV_420_888)
                    .build().also { ia ->
                        ia.setAnalyzer(analyzerExecutor) { imageProxy ->
                            if (!scanned.get()) {
                                val payload = decodeQr(imageProxy, qrReader)
                                if (payload != null && scanned.compareAndSet(false, true)) {
                                    // Dispatch to main thread before mutating Compose state.
                                    mainExecutor.execute { onScanned(payload) }
                                }
                            }
                            imageProxy.close()
                        }
                    }

                try {
                    cameraProvider.unbindAll()
                    cameraProvider.bindToLifecycle(
                        lifecycleOwner,
                        CameraSelector.DEFAULT_BACK_CAMERA,
                        preview,
                        analysis,
                    )
                } catch (_: Exception) {
                    // Camera unavailable on this device/emulator configuration
                }
            }, ContextCompat.getMainExecutor(ctx))

            previewView
        },
        modifier = Modifier.fillMaxSize(),
    )
}

private fun decodeQr(imageProxy: ImageProxy, reader: QRCodeReader): String? {
    val plane = imageProxy.planes[0]
    val buffer = plane.buffer
    val bytes = ByteArray(buffer.remaining())
    buffer.get(bytes)
    val source = PlanarYUVLuminanceSource(
        bytes,
        imageProxy.width,
        imageProxy.height,
        0, 0,
        imageProxy.width,
        imageProxy.height,
        false,
    )
    return try {
        reader.decode(BinaryBitmap(HybridBinarizer(source))).text
    } catch (_: NotFoundException) {
        null
    } catch (_: Exception) {
        null
    }
}

// ── Overlay UI ────────────────────────────────────────────────────────────────

@Composable
private fun BoxScope.ScannerOverlay(
    onDismiss: () -> Unit,
    onManualEntry: () -> Unit,
) {
    Column(modifier = Modifier.fillMaxSize()) {
        // Top dark region
        Spacer(
            Modifier
                .fillMaxWidth()
                .weight(1f)
                .background(OverlayColor),
        )
        // Middle: side shades flanking the transparent viewfinder
        Row(Modifier.fillMaxWidth()) {
            Spacer(
                Modifier
                    .weight(1f)
                    .height(ScannerSize)
                    .background(OverlayColor),
            )
            Box(
                Modifier
                    .size(ScannerSize)
                    .border(2.dp, NxGold, RoundedCornerShape(18.dp)),
            )
            Spacer(
                Modifier
                    .weight(1f)
                    .height(ScannerSize)
                    .background(OverlayColor),
            )
        }
        // Bottom dark region with instructions and manual-entry fallback
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .weight(1.4f)
                .background(OverlayColor)
                .padding(horizontal = 32.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Top,
        ) {
            Spacer(Modifier.height(24.dp))
            Text(
                text = "وجّه الكاميرا نحو رمز QR في لوحة التحكم",
                color = Color.White,
                fontSize = 14.sp,
            )
            Spacer(Modifier.height(6.dp))
            Text(
                text = "Direct the camera at the QR code in the web console",
                color = NxSilver.copy(alpha = 0.6f),
                fontSize = 12.sp,
            )
            Spacer(Modifier.height(28.dp))
            OutlinedButton(
                onClick = onManualEntry,
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.outlinedButtonColors(contentColor = NxSilver),
                border = androidx.compose.foundation.BorderStroke(1.dp, NxSilver.copy(alpha = 0.35f)),
            ) {
                Text("لصق يدوياً  /  Paste manually", color = NxSilver, fontSize = 14.sp)
            }
        }
    }

    // Cancel button — fixed to top-left above the overlay
    IconButton(
        onClick = onDismiss,
        modifier = Modifier
            .align(Alignment.TopStart)
            .statusBarsPadding()
            .padding(8.dp),
    ) {
        Icon(Icons.Default.Close, contentDescription = "إلغاء / Cancel", tint = Color.White)
    }
}

@Composable
private fun CameraPermissionDenied(
    onDismiss: () -> Unit,
    onManualEntry: () -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Text("لا يمكن الوصول إلى الكاميرا", color = NxSilver, fontSize = 16.sp)
        Spacer(Modifier.height(6.dp))
        Text(
            "Camera access is unavailable",
            color = NxSilver.copy(alpha = 0.5f),
            fontSize = 13.sp,
        )
        Spacer(Modifier.height(8.dp))
        Text(
            "فعّل الإذن من إعدادات التطبيق — Enable permission in app settings",
            color = NxSilver.copy(alpha = 0.5f),
            fontSize = 12.sp,
        )
        Spacer(Modifier.height(28.dp))
        Button(
            onClick = onManualEntry,
            colors = ButtonDefaults.buttonColors(containerColor = NxBlue),
            shape = RoundedCornerShape(12.dp),
        ) {
            Text("لصق يدوياً  /  Paste manually")
        }
        Spacer(Modifier.height(12.dp))
        OutlinedButton(
            onClick = onDismiss,
            shape = RoundedCornerShape(12.dp),
            colors = ButtonDefaults.outlinedButtonColors(contentColor = NxSilver),
        ) {
            Text("إلغاء  /  Cancel")
        }
    }
}
