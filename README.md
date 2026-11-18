# Dolibarr-Modul „Hausverwaltung“

Externes Modul für **Dolibarr 20.0.x** (getestet mit 20.0.4, Multicompany-fähig über `entity`).
Allgemein für Wohnungseigentumsgemeinschaften verwendbar – **je Liegenschaft eine Firma (Entität)** in Multicompany.
Erste Anwendung: Bibali 83b (3 Gebäude, 11 Apartments); die Bibali-Daten sind nur Beispiel-Startdaten.

## Neues Objekt anlegen (allgemein)
1. In Multicompany eine neue Firma für die Liegenschaft anlegen (Name, Adresse, OIB, Bankkonten: Betriebskonto + je Gebäude ein Rücklagenkonto).
2. Einheiten als Geschäftspartner (Kunde) anlegen, Name endet auf die Einheitennummer (z. B. „Musterstraße 5 - Top 3“); Eigentümer als Kontakte mit Häkchen **Eigentümer = Ja**, E-Mail und OIB (Mieter/Notfallkontakte auf Nein).
   m² im Feld `wohnflaeche_m2`, optional Anteil in `eigentumsanteil` (oder direkt an der Einheit im Modul).
3. Modul in dieser Firma aktivieren, Einstellungen setzen.
4. *Startdaten importieren*: leere Vorlage herunterladen, ausfüllen (Gebäude – bei nur einem Haus ein Eintrag –, Einheiten, Kostenplan mit Verteilschlüssel), hochladen.
5. Gebäude: Rücklagenkonto zuordnen. Kostenplan prüfen. Vorschreibung für den gewünschten Zeitraum erstellen.

**Firma als Eigentümer:** Kontakt mit Firmenname im Feld *Nachname*, Vorname leer, Firmenadresse, Firmen-E-Mail, **Firmen-OIB**, Eigentümer = Ja. Ansprechpersonen der Firma als weitere Kontakte mit Eigentümer = Nein.

Texte wie „Apartment“ lassen sich je Firma über *Einstellungen → Übersetzung* überschreiben (z. B. Schlüssel `HvUnit` → „Wohnung“).

## Vorschreibung: Monat oder Zeitraum
- **Einzelmonat**: wie bisher (€/m² × m²).
- **Zeitraum** (z. B. Jänner–Dezember): ein Dokument je Einheit mit Zahlungsplan; gleiche Monatsraten, fällig jeweils am eingestellten Tag; Empfehlung Dauerauftrag.
  Sonderfall Jahresbeginn mitten im Jahr (z. B. Bibali 2026): einfach **Restjahr** (z. B. 11–12/2026) erzeugen, ab dem Folgejahr **ganzes Jahr**.
- Überschneidungen mit bestehenden Vorschreibungen werden erkannt und übersprungen.
- *Zahlungsstand* zeigt je Vorschreibung fällig bis heute / bezahlt / Rückstand.

## Funktionen

