<?php
declare(strict_types=1);

/**
 * 2.3 : fraude & risques.
 * - Liste de blocage (e-mail, domaine d'e-mail, adresse IP) : la commande est refusée avant le paiement.
 * - Score de risque calculé à la création de chaque commande en ligne, à partir de signaux simples et explicables.
 *   Au-dessus du seuil, la commande est marquée « à vérifier » : elle suit son cours (le paiement reste possible),
 *   mais le super-admin la voit dans sa file et peut l'annuler et la rembourser avant l'événement.
 */
final class Risk
{
    public const KINDS = ['email' => 'E-mail', 'domain' => 'Domaine', 'ip' => 'Adresse IP'];
    private const DISPOSABLE = ['yopmail.com', 'yopmail.fr', 'mailinator.com', 'guerrillamail.com', 'guerrillamail.info', '10minutemail.com', 'tempmail.com', 'temp-mail.org', 'trashmail.com',
        'getnada.com', 'sharklasers.com', 'dispostable.com', 'maildrop.cc', 'throwawaymail.com', 'fakeinbox.com', 'jetable.org', 'mail-temporaire.fr', 'moakt.com', 'emailondeck.com', 'mohmal.com'];

    public static function ip(): ?string { $ip = (string)($_SERVER['REMOTE_ADDR'] ?? ''); return $ip !== '' ? mb_substr($ip, 0, 45) : null; }

    /** Message d'erreur si l'acheteur est bloqué, sinon null. */
    public static function blocked(string $email, ?string $ip = null): ?string
    {
        $email = strtolower(trim($email)); $dom = substr(strrchr($email, '@') ?: '', 1);
        $ip ??= self::ip();
        $hit = one("SELECT * FROM risk_blocks WHERE (expires_at IS NULL OR expires_at > NOW()) AND ((kind = 'email' AND value = ?) OR (kind = 'domain' AND value = ?) OR (kind = 'ip' AND value = ?)) LIMIT 1", [$email, $dom, (string)$ip]);
        if ($hit) { log_line('fraude', 'Commande bloquée (' . $hit['kind'] . ' ' . $hit['value'] . ') ' . $email . ' ' . $ip); return 'Cette commande ne peut pas être passée. Si tu penses que c\'est une erreur, écris-nous depuis la page Contact.'; }
        if (setting('risk_block_disposable') === '1' && in_array($dom, self::DISPOSABLE, true)) return 'Les adresses e-mail jetables ne sont pas acceptées : utilise ton adresse habituelle pour recevoir tes billets.';
        return null;
    }

    /** Calcule le score d'une commande qui vient d'être créée et le range. @return array{score:int, flags:array} */
    public static function assess(array $o): array
    {
        $ip = self::ip();
        $email = strtolower((string)$o['email']); $dom = substr(strrchr($email, '@') ?: '', 1);
        $s = 0; $f = [];
        $add = function (int $pts, string $why) use (&$s, &$f) { $s += $pts; $f[] = $why; };
        if (in_array($dom, self::DISPOSABLE, true)) $add(30, 'e-mail jetable');
        if (preg_match('~\d{5,}~', strstr($email, '@', true) ?: '')) $add(5, 'e-mail généré');
        if ($ip) {
            $n = (int)val('SELECT COUNT(*) FROM orders WHERE ip = ? AND created_at >= NOW() - INTERVAL 1 HOUR AND id <> ?', [$ip, $o['id']]);
            $m = (int)val('SELECT COUNT(DISTINCT LOWER(email)) FROM orders WHERE ip = ? AND created_at >= NOW() - INTERVAL 1 HOUR AND id <> ?', [$ip, $o['id']]);
            if ($n >= (int)setting('risk_ip_hour', '6')) $add(20, $n . ' commandes depuis cette IP en 1 h');
            if ($m >= 3) $add(20, $m . ' e-mails différents depuis cette IP');
        }
        $fails = (int)val("SELECT COUNT(*) FROM orders WHERE LOWER(email) = ? AND status IN ('expired','cancelled') AND created_at >= NOW() - INTERVAL 1 DAY", [$email]);
        if ($fails >= 3) $add(20, $fails . ' paiements abandonnés en 24 h');
        if ((int)val("SELECT COUNT(*) FROM disputes d JOIN orders x ON x.id = d.order_id WHERE LOWER(x.email) = ?", [$email])) $add(45, 'déjà un litige de paiement');
        $tickets = (int)val("SELECT COALESCE(SUM(qty), 0) FROM order_items WHERE order_id = ?", [$o['id']]);
        $first = !(int)val("SELECT COUNT(*) FROM orders WHERE LOWER(email) = ? AND status = 'paid' AND id <> ?", [$email, $o['id']]);
        if ((int)$o['total'] >= (int)setting('risk_amount', '50000')) $add(15, 'montant élevé (' . money((int)$o['total']) . ')');
        if ($first && $tickets >= 8) $add(15, 'premier achat de ' . $tickets . ' articles');
        if ($o['event_id'] && ($ev = one('SELECT starts_at FROM events WHERE id = ?', [$o['event_id']])) && strtotime($ev['starts_at']) - time() < 3 * 3600 && strtotime($ev['starts_at']) > time() && (int)$o['total'] >= 20000) $add(10, 'gros achat juste avant le début');
        if (preg_match('~^(.)\1{2,}|^(test|aaa|xxx|azerty|qwerty)~i', trim((string)$o['last_name']))) $add(10, 'nom suspect');
        $status = $s >= (int)setting('risk_threshold', '50') ? 'review' : null;
        update('orders', ['ip' => $ip, 'risk_score' => min(100, $s), 'risk_flags' => $f ? mb_substr(implode(' · ', $f), 0, 255) : null, 'risk_status' => $status], 'id = ?', [$o['id']]);
        return ['score' => $s, 'flags' => $f];
    }

    public static function block(string $kind, string $value, ?string $reason, ?int $by, ?int $days = null): void
    {
        $value = strtolower(trim($value));
        if (!isset(self::KINDS[$kind]) || $value === '') return;
        q('INSERT INTO risk_blocks (kind, value, reason, created_by, created_at, expires_at) VALUES (?, ?, ?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE reason = VALUES(reason), expires_at = VALUES(expires_at)',
            [$kind, $value, $reason ? mb_substr($reason, 0, 255) : null, $by, $days ? date('Y-m-d H:i:s', time() + $days * 86400) : null]);
    }

    public static function badge(int $score): string
    {
        $c = $score >= 50 ? 'red' : ($score >= 25 ? 'orange' : 'grey');
        return '<span class="badge ' . $c . '" title="Score de risque">' . $score . '</span>';
    }
}
