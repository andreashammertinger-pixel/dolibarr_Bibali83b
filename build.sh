#!/usr/bin/env bash
# Erzeugt dist/module_<modul>-<version>.zip für "Externes Modul bereitstellen"
# Aufruf: ./build.sh              → alle Module (hausverwaltung, ideenbox)
#         ./build.sh ideenbox     → nur ein Modul
#         ./build.sh apk          → zusätzlich Android-App (benötigt Android-SDK)
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p dist
MODS=("$@"); [ ${#MODS[@]} -eq 0 ] && MODS=(hausverwaltung ideenbox)
for M in "${MODS[@]}"; do
  if [ "$M" = "apk" ]; then
    (cd app-ideenbox && ./gradlew -q assembleRelease)
    VER=$(grep -oP 'versionName = "\K[^"]+' app-ideenbox/app/build.gradle.kts)
    cp app-ideenbox/app/build/outputs/apk/release/app-release.apk "dist/ideenbox-$VER.apk"
    echo "dist/ideenbox-$VER.apk"
    continue
  fi
  DESC=$(ls "$M"/core/modules/mod*.class.php)
  VER=$(grep -oP "this->version = '\K[^']+" "$DESC")
  rm -f "dist/module_$M-$VER.zip"
  zip -qr "dist/module_$M-$VER.zip" "$M" -x '*.DS_Store'
  echo "dist/module_$M-$VER.zip"
done
