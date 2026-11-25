<?php
declare(strict_types=1);

/**
 * 2.6 : application « Entrevo Caisse » (bars et points de vente).
 * - Appareils appairés par un code à 6 chiffres (ou un QR) créé par l'organisateur ; l'appareil reçoit un jeton
 *   (seule son empreinte est gardée) et n'a pas besoin de compte utilisateur. Révocable à tout moment.
 * - Trois moyens de paiement : porte-monnaie cashless (QR du billet ou bracelet NFC), carte bancaire (TPE externe), espèces.
 * - Les ventes carte et espèces peuvent être faites hors connexion : l'appli les garde et les envoie au retour du réseau
 *   (identifiant client unique : une vente n'est jamais comptée deux fois). Le cashless exige le réseau (solde à jour).
 */
final class Pos
{
    public const METHODS = ['cashless' => 'Cashless', 'card' => 'Carte bancaire', 'cash' => 'Espèces'];

    public static function nfc(string $uid): string { return substr(strtoupper(preg_replace('~[^0-9A-Fa-f]~', '', $uid)), 0, 40); }

    // ---------- Appareils ----------
    public static function createDevice(int $eventId, ?int $barId, string $name, ?int $userId): array
    {
        do { $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT); }
        while (val('SELECT id FROM pos_devices WHERE pair_code = ? AND pair_expires > NOW()', [$code]));
        $id = insert('pos_devices', ['event_id' => $eventId, 'bar_id' => $barId ?: null, 'name' => mb_substr($name, 0, 80), 'pair_code' => $code, 'pair_expires' => date('Y-m-d H:i:s', time() + 1800), 'created_by' => $userId, 'created_at' => now()]);
        return one('SELECT * FROM pos_devices WHERE id = ?', [$id]);
    }

    public static function renewCode(array $d): array
    {
        do { $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT); }
        while (val('SELECT id FROM pos_devices WHERE pair_code = ? AND pair_expires > NOW()', [$code]));
        update('pos_devices', ['pair_code' => $code, 'pair_expires' => date('Y-m-d H:i:s', time() + 1800), 'token_hash' => null, 'revoked' => 0], 'id = ?', [$d['id']]);
        return one('SELECT * FROM pos_devices WHERE id = ?', [$d['id']]);
    }

    /** Lien d'appairage (encodé dans le QR affiché à l'organisateur). */
    public static function pairUrl(array $d): string { return abs_url('/caisse/') . '#pair=' . $d['pair_code']; }

    /** @return array{0:array,1:string} appareil et jeton en clair (montré une seule fois) */
    public static function pair(string $code, string $platform): ?array
    {
        $code = preg_replace('~\D~', '', $code);
        $d = strlen($code) === 6 ? one('SELECT * FROM pos_devices WHERE pair_code = ? AND pair_expires > NOW() AND revoked = 0', [$code]) : null;
        if (!$d) return null;
        $token = 'pos_' . bin2hex(random_bytes(24));
        update('pos_devices', ['token_hash' => hash('sha256', $token), 'pair_code' => null, 'pair_expires' => null, 'paired_at' => now(), 'last_seen' => now(), 'platform' => mb_substr($platform, 0, 40)], 'id = ?', [$d['id']]);
        return [one('SELECT * FROM pos_devices WHERE id = ?', [$d['id']]), $token];
    }

    public static function device(string $token): ?array
    {
        if (!str_starts_with($token, 'pos_')) return null;
        $d = one('SELECT * FROM pos_devices WHERE token_hash = ? AND revoked = 0', [hash('sha256', $token)]);
        if ($d && (!$d['last_seen'] || strtotime($d['last_seen']) < time() - 60)) q('UPDATE pos_devices SET last_seen = NOW() WHERE id = ?', [$d['id']]);
        return $d;
    }

    /** Terminal de paiement de l'appareil : saisie manuelle (TPE autonome) ou Nepting en protocole Caisse-AP (appli Android). */
    public const TPE_DEFAULTS = ['mode' => 'manual', 'host' => '', 'port' => 8888, 'cj' => '', 'ca' => '01', 'cz' => '0320', 'crlf' => 1, 'timeout' => 120];

    public static function tpe(array $d): array
    {
        $j = json_decode((string)($d['tpe_json'] ?? ''), true);
        $t = (is_array($j) ? $j : []) + self::TPE_DEFAULTS;
        $t['mode'] = $t['mode'] === 'nepting' ? 'nepting' : 'manual';
        $t['port'] = max(1, min(65535, (int)$t['port']));
        $t['cj'] = str_pad(substr(preg_replace('~\D~', '', (string)$t['cj']) ?: (string)$d['id'], 0, 12), 12, '0', STR_PAD_LEFT);
        $t['ca'] = str_pad(substr(preg_replace('~\D~', '', (string)$t['ca']), 0, 2) ?: '01', 2, '0', STR_PAD_LEFT);
        $t['cz'] = preg_match('~^\d{4}$~', (string)$t['cz']) ? (string)$t['cz'] : '0320';
        $t['timeout'] = max(30, min(300, (int)$t['timeout']));
        return $t;
    }

    // ---------- Configuration envoyée à l'appli ----------
    public static function config(array $d): array
    {
        $ev = one('SELECT * FROM events WHERE id = ?', [$d['event_id']]);
        $org = one('SELECT id, name, logo, accent FROM organizers WHERE id = ?', [$ev['organizer_id']]);
        $bars = all('SELECT id, name FROM bars WHERE event_id = ? AND active = 1 ORDER BY id', [$ev['id']]);
        $products = array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['name'], 'category' => $p['category'], 'price' => (int)$p['price'], 'deposit' => (int)$p['deposit'],
            'bars' => $p['bar_ids'] ? array_map('intval', explode(',', (string)$p['bar_ids'])) : []], all('SELECT * FROM bar_products WHERE event_id = ? AND active = 1 ORDER BY category, sort, name', [$ev['id']]));
        $methods = array_keys(self::METHODS);
        if (!(int)$ev['cashless_enabled']) $methods = array_values(array_diff($methods, ['cashless']));
        return ['device' => ['id' => (int)$d['id'], 'name' => $d['name'], 'bar_id' => $d['bar_id'] ? (int)$d['bar_id'] : null], 'tpe' => self::tpe($d),
            'event' => ['id' => (int)$ev['id'], 'name' => $ev['name'], 'starts_at' => $ev['starts_at'], 'cashless' => (bool)$ev['cashless_enabled']],
            'org' => ['name' => $org['name'], 'logo' => $org['logo'] ? abs_url('/' . ltrim($org['logo'], '/')) : null, 'accent' => $org['accent'] ?: null],
            'bars' => array_map(fn($b) => ['id' => (int)$b['id'], 'name' => $b['name']], $bars), 'products' => $products, 'methods' => $methods,
            'platform' => (string)setting('platform_name', 'Entrevo'), 'server_time' => date('c')];
    }

    // ---------- Porte-monnaie ----------
    public static function wallet(int $eventId, array $in): ?array
    {
        if (!empty($in['nfc'])) {
            $uid = self::nfc((string)$in['nfc']);
            return $uid !== '' ? one('SELECT * FROM wallets WHERE event_id = ? AND nfc_uid = ?', [$eventId, $uid]) : null;
        }
        return !empty($in['code']) ? Cashless::byCode($eventId, (string)$in['code']) : null;
    }

    public static function holder(array $w): string
    {
        $t = $w['ticket_id'] ? one('SELECT first_name, last_name FROM tickets WHERE id = ?', [$w['ticket_id']]) : null;
        return $t ? trim($t['first_name'] . ' ' . mb_substr((string)$t['last_name'], 0, 1) . '.') : '';
    }

    public static function linkNfc(int $eventId, string $code, string $uid): array
    {
        $uid = self::nfc($uid);
        if (strlen($uid) < 8) throw new RuntimeException('Bracelet illisible : approche-le à nouveau.');
        $w = Cashless::byCode($eventId, $code);
        if (!$w) throw new RuntimeException('Billet inconnu pour cet événement.');
        $other = one('SELECT * FROM wallets WHERE event_id = ? AND nfc_uid = ? AND id <> ?', [$eventId, $uid, $w['id']]);
        if ($other) throw new RuntimeException('Ce bracelet est déjà associé à un autre billet.');
        update('wallets', ['nfc_uid' => $uid], 'id = ?', [$w['id']]);
        return one('SELECT * FROM wallets WHERE id = ?', [$w['id']]);
    }

    // ---------- Ventes ----------
    /** Lignes de vente au prix du serveur ; un produit retiré depuis garde le prix envoyé par l'appli (vente hors ligne). */
    private static function lines(int $eventId, array $items): array
    {
        $lines = []; $total = 0;
        foreach ($items as $it) {
            $pid = (int)($it['id'] ?? $it[0] ?? 0); $qty = (int)($it['qty'] ?? $it[1] ?? 0);
            if ($qty === 0 || abs($qty) > 200) continue;
            $p = one('SELECT * FROM bar_products WHERE id = ? AND event_id = ?', [$pid, $eventId]);
            $unit = $p ? (int)$p['price'] + (int)$p['deposit'] : max(0, min(100000, (int)($it['unit'] ?? 0)));
            $name = $p ? $p['name'] : mb_substr((string)($it['name'] ?? 'Article'), 0, 80);
            $lines[] = ['id' => $pid, 'name' => $name, 'qty' => $qty, 'unit' => $unit];
            $total += $unit * $qty;
        }
        return [$lines, $total];
    }

    /**
     * Enregistre une vente. $in : method, items, bar_id, client_id, code|nfc (cashless), cash_given, staff, at (horodatage hors ligne), offline.
     * Idempotente sur client_id.
     */
    public static function sale(array $d, array $in): array
    {
        $cid = substr(preg_replace('~[^A-Za-z0-9_-]~', '', (string)($in['client_id'] ?? '')), 0, 40) ?: null;
        if ($cid && ($ex = one('SELECT * FROM pos_sales WHERE client_id = ?', [$cid]))) return self::out($ex) + ['duplicate' => true];
        $method = (string)($in['method'] ?? '');
        if (!isset(self::METHODS[$method])) throw new RuntimeException('Moyen de paiement inconnu.');
        $barId = (int)($d['bar_id'] ?: ($in['bar_id'] ?? 0));
        if (!val('SELECT id FROM bars WHERE id = ? AND event_id = ?', [$barId, $d['event_id']])) throw new RuntimeException('Choisis ton bar.');
        $at = now();
        if (!empty($in['at']) && ($ts = strtotime((string)$in['at'])) && $ts <= time() + 300 && $ts >= time() - 7 * 86400) $at = date('Y-m-d H:i:s', $ts);
        $row = ['event_id' => $d['event_id'], 'bar_id' => $barId, 'device_id' => $d['id'], 'method' => $method, 'staff' => mb_substr(trim((string)($in['staff'] ?? '')), 0, 60) ?: null,
            'client_id' => $cid, 'offline' => !empty($in['offline']) ? 1 : 0, 'created_at' => $at, 'received_at' => now()];
        if ($method === 'cashless') {
            $ev = one('SELECT cashless_enabled FROM events WHERE id = ?', [$d['event_id']]);
            if (!(int)$ev['cashless_enabled']) throw new RuntimeException('Le cashless n\'est pas activé pour cet événement.');
            $w = self::wallet((int)$d['event_id'], $in);
            if (!$w) throw new RuntimeException(!empty($in['nfc']) ? 'Bracelet non associé : associe-le d\'abord à un billet.' : 'Billet inconnu pour cet événement.');
            $items = array_map(fn($it) => [(int)($it['id'] ?? $it[0] ?? 0), (int)($it['qty'] ?? $it[1] ?? 0)], (array)($in['items'] ?? []));
            $r = Cashless::sale($w, $barId, $items, null, 'caisse:' . $d['id']);
            $id = insert('pos_sales', $row + ['amount' => (int)$r['total'], 'items' => json_encode($r['lines'], JSON_UNESCAPED_UNICODE), 'wallet_id' => $w['id'], 'wallet_tx_id' => $r['tx']]);
            return self::out(one('SELECT * FROM pos_sales WHERE id = ?', [$id])) + ['balance' => $r['balance'], 'name' => self::holder($w)];
        }
        [$lines, $total] = self::lines((int)$d['event_id'], (array)($in['items'] ?? []));
        if (!$lines || $total <= 0) throw new RuntimeException('Panier vide.');
        $given = $method === 'cash' && isset($in['cash_given']) ? max(0, (int)$in['cash_given']) : null;
        $ref = null;
        if ($method === 'card' && !empty($in['payment']) && is_array($in['payment'])) {
            $pm = $in['payment'];
            $ref = mb_substr(json_encode(['via' => mb_substr((string)($pm['via'] ?? ''), 0, 20), 'auth' => mb_substr((string)($pm['auth'] ?? ''), 0, 12), 'app' => mb_substr((string)($pm['app'] ?? ''), 0, 6),
                'paid' => (int)($pm['paid'] ?? 0), 'ref' => mb_substr((string)($pm['ref'] ?? ''), 0, 40)], JSON_UNESCAPED_UNICODE), 0, 255);
            // l'argent est déjà pris sur la carte : on enregistre toujours la vente, l'écart éventuel reste visible dans le détail
        }
        $id = insert('pos_sales', $row + ['amount' => $total, 'items' => json_encode($lines, JSON_UNESCAPED_UNICODE), 'cash_given' => $given, 'payment_ref' => $ref]);
        return self::out(one('SELECT * FROM pos_sales WHERE id = ?', [$id]));
    }

    private static function out(array $s): array
    {
        return ['ok' => true, 'sale' => ['id' => (int)$s['id'], 'method' => $s['method'], 'amount' => (int)$s['amount'], 'at' => $s['created_at'], 'client_id' => $s['client_id'], 'undone' => (bool)$s['undone']]];
    }

    /** Annulation d'une vente (erreur de saisie) dans les 10 minutes, depuis le même bar. */
    public static function undo(array $d, int $saleId): array
    {
        $s = one('SELECT * FROM pos_sales WHERE id = ? AND event_id = ? AND undone = 0 AND received_at >= NOW() - INTERVAL 10 MINUTE', [$saleId, $d['event_id']]);
        if (!$s || ($d['bar_id'] && (int)$s['bar_id'] !== (int)$d['bar_id'])) throw new RuntimeException('Vente introuvable ou trop ancienne pour être annulée (10 minutes).');
        $balance = null;
        if ($s['method'] === 'cashless' && $s['wallet_tx_id']) $balance = Cashless::undo((int)$s['wallet_tx_id'], (int)$d['event_id'], null)['balance'];
        update('pos_sales', ['undone' => 1], 'id = ?', [$s['id']]);
        return ['ok' => true, 'balance' => $balance];
    }

    /** Récapitulatif de caisse (fond de caisse, clôture) depuis $since. */
    public static function summary(int $eventId, ?int $barId, ?int $deviceId, string $since): array
    {
        $w = 'event_id = ? AND undone = 0 AND created_at >= ?'; $p = [$eventId, $since];
        if ($barId) { $w .= ' AND bar_id = ?'; $p[] = $barId; }
        if ($deviceId) { $w .= ' AND device_id = ?'; $p[] = $deviceId; }
        $by = [];
        foreach (all("SELECT method, COUNT(*) n, SUM(amount) total FROM pos_sales WHERE $w GROUP BY method", $p) as $r) $by[$r['method']] = ['n' => (int)$r['n'], 'total' => (int)$r['total']];
        $prod = [];
        foreach (all("SELECT items FROM pos_sales WHERE $w", $p) as $r) foreach (json_decode((string)$r['items'], true) ?: [] as $l) {
            $k = (string)$l['name']; $prod[$k]['qty'] = ($prod[$k]['qty'] ?? 0) + (int)$l['qty']; $prod[$k]['total'] = ($prod[$k]['total'] ?? 0) + (int)$l['qty'] * (int)$l['unit'];
        }
        uasort($prod, fn($a, $b) => $b['total'] <=> $a['total']);
        return ['since' => $since, 'methods' => $by, 'total' => array_sum(array_column($by, 'total')), 'count' => array_sum(array_column($by, 'n')), 'products' => array_slice($prod, 0, 15, true)];
    }

    /** Totaux pour l'organisateur (onglet cashless). */
    public static function stats(int $eventId): array
    {
        $by = [];
        foreach (all('SELECT method, COUNT(*) n, SUM(amount) total FROM pos_sales WHERE event_id = ? AND undone = 0 GROUP BY method', [$eventId]) as $r) $by[$r['method']] = ['n' => (int)$r['n'], 'total' => (int)$r['total']];
        $bars = all('SELECT b.id, b.name, SUM(CASE WHEN s.method = \'cashless\' THEN s.amount END) cashless, SUM(CASE WHEN s.method = \'card\' THEN s.amount END) card, SUM(CASE WHEN s.method = \'cash\' THEN s.amount END) cash, COUNT(s.id) n
            FROM bars b LEFT JOIN pos_sales s ON s.bar_id = b.id AND s.undone = 0 WHERE b.event_id = ? GROUP BY b.id, b.name ORDER BY b.id', [$eventId]);
        return ['methods' => $by, 'total' => array_sum(array_column($by, 'total')), 'bars' => $bars,
            'offline' => (int)val('SELECT COUNT(*) FROM pos_sales WHERE event_id = ? AND offline = 1', [$eventId])];
    }
}
