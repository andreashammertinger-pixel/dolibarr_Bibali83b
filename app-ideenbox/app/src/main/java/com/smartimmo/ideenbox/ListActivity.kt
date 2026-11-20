package com.smartimmo.ideenbox

import android.app.Activity
import android.content.Intent
import android.os.Bundle
import android.view.View
import android.view.ViewGroup
import android.widget.BaseAdapter
import android.widget.Button
import android.widget.ListView
import android.widget.TextView
import android.widget.Toast
import kotlin.concurrent.thread

class ListActivity : Activity() {
    private var openOnly = true
    private var items: List<Note> = emptyList()
    private val list by lazy { findViewById<ListView>(R.id.list) }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_list)
        findViewById<Button>(R.id.btnOpen).setOnClickListener { openOnly = true; load() }
        findViewById<Button>(R.id.btnAll).setOnClickListener { openOnly = false; load() }
        findViewById<Button>(R.id.btnSync).setOnClickListener {
            Toast.makeText(this, "Abgleich …", Toast.LENGTH_SHORT).show()
            thread {
                val err = Sync.run(this)
                runOnUiThread {
                    Toast.makeText(this, err ?: "Mit Dolibarr abgeglichen", Toast.LENGTH_LONG).show()
                    load()
                }
            }
        }
        list.adapter = adapter
        list.setOnItemClickListener { _, _, pos, _ ->
            startActivity(Intent(this, EditActivity::class.java).putExtra(Reminders.EXTRA_ID, items[pos].id))
        }
        list.setOnItemLongClickListener { _, _, pos, _ ->
            val n = items[pos]
            n.status = if (n.isOpen) Note.STATUS_DONE else Note.STATUS_OPEN
            n.dirty = true
            NoteDb.get(this).save(n)
            if (n.isOpen) Reminders.schedule(this, n) else Reminders.cancel(this, n)
            Sync.now(this)
            Toast.makeText(this, if (n.isOpen) "Wieder offen" else "Erledigt ✔", Toast.LENGTH_SHORT).show()
            load(); true
        }
    }

    override fun onResume() {
        super.onResume()
        load()
    }

    private fun load() {
        items = NoteDb.get(this).list(openOnly)
        findViewById<Button>(R.id.btnOpen).backgroundTintList = getColorStateList(if (openOnly) R.color.accent else R.color.neutral)
        findViewById<Button>(R.id.btnAll).backgroundTintList = getColorStateList(if (!openOnly) R.color.accent else R.color.neutral)
        findViewById<TextView>(R.id.txtEmpty).visibility = if (items.isEmpty()) View.VISIBLE else View.GONE
        adapter.notifyDataSetChanged()
    }

    private val adapter = object : BaseAdapter() {
        override fun getCount() = items.size
        override fun getItem(position: Int) = items[position]
        override fun getItemId(position: Int) = items[position].id
        override fun getView(position: Int, convertView: View?, parent: ViewGroup): View {
            val v = convertView ?: layoutInflater.inflate(R.layout.item_note, parent, false)
            val n = items[position]
            v.findViewById<TextView>(R.id.icon).text = when {
                !n.isOpen -> "✔"
                n.isTodo -> "📋"
                else -> "💡"
            }
            val t = v.findViewById<TextView>(R.id.text)
            t.text = n.text
            t.alpha = if (n.isOpen) 1f else 0.5f
            val meta = mutableListOf<String>()
            n.reminder?.let { meta += (if (n.isOpen && it < System.currentTimeMillis()) "⚠ " else "⏰ ") + Fmt.short(it) }
            if (n.assignment.isNotBlank()) meta += "→ " + n.assignment
            meta += if (n.dirty) "⏳ nicht übertragen" else "☁"
            v.findViewById<TextView>(R.id.meta).text = meta.joinToString("  ·  ")
            return v
        }
    }
}
