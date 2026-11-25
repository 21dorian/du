#!/bin/sh
# Fabrique le ZIP complet d'Entrevo (première installation) : livraisons/entrevo-vX.Y.Z.zip
set -e
cd "$(dirname "$0")/.."
REPO=$(pwd)
V=$(cat entrevo/VERSION | tr -d '[:space:]')
mkdir -p livraisons
OUT="livraisons/entrevo-v$V.zip"
rm -f "$OUT"
TMP=$(mktemp -d)
cp -r entrevo "$TMP/entrevo"
cd "$TMP/entrevo"
rm -f config.php storage/logs/*.log storage/logs/.throttle-* storage/sessions/sess_* storage/maj/*.zip storage/maj/secours.json
rm -rf uploads/demo uploads/20* storage/sauvegardes storage/contrats
cd "$TMP"
zip -qr -X "$REPO/$OUT" entrevo
cd "$REPO"
rm -rf "$TMP"
echo "Livraison créée : $OUT ($(du -h "$OUT" | cut -f1))"
