package com.smartimmo.ideenbox

import java.util.Calendar

/**
 * Versteht gesprochene deutsche Zeitangaben, z. B.
 * „morgen um 9“, „Freitag 14 Uhr 30“, „in zwei Stunden“, „übermorgen früh“,
 * „am 25. November“, „25.11. um halb drei“, „nächste Woche“, „heute Abend“.
 */
object DateParser {

    /**
     * Wortgrenze, die auch Umlaute (ü, ä, ö, ß) als Buchstaben behandelt – gleich auf Android (ICU) und Java.
     * Kein „(?U)“: das kennt die Regex-Bibliothek von Android nicht (Absturz).
     */
    private const val WB = "(?:(?<![\\p{L}\\p{N}])(?=[\\p{L}\\p{N}])|(?<=[\\p{L}\\p{N}])(?![\\p{L}\\p{N}]))"
    fun re(p: String) = Regex(p.replace("\\b", WB))

    private const val DEFAULT_HOUR = 9

    private val numberWords = linkedMapOf(
        "einundzwanzig" to 21, "zweiundzwanzig" to 22, "dreiundzwanzig" to 23, "vierundzwanzig" to 24,
        "fünfundzwanzig" to 25, "sechsundzwanzig" to 26, "siebenundzwanzig" to 27, "achtundzwanzig" to 28,
        "neunundzwanzig" to 29, "einunddreißig" to 31, "fünfundvierzig" to 45, "fünfzehn" to 15,
        "sechzehn" to 16, "siebzehn" to 17, "achtzehn" to 18, "neunzehn" to 19, "dreizehn" to 13,
        "vierzehn" to 14, "zwanzig" to 20, "dreißig" to 30, "vierzig" to 40, "fünfzig" to 50,
        "zwölf" to 12, "elf" to 11, "zehn" to 10, "neun" to 9, "acht" to 8, "sieben" to 7,
        "sechs" to 6, "fünf" to 5, "vier" to 4, "drei" to 3, "zwei" to 2, "zwo" to 2,
        "eins" to 1, "einer" to 1, "einem" to 1, "einen" to 1, "eine" to 1, "ein" to 1,
    )

    private val weekdays = mapOf(
        "montag" to Calendar.MONDAY, "dienstag" to Calendar.TUESDAY, "mittwoch" to Calendar.WEDNESDAY,
        "donnerstag" to Calendar.THURSDAY, "freitag" to Calendar.FRIDAY, "samstag" to Calendar.SATURDAY,
        "sonnabend" to Calendar.SATURDAY, "sonntag" to Calendar.SUNDAY,
    )

    private val months = mapOf(
        "januar" to 0, "jänner" to 0, "jaenner" to 0, "februar" to 1, "feber" to 1, "märz" to 2, "maerz" to 2,
        "april" to 3, "mai" to 4, "juni" to 5, "juli" to 6, "august" to 7, "september" to 8,
        "oktober" to 9, "november" to 10, "dezember" to 11,
    )

    /** Normalisiert: Kleinbuchstaben, Zahlwörter → Ziffern */
    fun normalize(input: String): String {
        var s = " " + input.lowercase().replace(re("[,;!?]"), " ").replace(re("\\s+"), " ") + " "
        for ((w, n) in numberWords) {
            s = s.replace(re("(?<=[\\s.])$w(?=[\\s.])"), n.toString())
        }
        return s.replace(re("\\s+"), " ").trim()
    }

