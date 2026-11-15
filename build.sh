#!/usr/bin/env bash
# Erzeugt dist/module_hausverwaltung-<version>.zip für "Externes Modul bereitstellen"
set -euo pipefail
cd "$(dirname "$0")"
VER=$(grep -oP "this->version = '\K[^']+" hausverwaltung/core/modules/modHausverwaltung.class.php)
mkdir -p dist
rm -f "dist/module_hausverwaltung-$VER.zip"
zip -qr "dist/module_hausverwaltung-$VER.zip" hausverwaltung -x '*.DS_Store'
echo "dist/module_hausverwaltung-$VER.zip"
