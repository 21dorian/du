# Entrevo Pro (Android)

L'application des organisateurs et des équipes de contrôle. Au premier lancement, on saisit l'adresse de la billetterie
puis on choisit :

- **Organisateur** : ventes en direct, remplissage par tarif, dernières commandes, alertes, vente rapide au guichet,
  message express aux participants, ouverture ou clôture des ventes ;
- **Contrôle des entrées** : scan des QR codes, **lecteur NFC du téléphone** pour les bracelets, liste des invités,
  jauge et file d'attente, messages du PC sécurité.

Ce sont les mêmes écrans que la version web (`/pro/app` et `/scan`), avec en plus :

- le **lecteur NFC** du téléphone (bracelets MIFARE, NTAG…), transmis à l'appli de contrôle ;
- les **notifications Android** des ventes et alertes pendant que l'appli est ouverte ou en arrière-plan récent.

Pour les notifications quand l'appli est complètement fermée, utilise la version web installée (Chrome Android, ou
iPhone avec l'appli ajoutée à l'écran d'accueil) : elle reçoit les notifications Web Push du site. L'envoi de
notifications à une appli Android fermée demande un compte Firebase à ton nom ; ce n'est pas branché.

Seule la billetterie saisie au premier lancement peut utiliser le NFC et les notifications. Pour en changer :
menu « Plus » (organisateur) ou menu du scan › « Changer d'appli ou de site ».

## Construire l'APK

Prérequis : Node 18+, JDK 21, SDK Android (variable `ANDROID_HOME`).

```
npm install
node scripts/prepare-www.js
npx cap add android        # la première fois seulement
npx cap sync android
node scripts/patch-android.js
cd android && ./gradlew assembleDebug
```

L'APK de test est dans `android/app/build/outputs/apk/debug/app-debug.apk`. Pour le Play Store, il faut une version
signée (`./gradlew bundleRelease` avec ta clé) et un compte développeur Google à ton nom.
