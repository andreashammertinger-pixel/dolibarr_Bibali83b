# Rücklage (pričuva)

## Konten und Bewegungen

Jedes Haus hat ein eigenes **Rücklagenkonto** (Bankkonto in Dolibarr) und ein Rücklagen-Produkt. *Hausverwaltung → Rücklagen* zeigt Kontostand und Bewegungen je Haus:

- **Zugänge**: automatisch bei jeder Zahlung einer Vorschreibung (Rücklagenanteil),
- **Abgänge**: Eingangsrechnungen mit *aus Rücklage bezahlt*.

## Rücklagen-Planung

*Hausverwaltung → Rücklagen-Planung*

Mehrjahresplan der Instandhaltung (Fassade, Dach, Kanalanschluss …) je Haus oder für alle Häuser. Die Prognose rechnet mit:

- Startwert = aktueller Kontostand,
- Zuführung = aktuelle Sätze,
- Baukostensteigerung (Standard 3 % pro Jahr),
- Planungszeitraum (Standard 20 Jahre).

Ergebnis je Haus: niedrigster Stand, Jahr einer Unterdeckung und **Mindestsatz laut Plan**. Liegt der aktuelle Satz darüber, ist die Rücklage ausreichend.

!!! tip "Jährliche Begehung"
    Nach der jährlichen Begehung Positionen anpassen (Jahr, Betrag). Die Prognose rechnet sofort neu; die Erläuterung zur Vorschreibung zeigt die nächsten Maßnahmen.

**Druckansicht** (Knöpfe *Druckansicht HR, DE, …*) in jeder Sprache – als Beilage für die Eigentümerversammlung.

### Planpositionen bearbeiten

- **Planpositionen bearbeiten** (Knopf über der Liste): alle Positionen direkt in der Tabelle ändern – Jahr, Bezeichnung, Gebäude und Verteilschlüssel, Betrag zu heutigen Preisen, Schätzgrundlage, aktiv. Unten bis zu 3 neue Positionen eintragen, mit *Löschen* entfernen, dann *Speichern*.
- **Neue Planposition**: einzelne Position mit Hinweis und Übersetzungen anlegen.
- **Bezeichnung anklicken**: Details einer Position mit Hinweis und Übersetzungen je Sprache (für die Druckansicht und die Erläuterung).

!!! tip "Übersetzungen"
    Ändern Sie einen Text in der Tabelle, wird die Übersetzung in derselben Sprache automatisch mitgezogen. Die anderen Sprachen bitte über *Bezeichnung anklicken* prüfen.

**Import** (eingeklappt, optional – z. B. für ein neues Objekt): JSON-Datei mit Planpositionen (mehrsprachig). Häkchen *bestehende Planpositionen ersetzen* verhindert Doppelungen.
