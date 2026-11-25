#!/bin/sh
# Prépare le projet Android d'Entrevo Caisse et fabrique un APK de test (nécessite Node.js, JDK 21 et le SDK Android).
set -e
cd "$(dirname "$0")/../entrevo-caisse-native"
npm install --no-audit --no-fund
[ -d android ] || npx cap add android
npm run android
cd android && ./gradlew assembleDebug
echo "APK : entrevo-caisse-native/android/app/build/outputs/apk/debug/app-debug.apk"
