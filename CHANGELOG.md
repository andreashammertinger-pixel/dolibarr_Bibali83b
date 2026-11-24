# Changelog

## 1.15.0
- **Rücklagen-Satz je Haus festlegen** (Rücklagen-Planung): Mindestsatz lt. Plan, Plan × Faktor, manuell oder gesetzliches Minimum; Vorschau mit Zuführung je Monat und Warnung unter Minimum; Satz auf 2 Nachkommastellen aufgerundet
- **Gesetzliches Minimum** angezeigt (0,54 % der etalonska cijena građenja ÷ 12, Werte in den Einstellungen)
- **Verlauf** jeder Festlegung mit Datum, Benutzer, alt → neu, gesetzl. Minimum, Mindestsatz lt. Plan, Planvolumen, Kontostand, Begründung/Beschluss; in der Druckansicht für die Eigentümerversammlung (alle Sprachen)
- Vorschreibung verwendet den Satz des Hauses (Satz je Apartment nur noch als Rückfall); bisherige Sätze werden beim Aktivieren übernommen
- Erläuterung nennt zusätzlich den gesetzlichen Mindestsatz

## 1.14.0
- **KI-Übersetzung** (Claude-API, eigener API-Schlüssel, verschlüsselt gespeichert): neue Kostenarten, Planpositionen und Textbausteine werden beim Speichern in alle Dokumentsprachen übersetzt; jeder Bearbeiter schreibt in seiner Sprache („geschrieben in“)
- Bei Änderung bestehender Texte Nachfrage: „Änderung auch in die anderen Sprachen übernehmen?“ – Ja, übersetzen / Nein, nur diese Sprache; von Hand korrigierte Übersetzungen bleiben erhalten
- Sprachen-Seite: geänderte Textbausteine auf Wunsch in alle Sprachen; „Fehlende Texte automatisch übersetzen“ für neue Sprachen
- Fester Wortschatz (pričuva, zaduženje …), Platzhalter bleiben erhalten; bei Ausfall des Dienstes wird trotzdem gespeichert
- Einstellungen: API-Schlüssel, ein/aus, Verbindung testen
- Listen zeigen Kostenarten und Planpositionen in der Sprache des Benutzers

## 1.13.0
- Rücklagen-Planung: **Planpositionen direkt im Modul bearbeiten** – Knopf „Planpositionen bearbeiten“ (alle Positionen in einer Tabelle: Jahr, Bezeichnung, Gebäude/Schlüssel, Betrag, Schätzgrundlage, aktiv, löschen, bis zu 3 neue), Knopf „Neue Planposition“, Klick auf die Bezeichnung öffnet Details und Übersetzungen
- Geänderte Texte ziehen die Übersetzung derselben Sprache automatisch mit
- Import nur noch optional (eingeklappt)

## 1.12.0
- **Hausverwaltungs-Begriffe in Dolibarr** (Seite Sprachen, ein Klick, nur für die aktuelle Firma, alle Sprachen, update-sicher): Geschäftspartner → *Apartments & Kontakte*, Kunden → *Apartments*, Kontakte/Adressen → *Eigentümer & Kontakte*, Rechnung | Zahlung → *Vorschreibung | Zahlung*, Kundenrechnungen → *Vorschreibungen*, Kundenzahlungen → *Zahlungen der Eigentümer*, Spalten Name/Art/Apartment-Nr./Zuständig; Interessenten ausgeblendet; Zurücksetzen möglich
- Doku mit den neuen Menünamen (Handbuch de/hr, Eigentümerhilfe 5 Sprachen)

## 1.11.0
- **Versendete Dokumente beim Apartment**: Jede an Eigentümer versendete Datei (Vorschreibung, Übersetzung, Erläuterung, Abrechnungsblatt, Nachzahlungsrechnung) liegt zusätzlich unter Geschäftspartner → *Verknüpfte Dokumente*, mit Versanddatum im Dateinamen; sichtbar auch im Eigentümer-Zugang. Testversand legt nichts ab.
- Einmalig beim Aktivieren: bereits versendete Vorschreibungen werden nachträglich abgelegt
- Abrechnungsblätter erscheinen beim Apartment erst nach dem Versand (nicht schon beim Erzeugen)
- Einstellung „Versendete Dokumente beim Apartment ablegen“ (Standard an)
- Doku (Handbuch de/hr, Eigentümerhilfe 5 Sprachen) angepasst

