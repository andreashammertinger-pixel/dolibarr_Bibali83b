# Changelog

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
