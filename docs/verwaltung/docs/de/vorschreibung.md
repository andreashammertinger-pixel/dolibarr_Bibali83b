# Vorschreibung erstellen und versenden

Die Vorschreibung ist die Rechnung der Gemeinschaft an das Apartment. Sie enthält drei Positionen: **Betriebskosten**, **Rücklage** des Hauses und **Verwaltung** – jeweils Satz in €/m² × Wohnfläche.

## 1. Vorschau prüfen

*Hausverwaltung → Vorschreibung erstellen*

1. Zeitraum wählen:
    - **Jahresvorschreibung:** *Von* `2027-01`, *Bis* `2027-12` (Standardfall, im Dezember für das Folgejahr)
    - **Restjahr:** Knopf *Restjahr* (z. B. bei Start mitten im Jahr)
    - **einzelner Monat:** Knopf *Aktueller Monat*
2. **Vorschau aktualisieren** klicken und die Tabelle prüfen: Betrag je Apartment, Warnungen (OIB fehlt, kein Eigentümer-Kontakt, KPD fehlt).
3. In der Spalte **PDF-Vorschau** einzelne PDFs öffnen (Original und Übersetzung, mit Wasserzeichen *Entwurf*).

!!! info "Kroatisches Original ist maßgeblich (Finanz)"
    Die Übersetzung der Vorschreibung trägt oben links den Kasten **„KOPIE – Übersetzung“** mit dem Satz *„Diese Übersetzung dient nur zur Information. Rechtlich maßgeblich ist das kroatische Original.“* (in der Sprache des Eigentümers und auf Kroatisch). Das gilt nur für Vorschreibungen/Rechnungen; alle anderen Dokumente tragen nur den KI-Hinweis in der Fußzeile.

## 2. Entwürfe erzeugen

1. Häkchen **„Rechnungen direkt freigeben“ nicht setzen** – so entstehen Entwürfe, die man noch löschen kann.
2. **Vorschreibungen erzeugen** klicken.

!!! tip "Korrektur nötig?"
    Entwurf in der Rechnungsliste löschen, Kostenplan bzw. Apartment korrigieren und erneut *Vorschreibungen erzeugen*. Bereits vorhandene Vorschreibungen für denselben Zeitraum werden **nicht** doppelt angelegt.

## 3. Freigeben

*Vorschreibung | Zahlung → Vorschreibungen → Liste*

1. Filter *Status* = **Entwurf**, alle anhaken.
2. **Massenaktionen → Freigeben**.

Die Rechnungen bekommen ihre Nummer (z. B. `IN2611-0002`) und das PDF (kroatisch + Sprache des Eigentümers).

!!! info "Freigabe nach dem Fälligkeitstag"
    Ist eine Rate bei der Freigabe schon fällig (z. B. Freigabe am 18., Fälligkeit am 5.), gilt automatisch eine Frist von **7 Tagen ab Freigabe**. Folgende Raten bleiben am Fälligkeitstag.

## 4. Versenden

*Hausverwaltung → Vorschreibungen versenden*

1. **Abrechnungsmonat** = erster Monat des Zeitraums (z. B. `2027-01`), *OK*.
2. Noch nicht versendete Vorschreibungen sind angehakt.
3. **Testversand**: eigene Adresse eintragen → *Testversand (nur an diese Adresse)*. Mails kommen mit `[TEST]` und allen Anhängen.
4. **An Eigentümer versenden** → bestätigen.

Jede Mail enthält:

- Vorschreibung auf Kroatisch und in der Sprache des Eigentümers,
- die **Erläuterung zur Vorschreibung** (Zusammensetzung, Sätze je Haus, Rücklage, Ablauf) – wird beim Versand erzeugt,
- Text in der Sprache des Apartments mit Monatsrate, Fälligkeit und Bankdaten.

!!! danger "Nicht über „E-Mail senden“ an der Rechnung"
    Das allgemeine E-Mail-Fenster von Dolibarr (an der Rechnung) hängt nur das kroatische PDF an und verwendet einen Standardtext. Immer *Hausverwaltung → Vorschreibungen versenden* verwenden.

## Nach dem Versand

- **Rückläufer** (falsche Adresse) kommen an die Absenderadresse – Postfach prüfen.
- Alle versendeten Dateien liegen zusätzlich beim **Apartment → Verknüpfte Dokumente**, mit Versanddatum vorne im Dateinamen (z. B. `2026-11-18_IN2611-0001.pdf`). Der Testversand legt nichts ab.
- Die Eigentümer sehen Vorschreibung und Erläuterung auch in ihrem [Zugang](eigentuemer-zugang.md).

## Werte geändert? Vorschreibungen prüfen und neu erstellen

Ändern Sie Kosten, Flächen, den Rücklagen-Satz eines Hauses oder das Verwaltungshonorar, prüft das Modul alle **offenen** Vorschreibungen (noch nicht bezahlt). Passen welche nicht mehr, erscheint auf den Seiten der Hausverwaltung oben die Warnung **„Achtung: … offene Vorschreibungen passen nicht mehr zu den aktuellen Werten (bisher …, neu …). Sollen neue Vorschreibungen erstellt und versendet werden?“**

1. **Ja – prüfen und neu erstellen** öffnet *Hausverwaltung → Vorschreibungen prüfen*: je Vorschreibung die Abweichung (z. B. *Rücklage 0,80 statt 0,49 €/m²*), bisheriger und neuer Betrag, Differenz.
2. Auswahl prüfen, **Ausgewählte neu berechnen** und bestätigen. Die Vorschreibung wird mit den aktuellen Werten geändert – **gleiche Rechnungsnummer, keine Gutschrift** –, PDF (Original und Übersetzung) und Erläuterung werden neu erzeugt, die Fälligkeit bleibt.
3. **Versenden** (Knopf je Abrechnungsmonat) führt zum Versand mit den neu berechneten Vorschreibungen vorausgewählt. In der E-Mail steht: *„Diese Vorschreibung wurde an die aktuellen Sätze angepasst und ersetzt die bisher zugesandte Fassung (bisher …, neu …).“*

Soll eine Vorschreibung bewusst unverändert bleiben, **Ausgewählte beibehalten** – die Warnung erscheint dafür erst wieder, wenn sich die Werte erneut ändern. Bereits (teilweise) bezahlte Vorschreibungen werden nicht geändert. Jede Neuberechnung wird protokolliert und beim Apartment unter **Ereignisse** vermerkt (*Vorschreibung … neu berechnet: alt → neu*).

!!! warning "Vorschreibung und Erläuterung müssen zusammenpassen"
    Vor jedem Versand (einzeln und im Rundschreiben) vergleicht das Modul die Vorschreibung mit den aktuellen Werten. Weicht etwas ab, wird **nicht** versendet und keine Erläuterung erzeugt – zuerst wie oben neu berechnen.
