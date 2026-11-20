# Ideenbox – Android-App + Dolibarr-Modul

Gedanken und ToDos im Auto per Sprache festhalten, im Büro in Dolibarr zuordnen.

## Bedienung im Auto
1. App öffnen → **IDEE** oder **TODO** antippen (oder App-Symbol lange drücken → *Idee diktieren* / *ToDo diktieren*; als Verknüpfung auf den Startbildschirm ziehen).
2. Sprechen. Die App liest die Rückfragen vor:
   * Idee: „Möchtest du eine Erinnerung?“ → *ja* / *nein* / gleich eine Zeit („ja, morgen um 10“)
   * ToDo: „Bis wann?“ → z. B. „Freitag nachmittags“, „in zwei Stunden“, „am 25. November um 9“, „ohne Termin“
   * „Freitag um 9 Uhr. Passt das?“ → *ja* / *nein*
3. „Gespeichert.“ – fertig. Alle Fragen lassen sich auch mit großen Schaltflächen beantworten (JA, NEIN, in 1 Std., heute 17:00, morgen 9:00, OHNE TERMIN). *Rückgängig* direkt nach dem Speichern.

Im Querformat (Halterung) liegen IDEE und TODO nebeneinander. Bildschirm bleibt während der App an.

**Tippen** (Besprechung): Art wählen, Text, Termin per Kalender oder Schnellwahl, *Speichern*.
**Liste**: offene Einträge, überfällige mit ⚠, Zuordnung aus dem Büro (→ Firma › Projekt), antippen = bearbeiten, lange drücken = erledigt.
**Erinnerung**: Benachrichtigung mit *Erledigt* und *In 1 Stunde* – funktioniert auch ohne Internet.

Ohne Internet wird lokal gespeichert und später automatisch übertragen. Spracherkennung offline: in den Android-Einstellungen das Offline-Sprachpaket Deutsch laden (Google → Spracherkennung).

## Verbindung mit Dolibarr
1. Modul **Ideenbox** (`ideenbox/`, ZIP mit `./build.sh ideenbox`) in der Holding installieren und aktivieren (aktiviert *API/Webservices* mit).
2. Benutzer: Rechte *Ideenbox – ansehen, erfassen* geben; in der Benutzerkarte den **Schlüssel für API** erzeugen.
3. App → ⚙ → Dolibarr-Adresse + API-Schlüssel → *Speichern und Verbindung testen*.
   Gespeichert wird in der Firma, in der der API-Benutzer angemeldet ist (Standardfirma) – also in der Holding.

Im Büro: Menü **Ideen & ToDos** → Übersicht / ToDos / Ideen / *Noch nicht zugeordnet*. Je Eintrag: Firma, Geschäftspartner, Projekt, Zuständig zuordnen; **Als Projektaufgabe übernehmen** (Aufgabe in der Firma des Projekts) oder **In Agenda eintragen**. Einträge mit Termin landen automatisch in der Agenda (abschaltbar in den Moduleinstellungen). Erledigt im Büro ⇄ erledigt in der App. Im Büro getippte ToDos mit Termin erinnern auch auf dem Telefon. Widget *Ideen & ToDos (offen)* für die Startseite.

### REST-API (`DOLAPIKEY`-Header)
| Aufruf | Zweck |
|---|---|
| `GET /api/index.php/ideenbox/ping` | Verbindung/Rechte prüfen |
| `GET /api/index.php/ideenbox/notes?since=<unix>&status=0,1` | Einträge (geändert seit) |
| `POST /api/index.php/ideenbox/notes` | anlegen: `uuid, type (idea/todo), text, reminder (unix), status, source` – gleiche `uuid` = Aktualisierung |
| `PUT /api/index.php/ideenbox/notes/{id}` | Text, Termin, Status (0 neu, 1 in Arbeit, 2 erledigt, 9 verworfen) |

## App bauen und installieren
```
cd app-ideenbox && ./gradlew assembleRelease     # oder: ./build.sh apk
```
Voraussetzung Android-SDK (`local.properties` mit `sdk.dir=…` oder `ANDROID_HOME`). Ohne eigenen Schlüssel wird mit dem Debug-Schlüssel signiert. Für Updates immer denselben Schlüssel verwenden: Umgebungsvariablen `IDEENBOX_KEYSTORE`, `IDEENBOX_KEYSTORE_PASSWORD`, `IDEENBOX_KEY_ALIAS` (bzw. Secrets im GitHub-Workflow *Android-App Ideenbox*, der die APK als Artefakt bereitstellt).

Installation: APK aufs Telefon, öffnen, „Unbekannte Apps installieren“ erlauben. Beim ersten Start Mikrofon und Benachrichtigungen erlauben; unter ⚙ ggf. *Pünktliche Erinnerungen erlauben*. Android 8 oder neuer.

Tests: `./gradlew testDebugUnitTest` (Datums-/Antworterkennung, Bildschirme mit Robolectric; Abgleich gegen eine Testinstanz mit `IDEENBOX_TEST_URL`/`IDEENBOX_TEST_KEY`).
