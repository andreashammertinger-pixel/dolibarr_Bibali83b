package com.smartimmo.ideenbox

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test
import java.util.Calendar

class DateParserTest {
    // Donnerstag, 20.11.2026, 10:15
    private fun now(): Calendar = Calendar.getInstance().apply {
        clear(); set(2026, Calendar.NOVEMBER, 20, 10, 15, 0)
    }

    private fun at(y: Int, mo: Int, d: Int, h: Int, mi: Int = 0): Long =
        Calendar.getInstance().apply { clear(); set(y, mo, d, h, mi, 0) }.timeInMillis

    private fun p(s: String) = DateParser.parse(s, now())

    @Test fun morgenUm9() = assertEquals(at(2026, 10, 21, 9), p("morgen um 9"))
    @Test fun morgenUm9Uhr30() = assertEquals(at(2026, 10, 21, 9, 30), p("Morgen um 9 Uhr 30"))
    @Test fun morgenFrueh() = assertEquals(at(2026, 10, 21, 8), p("morgen früh"))
    @Test fun uebermorgen() = assertEquals(at(2026, 10, 22, 9), p("übermorgen"))
    @Test fun heuteAbend() = assertEquals(at(2026, 10, 20, 18), p("heute Abend"))
    @Test fun heuteUm1430() = assertEquals(at(2026, 10, 20, 14, 30), p("heute um 14:30 Uhr"))
    @Test fun um3meint15() = assertEquals(at(2026, 10, 20, 15), p("um 3"))
    @Test fun um8schonVorbei() = assertEquals(at(2026, 10, 21, 8), p("um 8 Uhr"))
    @Test fun halbDrei() = assertEquals(at(2026, 10, 20, 14, 30), p("halb drei"))
    @Test fun freitag() = assertEquals(at(2026, 10, 27, 9), p("am Freitag"))
    @Test fun donnerstagNaechste() = assertEquals(at(2026, 10, 26, 16), p("Donnerstag 16 Uhr"))
    @Test fun montagNachmittag() = assertEquals(at(2026, 10, 23, 15), p("nächsten Montag nachmittags"))
    @Test fun inZweiStunden() = assertEquals(at(2026, 10, 20, 12, 15), p("in zwei Stunden"))
    @Test fun inEinerHalbenStunde() = assertEquals(at(2026, 10, 20, 10, 45), p("in einer halben Stunde"))
    @Test fun in10Minuten() = assertEquals(at(2026, 10, 20, 10, 25), p("in 10 Minuten"))
    @Test fun in3Tagen() = assertEquals(at(2026, 10, 23, 9), p("in drei Tagen"))
    @Test fun datumMonat() = assertEquals(at(2026, 10, 25, 9), p("am 25. November"))
    @Test fun datumMonatUhr() = assertEquals(at(2026, 11, 3, 11), p("3. Dezember um 11 Uhr"))
    @Test fun datumZahl() = assertEquals(at(2026, 10, 28, 10), p("am 28.11. um 10"))
    @Test fun datumVorbeiNaechstesJahr() = assertEquals(at(2027, 0, 15, 9), p("15. Jänner"))
    @Test fun naechsteWoche() = assertEquals(at(2026, 10, 23, 9), p("nächste Woche"))
    @Test fun nichts() = assertNull(p("keine Ahnung"))
    @Test fun nein() = assertNull(p("nein"))
}
