package com.smartimmo.ideenbox

import org.json.JSONObject
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL

/** REST-Schnittstelle des Dolibarr-Moduls „Ideenbox“ (/api/index.php/ideenbox/…) */
class Dolibarr(private val baseUrl: String, private val apiKey: String) {

    class ApiException(val code: Int, msg: String) : IOException(msg)

    private fun call(method: String, path: String, body: JSONObject? = null): JSONObject {
        val root = baseUrl.trimEnd('/').removeSuffix("/api/index.php")
        val con = URL("$root/api/index.php/ideenbox/$path").openConnection() as HttpURLConnection
        con.requestMethod = method
        con.connectTimeout = 15_000
        con.readTimeout = 30_000
        con.setRequestProperty("DOLAPIKEY", apiKey)
        con.setRequestProperty("Accept", "application/json")
        if (body != null) {
            con.doOutput = true
            con.setRequestProperty("Content-Type", "application/json; charset=utf-8")
            con.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
        }
        val code = con.responseCode
        val text = (if (code in 200..299) con.inputStream else con.errorStream)?.bufferedReader(Charsets.UTF_8)?.use { it.readText() } ?: ""
        con.disconnect()
        if (code !in 200..299) {
            val msg = try {
                JSONObject(text).optJSONObject("error")?.optString("message") ?: text
            } catch (_: Exception) {
                text.take(200)
            }
            throw ApiException(code, "HTTP $code: $msg")
        }
        return JSONObject(text)
    }

    fun ping(): JSONObject = call("GET", "ping")

    fun pull(since: Long): JSONObject = call("GET", "notes?since=$since&limit=2000")

    fun push(n: Note): JSONObject = if (n.remoteId == null) call("POST", "notes", toJson(n)) else call("PUT", "notes/${n.remoteId}", toJson(n))

    companion object {
        fun toJson(n: Note) = JSONObject().apply {
            put("uuid", n.uuid); put("type", n.type); put("text", n.text)
            put("reminder", n.reminder?.let { it / 1000 } ?: 0)
            put("status", n.status); put("source", n.source); put("created", n.created / 1000)
        }

        /** Server-Datensatz in lokale Notiz übernehmen */
        fun merge(local: Note?, o: JSONObject): Note {
            val n = local ?: Note(uuid = o.optString("uuid").ifBlank { "srv-" + o.getInt("id") }, dirty = false)
            n.remoteId = o.getInt("id")
            n.type = o.optString("type", Note.TYPE_IDEA)
            n.text = o.optString("text", n.text)
            n.reminder = if (o.isNull("reminder")) null else o.getLong("reminder") * 1000
            n.status = o.optInt("status", n.status)
            if (!o.isNull("created")) n.created = o.getLong("created") * 1000
            n.source = o.optString("source", n.source).let { if (it == "null") "" else it }
            n.assignment = o.optString("assignment", "")
            return n
        }
    }
}
