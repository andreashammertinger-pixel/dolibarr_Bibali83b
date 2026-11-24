# Changelog Küchenangebot

## 1.12.0
- **AB ist maßgeblich**: Eine gültige nobilia-Auftragsbestätigung ersetzt alle Angaben aus dem Nobilia-Angebot (AN) – Ausführung (Front, Korpus, Griff, Sockel, Arbeitsplatte, **Nischenrückwand** aus der Position Nischenverkleidung/APL-Dekor), Geräte und Positionen. Geräte mit gleicher Artikelnummer im AN behalten die ausführlichere Bezeichnung und Energieklasse. Die AN-Daten bleiben intern gesichert; ein später erneut hochgeladenes AN überschreibt die AB nicht
- Abweichungen AB ↔ AN werden nur noch als Hinweis angezeigt (kein „trotz Abweichung übernehmen“ mehr nötig); Storno-ABs und ABs ohne Preis werden weiterhin nicht übernommen
- **Blockverrechnung** der AB wird gelesen (Blockvereinbarung, Auftragswerte Möbel/Geräte/Zubehör, Rabatte, Sonderrabatt, Teuerungszuschlag); Faktor = AB-Gesamtpreis / Listenwert der AB (Summe der Auftragswerte)

## 1.11.1
- Fußzeile: österreichische Bezeichnungen statt „Prof Id 1/2/3“ – St.-Nr., FN, Firmenbuchgericht, Geschäftsführer (hr/en übersetzt)
- Seite 1: Bezeichnung des Küchentyps (z. B. „Stan B, D, F“) in eigener Zeile unter „Darstellung unverbindlich …“
- „Ausstattung im Detail“ zeigt das 3D-Bild bei jedem Küchentyp, auch beim ersten

## 1.11.0
- **Interne Kalkulation (PDF)**: Knopf in der Küchenkalkulation öffnet eine Aufstellung nur zum internen Check bzw. als Vorlage (rot markiert „INTERN – nicht für Kunden“, wird nicht beim Angebot abgelegt): Kunde/Bauvorhaben/Art/Version/Angebot, verwendete Werte, je Küchentyp Nobilia-Angebot, Katalogwert, Faktor, Einkauf (ggf. laut AB), Laufmeter, Zusatzpositionen und Preisaufbau Kosten/Verkauf/Deckung je Küche (Küche, Montage, Antransport, Müll, Gemeinkosten, Rundung/Endpreis), Gesamtsummen inkl. kostendeckendem Preis, Deckungsbeitrag, Faktor, Preis-Check gegen bisherige Küchen

## 1.10.2
- PDF Seite 1: „Ihr Ansprechpartner“ mehrzeilig – Name, darunter Telefonnummer (und ggf. E-Mail); Trennung in der Einstellung mit Komma

## 1.10.1
- Fix: Mehrzeilige Felder (Angebotsbestimmungen, Wörterbuch, Kontakt im Plankopf, Textbausteine, Objektadresse, Anmerkungen) zeigten Zeilenumbrüche als „\n“ an; nach dem Speichern standen die Angebotsbestimmungen als ein Absatz unter Punkt 1. Umbrüche bleiben jetzt erhalten, bereits so gespeicherte Texte werden beim Lesen automatisch repariert

## 1.10.0
- **Firmenkunden** („Privatkunde“ nicht angehakt): Angebot zeigt nur **Nettopreise** mit Hinweis „Nettopreis, zuzüglich der gesetzlichen Umsatzsteuer“ – keine USt-Zeile, kein Bruttobetrag (Deckblatt, Typenübersicht, Summen, Änderungsblock, Annahme). Das Dolibarr-Angebot rechnet weiter mit USt für die spätere Rechnung. Runden bei Firmen auf den Nettopreis
- **Übersetzung hr/en** verbessert: zusammengesetzte nobilia-Farben (z. B. Taupegrau → taupe siva, Seidengrau, Alpinweiß), Dekore „… Nachbildung“ → „imitacija …“ (mit Genitiv, z. B. imitacija betona), Armaturen-Begriffe (Küchenarmatur, Hochdruck, Rohrauslauf, chrom), „Typ“ in Typbezeichnungen → „Tip“

