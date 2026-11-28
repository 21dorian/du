<?php
declare(strict_types=1);

/**
 * Catalogue des modules de l'espace organisateur.
 * status : 'on' (disponible, lien direct), 'beta' (disponible, en rodage), 'soon' (annoncé : page « Bientôt » + vote).
 * Les modules « soon » servent de feuille de route visible : les votes des organisateurs
 * remontent au super-admin (Super-admin › Modules) pour prioriser les développements.
 */
final class Features
{
    public const CATEGORIES = [
        'sell' => ['', 'Vendre'],
        'market' => ['', 'Faire venir du monde'],
        'onsite' => ['', 'Le jour J'],
        'money' => ['', 'Argent & compta'],
        'team' => ['', 'Équipe & organisation'],
        'connect' => ['', 'Connecter'],
    ];

    /** @return array<string, array{cat:string, emoji:string, name:string, status:string, url?:string, pitch:string, points?:array, eta?:string}> */
    public static function all(): array
    {
        $a = self::base() + self::extras();
        // modules sortis du développement (1.5) : on garde leur fiche, avec un lien direct
        foreach (['jauge-zone' => '/pro/pc', 'tva' => '/pro/finances', 'export-compta' => '/pro/finances'] as $k => $url) if (isset($a[$k])) $a[$k] = ['status' => 'beta', 'url' => $url] + $a[$k];
        // 1.8 : sortis du développement
        foreach (['avis' => '/pro/avis', 'relance-panier' => '/pro/campagnes', 'calendrier' => '/pro/campagnes', 'transfert-billet' => '/pro/evenements', 'early-bird' => '/pro/evenements', 'echange-date' => '/pro/evenements', 'tarif-groupe' => '/pro/evenements', 'vente-privee' => '/pro/evenements', 'restriction-age' => '/pro/evenements', 'affiliation' => '/pro/liens', 'cartes-cadeaux' => '/pro/cartes-cadeaux', 'abonnements' => '/pro/abonnements', 'boutique' => '/pro/boutique', 'sms' => '/pro/sms'] as $k => $url) if (isset($a[$k])) $a[$k] = ['status' => 'on', 'url' => $url] + $a[$k];
        // 4.0 : tout ce qui était en rodage ou déjà construit passe disponible, plus les nouveaux modules
        foreach (['cashless' => '/pro/cashless', 'mini-site' => '/pro/mini-site', 'reseaux' => '/pro/visuels', 'bracelets' => '/pro/cashless', 'objets-trouves' => '/pro/objets-trouves', 'artistes' => '/pro/artistes',
            'factures' => '/pro/factures', 'devis' => '/pro/devis', 'subventions' => '/pro/bilans', 'decouverte' => '/', 'campagnes' => '/pro/campagnes', 'controle-pro' => '/pro/pc', 'budget' => '/pro/budget',
            'documents' => '/pro/documents', 'benevoles' => '/pro/benevoles', 'taches' => '/pro/taches', 'jauge-zone' => '/pro/pc', 'tva' => '/pro/finances', 'export-compta' => '/pro/factures',
            'prix-dynamique' => '/pro/evenements', 'double-auth' => '/mon-compte', 'zapier' => '/pro/connecteurs', 'google-sheets' => '/pro/connecteurs', 'emailing-externe' => '/pro/connecteurs', 'pixels-avances' => '/pro/connecteurs',
            'codes-auto' => '/pro/automatisations', 'parrainage' => '/pro/automatisations', 'rapport-fin' => '/pro/automatisations', 'partage-stats' => '/pro/evenements', 'saison' => '/pro/evenements',
            'places-pmr' => '/pro/evenements', 'billet-nominatif-id' => '/pro/evenements', 'vestiaire' => '/pro/evenements', 'badges-live' => '/pro/evenements', 'kiosque' => '/pro/evenements',
            'roles-perso' => '/pro/equipe', 'import-contacts' => '/pro/contacts', 'doublons' => '/pro/contacts', 'app-orga' => '/pro/app',
            'paiement-plusieurs-fois' => '/pro/evenements', 'google-wallet' => '/pro/evenements'] as $k => $url) // 4.1 if (isset($a[$k])) $a[$k] = ['status' => 'on', 'url' => $url] + $a[$k];
        return $a;
    }

