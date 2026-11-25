# Rundschreiben an Eigentümer

*Hausverwaltung → Rundschreiben* – E-Mail an alle oder ausgewählte Eigentümer, jeweils in ihrer Sprache, mit Anhängen.

## Ablauf

1. **Neues Rundschreiben**.
2. **Text**: *geschrieben in* (Ihre Sprache), Betreff und Inhalt. Anrede („Guten Tag …“) und Grußformel ergänzt Dolibarr automatisch – im Text weglassen. *Speichern* übersetzt in die Sprachen der ausgewählten Empfänger; bei späteren Änderungen fragt Dolibarr, ob die anderen Sprachen übernommen werden sollen.
3. **Übersetzungen** prüfen und bei Bedarf korrigieren (Kennzeichen *KI* bzw. *bearbeitet*). *Jetzt übersetzen (KI)* übersetzt alle neu.
4. **Anhänge**:
    - **Datei hochladen** mit Sprache – oder *alle Sprachen* für sprachneutrale Dateien (Plan, Foto, Tabelle).
    - **Datei aus Dolibarr** (Modul *Dokumente*) übernehmen.
    - **Dasselbe Schreiben in mehreren Sprachen** (z. B. DE, HR, SL): zuerst eine Sprache als *Neues Dokument hochladen*, die weiteren Sprachen dann **beim Dokument** mit *Sprachfassung hinzufügen*. Nur fehlende Sprachen werden per KI übersetzt; eine echte Sprachfassung ersetzt eine vorhandene KI-Übersetzung.
    - Schon getrennt hochgeladen? Beim Dokument *Ist dasselbe Schreiben wie … → Zusammenführen*. Die Seite warnt, wenn mehrere Dokumente mit je nur einer Sprache vorhanden sind.
    - **Fehlende Sprachfassungen per KI übersetzen (PDF)**: erstellt je Sprache ein PDF; unten steht klein „Mittels KI in die jeweilige Sprache übersetzt – bei Unklarheiten bitte rückfragen.“
    - **Dokument der Hausverwaltung** – *Rücklagen-Plan*, *Abrechnungsblatt (je Apartment)* mit Jahr oder *Vorschreibung mit Erläuterung (je Apartment)* mit Abrechnungsmonat wählen, *Hinzufügen*. Diese Dokumente werden **beim Versand neu erzeugt** (aktueller Stand), je Apartment mit seinen eigenen Zahlen, als kroatisches Original und in der Sprache des Eigentümers. Fehlt für ein Apartment das Dokument (z. B. keine Vorschreibung für den Monat), wird dieses Apartment **nicht** versendet und im Protokoll als Fehler gezeigt.
    - Die Tabelle *wird angehängt* zeigt je Sprache, was jeder Eigentümer bekommt: das **Original** (kroatische Fassung, sonst die hochgeladene Ausgangsfassung) und die **Fassung in seiner Sprache**.
5. **Empfänger**: alle, keine oder je Haus (H1, H2 …) auswählen, *Speichern*. Apartments ohne Eigentümer-E-Mail sind gesperrt.
6. **Versand**:
    - **Testversand** – je Sprache der Empfänger eine E-Mail an Ihre Adresse, mit genau dem Text und den Anhängen, die ein Beispiel-Apartment dieser Sprache bekäme (oben im Test steht, welches Apartment und welche Empfänger).
    - **An ausgewählte Eigentümer senden** – je Apartment eine E-Mail an dessen Eigentümer.

!!! info "Versand Schritt für Schritt"
    Testversand, Versand und *Fehlende Sprachfassungen per KI übersetzen* laufen in einzelnen Schritten mit Fortschrittsanzeige (Text übersetzen, KI-Übersetzung je Datei und Sprache, Testmail je Sprache, je Apartment eine E-Mail). Bitte die Seite offen lassen, bis sie sich selbst aktualisiert. Braucht der Server länger (z. B. bei einer KI-Übersetzung), steht *wird gleich erneut geprüft* – der Schritt läuft im Hintergrund weiter. Wird der Ablauf abgebrochen: Seite neu laden und erneut starten – Erledigtes wird nicht wiederholt, **bereits versendete Apartments bekommen das Rundschreiben nie doppelt**.

!!! warning "Testversand ist Pflicht"
    *Senden* ist erst nach einem erfolgreichen Testversand möglich (grüner Hinweis *Testversand erfolgreich am …*). Jede Änderung an Text, Übersetzungen, Anhängen oder Empfängern verlangt einen neuen Testversand.

Fehlende Übersetzungen werden vor dem Versand automatisch ergänzt. Das Versandprotokoll steht unten; Text und Anhänge werden beim **Apartment → Verknüpfte Dokumente** abgelegt (mit Versanddatum).

!!! info "Hinweis in jeder E-Mail"
    Unter der Grußformel steht klein: Hausverwaltung (Version, Zeitpunkt) · „Mittels KI in die jeweilige Sprache übersetzt – bei Unklarheiten bitte rückfragen.“ Derselbe Satz steht in der Fußzeile aller Dokumente – in jeder Sprache gleich, auch auf Kroatisch.

!!! warning "Keine personenbezogenen Daten in Anhängen zur KI-Übersetzung"
    Für die KI-Übersetzung wird das PDF an den Übersetzungsdienst übertragen. Dokumente mit Namen, OIB oder Kontodaten einzelner Eigentümer nicht per KI übersetzen.
