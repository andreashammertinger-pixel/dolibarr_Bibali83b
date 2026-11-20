package com.smartimmo.ideenbox

import android.Manifest
import android.app.Activity
import android.content.Intent
import android.content.pm.PackageManager
import android.content.res.Configuration
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.View
import android.widget.Button
import android.widget.LinearLayout
import android.widget.TextView
import java.util.Calendar

class MainActivity : Activity(), VoiceDialog.Ui {

    private lateinit var dialog: VoiceDialog
    private val main = Handler(Looper.getMainLooper())
    private var pendingStart: String? = null
    private var lastSaved: Note? = null

    private val idlePanel by lazy { findViewById<View>(R.id.idlePanel) }
    private val dialogPanel by lazy { findViewById<View>(R.id.dialogPanel) }
    private val txtPrompt by lazy { findViewById<TextView>(R.id.txtPrompt) }
    private val txtHeard by lazy { findViewById<TextView>(R.id.txtHeard) }
    private val txtMic by lazy { findViewById<TextView>(R.id.txtMic) }
    private val txtType by lazy { findViewById<TextView>(R.id.txtType) }
    private val txtStatus by lazy { findViewById<TextView>(R.id.txtStatus) }
    private val rowTimes by lazy { findViewById<View>(R.id.rowTimes) }
    private val btnYes by lazy { findViewById<Button>(R.id.btnYes) }
    private val btnNo by lazy { findViewById<Button>(R.id.btnNo) }
    private val btnCancel by lazy { findViewById<Button>(R.id.btnCancel) }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)
        dialog = VoiceDialog(this, this)

        findViewById<Button>(R.id.btnIdea).setOnClickListener { startVoice(Note.TYPE_IDEA) }
        findViewById<Button>(R.id.btnTodo).setOnClickListener { startVoice(Note.TYPE_TODO) }
        findViewById<Button>(R.id.btnType).setOnClickListener { startActivity(Intent(this, EditActivity::class.java)) }
        findViewById<Button>(R.id.btnList).setOnClickListener { startActivity(Intent(this, ListActivity::class.java)) }
        findViewById<Button>(R.id.btnSettings).setOnClickListener { startActivity(Intent(this, SettingsActivity::class.java)) }

        btnYes.setOnClickListener {
            if (lastSaved != null) backToIdle() else if (btnYes.tag == "done") dialog.finishDictation() else dialog.yes()
        }
        btnNo.setOnClickListener { dialog.no() }
        btnCancel.setOnClickListener {
            val n = lastSaved
            if (n != null) {
                // Rückgängig: gerade gespeicherten Eintrag wieder entfernen
                Reminders.cancel(this, n)
                if (n.remoteId == null && NoteDb.get(this).get(n.id)?.remoteId == null) NoteDb.get(this).delete(n.id)
                else NoteDb.get(this).get(n.id)?.let { it.status = Note.STATUS_DISCARDED; it.dirty = true; NoteDb.get(this).save(it); Sync.now(this) }
                lastSaved = null
                txtPrompt.text = "Rückgängig gemacht"
                main.postDelayed({ backToIdle() }, 1200)
            } else dialog.cancel()
        }
        findViewById<Button>(R.id.btnT1).setOnClickListener { dialog.chooseTime(roundQuarter(System.currentTimeMillis() + 3_600_000L)) }
        findViewById<Button>(R.id.btnT2).setOnClickListener { dialog.chooseTime(todayAt(17)) }
        findViewById<Button>(R.id.btnT3).setOnClickListener { dialog.chooseTime(tomorrowAt(9)) }

        applyOrientation(resources.configuration)
        askPermissions()
        Sync.schedulePeriodic(this)
        handleIntent(intent)
        showLastCrash()
    }

    /** Falls die App zuvor abgestürzt ist: Fehlertext anzeigen (zum Weiterleiten an den Entwickler) */
    private fun showLastCrash() {
        val f = java.io.File(filesDir, App.CRASH_FILE)
        if (!f.exists()) return
        val txt = f.readText()
        f.delete()
        android.app.AlertDialog.Builder(this)
            .setTitle("Die App ist beim letzten Mal abgestürzt")
            .setMessage(txt.take(1500))
            .setPositiveButton("Fehler teilen") { _, _ ->
                startActivity(Intent.createChooser(Intent(Intent.ACTION_SEND).setType("text/plain")
                    .putExtra(Intent.EXTRA_SUBJECT, "Ideenbox Fehlerbericht").putExtra(Intent.EXTRA_TEXT, txt), "Fehler teilen"))
            }
            .setNegativeButton("Schließen", null).show()
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        handleIntent(intent)
    }

    private fun handleIntent(i: Intent?) {
        when (i?.action) {
            ACTION_IDEA -> startVoice(Note.TYPE_IDEA)
            ACTION_TODO -> startVoice(Note.TYPE_TODO)
        }
    }

    override fun onResume() {
        super.onResume()
        refreshStatus()
        Sync.now(this)
    }

    override fun onConfigurationChanged(newConfig: Configuration) {
        super.onConfigurationChanged(newConfig)
        applyOrientation(newConfig)
    }

    /** Im Querformat (Halterung im Auto) liegen die zwei Schaltflächen nebeneinander */
    private fun applyOrientation(c: Configuration) {
        val row = findViewById<LinearLayout>(R.id.bigRow)
        val land = c.orientation == Configuration.ORIENTATION_LANDSCAPE
        row.orientation = if (land) LinearLayout.HORIZONTAL else LinearLayout.VERTICAL
        for (id in listOf(R.id.btnIdea, R.id.btnTodo)) {
            val b = findViewById<Button>(id)
            b.layoutParams = LinearLayout.LayoutParams(if (land) 0 else -1, if (land) -1 else 0, 1f).apply { setMargins(12, 12, 12, 12) }
        }
    }

    override fun onDestroy() {
        dialog.destroy()
        super.onDestroy()
    }

    private fun refreshStatus() {
        val db = NoteDb.get(this)
        val prefs = Prefs(this)
        val open = db.countOpen()
        val dirty = db.countDirty()
        findViewById<Button>(R.id.btnList).text = "☰  Liste ($open)"
        txtStatus.text = when {
            !prefs.configured -> "Dolibarr nicht verbunden – ⚙"
            dirty > 0 -> "$dirty noch nicht übertragen" + (if (prefs.lastSyncError.isNotBlank()) " · ${prefs.lastSyncError.take(40)}" else "")
            prefs.lastSyncOk > 0 -> "☁ mit Dolibarr abgeglichen ${Fmt.short(prefs.lastSyncOk)}"
            else -> "☁ verbunden"
        }
    }

    // --------------------------------------------------------------- Sprache

    private fun startVoice(type: String) {
        if (checkSelfPermission(Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
            pendingStart = type
            requestPermissions(arrayOf(Manifest.permission.RECORD_AUDIO), REQ_AUDIO)
            return
        }
        main.removeCallbacksAndMessages(null)
        lastSaved = null
        idlePanel.visibility = View.GONE
        dialogPanel.visibility = View.VISIBLE
        txtHeard.text = ""
        txtType.text = if (type == Note.TYPE_TODO) "✅ TODO" else "💡 IDEE"
        txtType.setTextColor(getColor(if (type == Note.TYPE_TODO) R.color.todo else R.color.idea))
        btnCancel.text = "✕  Abbrechen"
        dialog.start(type)
    }

    private fun backToIdle() {
        main.removeCallbacksAndMessages(null)
        lastSaved = null
        dialogPanel.visibility = View.GONE
        idlePanel.visibility = View.VISIBLE
        refreshStatus()
    }

    override fun onPrompt(text: String) { txtPrompt.text = text }

    override fun onHeard(text: String, partial: Boolean) {
        txtHeard.text = text
        txtHeard.alpha = if (partial) 0.7f else 1f
    }

    override fun onListening(active: Boolean) {
        txtMic.visibility = if (active) View.VISIBLE else View.INVISIBLE
    }

    override fun onAsk(kind: VoiceDialog.Ask) {
        btnYes.tag = null
        when (kind) {
            VoiceDialog.Ask.TEXT -> {
                rowTimes.visibility = View.GONE
                btnYes.visibility = View.VISIBLE; btnYes.text = "FERTIG"; btnYes.tag = "done"
                btnNo.visibility = View.GONE
            }
            VoiceDialog.Ask.YES_NO -> {
                rowTimes.visibility = View.VISIBLE
                btnYes.visibility = View.VISIBLE; btnYes.text = "JA"
                btnNo.visibility = View.VISIBLE; btnNo.text = "NEIN"
            }
            VoiceDialog.Ask.WHEN -> {
                rowTimes.visibility = View.VISIBLE
                btnYes.visibility = View.GONE
                btnNo.visibility = View.VISIBLE; btnNo.text = "OHNE TERMIN"
            }
            VoiceDialog.Ask.CONFIRM -> {
                rowTimes.visibility = View.GONE
                btnYes.visibility = View.VISIBLE; btnYes.text = "JA"
                btnNo.visibility = View.VISIBLE; btnNo.text = "NEIN"
            }
            VoiceDialog.Ask.NONE -> {}
        }
    }

    override fun onFinished(note: Note?, message: String) {
        txtMic.visibility = View.INVISIBLE
        rowTimes.visibility = View.GONE
        btnNo.visibility = View.GONE
        txtPrompt.text = (if (note != null) "✔ " else "") + message
        if (note != null) {
            lastSaved = note
            btnYes.visibility = View.VISIBLE; btnYes.text = "OK"; btnYes.tag = null
            btnCancel.text = "↶  Rückgängig"
            main.postDelayed({ backToIdle() }, 7000)
        } else {
            btnYes.visibility = View.GONE
            main.postDelayed({ backToIdle() }, 2500)
        }
    }

    // --------------------------------------------------------------- Rechte

    private fun askPermissions() {
        val need = mutableListOf<String>()
        if (checkSelfPermission(Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) need += Manifest.permission.RECORD_AUDIO
        if (Build.VERSION.SDK_INT >= 33 && checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) need += Manifest.permission.POST_NOTIFICATIONS
        if (need.isNotEmpty()) requestPermissions(need.toTypedArray(), REQ_INIT)
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        val p = pendingStart
        pendingStart = null
        if (requestCode == REQ_AUDIO && p != null && checkSelfPermission(Manifest.permission.RECORD_AUDIO) == PackageManager.PERMISSION_GRANTED) startVoice(p)
    }

    companion object {
        const val ACTION_IDEA = "com.smartimmo.ideenbox.IDEA"
        const val ACTION_TODO = "com.smartimmo.ideenbox.TODO"
        private const val REQ_AUDIO = 1
        private const val REQ_INIT = 2

        fun roundQuarter(t: Long): Long {
            val c = Calendar.getInstance().apply { timeInMillis = t }
            c.set(Calendar.MINUTE, (c.get(Calendar.MINUTE) + 14) / 15 * 15)
            c.set(Calendar.SECOND, 0); c.set(Calendar.MILLISECOND, 0)
            return c.timeInMillis
        }

        fun todayAt(h: Int): Long {
            val c = Calendar.getInstance()
            c.set(Calendar.HOUR_OF_DAY, h); c.set(Calendar.MINUTE, 0); c.set(Calendar.SECOND, 0); c.set(Calendar.MILLISECOND, 0)
            if (c.timeInMillis <= System.currentTimeMillis()) c.add(Calendar.DAY_OF_YEAR, 1)
            return c.timeInMillis
        }

        fun tomorrowAt(h: Int): Long {
            val c = Calendar.getInstance()
            c.add(Calendar.DAY_OF_YEAR, 1)
            c.set(Calendar.HOUR_OF_DAY, h); c.set(Calendar.MINUTE, 0); c.set(Calendar.SECOND, 0); c.set(Calendar.MILLISECOND, 0)
            return c.timeInMillis
        }
    }
}
