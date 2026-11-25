<?php
declare(strict_types=1);

/* 2.4 : cashless & bars — réglages, caisse bar (tablette / téléphone), espace participant, recharge en ligne. */

require_once APP . '/controllers/public.php';

function cashless_tab(string $id): string
{
    require_once APP . '/controllers/pro.php';
    $org = require_org();
    require_module('cashless');
    $ev = org_event((int)$id);
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'settings') {
            update('events', ['cashless_enabled' => checked('cashless_enabled'), 'cashless_min_topup' => max(100, parse_money(input('cashless_min_topup'))), 'cashless_refund_until' => input('cashless_refund_until') ?: null], 'id = ?', [$ev['id']]);
            back(['success' => 'Réglages cashless enregistrés.']);
        }
        if ($a === 'bar') {
            $name = mb_substr(trim((string)input('name')), 0, 80);
            if ($name === '') back(['error' => 'Donne un nom au bar.']);
            $pin = preg_replace('~\D~', '', (string)input('pin')) ?: null;
            if ((int)input('bar_id')) update('bars', ['name' => $name, 'pin' => $pin], 'id = ? AND event_id = ?', [(int)input('bar_id'), $ev['id']]);
            else insert('bars', ['event_id' => $ev['id'], 'name' => $name, 'pin' => $pin, 'active' => 1]);
            back(['success' => 'Bar enregistré.']);
        }
        if ($a === 'bar_toggle') { q('UPDATE bars SET active = 1 - active WHERE id = ? AND event_id = ?', [(int)input('bar_id'), $ev['id']]); back(); }
        if ($a === 'product') {
            $name = mb_substr(trim((string)input('name')), 0, 80);
            if ($name === '' || parse_money(input('price')) <= 0) back(['error' => 'Nom et prix obligatoires.']);
            $d = ['name' => $name, 'category' => mb_substr(trim((string)input('category')) ?: 'boisson', 0, 30), 'price' => parse_money(input('price')), 'deposit' => parse_money(input('deposit')),
                'bar_ids' => implode(',', array_map('intval', (array)($_POST['bar_ids'] ?? []))) ?: null, 'sort' => (int)input('sort'), 'active' => 1];
            if ((int)input('product_id')) update('bar_products', $d, 'id = ? AND event_id = ?', [(int)input('product_id'), $ev['id']]);
            else insert('bar_products', $d + ['event_id' => $ev['id']]);
            back(['success' => 'Produit enregistré.']);
        }
        if ($a === 'product_toggle') { q('UPDATE bar_products SET active = 1 - active WHERE id = ? AND event_id = ?', [(int)input('product_id'), $ev['id']]); back(); }
        if ($a === 'refunded') {
            $w = one("SELECT * FROM wallets WHERE id = ? AND event_id = ? AND status = 'refund_req'", [(int)input('wallet_id'), $ev['id']]);
            if ($w && (int)$w['balance'] > 0) {
                insert('wallet_tx', ['wallet_id' => $w['id'], 'event_id' => $ev['id'], 'kind' => 'refund', 'amount' => -(int)$w['balance'], 'user_id' => user()['id'], 'items' => json_encode(['iban' => substr((string)$w['refund_iban'], -4)]), 'created_at' => now()]);
                q("UPDATE wallets SET balance = 0, status = 'refunded', refunded_at = NOW() WHERE id = ?", [$w['id']]);
            }
            back(['success' => 'Remboursement noté.']);
        }
    }
    $bars = all('SELECT b.*, (SELECT COALESCE(-SUM(amount), 0) FROM wallet_tx t WHERE t.bar_id = b.id AND t.kind = \'sale\' AND t.undone = 0) sales FROM bars b WHERE event_id = ? ORDER BY id', [$ev['id']]);
    $products = all('SELECT * FROM bar_products WHERE event_id = ? ORDER BY category, sort, name', [$ev['id']]);
    $k = Cashless::stats((int)$ev['id']);
    $sold = [];
    foreach (all("SELECT items FROM wallet_tx WHERE event_id = ? AND kind = 'sale' AND undone = 0", [$ev['id']]) as $t) foreach (json_decode((string)$t['items'], true) ?: [] as $l) { $sold[$l['name']]['qty'] = ($sold[$l['name']]['qty'] ?? 0) + $l['qty']; $sold[$l['name']]['sum'] = ($sold[$l['name']]['sum'] ?? 0) + $l['qty'] * $l['unit']; }
    uasort($sold, fn($a, $b) => $b['sum'] <=> $a['sum']);
    $hours = all("SELECT DATE_FORMAT(created_at, '%d/%m %Hh') h, -SUM(amount) s FROM wallet_tx WHERE event_id = ? AND kind = 'sale' AND undone = 0 GROUP BY h ORDER BY MIN(created_at)", [$ev['id']]);
    $refunds = all("SELECT * FROM wallets WHERE event_id = ? AND status = 'refund_req' ORDER BY id", [$ev['id']]);
    return ev_page($ev, 'cashless', 'Cashless & bars', 'cashless', compact('bars', 'products', 'k', 'sold', 'hours', 'refunds'));
}

