<?php
declare(strict_types=1);

/**
 * Campagnes e-mail des organisateurs.
 *
 * Audience (JSON) : source = 'optin' (contacts ayant coché « recevoir les actus ») ou 'customers'
 * (clients ayant acheté : prospection pour des événements analogues, autorisée avec désinscription
 * dans chaque e-mail), filtres facultatifs : events (venus à…), not_event (pas encore inscrits à…), segment.
 * Contenu (JSON) : liste de blocs {type: heading|text|button|event|image|divider, …}.
 * Envoi : les destinataires sont figés au lancement, puis envoyés par lots par les tâches de fond.
 * Suivi : pixel d'ouverture, redirection de clics signée, ventes attribuées via utm_campaign = c{id}.
 */
final class Campaigns
{
    public const STATUS = ['draft' => ['Brouillon', 'grey'], 'scheduled' => ['Programmée', 'orange'], 'sending' => ['En cours d\'envoi', 'orange'], 'sent' => ['Envoyée', 'green'], 'cancelled' => ['Annulée', 'red']];
    public const SEGMENTS = ['' => 'Tout le monde', 'fideles' => 'Fidèles (2 événements ou plus)', 'gros' => '100 € dépensés ou plus', 'recents' => 'Achat dans les 30 derniers jours', 'dormants' => 'Pas d\'achat depuis 6 mois'];
    public const BATCH = 40;

    public static function badge(string $s): string { [$l, $c] = self::STATUS[$s] ?? [$s, 'grey']; return '<span class="badge ' . $c . '">' . e($l) . '</span>'; }

    public static function get(int $orgId, int $id): ?array { return one('SELECT * FROM campaigns WHERE id = ? AND organizer_id = ?', [$id, $orgId]); }

    public static function blocks(array $c): array { return json_decode((string)$c['blocks'], true) ?: []; }

    public static function audience(array $c): array
    {
        return (json_decode((string)$c['audience'], true) ?: []) + ['source' => 'optin', 'events' => [], 'not_event' => 0, 'segment' => ''];
    }

