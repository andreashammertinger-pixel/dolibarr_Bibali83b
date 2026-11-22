# Dolibarr-Modul „Küchenangebot“ (migora)

Eigenständiges externes Modul für **Dolibarr 20.0.x** (ab 19). Es ändert nichts an Dolibarr selbst: eigene Tabellen (`llx_ka_*`), eigene Seiten unter `/custom/kuechenangebot/`, eigene PDF-Vorlage `migorakueche` für Angebote und ein zusätzlicher Reiter **Küchenkalkulation** im Dolibarr-Angebot.

Aus den Standard-Unterlagen der Nobilia-Planung (CARAT/PDF Xpansion) wird ein Kundenangebot im migora-Layout erzeugt:

* **AN…pdf** – Nobilia-Angebot (Merkmale, Positionen, Geräte, Summe Holz/Elektro/Zubehör)
* **BD…pdf** – Pläne mit Grundriss, Ansicht und **3D-Perspektive** (das Bild wird automatisch herausgelöst)

Das Auslesen läuft in PHP, ohne Zusatzprogramme auf dem Server.

## Installation
1. `./build.sh kuechenangebot` → `dist/module_kuechenangebot-<version>.zip`
2. In der Firma **migora gmbh**: *Einstellungen → Module → Externes Modul bereitstellen* → ZIP hochladen.
3. Modul **Küchenangebot** aktivieren (braucht *Geschäftspartner* und *Angebote*).
4. Rechte vergeben: *Küchenkalkulationen ansehen / erstellen / Einstellungen pflegen*.
5. *Küchen → Einstellungen*: Sätze, Gemeinkosten, AGB-Adresse, Ansprechpartner prüfen.

Das Logo kommt aus *Einstellungen → Firma/Organisation* (bei migora: `Migora_kl.jpg`), die Akzentfarbe Taupe `#AE9D95` stammt aus dem Logo und lässt sich in den Einstellungen ändern.

## Ablauf (Küchen → Neue Küchenkalkulation)
| Schritt | Was passiert |
|---|---|
| **1. Kunde und Projekt** | Vorhandenen Kunden suchen (Name, Ort, E-Mail). Fehlt er, direkt darunter **neu anlegen** (Privatperson oder Firma). Mögliche Dubletten (gleicher Name oder gleiche E-Mail) werden angezeigt; anlegen erst nach Bestätigung. **Art**: *Private Küche* oder *Mehrfamilienhaus / Wohnbau*. **Zusätzliche Sprache** (Kroatisch, Englisch): Das Angebot ist immer deutsch, zusätzlich entsteht eine gekennzeichnete Übersetzungskopie. |
| **2. Nobilia-Unterlagen** | Je Küchentyp AN-PDF und BD-PDF hochladen, Stückzahl eintragen (z. B. 12 × Typ 22). Ausgelesen werden Merkmale, Positionen, Geräte mit Energieeffizienzklasse, Katalogwert, Vorschlag **Laufmeter** (aus den Sockelblenden bzw. Arbeitsplatten), 3D-Bild und Hinweise. **Preisbasis** je Typ: *Euro-Werte* oder *Katalogpunkte*. **Einkauf = Katalogwert × Faktor** (Punkte → Euro, Konditionen, Rabatt). Optional **Endpreis je Küche** (überschreibt den berechneten Preis). Eigenes Bild hochladen ist möglich. |
| **3. Kalkulation** | **Aufschlag** auf den Nobilia-Einkauf in % oder als Pauschale je Küche · **Antransport** pauschal je Projekt oder je Küche · **Arbeit Aufbau**: Arbeiter × Stunden × Stundensatz, Pauschale je Küche oder € je Laufmeter · **Müllentsorgung**: Pauschale oder Stunden × Stundensatz × Kostenfaktor, je Projekt oder je Küche · USt., Gültigkeit. Projektbeträge werden nach Einkaufsanteil auf die Küchen verteilt. |
| **Preis-Check** | **Preis kostendeckend** = (Einkauf + interne Montagekosten + Transport + Müll) × (1 + Gemeinkosten %). Dazu der Preis nach **Ø-Aufschlagsfaktor** und nach **Ø-Preis je Laufmeter** bisheriger Küchen, der **Ø-Preis je Küche** und der Deckungsbeitrag, mit Hinweis, ob der Preis darunter, darüber oder im Bereich liegt. **Getrennt nach privat und Mehrfamilienhaus.** |
| **4. Hinweise** | Textbausteine ankreuzen (Vorauswahl in den Einstellungen), Hinweise aus den Nobilia-Unterlagen übernehmen (interne wie „Katalogpunkte“ oder die AGB der Planerin werden ausgeblendet), eigene Anmerkung auf Deutsch und in der Zusatzsprache. |
| **5. Angebot erstellen** | Legt ein normales **Dolibarr-Angebot** an, je Küchentyp **eine Zeile** „Küche … inkl. Lieferung und Aufbau“. Die Einzelpositionen von Nobilia erscheinen nicht. Der Einkaufspreis wird für Dolibarrs Margen mitgegeben. Erzeugt werden `REF.pdf` (deutsch) und gegebenenfalls `REF_hr.pdf` bzw. `REF_en.pdf`. Solange das Angebot ein Entwurf ist, aktualisiert ein neuer Klick die Zeilen. Danach werden nur noch die PDFs neu erzeugt. |