    private static function base(): array
    {
        return [
            // ---- Vendre
            'billetterie' => ['cat' => 'sell', 'emoji' => '', 'name' => 'Billetterie en ligne', 'status' => 'on', 'url' => '/pro/evenements', 'pitch' => 'Tarifs, quotas, dates multiples, prix libre, packs, options et dons.'],
            'guichet' => ['cat' => 'sell', 'emoji' => '', 'name' => 'Guichet sur place', 'status' => 'on', 'url' => '/pro/evenements', 'pitch' => 'Vente au comptoir, ticket thermique, clôture de caisse.'],
            'placement' => ['cat' => 'sell', 'emoji' => '', 'name' => 'Placement numéroté', 'status' => 'on', 'url' => '/pro/evenements', 'pitch' => 'Plan de salle 2D, choix des places par l\'acheteur.'],
            'revente' => ['cat' => 'sell', 'emoji' => '', 'name' => 'Bourse aux billets', 'status' => 'on', 'url' => '/pro/evenements', 'pitch' => 'Revente officielle avec remboursement automatique du vendeur.'],
            'abonnements' => ['cat' => 'sell', 'emoji' => '', 'name' => 'Abonnements & pass saison', 'status' => 'soon', 'eta' => 'Été 2027',
                'pitch' => 'Un pass qui ouvre plusieurs événements, des cartes 10 entrées, des abonnements annuels.',
                'points' => ['Pass multi-événements avec quota par événement', 'Cartes à entrées (5, 10…) décomptées au scan', 'Renouvellement annuel en un clic', 'Tarifs abonnés automatiques']],
            'boutique' => ['cat' => 'sell', 'emoji' => '', 'name' => 'Boutique & merch', 'status' => 'soon', 'eta' => 'Printemps 2027',
                'pitch' => 'Vends t-shirts, affiches et goodies avec ou sans billet, retrait sur place ou envoi.',
                'points' => ['Produits avec tailles et stocks', 'Retrait au stand avec QR code', 'Envoi postal avec frais de port', 'Ventes hors événement']],
            'cartes-cadeaux' => ['cat' => 'sell', 'emoji' => '', 'name' => 'Cartes cadeaux', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Des bons d\'achat à offrir, utilisables sur tous tes événements.',
                'points' => ['Montant libre ou fixe', 'Carte PDF personnalisée', 'Solde utilisable en plusieurs fois']],
            'cashless' => ['cat' => 'sell', 'emoji' => '', 'name' => 'Cashless & bars', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Paiement sans espèces sur place : bracelet ou QR rechargeable, caisses bar et food.',
                'points' => ['Recharge en ligne avant l\'événement', 'Caisses tactiles sur téléphone', 'Remboursement du solde restant', 'Stats de consommation en direct']],

            // ---- Faire venir du monde
            'codes-promo' => ['cat' => 'market', 'emoji' => '', 'name' => 'Codes promo & invitations', 'status' => 'on', 'url' => '/pro/evenements', 'pitch' => 'Réductions, codes de déblocage, invitations en masse, partenaires.'],
            'widget' => ['cat' => 'market', 'emoji' => '', 'name' => 'Widget pour ton site', 'status' => 'on', 'url' => '/pro/organisation', 'pitch' => 'La billetterie directement intégrée à ton site web.'],
            'suivi' => ['cat' => 'market', 'emoji' => '', 'name' => 'Suivi marketing', 'status' => 'on', 'url' => '/pro/organisation', 'pitch' => 'Google Analytics, Meta Pixel, liens UTM et entonnoir de conversion.'],
            'campagnes' => ['cat' => 'market', 'emoji' => '', 'name' => 'Campagnes e-mail', 'status' => 'beta', 'url' => '/pro/campagnes',
                'pitch' => 'Préviens tes anciens participants de ton prochain événement : segments, éditeur, ouvertures, clics et ventes générées.'],
            'sms' => ['cat' => 'market', 'emoji' => '', 'name' => 'Campagnes SMS', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Un SMS la veille ou pour les dernières places : le canal le plus lu, en complément des e-mails.',
                'points' => ['Mêmes segments que les e-mails', 'Lien suivi et ventes attribuées', 'Mention STOP et liste de désinscrits', 'Envoi seulement aux heures autorisées']],
            'mini-site' => ['cat' => 'market', 'emoji' => '', 'name' => 'Mini-site d\'événement', 'status' => 'soon', 'eta' => 'Printemps 2027',
                'pitch' => 'Une vraie page de festival : programmation, artistes, plan, FAQ, partenaires, avec ton propre nom de domaine.',
                'points' => ['Blocs : line-up, horaires, plan d\'accès, FAQ', 'Thèmes de couleurs et polices', 'Nom de domaine personnalisé', 'Billetterie intégrée']],
            'affiliation' => ['cat' => 'market', 'emoji' => '', 'name' => 'Ambassadeurs & affiliation', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Donne un lien perso à tes ambassadeurs et récompense ceux qui vendent le plus.',
                'points' => ['Lien de suivi par ambassadeur', 'Classement des meilleurs vendeurs', 'Récompenses : invitations, réductions, commissions']],
            'decouverte' => ['cat' => 'market', 'emoji' => '', 'name' => 'Place de marché Entrevo', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Tes événements mis en avant auprès de tout le public de la plateforme, par ville et par thème.',
                'points' => ['Pages par ville et par catégorie', 'Recommandations personnalisées', 'Mise en avant sponsorisée']],
            'avis' => ['cat' => 'market', 'emoji' => '', 'name' => 'Avis & questionnaire de satisfaction', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Un e-mail le lendemain pour récolter notes et avis, et les afficher sur tes prochaines pages.',
                'points' => ['Envoi automatique après l\'événement', 'Questions personnalisables', 'Note moyenne affichée sur ta page organisateur']],

            // ---- Le jour J
            'reseaux' => ['cat' => 'market', 'emoji' => '', 'name' => 'Réseaux sociaux', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Visuels prêts à poster générés depuis ton affiche, et publication programmée sur Instagram et Facebook.',
                'points' => ['Visuels carré, story et bannière en un clic', 'Compte à rebours « J-7 », « Dernières places » automatiques', 'Publication programmée', 'Lien suivi pour mesurer les ventes']],
            'controle' => ['cat' => 'onsite', 'emoji' => '', 'name' => 'Contrôle d\'accès', 'status' => 'on', 'url' => '/scan', 'pitch' => 'Appli de scan hors ligne, douchette, portes, zones, entrées/sorties.'],
            'accreditations' => ['cat' => 'onsite', 'emoji' => '', 'name' => 'Accréditations & badges', 'status' => 'on', 'url' => '/pro/evenements', 'pitch' => 'Presse, artistes, prestataires : badges imprimables par zone.'],
            'controle-pro' => ['cat' => 'onsite', 'emoji' => '', 'name' => 'PC sécurité temps réel', 'status' => 'beta', 'url' => '/pro/pc', 'eta' => 'Printemps 2027',
                'pitch' => 'Un écran de supervision : jauge par zone, flux par porte, alertes de saturation et appareils connectés.',
                'points' => ['Jauge en direct par zone', 'Débit par porte et prévision d\'attente', 'Alertes de saturation', 'Liste des appareils de scan et leur batterie']],
            'bracelets' => ['cat' => 'onsite', 'emoji' => '', 'name' => 'Bracelets RFID / NFC', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Échange billet → bracelet à l\'entrée, puis contrôle sans contact.',
                'points' => ['Association billet bracelet au comptoir', 'Lecture NFC sur téléphone Android', 'Compatible cashless']],
            'objets-trouves' => ['cat' => 'onsite', 'emoji' => '', 'name' => 'Objets trouvés', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Les participants déclarent une perte, ton équipe enregistre les objets trouvés, la plateforme fait le lien.',
                'points' => ['Formulaire de déclaration lié au billet', 'Registre avec photo', 'Notification automatique en cas de correspondance']],

            // ---- Argent & compta
            'artistes' => ['cat' => 'onsite', 'emoji' => '', 'name' => 'Artistes & planning', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'La programmation et la logistique artistes : horaires de passage, fiches techniques, loges, catering, invitations.',
                'points' => ['Feuille de route par artiste (arrivée, balance, passage)', 'Fiches techniques et riders en pièce jointe', 'Line-up publié automatiquement sur la page', 'Invitations artistes en un clic']],
            'finances' => ['cat' => 'money', 'emoji' => '', 'name' => 'Finances & reversements', 'status' => 'on', 'url' => '/pro/finances', 'pitch' => 'Solde, commissions, reversements et relevés.'],
            'factures' => ['cat' => 'money', 'emoji' => '', 'name' => 'Factures & compta', 'status' => 'soon', 'eta' => 'Printemps 2027',
                'pitch' => 'Factures automatiques pour les acheteurs pro, exports comptables, TVA par tarif.',
                'points' => ['Facture PDF numérotée à la demande de l\'acheteur', 'Taux de TVA par tarif', 'Export FEC et grand livre', 'Rapprochement des reversements']],
            'devis' => ['cat' => 'money', 'emoji' => '', 'name' => 'Devis & ventes groupes', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Comités d\'entreprise, écoles, groupes : devis, bon de commande, paiement par virement.',
                'points' => ['Devis PDF en un clic', 'Paiement différé par virement', 'Billets nominatifs remplis par le groupe']],
            'subventions' => ['cat' => 'money', 'emoji' => '', 'name' => 'Bilans pour subventions', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Les chiffres que demandent les financeurs, prêts à copier : fréquentation, publics, tarifs sociaux.',
                'points' => ['Fréquentation par tranche d\'âge et ville', 'Part des tarifs réduits et gratuits', 'Export PDF du bilan']],

            // ---- Équipe & organisation
            'budget' => ['cat' => 'money', 'emoji' => '', 'name' => 'Budget prévisionnel', 'status' => 'beta', 'url' => '/pro/budget', 'eta' => '2027',
                'pitch' => 'Dépenses prévues contre recettes réelles : sais en direct si ton événement est rentable, et à partir de combien de billets.',
                'points' => ['Postes de dépenses (artistes, technique, sécurité…)', 'Seuil de rentabilité calculé', 'Recettes billetterie reliées automatiquement', 'Export pour ton trésorier']],
            'documents' => ['cat' => 'money', 'emoji' => '', 'name' => 'Documents & contrats', 'status' => 'beta', 'url' => '/pro/documents', 'eta' => '2027',
                'pitch' => 'Tous les papiers de l\'événement au même endroit : contrats, assurances, autorisations, devis, avec rappels d\'échéance.',
                'points' => ['Dossier par événement', 'Rappels avant échéance (assurance, préfecture, SACEM)', 'Signature électronique', 'Partage avec l\'équipe']],
            'equipe' => ['cat' => 'team', 'emoji' => '', 'name' => 'Équipe & droits', 'status' => 'on', 'url' => '/pro/equipe', 'pitch' => 'Responsables, gestionnaires, contrôleurs.'],
            'journal' => ['cat' => 'team', 'emoji' => '', 'name' => 'Journal d\'activité', 'status' => 'on', 'url' => '/pro/journal', 'pitch' => 'Qui a fait quoi, et quand.'],
            'benevoles' => ['cat' => 'team', 'emoji' => '', 'name' => 'Staff & bénévoles', 'status' => 'beta', 'url' => '/pro/benevoles', 'eta' => 'Printemps 2027',
                'pitch' => 'Recrute, planifie et pointe tes bénévoles : créneaux, postes, rappels et badge QR.',
                'points' => ['Annuaire réutilisable d\'un événement à l\'autre', 'Formulaire de candidature public', 'Planning par poste avec taux de remplissage', 'Espace perso pour chaque bénévole', 'Pointage par badge QR et heures réalisées']],
            'taches' => ['cat' => 'team', 'emoji' => '', 'name' => 'Tâches & rétroplanning', 'status' => 'beta', 'url' => '/pro/taches', 'eta' => '2027',
                'pitch' => 'La to-do de ton événement : jalons, responsables, échéances et rappels.',
                'points' => ['Modèles de rétroplanning (festival, concert, salon)', 'Assignation à un membre de l\'équipe', 'Vue calendrier et kanban']],
            'multi-orga' => ['cat' => 'team', 'emoji' => '', 'name' => 'Réseau multi-structures', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Fédérations, réseaux de salles : un compte parent qui voit les chiffres de toutes ses structures.',
                'points' => ['Tableau de bord consolidé', 'Droits par structure', 'Rapports communs']],

            // ---- Connecter
            'api' => ['cat' => 'connect', 'emoji' => '', 'name' => 'API & webhooks', 'status' => 'on', 'url' => '/pro/organisation', 'pitch' => 'Accès à tes données et notifications temps réel pour tes outils.'],
            'import' => ['cat' => 'connect', 'emoji' => '', 'name' => 'Import de billets externes', 'status' => 'on', 'url' => '/pro/evenements', 'pitch' => 'Contrôle aussi les billets vendus ailleurs.'],
            'zapier' => ['cat' => 'connect', 'emoji' => '', 'name' => 'Zapier, Make & n8n', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Branche Entrevo à 5 000 applis sans coder : Google Sheets, Mailchimp, Slack…',
                'points' => ['Déclencheurs : nouvelle commande, nouveau scan', 'Actions : créer une invitation, un code promo']],
            'google-wallet' => ['cat' => 'connect', 'emoji' => '', 'name' => 'Billets Apple & Google Wallet', 'status' => 'soon', 'eta' => 'Printemps 2027',
                'pitch' => 'Le billet directement dans le portefeuille du téléphone, avec rappel automatique le jour J.',
                'points' => ['Bouton « Ajouter au Wallet » dans l\'e-mail', 'Mise à jour du billet en cas de changement', 'Rappel géolocalisé']],
        ];
    }


