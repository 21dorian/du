<?php
declare(strict_types=1);

/**
 * 2.2 : données personnelles (RGPD).
 * - Demandes publiques (accès, effacement, rectification, opposition), confirmées par e-mail, délai légal d'un mois.
 * - Inventaire de tout ce que la plateforme détient sur une adresse e-mail, export JSON, anonymisation.
 * - Conservation : les commandes des événements terminés depuis plus de N ans sont anonymisées automatiquement.
 * Les montants et références des commandes sont conservés (obligation comptable de 10 ans) ; seules les données
 * qui identifient la personne sont effacées.
 */
final class Gdpr
{
    public const KINDS = ['access' => 'Accès / copie de mes données', 'delete' => 'Effacement de mes données', 'rectify' => 'Rectification', 'oppose' => 'Opposition (plus de messages)'];
    public const STATUS = ['unverified' => ['Non confirmée', 'grey'], 'pending' => ['À traiter', 'red'], 'done' => ['Traitée', 'green'], 'rejected' => ['Refusée', 'grey']];

    public static function badge(string $s): string { [$l, $c] = self::STATUS[$s] ?? [$s, 'grey']; return '<span class="badge ' . $c . '">' . e($l) . '</span>'; }

    /** Sources : libellé => [requête, paramètres] — toutes indexées sur l'adresse e-mail. */
    private static function sources(string $email): array
    {
        $e = strtolower(trim($email));
        return [
            'Comptes utilisateurs' => ['SELECT id, name, email, role, organizer_id, created_at, last_login FROM users WHERE LOWER(email) = ?', [$e]],
            'Commandes' => ["SELECT o.id, o.ref, o.created_at, o.status, o.first_name, o.last_name, o.email, o.phone, o.birth_date, o.ship_address, o.answers, o.total, o.marketing_optin, e.name event FROM orders o LEFT JOIN events e ON e.id = o.event_id WHERE LOWER(o.email) = ?", [$e]],
            'Billets à son nom' => ['SELECT t.id, t.code, t.first_name, t.last_name, t.company, t.holder_email, t.answers, t.status, t.checked_in_at FROM tickets t JOIN orders o ON o.id = t.order_id WHERE LOWER(o.email) = ? OR LOWER(t.holder_email) = ?', [$e, $e]],
            'Billets transmis' => ['SELECT id, from_name, from_email, to_name, to_email, created_at FROM ticket_transfers WHERE LOWER(from_email) = ? OR LOWER(to_email) = ?', [$e, $e]],
            'Listes d\'attente' => ['SELECT id, event_id, first_name, last_name, email, status, created_at FROM waitlist WHERE LOWER(email) = ?', [$e]],
            'Préventes' => ['SELECT id, event_id, email, created_at FROM presale_access WHERE LOWER(email) = ?', [$e]],
            'Avis' => ['SELECT r.id, r.rating, r.comment, r.author, r.created_at FROM reviews r JOIN orders o ON o.id = r.order_id WHERE LOWER(o.email) = ?', [$e]],
            'Campagnes reçues' => ['SELECT id, campaign_id, email, first_name, sent_at, opened_at, clicked_at FROM campaign_recipients WHERE LOWER(email) = ?', [$e]],
            'Désinscriptions' => ['SELECT organizer_id, email, created_at FROM email_unsubscribes WHERE LOWER(email) = ?', [$e]],
            'Bénévolat' => ['SELECT id, organizer_id, first_name, last_name, email, phone, team, notes, created_at FROM staff_members WHERE LOWER(email) = ?', [$e]],
            'Cartes cadeaux' => ['SELECT id, code, buyer_email, recipient_email, recipient_name, message, balance FROM gift_cards WHERE LOWER(buyer_email) = ? OR LOWER(recipient_email) = ?', [$e, $e]],
            'Abonnements' => ['SELECT id, code, first_name, last_name, email, status, created_at FROM passes WHERE LOWER(email) = ?', [$e]],
            'Support' => ['SELECT id, ref, name, email, subject, status, created_at FROM support_tickets WHERE LOWER(email) = ?', [$e]],
            'Intérêt pour une fonctionnalité' => ['SELECT id, feature, email, created_at FROM public_interest WHERE LOWER(email) = ?', [$e]],
            'Ambassadeurs' => ['SELECT id, event_id, name, email, created_at FROM tracking_links WHERE LOWER(email) = ?', [$e]],
        ];
    }

