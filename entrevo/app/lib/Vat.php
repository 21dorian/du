<?php
declare(strict_types=1);

/**
 * TVA côté organisateur : taux par tarif (rates.vat_rate), sinon taux par défaut de l'organisation
 * (organizers.vat_rate), sinon non assujetti. Les prix sont TTC ; la réduction est répartie au prorata,
 * les dons ne sont pas soumis, les frais de service appartiennent à la plateforme.
 */
final class Vat
{
    public const RATES = ['' => 'Taux de l\'organisation', '0' => 'Non soumis / 0 %', '2.1' => '2,1 %', '5.5' => '5,5 % (billetterie spectacles)', '10' => '10 %', '20' => '20 %'];

    public static function label(?float $r): string { return $r === null ? 'Non assujetti' : rtrim(rtrim(number_format($r, 2, ',', ''), '0'), ',') . ' %'; }

    /** Ventilation d'une commande : [taux (string) => ['ttc', 'ht', 'vat']]. Clé 'none' = non assujetti. */
    public static function breakdown(array $o, array $items, array $org, array $rateVat = []): array
    {
        $def = $org['vat_rate'] !== null && $org['vat_rate'] !== '' ? (float)$org['vat_rate'] : null;
        $lines = []; $sum = 0;
        foreach ($items as $i) {
            $amt = (int)$i['qty'] * (int)$i['unit_price'];
            if ($amt <= 0) continue;
            $r = ($i['kind'] ?? 'ticket') !== 'product' && isset($rateVat[(int)$i['rate_id']]) && $rateVat[(int)$i['rate_id']] !== null ? (float)$rateVat[(int)$i['rate_id']] : $def;
            $lines[] = [$r, $amt]; $sum += $amt;
        }
        $discount = min((int)$o['discount'], $sum);
        $out = [];
        foreach ($lines as [$r, $amt]) {
            $ttc = $amt - ($sum ? (int)round($discount * $amt / $sum) : 0);
            $k = $r === null ? 'none' : (string)$r;
            $ht = $r === null ? $ttc : (int)round($ttc / (1 + $r / 100));
            $out[$k] = ['ttc' => ($out[$k]['ttc'] ?? 0) + $ttc, 'ht' => ($out[$k]['ht'] ?? 0) + $ht, 'vat' => ($out[$k]['vat'] ?? 0) + ($ttc - $ht)];
        }
        if ((int)($o['donation'] ?? 0) > 0) $out['don'] = ['ttc' => (int)$o['donation'], 'ht' => (int)$o['donation'], 'vat' => 0];
        return $out;
    }

    public static function rateMap(int $eventId): array
    {
        return array_column(all('SELECT id, vat_rate FROM rates WHERE event_id = ?', [$eventId]), 'vat_rate', 'id');
    }

    /** Commandes payées d'une période avec leur ventilation (pour la TVA collectée et les exports). */
    public static function orders(array $org, string $from, string $to): array
    {
        $orders = all("SELECT o.*, e.name ev_name FROM orders o JOIN events e ON e.id = o.event_id WHERE o.organizer_id = ? AND o.status IN ('paid','refunded') AND (o.channel IS NULL OR o.channel <> 'systeme')
            AND o.total > 0 AND COALESCE(o.paid_at, o.created_at) BETWEEN ? AND ? ORDER BY o.paid_at LIMIT 50000", [$org['id'], $from, $to]);
        if (!$orders) return [];
        $items = [];
        foreach (all('SELECT * FROM order_items WHERE order_id IN (' . in_list(array_column($orders, 'id')) . ')') as $i) $items[(int)$i['order_id']][] = $i;
        $maps = [];
        foreach ($orders as &$o) {
            $maps[$o['event_id']] ??= self::rateMap((int)$o['event_id']);
            $o['vat'] = self::breakdown($o, $items[(int)$o['id']] ?? [], $org, $maps[$o['event_id']]);
            $o['items'] = $items[(int)$o['id']] ?? [];
        }
        return $orders;
    }
}
