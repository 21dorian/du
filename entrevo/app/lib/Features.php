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
            'campagnes' => ['cat' => 'market', 'emoji' => '✉️', 'name' => 'Campagnes e-mail & SMS', 'status' => 'soon', 'eta' => 'Printemps 2027',
                'pitch' => 'Préviens tes anciens participants de ton prochain événement, avec un vrai éditeur de newsletter.',
                'points' => ['Base contacts avec consentement RGPD', 'Segments : déjà venus, VIP, ville…', 'Éditeur glisser-déposer', 'Statistiques d\'ouverture et de ventes générées', 'Envoi SMS en option']],
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
