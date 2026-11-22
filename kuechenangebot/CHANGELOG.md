# Changelog Küchenangebot

## 1.4.0
- **Unterlagen (Grundlage des Angebots)** in der Küchenkalkulation: beliebig viele Dateien hochladen (Einreichplan, Skizze, Fotos, PDFs …), anzeigen, herunterladen, löschen. Nur intern – sie werden nicht zum Angebot gelegt und nicht an den Kunden geschickt

## 1.3.0
- **Pläne mit migora-Plankopf**: Beim Hochladen der Pläne (BD…pdf) werden Logo und Kontakt der Planerin sowie die Kundenadresse aus dem PDF entfernt und durch migora-Logo, Kontakt und die Bezeichnung (z. B. „BVH …“) ersetzt – PHP-Fassung des migora Planumwandlers (Repository migora_logo_change), gleiche Bereiche und Positionen
- **Freigabe erst nach Annahme**: Die Pläne liegen bis dahin nur intern in der Küchenkalkulation (Download-Link). Bei „Unterschrieben“ bzw. „Fakturiert“ kommen sie automatisch zu den Dokumenten des Angebots, bei Auftragserstellung auch zum Auftrag; beim Wiederöffnen werden sie beim Angebot entfernt
- Neue Felder: „Bezeichnung im Plankopf“ je Kalkulation, Einstellungen „Kontakt im Plankopf“ und „Vorsatz Bezeichnung“
- Entfernt: Option „Original-Pläne anhängen“ (hätte Pläne mit dem Logo der Planerin weitergegeben)

## 1.2.0
- Privatkunden: Satz „Dieses Angebot wurde nach Ihren individuellen Angaben erstellt …“ auf dem Deckblatt, unter dem Gesamtbetrag und in der Angebotszeile in Dolibarr (de/hr/en)
- Fix: Angebotszeile in Dolibarr zeigte Programm/Front und Küchentyp nicht an

## 1.1.0
- **Privatkunde (Verbraucher)** in der Kalkulation: Das Angebot enthält zwei Bestätigungen zum Ankreuzen – individuelle Anfertigung, daher kein Rücktrittsrecht (§ 18 Abs 1 Z 3 FAGG), und ausdrücklicher Beginn vor Fristende (§§ 10, 18 Abs 1 Z 1 FAGG); in de/hr/en
- Angebotsbestimmungen: neuer Punkt Rücktrittsrecht; Storno für Verbraucher nach tatsächlichen Kosten (keine Pauschale, OGH 4 Ob 236/22t), für Unternehmer 30 % bzw. bis 100 % bei Maßware

## 1.0.0
- Erste Version für migora gmbh
- Nobilia-Angebot (AN…pdf) und Pläne (BD…pdf) aus CARAT einlesen, ohne Zusatzprogramme: Merkmale, Positionen, Geräte mit EEK, Summen, Laufmeter-Vorschlag, 3D-Bild, Hinweise
- Kunde aus Dolibarr wählen oder direkt neu anlegen (mit Dublettenprüfung)
- Private Küche oder Mehrfamilienhaus mit mehreren Küchentypen × Stückzahl
- Kalkulation: Aufschlag (% oder pauschal), Antransport, Aufbau (Arbeiter × Stunden × Satz, Pauschale, je Laufmeter), Müllentsorgung (pauschal oder Stunden × Satz × Kostenfaktor), Katalogpunkte oder Euro mit Faktor, Endpreis je Küche
- Preis-Check: kostendeckender Preis, Ø-Aufschlagsfaktor, Ø je Laufmeter, Ø je Küche – getrennt nach privat / Mehrfamilienhaus; Kennzahlen-Seite mit händischen Einträgen
- Dolibarr-Angebot mit einer Zeile je Küchentyp „inkl. Lieferung und Aufbau“; PDF-Vorlage `migorakueche` (Deckblatt mit 3D-Bild, Highlights und Gesamtpreis, Ausstattung, Hinweise, Angebotsbestimmungen, AGB-QR-Code, Annahme mit Unterschrift)
- Immer deutsch, optional Übersetzungskopie Kroatisch oder Englisch
- Textbausteine (de/hr/en) mit Vorauswahl, überarbeitete Angebotsbestimmungen
