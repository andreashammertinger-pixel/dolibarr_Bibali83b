package com.smartimmo.ideenbox

import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.speech.RecognitionListener
import android.speech.RecognizerIntent
import android.speech.SpeechRecognizer
import android.speech.tts.TextToSpeech
import android.speech.tts.UtteranceProgressListener
import java.util.Locale

/**
 * Freihändiger Ablauf im Auto:
 *  Idee:  „Ich höre.“ → Diktat → „Notiert: … Möchtest du eine Erinnerung?“ → ja/nein/Zeitangabe → bestätigen → speichern
 *  ToDo:  „Was darfst du nicht vergessen?“ → Diktat → „Bis wann?“ → Zeitangabe/„ohne“ → bestätigen → speichern
 * Jede Frage kann auch mit den großen Schaltflächen beantwortet werden.
 */
class VoiceDialog(private val ctx: Context, private val ui: Ui) {

    interface Ui {
        fun onPrompt(text: String)
        fun onHeard(text: String, partial: Boolean)
        fun onListening(active: Boolean)
        fun onAsk(kind: Ask)
        fun onFinished(note: Note?, message: String)
    }

    enum class Ask { NONE, TEXT, YES_NO, WHEN, CONFIRM }
    private enum class Step { IDLE, TEXT, ASK_REMINDER, WHEN, CONFIRM }

    private val prefs = Prefs(ctx)
    private val main = Handler(Looper.getMainLooper())
    private var tts: TextToSpeech? = null
    private var ttsReady = false
    private var recognizer: SpeechRecognizer? = null
    private var step = Step.IDLE
    private var retries = 0
    private var whenTries = 0
    private var pendingAfterSpeak: (() -> Unit)? = null
    private var listening = false

    private var type = Note.TYPE_IDEA
    private var text = ""
    private var reminder: Long? = null

    val active get() = step != Step.IDLE