| Bereich | Was es tut |
|---|---|
| **Gebäude** | Haus 1–3, je Gebäude ein **Rücklagen-Bankkonto** und eine eigene Leistung `HV-RL-<Gebäude>` |
| **Apartments** | Ref (1.1 …), Gebäude, m², Rücklage €/m², Verwaltung €/m², Verknüpfung mit Geschäftspartner |
| **Kostenplan** | Kostenarten mit Gebäude (*alle* oder ein Gebäude), Rhythmus (12/6/3/1 Monate), netto, MwSt |
| **Übersicht** | Verteilung + Monats- und Jahresbetrag je Apartment (wie Blätter *Verteilung / Monatsabrechnung / Jahresübersicht*) |
| **Vorschreibung** | Erzeugt pro Monat eine Kundenrechnung je Apartment mit 3 Positionen: `HV-BK` Betriebskosten, `HV-RL-x` Rücklage, `HV-VW` Verwaltung. Dubletten-Schutz je Apartment + Monat, optional direkt freigeben + PDF |
| **Rücklagen-Automatik** | Bei Zahlungseingang (Trigger `PAYMENT_ADD_TO_BANK`) wird der Rücklagenanteil automatisch vom Betriebskonto auf das Rücklagenkonto des Gebäudes umgebucht – auch anteilig bei Teilzahlungen. Eingangsrechnungen mit „Aus Rücklage bezahlt“ werden vom Rücklagenkonto entnommen. Löschen einer Zahlung macht die Umbuchung rückgängig |
| **Eingangsrechnungen** | Extrafelder *Kostenart (Hausverwaltung)* und *Aus Rücklage bezahlt* auf Lieferantenrechnungen |
| **Jahresabrechnung** | Plan vs. Ist je Kostenart, Ist-Anteil je Apartment vs. vorgeschriebene Akonto-BK → Saldo. *Abrechnung buchen*: **Nachzahlung** → eigene Rechnung „Abrechnung“ (Leistung `HV-BKA`); **Guthaben** → wird automatisch als Zeile „Guthaben aus Abrechnung“ (`HV-GUT`) mit den folgenden Vorschreibungen verrechnet, bis es aufgebraucht ist. Der durch ein Guthaben gedeckte Rücklagenanteil wird bei der Freigabe sofort auf das Rücklagenkonto umgebucht. |
| **Haushaltsversicherung** | Nachweisfelder am Geschäftspartner (Versicherer, Polizzen-Nr., gültig bis) gemäß Miteigentumsvertrag §10.2; Status in *Apartments*, Warnung in der Übersicht |
| **Versand** | *Vorschreibungen versenden*: je Vorschreibung eine E-Mail an alle Eigentümer-Kontakte (Rechnungskontakt) mit kroatischem Original + Übersetzungskopie; Betreff/Text in der Sprache des Apartments. Testversand an eigene Adresse, Versandprotokoll, Agenda-Eintrag. Voraussetzung: E-Mail-Versand in Dolibarr eingerichtet (*Einstellungen → E-Mails*). |
| **Mobil** | `/custom/hausverwaltung/mobile.php`: Beleg fotografieren, Kostenart wählen (Lieferant und MwSt werden vorbelegt), Betrag → Lieferantenrechnung im Entwurf inkl. Foto. Außerdem offene Vorschreibungen und Rücklagenstände |

### Festgelegte Struktur (Entscheidungen)
* **Firma 5 „Bibali 83B“ (Zgrada Bibali 83B)** stellt die Vorschreibung aus; Zahlungseingang auf das Gemeinschaftskonto `1200` RBA-Bibali83B, Rücklagenkonten `2801–2803`.
* **Vorschreibung kompakt** ohne USt-Ausweis: Betriebskosten / Rücklage / Verwaltung. Details folgen in der Jahresabrechnung.
* **Verwaltungshonorar:** 0,30 €/m² **netto**. Green Villa and Apartment Resorts d.o.o. verrechnet es der Gemeinschaft mit 25 % USt. Da die Gemeinschaft keine Vorsteuer abziehen kann, wird **brutto** (0,375 €/m²) vorgeschrieben.
  Beim Erzeugen der Vorschreibungen legt das Modul die Honorar-Eingangsrechnung (Entwurf, Ref. `HON-JJJJ-MM`) automatisch an – die Summe entspricht exakt der vorgeschriebenen Verwaltung.
  Green Resorts stellt die Ausgangsrechnung in seiner eigenen Firma (z. B. als wiederkehrende Rechnungsvorlage).

