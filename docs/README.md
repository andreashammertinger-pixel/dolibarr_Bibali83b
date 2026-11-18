# Hilfe-Website (Handbuch, Eigentümerhilfe, News)

Statische Website mit [MkDocs Material](https://squidfunk.github.io/mkdocs-material/) und [mkdocs-static-i18n](https://ultrabug.github.io/mkdocs-static-i18n/).

| Ordner | Inhalt | Sprachen | Adresse |
|---|---|---|---|
| `verwaltung/` | Handbuch für Mitarbeiter | de (Standard), hr | `/verwaltung/` (Verzeichnisschutz empfohlen) |
| `eigentuemer/` | Hilfe für Eigentümer | hr (Standard), de, en, sl, uk | `/eigentuemer/` |
| `shared/news/<sprache>/` | News und Recht (mit Quellen) – in beide Bereiche kopiert | de, hr, en, sl, uk | `…/news/` |
| `shared/assets/` | Logo, Farben | – | – |
| `landing/` | Einstiegsseite (Rolle und Sprache wählen) | – | `/` |

## Bauen

```bash
pip install mkdocs-material mkdocs-static-i18n
./docs/build-docs.sh        # Ergebnis: docs/site/
```

## Veröffentlichen

GitHub Action `.github/workflows/docs.yml` baut bei jeder Änderung unter `docs/` und lädt per FTPS/SFTP auf den Webspace (Secrets siehe Workflow-Datei). `.htaccess`-Dateien auf dem Server (Verzeichnisschutz) bleiben unangetastet.

## Neue Seite / neue Sprache

* Seite: Datei in jedem Sprachordner anlegen (gleicher Dateiname), Eintrag in `nav` der `mkdocs.yml`, Titel-Übersetzung unter `nav_translations`.
* Sprache: Ordner `docs/<code>/` anlegen und in `mkdocs.yml` unter `languages` eintragen; fehlende Seiten zeigen die Standardsprache.
* News: Datei `JJJJ-MM-thema.md` in `shared/news/<sprache>/` und Zeile in der Tabelle von `index.md`. Immer mit Abschnitt **Quellen**.

## Hinweis

Material for MkDocs wird bis 5.5.2027 gepflegt; Nachfolger ist *Zensical* (liest dieselbe `mkdocs.yml`).