## 1.10.0
- **Online-Hilfe**: Handbuch für die Verwaltung (de/hr), Hilfe für Eigentümer (hr/de/en/sl/uk), News und Recht mit Quellen – Website im Ordner `docs/`, automatische Veröffentlichung per GitHub Action
- Fragezeichen oben rechts auf jeder Seite der Hausverwaltung öffnet das passende Kapitel in der Sprache des Benutzers; Menüpunkt „Handbuch (online)“
- Menü **Hilfe** für Eigentümer (externe Benutzer) öffnet die Eigentümerhilfe in ihrer Sprache (Modul dafür für externe Benutzer freigegeben – die Hausverwaltung selbst bleibt gesperrt)
- Einstellung „Hilfe-Adresse“; Erläuterung zur Vorschreibung verweist auf die Eigentümerhilfe

## 1.9.2
- Erläuterung: Hinweis zu den geplanten Maßnahmen ergänzt (jährliche Begehung, Zeitpunkt und Umfang können sich verschieben), mehrzeilig, in allen Sprachen

## 1.9.1
- Fix: SQL-Fehler „Filter error – Bad syntax of the search string“ in Rechnungs-, Eingangsrechnungs- und Produktlisten. Filter der Auswahllisten (Apartment, Kostenart, KPD) auf die Syntax von Dolibarr ≥ 19 umgestellt; bestehende Installationen werden beim erneuten Aktivieren korrigiert

## 1.9.0
- **Erläuterung zur Vorschreibung** je Apartment (PDF, kroatisches Original + Übersetzung): monatliche Zusammensetzung (Satz × m² für Betriebskosten, Rücklage, Verwaltung), Betriebskosten je Kostenart mit Anteil, Sätze je Haus mit Begründung, Rücklage (Kontostand, Mindestsatz laut Plan, nächste Maßnahmen), Ablauf/Zahlung/Kontakt
- Wird beim Versand der Vorschreibung automatisch erzeugt, angehängt (Hinweis im Mailtext) und neben der Rechnung abgelegt – damit auch im Eigentümer-Zugang sichtbar
- Einstellungen: Erläuterung anhängen (Standard an), Adresse Eigentümer-Zugang (erscheint in der Erläuterung)
- Texte in hr/de/en/sl/uk, über die Sprachen-Seite übersetzbar

## 1.8.4
- Eigentümer-Zugang (externe Benutzer): Hausverwaltungs-Seiten, Menü und API für externe Benutzer immer gesperrt (auch bei versehentlich vergebenen Rechten); Warnung in der Übersicht, falls externe Benutzer Hausverwaltungs-Rechte haben
- Abrechnungsblätter zusätzlich in den Dokumenten des Apartments (Geschäftspartner) – dort sieht sie der Eigentümer mit seinem Zugang
- Download der Abrechnungsblätter in der Jahresabrechnung über eigene, geschützte Seite

## 1.8.3
- Verspätete Ausstellung: Ist eine Rate bei der Freigabe schon fällig (z. B. Freigabe am 18.11., Fälligkeit 5.11.), gilt eine Frist von 7 Tagen ab Freigabe (Einstellung `HAUSVERWALTUNG_LATE_DAYS`). Wirkt auf PDF (Fälligkeit, Zahlungsplan), E-Mail („binnen 7 Tagen“), Zahlungsstatus und das Fälligkeitsdatum der Rechnung; folgende Raten bleiben am Fälligkeitstag.

## 1.8.2
- E-Mail Vorschreibung übersichtlicher: Gesamtbetrag, Monatsrate, erste Fälligkeit und Zahlungsangaben (Empfänger, IBAN, BIC, Zahlungsreferenz) jeweils in eigener Zeile – in allen Sprachen
- Gleicher Zahlungsblock in der E-Mail zur Nachzahlung (Abrechnung)
- IBAN in E-Mail, Vorschreibung und Abrechnungsblatt lesbar in 4er-Gruppen

