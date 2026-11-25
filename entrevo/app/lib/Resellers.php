<?php
declare(strict_types=1);

/**
 * 2.4 : revendeurs.
 * Offices de tourisme, comités d'entreprise, disquaires… vendent les billets des organisateurs qui les y autorisent,
 * depuis un portail web (lien secret). Le revendeur encaisse lui-même l'acheteur (comptoir) : la vente est enregistrée
 * comme une vente au guichet (méthode « guichet », moyen « revendeur ») et n'entre pas dans le solde à reverser.
 * Chaque mois, un relevé par organisateur indique le montant vendu, la commission du revendeur et ce qu'il doit reverser.
 */
final class Resellers
{
    public const KINDS = ['tourisme' => 'Office de tourisme', 'ce' => 'Comité d\'entreprise / CSE', 'commerce' => 'Commerce / disquaire', 'asso' => 'Association', 'autre' => 'Autre'];

    public static function byToken(string $token): ?array
    {
        if (strlen($token) < 20) return null;
        return one('SELECT * FROM resellers WHERE token = ? AND active = 1', [$token]);
    }

    /** Organisateurs qui ont ouvert leur billetterie à ce revendeur. */
    public static function organizers(int $rid): array
    {
        return all("SELECT o.*, a.commission_pct access_pct FROM reseller_access a JOIN organizers o ON o.id = a.organizer_id WHERE a.reseller_id = ? AND a.active = 1 AND o.status = 'active' ORDER BY o.name", [$rid]);
    }

    public static function allowed(int $rid, int $orgId): ?array
    {
        return one('SELECT * FROM reseller_access WHERE reseller_id = ? AND organizer_id = ? AND active = 1', [$rid, $orgId]);
    }

    /** Commission en % : celle négociée avec l'organisateur, sinon celle du revendeur. */
    public static function pct(array $r, ?array $access): float
    {
        return (float)($access && $access['commission_pct'] !== null ? $access['commission_pct'] : $r['commission_pct']);
    }

    public static function events(int $rid): array
    {
        $ids = array_column(self::organizers($rid), 'id');
        if (!$ids) return [];
        return all("SELECT e.*, o.name org_name FROM events e JOIN organizers o ON o.id = e.organizer_id WHERE e.organizer_id IN (" . implode(',', array_map('intval', $ids)) . ")
            AND e.status = 'published' AND COALESCE(e.ends_at, e.starts_at) >= NOW() ORDER BY e.starts_at");
    }

    /** Tarifs qu'un revendeur peut vendre : actifs, non masqués, prix fixe, en vente. */
    public static function rates(array $ev): array
    {
        $now = date('Y-m-d H:i:s');
        return array_values(array_filter(all('SELECT * FROM rates WHERE event_id = ? AND active = 1 AND hidden = 0 ORDER BY sort, id', [$ev['id']]),
            fn($r) => ($r['price_mode'] ?? 'fixed') === 'fixed' && (!$r['sale_start'] || $r['sale_start'] <= $now) && (!$r['sale_end'] || $r['sale_end'] >= $now)));
    }

    /** Relevé : ventes payées d'un mois, par organisateur. */
    public static function statement(int $rid, string $month, ?int $orgId = null): array
    {
        $from = $month . '-01'; $to = date('Y-m-d', strtotime($from . ' +1 month'));
        $rows = all("SELECT o.organizer_id, g.name org_name, COUNT(*) n, COALESCE(SUM(o.total), 0) total, COALESCE(SUM(o.reseller_commission), 0) commission,
                (SELECT COALESCE(SUM(qty), 0) FROM order_items i JOIN orders x ON x.id = i.order_id WHERE x.reseller_id = ? AND x.organizer_id = o.organizer_id AND x.status = 'paid' AND x.paid_at >= ? AND x.paid_at < ?) tickets
            FROM orders o JOIN organizers g ON g.id = o.organizer_id
            WHERE o.reseller_id = ? AND o.status = 'paid' AND o.paid_at >= ? AND o.paid_at < ?" . ($orgId ? ' AND o.organizer_id = ' . (int)$orgId : '') . " GROUP BY o.organizer_id, g.name ORDER BY g.name",
            [$rid, $from, $to, $rid, $from, $to]);
        foreach ($rows as &$r) $r['owed'] = (int)$r['total'] - (int)$r['commission'];
        return $rows;
    }

    public static function orders(int $rid, ?string $month = null, ?int $orgId = null, int $limit = 500): array
    {
        $w = 'o.reseller_id = ?'; $p = [$rid];
        if ($month) { $w .= ' AND o.created_at >= ? AND o.created_at < ?'; $p[] = $month . '-01'; $p[] = date('Y-m-d', strtotime($month . '-01 +1 month')); }
        if ($orgId) { $w .= ' AND o.organizer_id = ?'; $p[] = $orgId; }
        return all("SELECT o.*, e.name event_name, g.name org_name, (SELECT COALESCE(SUM(qty), 0) FROM order_items i WHERE i.order_id = o.id) n
            FROM orders o LEFT JOIN events e ON e.id = o.event_id JOIN organizers g ON g.id = o.organizer_id WHERE $w ORDER BY o.id DESC LIMIT " . max(1, $limit), $p);
    }

    public static function months(int $n = 12): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) $out[] = date('Y-m', strtotime(date('Y-m-01') . " -$i month"));
        return $out;
    }

    public static function csv(array $r, string $month, array $orders): void
    {
        $rows = [['Date', 'Référence', 'Organisateur', 'Événement', 'Acheteur', 'Billets', 'Montant', 'Commission', 'À reverser', 'Statut']];
        foreach ($orders as $o) $rows[] = [$o['created_at'], $o['ref'], $o['org_name'], (string)$o['event_name'], trim($o['first_name'] . ' ' . $o['last_name']), $o['n'],
            money_input((int)$o['total']), money_input((int)$o['reseller_commission']), money_input((int)$o['total'] - (int)$o['reseller_commission']), $o['status']];
        csv_download('releve-' . preg_replace('~[^a-z0-9]+~', '-', strtolower($r['name'])) . '-' . $month . '.csv', $rows);
    }
}
