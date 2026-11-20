package com.smartimmo.ideenbox

import android.app.Activity
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.provider.Settings
import android.view.View
import android.widget.Button
import android.widget.EditText
import android.widget.Switch
import android.widget.TextView
import kotlin.concurrent.thread

class SettingsActivity : Activity() {
    private val prefs by lazy { Prefs(this) }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_settings)
        val url = findViewById<EditText>(R.id.edtUrl)
        val key = findViewById<EditText>(R.id.edtKey)
        val lang = findViewById<EditText>(R.id.edtLang)
        val speak = findViewById<Switch>(R.id.swSpeak)
        val askIdea = findViewById<Switch>(R.id.swAskIdea)
        val result = findViewById<TextView>(R.id.txtResult)
        url.setText(prefs.url); key.setText(prefs.apiKey); lang.setText(prefs.speechLang)
        speak.isChecked = prefs.speak; askIdea.isChecked = prefs.askReminderForIdeas
        speak.setOnCheckedChangeListener { _, v -> prefs.speak = v }
        askIdea.setOnCheckedChangeListener { _, v -> prefs.askReminderForIdeas = v }

        findViewById<Button>(R.id.btnTest).setOnClickListener {
            var u = url.text.toString().trim()
            if (u.isNotEmpty() && !u.startsWith("http")) u = "https://$u"
            prefs.url = u; prefs.apiKey = key.text.toString()
            prefs.speechLang = lang.text.toString().trim().ifEmpty { "de-DE" }
            result.text = "Teste …"
            thread {
                val msg = try {
                    val r = Dolibarr(prefs.url, prefs.apiKey).ping()
                    prefs.lastPull = 0
                    val err = Sync.run(this)
                    "✔ Verbunden als ${r.optString("user")}" +
                        (if (!r.optBoolean("can_write")) "\n⚠ Benutzer darf keine Ideen erfassen (Recht fehlt)" else "") +
                        (err?.let { "\n⚠ $it" } ?: "\n✔ Abgleich erfolgreich")
                } catch (e: Dolibarr.ApiException) {
                    when (e.code) {
                        401 -> "✖ API-Schlüssel ungültig"
                        403 -> "✖ Keine Berechtigung: Recht „Ideenbox“ beim Benutzer setzen\n${e.message}"
                        404 -> "✖ Modul Ideenbox in Dolibarr nicht gefunden (aktiviert? Modul API aktiv?)"
                        else -> "✖ ${e.message}"
                    }
                } catch (e: Exception) {
                    "✖ Keine Verbindung: ${e.message}"
                }
                runOnUiThread { result.text = msg }
            }
        }

        val btnAlarm = findViewById<Button>(R.id.btnAlarm)
        if (Reminders.canExact(this)) btnAlarm.visibility = View.GONE
        btnAlarm.setOnClickListener {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                startActivity(Intent(Settings.ACTION_REQUEST_SCHEDULE_EXACT_ALARM, Uri.parse("package:$packageName")))
            }
        }
        findViewById<TextView>(R.id.txtVersion).text = "Version " + packageManager.getPackageInfo(packageName, 0).versionName
    }

    override fun onPause() {
        super.onPause()
        prefs.speechLang = findViewById<EditText>(R.id.edtLang).text.toString().trim().ifEmpty { "de-DE" }
    }
}