    /** @return Zeitpunkt in Millisekunden oder null, wenn keine Zeitangabe erkannt wurde */
    fun parse(input: String, now: Calendar = Calendar.getInstance()): Long? {
        val s = normalize(input)
        if (s.isEmpty()) return null

        // 1) relativ: „in 2 stunden“, „in einer halben stunde“, „in 10 minuten“, „in 3 tagen“
        re("in (?:(\\d+) )?(halbe[n]?|viertel) ?stunde").find(s)?.let { m ->
            val c = now.clone() as Calendar
            c.add(Calendar.MINUTE, if (m.groupValues[2] == "viertel") 15 else 30)
            return c.timeInMillis
        }
        re("in (\\d+)(?: und (?:1 )?halb(?:e[n]?)?)? ?(minute[n]?|min|stunde[n]?|std|tag(?:en)?|woche[n]?|monat(?:en)?)\\b").find(s)?.let { m ->
            val n = m.groupValues[1].toInt()
            val half = m.value.contains("halb")
            val c = now.clone() as Calendar
            val unit = m.groupValues[2]
            when {
                unit.startsWith("min") -> c.add(Calendar.MINUTE, n)
                unit.startsWith("st") -> c.add(Calendar.MINUTE, n * 60 + if (half) 30 else 0)
                unit.startsWith("tag") -> c.add(Calendar.DAY_OF_YEAR, n)
                unit.startsWith("woche") -> c.add(Calendar.DAY_OF_YEAR, 7 * n)
                else -> c.add(Calendar.MONTH, n)
            }
            if (unit.startsWith("tag") || unit.startsWith("woche") || unit.startsWith("monat")) {
                val t = parseTime(s)
                if (t != null) setTime(c, t.first, t.second) else setTime(c, DEFAULT_HOUR, 0)
            }
            return c.timeInMillis
        }

        // 2) Datum
        val date = parseDate(s, now)
        // 3) Uhrzeit
        val time = parseTime(s)

        if (date == null && time == null) return null
        val c = (date ?: now).clone() as Calendar
        if (time != null) {
            setTime(c, time.first, time.second)
            if (date == null && c.timeInMillis <= now.timeInMillis) c.add(Calendar.DAY_OF_YEAR, 1)
        } else {
            setTime(c, DEFAULT_HOUR, 0)
            if (c.timeInMillis <= now.timeInMillis) {
                // „heute“ ohne Uhrzeit → in einer Stunde, volle Viertelstunde
                c.timeInMillis = now.timeInMillis
                c.add(Calendar.HOUR_OF_DAY, 1)
                c.set(Calendar.MINUTE, (c.get(Calendar.MINUTE) / 15) * 15)
                c.set(Calendar.SECOND, 0); c.set(Calendar.MILLISECOND, 0)
            }
        }
        return c.timeInMillis
    }

    private fun setTime(c: Calendar, h: Int, m: Int) {
        c.set(Calendar.HOUR_OF_DAY, h); c.set(Calendar.MINUTE, m)
        c.set(Calendar.SECOND, 0); c.set(Calendar.MILLISECOND, 0)
    }

    private fun startOfDay(now: Calendar, addDays: Int): Calendar {
        val c = now.clone() as Calendar
        c.add(Calendar.DAY_OF_YEAR, addDays)
        setTime(c, 0, 0)
        return c
    }

    private fun parseDate(s: String, now: Calendar): Calendar? {
        if (re("\\bübermorgen\\b|\\buebermorgen\\b").containsMatchIn(s)) return startOfDay(now, 2)
        if (re("(?<!heute )\\bmorgen\\b").containsMatchIn(s)) return startOfDay(now, 1)
        if (re("\\bheute\\b|\\bheut\\b").containsMatchIn(s)) return startOfDay(now, 0)

        // „am 25.11.“, „25.11.2027“
        re("\\b(\\d{1,2})\\.\\s?(\\d{1,2})\\.(?:\\s?(\\d{2,4}))?").find(s)?.let { m ->
            val d = m.groupValues[1].toInt(); val mo = m.groupValues[2].toInt() - 1
            if (d in 1..31 && mo in 0..11) {
                val y = m.groupValues[3].toIntOrNull()?.let { if (it < 100) 2000 + it else it }
                return dateFor(now, d, mo, y)
            }
        }
        // „25. november“, „am 3 dezember“
        re("\\b(\\d{1,2})\\.? ?(${months.keys.joinToString("|")})\\b(?: (\\d{4}))?").find(s)?.let { m ->
            val d = m.groupValues[1].toInt(); val mo = months.getValue(m.groupValues[2])
            return dateFor(now, d, mo, m.groupValues[3].toIntOrNull())
        }
        // Wochentag: „freitag“, „nächsten montag“
        for ((name, wd) in weekdays) {
            if (re("\\b$name\\b").containsMatchIn(s)) {
                var diff = (wd - now.get(Calendar.DAY_OF_WEEK) + 7) % 7
                if (diff == 0) diff = 7
                if (re("(übernächste|uebernächste)").containsMatchIn(s)) diff += 7
                return startOfDay(now, diff)
            }
        }
        // „am 25.“ / „am 25“ (nächster solcher Tag)
        re("\\bam (\\d{1,2})\\.?(?! ?uhr)(?:\\s|$)").find(s)?.let { m ->
            val d = m.groupValues[1].toInt()
            if (d in 1..31) {
                val c = now.clone() as Calendar
                if (d <= c.get(Calendar.DAY_OF_MONTH)) c.add(Calendar.MONTH, 1)
                c.set(Calendar.DAY_OF_MONTH, minOf(d, c.getActualMaximum(Calendar.DAY_OF_MONTH)))
                setTime(c, 0, 0)
                return c
            }
        }
        if (re("ende der woche|ende dieser woche").containsMatchIn(s)) {
            val diff = (Calendar.FRIDAY - now.get(Calendar.DAY_OF_WEEK) + 7) % 7
            return startOfDay(now, diff)
        }
        if (re("\\b(wochenende)\\b").containsMatchIn(s)) {
            var diff = (Calendar.SATURDAY - now.get(Calendar.DAY_OF_WEEK) + 7) % 7
            if (diff == 0) diff = 7
            return startOfDay(now, diff)
        }
        if (re("nächste[nr]? woche|naechste[nr]? woche|kommende[nr]? woche").containsMatchIn(s)) {
            var diff = (Calendar.MONDAY - now.get(Calendar.DAY_OF_WEEK) + 7) % 7
            if (diff == 0) diff = 7
            return startOfDay(now, diff)
        }
        if (re("nächste[nr]? monat|naechste[nr]? monat|kommende[nr]? monat").containsMatchIn(s)) {
            val c = now.clone() as Calendar
            c.add(Calendar.MONTH, 1); c.set(Calendar.DAY_OF_MONTH, 1); setTime(c, 0, 0)
            return c
        }
        return null
    }

