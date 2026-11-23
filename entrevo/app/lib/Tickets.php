<?php
declare(strict_types=1);

/** Logique métier : disponibilités, commandes, billets, contrôle d'accès. */
final class Tickets
{
    // ---------- Codes billets signés ----------
    public static function check(string $base): string
    {
        return strtoupper(substr(hash_hmac('sha256', 'tk:' . $base, (string)config('secret')), 0, 4));
    }

    public static function newCode(): string
    {
        do {
            $base = random_code(10);
            $code = $base . self::check($base);
        } while (val('SELECT id FROM tickets WHERE code = ?', [$code]));
        return $code;
    }

    public static function normalize(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('~[?&]c=([A-Za-z0-9]+)~', $raw, $m)) $raw = $m[1];
        return substr(preg_replace('~[^A-Z0-9]~', '', strtoupper($raw)), 0, 40);
    }

    public static function looksGenuine(string $code): bool
    {
        return strlen($code) === 14 && hash_equals(self::check(substr($code, 0, 10)), substr($code, 10));
    }

    // ---------- Disponibilités ----------
    public static function rateUsed(int $rateId): int
    {
        $sold = (int)val("SELECT COUNT(*) FROM tickets WHERE rate_id = ? AND status = 'valid'", [$rateId]);
        $held = (int)val("SELECT COALESCE(SUM(i.qty),0) FROM order_items i JOIN orders o ON o.id = i.order_id
                          WHERE i.rate_id = ? AND o.status = 'pending' AND o.expires_at > NOW()", [$rateId]);
        return $sold + $held;
    }

    public static function eventUsed(int $eventId): int
    {
        $sold = (int)val("SELECT COUNT(*) FROM tickets WHERE event_id = ? AND status = 'valid'", [$eventId]);
        $held = (int)val("SELECT COALESCE(SUM(i.qty),0) FROM order_items i JOIN orders o ON o.id = i.order_id
                          WHERE o.event_id = ? AND o.status = 'pending' AND o.expires_at > NOW()", [$eventId]);
        return $sold + $held;
    }

    public static function remaining(array $rate, array $event): int
    {
        $rem = PHP_INT_MAX;
        if ((int)$rate['quota'] > 0) $rem = (int)$rate['quota'] - self::rateUsed((int)$rate['id']);
        if ((int)$event['capacity'] > 0) $rem = min($rem, (int)$event['capacity'] - self::eventUsed((int)$event['id']));
        return max(0, $rem);
    }

    /** @return array{ok:bool, why?:string, remaining?:int} */
    public static function onSale(array $rate, array $event): array
    {
        if (!$rate['active']) return ['ok' => false, 'why' => 'Indisponible'];
        if ($event['status'] !== 'published') return ['ok' => false, 'why' => 'Vente fermée'];
        $now = time();
        if ($rate['sale_start'] && strtotime($rate['sale_start']) > $now) return ['ok' => false, 'why' => 'En vente le ' . fr_date($rate['sale_start'])];
        if ($rate['sale_end'] && strtotime($rate['sale_end']) < $now) return ['ok' => false, 'why' => 'Vente terminée'];
        if (strtotime($event['ends_at'] ?: $event['starts_at']) + 6 * 3600 < $now) return ['ok' => false, 'why' => 'Événement passé'];
        $rem = self::remaining($rate, $event);
        if ($rem <= 0) return ['ok' => false, 'why' => 'Complet'];
        return ['ok' => true, 'remaining' => $rem];
    }

    // ---------- Codes promo ----------
    public static function findPromo(int $eventId, string $code): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') return ['error' => null];
        $p = one('SELECT * FROM promos WHERE event_id = ? AND code = ? AND active = 1', [$eventId, $code]);
        if (!$p) return ['error' => 'Code inconnu.'];
        if ((int)$p['max_uses'] > 0 && (int)$p['used'] >= (int)$p['max_uses']) return ['error' => 'Ce code a atteint sa limite d\'utilisation.'];
        if ($p['valid_until'] && strtotime($p['valid_until']) < time()) return ['error' => 'Ce code a expiré.'];
        $p['rate_list'] = array_filter(array_map('intval', explode(',', (string)$p['rate_ids'])));
        return ['promo' => $p];
    }

    public static function discount(?array $promo, array $items): int
    {
        if (!$promo || $promo['kind'] === 'unlock') return 0;
        $eligible = array_filter($items, fn($i) => !$promo['rate_list'] || in_array((int)$i['rate_id'], $promo['rate_list'], true));
        $base = array_sum(array_map(fn($i) => $i['unit_price'] * $i['qty'], $eligible));
        if ($promo['kind'] === 'percent') return min($base, (int)round($base * (int)$promo['value'] / 100));
        $qty = array_sum(array_map(fn($i) => $i['qty'], $eligible));
        return min($base, (int)$promo['value'] * $qty);
    }

    // ---------- Commission de la plateforme ----------
    public static function feeRates(array $org): array
    {
        $pct = $org['fee_percent'] !== null && $org['fee_percent'] !== '' ? (float)$org['fee_percent'] : (float)setting('fee_percent');
        $fixed = $org['fee_fixed'] !== null && $org['fee_fixed'] !== '' ? (int)$org['fee_fixed'] : (int)setting('fee_fixed');
        return [$pct, $fixed];
    }

    /** Frais de service pour un montant net et un nombre de billets payants. */
    public static function computeFees(array $org, int $net, int $paidTickets): int
    {
        if ($net <= 0 || $paidTickets <= 0) return 0;
        [$pct, $fixed] = self::feeRates($org);
        return (int)round($net * $pct / 100) + $fixed * $paidTickets;
    }

    /** Frais affichés à l'acheteur pour un billet à ce prix (si frais à sa charge). */
    public static function unitFee(array $org, int $price): int
    {
        return $org['fee_mode'] === 'buyer' ? self::computeFees($org, $price, 1) : 0;
    }

    // ---------- Commandes ----------
    /**
     * @param array $p event, org, quantities [rateId=>qty], buyer [email, first_name, last_name, phone],
     *                 holders [rateId=>[i=>[first_name,last_name,answers]]], order_answers, promo, method, pay_method, note, user_id, unlocked (rate ids), force
     * @return array{order?:array, errors?:string[]}
     */
    public static function createOrder(array $p): array
    {
        $ev = $p['event'];
        $org = $p['org'];
        $method = $p['method'] ?? 'online';
        $staff = $method !== 'online';
        $errors = [];

        db()->beginTransaction();
        try {
            // verrou sur l'événement : évite la survente en cas d'achats simultanés
            q('SELECT id FROM events WHERE id = ? FOR UPDATE', [$ev['id']]);

            $promo = null;
            if (!empty($p['promo']) && !$staff) {
                $r = self::findPromo((int)$ev['id'], (string)$p['promo']);
                if (!empty($r['error'])) $errors[] = $r['error']; else $promo = $r['promo'] ?? null;
            }
            $unlocked = array_map('intval', $p['unlocked'] ?? []);
            if ($promo && $promo['kind'] === 'unlock') $unlocked = array_merge($unlocked, $promo['rate_list']);

            $items = [];
            $count = 0;
            foreach (($p['quantities'] ?? []) as $rateId => $qty) {
                $qty = (int)$qty;
                if ($qty <= 0) continue;
                $rate = one('SELECT * FROM rates WHERE id = ? AND event_id = ?', [(int)$rateId, $ev['id']]);
                if (!$rate) { $errors[] = 'Tarif inconnu.'; continue; }
                if (!$staff) {
                    if ($rate['hidden'] && !in_array((int)$rate['id'], $unlocked, true)) { $errors[] = 'Tarif non disponible.'; continue; }
                    $st = self::onSale($rate, $ev);
                    if (!$st['ok']) { $errors[] = $rate['name'] . ' : ' . $st['why']; continue; }
                    if ((int)$rate['max_per_order'] > 0 && $qty > (int)$rate['max_per_order']) { $errors[] = $rate['name'] . ' : maximum ' . $rate['max_per_order'] . ' par commande.'; continue; }
                }
                $rem = self::remaining($rate, $ev);
                if ($qty > $rem && empty($p['force'])) { $errors[] = $rate['name'] . ' : plus que ' . $rem . ' place(s) disponible(s).'; continue; }
                $items[] = ['rate_id' => (int)$rate['id'], 'name' => $rate['name'], 'qty' => $qty, 'unit_price' => $method === 'invitation' ? 0 : (int)$rate['price']];
                $count += $qty;
            }
            if (!$items && !$errors) $errors[] = 'Choisissez au moins un billet.';
            if (!$staff && (int)$ev['max_per_order'] > 0 && $count > (int)$ev['max_per_order']) $errors[] = 'Maximum ' . $ev['max_per_order'] . ' billets par commande.';
            if ($errors) { db()->rollBack(); return ['errors' => array_values(array_unique($errors))]; }

            $subtotal = array_sum(array_map(fn($i) => $i['unit_price'] * $i['qty'], $items));
            $discount = self::discount($promo, $items);
            $net = $subtotal - $discount;
            $paidTickets = array_sum(array_map(fn($i) => $i['unit_price'] > 0 ? $i['qty'] : 0, $items));
            $feeMode = $org['fee_mode'] === 'organizer' ? 'organizer' : 'buyer';
            $fees = $method === 'online' ? self::computeFees($org, $net, $paidTickets) : 0;
            if ($feeMode === 'buyer') { $total = $net + $fees; $commission = $fees; $orgAmount = $net; }
            else { $total = $net; $commission = min($fees, $net); $orgAmount = $net - $commission; }
            if ($method !== 'online') { $orgAmount = 0; $commission = 0; } // encaissé directement par l'organisateur

            $buyer = $p['buyer'];
            $order = [
                'ref' => self::newRef(),
                'token' => token(24),
                'event_id' => $ev['id'],
                'organizer_id' => $ev['organizer_id'],
                'email' => mb_substr((string)($buyer['email'] ?? ''), 0, 190),
                'first_name' => mb_substr((string)($buyer['first_name'] ?? ''), 0, 120),
                'last_name' => mb_substr((string)($buyer['last_name'] ?? ''), 0, 120),
                'phone' => mb_substr((string)($buyer['phone'] ?? ''), 0, 40),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'fees' => $method === 'online' ? $fees : 0,
                'total' => $total,
                'fee_mode' => $feeMode,
                'commission' => $commission,
                'organizer_amount' => $orgAmount,
                'promo_code' => $promo['code'] ?? null,
                'method' => $method,
                'pay_method' => $p['pay_method'] ?? null,
                'status' => 'pending',
                'answers' => json_encode(['order' => $p['order_answers'] ?? [], 'holders' => $p['holders'] ?? []], JSON_UNESCAPED_UNICODE),
                'note' => mb_substr((string)($p['note'] ?? ''), 0, 500),
                'created_by' => $p['user_id'] ?? null,
                'created_at' => now(),
                'expires_at' => date('Y-m-d H:i:s', time() + 60 * max(5, (int)setting('pending_minutes'))),
            ];
            $order['id'] = insert('orders', $order);
            foreach ($items as $it) insert('order_items', $it + ['order_id' => $order['id']]);
            db()->commit();
            return ['order' => $order];
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
    }

    private static function newRef(): string
    {
        do { $ref = 'E' . random_code(7); } while (val('SELECT id FROM orders WHERE ref = ?', [$ref]));
        return $ref;
    }

    /** Valide le paiement et émet les billets (idempotent). */
    public static function markPaid(int $orderId, array $extra = []): ?array
    {
        db()->beginTransaction();
        try {
            $o = one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if (!$o) { db()->rollBack(); return null; }
            if ($o['status'] === 'paid') { db()->commit(); return $o; }
            if (!in_array($o['status'], ['pending', 'expired'], true)) { db()->rollBack(); return $o; }
            update('orders', array_merge(['status' => 'paid', 'paid_at' => now()], $extra), 'id = ?', [$orderId]);
            if ($o['promo_code']) q('UPDATE promos SET used = used + 1 WHERE event_id = ? AND code = ?', [$o['event_id'], $o['promo_code']]);
            $answers = json_decode((string)$o['answers'], true) ?: [];
            $holders = $answers['holders'] ?? [];
            foreach (all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]) as $it) {
                for ($i = 0; $i < (int)$it['qty']; $i++) {
                    $h = $holders[$it['rate_id']][$i] ?? [];
                    insert('tickets', [
                        'order_id' => $orderId,
                        'event_id' => $o['event_id'],
                        'rate_id' => $it['rate_id'],
                        'rate_name' => $it['name'],
                        'code' => self::newCode(),
                        'first_name' => mb_substr(trim((string)($h['first_name'] ?? '')) ?: (string)$o['first_name'], 0, 120),
                        'last_name' => mb_substr(trim((string)($h['last_name'] ?? '')) ?: (string)$o['last_name'], 0, 120),
                        'price' => $it['unit_price'],
                        'answers' => !empty($h['answers']) ? json_encode($h['answers'], JSON_UNESCAPED_UNICODE) : null,
                        'status' => 'valid',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]);
                }
            }
            db()->commit();
            return one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
    }

    public static function cancelOrder(array $o, string $status = 'cancelled'): void
    {
        update('orders', ['status' => $status, 'cancelled_at' => now()], 'id = ?', [$o['id']]);
        q("UPDATE tickets SET status = 'cancelled', updated_at = NOW() WHERE order_id = ?", [$o['id']]);
        if ($o['promo_code'] && $o['status'] === 'paid') q('UPDATE promos SET used = GREATEST(used - 1, 0) WHERE event_id = ? AND code = ?', [$o['event_id'], $o['promo_code']]);
    }

    public static function expirePending(): void
    {
        q("UPDATE orders SET status = 'expired' WHERE status = 'pending' AND expires_at < NOW() - INTERVAL 2 HOUR");
    }

    // ---------- Contrôle d'accès ----------
    public const LABELS = [
        'ok' => 'Accès autorisé',
        'exit' => 'Sortie enregistrée',
        'already' => 'Déjà entré',
        'invalid' => 'Billet inconnu',
        'forged' => 'Code invalide',
        'cancelled' => 'Billet annulé',
        'wrong_event' => 'Autre événement',
        'not_allowed' => 'Accès non autorisé ici',
        'not_inside' => 'Pas entré',
        'closed' => 'Point d\'accès fermé',
    ];

    /**
     * Valide un passage. Utilisé en direct et pour les passages enregistrés hors ligne.
     * @param array $p event, code, access_point_id, direction, user, uuid, device, scanned_at, offline
     */
    public static function scan(array $p): array
    {
        $ev = $p['event'];
        $code = self::normalize((string)$p['code']);
        $uuid = (string)($p['uuid'] ?? '') ?: bin2hex(random_bytes(16));
        $direction = ($p['direction'] ?? 'in') === 'out' ? 'out' : 'in';
        $at = !empty($p['scanned_at']) ? date('Y-m-d H:i:s', min(time(), (int)strtotime((string)$p['scanned_at']))) : now();

        $existing = one('SELECT * FROM scans WHERE uuid = ?', [$uuid]);
        if ($existing) return self::scanResponse($existing, $existing['ticket_id'] ? one('SELECT * FROM tickets WHERE id = ?', [$existing['ticket_id']]) : null, []);

        db()->beginTransaction();
        try {
            $ap = !empty($p['access_point_id']) ? one('SELECT * FROM access_points WHERE id = ? AND event_id = ?', [(int)$p['access_point_id'], $ev['id']]) : null;
            $t = $code !== '' ? one('SELECT * FROM tickets WHERE code = ? FOR UPDATE', [$code]) : null;
            $info = [];
            if (!$t) {
                $result = self::looksGenuine($code) ? 'invalid' : 'forged';
            } elseif ((int)$t['event_id'] !== (int)$ev['id']) {
                $result = 'wrong_event';
                $info['other_event'] = (string)val('SELECT name FROM events WHERE id = ?', [$t['event_id']]);
            } elseif ($t['status'] !== 'valid') {
                $result = 'cancelled';
            } elseif ($ap && !$ap['active']) {
                $result = 'closed';
            } elseif ($ap && $ap['rate_ids'] && !in_array((int)$t['rate_id'], array_map('intval', explode(',', $ap['rate_ids'])), true)) {
                $result = 'not_allowed';
            } elseif ($direction === 'out') {
                if (!$t['inside']) $result = 'not_inside';
                else { $result = 'exit'; update('tickets', ['inside' => 0, 'last_scan_at' => $at, 'updated_at' => now()], 'id = ?', [$t['id']]); }
            } elseif ($t['inside'] || ((int)$t['entries'] > 0 && !$ev['allow_reentry'])) {
                $result = 'already';
            } else {
                $result = 'ok';
                update('tickets', [
                    'inside' => 1, 'entries' => (int)$t['entries'] + 1, 'checked_in_at' => $t['checked_in_at'] ?: $at,
                    'last_scan_at' => $at, 'updated_at' => now(),
                ], 'id = ?', [$t['id']]);
                if ((int)$t['entries'] > 0) $info['reentry'] = true;
            }
            if ($result === 'already') {
                $last = one("SELECT s.scanned_at, a.name AS ap FROM scans s LEFT JOIN access_points a ON a.id = s.access_point_id
                             WHERE s.ticket_id = ? AND s.result = 'ok' AND s.undone = 0 ORDER BY s.scanned_at DESC LIMIT 1", [$t['id']]);
                $info['last_at'] = $last['scanned_at'] ?? $t['checked_in_at'];
                $info['last_ap'] = $last['ap'] ?? '';
            }
            $scan = [
                'uuid' => $uuid, 'event_id' => $ev['id'], 'ticket_id' => $t['id'] ?? null, 'code' => $code ?: '-',
                'access_point_id' => $ap['id'] ?? null, 'direction' => $direction, 'result' => $result,
                'user_id' => $p['user']['id'] ?? null, 'device' => mb_substr((string)($p['device'] ?? ''), 0, 64),
                'offline' => !empty($p['offline']) ? 1 : 0, 'scanned_at' => $at, 'received_at' => now(),
            ];
            $scan['id'] = insert('scans', $scan);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        $t = $t ? one('SELECT * FROM tickets WHERE id = ?', [$t['id']]) : null;
        return self::scanResponse($scan, $t, $info);
    }

    private static function scanResponse(array $scan, ?array $t, array $info): array
    {
        $r = $scan['result'];
        return [
            'id' => (int)$scan['id'],
            'uuid' => $scan['uuid'],
            'result' => $r,
            'level' => in_array($r, ['ok', 'exit'], true) ? 'ok' : (in_array($r, ['already', 'not_inside'], true) ? 'warn' : 'error'),
            'label' => self::LABELS[$r] ?? $r,
            'code' => $scan['code'],
            'info' => $info,
            'ticket' => $t ? [
                'code' => $t['code'], 'name' => trim($t['first_name'] . ' ' . $t['last_name']), 'rate' => $t['rate_name'],
                'rate_id' => (int)$t['rate_id'], 'status' => $t['status'], 'inside' => (int)$t['inside'], 'entries' => (int)$t['entries'],
            ] : null,
            'at' => $scan['scanned_at'],
        ];
    }

    public static function undoScan(int $scanId, int $eventId): bool
    {
        $s = one('SELECT * FROM scans WHERE id = ? AND event_id = ? AND undone = 0', [$scanId, $eventId]);
        if (!$s || !$s['ticket_id']) return false;
        $t = one('SELECT * FROM tickets WHERE id = ?', [$s['ticket_id']]);
        if ($s['result'] === 'ok') {
            $entries = max(0, (int)$t['entries'] - 1);
            update('tickets', ['entries' => $entries, 'inside' => 0, 'checked_in_at' => $entries ? $t['checked_in_at'] : null, 'updated_at' => now()], 'id = ?', [$t['id']]);
        } elseif ($s['result'] === 'exit') {
            update('tickets', ['inside' => 1, 'updated_at' => now()], 'id = ?', [$t['id']]);
        } else {
            return false;
        }
        update('scans', ['undone' => 1], 'id = ?', [$scanId]);
        return true;
    }

    public static function stats(int $eventId): array
    {
        $byRate = all("SELECT r.id, r.name, r.quota, r.price,
                SUM(t.status = 'valid') AS sold,
                COALESCE(SUM(CASE WHEN t.status = 'valid' THEN t.price END),0) AS revenue,
                SUM(t.status = 'valid' AND t.entries > 0) AS checked,
                SUM(t.status = 'valid' AND t.inside = 1) AS inside
            FROM rates r LEFT JOIN tickets t ON t.rate_id = r.id
            WHERE r.event_id = ? GROUP BY r.id ORDER BY r.sort, r.id", [$eventId]);
        $o = one("SELECT COUNT(*) n, COALESCE(SUM(total),0) total, COALESCE(SUM(organizer_amount),0) org, COALESCE(SUM(commission),0) com
                  FROM orders WHERE event_id = ? AND status = 'paid'", [$eventId]);
        $sold = array_sum(array_map(fn($r) => (int)$r['sold'], $byRate));
        return [
            'by_rate' => $byRate,
            'sold' => $sold,
            'orders' => (int)$o['n'],
            'revenue' => (int)$o['total'],
            'org_amount' => (int)$o['org'],
            'commission' => (int)$o['com'],
            'checked' => array_sum(array_map(fn($r) => (int)$r['checked'], $byRate)),
            'inside' => array_sum(array_map(fn($r) => (int)$r['inside'], $byRate)),
        ];
    }

    /** Solde d'un organisateur : ventes en ligne encaissées par la plateforme - reversements. */
    public static function balance(int $orgId): array
    {
        $r = one("SELECT COALESCE(SUM(total),0) collected, COALESCE(SUM(commission),0) commission, COALESCE(SUM(organizer_amount),0) due, COUNT(*) n
                  FROM orders WHERE organizer_id = ? AND status = 'paid' AND method = 'online' AND provider IN ('stripe','paypal','sumup','mollie')", [$orgId]);
        $paid = (int)val('SELECT COALESCE(SUM(amount),0) FROM payouts WHERE organizer_id = ?', [$orgId]);
        return [
            'collected' => (int)$r['collected'], 'commission' => (int)$r['commission'], 'due' => (int)$r['due'],
            'paid_out' => $paid, 'balance' => (int)$r['due'] - $paid, 'orders' => (int)$r['n'],
        ];
    }
}
