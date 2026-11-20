package com.smartimmo.ideenbox

import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class AnswerTest {
    @Test fun ja() { for (s in listOf("ja", "Ja bitte", "gerne", "okay", "passt", "genau so")) assertTrue(s, VoiceDialog.isYes(s)) }
    @Test fun nein() { for (s in listOf("nein", "nee danke", "nö", "keine", "ohne Termin", "nicht nötig")) assertTrue(s, VoiceDialog.isNo(s)) }
    @Test fun neinIstKeinJa() { assertFalse(VoiceDialog.isYes("nein danke bitte nicht")) }
    @Test fun json() {
        val n = Note(type = Note.TYPE_TODO, text = "Test", reminder = 1_800_000_000_000L)
        val j = Dolibarr.toJson(n)
        assertTrue(j.getLong("reminder") == 1_800_000_000L && j.getString("type") == "todo")
    }
}
