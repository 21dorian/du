# Entrevo — billetterie & contrôle d'accès 🎟️

Plateforme de billetterie multi-organisateurs complète :

- **Vente** : événements simples ou à dates multiples (séances, récurrences, jauge par date), tarifs fixes ou à prix libre, packs (ex. famille 4 places), pass multi-entrées ou « 1 entrée par jour », tarifs secrets par code d'accès, codes promo, options payantes (merch, parking…), dons, placement numéroté sur plan de salle, événements privés par mot de passe, publication programmée, file d'attente virtuelle pour les grosses ouvertures de vente, liste d'attente quand c'est complet, bourse aux billets officielle (revente au prix d'origine), demandes de remboursement.
- **Paiement** : Stripe, PayPal, SumUp, Mollie (avec notifications automatiques), guichet (espèces, TPE…), billets gratuits.
- **Organisateurs** : statistiques détaillées (jour, heure, canal, provenance UTM, codes promo, options, dates), participants et exports, guichet avec ticket thermique, journal et clôture de caisse, invitations en masse, liens partenaires avec lots de places, accréditations avec badges et zones, import de billets vendus ailleurs, messages aux participants, widget à intégrer à son site, suivi Google Analytics / Tag Manager / Pixel Meta, API et webhooks, billets et e-mails personnalisés, reçus PDF, relevés mensuels de commissions.
- **Contrôle d'accès** : application sur téléphone (caméra, douchette, recherche par nom), hors ligne, plusieurs portes et zones, dates, sorties et ré-entrées, liste de contrôle papier de secours.
- **Plateforme** : commission par défaut ou personnalisée par organisateur, reversements, mises à jour en un clic avec retour arrière.

---

## 1. Installer chez Dyjix (cPanel) — 10 minutes

**Ce qu'il faut :** un hébergement Dyjix avec cPanel, PHP 8.1 ou plus, une base MySQL, et un nom de domaine (ou sous-domaine) avec un certificat SSL (gratuit, « Let's Encrypt » / « AutoSSL » dans cPanel).

1. **Choisis la version de PHP**
   cPanel › « Sélectionner une version de PHP » › **8.2** (ou plus). Vérifie que les extensions `pdo_mysql`, `curl`, `zip`, `mbstring`, `iconv` et `gd` sont cochées.

2. **Crée la base de données**
   cPanel › « Bases de données MySQL » :
   - crée une base (ex. `moncompte_entrevo`) ;
   - crée un utilisateur avec un mot de passe solide ;
   - ajoute l'utilisateur à la base avec **tous les privilèges**.
   Note les trois informations (base, utilisateur, mot de passe).

3. **Envoie les fichiers**
   cPanel › « Gestionnaire de fichiers » › va dans `public_html` (ou le dossier de ton sous-domaine) › **Charger** le fichier `entrevo-v1.0.0.zip` › clic droit › **Extraire**.
   Tu obtiens un dossier `entrevo/`. Deux possibilités :
   - billetterie à la racine du site : déplace **le contenu** de `entrevo/` dans `public_html/` (fichiers cachés compris, notamment `.htaccess` : active « Afficher les fichiers cachés » dans les paramètres du gestionnaire) ;
   - billetterie dans un sous-dossier : laisse `entrevo/`, l'adresse sera `https://tondomaine.fr/entrevo`.

4. **Lance l'installation**
   Ouvre `https://tondomaine.fr/install.php` (ou `/entrevo/install.php`).
   L'assistant vérifie l'hébergement, puis demande : la base de données (serveur : `localhost`), le nom de la plateforme, et ton compte super-administrateur.
   Coche « données de démonstration » si tu veux tester avec de faux événements.

5. **C'est en ligne 🎉**
   Connecte-toi sur `/connexion`. Le fichier d'installation se désactive tout seul.

6. **Force le HTTPS** (indispensable pour la caméra du scanner et les paiements)
   Une fois le certificat SSL actif, ouvre le fichier `.htaccess` et retire le `#` devant les deux lignes `RewriteCond %{HTTPS} off` et `RewriteRule ^ https://...`.