// ---------- Caisse bar ----------
function bar_pos(string $eventId): string
{
    $u = require_login(['owner', 'manager', 'scanner']);
    $ev = one('SELECT * FROM events WHERE id = ?', [(int)$eventId]);
    if (!$ev || ($u['role'] !== 'superadmin' && (int)$ev['organizer_id'] !== (int)$u['organizer_id'])) abort(404);
    $bars = all('SELECT id, name, pin IS NOT NULL has_pin FROM bars WHERE event_id = ? AND active = 1 ORDER BY id', [$ev['id']]);
    $products = all('SELECT id, name, category, price, deposit, bar_ids FROM bar_products WHERE event_id = ? AND active = 1 ORDER BY category, sort, name', [$ev['id']]);
    return view('bar/pos', compact('ev', 'bars', 'products', 'u'));
}

function bar_api(string $eventId): never
{
    $u = require_login(['owner', 'manager', 'scanner']);
    $ev = one('SELECT * FROM events WHERE id = ?', [(int)$eventId]);
    if (!$ev || ($u['role'] !== 'superadmin' && (int)$ev['organizer_id'] !== (int)$u['organizer_id'])) json_out(['error' => 'Événement inconnu'], 404);
    if (!(int)$ev['cashless_enabled']) json_out(['error' => 'Le cashless n\'est pas activé pour cet événement.'], 400);
    $bar = one('SELECT * FROM bars WHERE id = ? AND event_id = ? AND active = 1', [(int)input('bar_id'), $ev['id']]);
    if (!$bar) json_out(['error' => 'Choisis ton bar.'], 400);
    if ($bar['pin'] && !hash_equals((string)$bar['pin'], (string)input('pin'))) json_out(['error' => 'Code du bar incorrect.'], 403);
    $a = (string)input('action');
    try {
        if ($a === 'undo') { $r = Cashless::undo((int)input('tx_id'), (int)$ev['id'], (int)$u['id']); json_out(['ok' => true, 'balance' => $r['balance']]); }
        $w = Cashless::byCode((int)$ev['id'], (string)input('code'));
        if (!$w) json_out(['error' => 'Billet inconnu pour cet événement.'], 404);
        $t = $w['ticket_id'] ? one('SELECT first_name, last_name, rate_name FROM tickets WHERE id = ?', [$w['ticket_id']]) : null;
        $who = $t ? trim($t['first_name'] . ' ' . mb_substr((string)$t['last_name'], 0, 1) . '.') : '';
        if ($a === 'lookup') json_out(['ok' => true, 'balance' => (int)$w['balance'], 'name' => $who, 'status' => $w['status']]);
        if ($a === 'topup') {
            $r = Cashless::topup($w, parse_money(input('amount')), input('method') === 'card' ? 'card' : 'cash', ['bar_id' => $bar['id'], 'user_id' => $u['id'], 'device' => 'bar']);
            json_out(['ok' => true, 'balance' => $r['balance'], 'tx' => $r['tx'], 'name' => $who]);
        }
        if ($a === 'sale') {
            $items = array_map(fn($x) => [(int)($x[0] ?? 0), (int)($x[1] ?? 0)], json_decode((string)input('items'), true) ?: []);
            $r = Cashless::sale($w, (int)$bar['id'], $items, (int)$u['id'], 'bar');
            json_out(['ok' => true, 'balance' => $r['balance'], 'total' => $r['total'], 'tx' => $r['tx'], 'name' => $who]);
        }
    } catch (Throwable $e) { json_out(['error' => $e->getMessage()], 400); }
    json_out(['error' => 'Action inconnue'], 400);
}

