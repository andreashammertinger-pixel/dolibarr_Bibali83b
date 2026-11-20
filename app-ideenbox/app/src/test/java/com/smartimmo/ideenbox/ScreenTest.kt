package com.smartimmo.ideenbox

import android.app.Activity
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.Canvas
import android.widget.Button
import android.widget.TextView
import androidx.test.core.app.ApplicationProvider
import androidx.work.testing.WorkManagerTestInitHelper
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.Robolectric
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config
import org.robolectric.annotation.GraphicsMode
import java.io.File
import java.io.FileOutputStream

/** Startet die Bildschirme und legt Screenshots unter build/screens ab */
@RunWith(RobolectricTestRunner::class)
@GraphicsMode(GraphicsMode.Mode.NATIVE)
@Config(sdk = [34], qualifiers = "w411dp-h891dp-xxhdpi")
class ScreenTest {

    @Before fun init() {
        WorkManagerTestInitHelper.initializeTestWorkManager(ApplicationProvider.getApplicationContext())
    }

    private fun shot(a: Activity, name: String) {
        val v = a.window.decorView
        v.measure(android.view.View.MeasureSpec.makeMeasureSpec(1233, android.view.View.MeasureSpec.EXACTLY),
            android.view.View.MeasureSpec.makeMeasureSpec(2673, android.view.View.MeasureSpec.EXACTLY))
        v.layout(0, 0, 1233, 2673)
        val b = Bitmap.createBitmap(v.width, v.height, Bitmap.Config.ARGB_8888)
        v.draw(Canvas(b))
        val dir = File("build/screens").apply { mkdirs() }
        FileOutputStream(File(dir, "$name.png")).use { b.compress(Bitmap.CompressFormat.PNG, 100, it) }
    }

    @Test fun main() {
        val ctx = ApplicationProvider.getApplicationContext<android.content.Context>()
        NoteDb.get(ctx).save(Note(type = Note.TYPE_TODO, text = "Angebot Dachsanierung einholen", reminder = System.currentTimeMillis() + 86_400_000))
        val a = Robolectric.buildActivity(MainActivity::class.java).setup().get()
        assertTrue(a.findViewById<Button>(R.id.btnList).text.startsWith("☰  Liste ("))
        shot(a, "1_start")
        // Dialog-Ansicht wie während einer Rückfrage
        a.findViewById<android.view.View>(R.id.idlePanel).visibility = android.view.View.GONE
        a.findViewById<android.view.View>(R.id.dialogPanel).visibility = android.view.View.VISIBLE
        a.findViewById<TextView>(R.id.txtType).text = "✅ TODO"
        a.onPrompt("Notiert. Bis wann?")
        a.onHeard("Angebot für die Dachsanierung bei drei Firmen einholen", false)
        a.onListening(true)
        a.onAsk(VoiceDialog.Ask.WHEN)
        shot(a, "2_dialog_wann")
        a.onPrompt("Freitag um 9 Uhr. Passt das?")
        a.onAsk(VoiceDialog.Ask.CONFIRM)
        shot(a, "3_dialog_bestaetigen")
    }

    @Test fun edit() {
        val a = Robolectric.buildActivity(EditActivity::class.java, Intent().putExtra("type", Note.TYPE_TODO)).setup().get()
        a.findViewById<android.widget.EditText>(R.id.edtText).setText("Steuerberater anrufen wegen Jahresabschluss")
        a.findViewById<Button>(R.id.btnQ3).performClick()
        shot(a, "4_tippen")
        a.findViewById<Button>(R.id.btnSave).performClick()
        val saved = NoteDb.get(a).list(true).first()
        assertEquals(Note.TYPE_TODO, saved.type)
        assertTrue(saved.reminder != null && saved.dirty)
    }

    @Test fun list() {
        val ctx = ApplicationProvider.getApplicationContext<android.content.Context>()
        val db = NoteDb.get(ctx)
        db.save(Note(type = Note.TYPE_TODO, text = "Reifenwechsel Firmenwagen", reminder = System.currentTimeMillis() - 3_600_000))
        db.save(Note(type = Note.TYPE_IDEA, text = "Photovoltaik auf Haus B prüfen – Förderung abklären", dirty = false, remoteId = 5, assignment = "SmartImmo Holding › PJ2611-0001 Sanierung Haus A"))
        db.save(Note(type = Note.TYPE_TODO, text = "Angebot Dachsanierung einholen", reminder = System.currentTimeMillis() + 2 * 86_400_000))
        val a = Robolectric.buildActivity(ListActivity::class.java).setup().get()
        shot(a, "5_liste")
    }

    @Test fun settings() {
        val a = Robolectric.buildActivity(SettingsActivity::class.java).setup().get()
        shot(a, "6_einstellungen")
    }
}