### Comptes de démonstration (si tu as coché la démo)
| Rôle | E-mail | Mot de passe |
|---|---|---|
| Organisateur | `orga@demo.entrevo` | `demo1234` |
| Contrôleur (scan) | `controle@demo.entrevo` | `demo1234` |

Supprime-les (ou désactive-les dans Super-admin › Utilisateurs) avant d'ouvrir au public.

---

## 2. Réglages à faire avant de vendre (Super-admin › Réglages)

- **Général** : nom, adresse du site (`https://…`), e-mail de contact, couleur.
- **Commission** : pourcentage + montant fixe par billet (par défaut 3 % + 0,50 €). Chaque organisateur choisit qui paie les frais ; tu peux faire un tarif perso par organisateur.
- **Paiements** : colle les clés de Stripe / PayPal / SumUp / Mollie et coche « Activer ». **Coupe le paiement de démonstration** dès que tu vends pour de vrai. Fais toujours un achat test avec les clés de test du prestataire.
- **E-mails** : crée une adresse dans cPanel › « Comptes de messagerie » (ex. `billets@tondomaine.fr`) et renseigne serveur, port 465 (SSL) ou 587 (STARTTLS), identifiant et mot de passe. Clique « Enregistrer et tester ».
- **Textes légaux** : CGV (l'acheteur doit les accepter) et mentions légales.

- **Notifications de paiement (webhooks)** : dans Réglages › Paiements, copie les adresses affichées dans les tableaux de bord Stripe et PayPal (SumUp et Mollie : automatique). Sans elles, une commande payée reste en attente quelques minutes si l'acheteur ferme son navigateur avant de revenir sur le site (elle est revérifiée automatiquement ensuite).
- **Tâche planifiée** : dans cPanel › « Tâches Cron », ajoute la commande affichée dans Réglages › Général, toutes les 5 minutes. Elle fait tourner la liste d'attente, les publications programmées et la revérification des paiements même quand personne ne visite le site.

**L'argent :** tous les paiements arrivent sur tes comptes. Dans Super-admin › Organisateurs › fiche, tu vois ce que tu dois reverser à chacun ; fais le virement depuis ta banque puis clique « Enregistrer le reversement ».

---

## 3. Le jour J : contrôle des entrées 📷

1. L'organisateur crée des comptes **Contrôleur** (Espace organisateur › Équipe).
2. Sur chaque téléphone : ouvrir `https://tondomaine.fr/scan`, se connecter, choisir l'événement, la porte et le sens (Entrée / Sortie).
3. Astuce : « Ajouter à l'écran d'accueil » → l'appli s'ouvre comme une vraie application.
4. **Hors ligne** : la liste des billets est téléchargée sur le téléphone. Sans réseau, le scan continue ; les passages sont envoyés automatiquement au retour du réseau (le petit compteur ⏳ en haut à droite).
5. Douchette USB / Bluetooth : branche-la, scanne, ça marche (pas besoin de cliquer dans un champ).
6. Suivi en direct pour l'organisateur : Événement › « En direct ».

Couleurs : **vert** = entre, **orange** = déjà entré / pas entré (sortie), **rouge** = refusé (inconnu, annulé, mauvais événement, mauvaise porte).

---

## Nouveautés 1.6 ✨

- **Page vitrine « Pour les organisateurs »** (`/organisateurs`, reliée au bouton « Organisateurs » et au pied de page) : promesse, étapes, fonctions incluses, fonctions à venir, tarif avec **simulateur**, questions fréquentes, appel à l'inscription.
- **Fonctions « Bientôt disponible » côté acheteurs**, chacune avec sa page de présentation et un formulaire « Préviens-moi » (e-mail facultatif) :
  - accueil : ce soir / ce week-end / gratuit, carte « autour de moi », recommandations ;
  - page événement : favoris, agenda, inviter des amis, covoiturage, payer à plusieurs, Apple Pay / Google Pay ;
  - confirmation de commande : billet dans le Wallet, agenda, donner son billet, changer de date, covoiturage ;
  - page organisateur : suivre l'organisateur, avis des participants ;
  - « Mes billets » : compte acheteur ;
  - pied de page : centre d'aide, cartes cadeaux, appli mobile, English.
- Super-admin › **Feuille de route** : nouveau tableau « Attentes du public » (nombre d'intéressés et d'e-mails à prévenir par fonction).

## Nouveautés 1.5 ✨

**Argent & conformité**
- **Vérification du compte (KYC)** : l'organisateur dépose sa pièce d'identité, son Kbis/statuts et son RIB (stockés en privé) ; le super-admin valide ou refuse chaque pièce (Super-admin › Vérification KYC). Option : bloquer les reversements tant que ce n'est pas vérifié (Réglages › Commissions).
- **Factures de commission** : une facture PDF numérotée par organisateur et par mois (HT, TVA, TTC), envoyée par e-mail, retrouvable dans Finances. Génération manuelle ou automatique le 1er du mois. Renseigne tes informations d'émetteur dans Réglages › Commissions.
- **TVA par tarif** (et taux par défaut de l'organisation) : détail HT / TVA / TTC sur les reçus, récap mensuel de TVA collectée dans Finances.
- **Export comptable** : ventes détaillées (CSV) et écritures au format **FEC**.

**Organisation d'événement**
- **Budget prévisionnel** par événement : dépenses et recettes prévues / réelles, billetterie reliée automatiquement, **seuil de rentabilité** en nombre de billets, export CSV, vue d'ensemble de tous les événements.
- **Tâches & rétroplanning** : tableau à colonnes (glisser-déposer) ou liste, responsable et échéance, modèles prêts à l'emploi (concert, festival, conférence) calés sur la date de l'événement. Tâches en retard dans « À traiter ».
- **Documents & contrats** : contrats artistes, assurances, autorisations… par événement, stockés en privé, avec date d'échéance et rappel 30 jours avant.

**Le jour J**
- **Bénévoles & staff** : annuaire, créneaux par poste avec frise horaire, affectations (avec détection des chevauchements), formulaire de candidature public, **espace bénévole** personnel (confirmer, se désister, s'inscrire), **badge QR**, rappel la veille, **pointage** arrivée/départ (douchette ou bouton), export des heures.
- **PC sécurité** : écran sombre plein écran mis à jour toutes les 5 secondes : présents / jauge, **jauge par zone** (à définir sur chaque porte), rythme d'entrée et temps estimé, refus récents, téléphones de contrôle, messages aux contrôleurs. **Alerte automatique** envoyée aux téléphones quand une zone atteint 90 % puis 100 %.

## Nouveautés 1.4.2 ✨

- **Plus de 50 nouvelles fonctionnalités annoncées « en développement »** directement dans les pages, sous forme de boutons et d'options pointillés avec le badge « dév ». Chacune ouvre sa page de présentation (et, côté organisateur, le vote).
- Organisateurs : options avancées de l'événement (préventes privées, contrôle d'identité, âge minimum, places PMR, transfert et échange de billets, assurance annulation, rappel J-1, pages en plusieurs langues), options de tarif (early bird, prix dynamique, dégressif, paiement en 3 fois, TVA), actions sur une commande (transférer, changer de date, facture pro, SMS), partage (visuels réseaux sociaux, stats partagées, rapport de fin), finances (paiement direct, export comptable, budget), équipe (rôles personnalisés, double authentification), connexions (Google Sheets, Mailchimp/Brevo, Zapier), envois automatiques (paniers abandonnés, codes anniversaire, satisfaction, parrainage), PC sécurité (jauge par zone, badges, vestiaire, borne).
- Super-admin : réglages (maintenance, connexion Google/Apple, langues, marque blanche), commissions par palier et formules d'abonnement, litiges et fraude, revendeurs, modèles d'e-mails et délivrabilité, KYC et contrats sur la fiche organisateur, RGPD, alertes et sauvegardes.

## Nouveautés 1.4.1 ✨

- **Menus réorganisés** en rubriques repliables (la position est mémorisée), pour les organisateurs comme pour le super-admin.
- **Nouvelles rubriques « en développement »** (badge « dév ») : chacune a sa page de présentation. Organisateurs : boutique, abonnements, cartes cadeaux, groupes & devis, SMS, mini-site, réseaux sociaux, ambassadeurs, avis, PC sécurité, bénévoles, artistes & planning, cashless, objets trouvés, factures, budget prévisionnel, bilans & subventions, rétroplanning, documents, intégrations (les organisateurs peuvent voter). Super-admin : statistiques, vérification KYC, contrats & tarifs, prospection, litiges, factures de commission, fraude, support, accueil & contenus, modèles d'e-mails, marque blanche, langues, sauvegardes automatiques (marquables « prioritaire »).
- Case « Masquer les modules en développement » en bas du menu.
- Super-admin › **Feuille de route** : les votes des organisateurs et tes chantiers prioritaires.

## Nouveautés 1.4 ✨

**Organisateurs**
- **Campagnes e-mail** (menu « Campagnes ») : modèles prêts à l'emploi, éditeur à blocs (titre, texte, événement avec affiche et bouton, bouton, image, séparateur) avec aperçu ordinateur/mobile, audience (inscrits aux actus ou tous les clients, segments, « venus à », « exclure ceux qui ont déjà un billet »), e-mail de test, envoi immédiat ou programmé. Statistiques : ouvertures, clics, désinscriptions et **ventes générées**.
- Au paiement, l'acheteur peut cocher « recevoir les prochains événements ». Chaque e-mail a un lien de désinscription en un clic.
- **Liste des événements** en cartes (ou en liste) : onglets À venir / En vente / Brouillons / Passés, recherche, tri, remplissage, ventes des 7 derniers jours, actions rapides (lien, guichet, stats, dupliquer).
- **Récap par e-mail** chaque matin ou chaque lundi (Mon organisation › Notifications) et **alertes de remplissage** à 50 %, 80 % et complet.
- **Consigne de contrôle et couleur de bracelet par tarif** (onglet Tarifs) : s'affichent en grand sur l'appli de scan.
- Objectif de recettes visible sur la vue de l'événement.

**Contrôleurs (appli de scan)**
- Historique des derniers passages du téléphone, avec annulation.
- Entrée / sortie en touchant la pastille sur la caméra.
- Écran qui reste allumé pendant le scan.
- Bandeau « consigne » et couleur de bracelet selon le tarif.
- **Messages du PC** : l'organisateur écrit depuis « En direct », tous les téléphones sonnent et affichent le message.
- Stats en direct par porte et par heure. Côté organisateur, liste des téléphones connectés (porte, scans, batterie, passages en attente).

**Super-admin**
- Nouvelle **vue d'ensemble** par période : commissions, volume, billets, organisateurs actifs, graphique, liste « À faire ».
- **Reversements** : tous les soldes dus, contrôle des IBAN, montant ajustable, **fichier de virements groupés SEPA** à importer dans ta banque, lots annulables.
- **Validation des nouveaux organisateurs** (option dans Réglages › Général) : ils préparent leurs événements mais ne publient qu'après ton feu vert.
- **Santé** : PHP, extensions, base, disque, HTTPS, cron, e-mails envoyés, dernières erreurs, notifications de paiement.
- **Annonce** affichée en haut de l'espace de tous les organisateurs.
- Note interne sur chaque fiche organisateur.

⚠️ **La tâche planifiée (cron) devient indispensable** : elle envoie les campagnes par lots, les récaps et les alertes. Vérifie-la dans Super-admin › Santé.

## Nouveautés 1.3 ✨

- **Menu de gestion réorganisé** par grandes rubriques (Faire venir du monde, Le jour J, Gestion) avec les modules à venir signalés « Bientôt ».
- **Tous les modules** : le catalogue de ce qui est inclus et de ce qui arrive (34 modules). Chaque module « Bientôt » a sa page de présentation et un bouton « Ça m'intéresse » : les votes remontent au super-admin (menu **Modules & votes**) pour choisir quoi développer en premier.
- **Participants** : la base de tous tes acheteurs, un contact par e-mail avec son historique (événements, commandes, billets, présence, montant dépensé), segments (fidèles, 100 € et plus, récents, dormants) et export CSV.
- **Tableau de bord** : actions rapides, bandeau « Aujourd'hui » comparé à hier, liste « À traiter », carte « Quand ça achète » (jour × heure), sources des ventes, objectifs de recettes, aperçu des modules à venir et « Quoi de neuf ».
- **Objectif de recettes** par événement (Infos & réglages › Jauge & règles).
- La recherche Ctrl+K trouve aussi les modules.

## Nouveautés 1.2 ✨

- **Nouveau look** clair et coloré (bleu électrique, cartes arrondies, polices Host Grotesk et Space Mono).
- **Tableau de bord** : recettes, billets, conversion avec tendances, graphiques interactifs, activité en direct, check-list « Bien démarrer ».
- **Vue événement** : compte à rebours, remplissage, ventes par tarif et par canal, entonnoir visiteurs → paniers → paiements, préparation de l'événement, QR code de partage.
- **Recherche partout** (Ctrl+K) et **cloche de notifications**.
- **Commandes** : filtres (statut, vente, étiquette, dates), tri par colonne, pagination, sélection multiple avec actions groupées (étiqueter, renvoyer les billets, exporter, annuler).
- **Fiche commande** : remboursement partiel (montant libre et/ou billets choisis), acheteur modifiable, étiquettes, note interne, historique complet.
- **Journal d'activité** : qui a fait quoi et quand (menu « Journal d'activité », réservé au responsable).
- **Côté acheteur** : rail « Ça arrive bientôt » sur l'accueil, compte à rebours, alerte « plus que N places », barre d'achat collante sur mobile, apparitions en douceur, confettis à la confirmation 🎉 (désactivés si l'appareil demande moins d'animations).

---

## 4. Mises à jour ⬆️

Quand je t'envoie un fichier `maj-X.Y.Z.zip` :
1. Super-admin › **Mises à jour** ;
2. dépose le fichier dans la zone, clique **Installer** ;
3. le site vérifie tout seul qu'il fonctionne. En cas de souci, un lien « Revenir à la version précédente » apparaît (et la liste « Revenir en arrière » reste disponible).

Tes données (base, billets, images, `config.php`) ne sont jamais modifiées par une mise à jour. Installe les mises à jour **dans l'ordre** (1.1.0 avant 1.2.0…). Pense à télécharger une sauvegarde des données avant (bouton sur la même page).

---

## 5. Sauvegardes

- Super-admin › Mises à jour › « Télécharger la sauvegarde » : toute la base en un fichier `.sql`.
- cPanel propose aussi des sauvegardes complètes (« Sauvegarde » / JetBackup chez Dyjix).
- Les fichiers importants : `config.php` (connexion à la base, clé secrète des QR codes — **ne le perds pas**, sinon les billets déjà émis ne seraient plus reconnus comme authentiques) et le dossier `uploads/` (affiches).

---

## 6. En cas de souci

- **Page blanche / erreur** : regarde `storage/logs/erreurs.log` (gestionnaire de fichiers).
- **Les e-mails n'arrivent pas** : configure le SMTP (section 2) et teste ; regarde les spams ; journal : `storage/logs/emails.log`.
- **La caméra ne s'ouvre pas sur le téléphone** : le site doit être en `https://` ; autorise la caméra dans le navigateur.
- **« Fichier trop lourd » en mise à jour** : augmente `upload_max_filesize` dans cPanel › « Sélectionner une version de PHP » › Options.
- **Mot de passe super-admin perdu** : « Mot de passe oublié » sur la page de connexion (nécessite des e-mails fonctionnels).

---

Bibliothèques incluses (licences libres) : FPDF, PHPMailer (LGPL 2.1), chillerlan/php-qrcode (MIT/Apache 2.0), jsQR (Apache 2.0), police Barlow (SIL OFL).
