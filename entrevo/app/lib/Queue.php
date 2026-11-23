<?php
declare(strict_types=1);

/** File d'attente virtuelle : laisse entrer N visiteurs par minute sur la billetterie lors d'une ouverture des ventes très demandée. */
final class Queue
{
    public static function admitted(array $ev): bool
    {
        $u = user();
        if ($u && ($u['role'] === 'superadmin' || (int)$u['organizer_id'] === (int)$ev['organizer_id'])) return true;
        return ($_SESSION['queue_ok'][$ev['id']] ?? 0) > time();
    }

    public static function admit(array $ev): void { $_SESSION['queue_ok'][$ev['id']] = time() + 1800; }

    public static function status(array $ev): array
    {
        $tok = $_SESSION['queue_tok'][$ev['id']] ?? null;
        $e = $tok ? one('SELECT * FROM queue_entries WHERE token = ? AND event_id = ?', [$tok, $ev['id']]) : null;
        if (!$e) {
            $tok = token(20);
            $_SESSION['queue_tok'][$ev['id']] = $tok;
            insert('queue_entries', ['event_id' => $ev['id'], 'token' => $tok, 'created_at' => now(), 'last_seen' => now()]);
            $e = one('SELECT * FROM queue_entries WHERE token = ?', [$tok]);
        } else {
            update('queue_entries', ['last_seen' => now()], 'id = ?', [$e['id']]);
        }
        // admissions : au plus queue_rate personnes par minute
        $rate = max(1, (int)$ev['queue_rate']);
        $recent = (int)val('SELECT COUNT(*) FROM queue_entries WHERE event_id = ? AND admitted_at > NOW() - INTERVAL 60 SECOND', [$ev['id']]);
        $slots = $rate - $recent;
        if ($slots > 0) {
            $ids = q('SELECT id FROM queue_entries WHERE event_id = ? AND admitted_at IS NULL AND last_seen > NOW() - INTERVAL 90 SECOND ORDER BY id LIMIT ' . (int)$slots, [$ev['id']])->fetchAll(PDO::FETCH_COLUMN);
            if ($ids) q('UPDATE queue_entries SET admitted_at = NOW() WHERE id IN (' . in_list($ids) . ')');
        }
        $e = one('SELECT * FROM queue_entries WHERE id = ?', [$e['id']]);
        if ($e['admitted_at']) { self::admit($ev); return ['admitted' => true, 'url' => url('/e/' . $ev['slug'])]; }
        $pos = (int)val('SELECT COUNT(*) FROM queue_entries WHERE event_id = ? AND admitted_at IS NULL AND last_seen > NOW() - INTERVAL 90 SECOND AND id < ?', [$ev['id'], $e['id']]) + 1;
        return ['admitted' => false, 'position' => $pos, 'minutes' => (int)ceil($pos / $rate)];
    }
}
