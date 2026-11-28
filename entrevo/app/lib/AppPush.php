<?php
declare(strict_types=1);

/**
 * 4.3 : alertes de l'appli organisateur. Chaque alerte est gardée (fil d'activité de l'appli) et, si l'organisateur
 * a activé les notifications sur un appareil, envoyée en Web Push par la tâche de fond (sans ralentir l'achat).
 */
final class AppPush
{
    public const KINDS = ['sale' => 'Chaque vente', 'milestone' => 'Paliers de ventes', 'soldout' => 'Tarif ou événement complet', 'refund' => 'Demandes de remboursement', 'security' => 'Jauge et sécurité', 'daily' => 'Résumé du soir'];
    public const DEFAULT_PREFS = ['sale' => 1, 'milestone' => 1, 'soldout' => 1, 'refund' => 1, 'security' => 1, 'daily' => 0, 'step' => 100];

    public static function prefs(?string $json): array { return array_merge(self::DEFAULT_PREFS, json_decode((string)$json, true) ?: []); }

    /** Enregistre une alerte (sans doublon grâce à $dedup) et la met en file pour les appareils abonnés. */
    public static function alert(int $orgId, string $kind, string $title, string $body = '', string $url = '/pro/app', ?int $eventId = null, ?string $dedup = null): void
    {
        try {
            if ($dedup && val('SELECT id FROM app_alerts WHERE organizer_id = ? AND dedup = ?', [$orgId, $dedup])) return;
            insert('app_alerts', ['organizer_id' => $orgId, 'event_id' => $eventId, 'kind' => $kind, 'title' => mb_substr($title, 0, 190), 'body' => mb_substr($body, 0, 300) ?: null, 'url' => $url, 'dedup' => $dedup, 'created_at' => now()]);
            foreach (all('SELECT id, prefs FROM push_subscriptions WHERE organizer_id = ? AND fails < 5', [$orgId]) as $s) {
                if (!(int)(self::prefs($s['prefs'])[$kind] ?? 0)) continue;
                insert('push_queue', ['subscription_id' => $s['id'], 'payload' => json_encode(['title' => $title, 'body' => $body, 'url' => $url, 'tag' => $kind . ($eventId ? '-' . $eventId : '')], JSON_UNESCAPED_UNICODE), 'created_at' => now()]);
            }
        } catch (Throwable $e) { log_line('notifications', $e->getMessage()); }
    }

    /** Commande payée : vente, palier atteint, tarif ou événement complet. */
    public static function orderPaid(int $orgId, string $ref): void
    {
        $o = one('SELECT o.*, e.name ev, e.capacity cap FROM orders o JOIN events e ON e.id = o.event_id WHERE o.ref = ?', [$ref]);
        if (!$o) return;
        $evId = (int)$o['event_id'];
        $n = (int)val("SELECT COUNT(*) FROM tickets WHERE order_id = ? AND status = 'valid'", [$o['id']]);
        $who = trim((string)$o['first_name']) !== '' ? trim($o['first_name'] . ' ' . mb_substr((string)$o['last_name'], 0, 1) . '.') : (string)$o['last_name'];
        if (!in_array($o['method'], ['invitation', 'partner', 'import', 'stock'], true))
            self::alert($orgId, 'sale', ((int)$o['total'] ? money((int)$o['total']) . ' · ' : '') . $n . ' billet' . ($n > 1 ? 's' : ''), $o['ev'] . ' · ' . $who, '/pro/app?e=' . $evId, $evId);
        $sold = (int)val("SELECT COUNT(*) FROM tickets WHERE event_id = ? AND status = 'valid'", [$evId]);
        foreach (all('SELECT prefs FROM push_subscriptions WHERE organizer_id = ? GROUP BY prefs', [$orgId]) ?: [['prefs' => null]] as $s) {
            $step = max(10, (int)self::prefs($s['prefs'])['step']);
            $m = intdiv($sold, $step) * $step;
            if ($m > 0 && $sold - $n < $m) self::alert($orgId, 'milestone', $m . ' billets vendus', $o['ev'] . ((int)$o['cap'] ? ' · ' . round($sold / (int)$o['cap'] * 100) . ' % de la jauge' : ''), '/pro/app?e=' . $evId, $evId, 'm' . $evId . '-' . $m);
        }
        if ((int)$o['cap'] && $sold >= (int)$o['cap']) self::alert($orgId, 'soldout', 'Complet : ' . $o['ev'], $sold . ' billets vendus', '/pro/app?e=' . $evId, $evId, 'full' . $evId);
        foreach (all("SELECT r.id, r.name, r.quota, (SELECT COUNT(*) FROM tickets t WHERE t.rate_id = r.id AND t.status = 'valid') s FROM rates r
            WHERE r.event_id = ? AND r.quota > 0 AND r.id IN (SELECT rate_id FROM order_items WHERE order_id = ?)", [$evId, $o['id']]) as $r)
            if ((int)$r['s'] >= (int)$r['quota']) self::alert($orgId, 'soldout', 'Tarif complet : ' . $r['name'], $o['ev'], '/pro/app?e=' . $evId, $evId, 'rate' . $r['id']);
    }

    /** Résumé du soir (vers 21 h) pour les événements à venir. */
    public static function daily(): int
    {
        if ((int)date('G') < 21 || setting('push_daily_last') === date('Y-m-d')) return 0;
        set_setting('push_daily_last', date('Y-m-d'));
        $n = 0;
        foreach (all("SELECT o.organizer_id, COUNT(DISTINCT o.id) orders, COALESCE(SUM(o.total), 0) total FROM orders o WHERE o.status = 'paid' AND o.paid_at >= CURDATE() GROUP BY o.organizer_id") as $r) {
            self::alert((int)$r['organizer_id'], 'daily', 'Ta journée : ' . money((int)$r['total']), (int)$r['orders'] . ' commande(s) aujourd\'hui', '/pro/app', null, 'day' . date('Ymd'));
            $n++;
        }
        return $n;
    }

    /** Envoie la file (tâche de fond). */
    public static function flush(int $max = 60): int
    {
        if (!function_exists('openssl_pkey_derive')) return 0;
        $n = 0;
        foreach (all('SELECT q.*, s.endpoint, s.p256dh, s.auth FROM push_queue q JOIN push_subscriptions s ON s.id = q.subscription_id WHERE q.sent_at IS NULL ORDER BY q.id LIMIT ' . $max) as $q) {
            try { $code = WebPush::send($q, json_decode($q['payload'], true) ?: []); } catch (Throwable $e) { $code = 0; log_line('notifications', $e->getMessage()); }
            update('push_queue', ['sent_at' => now(), 'status' => $code], 'id = ?', [$q['id']]);
            if ($code >= 200 && $code < 300) q('UPDATE push_subscriptions SET last_ok_at = NOW(), fails = 0 WHERE id = ?', [$q['subscription_id']]);
            elseif (in_array($code, [404, 410], true)) q('DELETE FROM push_subscriptions WHERE id = ?', [$q['subscription_id']]);
            else q('UPDATE push_subscriptions SET fails = fails + 1 WHERE id = ?', [$q['subscription_id']]);
            $n++;
        }
        q('DELETE FROM push_queue WHERE sent_at < NOW() - INTERVAL 7 DAY');
        return $n;
    }
}
