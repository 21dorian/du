# Entrevo Caisse — application native (Android, iOS)

L'application de caisse des bars existe en deux versions construites à partir des **mêmes fichiers** (`entrevo/caisse/`) :

| | Web app (`https://ta-billetterie/caisse/`) | Application native (ce dossier) |
|---|---|---|
| Installation | « Ajouter à l'écran d'accueil » depuis le navigateur | Fichier APK / Google Play, App Store |
| Cashless par QR du billet | oui | oui |
| Bracelets NFC | Chrome Android uniquement (Web NFC) | **Android** (module natif `EntrevoNfc`) |
| Carte : terminal autonome (confirmation manuelle) | oui | oui |
| Carte : **terminal Nepting piloté** (Caisse-AP) | non (un navigateur ne peut pas ouvrir de connexion TCP) | **Android** (module natif `EntrevoTpe`) |
| Espèces, mode hors ligne | oui | oui |

## Construire l'application Android

Prérequis : Node.js 18+, JDK 21, Android Studio (ou le SDK Android avec `ANDROID_HOME` / `local.properties`).

```bash
cd entrevo-caisse-native
npm install
npx cap add android        # la première fois seulement
npm run android            # copie l'appli web, synchronise, ajoute les modules NFC + Nepting et les autorisations
cd android && ./gradlew assembleDebug    # APK de test : android/app/build/outputs/apk/debug/app-debug.apk
```

Pour publier : `npx cap open android`, puis dans Android Studio **Build › Generate Signed Bundle / APK** (clé de signature à garder précieusement) et envoi sur la Google Play Console (compte développeur : 25 $ une fois). L'APK peut aussi être installé directement sur les tablettes des bars (« sources inconnues »).

## iOS

```bash
npx cap add ios && npx cap sync ios && npx cap open ios
```

À construire sur un Mac avec Xcode et un compte Apple Developer (99 $/an). La version iOS fonctionne avec le cashless par QR, la carte en confirmation manuelle et les espèces ; les modules NFC et Nepting sont propres à Android (les iPhone ne lisent pas l'identifiant des bracelets sans autorisation spéciale d'Apple, et les terminaux Nepting sont des appareils Android).

## Appairage

À la première ouverture, l'application demande l'adresse de la billetterie et un code à 6 chiffres. Le plus simple : **scanner le QR code** affiché dans *Événement › Jour J › Cashless & bars › Application Entrevo Caisse* ; il contient l'adresse et le code.

## Terminal Nepting (protocole Caisse-AP)

Dans les réglages de l'appareil (site, onglet Cashless & bars) : mode « Terminal Nepting piloté par l'appli », adresse IP du terminal (fixe, réservée dans la box ou le routeur du festival), port (8888 par défaut), identifiant et numéro de caisse, version du protocole (0320 pour Caisse-AP 3.2).

Sur le terminal : activer la **connexion caisse** en TCP/IP (menu de l'application Nepting ou demande à ton prestataire monétique). La tablette du bar et le terminal doivent être sur **le même réseau Wi-Fi**. Si l'application caisse tourne directement sur le terminal Android Nepting, l'adresse est `127.0.0.1`.

Déroulé d'un paiement : l'appli envoie le montant au terminal ; le client présente sa carte ; le terminal répond (accepté / refusé, numéro d'autorisation) ; la vente est enregistrée avec la référence du paiement. En cas de souci réseau, le bouton « Encaisser sans le terminal connecté » permet de continuer en saisie manuelle.

**À valider avant un événement :** le format de trame suit la documentation publique de la connexion caisse Nepting (balises CZ, CJ, CA, CB, CD, CE, CF ; réponse AE, AF, AC, CC). Fais un essai réel avec ton terminal et ton prestataire (montant faible, puis remboursement) : la version du protocole et le caractère de fin de trame sont réglables sans recompiler l'application.
