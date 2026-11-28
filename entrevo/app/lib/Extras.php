<?php
declare(strict_types=1);

/**
 * 4.0 : modules sortis du développement — connecteurs (Google Sheets, Zapier/Make/n8n, Brevo/Mailchimp, pixels serveur Meta et GA4),
 * automatisations (codes promo automatiques, parrainage, rapport de fin d'événement, abonnés aux organisateurs), rôles personnalisés.
 * Les tâches de fond tournent avec Jobs::run().
 */
final class Extras
{
    public const DEFAULTS = [
        'sheets_token' => '', 'mail_provider' => '', 'mail_key' => '', 'mail_list' => '',
        'meta_pixel' => '', 'meta_token' => '', 'meta_test' => '', 'ga4_id' => '', 'ga4_secret' => '',
        'auto_birthday' => 0, 'auto_birthday_pct' => 10, 'auto_comeback' => 0, 'auto_comeback_pct' => 15, 'auto_thanks' => 0, 'auto_thanks_pct' => 10,
        'referral' => 0, 'referral_n' => 3, 'referral_pct' => 20, 'end_report' => 1,
    ];
    /** Rubriques de l'espace organisateur pour les rôles personnalisés : clé => [libellé, préfixes d'adresse]. */
    public const SECTIONS = [
        'dashboard' => ['Tableau de bord', ['/pro$']], 'events' => ['Événements (création et réglages)', ['/pro/evenements']],
        'orders' => ['Commandes & remboursements', ['/pro/commandes']], 'people' => ['Participants & fidélité', ['/pro/participants', '/pro/fidelite', '/pro/festivaliers']],
        'marketing' => ['Campagnes, SMS, liens, avis', ['/pro/campagnes', '/pro/sms', '/pro/liens', '/pro/avis', '/pro/visuels', '/pro/mini-site']],
        'shop' => ['Boutique, abonnements, cartes cadeaux, devis, revendeurs', ['/pro/boutique', '/pro/abonnements', '/pro/cartes-cadeaux', '/pro/devis', '/pro/revendeurs']],
        'money' => ['Finances, factures, bilans, budget', ['/pro/finances', '/pro/factures', '/pro/bilans', '/pro/budget', '/pro/compta']],
        'onsite' => ['Jour J : bénévoles, artistes, PC, objets trouvés', ['/pro/benevoles', '/pro/artistes', '/pro/pc', '/pro/objets-trouves', '/pro/taches', '/pro/documents']],
        'settings' => ['Organisation, équipe, intégrations', ['/pro/organisation', '/pro/equipe', '/pro/integrations', '/pro/connecteurs', '/pro/automatisations']],
    ];

    public static function settings(array $org): array
    {
        $j = json_decode((string)($org['connect_json'] ?? ''), true);
        return (is_array($j) ? $j : []) + self::DEFAULTS;
    }

    public static function save(array $org, array $patch): void
    {
        update('organizers', ['connect_json' => json_encode($patch + self::settings($org), JSON_UNESCAPED_UNICODE)], 'id = ?', [$org['id']]);
    }

    // ---------- rôles personnalisés ----------
    /** null = autorisé ; sinon message de refus. */
    public static function roleCheck(array $u, string $path, ?int $eventId = null): ?string
    {
        if (empty($u['custom_role_id']) || !in_array($u['role'], ['manager'], true)) return null;
        $r = one('SELECT * FROM org_roles WHERE id = ?', [$u['custom_role_id']]);
        if (!$r) return null;
        $allowed = array_filter(explode(',', (string)$r['sections']));
        $ok = in_array('dashboard', $allowed, true) && ($path === '/pro' || str_starts_with($path, '/pro/recherche') || str_starts_with($path, '/pro/notifications') || $path === '/pro/mobile' || str_starts_with($path, '/pro/app'));
        foreach ($allowed as $sec) foreach (self::SECTIONS[$sec][1] ?? [] as $pre) if (preg_match('~^' . str_replace('$', '', $pre) . (str_ends_with($pre, '$') ? '$' : '') . '~', $path)) $ok = true;
        if (str_starts_with($path, '/pro/bientot') || str_starts_with($path, '/pro/modules')) $ok = true;
        if (!$ok) return 'Ton rôle « ' . $r['name'] . ' » ne donne pas accès à cette page.';
        $evs = array_filter(array_map('intval', explode(',', (string)$r['event_ids'])));
        if ($eventId && $evs && !in_array($eventId, $evs, true)) return 'Ton rôle « ' . $r['name'] . ' » ne donne pas accès à cet événement.';
        return null;
    }

