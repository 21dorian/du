<?php
declare(strict_types=1);

/**
 * 1.8 : services aux acheteurs.
 * - donner son billet à un ami (nouveau code, l'ancien ne passe plus) ;
 * - ajout à l'agenda (.ics / Google) et rappel la veille ;
 * - relance des paniers abandonnés ;
 * - avis après l'événement.
 */
final class Buyers
{
    public const MAX_TRANSFERS = 3;

    // ---------- Donner son billet ----------

    /** Raison pour laquelle ce billet ne peut pas être donné (null = c'est possible). */
    public static function cannotTransfer(array $t, array $o, array $ev): ?string
    {
        if (empty($ev['transfer_enabled'])) return 'L\'organisateur n\'autorise pas les transferts pour cet événement.';
        if ($o['status'] !== 'paid' || $t['status'] !== 'valid') return 'Ce billet n\'est plus valable.';
        if ((int)$t['entries'] > 0) return 'Ce billet a déjà servi à l\'entrée.';
        if ($t['resale_status'] === 'listed') return 'Ce billet est en vente sur la bourse : retire-le d\'abord.';
        if ((int)$t['transfers'] >= self::MAX_TRANSFERS) return 'Ce billet a déjà été donné ' . self::MAX_TRANSFERS . ' fois : c\'est le maximum.';
        if (strtotime((string)($ev['ends_at'] ?: $ev['starts_at'])) < time()) return 'L\'événement est terminé.';
        return null;
    }

    /** Transfère le billet : nouveau nom, nouveau code, accès personnel pour le destinataire. */
    public static function transfer(array $t, array $o, array $ev, string $first, string $last, string $email, string $fromName, string $fromEmail): array
    {
        $old = $t['code'];
        $code = Tickets::newCode();
        $token = token(20);
        update('tickets', ['first_name' => $first, 'last_name' => $last, 'code' => $code, 'holder_email' => strtolower($email), 'holder_token' => $token,
            'transfers' => (int)$t['transfers'] + 1, 'transferred_at' => now(), 'updated_at' => now()], 'id = ?', [$t['id']]);
        insert('ticket_transfers', ['ticket_id' => $t['id'], 'order_id' => $o['id'], 'event_id' => $ev['id'], 'from_name' => $fromName, 'from_email' => $fromEmail,
            'to_name' => trim($first . ' ' . $last), 'to_email' => strtolower($email), 'old_code' => $old, 'created_at' => now()]);
        try { Audit::log('ticket.transferred', 'Billet ' . $old . ' donné à ' . trim($first . ' ' . $last), (int)$ev['id'], (int)$o['id'], (int)$o['organizer_id']); } catch (Throwable $e) { /* journal facultatif */ }
        $t = one('SELECT * FROM tickets WHERE id = ?', [$t['id']]);
        $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
        $session = $o['session_id'] ? one('SELECT * FROM sessions WHERE id = ?', [$o['session_id']]) : null;
        $link = abs_url('/billet/' . $token);
        try {
            $pdf = TicketPdf::build($o, $ev, $org, [$t]);
            Mailer::send($email, '🎁 ' . $fromName . ' t\'offre ta place pour ' . $ev['name'],
                '<p>Salut ' . e($first) . ' 👋</p><p><b>' . e($fromName) . '</b> te donne son billet pour <b>' . e($ev['name']) . '</b>. Chanceux·se !</p>'
                . '<p style="margin:0;color:#555">' . e(fr_date($session['starts_at'] ?? $ev['starts_at'])) . ($ev['venue'] ? '<br>' . e($ev['venue']) . ($ev['city'] ? ', ' . e($ev['city']) : '') : '') . '</p>'
                . '<p>🎟️ Ton billet est en pièce jointe, à ton nom. Montre le QR code à l\'entrée.</p>'
                . '<p><a href="' . e($link) . '" style="background:' . e(setting('primary_color', '#0032fa')) . ';color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;display:inline-block">Voir mon billet</a></p>'
                . '<p style="color:#777;font-size:13px">' . e(self::calendarLine($ev, $session)) . '</p>',
                [['data' => $pdf, 'name' => 'billet-' . $code . '.pdf']], $org['email'] ?? null);
        } catch (Throwable $e) { log_line('emails', 'Transfert billet #' . $t['id'] . ' : ' . $e->getMessage()); }
        if (is_email($fromEmail)) {
            try { Mailer::send($fromEmail, '✅ Ton billet est bien parti', '<p>C\'est fait : ton billet « ' . e($t['rate_name']) . ' » pour <b>' . e($ev['name']) . '</b> est maintenant au nom de <b>' . e(trim($first . ' ' . $last)) . '</b> (' . e($email) . ').</p><p>L\'ancien QR code ne fonctionne plus : seul le nouveau, reçu par ton ami·e, passera à l\'entrée.</p>'); }
            catch (Throwable $e) { log_line('emails', 'Transfert (confirmation) : ' . $e->getMessage()); }
        }
        return $t;
    }

