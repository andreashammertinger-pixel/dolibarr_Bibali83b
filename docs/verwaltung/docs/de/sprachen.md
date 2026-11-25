# Sprachen und Texte

Alle Dokumente an Eigentümer (Vorschreibung, Erläuterung, Abrechnung, E-Mails, Rücklagenplan) entstehen auf **Kroatisch** und zusätzlich in der **Sprache des Eigentümers**. Die Texte werden in der Ausgangssprache (meist Deutsch) verfasst und mittels KI übersetzt; das steht klein in der Fußzeile („KI-Übersetzung aus: Deutsch“). Die Sprache kommt aus dem Geschäftspartner (Apartment) → Feld *Standardsprache*.

## Sprachen-Seite

*Hausverwaltung → Sprachen* zeigt je Sprache:

- Anzahl Apartments mit dieser Sprache,
- ob alle **Dokumenttexte** übersetzt sind,
- ob **Kostenarten und Planpositionen** übersetzt sind.

## Text ändern

1. **Übersetzen** bei der gewünschten Sprache.
2. Text in der rechten Spalte ändern (links stehen Deutsch und Kroatisch als Vorlage), *Speichern*.

Eigene Texte werden in der Datenbank gespeichert und bleiben bei Modul-Updates erhalten. Die Texte der Erläuterung beginnen mit `HvInfo…`.

!!! warning "Platzhalter"
    `%s` im Text wird durch Beträge, Daten usw. ersetzt – nicht löschen und die Reihenfolge beibehalten.

## Neue Sprache

1. Beim Apartment die Standardsprache setzen (z. B. Italienisch) – die Sprache erscheint automatisch auf der Sprachen-Seite.
2. Texte direkt übersetzen **oder** *Export für Übersetzer* → Datei übersetzen lassen → *Übersetzungsdatei importieren*.

Solange Texte fehlen, wird Englisch verwendet.

## Begriffe in Dolibarr

Dolibarr spricht allgemein von *Geschäftspartnern*, *Kunden* und *Kundenrechnungen*. Auf der Sprachen-Seite übernimmt **Hausverwaltungs-Begriffe übernehmen** passende Begriffe – nur für diese Firma, in allen Sprachen, auch für die Eigentümer:

| Dolibarr | Hausverwaltung |
|---|---|
| Geschäftspartner | Apartments & Kontakte |
| Kunden | Apartments |
| Kontakte/Adressen | Eigentümer & Kontakte |
| Rechnung \| Zahlung | Vorschreibung \| Zahlung |
| Kundenrechnungen | Vorschreibungen |
| Kundenzahlungen | Zahlungen der Eigentümer |
| Kundennr. | Apartment-Nr. |
| Vertriebsmitarbeiter | Zuständig |

*Interessenten* werden ausgeblendet. Die Begriffe bleiben bei Dolibarr-Updates erhalten; **Zurücksetzen** stellt die Dolibarr-Begriffe wieder her. Einzelne Begriffe lassen sich unter *Einstellungen → Übersetzung* anpassen.

## KI-Übersetzung

Mit einem API-Schlüssel (Einstellungen → *KI-Übersetzung*) übersetzt Dolibarr Texte für die Dokumente automatisch:

- **Neue Texte** (neue Kostenart, neue Planposition, neue Zeile in *Planpositionen bearbeiten*) werden beim Speichern in alle Dokumentsprachen übersetzt.
- **Geänderte bestehende Texte**: beim Speichern fragt Dolibarr **„Änderung auch in die anderen Sprachen übernehmen?“** – *Ja, übersetzen* oder *Nein, nur diese Sprache*.
- **geschrieben in**: Sprache, in der Sie schreiben (vorbelegt mit Ihrer Sprache). Jeder Bearbeiter schreibt in seiner Sprache.
- Von Hand korrigierte Übersetzungen werden beim selben Speichern nicht überschrieben.
- **Sprachen-Seite → Übersetzen**: geänderte Textbausteine werden auf Wunsch ebenfalls in alle Sprachen übernommen; **Fehlende Texte automatisch übersetzen** füllt eine neue Sprache (z. B. Italienisch) mit einem Klick.

!!! warning "Keine personenbezogenen Daten"
    Übertragen werden nur die Texte selbst. Schreiben Sie in diese Felder keine Namen, OIB oder Kontodaten.

Ist der Dienst nicht erreichbar, wird der Text trotzdem gespeichert; eine Meldung weist darauf hin.

## Herkunftszeile auf Dokumenten

Jedes Dokument (Vorschreibung, Erläuterung, Abrechnung, Druckansicht Rücklagen-Plan, KI-übersetzte Anhänge) und jede E-Mail trägt unten klein: **erstellt mit Hausverwaltung, Version und Zeitpunkt**, die **Ausgangssprache** und den Hinweis, dass **Fassungen in anderen Sprachen mittels KI übersetzt** wurden. Ausgangssprache und Hinweis: *Einstellungen*.