## 1.8.1
- Import Rücklagen-Plan: Option „bestehende Planpositionen ersetzen“ (Standard an); ohne Option werden gleiche Positionen (Bezeichnung, Gebäude, Jahr) nicht doppelt angelegt
- Rücklagen-Plan Bibali überarbeitet: Ansätze so bemessen, dass die aktuellen Sätze bei 3 % Baukostensteigerung über 20 Jahre reichen

## 1.8.0
- **Abrechnungsblatt je Einheit** (Betriebskostenabrechnung): kroatisches Original + Kopie in der Sprache des Eigentümers; Kostenarten mit Gesamtkosten, Schlüssel, Anteil; Vorauszahlungen, Guthaben/Nachzahlung (mit Rechnungsnummer, Fälligkeit, IBAN), Rücklagen-Information. Nach dem Buchen gelten die festgeschriebenen Werte.
- *Jahresabrechnung*: Abrechnungsblätter erzeugen, Testversand, Versand an alle Eigentümer (mit Nachzahlungsrechnung als Anhang), Versandprotokoll
- Neue Seite **Sprachen**: Abdeckung je Sprache, Sprache hinzufügen, Texte und Kostenarten/Planpositionen übersetzen, Export/Import für Übersetzer; Sprachen der Eigentümer werden automatisch erkannt
- Kostenarten mit Übersetzungen je Sprache; Bibali-Übersetzungen `data/bibali83b_uebersetzungen.json`
- PDFs: gleiche Schriftgröße in allen Sprachen, alle Beschriftungen übersetzbar (keine Dolibarr-Standardtexte mehr)

## 1.7.0
- Rücklagen-Planung **mehrsprachig**: Druckansicht je Dokumentsprache (Standard hr/de/en/sl/uk, Einstellung `HAUSVERWALTUNG_DOC_LANGS`), Kroatisch = Original, andere Sprachen mit Hinweis „Kopie – Übersetzung“
- Planpositionen mit Übersetzungen je Sprache (Bezeichnung, Schätzgrundlage, Hinweis); Import akzeptiert Texte als `{"hr_HR": "...", "de_DE": "..."}`
- Gebäudenamen sprachneutral („Kuća 1 / Haus 1 / Будинок 1“)
- Bibali-Plan in allen fünf Sprachen
- Fix: Feldnamen mit Ziffern beim Speichern

## 1.6.0
- Neue Seite **Rücklagen-Planung** (Mehrjahresprogramm): Planpositionen je Gebäude oder „alle Gebäude“ (Aufteilung nach Schlüssel), Jahr, Betrag zu heutigen Preisen, Schätzgrundlage, aktiv/inaktiv
- Prognose je Gebäude über den Planungszeitraum: Start = Kontostand Rücklagenkonto, Zuführung = aktuelle Sätze, Entnahmen mit Baukostensteigerung; Ampel, niedrigster Stand, **Mindestsatz €/m²** ohne Unterdeckung
- Druckansicht (für Eigentümerversammlung / Beschluss), Import von Planpositionen aus JSON, API `/hausverwaltung/reserveplan`
- Einstellungen: Baukostensteigerung (Standard 3 %), Planungszeitraum (Standard 20 Jahre)
- Beispiel-Plan Bibali 83b (`data/bibali83b_ruecklagenplan.json`) und leere Vorlage

## 1.5.4
- Hinweis „Digitales Dokument – es erfolgt kein Versand in Papierform“ in der Dokumentsprache über der Fußzeile; in den Einstellungen abschaltbar (für Objekte mit Postversand)

## 1.5.3
- *Vorschreibung erstellen*: Spalte **PDF-Vorschau** je Einheit (Original und Übersetzungskopie) mit Wasserzeichen „Entwurf“ – es wird keine Rechnung angelegt
- Knopf „Vorschau“ heißt jetzt „Vorschau aktualisieren“ (berechnet die Tabelle für geänderte Zeiträume neu)

## 1.5.2
- Registrierungsnummer der Gemeinschaft (Firma → Prof-ID 2) im Absenderblock unter der OIB, nur wenn befüllt (hr „Reg. br.“, de „Reg.-Nr.“)

