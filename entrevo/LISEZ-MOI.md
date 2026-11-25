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

## Nouveautés 2.7 — Entrevo Caisse Pro, la caisse événementielle complète

**Espace de gestion « Bars & caisses »** (Événement › Jour J), avec 11 pages :
- **Tableau de bord en direct** : chiffre d'affaires, ventes par moyen de paiement, par heure, par point de vente, meilleures ventes, caisses ouvertes avec espèces en tiroir (alerte « caisse pleine »), alertes de stock, gobelets en circulation. Mise à jour automatique.
- **Catalogue** : catégories avec couleur et TVA, produits avec **photo**, description, **variantes** (25 cl / 50 cl…), **prix différents par bar**, consigne, stock suivi, couleur de touche ; ordre par glisser-déposer ; duplication ; import de la carte d'un autre événement.
- **Consignes** : plusieurs types (gobelet, pichet…), montant, remboursable ou souvenir, stock de départ ; suivi des gobelets sortis, rendus, en circulation, par bar.
- **Équipe** : serveurs et responsables avec **code personnel**, droits sur mesure (offrir, remise, annulation, consignes, espèces, clôture), création en série avec codes à imprimer, ventes et offerts par personne.
- **Happy hours & remises** : remise en %, en €, prix fixe ou 2e article offert, par catégorie ou produit, par bar, jours et horaires (même après minuit), dates ; appliqués automatiquement par les caisses ; journal des offerts et remises accordés.
- **Crédits offerts** : boissons ou repas offerts à l'équipe, aux artistes et aux invités, en consommations ou en euros par jour, avec plafond et catégories ; **badges PDF à QR code** ; création en un clic pour les bénévoles confirmés.
- **Caisses & clôtures** : fond de caisse, apports, remises en banque, clôture **Z** avec espèces comptées et écart, ticket X/Z en PDF, clôture à distance.
- **Stocks** : stock par produit et par bar, entrées, transferts réserve → bar, inventaire avec écart, alertes, export CSV.
- **Ventes** : journal filtrable (bar, serveur, moyen de paiement, jour, produit), offerts et remises, export CSV.
- **Appareils** et **Réglages** : moyens de paiement, connexion obligatoire des serveurs, sessions de caisse, TVA par défaut, motifs d'offert, photos et grandes touches, ticket de caisse.

**Application Entrevo Caisse**
- Connexion du serveur par **code PIN**, ouverture de caisse avec fond, verrouillage et changement de serveur.
- Touches avec **photos**, couleurs de catégorie, **variantes**, prix du bar, happy hour affiché, stock restant.
- **Offrir** un article ou tout le panier avec un motif, **remise** sur le panier : validation par le code d'un responsable si le serveur n'en a pas le droit.
- **Consignes** ajoutées automatiquement, **retour de consigne** remboursé en espèces ou sur le cashless.
- Paiements : cashless (QR ou bracelet NFC), carte (terminal autonome ou Nepting), espèces avec monnaie à rendre, **crédit offert** (badge), vente entièrement offerte.
- **Ticket de caisse** : QR code à scanner par le client (ticket en ligne, téléchargeable) et envoi **par e-mail** ; numérotation continue ; détail de la TVA.
- Menu caisse : remises en banque, apports, **clôture Z** avec comptage et écart ; dernières ventes avec ticket et annulation (stock remis, cashless recrédité).

## Nouveautés 2.6 — Entrevo Caisse, l'application des bars

