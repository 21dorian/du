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

    // ---------- Séances ----------
    public static function sessions(int $eventId, bool $onlyActive = true): array
    {
        return all('SELECT * FROM sessions WHERE event_id = ?' . ($onlyActive ? ' AND active = 1' : '') . ' ORDER BY starts_at, id', [$eventId]);
    }

    public static function hasSessions(int $eventId): bool
    {
        return (bool)val('SELECT id FROM sessions WHERE event_id = ? LIMIT 1', [$eventId]);
    }

    /** Met à jour les dates de l'événement à partir de ses séances (première et dernière). */
    public static function syncEventDates(int $eventId): void
    {
        $r = one('SELECT MIN(starts_at) a, MAX(COALESCE(ends_at, starts_at)) b FROM sessions WHERE event_id = ? AND active = 1', [$eventId]);
        if ($r && $r['a']) update('events', ['starts_at' => $r['a'], 'ends_at' => $r['b']], 'id = ?', [$eventId]);
    }

    // ---------- Disponibilités ----------
    // On compte en « places » (un pack de 4 = 4 places). Les accréditations et billets importés ne comptent pas.
    private const COUNTED = "t.status = 'valid' AND t.type IN ('ticket','invitation')";

    public static function rateUsed(int $rateId, ?int $sessionId = null): int
    {
        $ss = $sessionId ? ' AND t.session_id = ' . (int)$sessionId : '';
        $sold = (int)val('SELECT COUNT(*) FROM tickets t WHERE t.rate_id = ? AND ' . self::COUNTED . $ss, [$rateId]);
        $os = $sessionId ? ' AND o.session_id = ' . (int)$sessionId : '';
        $held = (int)val("SELECT COALESCE(SUM(i.qty * r.pack_size),0) FROM order_items i JOIN orders o ON o.id = i.order_id JOIN rates r ON r.id = i.rate_id
                          WHERE i.kind = 'rate' AND i.rate_id = ? AND o.status = 'pending' AND o.resale_id IS NULL AND o.expires_at > NOW()" . $os, [$rateId]);
        return $sold + $held;
    }

    public static function eventUsed(int $eventId, ?int $sessionId = null, int $exceptWaitlist = 0): int
    {
        $ss = $sessionId ? ' AND t.session_id = ' . (int)$sessionId : '';
        $sold = (int)val('SELECT COUNT(*) FROM tickets t WHERE t.event_id = ? AND ' . self::COUNTED . $ss, [$eventId]);
        $os = $sessionId ? ' AND o.session_id = ' . (int)$sessionId : '';
        $held = (int)val("SELECT COALESCE(SUM(i.qty * r.pack_size),0) FROM order_items i JOIN orders o ON o.id = i.order_id JOIN rates r ON r.id = i.rate_id
                          WHERE i.kind = 'rate' AND o.event_id = ? AND o.status = 'pending' AND o.resale_id IS NULL AND o.expires_at > NOW()" . $os, [$eventId]);
        // places proposées à la liste d'attente (réservées 24 h pour eux)
        $ws = $sessionId ? ' AND session_id = ' . (int)$sessionId : '';
        $offered = (int)val("SELECT COALESCE(SUM(qty),0) FROM waitlist WHERE event_id = ? AND status = 'offered' AND expires_at > NOW() AND id <> ?" . $ws, [$eventId, $exceptWaitlist]);
        return $sold + $held + $offered;
    }

    public static function capacity(array $event, ?array $session): int
    {
        if ($session && (int)$session['capacity'] > 0) return (int)$session['capacity'];
        return (int)$event['capacity'];
    }

    /** Places encore disponibles pour un tarif (et une séance). */
    public static function remaining(array $rate, array $event, ?array $session = null, int $exceptWaitlist = 0): int
    {
        $sid = $session ? (int)$session['id'] : null;
        $rem = PHP_INT_MAX;
        if ((int)$rate['quota'] > 0) $rem = (int)$rate['quota'] - self::rateUsed((int)$rate['id'], $sid);
        $cap = self::capacity($event, $session);
        if ($cap > 0) $rem = min($rem, $cap - self::eventUsed((int)$event['id'], $sid, $exceptWaitlist));
        return max(0, $rem);
    }

    /** Nombre d'achats possibles (un pack consomme plusieurs places). */
    public static function remainingUnits(array $rate, array $event, ?array $session = null, int $exceptWaitlist = 0): int
    {
        $rem = self::remaining($rate, $event, $session, $exceptWaitlist);
        return $rem === PHP_INT_MAX ? PHP_INT_MAX : intdiv($rem, max(1, (int)$rate['pack_size']));
    }

    public static function rateSessions(array $rate): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string)($rate['session_ids'] ?? '')))));
    }

    /** @return array{ok:bool, why?:string, remaining?:int} */
    public static function onSale(array $rate, array $event, ?array $session = null, int $exceptWaitlist = 0): array
    {
        if (!$rate['active']) return ['ok' => false, 'why' => 'Indisponible'];
        if ($event['status'] !== 'published') return ['ok' => false, 'why' => 'Vente fermée'];
        if (!empty($event['_presale_locked'])) return ['ok' => false, 'presale' => true, 'why' => 'Prévente privée'];
        $now = time();
        if ($rate['sale_start'] && strtotime($rate['sale_start']) > $now) return ['ok' => false, 'why' => 'En vente le ' . fr_date($rate['sale_start'])];
        if ($rate['sale_end'] && strtotime($rate['sale_end']) < $now) return ['ok' => false, 'why' => 'Vente terminée'];
        if ($session) {
            if (!$session['active']) return ['ok' => false, 'why' => 'Séance indisponible'];
            if (strtotime((string)($session['ends_at'] ?: $session['starts_at'])) < $now) return ['ok' => false, 'why' => 'Séance passée'];
            $only = self::rateSessions($rate);
            if ($only && !in_array((int)$session['id'], $only, true)) return ['ok' => false, 'why' => 'Pas pour cette date'];
        } elseif (strtotime($event['ends_at'] ?: $event['starts_at']) + 6 * 3600 < $now) {
            return ['ok' => false, 'why' => 'Événement passé'];
        }
        // early bird : ce palier ouvre quand le précédent est épuisé ou terminé
        if (!empty($rate['after_rate_id'])) {
            static $depth = 0;
            $prev = one('SELECT * FROM rates WHERE id = ? AND event_id = ?', [$rate['after_rate_id'], $event['id']]);
            if ($prev && $prev['active'] && $depth < 5) {
                $depth++; $p = self::onSale($prev, $event, $session, $exceptWaitlist); $depth--;
                $notYet = $p['ok'] || (!empty($p['next']) || ($prev['sale_start'] && strtotime($prev['sale_start']) > $now));
                if ($notYet) return ['ok' => false, 'next' => true, 'why' => 'Prochain palier : dès que « ' . $prev['name'] . ' » est épuisé'];
            }
        }
        $rem = self::remainingUnits($rate, $event, $session, $exceptWaitlist);
        if ($rem <= 0) return ['ok' => false, 'why' => 'Complet'];
        return ['ok' => true, 'remaining' => $rem];
    }

    public static function productRemaining(array $p): int
    {
        if ((int)$p['stock'] <= 0) return PHP_INT_MAX;
        $sold = (int)val("SELECT COALESCE(SUM(i.qty),0) FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.kind = 'product' AND i.product_id = ?
                          AND (o.status = 'paid' OR (o.status = 'pending' AND o.expires_at > NOW()))", [$p['id']]);
        return max(0, (int)$p['stock'] - $sold);
    }

    // ---------- Codes promo ----------
    public static function findPromo(int $eventId, string $code): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') return ['error' => null];
        $p = one('SELECT * FROM promos WHERE event_id = ? AND code = ? AND active = 1', [$eventId, $code]);
        if (!$p) return ['error' => 'Code inconnu.'];
        if ((int)$p['max_uses'] > 0 && (int)$p['used'] >= (int)$p['max_uses']) return ['error' => 'Ce code a déjà été utilisé à fond.'];
        if ($p['valid_until'] && strtotime($p['valid_until']) < time()) return ['error' => 'Ce code a expiré. Trop tard, désolé.'];
        if (!empty($p['valid_from']) && strtotime($p['valid_from']) > time()) return ['error' => 'Ce code sera valable à partir du ' . fr_date($p['valid_from']) . '. Patience'];
        $p['rate_list'] = array_filter(array_map('intval', explode(',', (string)$p['rate_ids'])));
        return ['promo' => $p];
    }

    /** Paliers du tarif dégressif : [['min' => 5, 'pct' => 10], …] triés par quantité. */
    public static function tiers(array $ev): array
    {
        $t = json_decode((string)($ev['group_tiers'] ?? ''), true);
        if (!is_array($t)) return [];
        $t = array_values(array_filter(array_map(fn($x) => ['min' => (int)($x['min'] ?? 0), 'pct' => (int)($x['pct'] ?? 0)], $t), fn($x) => $x['min'] >= 2 && $x['pct'] > 0 && $x['pct'] <= 90));
        usort($t, fn($a, $b) => $a['min'] <=> $b['min']);
        return $t;
    }

    /** Remise de groupe : pourcentage du montant des billets payants selon le nombre de billets. */
    public static function groupDiscount(array $ev, array $items): array
    {
        $tiers = self::tiers($ev);
        $paid = array_filter($items, fn($i) => ($i['kind'] ?? 'rate') === 'rate' && $i['unit_price'] > 0);
        $count = array_sum(array_map(fn($i) => $i['qty'] * max(1, (int)($i['_pack'] ?? $i['pack'] ?? 1)), $paid));
        $base = array_sum(array_map(fn($i) => $i['unit_price'] * $i['qty'], $paid));
        $cur = null; $next = null;
        foreach ($tiers as $t) { if ($count >= $t['min']) $cur = $t; elseif (!$next) $next = $t; }
        return ['amount' => $cur ? (int)round($base * $cur['pct'] / 100) : 0, 'pct' => $cur['pct'] ?? 0, 'count' => $count, 'next' => $next];
    }

    public static function discount(?array $promo, array $items): int
    {
        if (!$promo || $promo['kind'] === 'unlock') return 0;
        if (!empty($promo['min_qty'])) {
            $n = array_sum(array_map(fn($i) => ($i['kind'] ?? 'rate') === 'rate' ? $i['qty'] * max(1, (int)($i['_pack'] ?? $i['pack'] ?? 1)) : 0, $items));
            if ($n < (int)$promo['min_qty']) return 0;
        }
        $eligible = array_filter($items, fn($i) => ($i['kind'] ?? 'rate') === 'rate' && (!$promo['rate_list'] || in_array((int)$i['rate_id'], $promo['rate_list'], true)));
        $base = array_sum(array_map(fn($i) => $i['unit_price'] * $i['qty'], $eligible));
        if ($promo['kind'] === 'percent') return min($base, (int)round($base * (int)$promo['value'] / 100));
        $qty = array_sum(array_map(fn($i) => $i['qty'], $eligible));
        return min($base, (int)$promo['value'] * $qty);
    }

    // ---------- Commission de la plateforme ----------
    public static function feeRates(array $org): array
    {
        $t = Billing::terms($org);
        return [$t['pct'], $t['fixed']];
    }

    /** Frais de service pour un montant net et un nombre de billets payants (plafond par billet éventuel). */
    public static function computeFees(array $org, int $net, int $paidTickets): int
    {
        if ($net <= 0 || $paidTickets <= 0) return 0;
        $t = Billing::terms($org);
        $fee = (int)round($net * $t['pct'] / 100) + $t['fixed'] * $paidTickets;
        if ($t['cap'] !== null && $t['cap'] >= 0) $fee = min($fee, $t['cap'] * $paidTickets);
        return $fee;
    }

    /** Frais affichés à l'acheteur pour un billet à ce prix (si frais à sa charge). */
    public static function unitFee(array $org, int $price): int
    {
        return $org['fee_mode'] === 'buyer' ? self::computeFees($org, $price, 1) : 0;
    }

    // ---------- Commandes ----------
    /**
     * Crée une commande « en attente de paiement » (ou payée ensuite par confirm()).
     * @param array $p event, org, session (ligne ou null), quantities [rateId=>qty], prices [rateId=>centimes, prix libre],
     *                 products [productId=>qty], donation (centimes), seats [rateId=>[sièges]], buyer [email, first_name, last_name, phone],
     *                 holders [rateId=>[i=>[first_name,last_name,answers]]], order_answers, promo, unlocked (rate ids),
     *                 method (online|guichet|invitation|partner|import), channel, utm [source,medium,campaign], partner (ligne), waitlist (ligne),
     *                 resale (ligne de resales + ticket), pay_method, note, user_id, force
     * @return array{order?:array, errors?:string[]}
     */
    public static function createOrder(array $p): array
    {
        $ev = $p['event'];
        $org = $p['org'];
        $session = $p['session'] ?? null;
        $method = $p['method'] ?? 'online';
        $staff = in_array($method, ['guichet', 'invitation', 'import', 'pass'], true);
        $partner = $p['partner'] ?? null;
        $waitlist = $p['waitlist'] ?? null;
        $resale = $p['resale'] ?? null;
        $errors = [];
        if (self::hasSessions((int)$ev['id']) && !$session) return ['errors' => ['Choisis d\'abord une date']];
        if ($session && (int)$session['event_id'] !== (int)$ev['id']) return ['errors' => ['Séance inconnue.']];

        db()->beginTransaction();
        try {
            // verrou sur l'événement : évite la survente en cas d'achats simultanés
            q('SELECT id FROM events WHERE id = ? FOR UPDATE', [$ev['id']]);

            $promo = null;
            if (!empty($p['promo']) && !$staff && !$resale) {
                $r = self::findPromo((int)$ev['id'], (string)$p['promo']);
                if (!empty($r['error'])) $errors[] = $r['error']; else $promo = $r['promo'] ?? null;
            }
            $unlocked = array_map('intval', $p['unlocked'] ?? []);
            if ($promo && $promo['kind'] === 'unlock') $unlocked = array_merge($unlocked, $promo['rate_list']);
            $seatmap = Seating::map($ev);
            $seatsWanted = $p['seats'] ?? [];
            $allSeats = [];

            $items = [];
            $places = 0;
            $units = 0;
            foreach (($p['quantities'] ?? []) as $rateId => $qty) {
                $qty = (int)$qty;
                if ($qty <= 0) continue;
                $rate = one('SELECT * FROM rates WHERE id = ? AND event_id = ?', [(int)$rateId, $ev['id']]);
                if (!$rate) { $errors[] = 'Tarif inconnu.'; continue; }
                $pack = max(1, (int)$rate['pack_size']);
                if ($resale) {
                    // revente : un billet précis, au prix fixé (plafonné au prix d'origine)
                    $price = (int)$resale['price'];
                } else {
                    if ($partner) {
                        if ((int)$partner['rate_id'] !== (int)$rate['id']) { $errors[] = 'Tarif non disponible.'; continue; }
                        $usedByPartner = (int)val("SELECT COUNT(*) FROM tickets t JOIN orders o ON o.id = t.order_id WHERE o.partner_id = ? AND t.status = 'valid'", [$partner['id']]);
                        if ((int)$partner['quota'] > 0 && $usedByPartner + $qty * $pack > (int)$partner['quota']) { $errors[] = 'Plus que ' . max(0, (int)$partner['quota'] - $usedByPartner) . ' place(s) sur ton lot partenaire.'; continue; }
                    } elseif (!$staff) {
                        if ($rate['hidden'] && !in_array((int)$rate['id'], $unlocked, true)) { $errors[] = 'Tarif non disponible.'; continue; }
                        $st = self::onSale($rate, $ev, $session, (int)($waitlist['id'] ?? 0));
                        if (!$st['ok']) { $errors[] = $rate['name'] . ' : ' . $st['why']; continue; }
                        if (empty($p['group']) && (int)$rate['max_per_order'] > 0 && $qty > (int)$rate['max_per_order']) { $errors[] = $rate['name'] . ' : maximum ' . $rate['max_per_order'] . ' par commande.'; continue; }
                    }
                    $rem = self::remainingUnits($rate, $ev, $session, (int)($waitlist['id'] ?? 0));
                    if ($qty > $rem && empty($p['force'])) { $errors[] = $rate['name'] . ' : plus que ' . $rem . ' disponible(s).'; continue; }
                    $price = (int)$rate['price'];
                    if ($rate['price_mode'] === 'free' && !$staff) {
                        $wanted = (int)($p['prices'][$rate['id']] ?? $price);
                        if ($wanted < (int)$rate['price']) { $errors[] = $rate['name'] . ' : minimum ' . money((int)$rate['price']) . '.'; continue; }
                        $price = min($wanted, 1000000);
                    }
                    if (in_array($method, ['invitation', 'import', 'pass'], true) || ($partner && $partner['free'])) $price = 0;
                }
                // placement numéroté
                $seats = [];
                if ($seatmap && !$resale && $method !== 'import') {
                    $seats = array_values(array_unique(array_map('strval', (array)($seatsWanted[$rate['id']] ?? []))));
                    if (count($seats) !== $qty * $pack) { $errors[] = $rate['name'] . ' : choisis ' . ($qty * $pack) . ' place(s) sur le plan'; continue; }
                    foreach ($seats as $seat) {
                        $chk = Seating::check($ev, $session, $seat, $rate);
                        if ($chk !== true) { $errors[] = $chk; continue 2; }
                    }
                    $allSeats = array_merge($allSeats, $seats);
                } elseif ($resale && !empty($resale['seat'])) {
                    $seats = [$resale['seat']];
                }
                $items[] = ['kind' => 'rate', 'rate_id' => (int)$rate['id'], 'name' => $rate['name'] . ($pack > 1 ? ' (pack ' . $pack . ')' : ''),
                    'qty' => $qty, 'unit_price' => $price, 'seats' => $seats ? implode(',', $seats) : null, '_pack' => $pack];
                $places += $qty * $pack;
                $units += $qty;
            }
            foreach (($p['products'] ?? []) as $pid => $qty) {
                $qty = (int)$qty;
                if ($qty <= 0 || $resale) continue;
                $prod = one('SELECT * FROM products WHERE id = ? AND event_id = ? AND active = 1', [(int)$pid, $ev['id']]);
                if (!$prod) { $errors[] = 'Option inconnue.'; continue; }
                if ((int)$prod['max_per_order'] > 0 && $qty > (int)$prod['max_per_order']) { $errors[] = $prod['name'] . ' : maximum ' . $prod['max_per_order'] . '.'; continue; }
                $rem = self::productRemaining($prod);
                if ($qty > $rem) { $errors[] = $prod['name'] . ' : plus que ' . $rem . ' en stock.'; continue; }
                $vars = array_filter(array_map('trim', explode(',', (string)($prod['variants'] ?? ''))));
                $variant = $vars ? (string)($p['variants'][$prod['id']] ?? '') : '';
                if ($vars && !in_array($variant, $vars, true)) $variant = reset($vars);
                $items[] = ['kind' => 'product', 'rate_id' => 0, 'product_id' => (int)$prod['id'], 'name' => $prod['name'] . ($variant !== '' ? ' (' . $variant . ')' : ''), 'variant' => $variant !== '' ? $variant : null, 'qty' => $qty,
                    'unit_price' => $method === 'invitation' ? 0 : (int)$prod['price'], 'seats' => null, '_pack' => 0];
            }
            if (!$units && !$errors) $errors[] = 'Prends au moins un billet';
            if (!$staff && !$partner && !$resale && empty($p['group']) && (int)$ev['max_per_order'] > 0 && $units > (int)$ev['max_per_order']) $errors[] = 'Maximum ' . $ev['max_per_order'] . ' billets par commande.';
            if ($waitlist && $places > (int)$waitlist['qty']) $errors[] = 'Ta place réservée couvre ' . (int)$waitlist['qty'] . ' billet(s).';
            if ($errors) { db()->rollBack(); return ['errors' => array_values(array_unique($errors))]; }

            if ($promo && $promo['kind'] !== 'unlock') {
                $nt = array_sum(array_map(fn($i) => $i['kind'] === 'rate' ? $i['qty'] * $i['_pack'] : 0, $items));
                if ((int)$promo['min_qty'] > $nt) $errors[] = 'Le code ' . $promo['code'] . ' demande au moins ' . (int)$promo['min_qty'] . ' billets.';
                if ($promo['once_per_email'] && val("SELECT id FROM orders WHERE event_id = ? AND promo_code = ? AND LOWER(email) = ? AND status = 'paid' LIMIT 1", [$ev['id'], $promo['code'], strtolower((string)($p['buyer']['email'] ?? ''))]))
                    $errors[] = 'Tu as déjà utilisé le code ' . $promo['code'] . ' : il ne marche qu\'une fois par personne.';
                if ($errors) { db()->rollBack(); return ['errors' => $errors]; }
            }
            $subtotal = array_sum(array_map(fn($i) => $i['unit_price'] * $i['qty'], $items));
            $discount = self::discount($promo, $items);
            $group = ($method === 'online' && !$partner && !$resale) ? self::groupDiscount($ev, $items)['amount'] : 0;
            $discount = min($subtotal, $discount + $group);
            $net = $subtotal - $discount;
            $paidUnits = array_sum(array_map(fn($i) => $i['kind'] === 'rate' && $i['unit_price'] > 0 ? $i['qty'] : 0, $items));
            $donation = $method === 'online' && !$resale && !empty($ev['donation_enabled']) ? max(0, min(1000000, (int)($p['donation'] ?? 0))) : 0;
            $feeMode = $org['fee_mode'] === 'organizer' ? 'organizer' : 'buyer';
            $online = in_array($method, ['online', 'partner'], true);
            $fees = $online ? self::computeFees($org, $net, $paidUnits) : 0;
            if ($feeMode === 'buyer') { $total = $net + $fees; $commission = $fees; $orgAmount = $net; }
            else { $total = $net; $commission = min($fees, $net); $orgAmount = $net - $commission; }
            $total += $donation;
            $orgAmount += $donation;
            // carte cadeau : réduit ce qui reste à payer ; l'organisateur a déjà touché ce montant à la vente de la carte
            $giftCode = null; $giftAmount = 0;
            if (!empty($p['gift']) && $method === 'online' && !$resale && $total > 0) {
                [$card, $gErr] = Store::giftFind((int)$ev['organizer_id'], (string)$p['gift']);
                if ($card) { $giftCode = $card['code']; $giftAmount = min((int)$card['balance'], $total); $total -= $giftAmount; $orgAmount = max(0, $orgAmount - $giftAmount); }
            }
            if (!$online) { $orgAmount = 0; $commission = 0; } // encaissé directement par l'organisateur

            $buyer = $p['buyer'];
            $utm = $p['utm'] ?? [];
            $order = [
                'ref' => self::newRef(),
                'token' => token(24),
                'event_id' => $ev['id'],
                'organizer_id' => $ev['organizer_id'],
                'session_id' => $session['id'] ?? null,
                'email' => mb_substr((string)($buyer['email'] ?? ''), 0, 190),
                'first_name' => mb_substr((string)($buyer['first_name'] ?? ''), 0, 120),
                'last_name' => mb_substr((string)($buyer['last_name'] ?? ''), 0, 120),
                'phone' => mb_substr((string)($buyer['phone'] ?? ''), 0, 40),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'group_discount' => $group,
                'gift_code' => $giftCode,
                'gift_amount' => $giftAmount,
                'fees' => $online ? $fees : 0,
                'donation' => $donation,
                'total' => $total,
                'fee_mode' => $feeMode,
                'commission' => $commission,
                'organizer_amount' => $orgAmount,
                'promo_code' => $promo['code'] ?? null,
                'method' => $method,
                'channel' => mb_substr((string)($p['channel'] ?? ($resale ? 'revente' : ($partner ? 'partenaire' : ($method === 'online' ? 'web' : $method)))), 0, 30),
                'utm_source' => isset($utm['source']) ? mb_substr((string)$utm['source'], 0, 120) : null,
                'utm_medium' => isset($utm['medium']) ? mb_substr((string)$utm['medium'], 0, 120) : null,
                'utm_campaign' => isset($utm['campaign']) ? mb_substr((string)$utm['campaign'], 0, 120) : null,
                'partner_id' => $partner['id'] ?? null,
                'resale_id' => $resale['id'] ?? null,
                'waitlist_id' => $waitlist['id'] ?? null,
                'pay_method' => $p['pay_method'] ?? null,
                'status' => 'pending',
                'answers' => json_encode(['order' => $p['order_answers'] ?? [], 'holders' => $p['holders'] ?? []], JSON_UNESCAPED_UNICODE),
                'note' => mb_substr((string)($p['note'] ?? ''), 0, 500),
                'created_by' => $p['user_id'] ?? null,
                'created_at' => now(),
                'expires_at' => date('Y-m-d H:i:s', time() + 60 * max(5, (int)($ev['hold_minutes'] ?? 0) ?: (int)setting('pending_minutes'))),
            ];
            $order['id'] = insert('orders', $order);
            foreach ($items as $it) { unset($it['_pack']); insert('order_items', $it + ['order_id' => $order['id']]); }
            foreach ($allSeats as $seat) {
                insert('seat_holds', ['event_id' => $ev['id'], 'session_id' => (int)($session['id'] ?? 0), 'seat' => $seat, 'order_id' => $order['id'], 'expires_at' => $order['expires_at']]);
            }
            db()->commit();
            return ['order' => $order];
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) return ['errors' => ['Une des places choisies vient d\'être prise par quelqu\'un d\'autre Choisis-en une autre.']];
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
            if (!empty($o['gift_code'])) Store::giftDebit($o);
            if ($o['promo_code']) q('UPDATE promos SET used = used + 1 WHERE event_id = ? AND code = ?', [$o['event_id'], $o['promo_code']]);
            $answers = json_decode((string)$o['answers'], true) ?: [];
            $holders = $answers['holders'] ?? [];
            $type = in_array($o['method'], ['invitation', 'partner'], true) && (int)$o['total'] === 0 ? 'invitation' : ($o['method'] === 'import' ? 'external' : 'ticket');
            foreach (all("SELECT i.*, r.pack_size, r.max_entries, r.per_day, r.companion FROM order_items i LEFT JOIN rates r ON r.id = i.rate_id
                          WHERE i.order_id = ? AND i.kind = 'rate' ORDER BY i.id", [$orderId]) as $it) {
                $pack = max(1, (int)$it['pack_size']);
                $seats = $it['seats'] ? explode(',', $it['seats']) : [];
                $n = (int)$it['qty'] * $pack;
                for ($i = 0; $i < $n; $i++) {
                    $h = $holders[$it['rate_id']][$i] ?? [];
                    insert('tickets', [
                        'order_id' => $orderId,
                        'event_id' => $o['event_id'],
                        'session_id' => $o['session_id'],
                        'rate_id' => $it['rate_id'],
                        'rate_name' => preg_replace('~ \(pack \d+\)$~', '', $it['name']),
                        'code' => self::newCode(),
                        'seat' => $seats[$i] ?? null,
                        'type' => $type,
                        'max_entries' => max(1, (int)($it['max_entries'] ?? 1)),
                        'per_day' => (int)($it['per_day'] ?? 0),
                        'first_name' => mb_substr(trim((string)($h['first_name'] ?? '')) ?: (string)$o['first_name'], 0, 120),
                        'last_name' => mb_substr(trim((string)($h['last_name'] ?? '')) ?: (string)$o['last_name'], 0, 120),
                        'price' => $pack > 1 ? intdiv((int)$it['unit_price'], $pack) : (int)$it['unit_price'],
                        'answers' => !empty($h['answers']) ? json_encode($h['answers'], JSON_UNESCAPED_UNICODE) : null,
                        'status' => 'valid',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]);
                    // 4.0 : tarif PMR : un billet accompagnateur gratuit par billet
                    if (!empty($it['companion'])) insert('tickets', ['order_id' => $orderId, 'event_id' => $o['event_id'], 'session_id' => $o['session_id'], 'rate_id' => $it['rate_id'], 'rate_name' => 'Accompagnateur · ' . preg_replace('~ \(pack \d+\)$~', '', $it['name']),
                        'code' => self::newCode(), 'type' => $type, 'max_entries' => max(1, (int)($it['max_entries'] ?? 1)), 'per_day' => (int)($it['per_day'] ?? 0), 'first_name' => 'Accompagnateur', 'last_name' => mb_substr(trim((string)($h['last_name'] ?? '')) ?: (string)$o['last_name'], 0, 120),
                        'price' => 0, 'status' => 'valid', 'updated_at' => now(), 'created_at' => now()]);
                }
            }
            db()->commit();
            return one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
    }

    /**
     * Valide une commande payée et déclenche tout ce qui suit : billets, e-mail, revente, liste d'attente, webhooks.
     * À utiliser partout à la place de markPaid().
     */
    public static function confirm(int $orderId, array $extra = [], bool $sendEmail = true): ?array
    {
        $before = one('SELECT status FROM orders WHERE id = ?', [$orderId]);
        $o = self::markPaid($orderId, $extra);
        if (!$o || $o['status'] !== 'paid' || ($before['status'] ?? '') === 'paid') return $o;
        try { if (class_exists('Resale') && $o['resale_id']) Resale::settle($o); } catch (Throwable $e) { log_line('revente', $o['ref'] . ' : ' . $e->getMessage()); }
        if ($o['waitlist_id']) q("UPDATE waitlist SET status = 'converted', order_id = ? WHERE id = ?", [$o['id'], $o['waitlist_id']]);
        if (!empty($o['quote_id'])) q("UPDATE quotes SET status = 'paid', order_id = ? WHERE id = ?", [$o['id'], $o['quote_id']]);
        q('DELETE FROM seat_holds WHERE order_id = ?', [$o['id']]);
        if ((int)$o['event_id'] === 0) { try { Store::fulfil($o); } catch (Throwable $e) { log_line('boutique', $o['ref'] . ' : ' . $e->getMessage()); } }
        elseif ($sendEmail) Mailer::sendOrderSafe((int)$o['id']);
        try { if (class_exists('Hooks')) Hooks::fire((int)$o['organizer_id'], 'order.paid', Hooks::orderPayload($o)); } catch (Throwable $e) { log_line('webhooks', $e->getMessage()); }
        return $o;
    }

    public static function cancelOrder(array $o, string $status = 'cancelled'): void
    {
        update('orders', ['status' => $status, 'cancelled_at' => now()], 'id = ?', [$o['id']]);
        if (class_exists('Hooks')) Hooks::fire((int)$o['organizer_id'], $status === 'refunded' ? 'order.refunded' : 'order.cancelled', Hooks::orderPayload(one('SELECT * FROM orders WHERE id = ?', [$o['id']]) ?: $o));
        q("UPDATE tickets SET status = 'cancelled', updated_at = NOW() WHERE order_id = ? AND status = 'valid'", [$o['id']]);
        q('DELETE FROM seat_holds WHERE order_id = ?', [$o['id']]);
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
        'wrong_session' => 'Autre date',
        'max_reached' => 'Entrées épuisées',
        'not_today' => 'Déjà entré aujourd\'hui',
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
            $sessionId = !empty($p['session_id']) ? (int)$p['session_id'] : null;
            $t = $code !== '' ? one('SELECT * FROM tickets WHERE code = ? AND event_id = ? FOR UPDATE', [$code, $ev['id']]) : null;
            if (!$t && $code !== '') $t = one('SELECT * FROM tickets WHERE code = ? FOR UPDATE', [$code]);
            $info = [];
            $zones = null;
            if ($t && $t['population_id']) {
                $pop = one('SELECT * FROM populations WHERE id = ?', [$t['population_id']]);
                $zones = $pop ? array_values(array_filter(array_map('intval', explode(',', (string)$pop['access_point_ids'])))) : [];
                if ($pop) $info['population'] = $pop['name'];
            }
            $maxEntries = $t ? max(1, (int)$t['max_entries']) : 1;
            $enteredToday = $t && $t['per_day'] && $t['last_scan_at'] && date('Y-m-d', strtotime($t['last_scan_at'])) === date('Y-m-d', strtotime($at)) && (int)$t['entries'] > 0;
            if (!$t) {
                $result = self::looksGenuine($code) ? 'invalid' : 'forged';
            } elseif ((int)$t['event_id'] !== (int)$ev['id']) {
                $result = 'wrong_event';
                $info['other_event'] = (string)val('SELECT name FROM events WHERE id = ?', [$t['event_id']]);
            } elseif ($t['status'] !== 'valid') {
                $result = 'cancelled';
                if ($t['replaced_by']) $info['resold'] = true;
            } elseif ($sessionId && $t['session_id'] && (int)$t['session_id'] !== $sessionId) {
                $result = 'wrong_session';
                $info['session'] = fr_date((string)val('SELECT starts_at FROM sessions WHERE id = ?', [$t['session_id']]));
            } elseif ($ap && (!$ap['active'] || (!empty($ap['opens_at']) && strtotime((string)$ap['opens_at']) > strtotime((string)($p['scanned_at'] ?? 'now'))) || (!empty($ap['closes_at']) && strtotime((string)$ap['closes_at']) < strtotime((string)($p['scanned_at'] ?? 'now'))))) {
                $result = 'closed';
                if ($ap['active'] && !empty($ap['opens_at'])) $info['hours'] = 'Ouverture ' . date('H\\hi', strtotime((string)$ap['opens_at'])) . (!empty($ap['closes_at']) ? ', fermeture ' . date('H\\hi', strtotime((string)$ap['closes_at'])) : '');
            } elseif ($ap && $zones !== null && !in_array((int)$ap['id'], $zones, true)) {
                $result = 'not_allowed';
            } elseif ($ap && $zones === null && $ap['rate_ids'] && !in_array((int)$t['rate_id'], array_map('intval', explode(',', $ap['rate_ids'])), true)) {
                $result = 'not_allowed';
            } elseif ($direction === 'out') {
                if (!$t['inside']) $result = 'not_inside';
                else { $result = 'exit'; update('tickets', ['inside' => 0, 'last_scan_at' => $at, 'updated_at' => now()], 'id = ?', [$t['id']]); }
            } elseif ($t['inside']) {
                $result = 'already';
            } elseif ($t['per_day'] && $enteredToday && !$ev['allow_reentry']) {
                $result = 'not_today';
            } elseif (!$t['per_day'] && !$ev['allow_reentry'] && (int)$t['entries'] >= $maxEntries) {
                $result = $maxEntries > 1 ? 'max_reached' : 'already';
            } else {
                $result = 'ok';
                update('tickets', [
                    // « sur place » sert aux sorties/ré-entrées et au billet à entrée unique ; un pass multi-entrées ou « 1 par jour » ne bloque pas
                    'inside' => $ev['allow_reentry'] || ($maxEntries === 1 && !$t['per_day']) ? 1 : 0,
                    'entries' => (int)$t['entries'] + 1, 'checked_in_at' => $t['checked_in_at'] ?: $at,
                    'last_scan_at' => $at, 'updated_at' => now(),
                ], 'id = ?', [$t['id']]);
                if ((int)$t['entries'] > 0) $info['reentry'] = true;
                if ($maxEntries > 1 && !$t['per_day']) $info['left'] = $maxEntries - (int)$t['entries'] - 1;
                if ($t['resale_status'] === 'listed') { // le propriétaire est venu : on retire le billet de la bourse
                    q("UPDATE resales SET status = 'cancelled' WHERE ticket_id = ? AND status = 'listed'", [$t['id']]);
                    update('tickets', ['resale_status' => null], 'id = ?', [$t['id']]);
                }
            }
            if (in_array($result, ['already', 'not_today', 'max_reached'], true)) {
                $last = one("SELECT s.scanned_at, a.name AS ap FROM scans s LEFT JOIN access_points a ON a.id = s.access_point_id
                             WHERE s.ticket_id = ? AND s.result = 'ok' AND s.undone = 0 ORDER BY s.scanned_at DESC LIMIT 1", [$t['id']]);
                $info['last_at'] = $last['scanned_at'] ?? $t['checked_in_at'];
                $info['last_ap'] = $last['ap'] ?? '';
            }
            $scan = [
                'uuid' => $uuid, 'event_id' => $ev['id'], 'ticket_id' => $t['id'] ?? null, 'code' => $code ?: '-',
                'access_point_id' => $ap['id'] ?? null, 'session_id' => $sessionId, 'direction' => $direction, 'result' => $result,
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
        if ($t && $result === 'ok' && class_exists('Hooks')) Hooks::fire((int)$p['event']['organizer_id'], 'ticket.checked_in', ['code' => $t['code'], 'event_id' => (int)$t['event_id'], 'first_name' => $t['first_name'], 'last_name' => $t['last_name'],
            'rate_name' => $t['rate_name'], 'access_point' => $ap['name'] ?? null, 'scanned_at' => date('c', strtotime($at)), 'offline' => !empty($p['offline'])]);
        return self::scanResponse($scan, $t, $info);
    }

    private static function scanResponse(array $scan, ?array $t, array $info): array
    {
        $r = $scan['result'];
        return [
            'id' => (int)$scan['id'],
            'uuid' => $scan['uuid'],
            'result' => $r,
            'level' => in_array($r, ['ok', 'exit'], true) ? 'ok' : (in_array($r, ['already', 'not_inside', 'not_today', 'max_reached'], true) ? 'warn' : 'error'),
            'label' => self::LABELS[$r] ?? $r,
            'code' => $scan['code'],
            'info' => $info,
            'ticket' => $t ? [
                'code' => $t['code'], 'name' => trim($t['first_name'] . ' ' . $t['last_name']), 'rate' => $t['rate_name'],
                'rate_id' => (int)$t['rate_id'], 'status' => $t['status'], 'inside' => (int)$t['inside'], 'entries' => (int)$t['entries'],
                'session_id' => $t['session_id'] ? (int)$t['session_id'] : null, 'seat' => $t['seat'], 'max' => (int)$t['max_entries'], 'per_day' => (int)$t['per_day'],
                'type' => $t['type'], 'company' => $t['company'],
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

    public static function stats(int $eventId, ?int $sessionId = null): array
    {
        $ss = $sessionId ? ' AND t.session_id = ' . (int)$sessionId : '';
        $os = $sessionId ? ' AND session_id = ' . (int)$sessionId : '';
        $byRate = all("SELECT r.id, r.name, r.quota, r.price,
                SUM(t.status = 'valid') AS sold,
                COALESCE(SUM(CASE WHEN t.status = 'valid' THEN t.price END),0) AS revenue,
                SUM(t.status = 'valid' AND t.entries > 0) AS checked,
                SUM(t.status = 'valid' AND t.inside = 1) AS inside
            FROM rates r LEFT JOIN tickets t ON t.rate_id = r.id AND t.type IN ('ticket','invitation')$ss
            WHERE r.event_id = ? GROUP BY r.id ORDER BY r.sort, r.id", [$eventId]);
        $o = one("SELECT COUNT(*) n, COALESCE(SUM(total),0) total, COALESCE(SUM(organizer_amount),0) org, COALESCE(SUM(commission),0) com
                  FROM orders WHERE event_id = ? AND status = 'paid' AND (channel IS NULL OR channel <> 'systeme')$os", [$eventId]);
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
        $charges = class_exists('Billing') ? Billing::chargesTotal($orgId) : 0;
        return [
            'collected' => (int)$r['collected'], 'commission' => (int)$r['commission'], 'due' => (int)$r['due'],
            'paid_out' => $paid, 'charges' => $charges, 'balance' => (int)$r['due'] - $paid - $charges, 'orders' => (int)$r['n'],
        ];
    }
}