    // ---------- Agenda ----------

    public static function eventTimes(array $ev, ?array $session): array
    {
        $start = strtotime((string)($session['starts_at'] ?? $ev['starts_at']));
        $endRaw = $session ? ($session['ends_at'] ?? null) : ($ev['ends_at'] ?? null);
        $end = $endRaw ? strtotime((string)$endRaw) : $start + 3 * 3600;
        if ($end <= $start) $end = $start + 3 * 3600;
        return [$start, $end];
    }

    public static function place(array $ev): string
    {
        return trim(implode(', ', array_filter([$ev['venue'] ?? '', $ev['address'] ?? '', $ev['city'] ?? ''])));
    }

    public static function googleUrl(array $ev, ?array $session): string
    {
        [$s, $e] = self::eventTimes($ev, $session);
        $f = fn(int $t) => gmdate('Ymd\THis\Z', $t);
        return 'https://calendar.google.com/calendar/render?' . http_build_query(['action' => 'TEMPLATE', 'text' => $ev['name'], 'dates' => $f($s) . '/' . $f($e),
            'location' => self::place($ev), 'details' => abs_url('/e/' . $ev['slug'])]);
    }

    public static function icsUrl(array $ev, ?array $session): string
    {
        return url('/e/' . $ev['slug'] . '/agenda.ics', $session ? ['seance' => (int)$session['id']] : []);
    }

