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

**Import**: JSON-Datei mit Planpositionen (mehrsprachig). Häkchen *bestehende Planpositionen ersetzen* verhindert Doppelungen.
