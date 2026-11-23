<?php
declare(strict_types=1);

/**
 * Placement numéroté. Le plan est stocké en JSON dans events.seatmap :
 * {
 *   "categories": [{"id":"or","name":"Carré or","color":"#ffb703"}],
 *   "blocks": [{"id":"ORCH","name":"Orchestre","rows":10,"cols":20,"x":0,"y":0,"row_start":"A","num_start":1,"cat":"or","gap":0}],
 *   "disabled": ["ORCH-A1"],
 *   "stage": "Scène"
 * }
 * Un siège a une clé « BLOC-RANGNUMÉRO » (ex. ORCH-C12).
 */
final class Seating
{
    public static function map(array $ev): ?array
    {
        if (empty($ev['seatmap'])) return null;
        $m = json_decode((string)$ev['seatmap'], true);
        return is_array($m) && !empty($m['blocks']) ? $m + ['categories' => [], 'disabled' => [], 'stage' => 'Scène'] : null;
    }

    public static function rowLabel(string $start, int $i): string
    {
        if (ctype_digit($start)) return (string)((int)$start + $i);
        $n = 0;
        foreach (str_split(strtoupper($start)) as $ch) $n = $n * 26 + (ord($ch) - 64);
        $n += $i;
        $s = '';
        while ($n > 0) { $m = ($n - 1) % 26; $s = chr(65 + $m) . $s; $n = intdiv($n - 1, 26); }
        return $s;
    }

    /** @return array<string,array{key:string,block:string,row:string,num:int,x:int,y:int,cat:string,off:bool}> */
    public static function seats(array $map): array
    {
        $out = [];
        $disabled = array_flip($map['disabled'] ?? []);
        foreach ($map['blocks'] as $b) {
            $rows = max(1, min(60, (int)$b['rows']));
            $cols = max(1, min(80, (int)$b['cols']));
            for ($r = 0; $r < $rows; $r++) {
                $row = self::rowLabel((string)($b['row_start'] ?? 'A'), $r);
                for ($c = 0; $c < $cols; $c++) {
                    $num = (int)($b['num_start'] ?? 1) + $c;
                    $key = $b['id'] . '-' . $row . $num;
                    $out[$key] = ['key' => $key, 'block' => (string)$b['name'], 'row' => $row, 'num' => $num,
                        'x' => (int)$b['x'] + $c, 'y' => (int)$b['y'] + $r, 'cat' => (string)($b['cat'] ?? ''), 'off' => isset($disabled[$key])];
                }
            }
        }
        return $out;
    }

    public static function label(string $key, ?array $map = null): string
    {
        if ($map) {
            $s = self::seats($map)[$key] ?? null;
            if ($s) return $s['block'] . ' · Rang ' . $s['row'] . ' · Place ' . $s['num'];
        }
        return $key;
    }

    /** Sièges vendus ou bloqués (paiement en cours) pour une séance. */
    public static function taken(array $ev, ?int $sessionId, int $exceptOrder = 0): array
    {
        $ss = $sessionId ? ' AND session_id = ' . (int)$sessionId : '';
        $sold = q("SELECT seat FROM tickets WHERE event_id = ? AND status = 'valid' AND seat IS NOT NULL" . $ss, [$ev['id']])->fetchAll(PDO::FETCH_COLUMN);
        $held = q('SELECT seat FROM seat_holds WHERE event_id = ? AND session_id = ? AND expires_at > NOW() AND order_id <> ?', [$ev['id'], (int)$sessionId, $exceptOrder])->fetchAll(PDO::FETCH_COLUMN);
        return array_flip(array_merge($sold, $held));
    }

    /** @return true|string true si le siège peut être vendu pour ce tarif, sinon un message */
    public static function check(array $ev, ?array $session, string $key, array $rate)
    {
        $map = self::map($ev);
        $seat = $map ? (self::seats($map)[$key] ?? null) : null;
        if (!$seat || $seat['off']) return 'La place ' . $key . ' n\'existe pas.';
        if ($rate['seat_category'] && $seat['cat'] !== $rate['seat_category']) return 'La place ' . self::label($key, $map) . ' n\'est pas dans la catégorie du tarif « ' . $rate['name'] . ' ».';
        $taken = self::taken($ev, $session ? (int)$session['id'] : null);
        if (isset($taken[$key])) return 'La place ' . self::label($key, $map) . ' vient d\'être prise 😬';
        return true;
    }

    /** Données envoyées au plan interactif (public ou éditeur). */
    public static function payload(array $ev, ?int $sessionId): array
    {
        $map = self::map($ev);
        if (!$map) return [];
        $taken = self::taken($ev, $sessionId);
        $seats = [];
        foreach (self::seats($map) as $s) {
            $seats[] = [$s['key'], $s['x'], $s['y'], $s['cat'], $s['off'] ? 2 : (isset($taken[$s['key']]) ? 1 : 0), $s['block'], $s['row'], $s['num']];
        }
        return ['categories' => $map['categories'], 'stage' => $map['stage'] ?? 'Scène', 'seats' => $seats];
    }
}