    public static function ics(array $ev, ?array $session): string
    {
        [$s, $e] = self::eventTimes($ev, $session);
        $esc = fn(string $v) => str_replace(["\\", "\n", ',', ';'], ["\\\\", '\\n', '\\,', '\\;'], $v);
        $f = fn(int $t) => gmdate('Ymd\THis\Z', $t);
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Entrevo//Billetterie//FR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
            'UID:ev' . $ev['id'] . '-' . ($session['id'] ?? 0) . '@' . preg_replace('~[^a-z0-9.-]~i', '', parse_url(abs_url('/'), PHP_URL_HOST) ?: 'entrevo'),
            'DTSTAMP:' . $f(time()), 'DTSTART:' . $f($s), 'DTEND:' . $f($e), 'SUMMARY:' . $esc($ev['name']),
            'LOCATION:' . $esc(self::place($ev)), 'DESCRIPTION:' . $esc('Tes billets : ' . abs_url('/mes-billets') . "\n" . abs_url('/e/' . $ev['slug'])), 'URL:' . abs_url('/e/' . $ev['slug']),
            'BEGIN:VALARM', 'TRIGGER:-PT3H', 'ACTION:DISPLAY', 'DESCRIPTION:' . $esc($ev['name']), 'END:VALARM', 'END:VEVENT', 'END:VCALENDAR'];
        return implode("\r\n", array_map(fn($l) => self::fold($l), $lines)) . "\r\n";
    }

    private static function fold(string $l): string
    {
        $out = ''; while (strlen($l) > 74) { $cut = 74; while ($cut > 0 && (ord($l[$cut]) & 0xC0) === 0x80) $cut--; $out .= substr($l, 0, $cut) . "\r\n "; $l = substr($l, $cut); }
        return $out . $l;
    }

    private static function calendarLine(array $ev, ?array $session): string
    {
        return '📅 Ajoute-le à ton agenda : ' . self::googleUrl($ev, $session);
    }

    /** Liens HTML (e-mail) Google + fichier .ics. */
    public static function calendarLinks(array $ev, ?array $session): string
    {
        return '<p style="font-size:14px">📅 Ajouter à mon agenda : <a href="' . e(self::googleUrl($ev, $session)) . '">Google</a> · <a href="' . e(abs_url('/e/' . $ev['slug'] . '/agenda.ics', $session ? ['seance' => (int)$session['id']] : [])) . '">Apple / Outlook (.ics)</a></p>';
    }

    // ---------- Rappel la veille ----------

    public static function reminders(): int
    {
        $rows = all("SELECT o.id FROM orders o JOIN events e ON e.id = o.event_id JOIN organizers g ON g.id = o.organizer_id LEFT JOIN sessions s ON s.id = o.session_id
            WHERE o.status = 'paid' AND o.reminder_sent_at IS NULL AND g.auto_reminder = 1 AND o.email IS NOT NULL AND o.email <> '' AND (o.channel IS NULL OR o.channel <> 'systeme')
              AND COALESCE(s.starts_at, e.starts_at) BETWEEN NOW() + INTERVAL 2 HOUR AND NOW() + INTERVAL 30 HOUR
              AND o.paid_at < NOW() - INTERVAL 2 HOUR LIMIT 40");
        $n = 0;
        foreach ($rows as $r) {
            update('orders', ['reminder_sent_at' => now()], 'id = ?', [$r['id']]);
            $o = one('SELECT * FROM orders WHERE id = ?', [$r['id']]);
            $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
            $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
            $session = $o['session_id'] ? one('SELECT * FROM sessions WHERE id = ?', [$o['session_id']]) : null;
            $nb = (int)val("SELECT COUNT(*) FROM tickets WHERE order_id = ? AND status = 'valid' AND holder_email IS NULL", [$o['id']]);
            if (!$nb || !is_email((string)$o['email'])) continue;
            $start = strtotime((string)($session['starts_at'] ?? $ev['starts_at']));
            $when = date('Y-m-d', $start) === date('Y-m-d') ? 'Ce soir' : 'Demain';
            $place = self::place($ev);
            $link = abs_url('/commande/' . $o['ref'], ['t' => $o['token']]);
            try {
                Mailer::send((string)$o['email'], '⏰ ' . $when . ' : ' . $ev['name'] . ' !',
                    '<p>Salut ' . e($o['first_name']) . ' 👋</p><p><b>' . $when . '</b>, c\'est <b>' . e($ev['name']) . '</b>. On a hâte !</p>'
                    . '<table style="font-size:15px;border-collapse:collapse"><tr><td style="padding:4px 12px 4px 0">🕗</td><td><b>' . e(fr_date(date('Y-m-d H:i:s', $start))) . '</b></td></tr>'
                    . ($place ? '<tr><td style="padding:4px 12px 4px 0">📍</td><td>' . e($place) . ' — <a href="https://www.google.com/maps/search/?api=1&amp;query=' . e(rawurlencode($place)) . '">itinéraire</a></td></tr>' : '')
                    . '<tr><td style="padding:4px 12px 4px 0">🎟️</td><td>' . $nb . ' billet' . ($nb > 1 ? 's' : '') . ' · commande ' . e($o['ref']) . '</td></tr></table>'
                    . (!empty($ev['email_message']) ? '<div style="margin:16px 0;padding:12px 14px;background:#f6f4ef;border-left:4px solid #0032fa;white-space:pre-line">' . e($ev['email_message']) . '</div>' : '')
                    . '<p><a href="' . e($link) . '" style="background:' . e(setting('primary_color', '#0032fa')) . ';color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;display:inline-block">Afficher mes billets</a></p>'
                    . '<p style="color:#777;font-size:13px">Astuce : garde cette page ouverte ou fais une capture du QR code, au cas où le réseau sature sur place 😉</p>',
                    [], $org['email'] ?? null);
                $n++;
            } catch (Throwable $e) { log_line('emails', 'Rappel J-1 #' . $o['id'] . ' : ' . $e->getMessage()); }
        }
        return $n;
    }

    // ---------- Paniers abandonnés ----------

    public static function recovery(): int
    {
        $rows = all("SELECT o.id, o.email, o.event_id FROM orders o JOIN events e ON e.id = o.event_id JOIN organizers g ON g.id = o.organizer_id
            WHERE o.status IN ('pending','expired') AND o.recovery_sent_at IS NULL AND g.auto_recovery = 1 AND g.status = 'active'
              AND o.method = 'online' AND (o.channel IS NULL OR o.channel <> 'systeme') AND o.email IS NOT NULL AND o.email <> ''
              AND o.created_at BETWEEN NOW() - INTERVAL 48 HOUR AND NOW() - INTERVAL 1 HOUR AND (o.expires_at IS NULL OR o.expires_at < NOW())
              AND e.status = 'published' AND COALESCE(e.ends_at, e.starts_at) > NOW() + INTERVAL 3 HOUR
            ORDER BY o.id DESC LIMIT 40");
        $n = 0; $done = [];
        foreach ($rows as $r) {
            $key = strtolower((string)$r['email']) . '|' . $r['event_id'];
            // une seule relance par personne et par événement, et seulement si elle n'a pas acheté depuis
            q("UPDATE orders SET recovery_sent_at = NOW() WHERE LOWER(email) = ? AND event_id = ? AND status IN ('pending','expired') AND recovery_sent_at IS NULL", [strtolower((string)$r['email']), $r['event_id']]);
            if (isset($done[$key])) continue;
            $done[$key] = true;
            if (val("SELECT id FROM orders WHERE LOWER(email) = ? AND event_id = ? AND status = 'paid' AND id > ? LIMIT 1", [strtolower((string)$r['email']), $r['event_id'], $r['id']])) continue;
            $o = one('SELECT * FROM orders WHERE id = ?', [$r['id']]);
            if (!is_email((string)$o['email']) || val('SELECT 1 FROM email_unsubscribes WHERE organizer_id = ? AND email = ?', [$o['organizer_id'], strtolower((string)$o['email'])])) continue;
            $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
            $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
            $items = all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
            if (!$items) continue;
            $list = implode('', array_map(fn($i) => '<li>' . (int)$i['qty'] . ' × ' . e($i['name']) . '</li>', $items));
            $link = abs_url('/reprendre/' . $o['ref'], ['t' => $o['token']]);
            try {
                Mailer::send((string)$o['email'], '🛒 Tes billets pour ' . $ev['name'] . ' t\'attendent',
                    '<p>Salut ' . e($o['first_name']) . ' 👋</p><p>Tu as commencé à réserver pour <b>' . e($ev['name']) . '</b> (' . e(fr_date($ev['starts_at'], false)) . ') mais le paiement n\'est pas allé au bout. Un souci de carte, un coup de fil, le chat qui marche sur le clavier ?</p>'
                    . '<p>On t\'a gardé ton panier :</p><ul>' . $list . '</ul>'
                    . '<p><a href="' . e($link) . '" style="background:' . e(setting('primary_color', '#0032fa')) . ';color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;display:inline-block">Reprendre ma réservation</a></p>'
                    . '<p style="color:#777;font-size:13px">Les places partent vite, on ne peut pas te les garder éternellement. Plus intéressé·e ? Ignore simplement cet e-mail, on ne te relancera pas.</p>',
                    [], $org['email'] ?? null);
                $n++;
            } catch (Throwable $e) { log_line('emails', 'Relance panier #' . $o['id'] . ' : ' . $e->getMessage()); }
        }
        return $n;
    }

    /** Remet le panier d'une commande non payée dans la session. */
    public static function restoreCart(array $o): array
    {
        $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
        $cart = ['session' => $o['session_id'] ? (int)$o['session_id'] : null, 'q' => [], 'prices' => [], 'products' => [], 'donation' => (int)$o['donation'], 'seats' => [],
            'promo' => (string)$o['promo_code'], 'unlocked' => [], 'waitlist' => null, 'partner' => null];
        foreach (all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]) as $i) {
            if ($i['kind'] === 'rate') {
                $r = one('SELECT * FROM rates WHERE id = ?', [$i['rate_id']]);
                if (!$r) continue;
                $cart['q'][(int)$i['rate_id']] = ($cart['q'][(int)$i['rate_id']] ?? 0) + (int)$i['qty'];
                if ($r['price_mode'] === 'free') $cart['prices'][(int)$i['rate_id']] = (int)$i['unit_price'];
                if ($r['hidden']) $cart['unlocked'][] = (int)$r['id'];
            } elseif ($i['product_id']) {
                $cart['products'][(int)$i['product_id']] = (int)$i['qty'];
            }
        }
        return [$ev, $cart];
    }

    public static function recoveryStats(int $orgId, int $days = 90): array
    {
        $sent = (int)val("SELECT COUNT(DISTINCT LOWER(email), event_id) FROM orders WHERE organizer_id = ? AND recovery_sent_at >= NOW() - INTERVAL $days DAY", [$orgId]);
        $rec = one("SELECT COUNT(*) n, COALESCE(SUM(total), 0) revenue FROM orders WHERE organizer_id = ? AND status = 'paid' AND recovered_from IS NOT NULL AND created_at >= NOW() - INTERVAL $days DAY", [$orgId]);
        return ['sent' => $sent, 'recovered' => (int)$rec['n'], 'revenue' => (int)$rec['revenue'], 'rate' => $sent ? round($rec['n'] / $sent * 100, 1) : 0.0];
    }

    // ---------- Avis ----------

    public static function reviewRequests(): int
    {
        $rows = all("SELECT o.id FROM orders o JOIN events e ON e.id = o.event_id JOIN organizers g ON g.id = o.organizer_id LEFT JOIN sessions s ON s.id = o.session_id
            WHERE o.status = 'paid' AND o.review_sent_at IS NULL AND g.auto_review = 1 AND o.email IS NOT NULL AND o.email <> '' AND (o.channel IS NULL OR o.channel <> 'systeme')
              AND COALESCE(s.ends_at, s.starts_at, e.ends_at, e.starts_at) BETWEEN NOW() - INTERVAL 7 DAY AND NOW() - INTERVAL 10 HOUR
              AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.order_id = o.id) LIMIT 40");
        $n = 0;
        foreach ($rows as $r) {
            update('orders', ['review_sent_at' => now()], 'id = ?', [$r['id']]);
            $o = one('SELECT * FROM orders WHERE id = ?', [$r['id']]);
            $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
            // si l'événement a été scanné, on n'écrit qu'à ceux qui sont venus
            if (val("SELECT 1 FROM tickets WHERE event_id = ? AND entries > 0 LIMIT 1", [$ev['id']]) && !val("SELECT 1 FROM tickets WHERE order_id = ? AND entries > 0 LIMIT 1", [$o['id']])) continue;
            if (!is_email((string)$o['email'])) continue;
            $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
            $stars = '';
            for ($k = 1; $k <= 5; $k++) $stars .= '<a href="' . e(abs_url('/avis/' . $o['ref'], ['t' => $o['token'], 'note' => $k])) . '" style="display:inline-block;margin:0 3px;padding:10px 12px;border-radius:10px;background:#f4f4f8;text-decoration:none;font-size:22px" title="' . $k . '/5">' . str_repeat('⭐', 1) . '<span style="font-size:13px;color:#555;margin-left:4px">' . $k . '</span></a>';
            try {
                Mailer::send((string)$o['email'], '💬 Alors, ' . $ev['name'] . ', t\'as kiffé ?',
                    '<p>Salut ' . e($o['first_name']) . ' 👋</p><p>Merci d\'être venu·e à <b>' . e($ev['name']) . '</b> ! ' . e($org['name']) . ' aimerait savoir ce que tu en as pensé. Ça prend 20 secondes, promis.</p>'
                    . '<p style="text-align:center;margin:22px 0">' . $stars . '</p><p style="color:#777;font-size:13px;text-align:center">1 = bof · 5 = on y retourne demain</p>',
                    [], $org['email'] ?? null);
                $n++;
            } catch (Throwable $e) { log_line('emails', 'Demande d\'avis #' . $o['id'] . ' : ' . $e->getMessage()); }
        }
        return $n;
    }

    public static function orgRating(int $orgId): array
    {
        $r = one('SELECT COUNT(*) n, AVG(rating) avg FROM reviews WHERE organizer_id = ? AND visible = 1', [$orgId]);
        return ['count' => (int)$r['n'], 'avg' => $r['n'] ? round((float)$r['avg'], 1) : null];
    }

    public static function stars(float $avg): string
    {
        $full = (int)round($avg);
        return str_repeat('★', $full) . str_repeat('☆', 5 - $full);
    }
}
