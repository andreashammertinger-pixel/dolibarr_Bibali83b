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

### Rücklagen-Satz je Haus festlegen

Unter der Zusammenfassung steht **Festlegung der Rücklagen-Sätze je Haus**. Je Haus sehen Sie:

| Spalte | Bedeutung |
|---|---|
| gesetzl. Minimum | 0,54 % der *etalonska cijena građenja* ÷ 12 (Werte in den Einstellungen) |
| Mindestsatz lt. Plan | kleinster gleichbleibender Satz, mit dem die Rücklage im Planungszeitraum nie negativ wird |
| Festlegung | **Mindestsatz lt. Plan**, **Mindestsatz lt. Plan × Faktor** (z. B. 1,2 = 20 % Puffer), **manuell** oder **gesetzliches Minimum** |
| Satz | Ergebnis, auf 2 Nachkommastellen **aufgerundet**; nie unter dem gesetzlichen Minimum (außer manuell – dann rote Warnung) |
| Begründung / Beschluss | z. B. „Beschluss Eigentümerversammlung 12.12.2026“ |

**Sätze festlegen** speichert den neuen Satz und protokolliert im **Verlauf** Datum, Benutzer, alten und neuen Satz, gesetzliches Minimum, Mindestsatz lt. Plan, Planvolumen des Hauses, Kontostand und Begründung. Der Satz gilt für **neue Vorschreibungen**; bereits erstellte bleiben unverändert.

!!! tip "Für die Eigentümerversammlung"
    Die **Druckansicht** (in jeder Sprache) enthält die Festlegung je Haus mit allen Grundlagen und den Verlauf – als Beilage zum Beschluss.

!!! note "Satz bleibt fest"
    Ändert sich der Plan später, ändert sich der Satz **nicht** automatisch. Die Tabelle zeigt den neuen Mindestsatz; erst *Sätze festlegen* übernimmt ihn – nachvollziehbar mit neuem Eintrag im Verlauf.

### Planpositionen bearbeiten

- **Planpositionen bearbeiten** (Knopf über der Liste): alle Positionen direkt in der Tabelle ändern – Jahr, Bezeichnung, Gebäude und Verteilschlüssel, Betrag zu heutigen Preisen, Schätzgrundlage, aktiv. Unten bis zu 3 neue Positionen eintragen, mit *Löschen* entfernen, dann *Speichern*.
- **Neue Planposition**: einzelne Position mit Hinweis und Übersetzungen anlegen.
- **Bezeichnung anklicken**: Details einer Position mit Hinweis und Übersetzungen je Sprache (für die Druckansicht und die Erläuterung).

!!! tip "Übersetzungen"
    Ändern Sie einen Text in der Tabelle, wird die Übersetzung in derselben Sprache automatisch mitgezogen. Die anderen Sprachen bitte über *Bezeichnung anklicken* prüfen.

**Import** (eingeklappt, optional – z. B. für ein neues Objekt): JSON-Datei mit Planpositionen (mehrsprachig). Häkchen *bestehende Planpositionen ersetzen* verhindert Doppelungen.
