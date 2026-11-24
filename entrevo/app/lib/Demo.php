<?php
declare(strict_types=1);

/** Données de démonstration (optionnelles à l'installation). */
final class Demo
{
    public const ORG_EMAIL = 'orga@demo.entrevo';
    public const SCAN_EMAIL = 'controle@demo.entrevo';
    public const PASSWORD = 'demo1234';

    public static function seed(string $contactEmail): void
    {
        $orgId = insert('organizers', [
            'name' => 'Association Arc-en-Scène', 'slug' => unique_slug('organizers', 'arc-en-scene'), 'email' => $contactEmail,
            'phone' => '04 00 00 00 00', 'address' => '12 rue des Lilas, 69000 Lyon', 'legal_name' => 'Association Arc-en-Scène',
            'description' => "Association culturelle qui organise concerts, festivals et rencontres depuis 2012.",
            'fee_mode' => 'buyer', 'status' => 'active', 'created_at' => now(),
        ]);
        $hash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        insert('users', ['organizer_id' => $orgId, 'email' => self::ORG_EMAIL, 'name' => 'Camille (démo)', 'password_hash' => $hash, 'role' => 'owner', 'active' => 1, 'created_at' => now()]);
        $scanner = insert('users', ['organizer_id' => $orgId, 'email' => self::SCAN_EMAIL, 'name' => 'Équipe accueil (démo)', 'password_hash' => $hash, 'role' => 'scanner', 'active' => 1, 'created_at' => now()]);

        $d = fn(int $days, string $time) => date('Y-m-d', strtotime("+$days days")) . ' ' . $time . ':00';

        // 1. Festival
        $fest = self::event($orgId, 'Festival Lumières d\'Été', $d(24, '16:00'), $d(26, '23:59'), 'Parc de la Feyssine', 'Avenue de la Feyssine', 'Villeurbanne', 3000,
            "Trois jours de musique en plein air : pop, électro et jazz sur deux scènes, food-trucks et village associatif.\n\nOuverture des portes à 16 h. Bracelet remis à l'entrée sur présentation du billet.", [255, 120, 60], [120, 40, 200]);
        $r1 = self::rate($fest, 'Pass 3 jours', 'Accès aux 3 soirées', 6900, 1500, 0);
        $r2 = self::rate($fest, 'Billet vendredi', null, 2900, 600, 1);
        $r3 = self::rate($fest, 'Billet samedi', null, 3200, 600, 2);
        $r4 = self::rate($fest, 'Tarif réduit (-18 ans, étudiants)', 'Justificatif demandé à l\'entrée', 2200, 300, 3);
        $r5 = self::rate($fest, 'Pass VIP', 'Espace VIP, bar dédié, accès backstage', 14900, 80, 4, true);
        insert('promos', ['event_id' => $fest, 'code' => 'ETE10', 'kind' => 'percent', 'value' => 10, 'max_uses' => 200, 'used' => 12, 'active' => 1]);
        insert('promos', ['event_id' => $fest, 'code' => 'VIPACCESS', 'kind' => 'unlock', 'value' => 0, 'rate_ids' => (string)$r5, 'max_uses' => 0, 'active' => 1]);
        insert('access_points', ['event_id' => $fest, 'name' => 'Entrée principale', 'rate_ids' => null, 'active' => 1]);
        insert('access_points', ['event_id' => $fest, 'name' => 'Espace VIP', 'rate_ids' => (string)$r5, 'active' => 1]);
        q('UPDATE events SET allow_reentry = 1 WHERE id = ?', [$fest]);

        // 2. Concert jazz
        $jazz = self::event($orgId, 'Soirée Jazz au Port', $d(9, '20:30'), $d(9, '23:30'), 'La Péniche Bleue', 'Quai Rambaud', 'Lyon', 180,
            "Un quartet acoustique pour une soirée intimiste sur l'eau. Placement libre, bar sur place.", [20, 60, 140], [0, 170, 190]);
        self::rate($jazz, 'Plein tarif', null, 1800, 0, 0);
        self::rate($jazz, 'Tarif adhérent', 'Sur présentation de la carte d\'adhérent', 1200, 0, 1);

        // 3. Course avec formulaire personnalisé
        $trail = self::event($orgId, 'Trail des Crêtes 2026', $d(40, '08:00'), $d(40, '14:00'), 'Place du village', '', 'Saint-Martin-en-Haut', 800,
            "Deux parcours nature de 12 km et 25 km. Retrait des dossards à partir de 7 h. Certificat médical ou licence obligatoire.", [30, 140, 80], [200, 220, 60]);
        self::rate($trail, 'Parcours 12 km', null, 1500, 400, 0);
        self::rate($trail, 'Parcours 25 km', null, 2500, 400, 1);
        insert('form_fields', ['event_id' => $trail, 'label' => 'Date de naissance', 'type' => 'date', 'required' => 1, 'per_ticket' => 1, 'sort' => 0]);
        insert('form_fields', ['event_id' => $trail, 'label' => 'Taille de t-shirt', 'type' => 'select', 'options' => "XS\nS\nM\nL\nXL", 'required' => 1, 'per_ticket' => 1, 'sort' => 1]);
        insert('form_fields', ['event_id' => $trail, 'label' => 'Club / licence', 'type' => 'text', 'required' => 0, 'per_ticket' => 1, 'sort' => 2]);

        // 4. Conférence gratuite
        $conf = self::event($orgId, 'Conférence : la musique à l\'ère numérique', $d(14, '18:30'), $d(14, '20:30'), 'Médiathèque centrale', '30 boulevard Vivier-Merle', 'Lyon', 120,
            "Rencontre gratuite sur inscription avec des artistes et des professionnels du spectacle.", [90, 70, 230], [230, 80, 160]);
        self::rate($conf, 'Entrée libre (sur inscription)', null, 0, 0, 0);

        // Ventes de démonstration
        $org = one('SELECT * FROM organizers WHERE id = ?', [$orgId]);
        $firsts = ['Léa', 'Hugo', 'Chloé', 'Lucas', 'Manon', 'Nathan', 'Emma', 'Louis', 'Inès', 'Jules', 'Sarah', 'Tom', 'Camille', 'Noah', 'Zoé', 'Arthur', 'Lina', 'Paul', 'Jade', 'Adam'];
        $lasts = ['Martin', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit', 'Durand', 'Leroy', 'Moreau', 'Simon', 'Laurent', 'Lefèvre', 'Michel', 'Garcia', 'Roux'];
        $evFest = one('SELECT * FROM events WHERE id = ?', [$fest]);
        $evJazz = one('SELECT * FROM events WHERE id = ?', [$jazz]);
        $plan = [[$evFest, [$r1 => [1, 2], $r2 => [1, 3], $r3 => [1, 2], $r4 => [1, 2]], 46], [$evJazz, null, 22]];
        foreach ($plan as [$ev, $choice, $n]) {
            $rates = all('SELECT id FROM rates WHERE event_id = ? AND hidden = 0', [$ev['id']]);
            for ($i = 0; $i < $n; $i++) {
                $fn = $firsts[array_rand($firsts)]; $ln = $lasts[array_rand($lasts)];
                $rid = (int)$rates[array_rand($rates)]['id'];
                $qty = random_int(1, 3);
                $r = Tickets::createOrder(['event' => $ev, 'org' => $org, 'quantities' => [$rid => $qty], 'method' => $i % 9 === 8 ? 'guichet' : 'online',
                    'pay_method' => 'cb', 'buyer' => ['first_name' => $fn, 'last_name' => $ln, 'email' => strtolower(self::ascii($fn . '.' . $ln)) . '@exemple.fr']]);
                if (empty($r['order'])) continue;
                $o = $r['order'];
                $when = date('Y-m-d H:i:s', time() - random_int(1, 20 * 86400));
                update('orders', ['provider' => $o['method'] === 'online' ? 'demo' : null, 'created_at' => $when], 'id = ?', [$o['id']]);
                Tickets::markPaid((int)$o['id']);
                update('orders', ['paid_at' => $when, 'marketing_optin' => random_int(0, 9) < 4 ? 1 : 0], 'id = ?', [$o['id']]);
            }
        }
        // audience de démonstration (pour que l'entonnoir et la conversion aient des données)
        foreach ([$fest => 140, $jazz => 60, $trail => 25, $conf => 15] as $evId => $base) {
            for ($i = 29; $i >= 0; $i--) {
                $v = (int)round($base * (0.5 + mt_rand(0, 100) / 100) * (1 + (29 - $i) / 30));
                q('INSERT INTO event_traffic (event_id, day, views, visitors, carts, checkouts) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE views = VALUES(views)',
                    [$evId, date('Y-m-d', strtotime("-$i days")), (int)($v * 1.6), $v, (int)($v * 0.22), (int)($v * 0.12)]);
            }
        }
        unset($scanner);
    }

    private static function ascii(string $s): string { return preg_replace('~[^a-zA-Z.]~', '', iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s); }

    private static function event(int $orgId, string $name, string $start, string $end, string $venue, string $address, string $city, int $cap, string $desc, array $c1, array $c2): int
    {
        return insert('events', [
            'organizer_id' => $orgId, 'slug' => unique_slug('events', $name), 'name' => $name, 'description' => $desc,
            'venue' => $venue, 'address' => $address, 'city' => $city, 'starts_at' => $start, 'ends_at' => $end,
            'image' => self::poster($name), 'capacity' => $cap, 'max_per_order' => 10, 'status' => 'published', 'created_at' => now(),
        ]);
    }

    private static function rate(int $ev, string $name, ?string $desc, int $price, int $quota, int $sort, bool $hidden = false): int
    {
        return insert('rates', ['event_id' => $ev, 'name' => $name, 'description' => $desc, 'price' => $price, 'quota' => $quota,
            'max_per_order' => 0, 'hidden' => $hidden ? 1 : 0, 'active' => 1, 'sort' => $sort]);
    }

    /** Copie l'affiche de démonstration correspondante (app/demo/*.jpg) dans uploads/. */
    private static function poster(string $name): ?string
    {
        $map = ['Festival' => 'fest', 'Jazz' => 'jazz', 'Trail' => 'trail', 'Conf' => 'conf'];
        foreach ($map as $needle => $file) {
            if (!str_contains($name, $needle)) continue;
            $src = APP . '/demo/' . $file . '.jpg';
            if (!is_file($src)) return null;
            $dir = ROOT . '/uploads/demo';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            copy($src, $dir . '/' . $file . '.jpg');
            return 'uploads/demo/' . $file . '.jpg';
        }
        return null;
    }
}