// ---------- Espace participant ----------
function wallet_page(string $token): string
{
    $w = one('SELECT * FROM wallets WHERE token = ?', [$token]);
    if (!$w || strlen($token) < 20) abort(404);
    $ev = one('SELECT * FROM events WHERE id = ?', [$w['event_id']]);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$ev['organizer_id']]);
    $ended = strtotime((string)($ev['ends_at'] ?: $ev['starts_at'])) + 6 * 3600 < time();
    $canRefund = $ended && $ev['cashless_refund_until'] && date('Y-m-d') <= $ev['cashless_refund_until'] && (int)$w['balance'] > 0 && $w['status'] === 'active';
    if (is_post()) {
        if (input('action') === 'refund' && $canRefund) {
            $iban = strtoupper(preg_replace('~\s+~', '', (string)input('iban')));
            if (!iban_valid($iban)) redirect('/cashless/' . $token, ['error' => 'Cet IBAN n\'est pas valide.']);
            update('wallets', ['status' => 'refund_req', 'refund_iban' => $iban], 'id = ?', [$w['id']]);
            redirect('/cashless/' . $token, ['success' => 'Demande envoyée : ' . money((int)$w['balance']) . ' te seront remboursés par virement.']);
        }
        if (input('action') === 'topup' && !$ended && (int)$ev['cashless_enabled']) {
            $amount = input('amount') === 'libre' ? parse_money(input('free')) : (int)input('amount');
            if ($amount < (int)$ev['cashless_min_topup'] || $amount > 30000) redirect('/cashless/' . $token, ['error' => 'Recharge entre ' . money((int)$ev['cashless_min_topup']) . ' et 300 €.']);
            $t = $w['ticket_id'] ? one('SELECT * FROM tickets WHERE id = ?', [$w['ticket_id']]) : null;
            $buyer = one('SELECT email, first_name, last_name, phone FROM orders WHERE id = ?', [$t['order_id'] ?? 0]) ?: ['email' => $w['email'], 'first_name' => 'Participant', 'last_name' => '', 'phone' => ''];
            $o = Store::createOrder($org, $buyer, [['kind' => 'topup', 'name' => 'Recharge cashless · ' . $ev['name'], 'qty' => 1, 'unit_price' => $amount, 'item_ref' => (int)$w['id']]], ['channel' => 'cashless']);
            $_SESSION['orders'][] = $o['ref'];
            redirect('/paiement/' . $o['ref'] . '?t=' . $o['token']);
        }
    }
    $tx = all('SELECT t.*, b.name bar FROM wallet_tx t LEFT JOIN bars b ON b.id = t.bar_id WHERE t.wallet_id = ? ORDER BY t.id DESC LIMIT 100', [$w['id']]);
    return pub('wallet', compact('w', 'ev', 'org', 'tx', 'ended', 'canRefund'), 'Mon cashless · ' . $ev['name']);
}

/** Depuis la page de commande : ouvre (ou crée) le porte-monnaie d'un billet. */
function wallet_open(string $ref, string $code): never
{
    $o = order_by_ref($ref);
    $t = one("SELECT * FROM tickets WHERE order_id = ? AND code = ? AND status = 'valid'", [$o['id'], $code]);
    if (!$t) abort(404);
    $w = Cashless::forTicket($t);
    redirect('/cashless/' . $w['token']);
}
