<?php
declare(strict_types=1);

/**
 * PC sécurité : état en temps réel d'un événement (jauges par zone, flux, refus, appareils).
 * Présence par zone = entrées validées à cette porte − sorties à cette porte (passages annulés exclus).
 */
final class Pc
{
    public static function snapshot(array $ev): array
    {
        $id = (int)$ev['id'];
        $t = scan_stats_pc($id);
        $zones = [];
        foreach (all("SELECT a.id, a.name, a.capacity, a.active,
                SUM(s.result = 'ok' AND s.direction = 'in' AND s.undone = 0) ins,
                SUM(s.result = 'exit' AND s.undone = 0) outs,
                SUM(s.result = 'ok' AND s.undone = 0 AND s.scanned_at >= NOW() - INTERVAL 15 MINUTE) last15,
                SUM(s.result NOT IN ('ok','exit') AND s.scanned_at >= NOW() - INTERVAL 30 MINUTE) refused
            FROM access_points a LEFT JOIN scans s ON s.access_point_id = a.id WHERE a.event_id = ? GROUP BY a.id ORDER BY a.id", [$id]) as $z) {
            $inside = max(0, (int)$z['ins'] - (int)$z['outs']);
            $cap = (int)$z['capacity'];
            $pct = $cap ? round($inside / $cap * 100) : null;
            $zones[] = ['id' => (int)$z['id'], 'name' => $z['name'], 'open' => (int)$z['active'], 'inside' => $inside, 'capacity' => $cap ?: null, 'pct' => $pct,
                'level' => $pct === null ? 'ok' : ($pct >= 100 ? 'bad' : ($pct >= 90 ? 'warn' : 'ok')), 'per_min' => round((int)$z['last15'] / 15, 1), 'refused' => (int)$z['refused']];
        }
        // flux : entrées par tranche de 5 minutes sur 2 heures
        $map = array_column(all("SELECT FLOOR(UNIX_TIMESTAMP(scanned_at) / 300) b, COUNT(*) n FROM scans WHERE event_id = ? AND result = 'ok' AND undone = 0 AND scanned_at >= NOW() - INTERVAL 2 HOUR GROUP BY b", [$id]), 'n', 'b');
        $flux = []; $now = intdiv(time(), 300);
        for ($b = $now - 23; $b <= $now; $b++) $flux[] = ['t' => date('H:i', $b * 300), 'n' => (int)($map[$b] ?? 0)];
        $rate = array_sum(array_column(array_slice($flux, -3), 'n')) / 15; // entrées / min sur 15 min
        $remaining = max(0, $t['sold'] - $t['checked']);
        $refusals = all("SELECT s.scanned_at, s.result, s.code, a.name ap, t.first_name, t.last_name FROM scans s LEFT JOIN access_points a ON a.id = s.access_point_id LEFT JOIN tickets t ON t.id = s.ticket_id
            WHERE s.event_id = ? AND s.result NOT IN ('ok','exit') ORDER BY s.id DESC LIMIT 8", [$id]);
        $devices = all('SELECT d.ap_name, d.direction, d.battery, d.pending, d.last_seen, u.name FROM scan_devices d LEFT JOIN users u ON u.id = d.user_id WHERE d.event_id = ? AND d.last_seen >= NOW() - INTERVAL 2 HOUR ORDER BY d.last_seen DESC', [$id]);
        $cap = (int)$ev['capacity'];
        return [
            'at' => date('H:i:s'), 'name' => $ev['name'],
            'total' => $t + ['capacity' => $cap ?: null, 'pct' => $cap ? round($t['inside'] / $cap * 100) : null, 'remaining' => $remaining],
            'rate' => round($rate, 1), 'eta' => $rate > 0 && $remaining ? (int)ceil($remaining / $rate) : null,
            'zones' => $zones, 'flux' => $flux,
            'refusals' => array_map(fn($r) => ['at' => substr($r['scanned_at'], 11, 5), 'label' => Tickets::LABELS[$r['result']] ?? $r['result'], 'who' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: $r['code'], 'ap' => $r['ap'] ?: '—'], $refusals),
            'devices' => array_map(fn($d) => ['name' => $d['name'] ?: 'Contrôleur', 'ap' => $d['ap_name'] ?: '?', 'dir' => $d['direction'], 'battery' => $d['battery'] !== null ? (int)$d['battery'] : null, 'pending' => (int)$d['pending'], 'online' => strtotime($d['last_seen']) > time() - 90], $devices),
        ];
    }

    /** Alerte les contrôleurs (message du PC) quand une zone passe 90 % puis 100 %, une seule fois par seuil. */
    public static function alerts(array $ev, array $snap): void
    {
        foreach ($snap['zones'] as $z) {
            if ($z['pct'] === null) continue;
            foreach ([100, 90] as $lvl) {
                if ($z['pct'] < $lvl) continue;
                if (val('SELECT 1 FROM zone_alerts WHERE access_point_id = ? AND level = ?', [$z['id'], $lvl])) break;
                q('INSERT IGNORE INTO zone_alerts (access_point_id, level, created_at) VALUES (?, ?, NOW())', [$z['id'], $lvl]);
                insert('scan_messages', ['event_id' => $ev['id'], 'body' => ($lvl >= 100 ? '' . $z['name'] . ' est PLEINE (' . $z['inside'] . '/' . $z['capacity'] . ') : on ne fait plus entrer, attendez des sorties.' : '' . $z['name'] . ' à ' . $z['pct'] . ' % (' . $z['inside'] . '/' . $z['capacity'] . ') : ralentissez les entrées.'),
                    'level' => $lvl >= 100 ? 'alert' : 'warn', 'user_id' => null, 'created_at' => now()]);
                break;
            }
            // la zone redescend sous 80 % : on réarme les alertes
            if ($z['pct'] < 80) q('DELETE FROM zone_alerts WHERE access_point_id = ?', [$z['id']]);
        }
    }
}

function scan_stats_pc(int $eventId): array
{
    $r = one("SELECT COUNT(*) sold, SUM(entries > 0) checked, SUM(inside = 1) inside FROM tickets WHERE event_id = ? AND status = 'valid' AND type <> 'accreditation'", [$eventId]);
    return ['sold' => (int)$r['sold'], 'checked' => (int)$r['checked'], 'inside' => (int)$r['inside']];
}
