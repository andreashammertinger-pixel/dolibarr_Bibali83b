package com.smartimmo.ideenbox

import android.app.Application
import androidx.work.Configuration
import java.io.File

class App : Application(), Configuration.Provider {

    // WorkManager wird bei Bedarf hier eingerichtet (nicht beim Start über androidx.startup)
    override val workManagerConfiguration: Configuration
        get() = Configuration.Builder().build()

    override fun onCreate() {
        super.onCreate()
        // Absturz merken, damit er beim nächsten Start angezeigt werden kann
        val prev = Thread.getDefaultUncaughtExceptionHandler()
        Thread.setDefaultUncaughtExceptionHandler { t, e ->
            try {
                File(filesDir, CRASH_FILE).writeText(android.util.Log.getStackTraceString(e).take(6000))
            } catch (_: Exception) {
            }
            prev?.uncaughtException(t, e)
        }
        try {
            Reminders.createChannel(this)
        } catch (_: Exception) {
        }
    }

    companion object {
        const val CRASH_FILE = "letzter_fehler.txt"
    }
}
