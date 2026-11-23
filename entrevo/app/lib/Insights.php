<?php
declare(strict_types=1);

/** Calculs des tableaux de bord : périodes, comparaisons, séries, activité, notifications, recherche. */
final class Insights
{
    public const PERIODS = [7 => '7 jours', 30 => '30 jours', 90 => '90 jours', 365 => '12 mois'];
    private const REAL = "o.status = 'paid' AND (o.channel IS NULL OR o.channel <> 'systeme')";

    public static function period(int $days): array
    {
        $days = isset(self::PERIODS[$days]) ? $days : 30;
        $to = date('Y-m-d 23:59:59');
        $from = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
        $pTo = date('Y-m-d 23:59:59', strtotime($from . ' -1 day'));
        $pFrom = date('Y-m-d 00:00:00', strtotime($from . ' -' . $days . ' days'));
        return compact('days', 'from', 'to', 'pFrom', 'pTo');
    }

    /** Totaux d'une fenêtre (filtre SQL sur o.organizer_id ou o.event_id). */
    public static function totals(string $where, array $params, string $from, string $to): array
    {
        $r = one("SELECT COUNT(*) n, COALESCE(SUM(o.total),0) total, COALESCE(SUM(CASE WHEN o.method IN ('online','partner') THEN o.organizer_amount ELSE o.total END),0) net
                  FROM orders o WHERE $where AND " . self::REAL . ' AND o.paid_at BETWEEN ? AND ?', array_merge($params, [$from, $to]));
        $t = (int)val("SELECT COUNT(*) FROM tickets t JOIN orders o ON o.id = t.order_id WHERE $where AND " . self::REAL . " AND t.type IN ('ticket','invitation') AND t.status = 'valid' AND o.paid_at BETWEEN ? AND ?",
            array_merge($params, [$from, $to]));
        return ['orders' => (int)$r['n'], 'revenue' => (int)$r['total'], 'net' => (int)$r['net'], 'tickets' => $t];
    }

    /** Série quotidienne (ou mensuelle si > 120 jours) : montant, commandes, billets. */
    public static function series(string $where, array $params, string $from, string $to): array
    {
        $monthly = (strtotime($to) - strtotime($from)) / 86400 > 120;
        $fmt = $monthly ? '%Y-%m' : '%Y-%m-%d';
        $rows = all("SELECT DATE_FORMAT(o.paid_at, '$fmt') k, COUNT(*) n, SUM(o.total) t
                     FROM orders o WHERE $where AND " . self::REAL . " AND o.paid_at BETWEEN ? AND ? GROUP BY k", array_merge($params, [$from, $to]));
        $map = [];
        foreach ($rows as $r) $map[$r['k']] = $r;
        $out = ['keys' => [], 'labels' => [], 'revenue' => [], 'orders' => []];
        $mois = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        for ($d = strtotime(substr($from, 0, 10)); $d <= strtotime($to); $d = $monthly ? strtotime('+1 month', $d) : $d + 86400) {
            $k = date($monthly ? 'Y-m' : 'Y-m-d', $d);
            if ($monthly && in_array($k, $out['keys'], true)) continue;
            $out['keys'][] = $k;
            $out['labels'][] = $monthly ? $mois[(int)date('n', $d)] . ' ' . date('y', $d) : date('d/m', $d);
            $out['revenue'][] = (int)($map[$k]['t'] ?? 0);
            $out['orders'][] = (int)($map[$k]['n'] ?? 0);
        }
        return $out;
    }

    /** Ventes cumulées d'un événement depuis la première vente (pour la courbe de remplissage). */
    public static function cumulative(int $eventId): array
    {
        $rows = all("SELECT DATE(o.paid_at) d, COUNT(t.id) q FROM tickets t JOIN orders o ON o.id = t.order_id
                     WHERE t.event_id = ? AND t.status = 'valid' AND t.type IN ('ticket','invitation') AND " . self::REAL . ' GROUP BY DATE(o.paid_at) ORDER BY d', [$eventId]);
        if (!$rows) return ['labels' => [], 'values' => []];
        $map = array_column($rows, 'q', 'd');
        $labels = []; $values = []; $sum = 0;
        for ($d = strtotime($rows[0]['d']); $d <= strtotime(date('Y-m-d')); $d += 86400) {
            $sum += (int)($map[date('Y-m-d', $d)] ?? 0);
            $labels[] = date('d/m', $d);
            $values[] = $sum;
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /** Flux d'activité récente (ventes, remboursements, liste d'attente, revente, demandes). */
    public static function activity(int $orgId, ?int $eventId = null, int $limit = 12): array
    {
        $ev = $eventId ? ' AND o.event_id = ' . (int)$eventId : '';
        $items = [];
        foreach (all("SELECT o.id, o.ref, o.first_name, o.last_name, o.total, o.method, o.status, o.paid_at at, o.cancelled_at, e.name ev,
                      (SELECT COUNT(*) FROM tickets t WHERE t.order_id = o.id) n FROM orders o JOIN events e ON e.id = o.event_id
                      WHERE o.organizer_id = ? AND o.status IN ('paid','refunded','cancelled') AND (o.channel IS NULL OR o.channel <> 'systeme')$ev ORDER BY o.id DESC LIMIT $limit", [$orgId]) as $o) {
            $icon = ['guichet' => '💵', 'invitation' => '🎁', 'partner' => '🤝'][$o['method']] ?? '🎟️';
            $items[] = ['at' => $o['status'] === 'paid' ? $o['at'] : ($o['cancelled_at'] ?: $o['at']), 'icon' => $o['status'] === 'paid' ? $icon : '↩️',
                'title' => trim($o['first_name'] . ' ' . $o['last_name']) . ($o['status'] === 'paid' ? ' · ' . $o['n'] . ' billet' . ($o['n'] > 1 ? 's' : '') : ' · ' . ($o['status'] === 'refunded' ? 'remboursé' : 'annulé')),
                'sub' => $o['ev'], 'amount' => $o['status'] === 'paid' ? (int)$o['total'] : -(int)$o['total'], 'url' => url('/pro/commandes/' . $o['id'])];
        }
        $evw = $eventId ? ' AND w.event_id = ' . (int)$eventId : '';
        foreach (all("SELECT w.*, e.name ev FROM waitlist w JOIN events e ON e.id = w.event_id WHERE e.organizer_id = ?$evw ORDER BY w.id DESC LIMIT 5", [$orgId]) as $w) {
            $items[] = ['at' => $w['created_at'], 'icon' => '⏳', 'title' => ($w['first_name'] ?: $w['email']) . ' · liste d\'attente', 'sub' => $w['ev'], 'amount' => null, 'url' => url('/pro/evenements/' . $w['event_id'] . '/attente')];
        }
        foreach (all("SELECT x.*, e.name ev FROM resales x JOIN events e ON e.id = x.event_id WHERE e.organizer_id = ?" . ($eventId ? ' AND x.event_id = ' . (int)$eventId : '') . ' ORDER BY x.id DESC LIMIT 5', [$orgId]) as $x) {
            $items[] = ['at' => $x['sold_at'] ?: $x['created_at'], 'icon' => '🔁', 'title' => $x['status'] === 'sold' ? 'Billet revendu' : 'Billet mis en revente', 'sub' => $x['ev'], 'amount' => null, 'url' => url('/pro/evenements/' . $x['event_id'] . '/attente')];
        }
        usort($items, fn($a, $b) => strcmp((string)$b['at'], (string)$a['at']));
        return array_slice($items, 0, $limit);
    }

    /** Notifications à traiter. */
    public static function notifications(?array $org, bool $super): array
    {
        $n = [];
        if ($org) {
            $id = (int)$org['id'];
            foreach (all("SELECT o.id, o.ref, o.first_name, o.last_name FROM orders o WHERE o.organizer_id = ? AND o.status = 'paid' AND o.refund_requested_at IS NOT NULL ORDER BY o.refund_requested_at DESC LIMIT 5", [$id]) as $o)
                $n[] = ['level' => 'warn', 'title' => '💸 Demande de remboursement ' . $o['ref'], 'sub' => trim($o['first_name'] . ' ' . $o['last_name']), 'url' => url('/pro/commandes/' . $o['id'])];
            foreach (all("SELECT e.id, e.name, e.capacity, (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'valid' AND t.type IN ('ticket','invitation')) sold
                          FROM events e WHERE e.organizer_id = ? AND e.status = 'published' AND e.capacity > 0 AND COALESCE(e.ends_at, e.starts_at) > NOW()", [$id]) as $e) {
                $p = $e['sold'] / $e['capacity'] * 100;
                if ($p >= 90) $n[] = ['level' => $p >= 100 ? 'bad' : 'warn', 'title' => ($p >= 100 ? '🫠 Complet : ' : '🔥 Presque complet : ') . $e['name'], 'sub' => $e['sold'] . ' / ' . $e['capacity'] . ' billets', 'url' => url('/pro/evenements/' . $e['id'])];
            }
            foreach (all("SELECT p.name, p.stock, p.event_id, (SELECT COALESCE(SUM(i.qty),0) FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.product_id = p.id AND o.status = 'paid') sold
                          FROM products p JOIN events e ON e.id = p.event_id WHERE e.organizer_id = ? AND p.active = 1 AND p.stock > 0", [$id]) as $p)
                if ($p['stock'] - $p['sold'] <= max(3, $p['stock'] * 0.1)) $n[] = ['level' => 'warn', 'title' => '📦 Stock bas : ' . $p['name'], 'sub' => 'reste ' . max(0, $p['stock'] - $p['sold']), 'url' => url('/pro/evenements/' . $p['event_id'] . '/options')];
            $w = (int)val("SELECT COUNT(*) FROM waitlist w JOIN events e ON e.id = w.event_id WHERE e.organizer_id = ? AND w.status = 'waiting'", [$id]);
            if ($w) $n[] = ['level' => '', 'title' => '⏳ ' . $w . ' personne(s) en liste d\'attente', 'sub' => 'Augmente la jauge ou libère des places', 'url' => url('/pro/evenements')];
            foreach (all("SELECT id, name FROM events WHERE organizer_id = ? AND status = 'draft' AND starts_at BETWEEN NOW() AND NOW() + INTERVAL 30 DAY AND publish_at IS NULL", [$id]) as $e)
                $n[] = ['level' => '', 'title' => '👀 Pas encore publié : ' . $e['name'], 'sub' => 'L\'événement approche', 'url' => url('/pro/evenements/' . $e['id'])];
            if (!$org['iban'] && val("SELECT id FROM orders WHERE organizer_id = ? AND status = 'paid' AND method = 'online' AND total > 0 LIMIT 1", [$id]))
                $n[] = ['level' => 'warn', 'title' => '🏦 Ajoute ton IBAN', 'sub' => 'Pour recevoir tes reversements', 'url' => url('/pro/organisation')];
        }
        if ($super) {
            foreach (all("SELECT x.id, x.event_id, x.refund_info FROM resales x WHERE x.refund_status = 'failed' LIMIT 5") as $x)
                $n[] = ['level' => 'bad', 'title' => '⚠️ Remboursement vendeur échoué (revente)', 'sub' => mb_strimwidth((string)$x['refund_info'], 0, 80, '…'), 'url' => url('/admin/commandes')];
            $new = (int)val('SELECT COUNT(*) FROM organizers WHERE created_at > NOW() - INTERVAL 7 DAY');
            if ($new) $n[] = ['level' => '', 'title' => '👋 ' . $new . ' nouvel(s) organisateur(s) cette semaine', 'sub' => '', 'url' => url('/admin/organisateurs')];
            if (array_keys(Payments::available()) === ['demo'] || !Payments::available()) $n[] = ['level' => 'warn', 'title' => '🧪 Paiement de démo seulement', 'sub' => 'Branche un vrai prestataire', 'url' => url('/admin/reglages?tab=payments')];
        }
        return $n;
    }

    /** Recherche globale. */
    public static function search(string $q, ?array $org, bool $super): array
    {
        $like = '%' . $q . '%';
        $groups = [];
        $scope = $org ? ' AND e.organizer_id = ' . (int)$org['id'] : ($super ? '' : ' AND 0');
        $ev = all("SELECT e.id, e.name, e.starts_at, e.status FROM events e WHERE e.name LIKE ?$scope ORDER BY e.starts_at DESC LIMIT 6", [$like]);
        if ($ev) $groups[] = ['label' => 'Événements', 'items' => array_map(fn($e) => ['title' => $e['name'], 'sub' => fr_date($e['starts_at'], false), 'url' => url('/pro/evenements/' . $e['id'])], $ev)];
        if ($org) {
            $or = all("SELECT o.id, o.ref, o.first_name, o.last_name, o.email, o.total, e.name ev FROM orders o JOIN events e ON e.id = o.event_id
                       WHERE o.organizer_id = ? AND (o.channel IS NULL OR o.channel <> 'systeme') AND (o.ref LIKE ? OR o.email LIKE ? OR CONCAT(o.first_name, ' ', o.last_name) LIKE ?) ORDER BY o.id DESC LIMIT 6", [$org['id'], $like, $like, $like]);
            if ($or) $groups[] = ['label' => 'Commandes', 'items' => array_map(fn($o) => ['title' => $o['ref'] . ' · ' . trim($o['first_name'] . ' ' . $o['last_name']), 'sub' => $o['ev'] . ' · ' . money((int)$o['total']), 'url' => url('/pro/commandes/' . $o['id'])], $or)];
            $tk = all("SELECT t.order_id, t.code, t.first_name, t.last_name, t.rate_name, e.name ev FROM tickets t JOIN events e ON e.id = t.event_id
                       WHERE e.organizer_id = ? AND (t.code LIKE ? OR CONCAT(t.first_name, ' ', t.last_name) LIKE ? OR t.company LIKE ?) ORDER BY t.id DESC LIMIT 6", [$org['id'], $like, $like, $like]);
            if ($tk) $groups[] = ['label' => 'Participants & billets', 'items' => array_map(fn($t) => ['title' => trim($t['first_name'] . ' ' . $t['last_name']) . ' · ' . $t['code'], 'sub' => $t['ev'] . ' · ' . $t['rate_name'], 'url' => url('/pro/commandes/' . $t['order_id'])], $tk)];
        }
        if ($super) {
            $og = all('SELECT id, name, email FROM organizers WHERE name LIKE ? OR email LIKE ? LIMIT 6', [$like, $like]);
            if ($og) $groups[] = ['label' => 'Organisateurs', 'items' => array_map(fn($o) => ['title' => $o['name'], 'sub' => $o['email'], 'url' => url('/admin/organisateurs/' . $o['id'])], $og)];
            $or = all("SELECT o.ref, o.first_name, o.last_name, o.total, o.organizer_id FROM orders o WHERE o.ref LIKE ? OR o.email LIKE ? ORDER BY o.id DESC LIMIT 6", [$like, $like]);
            if ($or) $groups[] = ['label' => 'Commandes (toutes)', 'items' => array_map(fn($o) => ['title' => $o['ref'] . ' · ' . trim($o['first_name'] . ' ' . $o['last_name']), 'sub' => money((int)$o['total']), 'url' => url('/admin/commandes', ['q' => $o['ref']])], $or)];
        }
        // raccourcis de navigation
        $nav = $org ? ['Tableau de bord' => '/pro', 'Événements' => '/pro/evenements', 'Commandes' => '/pro/commandes', 'Finances' => '/pro/finances', 'Équipe' => '/pro/equipe', 'Mon organisation' => '/pro/organisation', 'Journal d\'activité' => '/pro/journal', 'Nouvel événement' => '/pro/evenements/nouveau', 'Participants' => '/pro/participants', 'Tous les modules' => '/pro/modules']
            : ['Réglages' => '/admin/reglages', 'Mises à jour' => '/admin/mises-a-jour', 'Organisateurs' => '/admin/organisateurs'];
        $go = [];
        foreach ($nav as $l => $u) if (stripos(iconv('UTF-8', 'ASCII//TRANSLIT', $l) ?: $l, iconv('UTF-8', 'ASCII//TRANSLIT', $q) ?: $q) !== false) $go[] = ['title' => '→ ' . $l, 'sub' => '', 'url' => url($u)];
        if ($go) array_unshift($groups, ['label' => 'Aller à', 'items' => $go]);
        if ($org) {
            $norm = fn($x) => mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $x) ?: $x);
            $mods = [];
            foreach (Features::all() as $k => $f) if (str_contains($norm($f['name'] . ' ' . $f['pitch']), $norm($q)))
                $mods[] = ['title' => $f['emoji'] . ' ' . $f['name'], 'sub' => $f['status'] === 'soon' ? 'Bientôt' : 'Module actif', 'url' => url($f['status'] === 'soon' ? '/pro/bientot/' . $k : $f['url'])];
            if ($mods) $groups[] = ['label' => 'Modules', 'items' => array_slice($mods, 0, 5)];
        }
        return $groups;
    }
}