### Sprachen
* **Oberfläche:** Deutsch, Kroatisch, Englisch vollständig. Dolibarr zeigt die Sprache des Browsers, wenn unter *Einstellungen → Benutzeroberfläche* die Standardsprache auf **„Automatisch (Browsersprache)“** steht und beim Benutzer keine eigene Sprache gesetzt ist.
* **Vorschreibung:** Das **Original ist immer kroatisch** (`IN….pdf`, Positionstexte in der Datenbank ebenfalls kroatisch) – das ist das Dokument für Finanz und Steuerberater.
  Ist die *Standardsprache* des Apartments nicht Kroatisch, wird automatisch zusätzlich eine **Übersetzungskopie** `IN…_de_DE.pdf` (de, sl, uk, en) erzeugt, deutlich markiert „KOPIE – Übersetzung, maßgeblich ist das kroatische Original“.
  Kroatische Eigentümer erhalten nur das Original. Einstellung `HAUSVERWALTUNG_ORIGINAL_LANG` (Standard `hr_HR`).

### PDF-Vorlage „hausverwaltung“
Layout nach Excel-Blatt *Rechnung*: Titel „Vorschreibung“, Datum/Fälligkeit, Empfänger (An) = Kontakt mit Funktion **Eigentümer** (sonst Adresse des Geschäftspartners), Absender (Von) = Gemeinschaft + Verwalter, Positionen, Gesamtbetrag ohne USt-Ausweis, Bankverbindung des Betriebskontos mit Zahlungsreferenz, Fußzeile mit OIB.

