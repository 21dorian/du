<?php
declare(strict_types=1);

/**
 * 2.3 : alertes de supervision. Vérifications toutes les 10 minutes (tâches de fond) ;
 * chaque alerte part au plus une fois toutes les N heures tant que le problème dure,
 * et un message « rétabli » part quand il disparaît.
 * Canaux : e-mail(s), Slack / Discord / Mattermost / Teams (webhook entrant), webhook JSON générique.
 */
final class Alerts
{
    public const CHECKS = [
        'cron' => 'Tâche planifiée arrêtée depuis plus d\'une heure',
        'sauvegarde' => 'Sauvegarde automatique en retard ou en échec',
        'paiements' => 'Erreurs de paiement en série (prestataire en panne ?)',
        'erreurs' => 'Pic d\'erreurs du site',
        'disque' => 'Espace disque presque plein',
        'litige' => 'Nouveau litige de paiement',
        'support' => 'Demande de support urgente',
        'fraude' => 'Commande suspecte à vérifier',
        'smtp' => 'E-mails qui ne partent plus',
    ];

    public static function enabled(string $k): bool { return in_array($k, explode(',', (string)setting('alert_checks', 'cron,sauvegarde,paiements,erreurs,disque,litige,smtp')), true); }

    /** Compte les lignes d'un journal écrites dans les N dernières minutes. */
    private static function logCount(string $file, int $minutes): int
    {
        $f = STORAGE . '/logs/' . $file . '.log';
        if (!is_file($f) || filemtime($f) < time() - $minutes * 60) return 0;
        $fh = fopen($f, 'rb'); $size = filesize($f); fseek($fh, max(0, $size - 200000)); $chunk = (string)fread($fh, 200000); fclose($fh);
        $since = date('Y-m-d H:i:s', time() - $minutes * 60); $n = 0;
        foreach (explode("\n", $chunk) as $l) if (preg_match('~^\[(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\]~', $l, $m) && $m[1] >= $since) $n++;
        return $n;
    }

    /** @return array<string, array{0:string,1:string,2:string}> clé => [niveau, titre, détail] des problèmes en cours */
    public static function problems(): array
    {
        $p = [];
        $cron = (int)setting('cron_last', '0');
        if (self::enabled('cron') && $cron && $cron < time() - 3600) $p['cron'] = ['alert', 'La tâche planifiée ne tourne plus', 'Dernier passage ' . ago(date('Y-m-d H:i:s', $cron)) . '. Rappels, campagnes, sauvegardes et relances sont à l\'arrêt.'];
        if (self::enabled('sauvegarde') && setting('backup_auto', '1') === '1') {
            $b = (int)setting('backup_last', '0');
            if (setting('backup_error')) $p['sauvegarde'] = ['alert', 'La sauvegarde automatique a échoué', (string)setting('backup_error')];
            elseif ($b && $b < time() - 36 * 3600) $p['sauvegarde'] = ['warn', 'Pas de sauvegarde depuis ' . ago(date('Y-m-d H:i:s', $b)), 'Vérifie la tâche planifiée et l\'espace disque.'];
        }
        if (self::enabled('paiements') && ($n = self::logCount('paiements', 30)) >= 5) $p['paiements'] = ['alert', $n . ' erreurs de paiement en 30 minutes', 'Le prestataire de paiement est peut-être en panne ou une clé a expiré. Journal : storage/logs/paiements.log'];
        if (self::enabled('erreurs') && ($n = self::logCount('erreurs', 15)) >= 10) $p['erreurs'] = ['alert', $n . ' erreurs du site en 15 minutes', 'Voir Super-admin › Santé et storage/logs/erreurs.log'];
        if (self::enabled('smtp') && ($n = self::logCount('emails', 30)) >= 5) $p['smtp'] = ['warn', $n . ' e-mails en échec en 30 minutes', 'Le serveur SMTP refuse peut-être les envois (mot de passe, quota). Les billets ne partent plus par e-mail.'];
        if (self::enabled('disque') && function_exists('disk_free_space') && ($free = @disk_free_space(ROOT)) !== false && $free < 300 * 1048576) $p['disque'] = ['alert', 'Plus que ' . Backups::human((int)$free) . ' d\'espace disque', 'Supprime d\'anciennes sauvegardes ou des fichiers inutiles.'];
        if (self::enabled('litige') && ($n = (int)val("SELECT COUNT(*) FROM disputes WHERE status = 'open' AND created_at >= NOW() - INTERVAL 1 DAY"))) $p['litige'] = ['warn', $n . ' nouveau(x) litige(s) de paiement', 'À traiter dans Super-admin › Litiges & impayés avant l\'échéance.'];
        if (self::enabled('support') && ($n = (int)val("SELECT COUNT(*) FROM support_tickets WHERE status = 'open' AND priority = 'urgent'"))) $p['support'] = ['warn', $n . ' demande(s) de support urgente(s)', 'Super-admin › Support'];
        if (self::enabled('fraude') && ($n = (int)val("SELECT COUNT(*) FROM orders WHERE risk_status = 'review'"))) $p['fraude'] = ['warn', $n . ' commande(s) suspecte(s) à vérifier', 'Super-admin › Fraude & risques'];
        return $p;
    }

