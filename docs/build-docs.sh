#!/usr/bin/env bash
# Baut die Hilfe-Website: site/index.html (Einstieg), site/verwaltung/ (Handbuch, de/hr), site/eigentuemer/ (Eigentümer, hr/de/en/sl/uk)
# Voraussetzung: pip install mkdocs-material mkdocs-static-i18n
set -euo pipefail
cd "$(dirname "$0")"
MKDOCS="${MKDOCS:-mkdocs}"
OUT="$(pwd)/site"
TMP="$(pwd)/.build"
rm -rf "$OUT" "$TMP"
mkdir -p "$OUT"
for SITE in verwaltung eigentuemer; do
  mkdir -p "$TMP/$SITE"
  cp -r "$SITE/." "$TMP/$SITE/"
  DEF=$(awk '/- locale:/{loc=$3} /^ +default: true/{print loc; exit}' "$SITE/mkdocs.yml")
  # gemeinsame Dateien: Design (im Standard-Sprachordner, gilt für alle Sprachen) und News je Sprache
  mkdir -p "$TMP/$SITE/docs/$DEF/assets"
  cp -r shared/assets/. "$TMP/$SITE/docs/$DEF/assets/"
  for L in $(ls "$SITE/docs"); do
    if [ -d "shared/news/$L" ]; then
      mkdir -p "$TMP/$SITE/docs/$L/news"
      cp -r "shared/news/$L/." "$TMP/$SITE/docs/$L/news/"
    fi
  done
  "$MKDOCS" build --strict -q -f "$TMP/$SITE/mkdocs.yml" -d "$OUT/$SITE"
done
cp -r landing/. "$OUT/"
rm -rf "$TMP"
echo "Fertig: $OUT"
