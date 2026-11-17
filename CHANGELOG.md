# Changelog

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
