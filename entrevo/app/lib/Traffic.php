<?php
declare(strict_types=1);

/** Mesure d'audience des pages événement (vues, visiteurs, paniers, passages au paiement) pour l'entonnoir de conversion. */
final class Traffic
{
    public static function hit(array $ev, string $what): void
    {
        if (!in_array($what, ['views', 'carts', 'checkouts'], true)) return;
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ua === '' || preg_match('~bot|crawl|spider|slurp|preview|facebookexternalhit|headless~i', $ua)) return;
        $u = user();
        if ($u && ($u['role'] === 'superadmin' || (int)$u['organizer_id'] === (int)$ev['organizer_id'])) return;
        $day = date('Y-m-d');
        $visitor = 0;
        if ($what === 'views' && empty($_SESSION['seen'][$ev['id']][$day])) { $_SESSION['seen'][$ev['id']][$day] = 1; $visitor = 1; }
        if ($what !== 'views') { // une seule fois par visiteur et par jour
            if (!empty($_SESSION['funnel'][$ev['id']][$day][$what])) return;
            $_SESSION['funnel'][$ev['id']][$day][$what] = 1;
        }
        try {
            q("INSERT INTO event_traffic (event_id, day, views, visitors, carts, checkouts) VALUES (?, ?, ?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE views = views + VALUES(views), visitors = visitors + VALUES(visitors), carts = carts + VALUES(carts), checkouts = checkouts + VALUES(checkouts)",
                [$ev['id'], $day, $what === 'views' ? 1 : 0, $visitor, $what === 'carts' ? 1 : 0, $what === 'checkouts' ? 1 : 0]);
        } catch (Throwable $e) { /* la mesure d'audience ne doit jamais bloquer une vente */ }
    }

    /** Entonnoir sur une période : visiteurs → paniers → paiement → payées. */
    public static function funnel(string $where, array $params, string $from, string $to): array
    {
        $t = one("SELECT COALESCE(SUM(views),0) views, COALESCE(SUM(visitors),0) visitors, COALESCE(SUM(carts),0) carts, COALESCE(SUM(checkouts),0) checkouts
                  FROM event_traffic WHERE $where AND day BETWEEN ? AND ?", array_merge($params, [substr($from, 0, 10), substr($to, 0, 10)]));
        $whereO = str_replace('event_id', 'o.event_id', $where);
        $o = one("SELECT COUNT(*) n, SUM(o.status = 'paid') paid FROM orders o WHERE $whereO AND o.method IN ('online','partner') AND o.created_at BETWEEN ? AND ?", array_merge($params, [$from, $to]));
        $paid = (int)$o['paid'];
        $visitors = (int)$t['visitors'];
        return ['views' => (int)$t['views'], 'visitors' => $visitors, 'carts' => (int)$t['carts'], 'checkouts' => (int)$t['checkouts'], 'orders' => (int)$o['n'],
            'paid' => $paid, 'abandoned' => max(0, (int)$t['carts'] - $paid), 'rate' => $visitors ? round($paid / $visitors * 100, 1) : null];
    }
}
