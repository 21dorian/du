<?php
declare(strict_types=1);

/**
 * 2.1 : campagnes SMS des organisateurs.
 *
 * Audience : uniquement les acheteurs ayant accepté de recevoir les actus (marketing_optin) et laissé un
 * numéro de mobile, désinscrits exclus. Numéros normalisés au format international (+33…).
 * Envoi : via l'API SMS de Brevo (réglée par le super-admin). Sans clé, mode test : rien ne part,
 * chaque SMS est écrit dans le journal « sms ». Les envois commerciaux ne partent que du lundi au samedi,
 * de 8 h à 20 h, hors jours fériés ; en dehors, la campagne attend le prochain créneau.
 */
final class Sms
{
    public const STATUS = ['scheduled' => ['Programmée', 'orange'], 'sending' => ['En cours d\'envoi', 'orange'], 'sent' => ['Envoyée', 'green'], 'cancelled' => ['Annulée', 'red']];
    public const BATCH = 30;
    private const GSM = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
    private const GSM_EXT = '^{}\\[~]|€';

    public static function badge(string $s): string { [$l, $c] = self::STATUS[$s] ?? [$s, 'grey']; return '<span class="badge ' . $c . '">' . e($l) . '</span>'; }

    public static function live(): bool { return trim((string)setting('sms_api_key', '')) !== ''; }

    public static function price(?array $org = null): int
    {
        $p = $org && class_exists('Billing') ? Billing::plan($org) : null;
        return max(0, $p && $p['sms_price'] !== null ? (int)$p['sms_price'] : (int)setting('sms_price', '8'));
    }

    public static function stopText(): string { return trim((string)setting('sms_stop', 'STOP au 36173')); }

    /** Numéro au format international, ou null s'il n'est pas un mobile exploitable. */
    public static function normalize(string $raw): ?string
    {
        $n = preg_replace('~[^\d+]~', '', $raw);
        if ($n === '' || $n === null) return null;
        if (str_starts_with($n, '00')) $n = '+' . substr($n, 2);
        if (preg_match('~^0([67]\d{8})$~', $n, $m)) return '+33' . $m[1];
        if (preg_match('~^\+33(0?)([67]\d{8})$~', $n, $m)) return '+33' . $m[2];
        if (str_starts_with($n, '+33')) return null; // fixe français : pas de SMS
        if (preg_match('~^\+[1-9]\d{7,14}$~', $n)) return $n;
        return null;
    }

    public static function pretty(string $e164): string
    {
        if (preg_match('~^\+33(\d)(\d{2})(\d{2})(\d{2})(\d{2})$~', $e164, $m)) return '0' . $m[1] . ' ' . $m[2] . ' ' . $m[3] . ' ' . $m[4] . ' ' . $m[5];
        return $e164;
    }

    public static function sender(array $org): string
    {
        $s = preg_replace('~[^A-Za-z0-9]~', '', (string)($org['sms_sender'] ?: iconv('UTF-8', 'ASCII//TRANSLIT', (string)$org['name'])));
        if (!preg_match('~^[A-Za-z]~', $s)) $s = 'Info' . $s;
        return substr($s, 0, 11);
    }

    /** Texte final (variables remplacées + mention STOP). */
    public static function text(string $body, ?string $first, ?string $link): string
    {
        $t = str_replace(['{prenom}', '{prénom}', '{lien}'], [trim((string)$first), trim((string)$first), (string)$link], $body);
        $t = trim(preg_replace('~[ \t]+~', ' ', $t));
        return $t . "\n" . self::stopText();
    }

    /** [caractères, segments, unicode] selon l'encodage GSM 7 bits ou Unicode. */
    public static function measure(string $text): array
    {
        $len = 0; $gsm = true;
        foreach (mb_str_split($text) as $ch) {
            if (mb_strpos(self::GSM, $ch) !== false) $len++;
            elseif (mb_strpos(self::GSM_EXT, $ch) !== false) $len += 2;
            else { $gsm = false; break; }
        }
        if (!$gsm) { $len = mb_strlen($text); return [$len, $len <= 70 ? 1 : (int)ceil($len / 67), true]; }
        return [$len, $len <= 160 ? 1 : (int)ceil($len / 153), false];
    }

    public static function link(?array $ev, int $campaignId): ?string
    {
        return $ev ? abs_url('/e/' . $ev['slug'], ['utm_source' => 'sms', 'utm_campaign' => 's' . $campaignId]) : null;
    }

    // ---------- Audience ----------
    public static function audience(array $c): array
    {
        return (json_decode((string)$c['audience'], true) ?: []) + ['events' => [], 'not_event' => 0, 'segment' => ''];
    }

