package com.smartimmo.ideenbox

import android.app.AlarmManager
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat

/** Erinnerungen über den Wecker-Dienst von Android (funktioniert auch ohne Internet) */
object Reminders {
    const val CHANNEL = "erinnerungen"
    const val EXTRA_ID = "note_id"
    const val ACTION_FIRE = "com.smartimmo.ideenbox.FIRE"
    const val ACTION_DONE = "com.smartimmo.ideenbox.DONE"
    const val ACTION_SNOOZE = "com.smartimmo.ideenbox.SNOOZE"

    fun createChannel(ctx: Context) {
        val ch = NotificationChannel(CHANNEL, "Erinnerungen", NotificationManager.IMPORTANCE_HIGH).apply {
            description = "Erinnerungen an Ideen und ToDos"
            enableVibration(true)
        }
        ctx.getSystemService(NotificationManager::class.java).createNotificationChannel(ch)
    }

    private fun pi(ctx: Context, n: Note): PendingIntent {
        val i = Intent(ctx, ReminderReceiver::class.java).setAction(ACTION_FIRE).putExtra(EXTRA_ID, n.id)
        return PendingIntent.getBroadcast(ctx, n.id.toInt(), i, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
    }

    fun canExact(ctx: Context): Boolean {
        val am = ctx.getSystemService(AlarmManager::class.java)
        return Build.VERSION.SDK_INT < Build.VERSION_CODES.S || am.canScheduleExactAlarms()
    }

    /** Erinnerung setzen bzw. entfernen (wenn erledigt oder ohne Termin) */
    fun schedule(ctx: Context, n: Note) {
        val am = ctx.getSystemService(AlarmManager::class.java)
        val p = pi(ctx, n)
        am.cancel(p)
        val t = n.reminder ?: return
        if (!n.isOpen || t < System.currentTimeMillis() - 60_000) return
        if (canExact(ctx)) am.setExactAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, t, p)
        else am.setAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, t, p)
    }

    fun cancel(ctx: Context, n: Note) {
        ctx.getSystemService(AlarmManager::class.java).cancel(pi(ctx, n))
        NotificationManagerCompat.from(ctx).cancel(n.id.toInt())
    }

    fun rescheduleAll(ctx: Context) {
        NoteDb.get(ctx).withReminder().forEach { schedule(ctx, it) }
    }

    fun show(ctx: Context, n: Note) {
        val open = PendingIntent.getActivity(
            ctx, n.id.toInt(),
            Intent(ctx, EditActivity::class.java).putExtra(EXTRA_ID, n.id).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        fun action(a: String) = PendingIntent.getBroadcast(
            ctx, (n.id * 10 + a.length).toInt(),
            Intent(ctx, ReminderReceiver::class.java).setAction(a).putExtra(EXTRA_ID, n.id),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        val title = if (n.isTodo) "ToDo" else "Idee"
        val b = NotificationCompat.Builder(ctx, CHANNEL)
            .setSmallIcon(R.drawable.ic_notify)
            .setContentTitle(title + (n.reminder?.let { " · " + Fmt.short(it) } ?: ""))
            .setContentText(n.text)
            .setStyle(NotificationCompat.BigTextStyle().bigText(n.text))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_REMINDER)
            .setContentIntent(open)
            .setAutoCancel(true)
            .addAction(0, "Erledigt", action(ACTION_DONE))
            .addAction(0, "In 1 Stunde", action(ACTION_SNOOZE))
        try {
            NotificationManagerCompat.from(ctx).notify(n.id.toInt(), b.build())
        } catch (_: SecurityException) {
            // Benachrichtigungen nicht erlaubt
        }
    }
}

class ReminderReceiver : BroadcastReceiver() {
    override fun onReceive(ctx: Context, intent: Intent) {
        val db = NoteDb.get(ctx)
        val n = db.get(intent.getLongExtra(Reminders.EXTRA_ID, 0)) ?: return
        when (intent.action) {
            Reminders.ACTION_FIRE -> if (n.isOpen) Reminders.show(ctx, n)
            Reminders.ACTION_DONE -> {
                n.status = Note.STATUS_DONE; n.dirty = true; db.save(n)
                Reminders.cancel(ctx, n)
                Sync.now(ctx)
            }
            Reminders.ACTION_SNOOZE -> {
                n.reminder = System.currentTimeMillis() + 3_600_000L; n.dirty = true; db.save(n)
                NotificationManagerCompat.from(ctx).cancel(n.id.toInt())
                Reminders.schedule(ctx, n)
                Sync.now(ctx)
            }
        }
    }
}

/** Nach Neustart des Telefons alle Erinnerungen neu setzen */
class BootReceiver : BroadcastReceiver() {
    override fun onReceive(ctx: Context, intent: Intent) {
        if (intent.action != Intent.ACTION_BOOT_COMPLETED && intent.action != Intent.ACTION_MY_PACKAGE_REPLACED) return
        Reminders.rescheduleAll(ctx)
        Sync.now(ctx)
    }
}
