package com.smartimmo.ideenbox

import android.content.ContentValues
import android.content.Context
import android.database.Cursor
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper
import java.util.UUID

/** Eine Idee oder ein ToDo (lokal gespeichert, wird mit Dolibarr abgeglichen) */
data class Note(
    var id: Long = 0,
    var uuid: String = UUID.randomUUID().toString(),
    var type: String = TYPE_IDEA,
    var text: String = "",
    var reminder: Long? = null,      // Millisekunden
    var status: Int = STATUS_OPEN,
    var source: String = "voice",
    var created: Long = System.currentTimeMillis(),
    var remoteId: Int? = null,
    var dirty: Boolean = true,        // noch nicht an Dolibarr übertragen
    var assignment: String = "",      // Zuordnung aus dem Büro (Firma › Partner › Projekt)
) {
    val isTodo get() = type == TYPE_TODO
    val isOpen get() = status == STATUS_OPEN || status == STATUS_ASSIGNED

    companion object {
        const val TYPE_IDEA = "idea"
        const val TYPE_TODO = "todo"
        const val STATUS_OPEN = 0
        const val STATUS_ASSIGNED = 1
        const val STATUS_DONE = 2
        const val STATUS_DISCARDED = 9
    }
}

class NoteDb private constructor(ctx: Context) : SQLiteOpenHelper(ctx, "ideenbox.db", null, 1) {

    override fun onCreate(db: SQLiteDatabase) {
        db.execSQL(
            """CREATE TABLE note (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT NOT NULL UNIQUE,
                type TEXT NOT NULL,
                text TEXT NOT NULL,
                reminder INTEGER,
                status INTEGER NOT NULL DEFAULT 0,
                source TEXT,
                created INTEGER NOT NULL,
                remote_id INTEGER,
                dirty INTEGER NOT NULL DEFAULT 1,
                assignment TEXT
            )"""
        )
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {}

    private fun values(n: Note) = ContentValues().apply {
        put("uuid", n.uuid); put("type", n.type); put("text", n.text)
        if (n.reminder != null) put("reminder", n.reminder) else putNull("reminder")
        put("status", n.status); put("source", n.source); put("created", n.created)
        if (n.remoteId != null) put("remote_id", n.remoteId) else putNull("remote_id")
        put("dirty", if (n.dirty) 1 else 0); put("assignment", n.assignment)
    }

    private fun read(c: Cursor) = Note(
        id = c.getLong(c.getColumnIndexOrThrow("id")),
        uuid = c.getString(c.getColumnIndexOrThrow("uuid")),
        type = c.getString(c.getColumnIndexOrThrow("type")),
        text = c.getString(c.getColumnIndexOrThrow("text")),
        reminder = c.getColumnIndexOrThrow("reminder").let { if (c.isNull(it)) null else c.getLong(it) },
        status = c.getInt(c.getColumnIndexOrThrow("status")),
        source = c.getString(c.getColumnIndexOrThrow("source")) ?: "",
        created = c.getLong(c.getColumnIndexOrThrow("created")),
        remoteId = c.getColumnIndexOrThrow("remote_id").let { if (c.isNull(it)) null else c.getInt(it) },
        dirty = c.getInt(c.getColumnIndexOrThrow("dirty")) == 1,
        assignment = c.getString(c.getColumnIndexOrThrow("assignment")) ?: "",
    )

    @Synchronized
    fun save(n: Note): Note {
        if (n.id == 0L) n.id = writableDatabase.insertOrThrow("note", null, values(n))
        else writableDatabase.update("note", values(n), "id = ?", arrayOf(n.id.toString()))
        return n
    }

    @Synchronized
    fun delete(id: Long) {
        writableDatabase.delete("note", "id = ?", arrayOf(id.toString()))
    }

    private fun query(where: String?, args: Array<String>?, order: String): List<Note> {
        val out = ArrayList<Note>()
        readableDatabase.query("note", null, where, args, null, null, order).use { c ->
            while (c.moveToNext()) out.add(read(c))
        }
        return out
    }

    fun get(id: Long) = query("id = ?", arrayOf(id.toString()), "id").firstOrNull()
    fun byUuid(uuid: String) = query("uuid = ?", arrayOf(uuid), "id").firstOrNull()
    fun dirty() = query("dirty = 1", null, "created")
    fun withReminder() = query("reminder IS NOT NULL AND status IN (0,1)", null, "reminder")

    /** offene zuerst: ToDos mit Termin nach Termin, dann neueste */
    fun list(openOnly: Boolean): List<Note> = query(
        if (openOnly) "status IN (0,1)" else "status <> 9", null,
        "CASE WHEN status IN (0,1) THEN 0 ELSE 1 END, CASE WHEN reminder IS NULL THEN 1 ELSE 0 END, reminder, created DESC"
    )

    fun countOpen(): Int = readableDatabase.rawQuery("SELECT COUNT(*) FROM note WHERE status IN (0,1)", null).use {
        it.moveToFirst(); it.getInt(0)
    }

    fun countDirty(): Int = readableDatabase.rawQuery("SELECT COUNT(*) FROM note WHERE dirty = 1", null).use {
        it.moveToFirst(); it.getInt(0)
    }

    companion object {
        @Volatile private var inst: NoteDb? = null
        fun get(ctx: Context): NoteDb = inst ?: synchronized(this) {
            inst ?: NoteDb(ctx.applicationContext).also { inst = it }
        }
    }
}
