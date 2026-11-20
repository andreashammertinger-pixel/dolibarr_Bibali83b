package com.smartimmo.ideenbox

import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale

/** Datumsangaben zum Anzeigen und zum Vorlesen */
object Fmt {
    private val de = Locale.GERMAN

    private fun dayDiff(t: Long, now: Long): Int {
        val a = Calendar.getInstance().apply { timeInMillis = now; set(Calendar.HOUR_OF_DAY, 12); set(Calendar.MINUTE, 0) }
        val b = Calendar.getInstance().apply { timeInMillis = t; set(Calendar.HOUR_OF_DAY, 12); set(Calendar.MINUTE, 0) }
        return Math.round((b.timeInMillis - a.timeInMillis) / 86_400_000.0).toInt()
    }

    private fun dayWord(t: Long, now: Long): String? = when (dayDiff(t, now)) {
        0 -> "heute"; 1 -> "morgen"; 2 -> "übermorgen"; -1 -> "gestern"; else -> null
    }

    /** „morgen, 09:00“ / „Fr 27.11., 14:30“ */
    fun short(t: Long, now: Long = System.currentTimeMillis()): String {
        val time = SimpleDateFormat("HH:mm", de).format(t)
        val dw = dayWord(t, now)
        if (dw != null) return "$dw, $time"
        val sameYear = Calendar.getInstance().apply { timeInMillis = t }.get(Calendar.YEAR) ==
            Calendar.getInstance().apply { timeInMillis = now }.get(Calendar.YEAR)
        return SimpleDateFormat(if (sameYear) "EE dd.MM., HH:mm" else "EE dd.MM.yyyy, HH:mm", de).format(t)
    }

    /** zum Vorlesen: „morgen um 9 Uhr“, „Freitag, den 27. November um 14 Uhr 30“ */
    fun spoken(t: Long, now: Long = System.currentTimeMillis()): String {
        val c = Calendar.getInstance().apply { timeInMillis = t }
        val h = c.get(Calendar.HOUR_OF_DAY); val m = c.get(Calendar.MINUTE)
        val time = if (m == 0) "um $h Uhr" else "um $h Uhr $m"
        val dw = dayWord(t, now)
        val diff = dayDiff(t, now)
        val day = when {
            dw != null -> dw
            diff in 2..6 -> SimpleDateFormat("EEEE", de).format(t)
            else -> SimpleDateFormat("EEEE, 'den' d. MMMM", de).format(t)
        }
        return "$day $time"
    }
}
