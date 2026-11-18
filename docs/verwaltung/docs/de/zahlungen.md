# Zahlungen und Zahlungsstand

## Zahlung erfassen

Eine Zahlung eines Eigentümers muss **der Vorschreibung zugeordnet** werden – nur dann wird der Rücklagenanteil automatisch umgebucht.

- **an der Rechnung:** Rechnung öffnen → *Zahlung erfassen* → Betrag, Datum, Bankkonto der Gemeinschaft
- **beim Bankabgleich:** Bankbuchung der Rechnung zuordnen

!!! info "Automatische Rücklagen-Umbuchung"
    Beim Erfassen der Zahlung bucht das Modul den Rücklagenanteil vom Bankkonto der Gemeinschaft auf das **Rücklagenkonto des Hauses** (z. B. *Rezerve-Kuća1*). Bei Teilzahlungen anteilig. Wird die Zahlung gelöscht, wird auch die Umbuchung zurückgenommen.

!!! warning
    Eine Bankbuchung **ohne** Bezug zur Rechnung löst keine Rücklagen-Umbuchung aus und die Rechnung bleibt offen.

## Zahlungsstand

*Hausverwaltung → Zahlungsstand*

Je Apartment: Vorschreibung gesamt, **fällig bis heute** (nach Ratenplan), bezahlt, **Rückstand**, nächste Rate. Rückstände sind rot markiert.

## Guthaben aus der Jahresabrechnung

Ein Guthaben wird beim Erstellen der nächsten Vorschreibungen automatisch abgezogen (Zeile *verrechnetes Guthaben*). Siehe [Jahresabrechnung](jahresabrechnung.md).
