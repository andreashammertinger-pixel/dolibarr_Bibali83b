# Dolibarr-Modul „Hausverwaltung“ – Bibali 83b

Externes Modul für **Dolibarr 20.0.x** (getestet mit 20.0.4, Multicompany-fähig über `entity`).
Bildet die Logik der Excel-Vorlage `Bibali83B_Betriebskosten_Template.xlsx` in Dolibarr ab.

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
| **Jahresabrechnung** | Plan vs. Ist je Kostenart, Ist-Anteil je Apartment vs. vorgeschriebene Akonto-BK → Saldo |
| **Mobil** | `/custom/hausverwaltung/mobile.php`: Beleg fotografieren, Kostenart wählen (Lieferant und MwSt werden vorbelegt), Betrag → Lieferantenrechnung im Entwurf inkl. Foto. Außerdem offene Vorschreibungen und Rücklagenstände |

### Berechnungslogik (wie Excel)
* Monatsanteil einer Kostenart = Brutto ÷ Rhythmus (Monate)
* Kostenart *alle Gebäude* → Anteil = m² Apartment ÷ m² aller Apartments
* Kostenart *Gebäude n* → Anteil = m² Apartment ÷ m² der Apartments in Gebäude n
* Rücklage = m² × Rücklage-Satz, Verwaltung = m² × Verwaltungs-Satz

## Installation
1. **Backup** von Datenbank und Dokumentenverzeichnis.
2. `./build.sh` → `dist/module_hausverwaltung-1.0.0.zip`
3. Dolibarr: *Einstellungen → Module → Externes Modul bereitstellen* → ZIP hochladen
   (alternativ Ordner `hausverwaltung/` nach `htdocs/custom/` kopieren).
4. In der Firma (Multicompany-Entität) der Hausverwaltung das Modul **Hausverwaltung** aktivieren.
5. *Einstellungen* des Moduls: Betriebskonto, Fälligkeitstag (5.), MwSt auf Vorschreibung (0 %).
6. **Startdaten Bibali 83b importieren** (Gebäude, 11 Apartments, Kostenplan aus der Excel-Vorlage).
7. *Gebäude*: je Gebäude das Rücklagen-Bankkonto zuordnen (vorher unter *Bank* anlegen).
8. *Apartments*: jedem Apartment den Geschäftspartner zuordnen (Geschäftspartner muss „Kunde“ sein).
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
