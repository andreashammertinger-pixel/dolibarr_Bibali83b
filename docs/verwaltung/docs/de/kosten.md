# Kosten und Eingangsrechnungen

## Kostenplan

*Hausverwaltung → Kostenplan*

Jede **Kostenart** hat:

| Feld | Bedeutung |
|---|---|
| Bezeichnung | wird in Erläuterung und Abrechnung gedruckt; Übersetzungen je Sprache möglich |
| Gebäude | ein Haus oder **alle Gebäude** |
| Betrag netto je Periode + MwSt | z. B. Versicherungsprämie pro Jahr |
| Zeitraum (Monate) | 12 = Jahresbetrag, 6 = halbjährlich, 1 = monatlich |
| Verteilschlüssel | m² (Standard), Miteigentumsanteil, gleich je Einheit |
| aktiv | nur aktive Kostenarten fließen in die Vorschreibung |

Der Monatsbetrag ist **Betrag × (1 + MwSt) ÷ Monate**. Der Betriebskostensatz je Apartment ergibt sich aus der Summe seiner Anteile ÷ m², gerundet auf 2 Nachkommastellen.

!!! example "Beispiel"
    Gartenpflege Haus 3: 150 € netto + 25 % MwSt je **6 Monate** → 31,25 € pro Monat → nur Haus 3.

## Eingangsrechnungen

Lieferantenrechnungen normal in Dolibarr erfassen (*Einkauf → Lieferantenrechnungen*) und zusätzlich:

- **Kostenart (Hausverwaltung)** wählen → bestimmt die Verteilung in der Jahresabrechnung,
- **Aus Rücklage bezahlt** anhaken, wenn es eine Instandhaltung aus der Rücklage ist → Betrag wird vom Rücklagenkonto umgebucht und **nicht** in die Betriebskostenabrechnung übernommen.

## Mobil erfassen

*Hausverwaltung → Mobil erfassen* (am Handy): Lieferant, Kostenart, Betrag, Foto des Belegs – fertig. Die Rechnung wird als Entwurf angelegt.

## Honorar des Verwalters

*Vorschreibung erstellen → Honorar-Eingangsrechnung des Verwalters anlegen* legt die Rechnung des Verwalters an die Gemeinschaft an (0,30 €/m² netto + MwSt).