Danach geht es wie gewohnt in Dolibarr weiter: freigeben, per E-Mail senden (beide PDFs anhängen), unterschrieben → Auftrag/Rechnung.

## PDF
1. **Deckblatt**: Logo, Empfänger, Angebotsdaten, großes **3D-Bild**, *Ihre Küche auf einen Blick* (Hersteller nobilia, Front, Korpus, Arbeitsplatte, Nischenrückwand, Griffe, **Elektrogeräte** mit EEK, Spüle und Armatur, **Aufbau durch Facharbeiter**, **Qualität**, **Gewährleistung** inkl. Garantieabwicklung über den Hersteller-Kundendienst) und der **Gesamtpreis** (brutto, netto).
2. **Leistungsumfang und Preis** (eine Zeile je Küchentyp), **Ausstattung im Detail**, Geräteliste.
3. **Hinweise**, **Angebotsbestimmungen**, **AGB mit QR-Code** (wenn Adresse eingestellt), **Annahme des Angebots** mit Preis, Ort/Datum und Unterschriften.

Deckblatttexte lassen sich über *Einstellungen → Übersetzung* anpassen (Schlüssel `KaPdfManufacturerText`, `KaPdfDurableText`, `KaPdfWarrantyText` …).
Nobilia-Begriffe übersetzt ein eingebautes Wörterbuch. Fehlende Begriffe ergänzen Sie in den Einstellungen (`deutsch = übersetzung`). Unbekannte Begriffe bleiben deutsch.

## Kennzahlen
*Küchen → Kennzahlen*: Angenommene (unterschriebene) und fakturierte Angebote aus dem Modul zählen automatisch. Frühere Küchen lassen sich händisch nachtragen (Einkauf, Verkauf, Laufmeter, Stück, optional Selbstkosten), jeweils als privat oder Mehrfamilienhaus.

## Einstellungen (Standardwerte = Beispiele, bitte anpassen)
| Konstante | Bedeutung | Standard |
|---|---|---|
| `KA_KOSTEN_STUNDE` | interne Kosten je Monteurstunde | 45 € |
| `KA_SATZ_STUNDE` | Verkaufs-Stundensatz Montage | 65 € |
| `KA_STD_PRO_LFM` | Richtwert Montagestunden je Laufmeter (wenn keine Stunden eingetragen) | 2,5 |
| `KA_GK_PRIVAT` / `KA_GK_MFH` | Gemeinkosten-Zuschlag | 12 % / 8 % |
| `KA_AUFSCHLAG_PRIVAT` / `_MFH` | Standard-Aufschlag | 30 % / 20 % |
| `KA_FAKTOR_EUR` / `_PUNKTE` | Standard-Faktor Katalogwert → Einkauf | 1 / 1 |
| `KA_AGB_URL` | Adresse der AGB für den QR-Code | leer |
| `KA_TERMS_DE/HR/EN` | Angebotsbestimmungen (eine je Zeile, `{GUELTIG_TAGE}`) | siehe unten |

