# Einstellungen und neues Objekt

## Einstellungen

*Hausverwaltung → Einstellungen* (Zahnrad beim Modul):

| Einstellung | Wofür |
|---|---|
| Bankkonto, Zahlungsart, Fälligkeitstag | Daten auf der Vorschreibung |
| MwSt Betriebskosten / Verwaltung | Vorschreibung ohne MwSt (Gemeinschaft), Verwaltung mit 25 % |
| Verwalter | Firma, die verwaltet (steht auf allen Dokumenten) |
| Absender / BCC | E-Mail-Absender, Kopie jeder Vorschreibung |
| Digitaler Hinweis | „Digitales Dokument – kein Papierversand“ |
| Erläuterung anhängen | Erläuterung zur Vorschreibung mitsenden |
| Adresse Eigentümer-Zugang | erscheint in der Erläuterung |
| Hilfe-Adresse | Basisadresse dieses Handbuchs |
| Baukostensteigerung / Planungszeitraum | für die Rücklagen-Planung |

## Neues Objekt anlegen

Das Modul ist für weitere Gebäude/Gemeinschaften verwendbar – je Gemeinschaft eine eigene Firma (Multicompany).

1. Firma anlegen (OIB in Prof-ID 1, Registrierungsnummer in Prof-ID 2), Modul aktivieren.
2. Apartments als Geschäftspartner (Name endet auf die Nummer, z. B. „… Apt 1.1“), Felder *Wohnfläche m²*, Sprache.
3. Eigentümer als Kontakte (siehe [Eigentümer-Zugang](eigentuemer-zugang.md)).
4. Bankkonten der Gemeinschaft und je Haus ein Rücklagenkonto.
5. *Einstellungen → Startdaten importieren* mit der Vorlage `vorlage_startdaten.json` (Gebäude, Einheiten, Kostenplan).
6. Rücklagen-Plan importieren (`vorlage_ruecklagenplan.json`).
7. [Vorschreibung](vorschreibung.md) erstellen.