    private fun dateFor(now: Calendar, d: Int, mo: Int, year: Int?): Calendar {
        val c = now.clone() as Calendar
        c.set(Calendar.DAY_OF_MONTH, 1)
        if (year != null) c.set(Calendar.YEAR, year)
        c.set(Calendar.MONTH, mo)
        c.set(Calendar.DAY_OF_MONTH, minOf(d, c.getActualMaximum(Calendar.DAY_OF_MONTH)))
        setTime(c, 0, 0)
        if (year == null && c.before(startOfDay(now, 0))) c.add(Calendar.YEAR, 1)
        return c
    }

    /** @return Stunde, Minute */
    private fun parseTime(s: String): Pair<Int, Int>? {
        var h: Int? = null
        var m = 0
        re("\\bhalb (\\d{1,2})\\b").find(s)?.let { h = (it.groupValues[1].toInt() + 23) % 24; m = 30 }
        if (h == null) re("\\bviertel nach (\\d{1,2})\\b").find(s)?.let { h = it.groupValues[1].toInt(); m = 15 }
        if (h == null) re("\\bviertel vor (\\d{1,2})\\b").find(s)?.let { h = (it.groupValues[1].toInt() + 23) % 24; m = 45 }
        if (h == null) re("\\b(\\d{1,2})[:.](\\d{2})\\b(?!\\.)").find(s)?.let {
            // nicht verwechseln mit Datum „25.11.“
            val hh = it.groupValues[1].toInt(); val mm = it.groupValues[2].toInt()
            if (hh in 0..23 && mm in 0..59 && !re("\\b${it.value.replace(".", "\\.")}\\.").containsMatchIn(s)) { h = hh; m = mm }
        }
        if (h == null) re("\\b(\\d{1,2}) ?uhr(?: (\\d{1,2})\\b)?").find(s)?.let {
            h = it.groupValues[1].toInt(); m = it.groupValues[2].toIntOrNull() ?: 0
        }
        if (h == null) re("\\bum (\\d{1,2})\\b(?!\\.)").find(s)?.let { h = it.groupValues[1].toInt() }

        val pm = re("nachmittag|abend|\\bnachts\\b|\\bpm\\b").containsMatchIn(s)
        val am = re("\\bfrüh\\b|\\bfrueh\\b|morgens|vormittag|\\bam morgen\\b|heute morgen").containsMatchIn(s)
        if (h == null) {
            return when {
                re("vormittag").containsMatchIn(s) -> 10 to 0
                re("\\bfrüh\\b|\\bfrueh\\b|morgens|heute morgen").containsMatchIn(s) -> 8 to 0
                re("mittag").containsMatchIn(s) && !re("nachmittag").containsMatchIn(s) -> 12 to 0
                re("nachmittag").containsMatchIn(s) -> 15 to 0
                re("abend").containsMatchIn(s) -> 18 to 0
                re("\\bnachts\\b").containsMatchIn(s) -> 21 to 0
                else -> null
            }
        }
        var hour = h!!
        if (hour !in 0..24 || m !in 0..59) return null
        if (hour == 24) hour = 0
        if (pm && hour in 1..11) hour += 12
        else if (!am && hour in 1..6) hour += 12 // „um 3“ meint meist 15 Uhr
        return hour to m
    }
}
