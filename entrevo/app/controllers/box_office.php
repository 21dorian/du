<?php
declare(strict_types=1);

/* 4.1 : guichet plein écran (vente sur place, invitations, réservations à régler plus tard), pensé pour une tablette ou un poste de billetterie. */

require_once APP . '/controllers/pro.php';

const BOX_PAY = ['especes' => 'Espèces', 'cb' => 'Carte (TPE)', 'cheque' => 'Chèque', 'virement' => 'Virement', 'mixte' => 'Mixte', 'pass' => 'Pass Culture / chèque-vacances'];

function box_ev(string $id): array
{
    require_module('guichet');
    require_org();
    return org_event((int)$id);
}

function box_screen(string $id): string
{
    $ev = box_ev($id);
    $org = current_org();
    $sessions = Tickets::sessions((int)$ev['id']);
    $seatmap = (bool)Seating::map($ev);
    $products = all('SELECT * FROM products WHERE event_id = ? AND active = 1 ORDER BY sort, id', [$ev['id']]);
    return view('pro/box_screen', compact('ev', 'org', 'sessions', 'seatmap', 'products'));
}

/** État du guichet : places restantes par tarif pour la date choisie, totaux du jour, réservations, dernières ventes. */
function box_state(string $id): never
{
    $ev = box_ev($id);
    $sid = (int)input('session_id');
    $session = $sid ? one('SELECT * FROM sessions WHERE id = ? AND event_id = ?', [$sid, $ev['id']]) : null;
    $groups = []; foreach (RatesPlus::groups((int)$ev['id']) as $g) $groups[(int)$g['id']] = $g['name'];
    $rates = [];
    foreach (all('SELECT * FROM rates WHERE event_id = ? ORDER BY sort, id', [$ev['id']]) as $r) {
        if ($session && ($only = Tickets::rateSessions($r)) && !in_array((int)$session['id'], $only, true)) continue;
        $rem = Tickets::remaining($r, $ev, $session);
        $rates[] = ['id' => (int)$r['id'], 'name' => $r['name'], 'price' => RatesPlus::price($r, $session ? (int)$session['id'] : null), 'active' => (bool)$r['active'], 'hidden' => (bool)$r['hidden'],
            'left' => $rem === PHP_INT_MAX ? null : $rem, 'pack' => max(1, (int)$r['pack_size']), 'group' => $groups[(int)$r['quota_group_id']] ?? null, 'proof' => $r['proof'], 'color' => $r['scan_color'] ?: null];
    }
    $cap = Tickets::capacity($ev, $session);
    $used = Tickets::eventUsed((int)$ev['id'], $session ? (int)$session['id'] : null);
    $today = all("SELECT COALESCE(pay_method, method) m, COUNT(*) n, COALESCE(SUM(total), 0) t FROM orders WHERE event_id = ? AND status = 'paid' AND method IN ('guichet','invitation') AND paid_at >= CURDATE() GROUP BY m", [$ev['id']]);
    $resa = all("SELECT o.id, o.ref, o.first_name, o.last_name, o.phone, o.email, o.total, o.expires_at, o.note, (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id AND i.kind = 'rate') n
        FROM orders o WHERE o.event_id = ? AND o.status = 'pending' AND o.method = 'guichet' AND o.channel = 'reservation' AND o.expires_at > NOW() ORDER BY o.expires_at LIMIT 50", [$ev['id']]);
    $last = all("SELECT o.id, o.ref, o.first_name, o.last_name, o.total, o.pay_method, o.method, o.paid_at, (SELECT COUNT(*) FROM tickets t WHERE t.order_id = o.id) n
        FROM orders o WHERE o.event_id = ? AND o.status IN ('paid','refunded') AND o.method IN ('guichet','invitation') ORDER BY o.paid_at DESC LIMIT 12", [$ev['id']]);
    json_out(['rates' => $rates, 'cap' => $cap, 'used' => $used, 'today' => $today, 'resa' => $resa, 'last' => $last]);
}

/** Recherche d'un client (anciens acheteurs de l'organisateur). */
function box_clients(string $id): never
{
    $ev = box_ev($id);
    $q = trim((string)input('q'));
    if (mb_strlen($q) < 2) json_out(['list' => []]);
    $like = '%' . $q . '%';
    $list = all("SELECT LOWER(email) email, MAX(first_name) first_name, MAX(last_name) last_name, MAX(phone) phone, COUNT(*) n FROM orders WHERE organizer_id = ? AND status = 'paid' AND email <> ''
        AND (email LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ? OR phone LIKE ? OR ref = ?) GROUP BY LOWER(email) ORDER BY n DESC LIMIT 8", [$ev['organizer_id'], $like, $like, $like, strtoupper($q)]);
    json_out(['list' => $list]);
}

function box_sell(string $id): never
{
    $ev = box_ev($id);
    $org = current_org();
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $kind = in_array($in['kind'] ?? '', ['sale', 'invitation', 'reservation'], true) ? $in['kind'] : 'sale';
    $q = []; foreach ((array)($in['qty'] ?? []) as $rid => $n) if ((int)$n > 0) $q[(int)$rid] = min(500, (int)$n);
    $prods = []; foreach ((array)($in['products'] ?? []) as $pid => $n) if ((int)$n > 0) $prods[(int)$pid] = min(100, (int)$n);
    $b = (array)($in['buyer'] ?? []);
    $buyer = ['first_name' => mb_substr(trim((string)($b['first_name'] ?? '')), 0, 120), 'last_name' => mb_substr(trim((string)($b['last_name'] ?? '')), 0, 120), 'email' => strtolower(trim((string)($b['email'] ?? ''))), 'phone' => mb_substr(trim((string)($b['phone'] ?? '')), 0, 40)];
    if ($buyer['email'] !== '' && !is_email($buyer['email'])) json_out(['error' => 'Cette adresse e-mail a l\'air fausse.'], 422);
    if ($kind === 'reservation' && $buyer['last_name'] === '') json_out(['error' => 'Une réservation doit être au nom de quelqu\'un.'], 422);
    if ($buyer['last_name'] === '' && $buyer['first_name'] === '') $buyer['last_name'] = $kind === 'invitation' ? 'Invité' : 'Client guichet';
    $session = Tickets::hasSessions((int)$ev['id']) ? one('SELECT * FROM sessions WHERE id = ? AND event_id = ?', [(int)($in['session_id'] ?? 0), $ev['id']]) : null;
    $seats = []; foreach ((array)($in['seats'] ?? []) as $rid => $list) $seats[(int)$rid] = array_values(array_filter(array_map(fn($x) => strtoupper(preg_replace('~[^A-Za-z0-9-]~', '', (string)$x)), preg_split('~[\s,;]+~', (string)$list))));
    $pay = array_key_exists((string)($in['pay_method'] ?? ''), BOX_PAY) ? (string)$in['pay_method'] : 'especes';
    $holdUntil = null;
    if ($kind === 'reservation') {
        $h = !empty($in['hold_until']) ? strtotime((string)$in['hold_until']) : false;
        $start = strtotime((string)($session['starts_at'] ?? $ev['starts_at']));
        $holdUntil = date('Y-m-d H:i:s', $h && $h > time() ? min($h, $start) : max(time() + 3600, $start - 3600));
    }
    $note = mb_substr(trim((string)($in['note'] ?? '')), 0, 400);
    if ($pay === 'mixte' && !empty($in['split'])) $note = trim($note . ' · Règlement : ' . implode(' + ', array_map(fn($s) => (BOX_PAY[$s['m'] ?? ''] ?? '?') . ' ' . money((int)($s['a'] ?? 0)), array_slice((array)$in['split'], 0, 4))), ' ·');
    $r = Tickets::createOrder(['event' => $ev, 'org' => $org, 'session' => $session, 'seats' => $seats, 'quantities' => $q, 'products' => $prods, 'buyer' => $buyer,
        'method' => $kind === 'invitation' ? 'invitation' : 'guichet', 'channel' => $kind === 'reservation' ? 'reservation' : ($kind === 'invitation' ? 'invitation' : 'guichet'),
        'pay_method' => $kind === 'sale' ? $pay : null, 'note' => $note, 'user_id' => user()['id'], 'force' => !empty($in['force']),
        'discount_pct' => max(0, min(100, (float)($in['discount_pct'] ?? 0))), 'promo' => (string)($in['promo'] ?? ''), 'staff_promo' => true, 'hold_until' => $holdUntil]);
    if (!empty($r['errors'])) json_out(['error' => implode(' ', $r['errors'])], 422);
    $o = $r['order'];
    if ($kind !== 'reservation') {
        $send = !empty($in['send']) && $buyer['email'] !== '';
        $o = Tickets::confirm((int)$o['id'], [], $send);
        Audit::log('box.sale', $o['ref'] . ' · ' . money((int)$o['total']) . ' · ' . ($kind === 'invitation' ? 'invitation' : (BOX_PAY[$pay] ?? $pay)), (int)$ev['id'], (int)$o['id']);
    } else {
        Audit::log('box.reservation', $o['ref'] . ' jusqu\'au ' . $holdUntil, (int)$ev['id'], (int)$o['id']);
        if ($buyer['email'] !== '') { try { Mailer::send($buyer['email'], 'Ta réservation · ' . $ev['name'], '<p>Bonjour ' . e($buyer['first_name']) . ',</p><p>Ta réservation pour <b>' . e($ev['name']) . '</b> (' . e(fr_date($session['starts_at'] ?? $ev['starts_at'])) . ') est enregistrée : <b>' . e(money((int)$o['total'])) . '</b> à régler à la billetterie avant le <b>' . e(fr_date($holdUntil)) . '</b>.</p><p>Référence : <b>' . e($o['ref']) . '</b></p>'); } catch (Throwable $e) { log_line('emails', $e->getMessage()); } }
    }
    json_out(['ok' => true, 'kind' => $kind, 'order' => ['id' => (int)$o['id'], 'ref' => $o['ref'], 'total' => (int)$o['total'], 'name' => trim($o['first_name'] . ' ' . $o['last_name']), 'hold_until' => $holdUntil],
        'pdf' => url('/commande/' . $o['ref'] . '/pdf', ['t' => $o['token']]), 'receipt' => url('/pro/commandes/' . $o['id'] . '/ticket-caisse'), 'detail' => url('/pro/commandes/' . $o['id'])]);
}

/** Réservation : encaisser (les billets sont émis) ou annuler (les places sont libérées). */
function box_reservation(string $id): never
{
    $ev = box_ev($id);
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $o = one("SELECT * FROM orders WHERE id = ? AND event_id = ? AND status = 'pending' AND channel = 'reservation'", [(int)($in['order_id'] ?? 0), $ev['id']]);
    if (!$o) json_out(['error' => 'Réservation introuvable ou déjà traitée.'], 404);
    if (($in['action'] ?? '') === 'cancel') {
        update('orders', ['status' => 'cancelled', 'cancelled_at' => now()], 'id = ?', [$o['id']]);
        q('DELETE FROM seat_holds WHERE order_id = ?', [$o['id']]);
        Audit::log('box.reservation_cancel', $o['ref'], (int)$ev['id'], (int)$o['id']);
        json_out(['ok' => true]);
    }
    $pay = array_key_exists((string)($in['pay_method'] ?? ''), BOX_PAY) ? (string)$in['pay_method'] : 'especes';
    update('orders', ['pay_method' => $pay, 'expires_at' => date('Y-m-d H:i:s', time() + 3600)], 'id = ?', [$o['id']]);
    $o = Tickets::confirm((int)$o['id'], [], !empty($in['send']) && is_email((string)$o['email']));
    Audit::log('box.sale', $o['ref'] . ' · réservation encaissée · ' . (BOX_PAY[$pay] ?? $pay), (int)$ev['id'], (int)$o['id']);
    json_out(['ok' => true, 'order' => ['id' => (int)$o['id'], 'ref' => $o['ref'], 'total' => (int)$o['total'], 'name' => trim($o['first_name'] . ' ' . $o['last_name'])],
        'pdf' => url('/commande/' . $o['ref'] . '/pdf', ['t' => $o['token']]), 'receipt' => url('/pro/commandes/' . $o['id'] . '/ticket-caisse'), 'detail' => url('/pro/commandes/' . $o['id'])]);
}