    /** Options et petits plus annoncés dans les pages (boutons et cases « dév »). */
    private static function extras(): array
    {
        $x = fn(string $cat, string $emoji, string $name, string $pitch, array $points = []) => ['cat' => $cat, 'emoji' => $emoji, 'name' => $name, 'status' => 'soon', 'eta' => '2027', 'pitch' => $pitch, 'points' => $points, 'extra' => true];
        return [
            // vendre
            'prix-dynamique' => $x('sell', '', 'Tarification dynamique', 'Le prix monte tout seul quand la demande s\'emballe (ou baisse pour remplir les derniers jours).', ['Paliers selon le remplissage', 'Prix plancher et plafond', 'Historique des prix affiché à l\'organisateur']),
            'early-bird' => $x('sell', '', 'Early bird automatique', 'Un tarif qui bascule automatiquement au suivant à une date ou après X billets vendus.', ['Enchaînement de tarifs', 'Compte à rebours « fin du prix early » sur la page', 'Alerte quand on change de palier']),
            'paiement-plusieurs-fois' => $x('sell', '', 'Paiement en 3 fois', 'Les gros paniers (pass festival, VIP) payables en plusieurs échéances.', ['2, 3 ou 4 fois sans frais pour l\'acheteur', 'Billet actif dès la première échéance', 'Relance automatique en cas d\'échec']),
            'tarif-groupe' => $x('sell', '', 'Tarif dégressif par quantité', 'Plus on achète de billets, moins ils coûtent : idéal pour les bandes de potes et les CE.', ['Paliers (5, 10, 20 billets…)', 'Affichage de l\'économie réalisée']),
            'assurance-annulation' => $x('sell', '', 'Assurance annulation', 'L\'acheteur peut assurer son billet en cas d\'empêchement (maladie, transport…).', ['Option au moment du paiement', 'Remboursement géré par l\'assureur', 'Commission reversée à l\'organisateur']),
            'transfert-billet' => $x('sell', '', 'Transfert de billet', 'L\'acheteur donne son billet à un ami en deux clics : nouveau nom, nouveau QR code, l\'ancien est désactivé.', ['Depuis l\'e-mail ou l\'espace « Mes billets »', 'Limite de transferts réglable', 'Historique visible par l\'organisateur']),
            'echange-date' => $x('sell', '', 'Échange de date', 'Sur un événement à plusieurs dates, l\'acheteur change de séance lui-même (s\'il reste de la place).', ['Frais d\'échange optionnels', 'Date limite d\'échange']),
            'billet-nominatif-id' => $x('sell', '', 'Contrôle d\'identité', 'Billets strictement nominatifs : nom vérifié à l\'entrée avec la pièce d\'identité, anti-revente sauvage.', ['Nom affiché en grand au scan', 'Date de naissance demandée', 'Transfert seulement via la bourse officielle']),
            'vente-privee' => $x('sell', '', 'Préventes privées', 'Vendre en avant-première à une liste précise : abonnés, anciens participants, membres.', ['Liste d\'e-mails autorisés', 'Lien privé ou code membre', 'Ouverture au public programmée']),
            'places-pmr' => $x('sell', '', 'Places PMR & accompagnateurs', 'Réserver des places accessibles et offrir le billet de l\'accompagnateur.', ['Quota de places PMR', 'Billet accompagnateur gratuit lié', 'Informations d\'accès dédiées']),
            'restriction-age' => $x('sell', '', 'Restriction d\'âge', 'Âge minimum par événement ou par tarif, rappelé à l\'achat et vérifié à l\'entrée.', ['Date de naissance obligatoire', 'Alerte « mineur » au scan']),
            // faire venir du monde
            'relance-panier' => $x('market', '', 'Relance des paniers abandonnés', 'Un e-mail automatique à ceux qui ont commencé à acheter sans finir, avec leur panier prêt.', ['Envoi 1 h puis 24 h après', 'Code promo optionnel', 'Ventes récupérées dans les stats']),
            'codes-auto' => $x('market', '', 'Codes promo automatiques', 'Des réductions qui partent toutes seules : anniversaire, fidélité, retour après longtemps.', ['Scénarios prêts à l\'emploi', 'Code unique par personne']),
            'parrainage' => $x('market', '', 'Parrainage acheteurs', 'Chaque acheteur reçoit un lien : s\'il fait venir des amis, il gagne une réduction ou une boisson.', ['Lien perso après l\'achat', 'Récompense automatique', 'Classement des meilleurs parrains']),
            'pixels-avances' => $x('market', '', 'Suivi publicitaire avancé', 'Conversions envoyées côté serveur à Meta, Google et TikTok pour des pubs mieux optimisées.', ['API Conversions Meta', 'Google Ads et TikTok', 'Résistant aux bloqueurs']),
            'multilingue' => $x('market', '', 'Pages en plusieurs langues', 'Ta page d\'événement, le paiement et les billets en anglais, espagnol, allemand…', ['Langue choisie par l\'acheteur', 'Traduction de tes textes', 'Billets PDF traduits']),
            'calendrier' => $x('market', '', 'Rappels acheteurs', 'Ajout au calendrier en un clic et rappel la veille avec l\'adresse et les infos pratiques.', ['Fichier .ics et Google Agenda', 'E-mail J-1 personnalisable', 'Infos météo et accès']),
            // jour J
            'jauge-zone' => $x('onsite', '', 'Jauge par zone', 'Combien de personnes dans chaque zone (fosse, balcon, VIP) en temps réel, avec seuil d\'alerte.', ['Entrées et sorties par zone', 'Alerte de saturation', 'Écran à afficher au PC sécurité']),
            'badges-live' => $x('onsite', '', 'Badges imprimés à l\'arrivée', 'Pour les salons et congrès : le badge nominatif sort de l\'imprimante au moment du scan.', ['Imprimantes d\'étiquettes courantes', 'Modèle de badge personnalisable']),
            'kiosque' => $x('onsite', '', 'Borne libre-service', 'Une tablette en mode kiosque : les gens achètent ou retirent leur billet sans faire la queue.', ['Paiement par carte (terminal SumUp)', 'Impression du billet']),
            'vestiaire' => $x('onsite', '', 'Vestiaire', 'Tickets de vestiaire numérotés, payants ou non, retrouvés en scannant le billet.', ['Numéro lié au billet', 'Encaissement au guichet']),
            // argent
            'stripe-connect' => $x('money', '', 'Paiement direct (Stripe Connect)', 'L\'argent des ventes arrive directement sur le compte de l\'organisateur, la commission est prélevée au passage.', ['Plus besoin d\'attendre les reversements', 'Commission prélevée automatiquement', 'Vérification d\'identité gérée par Stripe']),
            'tva' => $x('money', '', 'TVA par tarif', 'Taux de TVA par tarif ou option (5,5 %, 10 %, 20 %), mentions sur les reçus et déclarations simplifiées.', ['Taux par tarif et par option', 'Récapitulatif de TVA collectée', 'Reçus conformes']),
            'export-compta' => $x('money', '', 'Export comptable', 'Ventes, remboursements, commissions et reversements exportés pour ton logiciel comptable.', ['FEC, Excel, CSV', 'Plan comptable personnalisable']),
            'rapport-fin' => $x('money', '', 'Rapport de fin d\'événement', 'Un PDF propre le lendemain : ventes, fréquentation, publics, canaux, comparaison avec l\'édition précédente.', ['Envoi automatique', 'Logo de l\'organisation', 'Parfait pour les partenaires']),
            'partage-stats' => $x('money', '', 'Partager les stats', 'Un lien en lecture seule pour un partenaire, une salle ou un financeur, sans lui donner accès au compte.', ['Choix des chiffres visibles', 'Lien révocable']),
            // équipe & organisation
            'double-auth' => $x('team', '', 'Double authentification', 'Un code envoyé par appli ou SMS en plus du mot de passe pour protéger les comptes.', ['Appli d\'authentification', 'Obligatoire pour les responsables (option)']),
            'roles-perso' => $x('team', '', 'Rôles personnalisés', 'Créer ses propres rôles : « billetterie seulement », « compta », « lecture seule »…', ['Droits page par page', 'Accès limité à certains événements']),
            'domaine-perso' => $x('team', '', 'Domaine personnalisé', 'Ta billetterie sur billetterie.ton-site.fr, avec tes couleurs.', ['Certificat SSL automatique', 'E-mails envoyés à ton nom']),
            'import-contacts' => $x('team', '', 'Import de contacts', 'Ramener ta liste d\'anciens participants (fichier Excel) pour tes campagnes, avec leur consentement.', ['Import CSV/Excel', 'Dédoublonnage automatique', 'Preuve du consentement']),
            'doublons' => $x('team', '', 'Fusion des doublons', 'Regrouper les contacts en double (même personne, e-mails différents).', ['Détection automatique', 'Fusion en un clic']),
            'saison' => $x('team', '', 'Saison & duplication en masse', 'Préparer toute une saison d\'un coup : dupliquer 20 dates avec tarifs et réglages.', ['Création par calendrier', 'Modèles d\'événements', 'Abonnement de saison relié']),
            // connecter
            'google-sheets' => $x('connect', '', 'Synchro Google Sheets', 'Les commandes et participants qui se remplissent tout seuls dans un tableur partagé.', ['Mise à jour en temps réel', 'Choix des colonnes']),
            'emailing-externe' => $x('connect', '', 'Mailchimp & Brevo', 'Envoi automatique des contacts consentants vers ton outil d\'e-mailing habituel.', ['Synchro des inscrits aux actus', 'Étiquettes par événement']),
            'app-orga' => $x('connect', '', 'Appli mobile organisateur', 'Ventes en direct, alertes, vente rapide et message express, sur ton téléphone (web ou APK Entrevo Pro).', ['Notifications : vente, palier, complet, remboursement, jauge', 'Vente rapide au guichet', 'Ouvrir ou clôturer les ventes']),
        ];
    }


