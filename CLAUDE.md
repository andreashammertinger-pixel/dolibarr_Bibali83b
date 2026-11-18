# Arbeitsregeln für dieses Repository

## Dokumentation immer mitpflegen
Bei jeder Änderung am Modul `hausverwaltung/` (neue Funktion, geänderter Ablauf, neue Einstellung, neue Menüpunkte):

1. **Handbuch Verwaltung** `docs/verwaltung/docs/de/` **und** `docs/verwaltung/docs/hr/` anpassen (gleiche Dateinamen, Navigation in `docs/verwaltung/mkdocs.yml` inkl. `nav_translations`).
2. **Hilfe für Eigentümer** `docs/eigentuemer/docs/{hr,de,en,sl,uk}/` anpassen, wenn sich etwas für Eigentümer ändert (Dokumente, Zahlung, Zugang).
3. Beschriftungen in der Doku exakt wie in den Sprachdateien des Moduls bzw. von Dolibarr (`langs/*/hausverwaltung.lang`).
4. Hilfe-Links: `hvHelpUrl('<seite>/')` im `llxHeader` neuer Seiten setzen.
5. `./docs/build-docs.sh` muss fehlerfrei durchlaufen (`--strict`).

## News und Recht
* Beiträge in `docs/shared/news/<sprache>/JJJJ-MM-thema.md` (mindestens de und hr, für Eigentümer relevante auch en/sl/uk) und Eintrag in der Tabelle der jeweiligen `index.md`.
* **Immer mit Quellen** (Narodne novine, Ministerien, Porezna uprava, Fachliteratur) im Block `??? quelle "Quellen"`; Unsicheres als solches kennzeichnen, keine Rechtsberatung.

## Sonstiges
* Keine personenbezogenen Daten (Namen, OIB, Verträge) ins Repository.
* Kroatisch ist bei Dokumenten immer das Original; Eigentümer erhalten zusätzlich eine Kopie in ihrer Sprache.
* Versionsnummer in `core/modules/modHausverwaltung.class.php`, Eintrag in `CHANGELOG.md`, ZIP mit `./build.sh`.
