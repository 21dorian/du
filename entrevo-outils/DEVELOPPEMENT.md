# Entrevo — notes de développement

Application PHP 8.1+ / MySQL sans framework ni Composer (bibliothèques copiées dans `entrevo/vendor/`), pensée pour un hébergement mutualisé (Dyjix, cPanel). L'utilisateur reçoit un ZIP complet, puis des paquets de mise à jour qu'il dépose dans Super-admin › Mises à jour.

## Arborescence
- `entrevo/index.php` : contrôleur frontal ; routes dans `app/routes.php` (`fichier@fonction` → `app/controllers/fichier.php`).
- `app/lib/` : `Tickets` (métier : quotas, commandes, commissions, scans), `Payments` (Stripe/PayPal/SumUp/Mollie/démo), `Mailer`, `TicketPdf`, `Qr`, `Updater`, `Demo`.
- `app/migrations.php` : évolutions de la base, appliquées automatiquement au premier chargement après une mise à jour. **Toujours ajouter une nouvelle entrée numérotée, ne jamais modifier une ancienne.**
- `app/views/` : gabarits PHP (`layout_public`, `layout_back`, `scan/app` appli mobile).
- `assets/` : CSS, JS (`scan.js` = appli de contrôle hors ligne), polices, images. `sw.js` à la racine = service worker du scanner.
- `maj-secours.php` : retour arrière autonome (protégé : jamais modifié par une mise à jour).

## Ton & design
- Depuis 1.2 : thème clair inspiré de Weezevent + Shotgun. Bleu `#0032fa` (principal), lavande `#b388ff` et turquoise `#2dd2be` en accents, fond `#f0f5fa`, encre `#0f0f14`. Polices : Host Grotesk (texte, boutons), Barlow Condensed (grands titres), Space Mono (étiquettes). Cartes et boutons arrondis (10–24 px), quelques blocs sombres pour le relief. La couche « THÈME 1.2 » est à la fin de `assets/css/app.css`.
- Appli de scan : reste sombre (`body.scan-app`).
- Graphiques : `assets/js/charts.js` (bleu `#0032fa` / gris `#7d8699`), règles dataviz en tête du fichier.
- Textes : tutoiement, ton décalé et drôle (« façon Burger King »), émojis bienvenus, mais toujours clairs sur prix, paiements, erreurs.

## Tester en local
```
mysql -e "CREATE DATABASE entrevo CHARACTER SET utf8mb4; CREATE USER 'entrevo'@'localhost' IDENTIFIED BY 'entrevo'; GRANT ALL ON entrevo.* TO 'entrevo'@'localhost';"
php -S 127.0.0.1:8080 entrevo-outils/routeur-dev.php
```
Puis ouvrir http://127.0.0.1:8080/install.php.

## Livrer
1. Mise à jour : `php entrevo-outils/creer-maj.php X.Y.Z <tag-précédent> "Notes lisibles par l'utilisateur"` → `livraisons/maj-X.Y.Z.zip` (met aussi à jour `entrevo/VERSION`). Commiter, taguer `vX.Y.Z`.
2. ZIP complet (nouvelle installation) : `sh entrevo-outils/creer-livraison.sh` → `livraisons/entrevo-vX.Y.Z.zip`.
3. Une modification de base de données = nouvelle entrée dans `app/migrations.php`.
4. Fichiers jamais inclus dans un paquet : `config.php`, `storage/`, `uploads/`, `maj-secours.php`.

## Versions livrées
| Version | Commit de référence | Fichier |
|---|---|---|
| 1.0.0 | `2558ac0` | `livraisons/entrevo-v1.0.0.zip` |
| 1.1.0 | `bbba103` | `livraisons/entrevo-v1.1.0.zip`, `livraisons/maj-1.1.0.zip` |
| 1.2.0 | `3a66c6b` | `livraisons/entrevo-v1.2.0.zip`, `livraisons/maj-1.2.0.zip` |
| 1.3.0 | `200667c` | `livraisons/entrevo-v1.3.0.zip`, `livraisons/maj-1.3.0.zip` |
| 1.4.0 | `b9f7f7f` | `livraisons/entrevo-v1.4.0.zip`, `livraisons/maj-1.4.0.zip` |
| 1.4.1 | `f931d7d` | `livraisons/entrevo-v1.4.1.zip`, `livraisons/maj-1.4.1.zip` |
| 1.4.2 | `1479a61` | `livraisons/entrevo-v1.4.2.zip`, `livraisons/maj-1.4.2.zip` |
| 1.5.0 | `fe53f0b` | `livraisons/entrevo-v1.5.0.zip`, `livraisons/maj-1.5.0.zip` |
| 1.6.0 | `426819a` | `livraisons/entrevo-v1.6.0.zip`, `livraisons/maj-1.6.0.zip` |
| 1.7.0 | `04feaac` | `livraisons/entrevo-v1.7.0.zip`, `livraisons/maj-1.7.0.zip` |
| 1.8.0 | `3a24da7` | `livraisons/entrevo-v1.8.0.zip`, `livraisons/maj-1.8.0.zip` |
| 1.9.0 | `dd5699d` | `livraisons/entrevo-v1.9.0.zip`, `livraisons/maj-1.9.0.zip` |
| 2.0.0 | `1a78196` | `livraisons/entrevo-v2.0.0.zip`, `livraisons/maj-2.0.0.zip` |
| 2.1.0 | `63fbfca` | `livraisons/entrevo-v2.1.0.zip`, `livraisons/maj-2.1.0.zip` |
| 2.2.0 | `000ce70` | `livraisons/entrevo-v2.2.0.zip`, `livraisons/maj-2.2.0.zip` |
| 2.3.0 | `1789276` | `livraisons/entrevo-v2.3.0.zip`, `livraisons/maj-2.3.0.zip` |

Pour un paquet de mise à jour, passer ce commit comme référence de départ à `creer-maj.php` (les étiquettes git ne sont pas poussées).

## (historique) Préparé puis activé en 1.5
- Staff & bénévoles (`app/lib/Staff.php`, `app/controllers/staff.php`, `app/views/pro/staff/`) : tables créées par la migration 5, routes retirées. Il reste la vue planning d'événement, le pointage et le portail public (`staff_public`). Réactiver les routes dans `app/routes.php` une fois terminé.
