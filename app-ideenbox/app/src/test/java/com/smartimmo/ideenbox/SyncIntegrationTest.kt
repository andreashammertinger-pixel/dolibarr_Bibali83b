package com.smartimmo.ideenbox

import androidx.test.core.app.ApplicationProvider
import androidx.work.testing.WorkManagerTestInitHelper
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assume.assumeTrue
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config

/** Abgleich gegen eine echte Dolibarr-Testinstanz: IDEENBOX_TEST_URL und IDEENBOX_TEST_KEY setzen */
@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34])
class SyncIntegrationTest {
    @Test fun roundTrip() {
        val url = System.getenv("IDEENBOX_TEST_URL"); val key = System.getenv("IDEENBOX_TEST_KEY")
        assumeTrue(url != null && key != null)
        val ctx = ApplicationProvider.getApplicationContext<android.content.Context>()
        WorkManagerTestInitHelper.initializeTestWorkManager(ctx)
        Prefs(ctx).apply { this.url = url!!; apiKey = key!!; lastPull = 0 }
        val db = NoteDb.get(ctx)
        val n = db.save(Note(type = Note.TYPE_TODO, text = "Aus der App: Reifen wechseln", reminder = System.currentTimeMillis() + 7_200_000))
        assertNull(Sync.run(ctx))
        val synced = db.get(n.id)!!
        assertNotNull(synced.remoteId)
        assertEquals(false, synced.dirty)
        // erledigt in der App → Server
        synced.status = Note.STATUS_DONE; synced.dirty = true; db.save(synced)
        assertNull(Sync.run(ctx))
        val o = Dolibarr(url!!, key!!).pull(0).getJSONArray("notes")
        var found = false
        for (i in 0 until o.length()) if (o.getJSONObject(i).getString("uuid") == n.uuid) { found = true; assertEquals(2, o.getJSONObject(i).getInt("status")) }
        assertEquals(true, found)
        // Einträge aus dem Büro kommen aufs Telefon
        assert(db.list(false).any { it.text.contains("Steuerberater") })
    }
}