    /** Fonctions à venir côté public (acheteurs et visiteurs du site). */
    /** Fonctions acheteurs déjà en ligne (1.8) : retirées des listes « bientôt ». */
    public const PUBLIC_LIVE = ['agenda', 'transfert', 'filtres-date', 'avis-public', 'echange', 'inviter-amis', 'compte-acheteur', 'cartes-cadeaux-public',
        'cashless-public', 'favoris', 'suivre-orga', 'recommandations', 'covoiturage', 'aide', 'ressources', 'demo-visio', 'migration', // 4.0
        'payer-a-plusieurs', 'wallet', 'paiement-mobile']; // 4.1

    public static function public(): array
    {
        return array_diff_key(self::publicAll(), array_flip(self::PUBLIC_LIVE));
    }

    private static function publicAll(): array
    {
        $x = fn(string $emoji, string $name, string $pitch, array $points = []) => ['emoji' => $emoji, 'name' => $name, 'pitch' => $pitch, 'points' => $points, 'status' => 'soon'];
        return [
            // trouver une sortie
            'carte' => $x('', 'Carte des événements', 'Tous les événements autour de toi sur une carte, pour trouver une sortie près de chez toi.', ['Géolocalisation (si tu l\'acceptes)', 'Filtre par distance', 'Itinéraire en un clic']),
            'filtres-date' => $x('', 'Ce soir, ce week-end…', 'Filtrer en un clic : ce soir, demain, ce week-end, cette semaine, gratuit.', ['Raccourcis de dates', 'Fourchette de prix', 'Événements gratuits']),
            'favoris' => $x('', 'Favoris', 'Mets un événement de côté et retrouve-le plus tard, avec une alerte quand les places partent.', ['Liste de favoris dans ton compte', 'Alerte « dernières places »', 'Rappel avant la fin des ventes']),
            'suivre-orga' => $x('', 'Suivre un organisateur', 'Sois prévenu dès que ta salle ou ton festival préféré met de nouvelles dates en vente.', ['Un clic sur la page de l\'organisateur', 'E-mail à chaque nouvelle date', 'Accès aux préventes']),
            'recommandations' => $x('', 'Recommandations', 'Des idées de sorties selon ce que tu as déjà aimé.', ['Basé sur tes achats et favoris', 'Désactivable à tout moment']),
            // acheter
            'payer-a-plusieurs' => $x('', 'Payer à plusieurs', 'Tu réserves pour la bande, chacun paie sa part avec son propre lien.', ['Places bloquées le temps que chacun paie', 'Relance automatique des retardataires', 'Billets envoyés à chacun']),
            'inviter-amis' => $x('', 'Inviter des amis', 'Partage l\'événement à tes potes et voyez qui vient.', ['Lien d\'invitation', 'Liste des amis qui ont pris leur place']),
            'paiement-mobile' => $x('', 'Apple Pay & Google Pay', 'Payer en deux secondes avec ton téléphone.', ['Sans saisir ta carte', 'Authentification par empreinte ou visage']),
            'cartes-cadeaux-public' => $x('', 'Cartes cadeaux', 'Offre une sortie : une carte cadeau utilisable sur tous les événements.', ['Montant libre', 'Carte PDF à imprimer ou e-mail programmé', 'Utilisable en plusieurs fois']),
            // le jour J
            'wallet' => $x('', 'Billet dans le Wallet', 'Ajoute ton billet à Apple Wallet ou Google Wallet : il s\'affiche tout seul le jour J.', ['Fonctionne sans réseau', 'Rappel sur l\'écran verrouillé', 'Mis à jour si l\'horaire change']),
            'agenda' => $x('', 'Ajouter à mon agenda', 'L\'événement dans ton calendrier en un clic, avec l\'adresse et un rappel.', ['Google, Apple, Outlook', 'Rappel la veille']),
            'transfert' => $x('', 'Donner mon billet à un ami', 'Tu ne peux plus y aller ? Transfère ton billet à quelqu\'un en deux clics.', ['Nouveau nom, nouveau QR code', 'L\'ancien est désactivé', 'Gratuit']),
            'echange' => $x('', 'Changer de date', 'Sur un événement à plusieurs dates, échange ton billet toi-même s\'il reste de la place.', ['Sans appeler l\'organisateur', 'Frais éventuels affichés avant']),
            'covoiturage' => $x('', 'Covoiturage', 'Trouve ou propose un trajet vers l\'événement avec d\'autres participants.', ['Trajets aller et retour', 'Entre participants uniquement']),
            'cashless-public' => $x('', 'Recharger mon cashless', 'Recharge ton bracelet ou ton QR de paiement avant d\'arriver, et récupère le solde après.', ['Recharge en ligne', 'Remboursement du solde restant']),
            // compte & services
            'compte-acheteur' => $x('', 'Compte acheteur', 'Tous tes billets au même endroit, même ceux achetés avec des adresses différentes.', ['Historique de tes sorties', 'Infos préremplies au paiement', 'Connexion sans mot de passe']),
            'avis-public' => $x('', 'Donner mon avis', 'Note l\'événement après coup et aide les autres à choisir.', ['Avis vérifiés (billet scanné)', 'Réponse de l\'organisateur']),
            'appli' => $x('', 'Appli mobile', 'L\'appli pour trouver des sorties, garder tes billets hors ligne et recevoir les alertes.', ['iPhone et Android', 'Billets disponibles sans réseau', 'Notifications des organisateurs suivis']),
            'aide' => $x('', 'Centre d\'aide', 'Toutes les réponses : billet perdu, remboursement, accès, accessibilité.', ['Questions fréquentes', 'Formulaire relié à ta commande', 'Suivi de ta demande']),
            'langues-public' => $x('', 'Español, Deutsch…', 'L\'anglais est disponible (lien English en bas de page) ; l\'espagnol et l\'allemand arrivent.', ['Langue choisie automatiquement', 'Billets PDF traduits']),
            // vitrine organisateurs
            'demo-visio' => $x('', 'Démo en visio', 'Une démo de 20 minutes avec l\'équipe pour voir comment lancer ta billetterie.', ['Créneau au choix', 'Questions sur tes besoins précis']),
            'ressources' => $x('', 'Guides & ressources', 'Des guides pratiques pour réussir tes événements : communication, sécurité, budget, bénévoles.', ['Modèles à télécharger', 'Retours d\'expérience d\'organisateurs']),
            'migration' => $x('', 'Migration depuis une autre billetterie', 'On importe tes événements, tes contacts et tes billets déjà vendus.', ['Import accompagné', 'Billets externes contrôlables au scan']),
        ];
    }

