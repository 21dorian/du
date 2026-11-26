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
    public const METHODS = Till::METHODS;
    public const MODES = ['bar', 'topup', 'prep', 'display', 'access'];
    public const MODE_LABELS = ['bar' => 'Vente', 'topup' => 'Recharge cashless', 'prep' => 'Préparation', 'display' => 'Écran d\'appel', 'access' => 'Contrôle d\'accès'];

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
        update('pos_devices', ['bar_id' => null, 'mode' => 'bar', 'staff_name' => null, 'token_hash' => hash('sha256', $token), 'pair_code' => null, 'pair_expires' => null, 'paired_at' => now(), 'last_seen' => now(), 'platform' => mb_substr($platform, 0, 40)], 'id = ?', [$d['id']]);
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
        $st = Till::settings($ev);
        $se = Till::session($d);
        return ['device' => ['id' => (int)$d['id'], 'name' => $d['name'], 'bar_id' => $d['bar_id'] ? (int)$d['bar_id'] : null, 'mode' => in_array($d['mode'] ?? 'bar', self::MODES, true) ? $d['mode'] : 'bar'], 'tpe' => self::tpe($d),
            'event' => ['id' => (int)$ev['id'], 'name' => $ev['name'], 'starts_at' => $ev['starts_at'], 'cashless' => (bool)$ev['cashless_enabled']],
            'org' => ['name' => $org['name'], 'logo' => $org['logo'] ? abs_url('/' . ltrim($org['logo'], '/')) : null, 'accent' => $org['accent'] ?: null],
            'bars' => array_map(fn($b) => ['id' => (int)$b['id'], 'name' => $b['name']], $bars), 'methods' => $st['methods'],
            'settings' => ['tips' => (bool)$st['tips'], 'tip_pcts' => array_values(array_filter(array_map('intval', explode(',', (string)$st['tip_pcts'])))), 'tabs' => (bool)$st['tabs'], 'blind_close' => (bool)$st['blind_close'], 'free_amount' => (bool)$st['free_amount'], 'count_denoms' => (bool)$st['count_denoms'], 'denoms' => Till::DENOMS,
                'has_staff' => Till::hasStaff((int)$ev['organizer_id']), 'require_staff' => (bool)$st['require_staff'], 'sessions' => (bool)$st['sessions'], 'receipt' => $st['receipt'], 'photos' => (bool)$st['photos'], 'big' => (bool)$st['big_buttons'], 'reasons' => Till::reasons($st)],
            'session' => $se ? ['id' => (int)$se['id'], 'bar_id' => (int)$se['bar_id'], 'opened_at' => $se['opened_at'], 'float' => (int)$se['float_amount']] : null,
            'zones' => array_map(fn($z) => ['id' => (int)$z['id'], 'name' => $z['name'], 'profiles' => $z['profile_list'], 'capacity' => (int)$z['capacity'], 'color' => $z['color']], Pay::zones((int)$ev['id'], false)),
            'open_bars' => array_map('intval', array_column(all('SELECT DISTINCT bar_id FROM pos_sessions WHERE event_id = ? AND closed_at IS NULL', [$ev['id']]), 'bar_id')),
            'platform' => (string)setting('platform_name', 'Entrevo'), 'server_time' => date('c')] + Till::catalog($ev);
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
    /**
     * Enregistre une vente (ou un retour de consigne). Idempotente sur client_id.
     * $in : method, items, returns, discount_pct, offer_all, offer_reason, approver_pin, bar_id, client_id, code|nfc, cash_given,
     *       payment (carte), staff_id + staff_token, at (vente hors ligne), offline.
     */
    public static function sale(array $d, array $in): array
    {
        $cid = substr(preg_replace('~[^A-Za-z0-9_-]~', '', (string)($in['client_id'] ?? '')), 0, 40) ?: null;
        if ($cid && ($ex = one('SELECT * FROM pos_sales WHERE client_id = ?', [$cid]))) return self::out($ex) + ['duplicate' => true];
        $ev = one('SELECT * FROM events WHERE id = ?', [$d['event_id']]);
        $orgId = (int)$ev['organizer_id'];
        $set = Till::settings($ev);
        $method = (string)($in['method'] ?? '');
        if (!isset(Till::METHODS[$method])) throw new RuntimeException('Moyen de paiement inconnu.');
        if (in_array($method, ['cashless', 'card', 'cash', 'allowance'], true) && !in_array($method, $set['methods'], true)) throw new RuntimeException(Till::METHODS[$method] . ' n\'est pas accepté sur cet événement.');
        $barId = (int)($d['bar_id'] ?: ($in['bar_id'] ?? 0));
        if (!val('SELECT id FROM bars WHERE id = ? AND event_id = ?', [$barId, $d['event_id']])) throw new RuntimeException('Choisis ton bar.');
        $staff = Till::staffFrom($d, $in);
        if ($set['require_staff'] && !$staff && empty($in['offline'])) throw new RuntimeException('Connecte-toi avec ton code serveur.');
        $ts = time();
        if (!empty($in['at']) && ($t = strtotime((string)$in['at'])) && $t <= time() + 300 && $t >= time() - 7 * 86400) $ts = $t;
        $pin = isset($in['approver_pin']) ? (string)$in['approver_pin'] : null;
        $offline = !empty($in['offline']);
        // droits
        $hasOffer = !empty($in['offer_all']) || array_filter((array)($in['items'] ?? []), fn($it) => !empty($it['offered']));
        $approved = null;
        if ($hasOffer || $method === 'offered') $approved = Till::authorize($orgId, $staff, 'offer', $pin, !$offline) ?? $approved;
        if ((float)($in['discount_pct'] ?? 0) > 0) $approved = Till::authorize($orgId, $staff, 'discount', $pin, !$offline) ?? $approved;
        if (!empty($in['returns'])) Till::authorize($orgId, $staff, 'deposit', $pin, false);
        if ($method === 'offered') $in['offer_all'] = 1;
        $pr = Till::price($ev, $barId, $in, $ts);
        if (!$pr['lines']) throw new RuntimeException('Panier vide.');
        $session = Till::session($d);
        $row = ['event_id' => $d['event_id'], 'bar_id' => $barId, 'device_id' => $d['id'], 'method' => $method, 'staff' => $staff['name'] ?? (mb_substr(trim((string)($in['staff'] ?? '')), 0, 60) ?: null),
            'staff_id' => $staff['id'] ?? null, 'session_id' => $session['id'] ?? null, 'client_id' => $cid, 'offline' => $offline ? 1 : 0, 'created_at' => date('Y-m-d H:i:s', $ts), 'received_at' => now(),
            'subtotal' => $pr['subtotal'], 'discount' => $pr['discount'], 'offered_value' => $pr['offered'], 'offer_reason' => ($pr['offered'] ? mb_substr(trim((string)($in['offer_reason'] ?? '')), 0, 120) ?: null : null),
            'approved_by' => $approved && (!$staff || (int)$approved['id'] !== (int)$staff['id']) ? $approved['id'] : null, 'vat_json' => $pr['vat'] ? json_encode($pr['vat']) : null,
            'items' => json_encode($pr['lines'], JSON_UNESCAPED_UNICODE), 'receipt_token' => bin2hex(random_bytes(12))];
        $total = $pr['total'];
        $extra = [];
        db()->beginTransaction();
        try {
            if ($method === 'allowance') {
                $a = Till::allowance((int)$ev['id'], $in);
                if (!$a) throw new RuntimeException('Badge de crédit inconnu.');
                if ($pr['deposits'] < 0) throw new RuntimeException('Un retour de consigne ne se paie pas avec un crédit.');
                $cats = array_filter(explode(',', (string)$a['category_ids']));
                if ($cats) foreach ($pr['lines'] as $l) if ($l['kind'] === 'product' && !in_array((string)$l['category_id'], $cats, true)) throw new RuntimeException($l['name'] . ' n\'est pas couvert par ce crédit.');
                $use = $a['mode'] === 'euros' ? $total : $pr['products'];
                $u = Till::allowanceUsed($a);
                if ($use > $u['left']) throw new RuntimeException('Crédit insuffisant : il reste ' . Till::allowanceLabel($a, $u['left'] === PHP_INT_MAX ? 0 : $u['left']) . ' aujourd\'hui pour ' . $a['holder'] . '.');
                $row['allowance_id'] = $a['id']; $row['offered_value'] += $total; $row['offer_reason'] = $row['offer_reason'] ?: (Till::GROUPS[$a['grp']] ?? 'Crédit') . ' · ' . $a['holder'];
                $total = 0;
                $extra = ['holder' => $a['holder'], 'left' => Till::allowanceLabel($a, $u['left'] === PHP_INT_MAX ? 0 : $u['left'] - $use), 'unlimited' => $u['left'] === PHP_INT_MAX];
            } elseif ($method === 'offered') {
                $row['offered_value'] += $total; $total = 0;
            } elseif ($method === 'cashless') {
                if (!(int)$ev['cashless_enabled']) throw new RuntimeException('Le cashless n\'est pas activé pour cet événement.');
                $w = self::wallet((int)$d['event_id'], $in);
                if (!$w) throw new RuntimeException(!empty($in['nfc']) ? 'Bracelet non associé : associe-le d\'abord à un billet.' : 'Billet inconnu pour cet événement.');
                $r = Cashless::charge($w, $total, (int)$barId, $pr['lines'], 'caisse:' . $d['id']);
                $row['wallet_id'] = $w['id']; $row['wallet_tx_id'] = $r['tx'];
                $extra = ['balance' => $r['balance'], 'name' => self::holder($w)];
            } elseif ($method === 'card') {
                if ($total < 0) throw new RuntimeException('Un remboursement de consigne se fait en espèces ou en cashless.');
                if (!empty($in['payment']) && is_array($in['payment'])) { $pm = $in['payment'];
                    $row['payment_ref'] = mb_substr(json_encode(['via' => mb_substr((string)($pm['via'] ?? ''), 0, 20), 'auth' => mb_substr((string)($pm['auth'] ?? ''), 0, 12), 'app' => mb_substr((string)($pm['app'] ?? ''), 0, 6), 'paid' => (int)($pm['paid'] ?? 0), 'ref' => mb_substr((string)($pm['ref'] ?? ''), 0, 40)], JSON_UNESCAPED_UNICODE), 0, 255); }
            } elseif ($method === 'cash') {
                $row['cash_given'] = isset($in['cash_given']) ? max(0, (int)$in['cash_given']) : null;
            } elseif ($method === 'split') {
                // 2.8 : paiement en plusieurs fois (ex. le solde cashless puis le reste en carte)
                $parts = array_values(array_filter((array)($in['payments'] ?? []), 'is_array'));
                if (count($parts) < 2 || count($parts) > 4) throw new RuntimeException('Un paiement fractionné se fait en 2 à 4 parties.');
                if ($total <= 0) throw new RuntimeException('Rien à fractionner.');
                $sum = 0; $out = []; $hasCashless = false;
                foreach ($parts as $pt) {
                    $m = (string)($pt['method'] ?? ''); $a = (int)($pt['amount'] ?? 0);
                    if (!in_array($m, ['cashless', 'card', 'cash'], true) || !in_array($m, $set['methods'], true)) throw new RuntimeException('Moyen de paiement non accepté dans un paiement fractionné.');
                    if ($a <= 0) throw new RuntimeException('Chaque partie doit avoir un montant.');
                    $sum += $a; $o = ['method' => $m, 'amount' => $a];
                    if ($m === 'cashless') {
                        if ($hasCashless) throw new RuntimeException('Un seul porte-monnaie cashless par paiement.');
                        $hasCashless = true;
                        $w = self::wallet((int)$d['event_id'], $pt);
                        if (!$w) throw new RuntimeException('Billet ou bracelet inconnu.');
                        $r = Cashless::charge($w, $a, (int)$barId, $pr['lines'], 'caisse:' . $d['id']);
                        $row['wallet_id'] = $w['id']; $row['wallet_tx_id'] = $r['tx'];
                        $extra = ['balance' => $r['balance'], 'name' => self::holder($w)];
                    }
                    if ($m === 'card' && !empty($pt['payment']['auth'])) $o['auth'] = mb_substr((string)$pt['payment']['auth'], 0, 12);
                    if ($m === 'cash' && isset($pt['cash_given'])) { $o['given'] = max(0, (int)$pt['cash_given']); $row['cash_given'] = $o['given']; }
                    $out[] = $o;
                }
                if ($sum !== $total) throw new RuntimeException('Les parties (' . money($sum) . ') ne font pas le total (' . money($total) . ').');
                $row['payments_json'] = json_encode($out);
            }
            $row['amount'] = $total;
            // 3.1 : pourboire (carte ou espèces), hors chiffre d'affaires
            if ($set['tips'] && in_array($method, ['card', 'cash'], true) && $total > 0) $row['tip'] = max(0, min($total, (int)($in['tip'] ?? 0)));
            $row['ticket_no'] = Till::nextTicket((int)$d['event_id']);
            // 2.8 : commande à préparer (cuisine, comptoir) → numéro à appeler
            if (array_filter($pr['lines'], fn($l) => !empty($l['prep']))) { $row['order_no'] = Till::nextOrder($barId); $row['prep_status'] = 'new'; }
            $tab = !empty($in['tab_id']) ? one("SELECT id FROM pos_tabs WHERE id = ? AND event_id = ? AND status = 'open' FOR UPDATE", [(int)$in['tab_id'], $d['event_id']]) : null;
            if (!empty($in['tab_id']) && !$tab) throw new RuntimeException('Cette addition a déjà été réglée sur un autre appareil.');
            if ($tab) $row['tab_id'] = $tab['id'];
            $id = insert('pos_sales', $row);
            if ($tab) update('pos_tabs', ['status' => 'paid', 'sale_id' => $id, 'closed_at' => now(), 'updated_at' => now()], 'id = ?', [$tab['id']]);
            foreach ($pr['lines'] as $l) if ($l['kind'] === 'product') foreach ($l['stock_items'] ?? [] as [$spid, $sq]) Till::stockMove((int)$d['event_id'], (int)$spid, $barId, -(int)$sq, 'sale', $id);
            db()->commit();
        } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
        $sale = one('SELECT * FROM pos_sales WHERE id = ?', [$id]);
        if (!empty($in['receipt_email']) && is_email((string)$in['receipt_email'])) { try { Till::sendReceipt($sale, (string)$in['receipt_email']); } catch (Throwable $e) { log_line('caisse', 'Reçu : ' . $e->getMessage()); } }
        return self::out($sale) + $extra;
    }

    // ---------- 2.10 : additions ouvertes (tables, comptes clients), partagées par les appareils du point de vente ----------
    private static function tabOut(array $t): array
    {
        return ['id' => (int)$t['id'], 'label' => $t['label'], 'total' => (int)$t['total'], 'items' => (int)$t['items'], 'version' => (int)$t['version'], 'staff' => $t['staff'],
            'created_at' => $t['created_at'], 'updated_at' => $t['updated_at'], 'cart' => json_decode((string)$t['cart_json'], true) ?: []];
    }

    public static function tabs(array $d): array
    {
        if (!$d['bar_id']) return [];
        return array_map([self::class, 'tabOut'], all("SELECT * FROM pos_tabs WHERE event_id = ? AND bar_id = ? AND status = 'open' ORDER BY created_at", [$d['event_id'], $d['bar_id']]));
    }

    /** Crée ou met à jour une addition. $in : id?, version?, label, cart {items, returns, discount_pct}. */
    public static function tabSave(array $d, array $in): array
    {
        $ev = one('SELECT * FROM events WHERE id = ?', [$d['event_id']]);
        if (!Till::settings($ev)['tabs']) throw new RuntimeException('Les additions ne sont pas activées sur cet événement.');
        if (!$d['bar_id']) throw new RuntimeException('Choisis ton point de vente.');
        $cart = is_array($in['cart'] ?? null) ? $in['cart'] : [];
        $cart = ['items' => array_values(array_slice((array)($cart['items'] ?? []), 0, 120)), 'returns' => array_values(array_slice((array)($cart['returns'] ?? []), 0, 20)), 'discount_pct' => 0];
        $pr = Till::price($ev, (int)$d['bar_id'], $cart, time());
        $label = mb_substr(trim((string)($in['label'] ?? '')), 0, 80);
        $st = Till::staffFrom($d, $in);
        $row = ['cart_json' => json_encode($cart, JSON_UNESCAPED_UNICODE), 'total' => $pr['total'], 'items' => $pr['products'], 'updated_at' => now(), 'device_id' => $d['id']];
        if (!empty($in['id'])) {
            $t = one("SELECT * FROM pos_tabs WHERE id = ? AND event_id = ? AND bar_id = ?", [(int)$in['id'], $d['event_id'], $d['bar_id']]);
            if (!$t || $t['status'] !== 'open') throw new RuntimeException('Cette addition a déjà été réglée ou annulée.');
            if (isset($in['version']) && (int)$in['version'] !== (int)$t['version']) throw new RuntimeException('Cette addition a été modifiée sur un autre appareil : rouvre-la pour voir la dernière version.');
            if ($label !== '') $row['label'] = $label;
            q('UPDATE pos_tabs SET version = version + 1 WHERE id = ?', [$t['id']]);
            update('pos_tabs', $row, 'id = ?', [$t['id']]);
            return self::tabOut(one('SELECT * FROM pos_tabs WHERE id = ?', [$t['id']]));
        }
        if ($label === '') throw new RuntimeException('Donne un nom à l\'addition (table, prénom…).');
        if (!$pr['lines']) throw new RuntimeException('Addition vide.');
        $id = insert('pos_tabs', $row + ['event_id' => $d['event_id'], 'bar_id' => $d['bar_id'], 'label' => $label, 'staff_id' => $st['id'] ?? null, 'staff' => $st['name'] ?? (mb_substr(trim((string)($in['staff'] ?? '')), 0, 60) ?: null), 'created_at' => now()]);
        return self::tabOut(one('SELECT * FROM pos_tabs WHERE id = ?', [$id]));
    }

    public static function tabCancel(array $d, array $in): void
    {
        $t = one("SELECT * FROM pos_tabs WHERE id = ? AND event_id = ? AND bar_id = ? AND status = 'open'", [(int)($in['id'] ?? 0), $d['event_id'], $d['bar_id']]);
        if (!$t) throw new RuntimeException('Addition introuvable.');
        $ev = one('SELECT * FROM events WHERE id = ?', [$d['event_id']]);
        if ((int)$t['total'] > 0) Till::authorize((int)$ev['organizer_id'], Till::staffFrom($d, $in), 'undo', $in['approver_pin'] ?? null, (bool)Till::settings($ev)['require_staff']);
        update('pos_tabs', ['status' => 'cancelled', 'closed_at' => now(), 'updated_at' => now()], 'id = ?', [$t['id']]);
    }

    // ---------- 2.8 : point de recharge ----------
    private static function staffRow(array $d, array $in, array $extra): array
    {
        $st = Till::staffFrom($d, $in);
        $se = Till::session($d);
        return ['event_id' => $d['event_id'], 'bar_id' => (int)($d['bar_id'] ?: ($in['bar_id'] ?? 0)), 'device_id' => $d['id'], 'staff_id' => $st['id'] ?? null, 'staff' => $st['name'] ?? null,
            'session_id' => $se['id'] ?? null, 'created_at' => now(), 'received_at' => now(), 'receipt_token' => bin2hex(random_bytes(12)),
            'client_id' => substr(preg_replace('~[^A-Za-z0-9_-]~', '', (string)($in['client_id'] ?? '')), 0, 40) ?: null] + $extra;
    }

    /** Recharge d'un porte-monnaie au stand (espèces ou carte) : l'argent entre dans la caisse. */
    public static function topup(array $d, array $in): array
    {
        $ev = one('SELECT * FROM events WHERE id = ?', [$d['event_id']]);
        if (!(int)$ev['cashless_enabled']) throw new RuntimeException('Le cashless n\'est pas activé pour cet événement.');
        $method = ($in['method'] ?? '') === 'card' ? 'card' : 'cash';
        $amount = (int)($in['amount'] ?? 0);
        if ($amount < 100 || $amount > 50000) throw new RuntimeException('Recharge entre 1 € et 500 €.');
        $w = self::wallet((int)$ev['id'], $in);
        if (!$w) throw new RuntimeException('Billet ou bracelet inconnu.');
        $row = self::staffRow($d, $in, ['method' => $method, 'kind' => 'topup', 'amount' => $amount, 'subtotal' => $amount, 'wallet_id' => $w['id'],
            'items' => json_encode([['kind' => 'topup', 'name' => 'Recharge cashless', 'qty' => 1, 'unit' => $amount, 'total' => $amount]], JSON_UNESCAPED_UNICODE),
            'cash_given' => $method === 'cash' && isset($in['cash_given']) ? max(0, (int)$in['cash_given']) : null]);
        if (!$row['bar_id']) throw new RuntimeException('Choisis ton point de vente.');
        db()->beginTransaction();
        try {
            $r = Cashless::topup($w, $amount, $method, ['bar_id' => $row['bar_id'], 'device' => 'recharge:' . $d['id']]);
            $row['wallet_tx_id'] = $r['tx']; $row['ticket_no'] = Till::nextTicket((int)$ev['id']);
            $id = insert('pos_sales', $row);
            db()->commit();
        } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
        return self::out(one('SELECT * FROM pos_sales WHERE id = ?', [$id])) + ['balance' => $r['balance'], 'name' => self::holder($w)];
    }

    /** Nouveau porte-monnaie sans billet (bracelet NFC vendu ou prêté sur place). */
    public static function newWallet(array $d, array $in): array
    {
        $uid = self::nfc((string)($in['nfc'] ?? ''));
        if (strlen($uid) < 8) throw new RuntimeException('Approche le bracelet pour l\'activer.');
        if (one('SELECT id FROM wallets WHERE event_id = ? AND nfc_uid = ?', [$d['event_id'], $uid])) throw new RuntimeException('Ce bracelet est déjà activé.');
        do { $code = 'BR' . strtoupper(bin2hex(random_bytes(5))); } while (val('SELECT id FROM wallets WHERE code = ?', [$code]));
        $id = insert('wallets', ['event_id' => $d['event_id'], 'ticket_id' => null, 'code' => $code, 'token' => token(24), 'email' => null, 'balance' => 0, 'status' => 'active', 'nfc_uid' => $uid, 'created_at' => now()]);
        return one('SELECT * FROM wallets WHERE id = ?', [$id]);
    }

    /** Remboursement du solde sur place, en espèces (droit « rembourser un solde »). */
    public static function cashout(array $d, array $in): array
    {
        $ev = one('SELECT * FROM events WHERE id = ?', [$d['event_id']]);
        Till::authorize((int)$ev['organizer_id'], Till::staffFrom($d, $in), 'cashout', $in['approver_pin'] ?? null, true);
        $w = self::wallet((int)$ev['id'], $in);
        if (!$w) throw new RuntimeException('Billet ou bracelet inconnu.');
        $amount = (int)val('SELECT balance FROM wallets WHERE id = ?', [$w['id']]);
        if ($amount <= 0) throw new RuntimeException('Solde nul : rien à rembourser.');
        $row = self::staffRow($d, $in, ['method' => 'cash', 'kind' => 'cashout', 'amount' => -$amount, 'subtotal' => -$amount, 'wallet_id' => $w['id'],
            'items' => json_encode([['kind' => 'cashout', 'name' => 'Remboursement du solde cashless', 'qty' => 1, 'unit' => -$amount, 'total' => -$amount]], JSON_UNESCAPED_UNICODE)]);
        if (!$row['bar_id']) throw new RuntimeException('Choisis ton point de vente.');
        db()->beginTransaction();
        try {
            $tx = insert('wallet_tx', ['wallet_id' => $w['id'], 'event_id' => $ev['id'], 'kind' => 'refund', 'amount' => -$amount, 'bar_id' => $row['bar_id'], 'device' => 'recharge:' . $d['id'], 'items' => json_encode(['sur place' => true]), 'created_at' => now()]);
            q("UPDATE wallets SET balance = 0, status = 'refunded', refunded_at = NOW() WHERE id = ?", [$w['id']]);
            $row['wallet_tx_id'] = $tx; $row['ticket_no'] = Till::nextTicket((int)$ev['id']);
            $id = insert('pos_sales', $row);
            db()->commit();
        } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
        return self::out(one('SELECT * FROM pos_sales WHERE id = ?', [$id])) + ['refunded' => $amount, 'name' => self::holder($w)];
    }

    // ---------- 2.8 : préparation ----------
    public static function prepList(array $d, bool $readyOnly = false): array
    {
        $w = 's.event_id = ? AND s.undone = 0 AND s.order_no IS NOT NULL AND s.created_at >= NOW() - INTERVAL 8 HOUR'; $p = [$d['event_id']];
        if ($d['bar_id']) { $w .= ' AND s.bar_id = ?'; $p[] = $d['bar_id']; }
        $w .= $readyOnly ? " AND (s.prep_status = 'ready' OR (s.prep_status = 'served' AND s.served_at >= NOW() - INTERVAL 20 SECOND))" : " AND s.prep_status IN ('new','ready')";
        return array_map(fn($s) => ['id' => (int)$s['id'], 'order' => (int)$s['order_no'], 'status' => $s['prep_status'], 'bar' => $s['bar'], 'at' => $s['created_at'], 'ready_at' => $s['ready_at'],
            'lines' => array_values(array_map(fn($l) => ['name' => $l['name'], 'qty' => (int)$l['qty']], array_filter(json_decode((string)$s['items'], true) ?: [], fn($l) => ($l['kind'] ?? '') === 'product' && !empty($l['prep']))))],
            all("SELECT s.*, b.name bar FROM pos_sales s JOIN bars b ON b.id = s.bar_id WHERE $w ORDER BY s.id LIMIT 80", $p));
    }

    public static function prepSet(array $d, int $saleId, string $status): void
    {
        if (!in_array($status, ['new', 'ready', 'served'], true)) throw new RuntimeException('État inconnu.');
        $s = one('SELECT * FROM pos_sales WHERE id = ? AND event_id = ? AND order_no IS NOT NULL', [$saleId, $d['event_id']]);
        if (!$s) throw new RuntimeException('Commande introuvable.');
        update('pos_sales', ['prep_status' => $status, 'ready_at' => $status === 'ready' ? now() : ($status === 'new' ? null : $s['ready_at']), 'served_at' => $status === 'served' ? now() : null], 'id = ?', [$s['id']]);
    }

    private static function out(array $s): array
    {
        return ['ok' => true, 'sale' => ['id' => (int)$s['id'], 'ticket' => (int)$s['ticket_no'], 'order' => $s['order_no'] ? (int)$s['order_no'] : null, 'method' => $s['method'], 'amount' => (int)$s['amount'], 'tip' => (int)($s['tip'] ?? 0), 'offered' => (int)$s['offered_value'], 'discount' => (int)$s['discount'],
            'at' => $s['created_at'], 'client_id' => $s['client_id'], 'undone' => (bool)$s['undone'], 'receipt' => $s['receipt_token'] ? Till::receiptUrl($s) : null,
            'lines' => array_map(fn($l) => ['name' => $l['name'], 'qty' => (int)$l['qty'], 'total' => (int)$l['total'], 'offered' => !empty($l['offered']), 'promo' => $l['promo'] ?? null], json_decode((string)$s['items'], true) ?: [])]];
    }

    // ---------- 3.1 : remboursement partiel d'un ticket ----------
    /** Lignes d'un ticket avec les quantités déjà remboursées. */
    public static function saleGet(array $d, array $in): array
    {
        $s = !empty($in['sale_id']) ? one('SELECT s.*, b.name bar FROM pos_sales s JOIN bars b ON b.id = s.bar_id WHERE s.id = ? AND s.event_id = ?', [(int)$in['sale_id'], $d['event_id']])
            : one('SELECT s.*, b.name bar FROM pos_sales s JOIN bars b ON b.id = s.bar_id WHERE s.ticket_no = ? AND s.event_id = ?', [(int)($in['ticket'] ?? 0), $d['event_id']]);
        if (!$s) throw new RuntimeException('Ticket introuvable pour cet événement.');
        if (($s['kind'] ?? 'sale') !== 'sale') throw new RuntimeException('Ce ticket n\'est pas une vente.');
        if ((int)$s['undone']) throw new RuntimeException('Cette vente a été annulée.');
        $done = self::refundedQty((int)$s['id']);
        $lines = [];
        foreach (json_decode((string)$s['items'], true) ?: [] as $i => $l) {
            if (($l['kind'] ?? '') !== 'product' || (int)$l['qty'] <= 0) continue;
            $lines[] = ['i' => $i, 'name' => $l['name'], 'qty' => (int)$l['qty'], 'left' => max(0, (int)$l['qty'] - ($done[$i] ?? 0)), 'unit' => (int)round((int)$l['total'] / max(1, (int)$l['qty'])), 'total' => (int)$l['total']];
        }
        $paid = !in_array($s['method'], ['allowance', 'offered'], true);
        return ['id' => (int)$s['id'], 'ticket' => (int)$s['ticket_no'], 'bar' => $s['bar'], 'at' => $s['created_at'], 'method' => $s['method'], 'amount' => (int)$s['amount'], 'lines' => $lines, 'refundable' => $paid,
            'refund_method' => $s['method'] === 'split' ? 'cash' : $s['method'], 'refunded' => -(int)val("SELECT COALESCE(SUM(amount), 0) FROM pos_sales WHERE refund_of = ? AND undone = 0", [$s['id']])];
    }

    private static function refundedQty(int $saleId): array
    {
        $done = [];
        foreach (all('SELECT items FROM pos_sales WHERE refund_of = ? AND undone = 0', [$saleId]) as $r) foreach (json_decode((string)$r['items'], true) ?: [] as $l) if (isset($l['ref'])) $done[(int)$l['ref']] = ($done[(int)$l['ref']] ?? 0) + (int)$l['qty'];
        return $done;
    }

    /** Rembourse une partie d'un ticket : même moyen de paiement (le cashless est recrédité), stock remis. */
    public static function refund(array $d, array $in): array
    {
        $ev = one('SELECT * FROM events WHERE id = ?', [$d['event_id']]);
        $staff = Till::staffFrom($d, $in);
        $by = Till::authorize((int)$ev['organizer_id'], $staff, 'refund', $in['approver_pin'] ?? null, true);
        $info = self::saleGet($d, ['sale_id' => (int)($in['sale_id'] ?? 0)]);
        if (!$info['refundable']) throw new RuntimeException('Une vente offerte ou payée avec un crédit ne se rembourse pas.');
        $s = one('SELECT * FROM pos_sales WHERE id = ?', [$info['id']]);
        $items = json_decode((string)$s['items'], true) ?: [];
        $want = []; foreach ((array)($in['lines'] ?? []) as $x) if ((int)($x['qty'] ?? 0) > 0) $want[(int)$x['i']] = (int)$x['qty'];
        if (!$want) throw new RuntimeException('Choisis les articles à rembourser.');
        $left = []; foreach ($info['lines'] as $l) $left[$l['i']] = $l['left'];
        $lines = []; $sum = 0; $vat = []; $restock = [];
        foreach ($want as $i => $q) {
            if (!isset($left[$i]) || $q > $left[$i]) throw new RuntimeException('Quantité à rembourser supérieure à ce qui reste sur le ticket.');
            $l = $items[$i]; $amt = (int)round((int)$l['total'] * $q / max(1, (int)$l['qty']));
            $lines[] = ['kind' => 'product', 'id' => (int)$l['id'], 'name' => 'Remboursement ' . $l['name'], 'qty' => $q, 'unit' => -(int)round($amt / $q), 'full' => -$amt, 'total' => -$amt, 'ref' => $i,
                'vat' => $l['vat'] ?? null, 'category_id' => $l['category_id'] ?? null, 'cost' => isset($l['cost']) && $l['cost'] !== null ? -(int)round((int)$l['cost'] * $q / max(1, (int)$l['qty'])) : null];
            $sum += $amt;
            if (isset($l['vat'])) { $k = number_format((float)$l['vat'], 1, '.', ''); $vat[$k] = ($vat[$k] ?? 0) - $amt; }
            foreach ($l['stock_items'] ?? [] as [$spid, $sq]) $restock[] = [(int)$spid, (int)round((int)$sq * $q / max(1, (int)$l['qty']))];
        }
        $method = $info['refund_method'];
        $se = Till::session($d);
        $row = ['event_id' => $d['event_id'], 'bar_id' => (int)$s['bar_id'], 'device_id' => $d['id'], 'method' => $method, 'kind' => 'refund', 'amount' => -$sum, 'subtotal' => -$sum,
            'staff' => $staff['name'] ?? null, 'staff_id' => $staff['id'] ?? null, 'approved_by' => $by && (!$staff || (int)$by['id'] !== (int)$staff['id']) ? $by['id'] : null, 'session_id' => $se['id'] ?? null,
            'refund_of' => $s['id'], 'offer_reason' => mb_substr(trim((string)($in['reason'] ?? '')), 0, 120) ?: null, 'items' => json_encode($lines, JSON_UNESCAPED_UNICODE), 'vat_json' => $vat ? json_encode($vat) : null,
            'created_at' => now(), 'received_at' => now(), 'receipt_token' => bin2hex(random_bytes(12)), 'wallet_id' => $s['wallet_id']];
        $balance = null;
        db()->beginTransaction();
        try {
            if ($method === 'cashless' && $s['wallet_id']) {
                $tx = insert('wallet_tx', ['wallet_id' => $s['wallet_id'], 'event_id' => $d['event_id'], 'kind' => 'rembours', 'amount' => $sum, 'bar_id' => $s['bar_id'], 'device' => 'caisse:' . $d['id'], 'items' => json_encode(['ticket' => (int)$s['ticket_no']]), 'created_at' => now()]);
                q('UPDATE wallets SET balance = balance + ? WHERE id = ?', [$sum, $s['wallet_id']]);
                $row['wallet_tx_id'] = $tx; $balance = (int)val('SELECT balance FROM wallets WHERE id = ?', [$s['wallet_id']]);
            }
            $row['ticket_no'] = Till::nextTicket((int)$d['event_id']);
            $id = insert('pos_sales', $row);
            foreach ($restock as [$pid, $q]) if ($q > 0) Till::stockMove((int)$d['event_id'], $pid, (int)$s['bar_id'], $q, 'refund', $id);
            db()->commit();
        } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
        return self::out(one('SELECT * FROM pos_sales WHERE id = ?', [$id])) + ['refunded' => $sum, 'method' => $method, 'balance' => $balance];
    }

    // ---------- 3.1 : stock du bar depuis l'appli ----------
    public static function stockState(array $d): array
    {
        if (!$d['bar_id']) throw new RuntimeException('Choisis ton point de vente.');
        $rows = all('SELECT p.id, p.name, p.stock_alert, COALESCE(s.qty, 0) qty FROM bar_products p LEFT JOIN pos_stock s ON s.product_id = p.id AND s.bar_id = ? WHERE p.event_id = ? AND p.track_stock = 1 AND p.active = 1 ORDER BY p.sort, p.name', [$d['bar_id'], $d['event_id']]);
        $req = []; foreach (all("SELECT product_id, SUM(qty) q FROM pos_stock_requests WHERE bar_id = ? AND status = 'open' GROUP BY product_id", [$d['bar_id']]) as $r) $req[(int)$r['product_id']] = (int)$r['q'];
        return array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name'], 'qty' => (int)$r['qty'], 'alert' => $r['stock_alert'] !== null ? (int)$r['stock_alert'] : 10, 'requested' => $req[(int)$r['id']] ?? 0], $rows);
    }

    public static function stockLoss(array $d, array $in): void
    {
        $ev = one('SELECT * FROM events WHERE id = ?', [$d['event_id']]);
        $staff = Till::staffFrom($d, $in);
        Till::authorize((int)$ev['organizer_id'], $staff, 'stock', $in['approver_pin'] ?? null, (bool)Till::settings($ev)['require_staff']);
        $p = one('SELECT * FROM bar_products WHERE id = ? AND event_id = ?', [(int)($in['product_id'] ?? 0), $d['event_id']]);
        $q = (int)($in['qty'] ?? 0);
        if (!$p || $q <= 0 || $q > 1000 || !$d['bar_id']) throw new RuntimeException('Produit ou quantité invalide.');
        $reason = in_array($in['reason'] ?? '', ['casse', 'perime', 'offert', 'vol', 'autre'], true) ? (string)$in['reason'] : 'casse';
        Till::stockMove((int)$d['event_id'], (int)$p['id'], (int)$d['bar_id'], -$q, 'loss', null, null, $reason . ($staff ? ' · ' . $staff['name'] : '') . (!empty($in['note']) ? ' · ' . mb_substr((string)$in['note'], 0, 100) : ''));
    }

    public static function stockRequest(array $d, array $in): void
    {
        $p = one('SELECT id FROM bar_products WHERE id = ? AND event_id = ?', [(int)($in['product_id'] ?? 0), $d['event_id']]);
        $q = (int)($in['qty'] ?? 0);
        if (!$p || $q <= 0 || $q > 5000 || !$d['bar_id']) throw new RuntimeException('Produit ou quantité invalide.');
        $staff = Till::staffFrom($d, $in);
        insert('pos_stock_requests', ['event_id' => $d['event_id'], 'bar_id' => $d['bar_id'], 'product_id' => $p['id'], 'qty' => $q, 'note' => mb_substr(trim((string)($in['note'] ?? '')), 0, 255) ?: null,
            'staff' => $staff['name'] ?? (mb_substr((string)($in['staff'] ?? ''), 0, 60) ?: null), 'device_id' => $d['id'], 'status' => 'open', 'created_at' => now()]);
    }

    /** Annulation d'une vente dans les 10 minutes (droit « annuler » ou code d'un responsable). */
    public static function undo(array $d, int $saleId, array $in = []): array
    {
        $s = one('SELECT * FROM pos_sales WHERE id = ? AND event_id = ? AND undone = 0 AND received_at >= NOW() - INTERVAL 10 MINUTE', [$saleId, $d['event_id']]);
        if (!$s || ($d['bar_id'] && (int)$s['bar_id'] !== (int)$d['bar_id'])) throw new RuntimeException('Vente introuvable ou trop ancienne pour être annulée (10 minutes).');
        $ev = one('SELECT * FROM events WHERE id = ?', [$d['event_id']]);
        $staff = Till::staffFrom($d, $in);
        if (Till::settings($ev)['require_staff'] || isset($in['approver_pin'])) Till::authorize((int)$ev['organizer_id'], $staff, 'undo', $in['approver_pin'] ?? null, true);
        $balance = null;
        db()->beginTransaction();
        try {
            if (in_array($s['method'], ['cashless', 'split'], true) && $s['wallet_tx_id']) $balance = Cashless::undo((int)$s['wallet_tx_id'], (int)$d['event_id'], null)['balance'];
            update('pos_sales', ['undone' => 1], 'id = ?', [$s['id']]);
            foreach (json_decode((string)$s['items'], true) ?: [] as $l) if (($l['kind'] ?? '') === 'product') foreach ($l['stock_items'] ?? (!empty($l['stock']) ? [[(int)$l['id'], (int)$l['qty']]] : []) as [$spid, $sq]) Till::stockMove((int)$d['event_id'], (int)$spid, (int)$s['bar_id'], (int)$sq, 'undo', (int)$s['id']);
            db()->commit();
        } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
        return ['ok' => true, 'balance' => $balance];
    }

    /** Récapitulatif (session de caisse ouverte, ou ventes depuis $since). */
    public static function summary(int $eventId, ?int $barId, ?int $deviceId, string $since): array
    {
        $w = 'event_id = ? AND undone = 0 AND created_at >= ?'; $p = [$eventId, $since];
        if ($barId) { $w .= ' AND bar_id = ?'; $p[] = $barId; }
        if ($deviceId) { $w .= ' AND device_id = ?'; $p[] = $deviceId; }
        $rows = all("SELECT method, kind, amount, offered_value, discount, payments_json FROM pos_sales WHERE $w", $p);
        $by = Till::byMethod($rows)['methods'];
        $w .= " AND kind = 'sale'";
        $prod = [];
        foreach (all("SELECT items FROM pos_sales WHERE $w", $p) as $r) foreach (json_decode((string)$r['items'], true) ?: [] as $l) {
            $k = (string)$l['name']; $prod[$k]['qty'] = ($prod[$k]['qty'] ?? 0) + (int)$l['qty']; $prod[$k]['total'] = ($prod[$k]['total'] ?? 0) + (int)($l['total'] ?? $l['qty'] * $l['unit']);
        }
        uasort($prod, fn($a, $b) => $b['total'] <=> $a['total']);
        return ['since' => $since, 'methods' => $by, 'total' => array_sum(array_column($by, 'total')), 'count' => array_sum(array_column($by, 'n')), 'products' => array_slice($prod, 0, 15, true)];
    }

    /** Totaux pour l'organisateur. */
    public static function stats(int $eventId): array
    {
        $rows = all('SELECT bar_id, method, kind, amount, offered_value, discount, payments_json FROM pos_sales WHERE event_id = ? AND undone = 0', [$eventId]);
        $all = Till::byMethod($rows);
        $bars = [];
        foreach (all('SELECT id, name FROM bars WHERE event_id = ? ORDER BY id', [$eventId]) as $b) {
            $bm = Till::byMethod(array_values(array_filter($rows, fn($r) => (int)$r['bar_id'] === (int)$b['id'])));
            $bars[] = ['id' => $b['id'], 'name' => $b['name'], 'cashless' => $bm['methods']['cashless']['total'] ?? 0, 'card' => $bm['methods']['card']['total'] ?? 0, 'cash' => $bm['methods']['cash']['total'] ?? 0, 'offered' => $bm['offered'], 'n' => $bm['count']];
        }
        return ['methods' => $all['methods'], 'total' => $all['total'], 'topups' => $all['topups'], 'bars' => $bars, 'offline' => (int)val('SELECT COUNT(*) FROM pos_sales WHERE event_id = ? AND offline = 1', [$eventId])];
    }
}
