<?php
declare(strict_types=1);

/* 4.2 : impressions — stocks de billets pré-imprimés, billets thermiques Boca, cartes membres CR80. */

require_once APP . '/controllers/pro.php';

function pdf_out(string $pdf, string $name): never
{
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $name . '"');
    echo $pdf;
    exit;
}

function stocks_page(string $id): string
{
    $org = require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'create') {
            $r = one('SELECT * FROM rates WHERE id = ? AND event_id = ?', [(int)input('rate_id'), $ev['id']]);
            $n = max(1, min(500, (int)input('qty')));
            $label = mb_substr(trim((string)input('label')), 0, 160) ?: 'Stock guichet';
            if (!$r) back(['error' => 'Choisis un tarif.']);
            if ((int)$r['quota'] > 0 && (int)val("SELECT COUNT(*) FROM tickets WHERE rate_id = ? AND status = 'valid'", [$r['id']]) + $n > (int)$r['quota'])
                back(['error' => 'Pas assez de places restantes sur ce tarif pour imprimer ' . $n . ' billets.']);
            $price = isset($_POST['price']) && trim((string)$_POST['price']) !== '' ? parse_money((string)input('price')) : (int)$r['price'];
            $oid = insert('orders', ['ref' => 'S' . strtoupper(token(7)), 'token' => token(24), 'event_id' => $ev['id'], 'organizer_id' => $org['id'], 'email' => '', 'first_name' => $label, 'last_name' => '',
                'subtotal' => 0, 'total' => 0, 'method' => 'stock', 'channel' => 'stock', 'status' => 'pending', 'created_at' => now(), 'expires_at' => date('Y-m-d H:i:s', time() + 3600)]);
            insert('order_items', ['order_id' => $oid, 'kind' => 'rate', 'rate_id' => $r['id'], 'name' => $r['name'], 'qty' => $n, 'unit_price' => $price]);
            Tickets::markPaid($oid);
            $sid = insert('ticket_stocks', ['organizer_id' => $org['id'], 'event_id' => $ev['id'], 'rate_id' => $r['id'], 'order_id' => $oid, 'label' => $label, 'qty' => $n, 'unit_price' => $price, 'created_at' => now()]);
            Audit::log('stock.created', $label . ' · ' . $n . ' × ' . $r['name'], (int)$ev['id'], $oid);
            redirect('/pro/evenements/' . $ev['id'] . '/stocks', ['success' => $n . ' billets générés. Imprime-les en A4 ou au format thermique.']);
        }
        $s = one('SELECT * FROM ticket_stocks WHERE id = ? AND event_id = ?', [(int)input('stock_id'), $ev['id']]);
        if (!$s) back();
        if ($a === 'close') { // déclarer les invendus : on annule les N derniers billets non scannés, la recette = vendus × prix
            if ($s['closed_at']) back(['error' => 'Ce stock est déjà soldé.']);
            $unsold = max(0, min((int)$s['qty'], (int)input('unsold')));
            $codes = array_filter(array_map('trim', preg_split('~[\s,;]+~', (string)input('codes'))));
            $ids = [];
            if ($codes) $ids = array_column(all('SELECT id FROM tickets WHERE order_id = ? AND status = \'valid\' AND entries = 0 AND code IN (' . implode(',', array_fill(0, count($codes), '?')) . ')', array_merge([$s['order_id']], $codes)), 'id');
            if (count($ids) < $unsold) $ids = array_merge($ids, array_column(all('SELECT id FROM tickets WHERE order_id = ? AND status = \'valid\' AND entries = 0' . ($ids ? ' AND id NOT IN (' . implode(',', array_map('intval', $ids)) . ')' : '') . ' ORDER BY id DESC LIMIT ' . ($unsold - count($ids)), [$s['order_id']]), 'id'));
            if ($ids) q("UPDATE tickets SET status = 'cancelled', updated_at = NOW() WHERE id IN (" . implode(',', array_map('intval', $ids)) . ')');
            $sold = (int)val("SELECT COUNT(*) FROM tickets WHERE order_id = ? AND status = 'valid'", [$s['order_id']]);
            update('ticket_stocks', ['sold' => $sold, 'closed_at' => now()], 'id = ?', [$s['id']]);
            update('orders', ['subtotal' => $sold * (int)$s['unit_price'], 'total' => $sold * (int)$s['unit_price'], 'pay_method' => in_array(input('pay_method'), ['especes', 'cb', 'cheque', 'virement'], true) ? (string)input('pay_method') : 'especes'], 'id = ?', [$s['order_id']]);
            q('UPDATE order_items SET qty = ? WHERE order_id = ? AND kind = \'rate\'', [$sold, $s['order_id']]);
            Audit::log('stock.closed', $s['label'] . ' · ' . $sold . ' vendus / ' . count($ids) . ' invendus annulés', (int)$ev['id'], (int)$s['order_id']);
            back(['success' => 'Stock soldé : ' . $sold . ' vendus (' . money($sold * (int)$s['unit_price']) . '), ' . count($ids) . ' billets invendus annulés.']);
        }
        if ($a === 'cancel') {
            if ((int)val("SELECT COUNT(*) FROM tickets WHERE order_id = ? AND entries > 0", [$s['order_id']])) back(['error' => 'Des billets de ce stock ont déjà été scannés : solde-le plutôt.']);
            Tickets::cancelOrder(one('SELECT * FROM orders WHERE id = ?', [$s['order_id']]));
            q('DELETE FROM ticket_stocks WHERE id = ?', [$s['id']]);
            back(['success' => 'Stock annulé : les billets imprimés ne passeront plus au contrôle.']);
        }
        back();
    }
    $stocks = all("SELECT s.*, r.name rate_name, (SELECT COUNT(*) FROM tickets t WHERE t.order_id = s.order_id AND t.status = 'valid') valid, (SELECT COUNT(*) FROM tickets t WHERE t.order_id = s.order_id AND t.entries > 0) scanned
        FROM ticket_stocks s JOIN rates r ON r.id = s.rate_id WHERE s.event_id = ? ORDER BY s.id DESC", [$ev['id']]);
    $rates = all('SELECT id, name, price FROM rates WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    return ev_page($ev, 'stocks', 'Billets à imprimer', 'stocks', compact('org', 'ev', 'stocks', 'rates'));
}

function stock_pdf(string $id, string $sid): never
{
    $org = require_org();
    $ev = org_event((int)$id);
    $s = one('SELECT * FROM ticket_stocks WHERE id = ? AND event_id = ?', [(int)$sid, $ev['id']]) ?: abort(404);
    $o = one('SELECT * FROM orders WHERE id = ?', [$s['order_id']]);
    $tickets = all("SELECT * FROM tickets WHERE order_id = ? AND status = 'valid' ORDER BY id", [$o['id']]);
    if (!$tickets) abort(404, 'Aucun billet valide dans ce stock.');
    $pdf = input('format') === 'boca' ? Prints::boca($o, $ev, $org, $tickets) : (input('format') === 'ticket' ? Docs::thermal($o, $ev, $org, $tickets) : Prints::sheet($o, $ev, $org, $tickets, (string)$s['label']));
    pdf_out($pdf, 'stock-' . $s['id'] . '-' . $ev['slug'] . '.pdf');
}

/** Billets d'une commande au format thermique Boca (guichet, commandes). */
function order_boca(string $id): never
{
    $org = require_org();
    $o = one('SELECT * FROM orders WHERE id = ? AND organizer_id = ?', [(int)$id, $org['id']]) ?: abort(404);
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]) ?: abort(404);
    $tickets = all("SELECT * FROM tickets WHERE order_id = ? AND status = 'valid' ORDER BY id", [$o['id']]);
    if (!$tickets) abort(404, 'Aucun billet valide.');
    pdf_out(Prints::boca($o, $ev, $org, $tickets), 'billets-' . $o['ref'] . '-boca.pdf');
}