## 1.9.0
- Kalkulation je Angebot vollständig anpassbar: zusätzlich **interne Kosten je Monteurstunde, Richtwert Stunden je Laufmeter und Gemeinkosten %** (bisher nur in den Einstellungen); alle Werte vorbelegt aus den Einstellungen passend zur Art
- **„Standardwerte laden“** setzt die Kalkulation eines Angebots auf die Einstellungen zurück (z. B. nach Wechsel privat ↔ Mehrfamilienhaus); Positionen und Texte bleiben
- **Endpreis je Küche runden** (brutto auf 1, 10, 50 oder 100 €)

## 1.8.0
- **nobilia-Auftragsbestätigung (AB) importieren** je Küchentyp: AB-Nr., AB-Datum, Kommission, KV-Zeichen, Liefertermin, Ausführung und alle Positionen mit Brutto/Netto werden gelesen; die Liefer-/Rechnungsadresse wird ignoriert
- Gültige AB (kein Storno, Gesamtpreis > 0, passt zum Nobilia-Angebot): **Einkauf je Küche = AB-Gesamtpreis**, der Faktor des Typs wird daraus berechnet (Einkauf / Katalogwert). Storno-ABs und ABs ohne Preis werden erkannt und nicht übernommen
- **Vergleich AB ↔ AN**: Artikel und Farben (Front, Korpus, Arbeitsplatte, Griff); bei Abweichung nur mit „trotz Abweichung übernehmen“
- Ist das Angebot schon unterschrieben, wird der Verkaufspreis vor der Übernahme festgeschrieben – es ändert sich nur der Einkauf (Nachkalkulation)
- „Faktor als Standard übernehmen“ für Katalogpunkte bzw. Euro-Werte
- Kennzahlen: Verkauf aus dem Dolibarr-Angebot, Einkauf aus der Kalkulation (laut AB)
- Fix PDF-Leser: indirekte Längenangaben mit mehrstelliger Objektnummer (z. B. PDFlib-Dateien) wurden falsch gelesen

## 1.7.0
- **Deckblatt 50 : 50**: links Titel, Einleitung und Highlights, rechts das 3D-Bild in einheitlicher Größe (oben bündig mit „Ihr Küchenangebot“), darunter Gesamtpreis
- **Mehrere Küchentypen** (Mehrfamilienhaus): Übersicht „Küchentypen“ mit Stückzahl und Preis je Küche brutto auf dem Deckblatt; Highlights zusammengeführt – gleiche Werte einmal, abweichende je Typ („Kaschmir (Typ 23)“), Geräte mit Angabe der Typen, wenn sie nicht in allen Küchen sind
- Labelspalte der Highlights passt sich dem längsten Begriff an (keine Trennung mitten im Wort)

## 1.6.0
- **Versionen**: „Neue Version erstellen“ (wenn das Angebot freigegeben ist) kopiert die Kalkulation samt Kunde, Sätzen, Hinweisen, Unterlagen und Nobilia-Dateien; die alte Version wird gesperrt. Beim Erstellen des neuen Angebots wird das alte verknüpft und auf „Nicht unterschrieben“ gesetzt
- **Zusatz- und Minderpositionen** je Küchentyp (+/−), Preisbasis Nobilia-Wert (× Faktor + Aufschlag) oder Verkaufspreis netto; im Küchenpreis enthalten, im Angebot unter „inkl. Änderungen“ genannt
- PDF: Versionsangabe im Kopf, Block „Änderungen gegenüber Version …“ (geänderte Ausstattung, Geräte neu/entfallen, neue Planung, Stückzahl, Positionen, Preisdifferenz)
- Deckblatt passt Bild und Schriftgröße automatisch an, damit alles auf Seite 1 bleibt

## 1.5.0
- Angebot nennt die **Grundlage**: „Grundlage dieses Angebots: Beratungsgespräch … sowie folgende Unterlagen: …“ (Seite 2 unter dem Preis). Text zum Gespräch je Kalkulation eintragbar, Unterlagen werden aus dem Bereich „Unterlagen“ genannt (abschaltbar); de/hr/en

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
