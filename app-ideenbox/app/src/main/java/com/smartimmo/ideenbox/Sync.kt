package com.smartimmo.ideenbox

import android.content.Context
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.util.concurrent.TimeUnit

/** Abgleich mit Dolibarr: sobald Internet da ist, sonst später automatisch */
object Sync {
    private val net = Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build()

    fun now(ctx: Context) {
        if (!Prefs(ctx).configured) return
        try {
            val req = OneTimeWorkRequestBuilder<SyncWorker>().setConstraints(net).build()
            WorkManager.getInstance(ctx).enqueueUniqueWork("sync", ExistingWorkPolicy.REPLACE, req)
        } catch (e: Exception) {
            Prefs(ctx).lastSyncError = "Abgleich nicht möglich: ${e.message}"
        }
    }

    fun schedulePeriodic(ctx: Context) {
        try {
            val req = PeriodicWorkRequestBuilder<SyncWorker>(1, TimeUnit.HOURS).setConstraints(net).build()
            WorkManager.getInstance(ctx).enqueueUniquePeriodicWork("sync-periodic", ExistingPeriodicWorkPolicy.KEEP, req)
        } catch (_: Exception) {
        }
    }

    /** @return Fehlermeldung oder null */
    fun run(ctx: Context): String? {
        val prefs = Prefs(ctx)
        if (!prefs.configured) return "Dolibarr ist nicht eingerichtet"
        val api = Dolibarr(prefs.url, prefs.apiKey)
        val db = NoteDb.get(ctx)
        return try {
            for (n in db.dirty()) {
                val res = api.push(n)
                val fresh = db.get(n.id) ?: continue
                fresh.remoteId = res.getInt("id")
                fresh.assignment = res.optString("assignment", fresh.assignment)
                // nur als übertragen markieren, wenn sich lokal inzwischen nichts geändert hat
                if (fresh.text == n.text && fresh.status == n.status && fresh.reminder == n.reminder && fresh.type == n.type) fresh.dirty = false
                db.save(fresh)
            }
            val res = api.pull(prefs.lastPull)
            val arr = res.getJSONArray("notes")
            for (i in 0 until arr.length()) {
                val o = arr.getJSONObject(i)
                val local = db.byUuid(o.optString("uuid").ifBlank { "srv-" + o.getInt("id") })
                if (local != null && local.dirty) continue
                val n = db.save(Dolibarr.merge(local, o).also { it.dirty = false })
                Reminders.schedule(ctx, n)
                if (!n.isOpen) Reminders.cancel(ctx, n)
            }
            prefs.lastPull = res.optLong("now", 0) - 5 // kleine Überlappung
            prefs.lastSyncOk = System.currentTimeMillis()
            prefs.lastSyncError = ""
            null
        } catch (e: Exception) {
            prefs.lastSyncError = e.message ?: e.javaClass.simpleName
            prefs.lastSyncError
        }
    }
}

class SyncWorker(ctx: Context, params: WorkerParameters) : CoroutineWorker(ctx, params) {
    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        val err = Sync.run(applicationContext)
        when {
            err == null -> Result.success()
            err.startsWith("HTTP 401") || err.startsWith("HTTP 403") || err.contains("nicht eingerichtet") -> Result.failure()
            else -> Result.retry()
        }
    }
}
