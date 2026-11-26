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
            'published' => fn() => q("UPDATE events e JOIN organizers o ON o.id = e.organizer_id SET e.status = 'published' WHERE e.status = 'draft' AND e.publish_at IS NOT NULL AND e.publish_at <= NOW() AND o.status = 'active'")->rowCount(),
            'payments' => fn() => (time() - (int)setting('jobs_pay', 0) >= 180 || $force) ? (set_setting('jobs_pay', (string)time()) ?? Payments::recheckPending()) : 0,
            'waitlist' => fn() => class_exists('Waitlist') ? Waitlist::process() : 0,
            'prix' => fn() => class_exists('DynPrice') ? DynPrice::syncAll() : 0,
            'campaigns' => fn() => class_exists('Campaigns') ? Campaigns::process() : 0,
            'sms' => fn() => class_exists('Sms') ? Sms::process() : 0,
            'formules' => fn() => class_exists('Billing') ? Billing::monthly() : 0,
            'rgpd' => fn() => class_exists('Gdpr') ? Gdpr::retention() : 0,
            'sauvegarde' => fn() => class_exists('Backups') ? Backups::auto() : 0,
            'alertes' => fn() => class_exists('Alerts') ? Alerts::check() : 0,
            'webhooks' => fn() => class_exists('Hooks') ? Hooks::deliver() : 0,
            'compta' => fn() => class_exists('Accounting') ? Accounting::nightly() : 0,
            'invoices' => fn() => (setting('invoice_auto') === '1' && class_exists('Invoices') && setting('invoices_auto_last') !== date('Y-m', strtotime('first day of last month')) && trim((string)setting('invoice_name')) !== '')
                ? (set_setting('invoices_auto_last', date('Y-m', strtotime('first day of last month'))) ?? count(Invoices::generate(date('Y-m', strtotime('first day of last month')))))
                : 0,
            'recap' => fn() => class_exists('Recap') ? Recap::run() : 0,
            'alerts' => fn() => class_exists('Recap') ? Recap::thresholds() : 0,
            'staff' => fn() => class_exists('Staff') ? Staff::reminders() : 0,
            'rappels' => fn() => class_exists('Buyers') ? Buyers::reminders() : 0,
            'paniers' => fn() => class_exists('Buyers') ? Buyers::recovery() : 0,
            'paniers2' => fn() => class_exists('Buyers') ? Buyers::recoverySecond() : 0,
            'avis' => fn() => class_exists('Buyers') ? Buyers::reviewRequests() : 0,
            'queue' => fn() => q('DELETE FROM queue_entries WHERE last_seen < NOW() - INTERVAL 2 HOUR')->rowCount(),
        ];
        foreach ($steps as $name => $fn) {
            try { $r[$name] = $fn(); } catch (Throwable $e) { $r[$name] = 'erreur'; log_line('taches', $name . ' : ' . $e->getMessage()); }
        }
        try { set_setting('jobs_report', json_encode(['at' => date('Y-m-d H:i:s'), 'cron' => (bool)$force, 'r' => $r])); if ($force) set_setting('cron_last', (string)time()); } catch (Throwable $e) { /* sans gravité */ }
        return $r;
    }
}
