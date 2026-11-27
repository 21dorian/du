<?php
declare(strict_types=1);

/**
 * 4.1 : côté social (façon Shotgun / Dice) — « Ils y vont » (acheteurs qui acceptent d'apparaître) et « Payer à plusieurs » :
 * un ami bloque des places pour la bande, chacun paie la sienne avec le lien du groupe avant l'échéance.
 */
final class Social
{
    /** Prénoms (et initiale) des acheteurs qui ont accepté d'apparaître, plus le nombre total de billets. */
    public static function going(array $ev, int $limit = 12): array
    {
        $people = all("SELECT MAX(first_name) fn, MAX(last_name) ln FROM orders WHERE event_id = ? AND status = 'paid' AND show_public = 1 AND first_name <> '' GROUP BY LOWER(email) ORDER BY MAX(paid_at) DESC LIMIT " . (int)$limit, [$ev['id']]);
        $total = (int)val("SELECT COUNT(*) FROM tickets WHERE event_id = ? AND status = 'valid' AND type IN ('ticket','invitation')", [$ev['id']]);
        return ['people' => array_map(fn($p) => trim($p['fn'] . ' ' . mb_strtoupper(mb_substr((string)$p['ln'], 0, 1)) . ($p['ln'] ? '.' : '')), $people), 'total' => $total];
    }

    // ---------- payer à plusieurs ----------
    public static function group(string $token): ?array
    {
        return one('SELECT g.*, r.name rate_name, r.price, r.pack_size FROM buy_groups g JOIN rates r ON r.id = g.rate_id WHERE g.token = ?', [$token]);
    }

    /** Places du groupe déjà prises (commandes payées ou paniers en cours). */
    public static function taken(int $groupId): int
    {
        return (int)val("SELECT COALESCE(SUM(i.qty * GREATEST(1, r.pack_size)), 0) FROM order_items i JOIN orders o ON o.id = i.order_id JOIN rates r ON r.id = i.rate_id
            WHERE o.group_id = ? AND i.kind = 'rate' AND (o.status = 'paid' OR (o.status = 'pending' AND o.expires_at > NOW()))", [$groupId]);
    }

    public static function left(array $g): int
    {
        return strtotime((string)$g['expires_at']) > time() ? max(0, (int)$g['qty'] - self::taken((int)$g['id'])) : 0;
    }

    /** Places encore bloquées par les groupes actifs (comptées comme réservées dans les jauges). */
    public static function held(int $eventId, ?int $rateId = null, ?int $sessionId = null): int
    {
        $w = 'event_id = ? AND expires_at > NOW()'; $p = [$eventId];
        if ($rateId) { $w .= ' AND rate_id = ?'; $p[] = $rateId; }
        if ($sessionId) { $w .= ' AND (session_id = ? OR session_id IS NULL)'; $p[] = $sessionId; }
        $n = 0;
        foreach (all("SELECT * FROM buy_groups WHERE $w", $p) as $g) $n += max(0, (int)$g['qty'] - self::taken((int)$g['id']));
        return $n;
    }

    public static function members(int $groupId): array
    {
        return all("SELECT first_name, last_name, status, paid_at FROM orders WHERE group_id = ? AND (status = 'paid' OR (status = 'pending' AND expires_at > NOW())) ORDER BY id", [$groupId]);
    }

    /** Crée un groupe : les places sont bloquées jusqu'à l'échéance (24 h, au plus tard 3 h avant le début). */
    public static function create(array $ev, ?array $session, array $rate, int $qty, string $name, string $email): array
    {
        if (!(int)($ev['group_buy'] ?? 0)) throw new RuntimeException('Le paiement à plusieurs n\'est pas proposé pour cet événement.');
        if ($qty < 2 || $qty > 12) throw new RuntimeException('Un groupe compte de 2 à 12 personnes.');
        if (!is_email($email) || trim($name) === '') throw new RuntimeException('Ton prénom et ton e-mail sont nécessaires.');
        $st = Tickets::onSale($rate, $ev, $session);
        if (!$st['ok']) throw new RuntimeException($rate['name'] . ' : ' . $st['why']);
        if ($st['remaining'] < $qty) throw new RuntimeException('Plus que ' . (int)$st['remaining'] . ' place(s) pour ce tarif.');
        $start = strtotime((string)($session['starts_at'] ?? $ev['starts_at']));
        $exp = min(time() + 24 * 3600, $start - 3 * 3600);
        if ($exp < time() + 1800) throw new RuntimeException('Trop proche du début : achète directement tes places.');
        $tok = bin2hex(random_bytes(10));
        insert('buy_groups', ['event_id' => $ev['id'], 'session_id' => $session['id'] ?? null, 'rate_id' => $rate['id'], 'qty' => $qty, 'name' => mb_substr(trim($name), 0, 80), 'email' => strtolower($email), 'token' => $tok, 'expires_at' => date('Y-m-d H:i:s', $exp), 'created_at' => now()]);
        return self::group($tok);
    }
}