    public static function getPublic(string $key): ?array { $a = self::public(); return isset($a[$key]) ? $a[$key] + ['key' => $key] : null; }

    /** Chantiers côté super-admin (feuille de route de la plateforme). */
    public static function admin(): array
    {
        return [
            'stats-plateforme' => ['emoji' => '', 'name' => 'Statistiques de la plateforme', 'pitch' => 'Croissance, rétention des organisateurs, panier moyen, saisonnalité et prévisions de commissions.',
                'points' => ['Cohortes d\'organisateurs (qui revient vendre ?)', 'Volume et commissions par mois, prévision', 'Répartition par catégorie et par ville', 'Export pour ton expert-comptable']],
            'contrats' => ['emoji' => '', 'name' => 'Contrats & tarifs négociés', 'pitch' => 'Conditions particulières par organisateur : commission dégressive, forfait, durée, avenants.',
                'points' => ['Grilles de commission par palier de volume', 'Contrat signé en ligne', 'Date de fin et renouvellement', 'Historique des conditions']],
            'prospection' => ['emoji' => '', 'name' => 'Prospection', 'pitch' => 'Un mini-CRM pour suivre les organisateurs que tu démarches, jusqu\'à leur première vente.',
                'points' => ['Pipeline : contacté, démo, inscrit, première vente', 'Notes et rappels', 'Liens d\'inscription avec suivi', 'Offres de bienvenue']],
            'litiges' => ['emoji' => '', 'name' => 'Litiges & impayés', 'pitch' => 'Les contestations de paiement (chargebacks) remontées par Stripe, PayPal… et leur impact sur les soldes.',
                'points' => ['Liste des litiges ouverts et délais de réponse', 'Preuves automatiques (billet scanné, e-mails)', 'Retenue sur le solde de l\'organisateur', 'Suivi gagné / perdu']],
            'fraude' => ['emoji' => '', 'name' => 'Fraude & risques', 'pitch' => 'Repérer les achats suspects et les organisateurs à risque avant qu\'ils ne coûtent cher.',
                'points' => ['Alertes : rafales d\'achats, cartes multiples, pays inhabituels', 'Score de risque organisateur', 'Mise en attente des reversements', 'Liste noire']],
            'support' => ['emoji' => '', 'name' => 'Support', 'pitch' => 'Les demandes des organisateurs et des acheteurs dans une seule boîte, avec réponses types.',
                'points' => ['Formulaire de contact relié aux commandes', 'Réponses types', 'Délai de réponse et satisfaction', 'Base d\'aide en ligne']],
            'contenus' => ['emoji' => '', 'name' => 'Accueil & contenus', 'pitch' => 'Composer la page d\'accueil publique : mises en avant, bannières, catégories, pages d\'aide.',
                'points' => ['Événements mis en avant (sponsorisés ou coups de cœur)', 'Bannières et bandeaux', 'Pages libres (FAQ, à propos)', 'Référencement (titres, descriptions)']],
            'emails-modeles' => ['emoji' => '', 'name' => 'Modèles d\'e-mails', 'pitch' => 'Modifier les textes de tous les e-mails envoyés par la plateforme (confirmation, rappel, remboursement…).',
                'points' => ['Éditeur avec variables ({prenom}, {evenement}…)', 'Aperçu et e-mail de test', 'Logo et couleurs de la plateforme']],
            'marque-blanche' => ['emoji' => '', 'name' => 'Marque blanche', 'pitch' => 'Proposer la billetterie aux gros clients sous leur propre marque et leur propre nom de domaine.',
                'points' => ['Domaine personnalisé par organisateur', 'Logo, couleurs et e-mails à leur nom', 'Option payante']],
            'langues' => ['emoji' => '', 'name' => 'Langues', 'pitch' => 'Anglais disponible depuis la 4.2 (parcours d\'achat, e-mails, billets) ; espagnol, allemand… et interface organisateur à venir.',
                'points' => ['Choix de la langue par l\'acheteur', 'Traduction des pages et des e-mails', 'Billets PDF traduits']],
            'commission-paliers' => ['emoji' => '', 'name' => 'Commissions par palier', 'pitch' => 'Commission dégressive selon le volume, différente par catégorie d\'événement ou par type de billet.', 'points' => ['Paliers de volume mensuel', 'Taux par catégorie', 'Simulation avant application']],
            'formules' => ['emoji' => '', 'name' => 'Formules organisateurs', 'pitch' => 'Abonnements payants (Gratuit, Pro, Premium) qui débloquent des modules et baissent la commission.', 'points' => ['Paiement mensuel ou annuel', 'Modules réservés par formule', 'Période d\'essai']],
            'rgpd' => ['emoji' => '', 'name' => 'Demandes RGPD', 'pitch' => 'Export et suppression des données d\'une personne sur toute la plateforme, avec trace de la demande.', 'points' => ['Recherche par e-mail', 'Export ZIP', 'Anonymisation des commandes']],
            'connexion-sociale' => ['emoji' => '', 'name' => 'Connexion Google & Apple', 'pitch' => 'Se connecter sans mot de passe, pour les acheteurs comme pour les organisateurs.', 'points' => ['Google, Apple, lien magique par e-mail']],
            'audit-plateforme' => ['emoji' => '', 'name' => 'Journal du super-admin', 'pitch' => 'Toutes les actions des super-admins tracées : reversements, changements de commission, consultations d\'espaces.', 'points' => ['Qui, quoi, quand', 'Filtre par organisateur']],
            'alertes' => ['emoji' => '', 'name' => 'Alertes de supervision', 'pitch' => 'Être prévenu par SMS, Slack ou e-mail si le site tombe, si les paiements échouent ou si le cron s\'arrête.', 'points' => ['Seuils réglables', 'Plusieurs destinataires']],
            'delivrabilite' => ['emoji' => '', 'name' => 'Délivrabilité des e-mails', 'pitch' => 'Vérification SPF, DKIM et DMARC du domaine, taux de rebond et plaintes pour spam.', 'points' => ['Diagnostic DNS guidé', 'Liste des adresses en échec']],
            'maintenance' => ['emoji' => '', 'name' => 'Mode maintenance', 'pitch' => 'Mettre le site en pause proprement pendant une opération, avec une page d\'attente.', 'points' => ['Accès conservé pour les admins', 'Scanner toujours actif']],
            'revendeurs' => ['emoji' => '', 'name' => 'Réseau de revendeurs', 'pitch' => 'Des points de vente physiques (bars, disquaires, offices de tourisme) qui vendent les billets avec une commission.', 'points' => ['Compte revendeur', 'Commission par revendeur', 'Relevés mensuels']],
            'sauvegardes' => ['emoji' => '', 'name' => 'Sauvegardes automatiques', 'pitch' => 'Une sauvegarde quotidienne de la base et des images, gardée 30 jours, restaurable en un clic.',
                'points' => ['Sauvegarde chaque nuit', 'Copie vers un stockage externe (FTP, S3)', 'Restauration guidée', 'Alerte en cas d\'échec']],
        ];
    }

    public static function getAdmin(string $key): ?array { $a = self::admin(); return isset($a[$key]) ? $a[$key] + ['key' => $key, 'status' => 'soon', 'url' => '/pro/pc', 'cat' => ''] : null; }

    public static function get(string $key): ?array { $a = self::all(); return isset($a[$key]) ? $a[$key] + ['key' => $key] : null; }

    public static function votes(int $orgId): array
    {
        try { return array_column(all('SELECT feature FROM feature_votes WHERE organizer_id = ?', [$orgId]), 'feature', 'feature'); }
        catch (Throwable $e) { return []; }
    }

    public static function counts(): array
    {
        try { return array_column(all('SELECT feature, COUNT(*) n FROM feature_votes GROUP BY feature'), 'n', 'feature'); }
        catch (Throwable $e) { return []; }
    }
}