    /** Destinataires (numéro +33… => prénom) : opt-in uniquement, désinscrits exclus. */
    public static function recipients(int $orgId, array $a): array
    {
        $w = "o.organizer_id = ? AND o.status = 'paid' AND o.phone IS NOT NULL AND o.phone <> '' AND (o.channel IS NULL OR o.channel <> 'systeme')";
        $p = [$orgId];
        $evs = array_values(array_filter(array_map('intval', (array)$a['events'])));
        if ($evs) $w .= ' AND o.phone IN (SELECT phone FROM orders WHERE status = \'paid\' AND phone IS NOT NULL AND event_id IN (' . in_list($evs) . '))';
        if ((int)$a['not_event']) { $w .= ' AND o.phone NOT IN (SELECT phone FROM orders WHERE status = \'paid\' AND event_id = ? AND phone IS NOT NULL)'; $p[] = (int)$a['not_event']; }
        $having = match ((string)$a['segment']) {
            'fideles' => ' AND COUNT(DISTINCT o.event_id) >= 2',
            'gros' => ' AND SUM(o.total - o.refunded_amount) >= 10000',
            'recents' => ' AND MAX(o.created_at) >= NOW() - INTERVAL 30 DAY',
            'dormants' => ' AND MAX(o.created_at) < NOW() - INTERVAL 180 DAY',
            'venus' => ' AND SUM(EXISTS (SELECT 1 FROM tickets t WHERE t.order_id = o.id AND t.entries > 0)) > 0',
            default => '',
        };
        $rows = all("SELECT o.phone, SUBSTRING_INDEX(GROUP_CONCAT(o.first_name ORDER BY o.created_at DESC SEPARATOR '\x1f'), '\x1f', 1) first_name
            FROM orders o WHERE $w GROUP BY o.phone HAVING MAX(o.marketing_optin) = 1$having LIMIT 50000", $p);
        $stop = array_flip(array_column(all('SELECT phone FROM sms_optouts WHERE organizer_id = ?', [$orgId]), 'phone'));
        $out = [];
        foreach ($rows as $r) {
            $n = self::normalize((string)$r['phone']);
            if ($n && !isset($stop[$n]) && !isset($out[$n])) $out[$n] = $r['first_name'];
        }
        return $out;
    }

    // ---------- Créneau légal ----------
    /** Envoi commercial autorisé maintenant ? (lun.–sam., 8 h–20 h, hors fériés) */
    public static function windowOpen(?int $ts = null): bool
    {
        $ts ??= time();
        if ((int)date('N', $ts) === 7) return false;
        $h = (int)date('G', $ts);
        if ($h < 8 || $h >= 20) return false;
        return !in_array(date('m-d', $ts), self::holidays((int)date('Y', $ts)), true);
    }

    public static function holidays(int $y): array
    {
        $easter = easter_date_safe($y);
        $f = ['01-01', '05-01', '05-08', '07-14', '08-15', '11-01', '11-11', '12-25'];
        foreach ([1, 39, 50] as $d) $f[] = date('m-d', $easter + $d * 86400);
        return $f;
    }

    /** Prochain moment où l'envoi sera possible (pour l'affichage). */
    public static function nextWindow(): int
    {
        $t = time();
        for ($i = 0; $i < 24 * 8; $i++) { if (self::windowOpen($t)) return $t; $t = strtotime(date('Y-m-d H:00:00', $t)) + 3600; }
        return $t;
    }

    // ---------- Envoi ----------
    /** Envoie un SMS ; renvoie 'sent' ou 'test'. Lève une exception en cas d'échec. */
    public static function send(string $to, string $text, string $sender, string $tag = 'entrevo'): string
    {
        if (!self::live()) { log_line('sms', "TEST $sender -> $to : " . str_replace("\n", ' / ', $text)); return 'test'; }
        $ch = curl_init('https://api.brevo.com/v3/transactionalSMS/sms');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['api-key: ' . trim((string)setting('sms_api_key')), 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['sender' => $sender, 'recipient' => ltrim($to, '+'), 'content' => $text, 'type' => 'marketing', 'tag' => $tag], JSON_UNESCAPED_UNICODE)]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) throw new RuntimeException('Connexion au service SMS impossible : ' . $err);
        if ($code >= 400) {
            $j = json_decode((string)$raw, true);
            log_line('sms', "Brevo $code $raw");
            throw new RuntimeException('Service SMS : ' . ($j['message'] ?? ('HTTP ' . $code)), $code);
        }
        return 'sent';
    }

    /** Fige la liste des destinataires et passe la campagne en envoi. */
    public static function launch(array $c): int
    {
        $list = self::recipients((int)$c['organizer_id'], self::audience($c));
        foreach ($list as $phone => $first) insert('sms_messages', ['campaign_id' => $c['id'], 'phone' => $phone, 'first_name' => $first ? mb_substr((string)$first, 0, 120) : null]);
        update('sms_campaigns', ['status' => 'sending', 'total' => count($list)], 'id = ?', [$c['id']]);
        if (!$list) update('sms_campaigns', ['status' => 'sent', 'sent_at' => now()], 'id = ?', [$c['id']]);
        return count($list);
    }

    /** Tâche de fond : lance les campagnes programmées et envoie un lot pendant le créneau autorisé. */
    public static function process(int $budget = self::BATCH): int
    {
        if (!self::windowOpen()) return 0;
        foreach (all("SELECT * FROM sms_campaigns WHERE status = 'scheduled' AND (scheduled_at IS NULL OR scheduled_at <= NOW()) LIMIT 5") as $c) self::launch($c);
        $sent = 0;
        foreach (all("SELECT * FROM sms_campaigns WHERE status = 'sending' ORDER BY id LIMIT 3") as $c) {
            $org = one('SELECT * FROM organizers WHERE id = ?', [$c['organizer_id']]);
            $ev = $c['event_id'] ? one('SELECT * FROM events WHERE id = ?', [$c['event_id']]) : null;
            $link = self::link($ev, (int)$c['id']);
            $stop = array_flip(array_column(all('SELECT phone FROM sms_optouts WHERE organizer_id = ?', [$c['organizer_id']]), 'phone'));
            foreach (all("SELECT * FROM sms_messages WHERE campaign_id = ? AND status = 'queued' LIMIT " . max(1, $budget - $sent), [$c['id']]) as $m) {
                if (isset($stop[$m['phone']])) { update('sms_messages', ['status' => 'skipped', 'error' => 'Désinscrit entre-temps'], 'id = ?', [$m['id']]); continue; }
                try {
                    $st = self::send($m['phone'], self::text((string)$c['body'], $m['first_name'], $link), self::sender($org), 'c' . $c['id']);
                    update('sms_messages', ['status' => $st, 'sent_at' => now()], 'id = ?', [$m['id']]);
                    $sent++;
                } catch (Throwable $e) {
                    // clé refusée ou crédits épuisés : on met en pause sans « griller » les destinataires
                    if (in_array($e->getCode(), [401, 402, 403], true)) { set_setting('sms_err_' . $c['id'], mb_substr($e->getMessage(), 0, 250)); return $sent; }
                    update('sms_messages', ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)], 'id = ?', [$m['id']]);
                }
            }
            self::refresh((int)$c['id']);
            if ($sent >= $budget) break;
        }
        return $sent;
    }

    /** Recalcule les compteurs et le coût ; clôt la campagne quand tout est parti. */
    public static function refresh(int $id): void
    {
        $c = one('SELECT * FROM sms_campaigns WHERE id = ?', [$id]);
        $n = one("SELECT SUM(status IN ('sent','test')) sent, SUM(status = 'failed') failed, SUM(status = 'queued') queued FROM sms_messages WHERE campaign_id = ?", [$id]);
        $org = one('SELECT * FROM organizers WHERE id = ?', [$c['organizer_id']]);
        $data = ['sent' => (int)$n['sent'], 'failed' => (int)$n['failed'], 'cost' => (int)$n['sent'] * (int)$c['segments'] * self::price($org)];
        $done = !(int)$n['queued'] && in_array($c['status'], ['sending', 'cancelled'], true);
        if ($done && $c['status'] === 'sending') $data += ['status' => 'sent', 'sent_at' => now()];
        update('sms_campaigns', $data, 'id = ?', [$id]);
        // coût retenu sur le prochain reversement (une seule fois, SMS réellement partis seulement)
        if ($done && self::live() && $data['cost'] > 0 && setting('sms_deduct', '1') === '1' && class_exists('Billing'))
            Billing::charge((int)$c['organizer_id'], 'sms', 'Campagne SMS « ' . $c['name'] . ' » (' . $data['sent'] . ' destinataires)', $data['cost'], 'sms-' . $id);
        set_setting('sms_err_' . $id, '');
    }

    public static function lastError(array $c): string { return (string)setting('sms_err_' . $c['id'], ''); }

    public static function addOptout(int $orgId, string $phone): bool
    {
        $n = self::normalize($phone);
        if (!$n) return false;
        q('INSERT IGNORE INTO sms_optouts (organizer_id, phone, created_at) VALUES (?, ?, ?)', [$orgId, $n, now()]);
        return true;
    }
}

/** Date de Pâques sans l'extension calendar (souvent absente chez les hébergeurs). */
function easter_date_safe(int $y): int
{
    $a = $y % 19; $b = intdiv($y, 100); $c = $y % 100; $d = intdiv($b, 4); $e = $b % 4; $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30; $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $month = intdiv($h + $l - 7 * $m + 114, 31); $day = (($h + $l - 7 * $m + 114) % 31) + 1;
    return mktime(12, 0, 0, $month, $day, $y);
}