## 1.5.1
- Firma als Eigentümer: Kontakt mit Firmenname als Nachname, leerem Vornamen, Firmen-OIB und Eigentümer = Ja; E-Mail-Anrede dann „Sehr geehrte Damen und Herren“ (hr: „Poštovani,“)

## 1.5.0
- Neues Kontaktfeld **„Eigentümer“** (Ja/Nein): nur markierte Kontakte stehen auf Vorschreibung/Abrechnung und erhalten die E-Mail; Mieter, Notfallkontakte usw. bleiben außen vor. Mehrere Eigentümer je Einheit werden alle angeführt.
- Einmalige Übernahme beim Aktivieren: Kontakte mit Funktion „Eigentümer“ werden automatisch markiert
- *Apartments* zeigt die markierten Eigentümer bzw. eine Warnung, wenn keiner markiert ist

## 1.4.0
- Vorschreibung für einen **Zeitraum** (von–bis, z. B. Jahr oder Restjahr): ein Dokument je Einheit, Zeilen = Monatsbetrag × Monate, **Zahlungsplan** mit gleichen Monatsraten, Hinweis Dauerauftrag; Schutz vor Überschneidungen mit bestehenden Vorschreibungen
- Honorar-Eingangsrechnung und E-Mail-Text für Zeiträume
- Neue Seite **Zahlungsstand**: fällig bis heute, bezahlt, Rückstand, nächste Rate
- **Verteilschlüssel je Kostenart**: m², Miteigentumsanteil, gleich je Einheit (Anteil aus Geschäftspartner-Feld `eigentumsanteil` oder an der Einheit); ohne Werte automatisch nach m² mit Warnung
- **Allgemeiner Import**: Startdaten als JSON-Datei hochladen (leere Vorlage + Beispiel Bibali), Zuordnung der Geschäftspartner über die Einheitennummer am Namensende oder exakten Namen
- Keine Bibali-spezifischen Texte mehr im Code (Kopfzeile Mobil, Gebäudebezeichnung)

## 1.3.0
- Neue Seite *Vorschreibungen versenden*: E-Mail an alle Eigentümer-Kontakte, Anhänge kroatisches Original + Übersetzungskopie, Betreff/Text in Eigentümersprache (de/hr/sl/uk/en) mit Betrag, Fälligkeit, IBAN, Zahlungsreferenz
- Testversand an eigene Adresse, Versandprotokoll, Agenda-Eintrag an der Rechnung, optional BCC-Archivkopie und eigene Absenderadresse

## 1.2.0
- OIB auf Vorschreibung/Rechnung: Gemeinschaft (Firma 5) und Verwalter (Green Villa) im Absenderblock; Eigentümer (neues Kontaktfeld „OIB“) mit E-Mail unterhalb der Postanschrift (Adressfeld bleibt postkonform)
- OIB-Prüfung mit Prüfziffer (ISO 7064) und Warnliste auf *Vorschreibung erstellen*
- Lese-API (nur GET, Recht „Hausverwaltung ansehen“): `/hausverwaltung/overview`, `/hausverwaltung/costitems`, `/hausverwaltung/settlement/{jahr}`

## 1.1.0
- PDF-Vorlage „Vorschreibung“ nach Excel-Layout; kroatisches Original + markierte Übersetzungskopie (de/sl/uk/en)
- Positionen als €/m² × m² (2 Nachkommastellen), Apartment + m² im Betreff, Land englisch
- m² aus Geschäftspartner (Wohnfläche), Verteilschlüssel reine Wohnfläche
- Verwaltungshonorar netto + 25 % USt brutto vorgeschrieben; Honorar-Eingangsrechnung des Verwalters automatisch
- Jahresabrechnung buchen: Nachzahlungsrechnung bzw. Guthaben-Verrechnung auf folgenden Vorschreibungen
- KPD 2025 Auswahlliste (3.359 Codes) an Produkten/Leistungen
- Haushaltsversicherung-Nachweis je Apartment
- Import nutzt vorhandene Rücklage-Leistungen L_Rue_H1–H3

## 1.0.0
- Erste Version: Gebäude, Apartments, Kostenplan, Verteilung, Vorschreibung, Rücklagen-Automatik, mobile Belegerfassung