    // ---------- tâches de fond ----------
    public static function run(): int
    {
        $n = 0;
        foreach (['endReports', 'syncContacts', 'sendCapi', 'autoCodes', 'referrals', 'notifyFollowers'] as $f) {
            try { $n += self::$f(); } catch (Throwable $e) { log_line('taches', 'Extras::' . $f . ' : ' . $e->getMessage()); }
        }
        return $n;
    }

    private static function once(string $key, int $seconds): bool
    {
        $k = 'extras_' . $key;
        if (time() - (int)setting($k, 0) < $seconds) return false;
        set_setting($k, (string)time());
        return true;
    }

    /** Rapport de fin d'événement : le lendemain, un e-mail aux responsables avec le lien du rapport PDF. */
    public static function endReports(): int
    {
        if (!self::once('reports', 1800)) return 0;
        $n = 0;
        foreach (all("SELECT e.*, o.connect_json, o.name org FROM events e JOIN organizers o ON o.id = e.organizer_id WHERE e.report_sent_at IS NULL AND e.status = 'published'
            AND COALESCE(e.ends_at, e.starts_at) BETWEEN NOW() - INTERVAL 4 DAY AND NOW() - INTERVAL 12 HOUR LIMIT 10") as $ev) {
            update('events', ['report_sent_at' => now()], 'id = ?', [$ev['id']]);
            if (!self::settings($ev)['end_report']) continue;
            $sold = (int)val("SELECT COUNT(*) FROM tickets WHERE event_id = ? AND status = 'valid'", [$ev['id']]);
            $came = (int)val("SELECT COUNT(*) FROM tickets WHERE event_id = ? AND status = 'valid' AND entries > 0", [$ev['id']]);
            $rev = (int)val("SELECT COALESCE(SUM(total - refunded_amount), 0) FROM orders WHERE event_id = ? AND status = 'paid'", [$ev['id']]);
            $bar = (int)val("SELECT COALESCE(SUM(amount), 0) FROM pos_sales WHERE event_id = ? AND undone = 0 AND kind IN ('sale','refund')", [$ev['id']]);
            foreach (all("SELECT email FROM users WHERE organizer_id = ? AND role = 'owner' AND active = 1", [$ev['organizer_id']]) as $u) {
                try { Mailer::send($u['email'], 'Le bilan de « ' . $ev['name'] . ' »', '<p>Bravo pour « <b>' . e($ev['name']) . '</b> » ! Voici les premiers chiffres :</p><ul><li><b>' . $sold . '</b> billets, <b>' . $came . '</b> présents (' . ($sold ? (int)round($came / $sold * 100) : 0) . ' %)</li><li>Billetterie : <b>' . e(money($rev)) . '</b></li>' . ($bar ? '<li>Bars : <b>' . e(money($bar)) . '</b></li>' : '') . '</ul>'
                    . '<p><a href="' . e(abs_url('/pro/evenements/' . $ev['id'] . '/rapport.pdf')) . '">Télécharger le rapport complet (PDF)</a> · <a href="' . e(abs_url('/pro/evenements/' . $ev['id'] . '/360')) . '">Vue 360</a></p><p style="color:#86868b">Tu peux partager ces chiffres en lecture seule avec un partenaire depuis la Vue 360.</p>'); $n++; } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
            }
        }
        return $n;
    }

    /** Brevo ou Mailchimp : les contacts qui ont accepté les e-mails partent vers la liste choisie. */
    public static function syncContacts(): int
    {
        if (!self::once('sync', 600)) return 0;
        $n = 0;
        foreach (all("SELECT * FROM organizers WHERE connect_json LIKE '%mail_provider%'") as $org) {
            $s = self::settings($org);
            if (!in_array($s['mail_provider'], ['brevo', 'mailchimp'], true) || $s['mail_key'] === '' || $s['mail_list'] === '') continue;
            $rows = all("SELECT LOWER(o.email) email, MAX(o.first_name) fn, MAX(o.last_name) ln FROM orders o WHERE o.organizer_id = ? AND o.status = 'paid' AND o.marketing_optin = 1 AND o.email <> ''
                AND LOWER(o.email) NOT IN (SELECT email FROM contact_sync WHERE organizer_id = ? AND provider = ?) AND LOWER(o.email) NOT IN (SELECT email FROM email_unsubscribes WHERE organizer_id = ?) GROUP BY LOWER(o.email) LIMIT 50", [$org['id'], $org['id'], $s['mail_provider'], $org['id']]);
            foreach ($rows as $r) {
                [$code] = $s['mail_provider'] === 'brevo'
                    ? self::http('POST', 'https://api.brevo.com/v3/contacts', ['email' => $r['email'], 'attributes' => ['FIRSTNAME' => $r['fn'], 'LASTNAME' => $r['ln']], 'listIds' => [(int)$s['mail_list']], 'updateEnabled' => true], ['api-key: ' . $s['mail_key']])
                    : self::http('PUT', 'https://' . (explode('-', $s['mail_key'])[1] ?? 'us1') . '.api.mailchimp.com/3.0/lists/' . rawurlencode($s['mail_list']) . '/members/' . md5($r['email']),
                        ['email_address' => $r['email'], 'status_if_new' => 'subscribed', 'merge_fields' => ['FNAME' => (string)$r['fn'], 'LNAME' => (string)$r['ln']]], ['Authorization: Basic ' . base64_encode('entrevo:' . $s['mail_key'])]);
                q('INSERT INTO contact_sync (organizer_id, email, provider, status, synced_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE status = VALUES(status), synced_at = NOW()', [$org['id'], $r['email'], $s['mail_provider'], $code >= 200 && $code < 300 ? 'ok' : 'err' . $code]);
                $n++;
            }
        }
        return $n;
    }

    /** Commande payée : mise en file des conversions côté serveur (Meta, Google Analytics 4). */
    public static function queueConversion(int $orgId, array $data): void
    {
        $org = one('SELECT * FROM organizers WHERE id = ?', [$orgId]); if (!$org) return;
        $s = self::settings($org);
        $o = !empty($data['ref']) ? one('SELECT * FROM orders WHERE ref = ?', [$data['ref']]) : null; if (!$o) return;
        $value = round((int)$o['total'] / 100, 2);
        if ($s['meta_pixel'] !== '' && $s['meta_token'] !== '') {
            $ev = ['event_name' => 'Purchase', 'event_time' => time(), 'event_id' => 'order-' . $o['ref'], 'action_source' => 'website', 'event_source_url' => abs_url('/e/' . (string)val('SELECT slug FROM events WHERE id = ?', [$o['event_id']])),
                'user_data' => array_filter(['em' => [hash('sha256', strtolower(trim((string)$o['email'])))], 'ph' => $o['phone'] ? [hash('sha256', preg_replace('~\D~', '', (string)$o['phone']))] : null, 'fn' => $o['first_name'] ? [hash('sha256', mb_strtolower(trim((string)$o['first_name'])))] : null, 'client_ip_address' => $o['ip'] ?: null]),
                'custom_data' => ['currency' => 'EUR', 'value' => $value, 'order_id' => $o['ref'], 'num_items' => (int)val("SELECT COALESCE(SUM(qty), 0) FROM order_items WHERE order_id = ?", [$o['id']])]];
            insert('capi_queue', ['organizer_id' => $orgId, 'provider' => 'meta', 'order_id' => $o['id'], 'payload' => json_encode(['data' => [$ev]] + ($s['meta_test'] !== '' ? ['test_event_code' => $s['meta_test']] : [])), 'created_at' => now()]);
        }
        if ($s['ga4_id'] !== '' && $s['ga4_secret'] !== '') {
            insert('capi_queue', ['organizer_id' => $orgId, 'provider' => 'ga4', 'order_id' => $o['id'], 'payload' => json_encode(['client_id' => substr(md5((string)$o['email']), 0, 10) . '.' . strtotime((string)$o['created_at']),
                'events' => [['name' => 'purchase', 'params' => ['currency' => 'EUR', 'value' => $value, 'transaction_id' => $o['ref']]]]]), 'created_at' => now()]);
        }
    }

    public static function sendCapi(): int
    {
        $n = 0;
        foreach (all("SELECT q.*, o.connect_json FROM capi_queue q JOIN organizers o ON o.id = q.organizer_id WHERE q.status = 'pending' AND q.attempts < 5 ORDER BY q.id LIMIT 30") as $q) {
            $s = self::settings($q);
            [$code, $resp] = $q['provider'] === 'meta'
                ? self::http('POST', 'https://graph.facebook.com/v19.0/' . rawurlencode($s['meta_pixel']) . '/events?access_token=' . rawurlencode($s['meta_token']), json_decode($q['payload'], true))
                : self::http('POST', 'https://www.google-analytics.com/mp/collect?measurement_id=' . rawurlencode($s['ga4_id']) . '&api_secret=' . rawurlencode($s['ga4_secret']), json_decode($q['payload'], true));
            $ok = $code >= 200 && $code < 300;
            update('capi_queue', ['status' => $ok ? 'sent' : ((int)$q['attempts'] >= 4 ? 'failed' : 'pending'), 'attempts' => (int)$q['attempts'] + 1, 'response' => mb_substr($code . ' ' . $resp, 0, 500), 'sent_at' => $ok ? now() : null], 'id = ?', [$q['id']]);
            $n++;
        }
        return $n;
    }

    /** Code promo unique (une utilisation) sur le prochain événement en vente de l'organisateur. */
    private static function giveCode(array $org, string $email, string $first, string $scenario, string $ref, int $pct, string $subject, string $intro): bool
    {
        if (val('SELECT id FROM auto_codes WHERE organizer_id = ? AND email = ? AND scenario = ? AND ref = ?', [$org['id'], $email, $scenario, $ref])) return false;
        if (val('SELECT 1 FROM email_unsubscribes WHERE organizer_id = ? AND email = ?', [$org['id'], $email])) return false;
        $ev = one("SELECT * FROM events WHERE organizer_id = ? AND status = 'published' AND starts_at > NOW() ORDER BY starts_at LIMIT 1", [$org['id']]);
        if (!$ev) return false;
        do { $code = strtoupper(substr($scenario, 0, 4)) . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)); } while (val('SELECT id FROM promos WHERE event_id = ? AND code = ?', [$ev['id'], $code]));
        insert('promos', ['event_id' => $ev['id'], 'code' => $code, 'kind' => 'percent', 'value' => $pct, 'max_uses' => 1, 'used' => 0, 'valid_until' => date('Y-m-d 23:59:59', strtotime('+30 days')), 'active' => 1, 'once_per_email' => 1, 'batch' => 'auto-' . $scenario, 'label' => 'Code automatique · ' . $scenario]);
        insert('auto_codes', ['organizer_id' => $org['id'], 'email' => $email, 'scenario' => $scenario, 'ref' => $ref, 'code' => $code, 'event_id' => $ev['id'], 'created_at' => now()]);
        try { Mailer::send($email, $subject, '<p>' . ($first ? 'Bonjour ' . e($first) . ',' : 'Bonjour,') . '</p><p>' . $intro . '</p><p style="font-size:22px"><b>' . e($code) . '</b> : −' . $pct . ' % sur « ' . e($ev['name']) . ' » (' . e(fr_date($ev['starts_at'], false)) . ')</p><p><a href="' . e(abs_url('/e/' . $ev['slug'], ['code' => $code])) . '">J\'en profite</a></p><p style="color:#86868b">Code personnel, valable 30 jours, une seule utilisation. ' . e($org['name']) . '</p>'); } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
        return true;
    }

