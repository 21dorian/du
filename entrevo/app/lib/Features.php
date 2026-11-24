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
        'sell' => ['🎟️', 'Vendre'],
        'market' => ['📣', 'Faire venir du monde'],
        'onsite' => ['🚪', 'Le jour J'],
        'money' => ['💶', 'Argent & compta'],
        'team' => ['👥', 'Équipe & organisation'],
        'connect' => ['🔌', 'Connecter'],
    ];

    /** @return array<string, array{cat:string, emoji:string, name:string, status:string, url?:string, pitch:string, points?:array, eta?:string}> */
    public static function all(): array
    {
        return [
            // ---- Vendre
            'billetterie' => ['cat' => 'sell', 'emoji' => '🎟️', 'name' => 'Billetterie en ligne', 'status' => 'on', 'url' => '/pro/evenements', 'pitch' => 'Tarifs, quotas, dates multiples, prix libre, packs, options et dons.'],
            'guichet' => ['cat' => 'sell', 'emoji' => '💵', 'name' => 'Guichet sur place', 'status' => 'on', 'url' => '/pro/evenements', 'pitch' => 'Vente au comptoir, ticket thermique, clôture de caisse.'],
            'placement' => ['cat' => 'sell', 'emoji' => '💺', 'name' => 'Placement numéroté', 'status' => 'on', 'url' => '/pro/evenements', 'pitch' => 'Plan de salle 2D, choix des places par l\'acheteur.'],
            'revente' => ['cat' => 'sell', 'emoji' => '🔁', 'name' => 'Bourse aux billets', 'status' => 'on', 'url' => '/pro/evenements', 'pitch' => 'Revente officielle avec remboursement automatique du vendeur.'],
            'abonnements' => ['cat' => 'sell', 'emoji' => '🎫', 'name' => 'Abonnements & pass saison', 'status' => 'soon', 'eta' => 'Été 2027',
                'pitch' => 'Un pass qui ouvre plusieurs événements, des cartes 10 entrées, des abonnements annuels.',
                'points' => ['Pass multi-événements avec quota par événement', 'Cartes à entrées (5, 10…) décomptées au scan', 'Renouvellement annuel en un clic', 'Tarifs abonnés automatiques']],
            'boutique' => ['cat' => 'sell', 'emoji' => '🛍️', 'name' => 'Boutique & merch', 'status' => 'soon', 'eta' => 'Printemps 2027',
                'pitch' => 'Vends t-shirts, affiches et goodies avec ou sans billet, retrait sur place ou envoi.',
                'points' => ['Produits avec tailles et stocks', 'Retrait au stand avec QR code', 'Envoi postal avec frais de port', 'Ventes hors événement']],
            'cartes-cadeaux' => ['cat' => 'sell', 'emoji' => '🎁', 'name' => 'Cartes cadeaux', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Des bons d\'achat à offrir, utilisables sur tous tes événements.',
                'points' => ['Montant libre ou fixe', 'Carte PDF personnalisée', 'Solde utilisable en plusieurs fois']],
            'cashless' => ['cat' => 'sell', 'emoji' => '📱', 'name' => 'Cashless & bars', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Paiement sans espèces sur place : bracelet ou QR rechargeable, caisses bar et food.',
                'points' => ['Recharge en ligne avant l\'événement', 'Caisses tactiles sur téléphone', 'Remboursement du solde restant', 'Stats de consommation en direct']],

            // ---- Faire venir du monde
            'codes-promo' => ['cat' => 'market', 'emoji' => '🏷️', 'name' => 'Codes promo & invitations', 'status' => 'on', 'url' => '/pro/evenements', 'pitch' => 'Réductions, codes de déblocage, invitations en masse, partenaires.'],
            'widget' => ['cat' => 'market', 'emoji' => '🧩', 'name' => 'Widget pour ton site', 'status' => 'on', 'url' => '/pro/organisation', 'pitch' => 'La billetterie directement intégrée à ton site web.'],
            'suivi' => ['cat' => 'market', 'emoji' => '📊', 'name' => 'Suivi marketing', 'status' => 'on', 'url' => '/pro/organisation', 'pitch' => 'Google Analytics, Meta Pixel, liens UTM et entonnoir de conversion.'],
            'campagnes' => ['cat' => 'market', 'emoji' => '✉️', 'name' => 'Campagnes e-mail', 'status' => 'beta', 'url' => '/pro/campagnes',
                'pitch' => 'Préviens tes anciens participants de ton prochain événement : segments, éditeur, ouvertures, clics et ventes générées.'],
            'sms' => ['cat' => 'market', 'emoji' => '💬', 'name' => 'Campagnes SMS', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Un SMS la veille ou pour les dernières places : le canal le plus lu, en complément des e-mails.',
                'points' => ['Crédits SMS prépayés', 'Mêmes segments que les e-mails', 'Lien court suivi et ventes attribuées', 'Désinscription STOP automatique']],
            'mini-site' => ['cat' => 'market', 'emoji' => '🌐', 'name' => 'Mini-site d\'événement', 'status' => 'soon', 'eta' => 'Printemps 2027',
                'pitch' => 'Une vraie page de festival : programmation, artistes, plan, FAQ, partenaires, avec ton propre nom de domaine.',
                'points' => ['Blocs : line-up, horaires, plan d\'accès, FAQ', 'Thèmes de couleurs et polices', 'Nom de domaine personnalisé', 'Billetterie intégrée']],
            'affiliation' => ['cat' => 'market', 'emoji' => '🤝', 'name' => 'Ambassadeurs & affiliation', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Donne un lien perso à tes ambassadeurs et récompense ceux qui vendent le plus.',
                'points' => ['Lien de suivi par ambassadeur', 'Classement des meilleurs vendeurs', 'Récompenses : invitations, réductions, commissions']],
            'decouverte' => ['cat' => 'market', 'emoji' => '🧭', 'name' => 'Place de marché Entrevo', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Tes événements mis en avant auprès de tout le public de la plateforme, par ville et par thème.',
                'points' => ['Pages par ville et par catégorie', 'Recommandations personnalisées', 'Mise en avant sponsorisée']],
            'avis' => ['cat' => 'market', 'emoji' => '⭐', 'name' => 'Avis & questionnaire de satisfaction', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Un e-mail le lendemain pour récolter notes et avis, et les afficher sur tes prochaines pages.',
                'points' => ['Envoi automatique après l\'événement', 'Questions personnalisables', 'Note moyenne affichée sur ta page organisateur']],

            // ---- Le jour J
            'reseaux' => ['cat' => 'market', 'emoji' => '📲', 'name' => 'Réseaux sociaux', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Visuels prêts à poster générés depuis ton affiche, et publication programmée sur Instagram et Facebook.',
                'points' => ['Visuels carré, story et bannière en un clic', 'Compte à rebours « J-7 », « Dernières places » automatiques', 'Publication programmée', 'Lien suivi pour mesurer les ventes']],
            'controle' => ['cat' => 'onsite', 'emoji' => '📷', 'name' => 'Contrôle d\'accès', 'status' => 'on', 'url' => '/scan', 'pitch' => 'Appli de scan hors ligne, douchette, portes, zones, entrées/sorties.'],
            'accreditations' => ['cat' => 'onsite', 'emoji' => '🪪', 'name' => 'Accréditations & badges', 'status' => 'on', 'url' => '/pro/evenements', 'pitch' => 'Presse, artistes, prestataires : badges imprimables par zone.'],
            'controle-pro' => ['cat' => 'onsite', 'emoji' => '🛰️', 'name' => 'PC sécurité temps réel', 'status' => 'soon', 'eta' => 'Printemps 2027',
                'pitch' => 'Un écran de supervision : jauge par zone, flux par porte, alertes de saturation et appareils connectés.',
                'points' => ['Jauge en direct par zone', 'Débit par porte et prévision d\'attente', 'Alertes de saturation', 'Liste des appareils de scan et leur batterie']],
            'bracelets' => ['cat' => 'onsite', 'emoji' => '🎗️', 'name' => 'Bracelets RFID / NFC', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Échange billet → bracelet à l\'entrée, puis contrôle sans contact.',
                'points' => ['Association billet ↔ bracelet au comptoir', 'Lecture NFC sur téléphone Android', 'Compatible cashless']],
            'objets-trouves' => ['cat' => 'onsite', 'emoji' => '🧳', 'name' => 'Objets trouvés', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Les participants déclarent une perte, ton équipe enregistre les objets trouvés, la plateforme fait le lien.',
                'points' => ['Formulaire de déclaration lié au billet', 'Registre avec photo', 'Notification automatique en cas de correspondance']],

            // ---- Argent & compta
            'artistes' => ['cat' => 'onsite', 'emoji' => '🎤', 'name' => 'Artistes & planning', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'La programmation et la logistique artistes : horaires de passage, fiches techniques, loges, catering, invitations.',
                'points' => ['Feuille de route par artiste (arrivée, balance, passage)', 'Fiches techniques et riders en pièce jointe', 'Line-up publié automatiquement sur la page', 'Invitations artistes en un clic']],
            'finances' => ['cat' => 'money', 'emoji' => '💶', 'name' => 'Finances & reversements', 'status' => 'on', 'url' => '/pro/finances', 'pitch' => 'Solde, commissions, reversements et relevés.'],
            'factures' => ['cat' => 'money', 'emoji' => '🧾', 'name' => 'Factures & compta', 'status' => 'soon', 'eta' => 'Printemps 2027',
                'pitch' => 'Factures automatiques pour les acheteurs pro, exports comptables, TVA par tarif.',
                'points' => ['Facture PDF numérotée à la demande de l\'acheteur', 'Taux de TVA par tarif', 'Export FEC et grand livre', 'Rapprochement des reversements']],
            'devis' => ['cat' => 'money', 'emoji' => '📄', 'name' => 'Devis & ventes groupes', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Comités d\'entreprise, écoles, groupes : devis, bon de commande, paiement par virement.',
                'points' => ['Devis PDF en un clic', 'Paiement différé par virement', 'Billets nominatifs remplis par le groupe']],
            'subventions' => ['cat' => 'money', 'emoji' => '📑', 'name' => 'Bilans pour subventions', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Les chiffres que demandent les financeurs, prêts à copier : fréquentation, publics, tarifs sociaux.',
                'points' => ['Fréquentation par tranche d\'âge et ville', 'Part des tarifs réduits et gratuits', 'Export PDF du bilan']],

            // ---- Équipe & organisation
            'budget' => ['cat' => 'money', 'emoji' => '📒', 'name' => 'Budget prévisionnel', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Dépenses prévues contre recettes réelles : sais en direct si ton événement est rentable, et à partir de combien de billets.',
                'points' => ['Postes de dépenses (artistes, technique, sécurité…)', 'Seuil de rentabilité calculé', 'Recettes billetterie reliées automatiquement', 'Export pour ton trésorier']],
            'documents' => ['cat' => 'money', 'emoji' => '🗂️', 'name' => 'Documents & contrats', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Tous les papiers de l\'événement au même endroit : contrats, assurances, autorisations, devis, avec rappels d\'échéance.',
                'points' => ['Dossier par événement', 'Rappels avant échéance (assurance, préfecture, SACEM)', 'Signature électronique', 'Partage avec l\'équipe']],
            'equipe' => ['cat' => 'team', 'emoji' => '👥', 'name' => 'Équipe & droits', 'status' => 'on', 'url' => '/pro/equipe', 'pitch' => 'Responsables, gestionnaires, contrôleurs.'],
            'journal' => ['cat' => 'team', 'emoji' => '🕵️', 'name' => 'Journal d\'activité', 'status' => 'on', 'url' => '/pro/journal', 'pitch' => 'Qui a fait quoi, et quand.'],
            'benevoles' => ['cat' => 'team', 'emoji' => '🙋', 'name' => 'Staff & bénévoles', 'status' => 'soon', 'eta' => 'Printemps 2027',
                'pitch' => 'Recrute, planifie et pointe tes bénévoles : créneaux, postes, rappels et badge QR.',
                'points' => ['Annuaire réutilisable d\'un événement à l\'autre', 'Formulaire de candidature public', 'Planning par poste avec taux de remplissage', 'Espace perso pour chaque bénévole', 'Pointage par badge QR et heures réalisées']],
            'taches' => ['cat' => 'team', 'emoji' => '✅', 'name' => 'Tâches & rétroplanning', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'La to-do de ton événement : jalons, responsables, échéances et rappels.',
                'points' => ['Modèles de rétroplanning (festival, concert, salon)', 'Assignation à un membre de l\'équipe', 'Vue calendrier et kanban']],
            'multi-orga' => ['cat' => 'team', 'emoji' => '🏢', 'name' => 'Réseau multi-structures', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Fédérations, réseaux de salles : un compte parent qui voit les chiffres de toutes ses structures.',
                'points' => ['Tableau de bord consolidé', 'Droits par structure', 'Rapports communs']],

            // ---- Connecter
            'api' => ['cat' => 'connect', 'emoji' => '🔑', 'name' => 'API & webhooks', 'status' => 'on', 'url' => '/pro/organisation', 'pitch' => 'Accès à tes données et notifications temps réel pour tes outils.'],
            'import' => ['cat' => 'connect', 'emoji' => '📥', 'name' => 'Import de billets externes', 'status' => 'on', 'url' => '/pro/evenements', 'pitch' => 'Contrôle aussi les billets vendus ailleurs.'],
            'zapier' => ['cat' => 'connect', 'emoji' => '⚡', 'name' => 'Zapier, Make & n8n', 'status' => 'soon', 'eta' => '2027',
                'pitch' => 'Branche Entrevo à 5 000 applis sans coder : Google Sheets, Mailchimp, Slack…',
                'points' => ['Déclencheurs : nouvelle commande, nouveau scan', 'Actions : créer une invitation, un code promo']],
            'google-wallet' => ['cat' => 'connect', 'emoji' => '📲', 'name' => 'Billets Apple & Google Wallet', 'status' => 'soon', 'eta' => 'Printemps 2027',
                'pitch' => 'Le billet directement dans le portefeuille du téléphone, avec rappel automatique le jour J.',
                'points' => ['Bouton « Ajouter au Wallet » dans l\'e-mail', 'Mise à jour du billet en cas de changement', 'Rappel géolocalisé']],
        ];
    }

    /** Chantiers côté super-admin (feuille de route de la plateforme). */
    public static function admin(): array
    {
        return [
            'stats-plateforme' => ['emoji' => '📊', 'name' => 'Statistiques de la plateforme', 'pitch' => 'Croissance, rétention des organisateurs, panier moyen, saisonnalité et prévisions de commissions.',
                'points' => ['Cohortes d\'organisateurs (qui revient vendre ?)', 'Volume et commissions par mois, prévision', 'Répartition par catégorie et par ville', 'Export pour ton expert-comptable']],
            'kyc' => ['emoji' => '🪪', 'name' => 'Vérification KYC', 'pitch' => 'Collecte et contrôle des justificatifs des organisateurs avant les premiers reversements.',
                'points' => ['Dépôt Kbis / statuts / pièce d\'identité / RIB', 'Statut de vérification et date d\'expiration', 'Blocage des reversements tant que ce n\'est pas validé', 'Relances automatiques']],
            'contrats' => ['emoji' => '🤝', 'name' => 'Contrats & tarifs négociés', 'pitch' => 'Conditions particulières par organisateur : commission dégressive, forfait, durée, avenants.',
                'points' => ['Grilles de commission par palier de volume', 'Contrat signé en ligne', 'Date de fin et renouvellement', 'Historique des conditions']],
            'prospection' => ['emoji' => '🎯', 'name' => 'Prospection', 'pitch' => 'Un mini-CRM pour suivre les organisateurs que tu démarches, jusqu\'à leur première vente.',
                'points' => ['Pipeline : contacté, démo, inscrit, première vente', 'Notes et rappels', 'Liens d\'inscription avec suivi', 'Offres de bienvenue']],
            'litiges' => ['emoji' => '⚖️', 'name' => 'Litiges & impayés', 'pitch' => 'Les contestations de paiement (chargebacks) remontées par Stripe, PayPal… et leur impact sur les soldes.',
                'points' => ['Liste des litiges ouverts et délais de réponse', 'Preuves automatiques (billet scanné, e-mails)', 'Retenue sur le solde de l\'organisateur', 'Suivi gagné / perdu']],
            'factures-commission' => ['emoji' => '🧾', 'name' => 'Factures de commission', 'pitch' => 'Ta facture mensuelle de commission à chaque organisateur, numérotée, avec TVA, envoyée automatiquement.',
                'points' => ['Numérotation légale continue', 'TVA et mentions obligatoires', 'Envoi par e-mail et archive', 'Export comptable']],
            'fraude' => ['emoji' => '🛡️', 'name' => 'Fraude & risques', 'pitch' => 'Repérer les achats suspects et les organisateurs à risque avant qu\'ils ne coûtent cher.',
                'points' => ['Alertes : rafales d\'achats, cartes multiples, pays inhabituels', 'Score de risque organisateur', 'Mise en attente des reversements', 'Liste noire']],
            'support' => ['emoji' => '💬', 'name' => 'Support', 'pitch' => 'Les demandes des organisateurs et des acheteurs dans une seule boîte, avec réponses types.',
                'points' => ['Formulaire de contact relié aux commandes', 'Réponses types', 'Délai de réponse et satisfaction', 'Base d\'aide en ligne']],
            'contenus' => ['emoji' => '🏠', 'name' => 'Accueil & contenus', 'pitch' => 'Composer la page d\'accueil publique : mises en avant, bannières, catégories, pages d\'aide.',
                'points' => ['Événements mis en avant (sponsorisés ou coups de cœur)', 'Bannières et bandeaux', 'Pages libres (FAQ, à propos)', 'Référencement (titres, descriptions)']],
            'emails-modeles' => ['emoji' => '✉️', 'name' => 'Modèles d\'e-mails', 'pitch' => 'Modifier les textes de tous les e-mails envoyés par la plateforme (confirmation, rappel, remboursement…).',
                'points' => ['Éditeur avec variables ({prenom}, {evenement}…)', 'Aperçu et e-mail de test', 'Logo et couleurs de la plateforme']],
            'marque-blanche' => ['emoji' => '🎨', 'name' => 'Marque blanche', 'pitch' => 'Proposer la billetterie aux gros clients sous leur propre marque et leur propre nom de domaine.',
                'points' => ['Domaine personnalisé par organisateur', 'Logo, couleurs et e-mails à leur nom', 'Option payante']],
            'langues' => ['emoji' => '🌍', 'name' => 'Langues', 'pitch' => 'Billetterie en anglais, espagnol, allemand… pour les festivals qui accueillent des touristes.',
                'points' => ['Choix de la langue par l\'acheteur', 'Traduction des pages et des e-mails', 'Billets PDF traduits']],
            'sauvegardes' => ['emoji' => '💾', 'name' => 'Sauvegardes automatiques', 'pitch' => 'Une sauvegarde quotidienne de la base et des images, gardée 30 jours, restaurable en un clic.',
                'points' => ['Sauvegarde chaque nuit', 'Copie vers un stockage externe (FTP, S3)', 'Restauration guidée', 'Alerte en cas d\'échec']],
        ];
    }

    public static function getAdmin(string $key): ?array { $a = self::admin(); return isset($a[$key]) ? $a[$key] + ['key' => $key, 'status' => 'soon', 'cat' => ''] : null; }

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
