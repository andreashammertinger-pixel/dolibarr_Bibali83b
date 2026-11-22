#!/usr/bin/env bash
# Erzeugt dist/module_<modul>-<version>.zip für "Externes Modul bereitstellen"
#   ./build.sh                 → hausverwaltung (wie bisher)
#   ./build.sh kuechenangebot  → Küchenangebot (migora)
#   ./build.sh all             → beide
set -euo pipefail
cd "$(dirname "$0")"
build() {
	local mod="$1" cls
	cls=$(ls "$mod"/core/modules/mod*.class.php | head -1)
	local ver
	ver=$(grep -oP "this->version = '\K[^']+" "$cls")
	mkdir -p dist
	rm -f "dist/module_$mod-$ver.zip"
	zip -qr "dist/module_$mod-$ver.zip" "$mod" -x '*.DS_Store'
	echo "dist/module_$mod-$ver.zip"
}
case "${1:-hausverwaltung}" in
	all) build hausverwaltung; build kuechenangebot ;;
	*) build "${1:-hausverwaltung}" ;;
esac