    /** @return array<string, array> libellé => lignes */
    public static function inventory(string $email): array
    {
        $out = [];
        foreach (self::sources($email) as $label => [$sql, $p]) { try { $rows = all($sql, $p); } catch (Throwable $ex) { $rows = []; } if ($rows) $out[$label] = $rows; }
        // SMS : par numéro de téléphone des commandes
        $phones = array_values(array_unique(array_filter(array_map(fn($r) => class_exists('Sms') ? Sms::normalize((string)$r['phone']) : null, $out['Commandes'] ?? []))));
        if ($phones) { $sms = all('SELECT id, campaign_id, phone, first_name, status, sent_at FROM sms_messages WHERE phone IN (' . implode(',', array_fill(0, count($phones), '?')) . ')', $phones); if ($sms) $out['SMS reçus'] = $sms; }
        return $out;
    }

    public static function exportJson(string $email): string
    {
        return json_encode(['plateforme' => setting('platform_name'), 'personne' => $email, 'genere_le' => date('c'), 'donnees' => self::inventory($email)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Anonymise tout ce qui identifie la personne. Retourne le nombre de lignes touchées. */
    public static function anonymize(string $email): int
    {
        $e = strtolower(trim($email));
        $alias = 'anonyme-' . substr(hash('sha256', $e . config('secret', 'x')), 0, 12) . '@anonyme.invalid';
        $n = 0;
        $run = function (string $sql, array $p) use (&$n) { try { $n += q($sql, $p)->rowCount(); } catch (Throwable $ex) { log_line('rgpd', $ex->getMessage()); } };
        $orderIds = array_map('intval', array_column(all('SELECT id FROM orders WHERE LOWER(email) = ?', [$e]), 'id'));
        if ($orderIds) {
            $in = in_list($orderIds);
            $run("UPDATE tickets SET first_name = NULL, last_name = NULL, company = NULL, answers = NULL, holder_email = NULL WHERE order_id IN ($in)", []);
            $run("UPDATE reviews SET author = 'Anonyme', comment = NULL WHERE order_id IN ($in)", []);
        }
        $run("UPDATE orders SET first_name = 'Anonyme', last_name = NULL, email = ?, phone = NULL, birth_date = NULL, ship_address = NULL, answers = NULL, marketing_optin = 0 WHERE LOWER(email) = ?", [$alias, $e]);
        $run('UPDATE tickets SET holder_email = NULL WHERE LOWER(holder_email) = ?', [$e]);
        $run("UPDATE ticket_transfers SET from_email = IF(LOWER(from_email) = ?, ?, from_email), from_name = IF(LOWER(from_email) = ?, 'Anonyme', from_name), to_email = IF(LOWER(to_email) = ?, ?, to_email), to_name = IF(LOWER(to_email) = ?, 'Anonyme', to_name) WHERE LOWER(from_email) = ? OR LOWER(to_email) = ?", [$e, $alias, $e, $e, $alias, $e, $e, $e]);
        foreach (['DELETE FROM waitlist WHERE LOWER(email) = ?', 'DELETE FROM presale_access WHERE LOWER(email) = ?', 'DELETE FROM campaign_recipients WHERE LOWER(email) = ?', 'DELETE FROM public_interest WHERE LOWER(email) = ?'] as $sql) $run($sql, [$e]);
        $run("UPDATE staff_members SET first_name = 'Anonyme', last_name = NULL, email = NULL, phone = NULL, notes = NULL, skills = NULL WHERE LOWER(email) = ?", [$e]);
        $run("UPDATE gift_cards SET buyer_email = IF(LOWER(buyer_email) = ?, ?, buyer_email), recipient_email = IF(LOWER(recipient_email) = ?, NULL, recipient_email), recipient_name = IF(LOWER(recipient_email) = ?, NULL, recipient_name), message = NULL WHERE LOWER(buyer_email) = ? OR LOWER(recipient_email) = ?", [$e, $alias, $e, $e, $e, $e]);
        $run("UPDATE passes SET first_name = 'Anonyme', last_name = NULL, email = ? WHERE LOWER(email) = ?", [$alias, $e]);
        $run("UPDATE support_tickets SET name = 'Anonyme', email = ? WHERE LOWER(email) = ?", [$alias, $e]);
        $run("UPDATE tracking_links SET email = NULL WHERE LOWER(email) = ?", [$e]);
        // compte acheteur / bénévole : désactivé et anonymisé (jamais un compte organisateur ou admin)
        $run("UPDATE users SET name = 'Anonyme', email = ?, active = 0, password_hash = '' WHERE LOWER(email) = ? AND role NOT IN ('superadmin','owner')", [$alias, $e]);
        // l'opposition reste mémorisée sous forme d'empreinte pour ne plus jamais écrire à cette adresse
        set_setting('rgpd_block_' . substr(hash('sha256', $e), 0, 24), '1');
        Audit::log('rgpd.anonymized', 'Données anonymisées (' . $n . ' lignes)');
        return $n;
    }

    // ---------- Demandes ----------
    public static function request(string $email, string $kind, string $message): array
    {
        $id = insert('gdpr_requests', ['email' => strtolower(trim($email)), 'kind' => $kind, 'message' => mb_substr($message, 0, 3000) ?: null, 'status' => 'unverified', 'token' => token(24), 'created_at' => now()]);
        $r = one('SELECT * FROM gdpr_requests WHERE id = ?', [$id]);
        try {
            Mailer::send($r['email'], 'Confirme ta demande sur tes données personnelles', '<p>Bonjour,</p><p>Nous avons reçu une demande « ' . e(self::KINDS[$kind]) . ' » pour cette adresse. Pour être sûrs qu\'elle vient bien de toi, confirme-la :</p>'
                . '<p><a href="' . e(abs_url('/donnees-personnelles/confirmer/' . $r['token'])) . '" style="display:inline-block;background:#1d1d1f;color:#fff;padding:11px 20px;border-radius:980px;text-decoration:none">Confirmer ma demande</a></p><p style="color:#86868b;font-size:13px">Tu n\'as rien demandé ? Ignore cet e-mail : rien ne sera fait.</p>');
        } catch (Throwable $ex) { log_line('emails', 'RGPD : ' . $ex->getMessage()); }
        return $r;
    }

    public static function verify(string $token): ?array
    {
        $r = one("SELECT * FROM gdpr_requests WHERE token = ?", [$token]);
        if (!$r) return null;
        if ($r['status'] === 'unverified') {
            update('gdpr_requests', ['status' => 'pending', 'verified_at' => now(), 'due_on' => date('Y-m-d', strtotime('+1 month'))], 'id = ?', [$r['id']]);
            $to = trim((string)setting('dpo_email')) ?: trim((string)setting('contact_email'));
            if ($to) try { Mailer::send($to, '[RGPD] Nouvelle demande : ' . self::KINDS[$r['kind']], '<p>' . e($r['email']) . ' · à traiter avant le ' . e(date('d/m/Y', strtotime('+1 month'))) . '</p><p><a href="' . e(abs_url('/admin/rgpd/' . $r['id'])) . '">Ouvrir la demande</a></p>'); } catch (Throwable $ex) { /* sans gravité */ }
            $r = one('SELECT * FROM gdpr_requests WHERE id = ?', [$r['id']]);
        }
        return $r;
    }

    /** Tâche de fond : conservation limitée des données des acheteurs. */
    public static function retention(int $limit = 200): int
    {
        $years = (int)setting('retention_years', '0');
        if ($years < 1) return 0;
        $rows = all("SELECT o.id FROM orders o JOIN events e ON e.id = o.event_id WHERE o.first_name <> 'Anonyme' AND COALESCE(e.ends_at, e.starts_at) < NOW() - INTERVAL $years YEAR LIMIT " . (int)$limit);
        if (!$rows) return 0;
        $in = in_list(array_map('intval', array_column($rows, 'id')));
        q("UPDATE tickets SET first_name = NULL, last_name = NULL, company = NULL, answers = NULL, holder_email = NULL WHERE order_id IN ($in)");
        q("UPDATE orders SET first_name = 'Anonyme', last_name = NULL, email = CONCAT('anonyme-', id, '@anonyme.invalid'), phone = NULL, birth_date = NULL, ship_address = NULL, answers = NULL WHERE id IN ($in)");
        return count($rows);
    }

    public static function blocked(string $email): bool { return setting('rgpd_block_' . substr(hash('sha256', strtolower(trim($email))), 0, 24)) === '1'; }
}