## Angebotsbestimmungen – rechtliche Prüfung
Grundlage ist die migora-Vorlage (Angebot 2024-0040). Sie wurde für Verbraucher- und Unternehmergeschäfte in Österreich überarbeitet. **Keine Rechtsberatung** – vor dem Einsatz bitte von einer Anwältin, einem Anwalt oder der WKO prüfen lassen.

| Vorlage | Überarbeitet | Grund |
|---|---|---|
| Abrechnung erfolgt nach Verbrauch | Küche zum **Pauschalpreis**; nur Zusatzleistungen nach Aufwand/Verbrauch, nach Zustimmung | Widerspräche sonst dem Gesamtpreis im Angebot |
| 50 % bei Auftragsbestätigung, 50 % nach Fertigstellung | unverändert | – |
| Aufpreise/Sonderwünsche 100 % ab Auftragsbestätigung | Aufpreis wird **vorher bekannt gegeben** und ist ab Bestätigung zur Gänze zu bezahlen | Transparenz (§ 6 Abs 3 KSchG) |
| Stornogebühr 30 %, bei Möbeln 100 % | 30 %; bei bestellten, nach Maß geplanten Möbeln **bis zu** 100 %; gesetzliche Rücktrittsrechte bleiben | Stornogebühren sind Vertragsstrafen. Das Gericht kann sie mäßigen (§ 1336 Abs 2 ABGB, gegenüber Verbrauchern nicht abdingbar). Ein pauschaler Satz von 100 % ohne Bezug auf den tatsächlichen Aufwand ist angreifbar. |
| Zahlung ohne Abzug, **12 % Mahnspesen** bei Verzug | Verzugszinsen in **gesetzlicher Höhe** (Verbraucher 4 % p. a., Unternehmer 9,2 Prozentpunkte über dem Basiszinssatz) plus **notwendige und angemessene** Mahn- und Inkassokosten | Pauschale prozentuale Mahnspesen sind bei Verbrauchern sehr wahrscheinlich unzulässig: Ersetzt werden nur notwendige, angemessene Betreibungskosten (§ 1333 Abs 2 ABGB). Unter Unternehmern sind zusätzlich 40 € Pauschale möglich (§ 458 UGB). |
| Eigentumsvorbehalt | unverändert | – |
| *neu* | Gültigkeit, Aufmaß, Termine, Baufreiheit/Anschlüsse bauseits, zumutbare Abweichungen, gesetzliche Gewährleistung + Hersteller-Garantie über Kundendienst, österreichisches Recht, Gerichtsstand Graz nur für Unternehmer | Bei Verbrauchern ist der Gerichtsstand gesetzlich festgelegt (§ 14 KSchG). Änderungen nach Vertragsschluss sind nur zumutbar und geringfügig zulässig (§ 6 Abs 2 Z 3 KSchG). |

**Rücktrittsrecht (FAGG):** Wird außerhalb der Geschäftsräume oder im Fernabsatz abgeschlossen (z. B. Annahme beim Kunden zu Hause oder per E-Mail), gilt grundsätzlich ein Rücktrittsrecht von 14 Tagen. Für **nach Kundenspezifikation angefertigte Waren** gibt es keines (§ 18 Abs 1 Z 3 FAGG). Nach EuGH C-529/19 (*Möbel Kraft*) gilt das auch dann, wenn mit der Fertigung noch nicht begonnen wurde. Ob eine geplante Nobilia-Küche aus Serienelementen samt Montage darunterfällt, ist im Einzelfall **nicht sicher**. Für Abschlüsse außerhalb der Geschäftsräume sollte eine Widerrufsbelehrung bereitliegen.

**Gewährleistung:** Für Verbraucher gilt das VGG (2 Jahre für Waren, Vermutung der Mangelhaftigkeit 1 Jahr). Ob eine fest eingebaute Küche als unbewegliche Sache mit 3 Jahren gilt (§ 933 ABGB), ist **strittig**. Ein Ausschluss gegenüber Verbrauchern ist unzulässig.