    /** Destinataires (e-mail en minuscules => prénom) selon l'audience, désinscrits exclus. */
    public static function recipients(int $orgId, array $a): array
    {
        $w = "o.organizer_id = ? AND o.status = 'paid' AND o.email IS NOT NULL AND o.email <> '' AND (o.channel IS NULL OR o.channel <> 'systeme')";
        $p = [$orgId];
        $evs = array_values(array_filter(array_map('intval', (array)$a['events'])));
        if ($evs) $w .= ' AND LOWER(o.email) IN (SELECT LOWER(email) FROM orders WHERE status = \'paid\' AND event_id IN (' . in_list($evs) . '))';
        if ((int)$a['not_event']) { $w .= ' AND LOWER(o.email) NOT IN (SELECT LOWER(email) FROM orders WHERE status = \'paid\' AND event_id = ? AND email IS NOT NULL)'; $p[] = (int)$a['not_event']; }
        $w .= ' AND LOWER(o.email) NOT IN (SELECT email FROM email_unsubscribes WHERE organizer_id = ?)'; $p[] = $orgId;
        $having = ['HAVING 1'];
        if ($a['source'] !== 'customers') $having[] = 'MAX(o.marketing_optin) = 1';
        $having[] = match ((string)$a['segment']) {
            'fideles' => 'COUNT(DISTINCT o.event_id) >= 2',
            'gros' => 'SUM(o.total - o.refunded_amount) >= 10000',
            'recents' => 'MAX(o.created_at) >= NOW() - INTERVAL 30 DAY',
            'dormants' => 'MAX(o.created_at) < NOW() - INTERVAL 180 DAY',
            default => '1',
        };
        $rows = all("SELECT LOWER(o.email) email, SUBSTRING_INDEX(GROUP_CONCAT(o.first_name ORDER BY o.created_at DESC SEPARATOR '\x1f'), '\x1f', 1) first_name
            FROM orders o WHERE $w GROUP BY LOWER(o.email) " . implode(' AND ', $having) . ' LIMIT 50000', $p);
        $out = [];
        foreach ($rows as $r) if (is_email($r['email'])) $out[$r['email']] = $r['first_name'];
        return $out;
    }

    public static function counts(int $orgId): array
    {
        return [
            'optin' => count(self::recipients($orgId, ['source' => 'optin', 'events' => [], 'not_event' => 0, 'segment' => ''])),
            'customers' => count(self::recipients($orgId, ['source' => 'customers', 'events' => [], 'not_event' => 0, 'segment' => ''])),
            'unsub' => (int)val('SELECT COUNT(*) FROM email_unsubscribes WHERE organizer_id = ?', [$orgId]),
        ];
    }

    // ---------- Rendu ----------
    private static function sig(string $s): string { return substr(hash_hmac('sha256', $s, (string)config('secret', 'entrevo')), 0, 16); }

    /** Lien suivi : /c/{jeton}/l?u=…&s=… (signé pour éviter toute redirection ouverte). */
    public static function track(string $url, ?array $r): string
    {
        if (!$r) return $url;
        return abs_url('/c/' . $r['token'] . '/l', ['u' => $url, 's' => self::sig($r['token'] . '|' . $url)]);
    }

    public static function checkSig(string $token, string $url, string $sig): bool { return hash_equals(self::sig($token . '|' . $url), $sig); }

    public static function eventUrl(array $c, array $ev): string
    {
        return abs_url('/e/' . $ev['slug'], ['utm_source' => 'email', 'utm_medium' => 'campagne', 'utm_campaign' => 'c' . $c['id']]);
    }

    /** Ajoute les paramètres UTM de la campagne aux liens qui pointent vers le site. */
    private static function utm(array $c, string $url): string
    {
        $host = parse_url(abs_url('/'), PHP_URL_HOST);
        if (parse_url($url, PHP_URL_HOST) !== $host || str_contains($url, 'utm_campaign=')) return $url;
        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query(['utm_source' => 'email', 'utm_medium' => 'campagne', 'utm_campaign' => 'c' . $c['id']]);
    }

    /**
     * HTML complet de l'e-mail (styles en ligne, compatible messageries).
     * $r = destinataire (null pour l'aperçu) ; $org = organisateur.
     */
    public static function render(array $c, array $org, ?array $r = null): string
    {
        $color = '#0032fa';
        $first = $r['first_name'] ?? 'Camille';
        $sub = fn(string $s) => str_replace(['{prenom}', '{prénom}'], (string)$first, $s);
        $btn = fn(string $label, string $url) => '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:18px 0"><tr><td style="border-radius:12px;background:' . $color . '">'
            . '<a href="' . e(self::track(self::utm($c, $url), $r)) . '" style="display:inline-block;padding:14px 22px;color:#ffffff;font-weight:bold;text-decoration:none;font-size:16px">' . e($label) . '</a></td></tr></table>';
        $body = '';
        foreach (self::blocks($c) as $b) {
            switch ($b['type'] ?? '') {
                case 'heading': $body .= '<h2 style="margin:22px 0 8px;font-size:24px;line-height:1.2;color:#0f0f14">' . e($sub((string)($b['text'] ?? ''))) . '</h2>'; break;
                case 'text': $body .= '<div style="margin:0 0 14px;font-size:16px;line-height:1.55;white-space:pre-line">' . self::inline(e($sub((string)($b['text'] ?? '')))) . '</div>'; break;
                case 'button': if (!empty($b['url'])) $body .= $btn((string)($b['label'] ?: 'J\'y vais'), (string)$b['url']); break;
                case 'divider': $body .= '<hr style="border:0;border-top:1px solid #e1e7ef;margin:22px 0">'; break;
                case 'image': if (!empty($b['url'])) $body .= '<img src="' . e((string)$b['url']) . '" alt="" style="display:block;width:100%;max-width:536px;border-radius:12px;margin:10px 0">'; break;
                case 'event':
                    $ev = one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [(int)($b['event_id'] ?? 0), $org['id']]);
                    if (!$ev) break;
                    $min = val('SELECT MIN(price) FROM rates WHERE event_id = ? AND active = 1 AND hidden = 0', [$ev['id']]);
                    $body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border:1px solid #e1e7ef;border-radius:14px;overflow:hidden"><tr>'
                        . ($ev['image'] ? '<td width="170" style="vertical-align:top"><img src="' . e(abs_url($ev['image'])) . '" width="170" alt="" style="display:block;width:170px;height:auto"></td>' : '')
                        . '<td style="padding:14px 16px;vertical-align:top"><div style="font-size:12px;font-weight:bold;color:' . $color . ';text-transform:uppercase;letter-spacing:.06em">' . e(fr_date($ev['starts_at'])) . '</div>'
                        . '<div style="font-size:20px;font-weight:bold;margin:4px 0;color:#0f0f14">' . e($ev['name']) . '</div>'
                        . '<div style="font-size:14px;color:#667085">' . e(trim($ev['venue'] . ($ev['city'] ? ' · ' . $ev['city'] : ''), ' ·')) . ($min !== null ? ' · ' . ((int)$min ? 'dès ' . e(money((int)$min)) : 'gratuit') : '') . '</div>'
                        . $btn((string)($b['label'] ?? '') ?: 'Je prends ma place 🎟️', self::eventUrl($c, $ev)) . '</td></tr></table>';
                    break;
            }
        }
        $unsub = $r ? abs_url('/desinscription/' . $r['token']) : '#';
        $pixel = $r ? '<img src="' . e(abs_url('/c/' . $r['token'] . '/o.gif')) . '" width="1" height="1" alt="" style="display:block;border:0">' : '';
        $logo = $org['logo'] ? '<img src="' . e(abs_url($org['logo'])) . '" alt="" height="40" style="display:block;height:40px;margin-bottom:6px">' : '';
        $pre = trim((string)$c['preheader']) !== '' ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . e($sub((string)$c['preheader'])) . '</div>' : '';
        return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($c['subject']) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f0f5fa;font-family:Arial,Helvetica,sans-serif;color:#1c1c28">' . $pre
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0f5fa"><tr><td align="center" style="padding:24px 12px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px">'
            . '<tr><td style="padding:0 8px 14px">' . $logo . '<div style="font-weight:bold;font-size:15px;color:#0f0f14">' . e($org['name']) . '</div></td></tr>'
            . '<tr><td style="background:#ffffff;border-radius:18px;padding:28px 32px">' . ($body ?: '<p style="color:#98a2b3">Ajoute des blocs à ton e-mail 👈</p>') . '</td></tr>'
            . '<tr><td style="padding:18px 8px;font-size:12px;line-height:1.5;color:#667085;text-align:center">'
            . 'Tu reçois cet e-mail car tu as participé à un événement de ' . e($org['name']) . '.<br>'
            . '<a href="' . e($unsub) . '" style="color:#667085">Ne plus recevoir les actus de ' . e($org['name']) . '</a>'
            . ($org['address'] ? '<br>' . e($org['legal_name'] ?: $org['name']) . ' · ' . e($org['address']) : '')
            . '<br>Envoyé avec ' . e(setting('platform_name')) . '</td></tr></table>' . $pixel . '</td></tr></table></body></html>';
    }