### KPD 2025 (Fiskalizacija 2.0)
* Neues Feld **KPD 2025** an jedem Produkt / jeder Leistung (Extrafeld `kpd`, für alle Firmen – auch Green Resorts nutzbar).
  Auswahlliste aus Tabelle `llx_c_hv_kpd` mit allen **3.359 gültigen 6-stelligen KPD-2025-Codes** (kroatisch + deutsch), Quelle [kpd.informacija.hr](https://kpd.informacija.hr/).
* Leistung *Verwaltungskosten* (`HV-VW`) wird mit **68.32.01** (Verwaltung von Wohnimmobilien gegen Entgelt) angelegt – Vorschlag, bitte bestätigen.
* Wird auf der Vorschreibung unter der Position gedruckt (`KPD xx.xx.xx`).
* Die Seite *Vorschreibung erstellen* warnt, solange eine verwendete Leistung keinen KPD-Code hat.
* Die konkreten Codes bitte über die KPD-2025-Suche der Porezna uprava / DZS bestimmen und vom Steuerberater bestätigen lassen.

### Verteilschlüssel
Verteilung nach **reiner Wohnfläche** (ohne Loggia, Terrasse, Balkon) – Feld `wohnflaeche_m2` am Geschäftspartner (Summe 599,02 m²).
Grund: einige Apartments haben nachträglich Balkone erhalten, die im Elaborat etažiranja 2023-127 nicht enthalten sind.
Hinweis: Der Miteigentumsvertrag (§3, §5.3) nennt die Elaborat-Anteile als Grundlage, *sofern die Eigentümergemeinschaft nichts anderes beschließt* – die Abrechnung über die Wohnfläche sollte daher per Beschluss festgehalten werden.

### m² aus dem Geschäftspartner
Die Fläche je Apartment wird aus den Extrafeldern des Geschäftspartners gelesen: zuerst `wohnflaeche_m2` (Wohnfläche), sonst `bk_schluessel` (Einstellung `HAUSVERWALTUNG_AREA_FIELDS`). Der Wert im Modul gilt nur, wenn dort nichts eingetragen ist.
Auf der Vorschreibung: Einzelpreis = **€/m²**, Menge = **m² des Apartments**; Apartment-Nr. und m² stehen im Betreff-Block.

### Berechnungslogik (wie Excel)
* Monatsanteil einer Kostenart = Brutto ÷ Rhythmus (Monate)
* Kostenart *alle Gebäude* → Anteil = m² Apartment ÷ m² aller Apartments
* Kostenart *Gebäude n* → Anteil = m² Apartment ÷ m² der Apartments in Gebäude n
* Rücklage = m² × Rücklage-Satz, Verwaltung = m² × Verwaltungs-Satz (netto) × (1 + USt Honorar)

## OIB (Pflichtangabe)
| Wer | Feld in Dolibarr |
|---|---|
| Eigentümer | Kontakt → Feld **OIB** (je Person; Fallback Prof-ID 1 / USt-ID des Apartments) |
| Gemeinschaft (Firma 5) | *Firma/Institution* → Prof-ID 1 (oder USt-ID); Registrierungsnummer in Prof-ID 2 wird mitgedruckt |
| Verwalter (Green Villa) | Geschäftspartner → USt-ID `HR…` (oder Prof-ID 1) |

Alle OIB werden mit der Prüfziffer kontrolliert; fehlende/ungültige zeigt *Vorschreibung erstellen* als Warnung.

## REST-API (nur lesend)
`GET /api/index.php/hausverwaltung/overview` · `/costitems` · `/settlement/{jahr}` – Header `DOLAPIKEY`, bei Multicompany `DOLAPIENTITY: 5`. Erfordert das Recht *Hausverwaltung ansehen*.

## Installation
1. **Backup** von Datenbank und Dokumentenverzeichnis.
2. `./build.sh` → `dist/module_hausverwaltung-1.0.0.zip`
3. Dolibarr: *Einstellungen → Module → Externes Modul bereitstellen* → ZIP hochladen
   (alternativ Ordner `hausverwaltung/` nach `htdocs/custom/` kopieren).
4. In der Firma (Multicompany-Entität) der Hausverwaltung das Modul **Hausverwaltung** aktivieren.
5. *Einstellungen* des Moduls (in Firma 5): Betriebskonto `1200`, Fälligkeitstag (5.), MwSt auf Vorschreibung (0 %), Hausverwalter = Green Villa…, USt auf Honorar (25 %).
6. **Startdaten Bibali 83b importieren** (Gebäude, 11 Apartments, Kostenplan aus der Excel-Vorlage).
7. *Gebäude*: je Gebäude das Rücklagen-Bankkonto zuordnen (vorher unter *Bank* anlegen).
8. *Apartments*: Geschäftspartner „Bibali 83 B - Apt x.y“ werden beim Import automatisch zugeordnet – bitte kontrollieren.
   Bei *Gebäude* die vorhandenen Rücklage-Leistungen `L_Rue_H1–H3` auswählen (sonst legt das Modul `HV-RL-…` an).
9. Benutzerrechte *Hausverwaltung* vergeben.

## Hinweise zur Excel-Vorlage (beim Übertragen gefunden)
* **Blatt „Rechnung“, Zelle G40:** `=SUM(G18:G28)*20%` rechnet 20 % MwSt auf die Vorschreibung, obwohl die Positionen 0 % haben (und 20 % kein kroatischer Satz ist). Das Modul verwendet den eingestellten Satz (Standard 0 %).
* **Blatt „Verteilung“:** Vierteljährliche Kosten werden mit `Per = 4` gesucht, im Blatt „Kosten“ stehen sie aber mit `3` → vierteljährliche Kosten würden im Excel ignoriert (derzeit keine vorhanden). Das Modul rechnet korrekt Brutto ÷ 3.
* Spaltenüberschrift „Haus 4“ in Verteilung!J4 sollte „Haus 2“ heißen.
* **Gartenpflege** ist Haus 3 zugeordnet – bitte prüfen, ob sie nicht alle Gebäude betrifft.

## Tests
`tests/` enthält CLI-Skripte, mit denen das Modul gegen eine lokale Dolibarr-20.0.4-Instanz getestet wurde
(Aktivierung, Vorschreibung, Kundenzahlung voll/teilweise → Rücklagen-Umbuchung, Lieferantenzahlung aus Rücklage, Zahlung löschen).

## Nächste Schritte (Roadmap)
* PDF der Jahresabrechnung je Eigentümer + automatische Nachzahlungs-/Gutschriftsrechnung
* Serienversand der Vorschreibungen per E-Mail (Sprache je Eigentümer de/hr)
* Vollständige Übersetzungen hr_HR / en_US
* Geplanter Job (Cron) für die monatliche Vorschreibung am 1.
