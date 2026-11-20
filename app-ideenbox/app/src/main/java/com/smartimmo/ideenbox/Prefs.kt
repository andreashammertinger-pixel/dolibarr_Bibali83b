package com.smartimmo.ideenbox

import android.content.Context

class Prefs(ctx: Context) {
    private val sp = ctx.applicationContext.getSharedPreferences("ideenbox", Context.MODE_PRIVATE)

    var url: String
        get() = sp.getString("url", "") ?: ""
        set(v) = sp.edit().putString("url", v.trim().trimEnd('/')).apply()
    var apiKey: String
        get() = sp.getString("apikey", "") ?: ""
        set(v) = sp.edit().putString("apikey", v.trim()).apply()
    var speak: Boolean
        get() = sp.getBoolean("speak", true)
        set(v) = sp.edit().putBoolean("speak", v).apply()
    var askReminderForIdeas: Boolean
        get() = sp.getBoolean("ask_reminder_idea", true)
        set(v) = sp.edit().putBoolean("ask_reminder_idea", v).apply()
    var speechLang: String
        get() = sp.getString("lang", "de-DE") ?: "de-DE"
        set(v) = sp.edit().putString("lang", v).apply()
    var lastPull: Long
        get() = sp.getLong("last_pull", 0)
        set(v) = sp.edit().putLong("last_pull", v).apply()
    var lastSyncOk: Long
        get() = sp.getLong("last_sync_ok", 0)
        set(v) = sp.edit().putLong("last_sync_ok", v).apply()
    var lastSyncError: String
        get() = sp.getString("last_sync_error", "") ?: ""
        set(v) = sp.edit().putString("last_sync_error", v).apply()

    val configured get() = url.isNotBlank() && apiKey.isNotBlank()
}
