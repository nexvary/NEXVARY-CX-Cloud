package com.nexvary.cx.sip

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Intent
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat
import com.nexvary.cx.MainActivity
import com.nexvary.cx.R

class SipCallService : Service() {

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        when (intent?.action) {
            ACTION_STOP -> { stopForeground(STOP_FOREGROUND_REMOVE); stopSelf(); return START_NOT_STICKY }
            ACTION_HANGUP -> {
                sendBroadcast(Intent(BROADCAST_HANGUP))
                stopForeground(STOP_FOREGROUND_REMOVE)
                stopSelf()
                return START_NOT_STICKY
            }
        }
        createNotificationChannel()
        startForeground(NOTIFICATION_ID, buildNotification(), ServiceInfo.FOREGROUND_SERVICE_TYPE_MICROPHONE)
        return START_STICKY
    }

    private fun buildNotification() = NotificationCompat.Builder(this, CHANNEL_ID)
        .setSmallIcon(android.R.drawable.ic_menu_call)
        .setContentTitle(getString(R.string.notification_title))
        .setContentText(getString(R.string.channel_description))
        .setOngoing(true)
        .setPriority(NotificationCompat.PRIORITY_MAX)
        .setCategory(NotificationCompat.CATEGORY_CALL)
        .setContentIntent(openAppIntent())
        .addAction(android.R.drawable.ic_delete, getString(R.string.action_hangup), hangupIntent())
        .build()

    private fun openAppIntent(): PendingIntent {
        val intent = Intent(this, MainActivity::class.java)
        return PendingIntent.getActivity(this, 0, intent, PendingIntent.FLAG_IMMUTABLE)
    }

    private fun hangupIntent(): PendingIntent {
        val intent = Intent(this, SipCallService::class.java).setAction(ACTION_HANGUP)
        return PendingIntent.getService(this, 1, intent, PendingIntent.FLAG_IMMUTABLE)
    }

    private fun createNotificationChannel() {
        val channel = NotificationChannel(
            CHANNEL_ID,
            getString(R.string.channel_name),
            NotificationManager.IMPORTANCE_HIGH,
        ).apply { description = getString(R.string.channel_description) }
        getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
    }

    companion object {
        const val CHANNEL_ID = "nexvary_calls"
        const val NOTIFICATION_ID = 1001
        const val ACTION_START = "com.nexvary.cx.action.START_CALL"
        const val ACTION_STOP = "com.nexvary.cx.action.STOP_CALL"
        const val ACTION_HANGUP = "com.nexvary.cx.action.HANGUP"
        const val BROADCAST_HANGUP = "com.nexvary.cx.broadcast.HANGUP"
    }
}