Une **application dédiée** aux bars et points de vente, séparée du site : le site y renvoie (Cashless & bars, et l'ancienne adresse /bar/…).

- **Deux versions, mêmes écrans** : web app installable sur tablette ou téléphone (`/caisse/`, « Ajouter à l'écran d'accueil », plein écran, s'ouvre sans réseau) et application native Android / iOS (dossier `entrevo-caisse-native`, construite avec Capacitor).
- **Appairage des appareils** depuis *Cashless & bars* : QR code ou code à 6 chiffres, appareil lié à un bar ou au choix, dernière activité, déconnexion à distance. Pas besoin de compte pour les bénévoles.
- **Trois moyens de paiement** : porte-monnaie **cashless** (QR du billet à la caméra ou **bracelet NFC**), **carte bancaire** et **espèces** (montant reçu, monnaie à rendre).
- **Bracelets NFC** : association d'un bracelet à un billet en deux gestes (QR du billet puis contact du bracelet) ; paiement en approchant le bracelet. Application Android, ou Chrome sur Android.
- **Terminal de paiement Nepting** piloté par l'application Android (protocole Caisse-AP, réseau local) : le montant part sur le terminal, la réponse (acceptée / refusée, autorisation) revient dans la caisse. Sinon, terminal autonome avec confirmation sur l'appli.
- **Hors connexion** : les ventes carte et espèces sont gardées sur l'appareil et envoyées au retour du réseau, sans doublon. Le cashless demande le réseau (solde toujours exact).
- **Au bar** : catégories, grille de produits avec consignes, panier, dernières ventes avec annulation pendant 10 minutes (cashless recrédité), solde d'un billet, **récapitulatif de caisse** (cashless, carte, espèces attendues, meilleures ventes) et clôture de session, écran toujours allumé.
- **Côté organisateur** : ventes par bar et par moyen de paiement dans *Cashless & bars*.

## Nouveautés 2.5 — la gestion et la vitrine de l'organisateur

**Organisateurs**
- **Factures & compta** : registre de toutes les factures et avoirs du mois, PDF en un clic, archive ZIP du mois avec le registre en CSV. Une facture émise n'est jamais modifiée : chaque remboursement sur une commande facturée crée un **avoir numéroté** (AV-AAAA-nnnn), automatiquement, quel que soit le chemin du remboursement ; l'acheteur le retrouve sur sa commande. **Facturation en lot** des ventes d'un mois, ou facturation automatique de chaque vente. **Plan comptable réglable** (comptes et journaux) utilisé par l'export FEC. Alerte si une mention obligatoire manque (raison sociale, adresse, SIRET).
- **Bilans & subventions** : bilan d'activité par événement ou par saison, prêt à joindre à un dossier de subvention. Chiffres calculés tout seuls : fréquentation, remplissage, présence, tarifs réduits, nouveaux et fidèles, âge et provenance du public, canaux de vente, recettes, budget prévu et réalisé, bénévolat, artistes. Textes à rédiger (présentation, objectifs, actions, publics, partenaires, perspectives) avec enregistrement automatique. PDF mis en page et CSV.
- **Mini-site** : ta page publique gagne un menu, des pages libres (Qui sommes-nous, Infos pratiques…), des actualités avec image et publication programmée, un texte d'accueil, un pied de page, un **formulaire de contact** (messages reçus par e-mail et dans ton espace) et l'**inscription à ta lettre d'information** ; les abonnés rejoignent l'audience de tes campagnes e-mail.

**Super-admin**
- **Marque blanche** : la billetterie d'un organisateur servie sur **son propre domaine** (billetterie.sonfestival.fr), à ses couleurs, avec son logo et sans la marque de la plateforme ; e-mails de commande à son nom avec des liens vers son domaine. Vérification DNS, instructions de mise en place (CNAME, alias cPanel, HTTPS), activation et coupure.

## Nouveautés 2.4 — vendre partout, encaisser sur place

**Organisateurs**
- **Cashless & bars** : chaque billet devient un porte-monnaie. Rechargement en ligne (depuis la commande ou la page cashless) ou au bar ; caisse plein écran pour tablette (bars protégés par code, produits avec consigne, lecture du QR du billet à la caméra, annulation dans les 10 minutes, solde insuffisant refusé) ; remboursement du solde restant par virement après l'événement, jusqu'à la date choisie ; ventes par bar et par produit.
- **Visuels réseaux sociaux** : visuels prêts à publier, générés depuis l'affiche de l'événement, en story, post, carré et bannière ; quatre messages (annonce, dernières places, c'est ce soir, complet), trois styles (sombre, clair, plein cadre), accroche libre et QR code vers la billetterie ; téléchargement en PNG.
- **Intégrations** : webhooks fiables vers tes outils (Zapier, Make, n8n, ton CRM…) pour 8 événements (commande payée, remboursée, annulée, entrée scannée, événement publié, recharge cashless, demande de devis, nouvel avis). Signature HMAC, nouvelles tentatives espacées pendant 24 h, journal des envois, renvoi en un clic, envoi de test, recettes prêtes à l'emploi.
- **Revendeurs** : autorise des partenaires de la plateforme (offices de tourisme, CSE, commerces) à vendre tes billets au comptoir, avec la commission de ton choix ; relevé mensuel de ce que chacun te doit.

**Super-admin**
- **Revendeurs** : fiche revendeur, commission par défaut, accès ouverts par organisateur, lien secret du portail (envoi par e-mail, renouvellement, suspension), relevé mensuel par organisateur et export CSV.
- **Portail revendeur** : liste des événements à vendre, vente en quelques secondes (date, tarifs, acheteur), **placement numéroté attribué automatiquement** (places côte à côte), billets envoyés à l'acheteur, reçu imprimable, annulation dans les 30 minutes, relevé du mois. Ces ventes sont encaissées par le revendeur : elles n'entrent pas dans le solde à reverser.

## Nouveautés 2.3 — exploiter la plateforme sereinement

**Super-admin**
- **Sauvegardes automatiques** chaque nuit (compressées, rangées hors d'atteinte du web), rotation (les N dernières + une par mois sur 12 mois), copie par e-mail facultative, téléchargement et **restauration protégée** (l'état actuel est sauvegardé juste avant).
- **Alertes** par e-mail et sur Slack, Discord, Teams ou webhook : tâche planifiée arrêtée, sauvegarde en échec, erreurs de paiement en série, pic d'erreurs, disque presque plein, e-mails bloqués, nouveau litige, demande urgente, commande suspecte. Message « rétabli » quand le problème disparaît.
- **Fraude & risques** : score de risque explicable pour chaque commande (e-mail jetable, rafale depuis une même adresse IP, litiges passés, gros montant d'un nouvel acheteur…), file « à vérifier », blocage d'e-mails, de domaines et d'adresses IP, refus optionnel des e-mails jetables.
- **Contenus & e-mails** : pages éditables (FAQ, à propos…) en ligne sur /page/… avec lien en pied de page ; nouvel habillage des e-mails (en-tête clair, noir ou couleur, logo, signature, pied de page) avec aperçu en direct.
- **Prospection** : tableau des organisateurs à démarcher par étape (glisser-déposer), fiche, historique des appels et e-mails, relances du jour, import d'une liste, lien d'inscription, passage en « gagné ».

**Organisateurs**
- **Groupes & devis** : bouton « Venir en groupe » sur la page de l'événement, devis préparé en quelques clics (tarifs, remise, validité, date), envoyé avec son PDF, accepté en ligne ; paiement par carte (remise appliquée automatiquement) ou par virement (billets envoyés dès que tu confirmes la réception, sans commission).
- **Factures acheteurs** : l'acheteur obtient sa facture PDF (société, adresse, n° de TVA) depuis sa commande ; numérotation continue par organisateur.
- **Artistes & planning** : fiches artistes (cachet, statut, fiche technique, accueil), passages par scène avec arrivée et balance, **planning visuel**, alerte de chevauchement, feuille de route PDF, espace artiste avec ses horaires, publication du programme sur la page publique.
- **Objets trouvés** : registre avec référence et photo, page publique de déclaration de perte, **rapprochement automatique** des pertes et des objets, e-mail au propriétaire, restitution.

## Nouveautés 2.2 — un super-admin complet, une gestion plus profonde

**Super-admin**
- **Formules** : offres pour les organisateurs (commission, plafond par billet, abonnement mensuel, prix des SMS, modules inclus). Un module hors formule affiche « non inclus dans ta formule » avec un bouton pour le demander. Formule par défaut, revenu mensuel récurrent.
- **Contrats & tarifs** : conditions négociées par organisateur, avec dates, contrat signé en PDF et **palier automatique** (commission réduite au-delà d'un volume de ventes). Ordre d'application : contrat, puis réglage particulier, puis formule, puis réglage global.
- **Retenues sur reversements** : abonnements, coût des SMS, litiges perdus et ajustements manuels (ou avoirs) sont déduits automatiquement du solde à reverser ; l'organisateur les voit dans Finances.
- **Litiges & impayés** : contestations de paiement suivies avec échéance, **dossier de preuves pré-rempli** (billets envoyés, scans à l'entrée), organisateur prévenu, retenue appliquée si le litige est perdu et annulée s'il est gagné. Les litiges Stripe arrivent tout seuls (vérifiés auprès de Stripe).
- **Support** : vraie page Contact, suivi de la demande par lien, espace « Aide & support » pour les organisateurs, file de traitement avec priorités, prise en charge, notes internes, réponses enregistrées, contexte (commandes de la personne, demandes précédentes).
- **Données personnelles (RGPD)** : formulaire public avec confirmation par e-mail, délai d'un mois suivi, inventaire de tout ce que la plateforme sait d'une personne, copie envoyée en JSON, opposition (plus d'e-mails ni de SMS), anonymisation. Conservation limitée réglable (anonymisation automatique des acheteurs après N ans).
- **Fiche organisateur 360** : ventes et commission sur 12 mois, conditions appliquées et leur origine, formule, contrats, reversements, retenues, factures, équipe, connexions, journal, support, litiges, message direct.
- **Commandes** : filtres (statut, paiement, organisateur, dates, montant), totaux, export CSV, fiche détaillée avec historique, renvoi des billets, remboursement total ou partiel, et **geste commercial pris sur la commission**.
- **Utilisateurs & sécurité** : **double authentification** (application type Google Authenticator), journal des connexions, verrouillage après échecs, appareils connectés déconnectables à distance, membres de l'équipe plateforme **à accès limité** (support seulement, argent seulement…).
- **Réglages** : nouveaux onglets Apparence (logo), Plateforme (**mode maintenance** qui laisse fonctionner le scan, limites, modules coupés pour tous) et Sécurité (2FA obligatoire pour l'équipe, verrouillage).

**Gestion d'événement**
- Les 23 onglets d'un événement sont rangés en 7 rubriques (Vendre, Communiquer, Participants, Organiser, Jour J…).
- **Tarifs** : tableau de pilotage (statut réel : en vente, programmé, palier suivant, complet, en pause ; ventes, recettes, entrés), pause en un clic, duplication, ordre par glisser-déposer, alerte si les quotas ne couvrent pas la jauge.
- **Reprendre un événement** pour une nouvelle édition : tout est recopié en brouillon avec les dates décalées (ventes, early bird, codes promo, créneaux bénévoles, tâches, budget).
- **Prévision de remplissage** au jour J, d'après tes éditions passées ou le rythme actuel, avec le nombre de ventes par jour nécessaire pour remplir.

**Jour J**
- Contrôle en direct : débit par minute et par porte, heure estimée de fin de file, pic d'affluence, taux et motifs de refus, absents, graphique entrées / sorties par quart d'heure.
- Guichet : total à encaisser et **rendu de monnaie** (billets rapides 5 € à 100 €).

**Corrections**
- La création d'un nouvel événement plantait depuis la 1.9 : corrigé.
- Appli de scan et PC sécurité de nouveau lisibles (thème sombre), boutons du bandeau d'événement visibles, notifications lisibles.

## Nouveautés 2.1 — vendre au-delà des billets

**Cartes cadeaux** (Vendre plus › Cartes cadeaux)
- Page de vente publique (bouton « Offrir une carte cadeau » sur ta page organisateur) : montants proposés ou montant libre, pour soi ou à offrir avec un petit mot, envoyée par e-mail avec une carte PDF à imprimer.
- Utilisable en plusieurs fois sur tous tes événements, dans le même champ que les codes promo ; page publique pour connaître le solde.
- Pas de commission à la vente de la carte : elle est prélevée normalement quand la carte paie des billets.
- Côté organisateur : cartes émises, montants utilisés et restants, cartes offertes (geste commercial, lot de concours), annulation.

**Abonnements & pass saison** (Vendre plus › Abonnements)
- Pass saison (toutes les dates choisies) ou carnet de N entrées, prix, quota, date de fin, événements compris.
- L'abonné reçoit un lien vers son espace : il réserve sa place à chaque événement en un clic, sans repayer, et reçoit son billet comme d'habitude.

**Boutique & merch** (Vendre plus › Boutique & merch)
- Produits avec photo, prix, stock et tailles ou modèles ; retrait sur place et/ou envoi postal avec frais de port.
- Suivi des commandes à préparer (« préparée / remise »), ventes par produit.

**Campagnes SMS** (Faire venir du monde › Campagnes SMS)
- Message avec prénom et lien suivi vers l'événement, aperçu sur un téléphone, compteur de caractères et de SMS, coût estimé avant l'envoi, test sur ton mobile.
- Mêmes profils que les e-mails (fidèles, récents, dormants…), uniquement les acheteurs qui ont accepté de recevoir tes actus ; liste STOP et mention de désinscription ajoutée à chaque SMS.
- Envoi tout de suite ou programmé, uniquement du lundi au samedi de 8 h à 20 h hors jours fériés ; rapport par destinataire et ventes générées.
- Super-admin › Réglages › SMS : clé API Brevo, prix par SMS, mention STOP. Sans clé, tout fonctionne en mode test (SMS écrits dans le journal).

**Super-admin**
- Nouvelle page **Statistiques** : volume d'affaires, commissions, commandes et billets sur 3 à 24 mois, prestataires de paiement, canaux de vente, meilleurs événements, clients fidèles.

## Nouveautés 2.0 ✨ — la V2

**Nouveau design, haut de gamme**
- Thème inspiré d'Apple : police système (SF Pro sur iPhone et Mac, Inter ailleurs), titres en minuscules, boutons en pilule noirs, beaucoup d'air, ombres très douces.
- Éléments qui flottent : barre de navigation en verre dépoli, menu de l'espace pro en panneau de verre, tiroir d'achat en carte flottante, barre « Je réserve » sur mobile, menus et notifications en verre.
- Plus aucun emoji dans l'interface : remplacés par de fines icônes dessinées. Le bleu est réservé aux liens ; la couleur principale reste réglable dans Super-admin › Réglages.

**Vente**
- Codes promo : date de début, minimum de billets, « une fois par personne », **lots de codes uniques** (10 à 500, avec préfixe, export CSV), ventes et remises générées par code.
- Options avec **variantes** (tailles, couleurs) et ventes par variante.
- Minuteur de paiement et durée de la place gardée (liste d'attente) réglables par événement ; bouton « Proposer » pour offrir une place à une personne précise.
- 2ᵉ relance des paniers abandonnés 24 h après la première, avec un **code promo unique** facultatif (déjà appliqué via le bouton).

**Faire venir du monde**
- **Liens de suivi & ambassadeurs** (onglet de l'événement, et menu « Liens & ambassadeurs ») : un lien par canal (story, affiche, newsletter) ou par personne, avec QR code à imprimer, code promo appliqué automatiquement, clics, ventes, chiffre d'affaires et conversion. Classement des ambassadeurs ; chaque ambassadeur reçoit son lien et une **page de résultats** à lui.
- Campagnes : nouveaux segments « venus au moins une fois » et « ont acheté mais jamais venus ».

**Pages publiques**
- Onglet **Page** de l'événement : programme horaire, infos pratiques (venir, stationnement, accessibilité, à savoir, bar, contact) et **questions fréquentes** (aussi lues par Google).
- **Page organisateur** personnalisable (Mon organisation) : image de couverture, logo, accroche, réseaux sociaux, chiffres, événements passés, avis avec réponses.
- Note moyenne de l'organisateur affichée sur la page de l'événement.

**Après l'achat**
- **Espace « Mes billets »** : connexion par lien e-mail, tous les billets à venir et passés (même ceux offerts), accès direct aux commandes, invitation à donner son avis.
- Rappel avant l'événement : délai au choix (veille, 2 jours, 3 jours, 1 semaine) et message personnalisé par événement.
- Réponses publiques de l'organisateur sous les avis ; délai limite pour donner un billet.

**Gestion & jour J**
- Commandes : filtres par tarif, code promo et source (invité par un ami, relance panier, tarif groupe…), export enrichi.
- Points d'accès : **horaires d'ouverture et de fermeture automatiques** (respectés même hors ligne).
- PC sécurité : **main courante** (incidents par catégorie, zone, niveau ; clos/rouvrir ; export CSV).

## Nouveautés 1.9 ✨

**Pour les acheteurs**
- **📅 Changer de date** : sur un événement à plusieurs dates, l'acheteur déplace toute sa commande sur une autre date depuis sa page de commande, s'il reste de la place pour chacun de ses tarifs. Les QR codes restent les mêmes et les billets sont renvoyés par e-mail. À activer dans la fiche de l'événement (avec un délai : jusqu'à X heures avant la date d'origine). Indisponible si un billet a déjà servi, a été donné ou est en revente, et pour les événements avec plan de salle.
- **👯 Inviter des amis** : après l'achat, un encadré « Plus on est de fous… » (et une ligne dans l'e-mail de confirmation) donne un lien perso à partager : WhatsApp, SMS, e-mail, partage du téléphone ou copier. Les ventes passées par ce lien sont comptées ; l'acheteur voit combien d'amis ont pris leur place grâce à lui.

**Pour les organisateurs** (fiche de l'événement)
- **👥 Tarif dégressif** : jusqu'à 3 paliers (ex. −10 % dès 4 billets, −15 % dès 8). La remise s'applique à tous les billets payants de la commande, s'affiche en direct dans le tiroir (« plus que 1 billet pour −10 % ») et dans le récap. Cumulable avec un code promo, pas avec les partenaires ni la revente.
- **🔒 Préventes privées** : une date d'ouverture au grand public ; avant, seules les personnes autorisées (liste d'e-mails et/ou anciens participants) peuvent acheter. Elles entrent leur e-mail sur la page et reçoivent un lien d'accès personnel. Compte des liens envoyés et utilisés dans la fiche.
- **🔞 Restriction d'âge** : âge minimum par événement ou par tarif. Date de naissance et confirmation demandées à l'achat (refus si trop jeune), mention sur la page et sur le billet PDF, rappel « vérifier l'âge » sur l'appli de scan.
- Campagnes : nouvel encadré **Bouche-à-oreille** (ventes et chiffre d'affaires venus des invitations, meilleurs ambassadeurs). Le détail d'une commande indique « invité par », la remise de groupe, les changements de date et la date de naissance.
- Sur téléphone, la barre d'étapes de la page de confirmation ne déborde plus.

## Nouveautés 1.8 ✨

**Pour les acheteurs**
- **🎁 Donner son billet à un ami** : depuis la page de commande, en indiquant nom et e-mail. Le billet passe au nom de l'ami avec un **nouveau QR code** (l'ancien est refusé au scan). L'ami reçoit son billet par e-mail et sa propre page (`/billet/…`). Jusqu'à 3 transferts par billet. L'organisateur peut couper les transferts dans la fiche de l'événement et voit l'historique dans le détail de commande.
- **📅 Ajouter à mon agenda** : Google Agenda ou fichier .ics (Apple, Outlook), depuis la page de l'événement, la commande et l'e-mail de confirmation.
- **Filtres rapides sur l'accueil** : Ce soir, Ce week-end, Cette semaine, Gratuit (avec le nombre d'événements).
- **⭐ Avis** : note en un clic depuis l'e-mail, commentaire facultatif ; les avis s'affichent sur la page de l'organisateur.

**Envois automatiques** (organisateurs › Campagnes › « Envois automatiques », chacun désactivable)
- **🛒 Relance des paniers abandonnés** : 1 h après un paiement pas terminé, un e-mail avec un lien qui remet le panier et les infos en place. Les ventes récupérées sont comptées.
- **⏰ Rappel la veille** : heure, adresse avec itinéraire, lien vers les billets.
- **⭐ Demande d'avis** le lendemain (seulement aux personnes venues si l'événement a été scanné). Nouvelle page **Avis des participants** (note moyenne, répartition, avis par événement, masquer/afficher).
- Ces e-mails partent avec les tâches de fond : pour qu'ils partent à l'heure, vérifie la tâche planifiée (cron) dans Super-admin › Santé.

**Billetterie**
- **🐦 Early bird / paliers** : dans un tarif, « S'ouvre quand … est épuisé ou terminé ». Le palier suivant s'affiche grisé en attendant, le tarif en cours affiche « Prix early bird · plus que N à ce prix » et un compte à rebours la dernière semaine s'il a une date de fin.
- **🎟️ Nouveaux billets PDF** : billet aux couleurs de l'événement (affiche, titre, date et lieu en gros), ligne de découpe, **QR code deux fois plus grand**, nom du participant bien visible, conseils pratiques et mot de l'organisateur.
- Page organisateur : les cartes d'événements s'affichent enfin en grille.

## Nouveautés 1.7 ✨ — nouveau parcours d'achat

- **Achat dans un tiroir, sans quitter la page de l'événement** : panneau à droite sur ordinateur, feuille qui monte du bas sur téléphone. 4 étapes claires : **Billets → Toi → Récap → Paiement**, avec le total toujours visible en bas.
- **Billets façon « ticket »** (encoches, prix en gros, tarif choisi surligné) et une carte « Réserver » sur la page de l'événement.
- **Noms des participants facultatifs** : les billets sont au nom de l'acheteur par défaut. Après l'achat, le bloc **« Qui vient avec toi ? »** de la page de commande permet de renommer chaque billet (et de se renvoyer les PDF à jour). Les questions marquées obligatoires par l'organisateur restent demandées pendant l'achat.
- **Minuteur de réservation** : les places sont gardées 20 minutes, compte à rebours visible au paiement (il passe au rouge sur les 2 dernières minutes).
- **Options en fin de parcours** : produits (t-shirt…), don et code promo sont proposés à l'étape Récap, avec récapitulatif mis à jour en direct.
- **Connexion rapide** : « Déjà venu ? » envoie un lien par e-mail (valable 30 minutes) qui préremplit les infos ; l'appareil peut aussi s'en souvenir.
- Les événements avec **plan de salle** gardent l'ancien parcours (choix des places sur le plan).

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