    /** Gras **texte** et liens nus dans les blocs texte (après échappement). */
    private static function inline(string $escaped): string
    {
        $s = preg_replace('~\*\*(.+?)\*\*~u', '<b>$1</b>', $escaped);
        return (string)preg_replace('~(https?://[^\s<]+)~u', '<a href="$1" style="color:#0032fa">$1</a>', (string)$s);
    }

    public static function text(array $c): string
    {
        $out = [];
        foreach (self::blocks($c) as $b) {
            if (in_array($b['type'] ?? '', ['heading', 'text'], true)) $out[] = (string)($b['text'] ?? '');
            if (($b['type'] ?? '') === 'button' && !empty($b['url'])) $out[] = ($b['label'] ?? 'Lien') . ' : ' . $b['url'];
        }
        return implode("\n\n", $out);
    }

    // ---------- Envoi ----------
    /** Fige la liste des destinataires et passe la campagne en envoi. */
    public static function launch(array $c): int
    {
        $list = self::recipients((int)$c['organizer_id'], self::audience($c));
        foreach ($list as $email => $first) {
            q('INSERT IGNORE INTO campaign_recipients (campaign_id, email, first_name, token) VALUES (?, ?, ?, ?)', [$c['id'], $email, $first ? mb_substr((string)$first, 0, 80) : null, token(16)]);
        }
        update('campaigns', ['status' => 'sending', 'started_at' => now(), 'recipients' => count($list)], 'id = ?', [$c['id']]);
        return count($list);
    }

    public static function sendOne(array $c, array $org, array $r): void
    {
        $html = self::render($c, $org, $r);
        $subject = str_replace(['{prenom}', '{prénom}'], (string)($r['first_name'] ?? ''), (string)$c['subject']);
        Mailer::sendRaw((string)$r['email'], $subject, $html, self::text($c) . "\n\n—\nSe désinscrire : " . abs_url('/desinscription/' . $r['token']),
            (string)$org['name'], (string)$org['email'], abs_url('/desinscription/' . $r['token']));
    }