/** Cartes membres CR80 des abonnés (une par page pour imprimante PVC, ou planche A4). */
function member_cards(): never
{
    $org = require_org();
    $w = ['p.organizer_id = ?', "p.status = 'active'"]; $a = [$org['id']];
    if ((int)input('type_id')) { $w[] = 'p.pass_type_id = ?'; $a[] = (int)input('type_id'); }
    if ((int)input('pass_id')) { $w[] = 'p.id = ?'; $a[] = (int)input('pass_id'); }
    $rows = all('SELECT p.*, t.name tname, t.kind, t.valid_until FROM passes p JOIN pass_types t ON t.id = p.pass_type_id WHERE ' . implode(' AND ', $w) . ' ORDER BY p.last_name, p.first_name LIMIT 1000', $a);
    if (!$rows) abort(404, 'Aucun abonné actif à imprimer.');
    $cards = array_map(fn($p) => ['name' => trim($p['first_name'] . ' ' . $p['last_name']) ?: 'Abonné', 'sub' => $p['tname'] . ($p['kind'] === 'carnet' && $p['uses_left'] !== null ? ' · ' . (int)$p['uses_left'] . ' entrée(s)' : ''),
        'code' => $p['code'], 'valid' => $p['valid_until'] ? 'Valable jusqu\'au ' . date('d/m/Y', strtotime($p['valid_until'])) : 'Membre depuis le ' . date('d/m/Y', strtotime($p['created_at'])), 'kind' => $p['kind'] === 'carnet' ? 'Carnet' : 'Carte membre'], $rows);
    pdf_out(Prints::cards($cards, $org, input('format') === 'a4'), 'cartes-membres.pdf');
}
