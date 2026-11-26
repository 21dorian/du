<?php
declare(strict_types=1);

/**
 * 3.2 : prix dynamiques. Un tarif a des paliers de prix qui s'enchaînent tout seuls :
 * - selon les ventes : « 15 € jusqu'à 100 places vendues, 18 € jusqu'à 300, puis 22 € » ;
 * - selon la date : « 15 € jusqu'au 1er mars, 18 € jusqu'au 1er mai, puis 22 € ».
 * Le prix courant est recopié dans rates.price (toutes les pages de vente le lisent déjà) ; chaque changement est historisé.
 * Une commande en cours garde le prix auquel elle a été créée.
 */
final class DynPrice
{
    /** @return array{mode:string, steps:array<int,array{limit:mixed,price:int}>}|null */
    public static function config(array $r): ?array
    {
        $j = json_decode((string)($r['dyn_json'] ?? ''), true);
        if (!is_array($j) || empty($j['steps']) || !in_array($j['mode'] ?? '', ['sold', 'date'], true)) return null;
        return ['mode' => $j['mode'], 'steps' => array_values($j['steps'])];
    }

    /** Palier courant : prix, rang, places restantes à ce prix ou date de fin, prix suivant. */
    public static function state(array $r): ?array
    {
        $c = self::config($r); if (!$c) return null;
        $steps = $c['steps']; $n = count($steps);
        $sold = $c['mode'] === 'sold' ? Tickets::rateUsed((int)$r['id']) : 0;
        $i = $n - 1;
        foreach ($steps as $k => $s) {
            if ($s['limit'] === null || $s['limit'] === '') { $i = $k; break; }
            if ($c['mode'] === 'sold' ? $sold < (int)$s['limit'] : time() < strtotime((string)$s['limit'])) { $i = $k; break; }
        }
        $cur = $steps[$i];
        $last = $cur['limit'] === null || $cur['limit'] === '';
        return ['mode' => $c['mode'], 'index' => $i, 'count' => $n, 'price' => (int)$cur['price'], 'sold' => $sold,
            'left' => $c['mode'] === 'sold' && !$last ? max(0, (int)$cur['limit'] - $sold) : null,
            'until' => $c['mode'] === 'date' && !$last ? (string)$cur['limit'] : null,
            'next' => $i + 1 < $n ? (int)$steps[$i + 1]['price'] : null, 'steps' => $steps];
    }

    /** Applique le prix du palier courant aux tarifs dynamiques d'un événement. */
    public static function sync(int $eventId): int
    {
        $n = 0;
        foreach (all("SELECT * FROM rates WHERE event_id = ? AND dyn_json IS NOT NULL AND dyn_json <> '' AND price_mode <> 'free'", [$eventId]) as $r) {
            $st = self::state($r);
            if (!$st || $st['price'] === (int)$r['price']) continue;
            update('rates', ['price' => $st['price']], 'id = ?', [$r['id']]);
            insert('rate_prices', ['rate_id' => $r['id'], 'event_id' => $eventId, 'old_price' => (int)$r['price'], 'price' => $st['price'],
                'reason' => 'Palier ' . ($st['index'] + 1) . '/' . $st['count'] . ($st['mode'] === 'sold' ? ' · ' . $st['sold'] . ' place(s) vendue(s)' : ' · date'), 'created_at' => now()]);
            $n++;
        }
        return $n;
    }

    /** Tâche de fond : tous les événements en vente qui ont des tarifs dynamiques. */
    public static function syncAll(): int
    {
        $n = 0;
        foreach (all("SELECT DISTINCT r.event_id FROM rates r JOIN events e ON e.id = r.event_id WHERE r.dyn_json IS NOT NULL AND r.dyn_json <> '' AND e.status = 'published' AND COALESCE(e.ends_at, e.starts_at) >= NOW() - INTERVAL 1 DAY") as $e) $n += self::sync((int)$e['event_id']);
        return $n;
    }

    /** Lit le formulaire du tarif ; null = prix fixe. */
    public static function fromInput(): ?string
    {
        if (!checked('dyn_on')) return null;
        $mode = input('dyn_mode') === 'date' ? 'date' : 'sold';
        $steps = [];
        $limits = (array)($_POST['dyn_limit'] ?? []); $prices = (array)($_POST['dyn_price'] ?? []);
        foreach ($prices as $k => $p) {
            if (trim((string)$p) === '') continue;
            $lim = trim((string)($limits[$k] ?? ''));
            if ($lim !== '') $lim = $mode === 'sold' ? max(1, (int)$lim) : (strtotime($lim) ? date('Y-m-d H:i:s', strtotime($lim)) : '');
            $steps[] = ['limit' => $lim === '' ? null : $lim, 'price' => parse_money((string)$p)];
        }
        if (!$steps) return null;
        // le dernier palier n'a pas de limite ; les limites doivent croître
        $steps[count($steps) - 1]['limit'] = null;
        for ($i = 1; $i < count($steps) - 1; $i++) {
            $a = $steps[$i - 1]['limit']; $b = $steps[$i]['limit'];
            if ($a !== null && $b !== null && ($mode === 'sold' ? (int)$b <= (int)$a : strtotime((string)$b) <= strtotime((string)$a))) throw new RuntimeException('Les paliers doivent se suivre (limites croissantes).');
        }
        return json_encode(['mode' => $mode, 'steps' => $steps]);
    }

    public static function label(array $st): string
    {
        if ($st['next'] === null) return 'Dernier palier';
        return $st['mode'] === 'sold' ? 'Plus que ' . $st['left'] . ' place(s) à ce prix, ensuite ' . money($st['next'])
            : 'Ce prix jusqu\'au ' . fr_date($st['until']) . ', ensuite ' . money($st['next']);
    }
}