    /** Tâche de fond : lance les campagnes programmées et envoie un lot. */
    public static function process(int $budget = self::BATCH): int
    {
        foreach (all("SELECT * FROM campaigns WHERE status = 'scheduled' AND scheduled_at <= NOW() LIMIT 5") as $c) self::launch($c);
        $sent = 0;
        foreach (all("SELECT * FROM campaigns WHERE status = 'sending' ORDER BY started_at LIMIT 3") as $c) {
            $org = one('SELECT * FROM organizers WHERE id = ?', [$c['organizer_id']]);
            $rows = all('SELECT * FROM campaign_recipients WHERE campaign_id = ? AND sent_at IS NULL AND error IS NULL LIMIT ' . max(1, $budget - $sent), [$c['id']]);
            foreach ($rows as $r) {
                try { self::sendOne($c, $org, $r); update('campaign_recipients', ['sent_at' => now()], 'id = ?', [$r['id']]); $sent++; set_setting('campaign_err_' . $c['id'], ''); }
                catch (Throwable $e) {
                    log_line('emails', 'Campagne ' . $c['id'] . ' : ' . $e->getMessage());
                    // panne générale (expéditeur, serveur SMTP, fonction mail) : on met en pause sans « griller » les destinataires
                    if (self::isGlobal($e->getMessage())) { set_setting('campaign_err_' . $c['id'], mb_substr($e->getMessage(), 0, 250)); return $sent; }
                    update('campaign_recipients', ['error' => mb_substr($e->getMessage(), 0, 250)], 'id = ?', [$r['id']]);
                }
            }
            if (!val('SELECT id FROM campaign_recipients WHERE campaign_id = ? AND sent_at IS NULL AND error IS NULL LIMIT 1', [$c['id']])) update('campaigns', ['status' => 'sent', 'sent_at' => now()], 'id = ?', [$c['id']]);
            if ($sent >= $budget) break;
        }
        return $sent;
    }

    private static function isGlobal(string $msg): bool
    {
        foreach (['(From)', 'SMTP connect', 'Could not instantiate', 'SMTP Error: Could not authenticate', 'Connection refused', 'timed out', 'SMTP server error'] as $k) if (stripos($msg, $k) !== false) return true;
        return false;
    }

    /** Dernière panne générale d'envoi (affichée dans le rapport), ou ''. */
    public static function lastError(array $c): string { return (string)setting('campaign_err_' . $c['id'], ''); }

    public static function stats(array $c): array
    {
        $r = one('SELECT COUNT(*) total, SUM(sent_at IS NOT NULL) sent, SUM(opened_at IS NOT NULL) opened, SUM(clicked_at IS NOT NULL) clicked, SUM(unsubscribed_at IS NOT NULL) unsub, SUM(error IS NOT NULL) errors
            FROM campaign_recipients WHERE campaign_id = ?', [$c['id']]);
        $o = one("SELECT COUNT(*) n, COALESCE(SUM(total),0) revenue, COALESCE(SUM((SELECT SUM(qty) FROM order_items i WHERE i.order_id = orders.id)),0) tickets FROM orders WHERE organizer_id = ? AND status = 'paid' AND utm_campaign = ?", [$c['organizer_id'], 'c' . $c['id']]);
        $sent = (int)$r['sent'];
        $pct = fn($n) => $sent ? round($n / $sent * 100, 1) : 0;
        return ['total' => (int)$r['total'], 'sent' => $sent, 'opened' => (int)$r['opened'], 'clicked' => (int)$r['clicked'], 'unsub' => (int)$r['unsub'], 'errors' => (int)$r['errors'],
            'open_rate' => $pct((int)$r['opened']), 'click_rate' => $pct((int)$r['clicked']), 'orders' => (int)$o['n'], 'revenue' => (int)$o['revenue'], 'tickets' => (int)$o['tickets']];
    }

    /** Modèles de départ. */
    public static function templates(array $upcoming): array
    {
        $ev = $upcoming[0]['id'] ?? 0;
        return [
            'annonce' => ['📣 Annonce d\'événement', 'Ton prochain plan est là', [['type' => 'heading', 'text' => 'Salut {prenom}, on remet ça ! 🎉'], ['type' => 'text', 'text' => "Tu étais là la dernière fois, alors on te prévient en premier.\nLes places partent vite : réserve la tienne avant tout le monde."], ['type' => 'event', 'event_id' => $ev, 'label' => 'Je réserve 🎟️']]],
            'dernieres' => ['🔥 Dernières places', 'Plus que quelques places…', [['type' => 'heading', 'text' => 'Plus que quelques places 😬'], ['type' => 'text', 'text' => "Hé {prenom}, c'est presque complet.\nSi tu hésitais encore, c'est **maintenant ou jamais**."], ['type' => 'event', 'event_id' => $ev, 'label' => 'Je fonce 🏃']]],
            'merci' => ['🙏 Merci d\'être venu', 'Merci pour hier soir !', [['type' => 'heading', 'text' => 'Merci {prenom} ! 🙌'], ['type' => 'text', 'text' => "Quelle soirée ! Merci d'avoir été là.\nOn prépare déjà la suite, et tu seras le premier au courant."], ['type' => 'divider'], ['type' => 'text', 'text' => 'Un souvenir, une photo, un avis ? Réponds simplement à cet e-mail 💌']]],
            'vide' => ['📝 Page blanche', '', [['type' => 'heading', 'text' => 'Salut {prenom} 👋'], ['type' => 'text', 'text' => '']]],
        ];
    }
}