### AGB per QR-Code – ist das gedeckt?
**Grundsätzlich ja, mit Bedingungen.** AGB werden nur Vertragsinhalt, wenn auf sie vor Vertragsabschluss deutlich hingewiesen wird und der Kunde die Möglichkeit hat, sie zumutbar zur Kenntnis zu nehmen. Ein Hinweis im Angebot mit Klartext-Adresse **und** QR-Code erfüllt das in der Regel. Unter Unternehmern ist das üblich und anerkannt. Bei **Verbrauchern** ist es sicherer, die AGB zusätzlich mit dem Angebot oder der Auftragsbestätigung als PDF mitzuschicken bzw. auf Wunsch auszuhändigen. Diesen Satz enthält das Angebot bereits. Außerdem:
* Die Adresse muss **dauerhaft** dieselbe Fassung zeigen. Bei Änderungen eine neue Version mit Datum anlegen, z. B. `migora.at/agb-2026-11`, und die alte abrufbar lassen.
* Überraschende oder nachteilige Klauseln gelten nicht (§ 864a ABGB), gröblich benachteiligende sind nichtig (§ 879 Abs 3 ABGB), bei Verbrauchern gilt zusätzlich die Liste in § 6 KSchG.
* Bei Widerspruch gehen die Bestimmungen im Angebot den AGB vor (Satz in den Angebotsbestimmungen).

**Pflichtangaben auf Geschäftspapieren (§ 14 UGB):** Firma, Rechtsform, Sitz, **Firmenbuchnummer und Firmenbuchgericht**. Die Fußzeile nimmt die Daten aus *Firma/Organisation*. In Dolibarr sind bei migora derzeit Steuernummer und „Graz“ hinterlegt. Bitte die **FN** im Feld *Firmenbuchnummer* (Prof. Id 3) ergänzen.

??? quelle "Quellen"
    * ABGB §§ 864a, 879, 933, 1000, 1333, 1336 – RIS Bundesrecht konsolidiert, https://www.ris.bka.gv.at/GeltendeFassung.wxe?Abfrage=Bundesnormen&Gesetzesnummer=10001622
    * KSchG §§ 6, 14 – https://www.ris.bka.gv.at/GeltendeFassung.wxe?Abfrage=Bundesnormen&Gesetzesnummer=10002462
    * FAGG § 18 – https://www.ris.bka.gv.at/GeltendeFassung.wxe?Abfrage=Bundesnormen&Gesetzesnummer=20008847
    * UGB §§ 14, 456, 458 – https://www.ris.bka.gv.at/GeltendeFassung.wxe?Abfrage=Bundesnormen&Gesetzesnummer=10001702
    * VGG (Verbrauchergewährleistungsgesetz, BGBl. I Nr. 175/2021) – https://www.ris.bka.gv.at/GeltendeFassung.wxe?Abfrage=Bundesnormen&Gesetzesnummer=20011699
    * EuGH 21.10.2020, C-529/19, *Möbel Kraft* – https://curia.europa.eu
    * WKO, Allgemeine Geschäftsbedingungen / Ratgeber – https://www.wko.at
    * Basiszinssatz: Oesterreichische Nationalbank – https://www.oenb.at

## Technik
| Datei | Inhalt |
|---|---|
| `class/kapdfreader.class.php` | kleiner PDF-Leser (Objektströme, Type0-Schriften, Bilder, Textzeilen mit x-Position) |
| `class/kanobiliaparser.class.php` | Auswertung Nobilia/CARAT: Kopf, Merkmale, Positionen, Summen, Geräte, Laufmeter, 3D-Bild |
| `class/kakalk.class.php` | Kalkulation, Küchentypen, Kennzahlen-Vergleich |
| `class/kaservice.class.php` | Kunde anlegen/Dubletten, Import, Dolibarr-Angebot erzeugen |
| `class/kapdf.class.php` | PDF im migora-Layout (TCPDF, DejaVu Sans für č/ć/ž/š) |
| `class/kadict.class.php` | Fachwörterbuch de → hr/en |
| `core/modules/propale/doc/pdf_migorakueche.modules.php` | Dokumentvorlage für Angebote |

Getestet mit Dolibarr 20.0.4 und den Beispiel-Unterlagen *AN/BD 2205-8* (nicht im Repository – sie enthalten Kundendaten).
