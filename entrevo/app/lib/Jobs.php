<?php
declare(strict_types=1);

/**
 * Tâches de fond : lancées automatiquement après les pages (au plus une fois par minute),
 * ou par une tâche cron cPanel qui appelle /cron?cle=… toutes les 5 minutes (plus fiable).
 */
final class Jobs
{
    public static function cronKey(): string
    {
        $k = (string)setting('cron_key', '');
        if ($k === '') { $k = token(18); set_setting('cron_key', $k); }
        return $k;
    }

    public static function run(bool $force = false): array
    {
        $last = (int)setting('jobs_last', 0);
        if (!$force && time() - $last < 60) return ['skipped' => true];
        set_setting('jobs_last', (string)time());
        $r = [];
        $steps = [
            'expired' => fn() => Tickets::expirePending(),
            'holds' => fn() => q('DELETE h FROM seat_holds h LEFT JOIN orders o ON o.id = h.order_id WHERE h.expires_at < NOW() OR o.id IS NULL OR o.status IN (\'cancelled\',\'refunded\')')->rowCount(),
            'published' => fn() => q("UPDATE events SET status = 'published' WHERE status = 'draft' AND publish_at IS NOT NULL AND publish_at <= NOW()")->rowCount(),
            'payments' => fn() => (time() - (int)setting('jobs_pay', 0) >= 180 || $force) ? (set_setting('jobs_pay', (string)time()) ?? Payments::recheckPending()) : 0,
            'waitlist' => fn() => class_exists('Waitlist') ? Waitlist::process() : 0,
            'staff' => fn() => class_exists('Staff') ? Staff::reminders() : 0,
            'queue' => fn() => q('DELETE FROM queue_entries WHERE last_seen < NOW() - INTERVAL 2 HOUR')->rowCount(),
        ];
        foreach ($steps as $name => $fn) {
            try { $r[$name] = $fn(); } catch (Throwable $e) { $r[$name] = 'erreur'; log_line('taches', $name . ' : ' . $e->getMessage()); }
        }
        return $r;
    }
}
