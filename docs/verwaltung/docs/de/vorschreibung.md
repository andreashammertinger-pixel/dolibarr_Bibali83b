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

## 2. Entwürfe erzeugen

1. Häkchen **„Rechnungen direkt freigeben“ nicht setzen** – so entstehen Entwürfe, die man noch löschen kann.
2. **Vorschreibungen erzeugen** klicken.

!!! tip "Korrektur nötig?"
    Entwurf in der Rechnungsliste löschen, Kostenplan bzw. Apartment korrigieren und erneut *Vorschreibungen erzeugen*. Bereits vorhandene Vorschreibungen für denselben Zeitraum werden **nicht** doppelt angelegt.

## 3. Freigeben

*Rechnung | Zahlung → Kundenrechnungen → Liste*

1. Filter *Status* = **Entwurf**, alle anhaken.
2. **Massenaktionen → Freigeben**.

Die Rechnungen bekommen ihre Nummer (z. B. `IN2611-0002`) und das PDF (kroatisch + Kopie).

!!! info "Freigabe nach dem Fälligkeitstag"
    Ist eine Rate bei der Freigabe schon fällig (z. B. Freigabe am 18., Fälligkeit am 5.), gilt automatisch eine Frist von **7 Tagen ab Freigabe**. Folgende Raten bleiben am Fälligkeitstag.

## 4. Versenden

*Hausverwaltung → Vorschreibungen versenden*

1. **Abrechnungsmonat** = erster Monat des Zeitraums (z. B. `2027-01`), *OK*.
2. Noch nicht versendete Vorschreibungen sind angehakt.
3. **Testversand**: eigene Adresse eintragen → *Testversand (nur an diese Adresse)*. Mails kommen mit `[TEST]` und allen Anhängen.
4. **An Eigentümer versenden** → bestätigen.

Jede Mail enthält:

- kroatisches Original und Übersetzungskopie der Vorschreibung,
- die **Erläuterung zur Vorschreibung** (Zusammensetzung, Sätze je Haus, Rücklage, Ablauf) – wird beim Versand erzeugt,
- Text in der Sprache des Apartments mit Monatsrate, Fälligkeit und Bankdaten.

!!! danger "Nicht über „E-Mail senden“ an der Rechnung"
    Das allgemeine E-Mail-Fenster von Dolibarr (an der Rechnung) hängt nur das kroatische PDF an und verwendet einen Standardtext. Immer *Hausverwaltung → Vorschreibungen versenden* verwenden.

## Nach dem Versand

- **Rückläufer** (falsche Adresse) kommen an die Absenderadresse – Postfach prüfen.
- Die Eigentümer sehen Vorschreibung und Erläuterung auch in ihrem [Zugang](eigentuemer-zugang.md).
- Zahlungen siehe [Zahlungen](zahlungen.md).