    init {
        tts = TextToSpeech(ctx.applicationContext) { status ->
            if (status == TextToSpeech.SUCCESS) {
                tts?.language = Locale.forLanguageTag(prefs.speechLang)
                tts?.setAudioAttributes(
                    AudioAttributes.Builder().setUsage(AudioAttributes.USAGE_ASSISTANT)
                        .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH).build()
                )
                tts?.setOnUtteranceProgressListener(object : UtteranceProgressListener() {
                    override fun onStart(id: String?) {}
                    override fun onDone(id: String?) { main.post { afterSpeak() } }
                    @Deprecated("Deprecated in Java")
                    override fun onError(id: String?) { main.post { afterSpeak() } }
                })
                ttsReady = true
            }
        }
    }

    fun start(noteType: String) {
        cancelInternal()
        type = noteType; text = ""; reminder = null; retries = 0; whenTries = 0
        step = Step.TEXT
        ui.onAsk(Ask.TEXT)
        say(if (type == Note.TYPE_TODO) "ToDo. Was darfst du nicht vergessen?" else "Idee. Ich höre.") { listen() }
    }

    /** Schaltfläche „Ja“ */
    fun yes() {
        stopListening()
        when (step) {
            Step.ASK_REMINDER -> askWhen()
            Step.CONFIRM -> save()
            Step.TEXT -> if (text.isNotBlank()) afterText()
            else -> {}
        }
    }

    /** Schaltfläche „Nein“ / „Ohne Termin“ */
    fun no() {
        stopListening()
        when (step) {
            Step.ASK_REMINDER, Step.WHEN -> { reminder = null; save() }
            Step.CONFIRM -> { reminder = null; askWhen("Wann dann? Oder sag: ohne Termin.") }
            else -> {}
        }
    }

    /** Schaltfläche „Fertig“ während des Diktats */
    fun finishDictation() {
        if (step == Step.TEXT) recognizer?.stopListening()
    }

    /** Zeitpunkt über Schaltfläche gewählt */
    fun chooseTime(t: Long) {
        stopListening()
        reminder = t
        save()
    }

    fun cancel() {
        val was = active
        cancelInternal()
        if (was) {
            ui.onFinished(null, "Abgebrochen")
            say("Abgebrochen.") {}
        }
    }

    fun destroy() {
        cancelInternal()
        tts?.shutdown(); tts = null
        recognizer?.destroy(); recognizer = null
    }

    // ---------------------------------------------------------------- Ablauf

    private fun afterText() {
        retries = 0
        if (type == Note.TYPE_TODO) {
            askWhen("Notiert. Bis wann?")
        } else if (prefs.askReminderForIdeas) {
            step = Step.ASK_REMINDER
            ui.onAsk(Ask.YES_NO)
            say("Notiert. Möchtest du eine Erinnerung?") { listen() }
        } else {
            save()
        }
    }

    private fun askWhen(question: String = "Wann soll ich dich erinnern?") {
        step = Step.WHEN
        ui.onAsk(Ask.WHEN)
        say(question) { listen() }
    }

    private fun confirm(t: Long) {
        reminder = t
        step = Step.CONFIRM
        ui.onAsk(Ask.CONFIRM)
        say("${Fmt.spoken(t)}. Passt das?") { listen() }
    }

    private fun save() {
        val n = Note(type = type, text = text.trim(), reminder = reminder, source = "voice")
        NoteDb.get(ctx).save(n)
        Reminders.schedule(ctx, n)
        Sync.now(ctx)
        step = Step.IDLE
        val msg = (if (type == Note.TYPE_TODO) "ToDo gespeichert" else "Idee gespeichert") +
            (reminder?.let { ". Erinnerung ${Fmt.spoken(it)}" } ?: "")
        ui.onFinished(n, msg)
        say("$msg.") {}
    }

    private fun onResult(heard: String) {
        val s = heard.trim()
        when (step) {
            Step.TEXT -> {
                if (s.isEmpty()) return retry("Ich habe nichts verstanden. Bitte nochmal.")
                text = if (text.isBlank()) capitalize(s) else "$text $s"
                ui.onHeard(text, false)
                afterText()
            }
            Step.ASK_REMINDER -> {
                val t = DateParser.parse(s)
                when {
                    t != null -> confirm(t)
                    isNo(s) -> { reminder = null; save() }
                    isYes(s) -> askWhen()
                    else -> retry("Ja oder nein?")
                }
            }
            Step.WHEN -> {
                val t = DateParser.parse(s)
                when {
                    t != null -> confirm(t)
                    isNo(s) || s.contains("ohne") -> { reminder = null; save() }
                    ++whenTries >= 3 -> { reminder = null; save() }
                    else -> retry("Das habe ich nicht verstanden. Sag zum Beispiel: morgen um 9.")
                }
            }
            Step.CONFIRM -> {
                val t = DateParser.parse(s)
                when {
                    isYes(s) -> save()
                    t != null && !isNo(s) -> confirm(t)
                    isNo(s) -> { reminder = null; askWhen("Wann dann? Oder sag: ohne Termin.") }
                    else -> retry("Passt das? Ja oder nein?")
                }
            }
            Step.IDLE -> {}
        }
    }

    private fun onNoInput() {
        when (step) {
            Step.TEXT -> if (text.isNotBlank()) afterText() else retry("Ich habe nichts gehört. Bitte nochmal.")
            Step.ASK_REMINDER -> retry("Möchtest du eine Erinnerung? Ja oder nein?", fallback = { reminder = null; save() })
            Step.WHEN -> retry("Wann? Oder sag: ohne Termin.", fallback = { reminder = null; save() })
            Step.CONFIRM -> retry("Passt das?", fallback = { save() })
            Step.IDLE -> {}
        }
    }

    private fun retry(question: String, fallback: (() -> Unit)? = null) {
        if (++retries > 2) {
            retries = 0
            if (fallback != null) return fallback()
            cancelInternal()
            ui.onFinished(null, "Nichts verstanden – abgebrochen")
            say("Ich habe leider nichts verstanden. Abgebrochen.") {}
            return
        }
        say(question) { listen() }
    }

    // ---------------------------------------------------------------- Sprache

    private fun say(s: String, then: () -> Unit) {
        ui.onPrompt(s)
        val t = tts
        if (!prefs.speak || t == null || !ttsReady) {
            // ohne Sprachausgabe: kurz warten, damit die Frage gelesen werden kann
            pendingAfterSpeak = null
            main.postDelayed({ if (active || step == Step.IDLE) then() }, if (prefs.speak && !ttsReady) 600 else 250)
            return
        }
        pendingAfterSpeak = then
        t.speak(s, TextToSpeech.QUEUE_FLUSH, Bundle(), "u" + System.nanoTime())
    }

    private fun afterSpeak() {
        val f = pendingAfterSpeak
        pendingAfterSpeak = null
        f?.invoke()
    }

    private fun listen() {
        if (step == Step.IDLE) return
        if (!SpeechRecognizer.isRecognitionAvailable(ctx)) {
            ui.onPrompt("Spracherkennung ist auf diesem Telefon nicht verfügbar – bitte tippen.")
            return
        }
        val r = recognizer ?: SpeechRecognizer.createSpeechRecognizer(ctx).also { recognizer = it }
        r.setRecognitionListener(listener)
        val i = Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH).apply {
            putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM)
            putExtra(RecognizerIntent.EXTRA_LANGUAGE, prefs.speechLang)
            putExtra(RecognizerIntent.EXTRA_PARTIAL_RESULTS, step == Step.TEXT)
            putExtra(RecognizerIntent.EXTRA_MAX_RESULTS, 3)
            if (step == Step.TEXT) {
                // Gedanken brauchen Pausen – nicht zu früh abschneiden
                putExtra(RecognizerIntent.EXTRA_SPEECH_INPUT_COMPLETE_SILENCE_LENGTH_MILLIS, 2500L)
                putExtra(RecognizerIntent.EXTRA_SPEECH_INPUT_POSSIBLY_COMPLETE_SILENCE_LENGTH_MILLIS, 2000L)
                putExtra(RecognizerIntent.EXTRA_SPEECH_INPUT_MINIMUM_LENGTH_MILLIS, 4000L)
            }
        }
        listening = true
        ui.onListening(true)
        r.startListening(i)
    }

    private fun stopListening() {
        listening = false
        recognizer?.cancel()
        ui.onListening(false)
        tts?.stop()
        pendingAfterSpeak = null
    }

    private fun cancelInternal() {
        stopListening()
        step = Step.IDLE
        main.removeCallbacksAndMessages(null)
    }

    private val listener = object : RecognitionListener {
        override fun onReadyForSpeech(params: Bundle?) {}
        override fun onBeginningOfSpeech() {}
        override fun onRmsChanged(rmsdB: Float) {}
        override fun onBufferReceived(buffer: ByteArray?) {}
        override fun onEndOfSpeech() { ui.onListening(false) }
        override fun onEvent(eventType: Int, params: Bundle?) {}

        override fun onPartialResults(partialResults: Bundle?) {
            val p = partialResults?.getStringArrayList(SpeechRecognizer.RESULTS_RECOGNITION)?.firstOrNull() ?: return
            if (step == Step.TEXT && p.isNotBlank()) ui.onHeard((if (text.isBlank()) "" else "$text ") + p, true)
        }

        override fun onResults(results: Bundle?) {
            if (!listening) return
            listening = false
            ui.onListening(false)
            val list = results?.getStringArrayList(SpeechRecognizer.RESULTS_RECOGNITION) ?: arrayListOf()
            // bei Zeitfragen die erste Variante nehmen, die eine Zeitangabe oder Ja/Nein enthält
            val best = if (step == Step.TEXT) list.firstOrNull()
            else list.firstOrNull { DateParser.parse(it) != null || isYes(it) || isNo(it) } ?: list.firstOrNull()
            if (best.isNullOrBlank()) onNoInput() else onResult(best)
        }

        override fun onError(error: Int) {
            if (!listening || error == SpeechRecognizer.ERROR_CLIENT) return
            listening = false
            ui.onListening(false)
            if (step == Step.IDLE) return
            when (error) {
                SpeechRecognizer.ERROR_NO_MATCH, SpeechRecognizer.ERROR_SPEECH_TIMEOUT -> onNoInput()
                SpeechRecognizer.ERROR_RECOGNIZER_BUSY -> { recognizer?.cancel(); main.postDelayed({ listen() }, 500) }
                SpeechRecognizer.ERROR_INSUFFICIENT_PERMISSIONS -> {
                    cancelInternal(); ui.onFinished(null, "Mikrofon ist nicht erlaubt")
                }
                SpeechRecognizer.ERROR_NETWORK, SpeechRecognizer.ERROR_NETWORK_TIMEOUT, SpeechRecognizer.ERROR_SERVER -> {
                    if (step == Step.TEXT && text.isBlank()) {
                        cancelInternal()
                        ui.onFinished(null, "Spracherkennung braucht Internet oder das Offline-Sprachpaket Deutsch")
                        say("Keine Verbindung zur Spracherkennung.") {}
                    } else onNoInput()
                }
                else -> onNoInput()
            }
        }
    }

    companion object {
        private val yesWords = Regex("(?U)\\b(ja|jo|jep|jawohl|gerne?|bitte|ok|okay|genau|passt|richtig|stimmt|klar|sicher|freilich|eh)\\b")
        private val noWords = Regex("(?U)\\b(nein|nee|ne|nö|nicht|keine?n?|nix|ohne|abbrechen|lass|später nicht)\\b")
        fun isYes(s: String) = yesWords.containsMatchIn(s.lowercase()) && !isNo(s)
        fun isNo(s: String) = noWords.containsMatchIn(s.lowercase())
        fun capitalize(s: String) = s.replaceFirstChar { if (it.isLowerCase()) it.titlecase(Locale.GERMAN) else it.toString() }
    }
}
