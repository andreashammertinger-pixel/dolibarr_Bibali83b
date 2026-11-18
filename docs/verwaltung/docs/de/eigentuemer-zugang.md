# Eigentümer-Zugang

Eigentümer können sich in Dolibarr anmelden und sehen dort **nur ihr eigenes Apartment**: Vorschreibungen, Zahlungen, Erläuterungen und Abrechnungsblätter.

## Eigentümer als Kontakt

Am Apartment (*Apartments & Kontakte*) → *Kontakte*:

- **Eigentümer = Ja** → erhält Vorschreibungen und Abrechnungen, steht mit Name und OIB auf den Dokumenten.
- Mieter, Notfallkontakte usw. auf *Nein* lassen.
- **OIB** (11 Ziffern, wird geprüft) und **E-Mail** eintragen.
- Firma als Eigentümer: Firmenname als Nachname, Vorname leer.

## Benutzer anlegen

1. *Benutzer & Gruppen → Neuer Benutzer*, **extern** – verknüpft mit dem Apartment (Geschäftspartner).
2. In die Gruppe **Bibali83B** aufnehmen.

Rechte der Gruppe in der Firma *Bibali 83B*:

| Bereich | Recht |
|---|---|
| Rechnungen | **Kundenrechnungen einsehen** |
| Geschäftspartner | **Geschäftspartner einsehen** |
| Kontakte | aus |
| **Hausverwaltung** | **alles aus** |

!!! danger "Keine Hausverwaltungs-Rechte für Eigentümer"
    Die Hausverwaltung enthält Daten **aller** Apartments. Sie ist für externe Benutzer gesperrt; die *Übersicht* zeigt eine rote Warnung, falls trotzdem Rechte vergeben sind.

## Was der Eigentümer sieht

- *Vorschreibung | Zahlung → Vorschreibungen*: seine Vorschreibungen mit PDF (Original, Kopie, Erläuterung)
- Apartment → *Verknüpfte Dokumente*: alles, was an ihn versendet wurde (Vorschreibungen, Erläuterungen, Abrechnungen) mit Versanddatum
- Anleitung für Eigentümer: [Hilfe für Eigentümer](../../eigentuemer/de/)
