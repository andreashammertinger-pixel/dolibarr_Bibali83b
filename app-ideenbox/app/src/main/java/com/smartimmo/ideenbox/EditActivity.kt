package com.smartimmo.ideenbox

import android.app.Activity
import android.app.AlertDialog
import android.app.DatePickerDialog
import android.app.TimePickerDialog
import android.os.Bundle
import android.view.View
import android.view.WindowManager
import android.widget.Button
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import java.util.Calendar

/** Eingabe per Tastatur (z. B. in Besprechungen) und Bearbeiten vorhandener Einträge */
class EditActivity : Activity() {
    private lateinit var note: Note
    private val edt by lazy { findViewById<EditText>(R.id.edtText) }
    private val btnWhen by lazy { findViewById<Button>(R.id.btnWhen) }
    private val btnIdea by lazy { findViewById<Button>(R.id.btnIdea) }
    private val btnTodo by lazy { findViewById<Button>(R.id.btnTodo) }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_edit)
        val id = intent.getLongExtra(Reminders.EXTRA_ID, 0)
        val existing = if (id > 0) NoteDb.get(this).get(id) else null
        note = existing ?: Note(source = "keyboard", type = intent.getStringExtra("type") ?: Note.TYPE_IDEA)
        title = if (existing != null) "Bearbeiten" else "Neu"

        edt.setText(note.text)
        btnIdea.setOnClickListener { note.type = Note.TYPE_IDEA; refresh() }
        btnTodo.setOnClickListener { note.type = Note.TYPE_TODO; refresh() }
        btnWhen.setOnClickListener { pickDate() }
        findViewById<Button>(R.id.btnQ1).setOnClickListener { note.reminder = MainActivity.roundQuarter(System.currentTimeMillis() + 3_600_000L); refresh() }
        findViewById<Button>(R.id.btnQ2).setOnClickListener { note.reminder = MainActivity.todayAt(17); refresh() }
        findViewById<Button>(R.id.btnQ3).setOnClickListener { note.reminder = MainActivity.tomorrowAt(9); refresh() }
        findViewById<Button>(R.id.btnQ0).setOnClickListener { note.reminder = null; refresh() }
        findViewById<Button>(R.id.btnSave).setOnClickListener { save() }

        val btnDone = findViewById<Button>(R.id.btnDone)
        val btnDelete = findViewById<Button>(R.id.btnDelete)
        if (existing == null) {
            btnDone.visibility = View.GONE; btnDelete.visibility = View.GONE
            edt.requestFocus()
            window.setSoftInputMode(WindowManager.LayoutParams.SOFT_INPUT_STATE_VISIBLE)
        } else {
            btnDone.text = if (note.isOpen) "✔ Erledigt" else "↺ Wieder offen"
            btnDone.setOnClickListener {
                note.status = if (note.isOpen) Note.STATUS_DONE else Note.STATUS_OPEN
                save()
            }
            btnDelete.setOnClickListener {
                AlertDialog.Builder(this).setMessage("Eintrag löschen?")
                    .setPositiveButton("Löschen") { _, _ -> delete() }
                    .setNegativeButton("Abbrechen", null).show()
            }
            val info = StringBuilder("Erfasst ${Fmt.short(note.created)}")
            if (note.assignment.isNotBlank()) info.append("\nZuordnung im Büro: ${note.assignment}")
            info.append(if (note.dirty) "\nNoch nicht an Dolibarr übertragen" else "\nIn Dolibarr gespeichert")
            findViewById<TextView>(R.id.txtInfo).text = info
        }
        refresh()
    }

    private fun refresh() {
        btnIdea.backgroundTintList = getColorStateList(if (note.type == Note.TYPE_IDEA) R.color.idea else R.color.neutral)
        btnIdea.setTextColor(getColor(if (note.type == Note.TYPE_IDEA) R.color.dark_text else R.color.text))
        btnTodo.backgroundTintList = getColorStateList(if (note.type == Note.TYPE_TODO) R.color.todo else R.color.neutral)
        btnWhen.text = note.reminder?.let { "⏰  " + Fmt.short(it) } ?: "kein Termin – antippen zum Wählen"
    }

    private fun pickDate() {
        val c = Calendar.getInstance().apply { timeInMillis = note.reminder ?: MainActivity.tomorrowAt(9) }
        DatePickerDialog(this, { _, y, m, d ->
            TimePickerDialog(this, { _, h, min ->
                c.set(y, m, d, h, min, 0); c.set(Calendar.MILLISECOND, 0)
                note.reminder = c.timeInMillis; refresh()
            }, c.get(Calendar.HOUR_OF_DAY), c.get(Calendar.MINUTE), true).show()
        }, c.get(Calendar.YEAR), c.get(Calendar.MONTH), c.get(Calendar.DAY_OF_MONTH)).show()
    }

    private fun save() {
        val t = edt.text.toString().trim()
        if (t.isEmpty()) {
            Toast.makeText(this, "Bitte etwas eintippen", Toast.LENGTH_SHORT).show(); return
        }
        note.text = t
        note.dirty = true
        NoteDb.get(this).save(note)
        Reminders.schedule(this, note)
        if (!note.isOpen) Reminders.cancel(this, note)
        Sync.now(this)
        Toast.makeText(this, "Gespeichert" + (note.reminder?.let { " · Erinnerung " + Fmt.short(it) } ?: ""), Toast.LENGTH_SHORT).show()
        finish()
    }

    private fun delete() {
        Reminders.cancel(this, note)
        if (note.remoteId == null) NoteDb.get(this).delete(note.id)
        else {
            // in Dolibarr als „verworfen“ markieren statt endgültig löschen
            note.status = Note.STATUS_DISCARDED; note.dirty = true
            NoteDb.get(this).save(note); Sync.now(this)
        }
        finish()
    }
}
