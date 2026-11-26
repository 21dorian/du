<?php
declare(strict_types=1);

/**
 * 3.2 : fidélité et CRM festivaliers. Une fiche par e-mail qui réunit billets, présence et dépenses aux bars (cashless),
 * des points calculés à partir de ces données, des niveaux et des offres ciblées (codes promo par segment).
 */
final class Loyalty
{
    public const DEFAULTS = ['enabled' => 1, 'pts_ticket' => 1, 'pts_bar' => 1, 'pts_visit' => 50,
        'tiers' => [['name' => 'Argent', 'min' => 300, 'pct' => 5], ['name' => 'Or', 'min' => 1000, 'pct' => 10], ['name' => 'Platine', 'min' => 3000, 'pct' => 15]]];
    public const SEGMENTS = ['' => 'Tous', 'tier' => 'Membres d\'un niveau', 'fideles' => 'Fidèles (2 événements et +)', 'bar' => 'Gros consommateurs aux bars',
        'absents' => 'Ont acheté sans venir', 'nouveaux' => 'Nouveaux (90 jours)', 'dormants' => 'Dormants (plus d\'un an)'];

    public static function settings(array $org): array
    {
        $j = json_decode((string)($org['loyalty_json'] ?? ''), true);
        $s = (is_array($j) ? $j : []) + self::DEFAULTS;
        usort($s['tiers'], fn($a, $b) => $a['min'] <=> $b['min']);
        return $s;
    }

    public static function tier(array $s, int $pts): ?array
    {
        $t = null; foreach ($s['tiers'] as $x) if ($pts >= (int)$x['min']) $t = $x;
        return $t;
    }

    public static function nextTier(array $s, int $pts): ?array
    {
        foreach ($s['tiers'] as $x) if ($pts < (int)$x['min']) return $x;
        return null;
    }

    /** Tous les festivaliers de l'organisateur avec leurs indicateurs, triés par points. */
    public static function members(array $org): array
    {
        $s = self::settings($org);
        $rows = all("SELECT LOWER(o.email) email, SUBSTRING_INDEX(GROUP_CONCAT(o.first_name ORDER BY o.created_at DESC SEPARATOR '\x1f'), '\x1f', 1) first_name,
            SUBSTRING_INDEX(GROUP_CONCAT(o.last_name ORDER BY o.created_at DESC SEPARATOR '\x1f'), '\x1f', 1) last_name, MAX(o.phone) phone, MAX(o.marketing_optin) optin,
            COUNT(*) orders, COUNT(DISTINCT o.event_id) events, SUM(o.total - o.refunded_amount) spent, MIN(o.created_at) first_at, MAX(o.created_at) last_at,
            COALESCE(SUM(tk.valid), 0) tickets, COALESCE(SUM(tk.came), 0) came
            FROM orders o LEFT JOIN (SELECT order_id, SUM(status = 'valid') valid, SUM(status = 'valid' AND entries > 0) came FROM tickets GROUP BY order_id) tk ON tk.order_id = o.id
            WHERE o.organizer_id = ? AND o.status = 'paid' AND o.email IS NOT NULL AND o.email <> '' AND (o.channel IS NULL OR o.channel <> 'systeme') GROUP BY LOWER(o.email)", [$org['id']]);
        $visits = [];
        foreach (all("SELECT LOWER(o.email) em, COUNT(DISTINCT t.event_id) n FROM tickets t JOIN orders o ON o.id = t.order_id WHERE o.organizer_id = ? AND t.status = 'valid' AND t.entries > 0 GROUP BY LOWER(o.email)", [$org['id']]) as $v) $visits[$v['em']] = (int)$v['n'];
        $bar = self::barSpend((int)$org['id']);
        $out = [];
        foreach ($rows as $r) {
            $em = $r['email']; $b = $bar[$em] ?? ['t' => 0, 'n' => 0];
            $v = $visits[$em] ?? 0;
            $pts = (int)floor(max(0, (int)$r['spent']) / 100 * (float)$s['pts_ticket']) + (int)floor(max(0, $b['t']) / 100 * (float)$s['pts_bar']) + $v * (int)$s['pts_visit'];
            $out[$em] = $r + ['bar' => $b['t'], 'bar_n' => $b['n'], 'visits' => $v, 'points' => $pts, 'tier' => self::tier($s, $pts)];
        }
        // dépenses aux bars de personnes sans commande (cartes virtuelles créées au portail)
        foreach ($bar as $em => $b) if (!isset($out[$em]) && $em !== '') {
            $pts = (int)floor($b['t'] / 100 * (float)$s['pts_bar']);
            $out[$em] = ['email' => $em, 'first_name' => '', 'last_name' => '', 'phone' => '', 'optin' => 0, 'orders' => 0, 'events' => 0, 'spent' => 0, 'first_at' => null, 'last_at' => null, 'tickets' => 0, 'came' => 0,
                'bar' => $b['t'], 'bar_n' => $b['n'], 'visits' => 0, 'points' => $pts, 'tier' => self::tier($s, $pts)];
        }
        uasort($out, fn($a, $b) => $b['points'] <=> $a['points']);
        return $out;
    }

    /** Dépenses aux bars attribuables (cashless) : e-mail du porte-monnaie, sinon du billet ou de la commande. */
    public static function barSpend(int $orgId, ?string $email = null): array
    {
        $out = [];
        $rows = all("SELECT LOWER(COALESCE(NULLIF(w.email, ''), NULLIF(t.holder_email, ''), o.email)) em, SUM(s.amount) t, SUM(s.kind = 'sale') n
            FROM pos_sales s JOIN wallets w ON w.id = s.wallet_id JOIN events e ON e.id = s.event_id LEFT JOIN tickets t ON t.id = w.ticket_id LEFT JOIN orders o ON o.id = t.order_id
            WHERE e.organizer_id = ? AND s.undone = 0 AND s.kind IN ('sale','refund') GROUP BY em", [$orgId]);
        foreach ($rows as $r) if ($r['em']) $out[$r['em']] = ['t' => (int)$r['t'], 'n' => (int)$r['n']];
        return $email !== null ? ($out[strtolower($email)] ?? ['t' => 0, 'n' => 0]) : $out;
    }

    public static function segment(array $members, string $seg, string $tierName = ''): array
    {
        if ($seg === '') return $members;
        $barVals = array_values(array_filter(array_map(fn($m) => $m['bar'], $members)));
        rsort($barVals); $p90 = $barVals ? $barVals[(int)floor(count($barVals) * 0.1)] ?? end($barVals) : PHP_INT_MAX;
        return array_filter($members, fn($m) => match ($seg) {
            'tier' => $m['tier'] && ($tierName === '' || $m['tier']['name'] === $tierName),
            'fideles' => (int)$m['events'] >= 2,
            'bar' => $m['bar'] > 0 && $m['bar'] >= $p90,
            'absents' => (int)$m['tickets'] > 0 && (int)$m['came'] === 0 && $m['last_at'] && strtotime((string)$m['last_at']) < time(),
            'nouveaux' => $m['first_at'] && strtotime((string)$m['first_at']) >= time() - 90 * 86400,
            'dormants' => $m['last_at'] && strtotime((string)$m['last_at']) < time() - 365 * 86400,
            default => true,
        });
    }
}