    /** Scénarios : anniversaire, retour après plus d'un an, merci après l'événement. */
    public static function autoCodes(): int
    {
        if (!self::once('autocodes', 3600)) return 0;
        $n = 0;
        foreach (all("SELECT * FROM organizers WHERE connect_json LIKE '%auto_%'") as $org) {
            $s = self::settings($org);
            if ($s['auto_birthday']) foreach (all("SELECT LOWER(email) email, MAX(first_name) fn FROM orders WHERE organizer_id = ? AND status = 'paid' AND marketing_optin = 1 AND birth_date IS NOT NULL AND DATE_FORMAT(birth_date, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d') GROUP BY LOWER(email) LIMIT 200", [$org['id']]) as $r)
                $n += (int)self::giveCode($org, $r['email'], (string)$r['fn'], 'birthday', date('Y'), (int)$s['auto_birthday_pct'], 'Joyeux anniversaire de la part de ' . $org['name'], 'Joyeux anniversaire ! Pour fêter ça, voici un cadeau pour ta prochaine sortie.');
            if ($s['auto_comeback']) foreach (all("SELECT LOWER(email) email, MAX(first_name) fn FROM orders WHERE organizer_id = ? AND status = 'paid' AND marketing_optin = 1 GROUP BY LOWER(email) HAVING MAX(created_at) < NOW() - INTERVAL 365 DAY AND MAX(created_at) > NOW() - INTERVAL 1000 DAY LIMIT 100", [$org['id']]) as $r)
                $n += (int)self::giveCode($org, $r['email'], (string)$r['fn'], 'comeback', date('Y'), (int)$s['auto_comeback_pct'], 'Tu nous manques · ' . $org['name'], 'Ça fait un moment ! On serait ravis de te revoir : voici une réduction pour revenir.');
            if ($s['auto_thanks']) foreach (all("SELECT e.id, LOWER(o.email) email, MAX(o.first_name) fn FROM events e JOIN orders o ON o.event_id = e.id WHERE e.organizer_id = ? AND COALESCE(e.ends_at, e.starts_at) BETWEEN NOW() - INTERVAL 3 DAY AND NOW() - INTERVAL 20 HOUR AND o.status = 'paid' AND o.marketing_optin = 1
                AND EXISTS (SELECT 1 FROM tickets t WHERE t.order_id = o.id AND t.entries > 0) GROUP BY e.id, LOWER(o.email) LIMIT 300", [$org['id']]) as $r)
                $n += (int)self::giveCode($org, $r['email'], (string)$r['fn'], 'merci', 'ev' . $r['id'], (int)$s['auto_thanks_pct'], 'Merci d\'être venu(e) !', 'Merci d\'être venu(e) ! Pour la suite, voici une réduction sur notre prochain événement.');
        }
        return $n;
    }

    /** Parrainage : X amis venus par le lien d'un acheteur = un code de réduction pour lui. */
    public static function referrals(): int
    {
        if (!self::once('referrals', 1800)) return 0;
        $n = 0;
        foreach (all("SELECT * FROM organizers WHERE connect_json LIKE '%referral%'") as $org) {
            $s = self::settings($org); if (!$s['referral']) continue;
            foreach (all("SELECT r.id, LOWER(r.email) email, r.first_name, COUNT(f.id) n FROM orders f JOIN orders r ON r.id = f.referrer_order_id WHERE r.organizer_id = ? AND f.status = 'paid' GROUP BY r.id HAVING n >= ? LIMIT 100", [$org['id'], max(1, (int)$s['referral_n'])]) as $r)
                $n += (int)self::giveCode($org, $r['email'], (string)$r['first_name'], 'parrain', 'o' . $r['id'], (int)$s['referral_pct'], 'Merci pour tes invitations !', (int)$r['n'] . ' ami(s) ont pris leur billet grâce à ton lien. Merci ! Voici ta récompense.');
        }
        return $n;
    }

    /** Abonnés d'un organisateur : prévenus quand un nouvel événement est mis en vente. */
    public static function notifyFollowers(): int
    {
        if (!self::once('followers', 900)) return 0;
        $n = 0;
        foreach (all("SELECT e.*, o.name org, o.slug oslug FROM events e JOIN organizers o ON o.id = e.organizer_id WHERE e.status = 'published' AND e.followers_notified_at IS NULL AND e.starts_at > NOW() AND e.created_at >= NOW() - INTERVAL 30 DAY LIMIT 5") as $ev) {
            update('events', ['followers_notified_at' => now()], 'id = ?', [$ev['id']]);
            foreach (all('SELECT * FROM org_followers WHERE organizer_id = ? AND unsub_at IS NULL LIMIT 5000', [$ev['organizer_id']]) as $f) {
                try { Mailer::send($f['email'], 'Nouveau : ' . $ev['name'], '<p><b>' . e($ev['org']) . '</b> vient de mettre en vente un nouvel événement :</p><p style="font-size:20px"><b>' . e($ev['name']) . '</b><br>' . e(fr_date($ev['starts_at'])) . ($ev['city'] ? ' · ' . e($ev['city']) : '') . '</p><p><a href="' . e(abs_url('/e/' . $ev['slug'])) . '">Voir et réserver</a></p><p style="color:#86868b"><a href="' . e(abs_url('/suivre/' . $f['token'])) . '">Ne plus suivre ' . e($ev['org']) . '</a></p>'); $n++; } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
            }
        }
        return $n;
    }

    /** @return array{0:int,1:string} */
    public static function http(string $method, string $url, array $body, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers)]);
        $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        return [$code, mb_substr($res === false ? $err : (string)$res, 0, 400)];
    }
}