    /** Tâche de fond (toutes les 10 minutes). */
    public static function check(bool $force = false): int
    {
        if (!$force && time() - (int)setting('alerts_checked', '0') < 600) return 0;
        set_setting('alerts_checked', (string)time());
        $cool = max(1, (int)setting('alert_cooldown', '6')) * 3600;
        $open = json_decode((string)setting('alerts_open', '{}'), true) ?: [];
        $now = self::problems(); $sent = 0;
        foreach ($now as $k => [$lvl, $title, $body]) {
            if (isset($open[$k]) && time() - (int)$open[$k] < $cool) continue;
            self::send($k, $lvl, $title, $body); $open[$k] = time(); $sent++;
        }
        foreach (array_keys($open) as $k) if (!isset($now[$k])) {
            if (in_array($k, ['cron', 'sauvegarde', 'paiements', 'erreurs', 'disque', 'smtp'], true)) self::send($k, 'ok', 'Rétabli : ' . (self::CHECKS[$k] ?? $k), 'Le problème a disparu.');
            q('UPDATE alerts_log SET resolved_at = NOW() WHERE akey = ? AND resolved_at IS NULL', [$k]);
            unset($open[$k]);
        }
        set_setting('alerts_open', json_encode($open));
        return $sent;
    }

    public static function send(string $key, string $level, string $title, string $body): array
    {
        $name = (string)setting('platform_name', 'Entrevo');
        $done = [];
        foreach (array_filter(array_map('trim', preg_split('~[,;\s]+~', (string)(setting('alert_emails') ?: setting('contact_email'))))) as $to) {
            if (!is_email($to)) continue;
            try { Mailer::send($to, '[' . $name . ($level === 'ok' ? '' : ' · alerte') . '] ' . $title, '<p><b>' . e($title) . '</b></p><p>' . nl2br(e($body)) . '</p><p><a href="' . e(abs_url('/admin/sante')) . '">Ouvrir le super-admin</a></p>'); $done[] = 'e-mail'; }
            catch (Throwable $e) { log_line('alertes', 'E-mail : ' . $e->getMessage()); }
        }
        if ($url = trim((string)setting('alert_webhook'))) {
            $tag = ['alert' => '[ALERTE]', 'warn' => '[ATTENTION]', 'ok' => '[RÉTABLI]'][$level] ?? '';
            $text = $tag . ' *' . $name . '* — ' . $title . "\n" . $body;
            $payload = str_contains($url, 'discord.com') ? ['content' => $text] : (str_contains($url, 'hooks.slack.com') || str_contains($url, 'mattermost') ? ['text' => $text]
                : ['text' => $text, 'title' => $title, 'body' => $body, 'level' => $level, 'key' => $key, 'platform' => $name, 'at' => date('c')]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $ok = curl_exec($ch) !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) < 300; curl_close($ch);
            if ($ok) $done[] = 'webhook'; else log_line('alertes', 'Webhook en échec : ' . $title);
        }
        insert('alerts_log', ['akey' => $key, 'level' => $level, 'title' => mb_substr($title, 0, 190), 'body' => $body, 'channels' => implode(',', $done) ?: 'aucun', 'created_at' => now()]);
        return $done;
    }
}
