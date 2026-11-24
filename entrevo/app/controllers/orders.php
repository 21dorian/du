<?php
declare(strict_types=1);

/* Commandes côté organisateur : liste filtrable/triable/paginée, actions groupées,
   fiche commande (étiquettes, note interne, acheteur modifiable, remboursement partiel). */

require_once APP . '/controllers/pro.php';

const ORDER_SORTS = ['date' => 'o.created_at', 'total' => 'o.total', 'qty' => 'qty', 'name' => 'o.last_name', 'ref' => 'o.ref', 'event' => 'e.name'];
const ORDER_PER_PAGE = 50;

/** Construit le WHERE commun à la liste, à l'export et aux actions « toute la sélection ». */
function orders_filters(int $orgId, array $f): array
{
    $w = ['o.organizer_id = ?', "(o.channel IS NULL OR o.channel <> 'systeme')"];
    $p = [$orgId];
    if ($f['q'] !== '') {
        $w[] = '(o.ref LIKE ? OR o.email LIKE ? OR o.last_name LIKE ? OR o.first_name LIKE ? OR CONCAT(o.first_name, " ", o.last_name) LIKE ? OR o.phone LIKE ?)';
        array_push($p, ...array_fill(0, 6, '%' . $f['q'] . '%'));
    }
    $st = $f['st'];
    if ($st === 'refund_request') $w[] = "o.status = 'paid' AND o.refund_requested_at IS NOT NULL";
    elseif ($st === 'partial') $w[] = "o.status = 'paid' AND o.refunded_amount > 0";
    elseif (in_array($st, ['paid', 'pending', 'cancelled', 'refunded', 'expired'], true)) { $w[] = 'o.status = ?'; $p[] = $st; }
    else $w[] = "o.status IN ('paid','refunded','cancelled')";
    if ($f['ev']) { $w[] = 'o.event_id = ?'; $p[] = $f['ev']; }
    if (in_array($f['m'], ['online', 'guichet', 'gratuit', 'invitation'], true)) { $w[] = 'o.method = ?'; $p[] = $f['m']; }
    if ($f['tag'] !== '') { $w[] = 'FIND_IN_SET(?, o.tags)'; $p[] = $f['tag']; }
    if (!empty($f['rt'])) { $w[] = 'EXISTS (SELECT 1 FROM order_items i WHERE i.order_id = o.id AND i.rate_id = ?)'; $p[] = (int)$f['rt']; }
    if (($f['pr'] ?? '') === '*') $w[] = 'o.promo_code IS NOT NULL';
    elseif (($f['pr'] ?? '') !== '') { $w[] = 'o.promo_code = ?'; $p[] = strtoupper($f['pr']); }
    $src = $f['src'] ?? '';
    if ($src === 'ami') $w[] = 'o.referrer_order_id IS NOT NULL';
    elseif ($src === 'relance') $w[] = 'o.recovered_from IS NOT NULL';
    elseif ($src === 'groupe') $w[] = 'o.group_discount > 0';
    elseif (in_array($src, ['web', 'widget', 'revente', 'partenaire'], true)) { $w[] = 'o.channel = ?'; $p[] = $src; }
    if ($f['from'] && preg_match('~^\d{4}-\d{2}-\d{2}$~', $f['from'])) { $w[] = 'o.created_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
    if ($f['to'] && preg_match('~^\d{4}-\d{2}-\d{2}$~', $f['to'])) { $w[] = 'o.created_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
    return [implode(' AND ', $w), $p];
}

function orders_read_filters(): array
{
    return ['q' => trim((string)input('q')), 'st' => (string)input('st'), 'ev' => (int)input('ev'), 'm' => (string)input('m'),
        'tag' => mb_substr(trim((string)input('tag')), 0, 40), 'from' => (string)input('from'), 'to' => (string)input('to'),
        'rt' => (int)input('rt'), 'pr' => mb_substr(trim((string)input('pr')), 0, 64), 'src' => (string)input('src')];
}

function order_tags(?string $s): array { return array_values(array_filter(array_map('trim', explode(',', (string)$s)), 'strlen')); }

function clean_tag(string $t): string { return mb_substr(trim(str_replace(',', ' ', $t)), 0, 30); }

function orders_index(): string
{
    $org = require_org();
    $f = orders_read_filters();
    [$where, $p] = orders_filters((int)$org['id'], $f);
    $sort = array_key_exists((string)input('sort'), ORDER_SORTS) ? (string)input('sort') : 'date';
    $dir = input('dir') === 'asc' ? 'ASC' : 'DESC';
    $base = 'FROM orders o JOIN events e ON e.id = o.event_id WHERE ' . $where;
    $cols = 'o.*, e.name ev_name, (SELECT COALESCE(SUM(qty),0) FROM order_items i WHERE i.order_id = o.id) qty';

    if (input('csv')) {
        $ids = array_filter(array_map('intval', explode(',', (string)input('ids'))));
        $sql = "SELECT $cols $base" . ($ids ? ' AND o.id IN (' . implode(',', $ids) . ')' : '') . ' ORDER BY ' . ORDER_SORTS[$sort] . " $dir";
        orders_csv(all($sql, $p));
    }

    $sum = one("SELECT COUNT(*) n, COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.total - o.refunded_amount END),0) revenue,
                COALESCE(SUM(CASE WHEN o.status = 'paid' THEN (SELECT SUM(qty) FROM order_items i WHERE i.order_id = o.id) END),0) tickets $base", $p);
    $total = (int)$sum['n'];
    $pages = max(1, (int)ceil($total / ORDER_PER_PAGE));
    $page = max(1, min($pages, (int)input('page', 1)));
    $list = all("SELECT $cols $base ORDER BY " . ORDER_SORTS[$sort] . " $dir, o.id $dir LIMIT " . ORDER_PER_PAGE . ' OFFSET ' . (($page - 1) * ORDER_PER_PAGE), $p);
    $events = all('SELECT id, name FROM events WHERE organizer_id = ? ORDER BY starts_at DESC', [$org['id']]);
    $allTags = [];
    foreach (all("SELECT tags FROM orders WHERE organizer_id = ? AND tags IS NOT NULL AND tags <> ''", [$org['id']]) as $r) foreach (order_tags($r['tags']) as $t) $allTags[$t] = ($allTags[$t] ?? 0) + 1;
    arsort($allTags);
    $rateList = all('SELECT r.id, r.name, e.name ev_name FROM rates r JOIN events e ON e.id = r.event_id WHERE e.organizer_id = ?' . ($f['ev'] ? ' AND e.id = ' . (int)$f['ev'] : '') . ' ORDER BY e.starts_at DESC, r.sort, r.id', [$org['id']]);
    $promoList = array_column(all("SELECT DISTINCT promo_code FROM orders WHERE organizer_id = ? AND promo_code IS NOT NULL ORDER BY promo_code LIMIT 300", [$org['id']]), 'promo_code');
    $counts = ['refund_request' => (int)val("SELECT COUNT(*) FROM orders WHERE organizer_id = ? AND status = 'paid' AND refund_requested_at IS NOT NULL", [$org['id']])];
    return pro_page('Commandes', 'orders', compact('list', 'f', 'events', 'sort', 'dir', 'page', 'pages', 'total', 'sum', 'allTags', 'counts', 'rateList', 'promoList'), 'orders');
}

function orders_csv(array $list): never
{
    $rows = [['Référence', 'Date', 'Événement', 'Prénom', 'Nom', 'E-mail', 'Téléphone', 'Billets', 'Détail', 'Sous-total', 'Réduction', 'Code promo', 'Remise groupe', 'Frais', 'Total payé', 'Remboursé', 'Part organisateur', 'Vente', 'Paiement', 'Statut', 'Source', 'UTM source', 'Invité par', 'Étiquettes', 'Note interne']];
    foreach ($list as $o) $rows[] = [$o['ref'], $o['created_at'], $o['ev_name'], $o['first_name'], $o['last_name'], $o['email'], $o['phone'], $o['qty'],
        implode(' + ', array_map(fn($i) => $i['qty'] . ' × ' . $i['name'], all('SELECT qty, name FROM order_items WHERE order_id = ?', [$o['id']]))),
        money_input((int)$o['subtotal']), money_input((int)$o['discount']), (string)$o['promo_code'], money_input((int)($o['group_discount'] ?? 0)), money_input((int)$o['fees']), money_input((int)$o['total']), money_input((int)$o['refunded_amount']),
        money_input($o['method'] === 'online' ? (int)$o['organizer_amount'] : (int)$o['total'] - (int)$o['refunded_amount']), method_label($o['method']),
        provider_label($o['provider'] ?: $o['pay_method']), strip_tags(badge($o['status'])), (string)$o['channel'] . (!empty($o['recovered_from']) ? ' (relance)' : ''), (string)$o['utm_source'],
        !empty($o['referrer_order_id']) ? (string)val('SELECT ref FROM orders WHERE id = ?', [$o['referrer_order_id']]) : '', (string)$o['tags'], (string)$o['internal_note']];
    csv_download('commandes-' . date('Ymd') . '.csv', $rows);
}

/** Actions groupées sur une sélection (ou sur tout le résultat filtré). */
function orders_bulk(): never
{
    $org = require_org();
    $action = (string)input('bulk');
    if (input('all') === '1') {
        [$where, $p] = orders_filters((int)$org['id'], orders_read_filters());
        $ids = array_map('intval', array_column(all('SELECT o.id FROM orders o JOIN events e ON e.id = o.event_id WHERE ' . $where . ' LIMIT 5000', $p), 'id'));
    } else {
        $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
    }
    if (!$ids) back(['error' => 'Coche au moins une commande']);
    $in = implode(',', $ids);
    $orders = all("SELECT * FROM orders WHERE organizer_id = ? AND id IN ($in)", [$org['id']]);
    $n = 0; $fail = 0;
    switch ($action) {
        case 'tag':
        case 'untag':
            $tag = clean_tag((string)input('new_tag'));
            if ($tag === '') back(['error' => 'Donne un nom à l\'étiquette.']);
            foreach ($orders as $o) {
                $tags = order_tags($o['tags']);
                $new = $action === 'tag' ? array_values(array_unique([...$tags, $tag])) : array_values(array_diff($tags, [$tag]));
                if ($new !== $tags) { update('orders', ['tags' => $new ? mb_substr(implode(',', $new), 0, 255) : null], 'id = ?', [$o['id']]); $n++; }
            }
            Audit::log('bulk', ($action === 'tag' ? 'Étiquette « ' : 'Retrait de l\'étiquette « ') . $tag . ' » sur ' . $n . ' commande(s)');
            back(['success' => $action === 'tag' ? "Étiquette « $tag » posée sur $n commande(s)." : "Étiquette « $tag » retirée de $n commande(s)."]);
        case 'resend':
            foreach ($orders as $o) {
                if ($o['status'] !== 'paid') continue;
                try { Mailer::sendOrder((int)$o['id']) ? $n++ : $fail++; } catch (Throwable $e) { $fail++; }
            }
            Audit::log('bulk', "Billets renvoyés : $n commande(s)");
            back(['success' => "Billets renvoyés pour $n commande(s)." . ($fail ? " $fail envoi(s) impossible(s)." : '')]);
        case 'cancel':
            foreach ($orders as $o) {
                if ($o['status'] !== 'paid') continue;
                Tickets::cancelOrder($o, 'cancelled'); $n++;
                Audit::log('order.cancelled', 'Action groupée, sans remboursement', (int)$o['event_id'], (int)$o['id']);
            }
            back(['success' => "$n commande(s) annulée(s), billets désactivés (aucun remboursement)."]);
        case 'export':
            $f = orders_read_filters();
            redirect(url('/pro/commandes', array_filter($f) + ['csv' => 1, 'ids' => implode(',', $ids)]));
    }
    back(['error' => 'Action inconnue.']);
}

function order_view(string $id): string
{
    $org = require_org();
    $o = one('SELECT * FROM orders WHERE id = ? AND organizer_id = ?', [(int)$id, $org['id']]);
    if (!$o) abort(404);
    $ev = one('SELECT * FROM events WHERE id = ?', [$o['event_id']]);
    if (is_post()) order_action($o);
    $items = all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
    $tickets = all('SELECT * FROM tickets WHERE order_id = ? ORDER BY id', [$o['id']]);
    $answers = json_decode((string)$o['answers'], true) ?: [];
    $creator = $o['created_by'] ? val('SELECT name FROM users WHERE id = ?', [$o['created_by']]) : null;
    $history = all('SELECT a.*, u.name user_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.order_id = ? ORDER BY a.id DESC LIMIT 30', [$o['id']]);
    $refundable = max(0, (int)$o['total'] - (int)$o['refunded_amount']);
    $prev = val('SELECT id FROM orders WHERE organizer_id = ? AND id < ? AND status IN (\'paid\',\'refunded\',\'cancelled\') ORDER BY id DESC LIMIT 1', [$org['id'], $o['id']]);
    $next = val('SELECT id FROM orders WHERE organizer_id = ? AND id > ? AND status IN (\'paid\',\'refunded\',\'cancelled\') ORDER BY id ASC LIMIT 1', [$org['id'], $o['id']]);
    return view('layout_back', ['title' => 'Commande ' . $o['ref'], 'area' => 'pro', 'active' => 'orders',
        'crumbs' => [['Commandes', '/pro/commandes'], [$ev['name'], '/pro/evenements/' . $ev['id']]],
        'content' => view('pro/order_detail', compact('o', 'ev', 'items', 'tickets', 'answers', 'creator', 'history', 'refundable', 'prev', 'next'))]);
}

function order_action(array $o): never
{
    $a = (string)input('action');
    $evId = (int)$o['event_id']; $oid = (int)$o['id'];
    switch ($a) {
        case 'resend':
            if (input('email') && is_email((string)input('email')) && input('email') !== $o['email']) {
                update('orders', ['email' => trim((string)input('email'))], 'id = ?', [$oid]);
                Audit::log('order.updated', 'E-mail : ' . $o['email'] . ' → ' . input('email'), $evId, $oid);
            }
            try {
                if (!Mailer::sendOrder($oid)) back(['error' => 'Pas d\'e-mail valide ou pas de billet à envoyer.']);
                Audit::log('order.resent', (string)val('SELECT email FROM orders WHERE id = ?', [$oid]), $evId, $oid);
                back(['success' => 'Billets renvoyés !']);
            } catch (Throwable $e) { back(['error' => 'Envoi impossible : ' . $e->getMessage()]); }
        case 'buyer':
            $d = ['first_name' => mb_substr(trim((string)input('first_name')), 0, 80), 'last_name' => mb_substr(trim((string)input('last_name')), 0, 80),
                'email' => trim((string)input('email')), 'phone' => mb_substr(trim((string)input('phone')), 0, 40) ?: null];
            if ($d['first_name'] === '' || $d['last_name'] === '') back(['error' => 'Prénom et nom obligatoires.']);
            if ($d['email'] !== '' && !is_email($d['email'])) back(['error' => 'Cet e-mail a l\'air bancal']);
            $changes = [];
            $fl = ['first_name' => 'Prénom', 'last_name' => 'Nom', 'email' => 'E-mail', 'phone' => 'Téléphone'];
            foreach ($d as $k => $v) if ((string)$o[$k] !== (string)$v) $changes[] = $fl[$k] . ' : ' . ($o[$k] ?: '—') . ' → ' . ($v ?: '—');
            if ($changes) {
                update('orders', $d, 'id = ?', [$oid]);
                if (input('sync_tickets')) q('UPDATE tickets SET first_name = ?, last_name = ?, updated_at = NOW() WHERE order_id = ? AND first_name = ? AND last_name = ?', [$d['first_name'], $d['last_name'], $oid, $o['first_name'], $o['last_name']]);
                Audit::log('order.updated', implode(' · ', $changes), $evId, $oid);
            }
            back(['success' => $changes ? 'Acheteur mis à jour.' : 'Rien n\'a changé']);
        case 'meta':
            $tags = array_values(array_unique(array_filter(array_map('clean_tag', explode(',', (string)input('tags'))), 'strlen')));
            $note = mb_substr(trim((string)input('internal_note')), 0, 5000);
            update('orders', ['tags' => $tags ? mb_substr(implode(',', $tags), 0, 255) : null, 'internal_note' => $note !== '' ? $note : null], 'id = ?', [$oid]);
            if ((string)$o['tags'] !== implode(',', $tags) || (string)$o['internal_note'] !== $note) Audit::log('order.updated', 'Étiquettes / note interne', $evId, $oid);
            back(['success' => 'Enregistré.']);
        case 'holder':
            $t = one('SELECT * FROM tickets WHERE id = ? AND order_id = ?', [(int)input('ticket_id'), $oid]);
            if ($t) {
                $fn = trim((string)input('first_name')); $ln = trim((string)input('last_name'));
                update('tickets', ['first_name' => $fn, 'last_name' => $ln, 'updated_at' => now()], 'id = ?', [$t['id']]);
                Audit::log('ticket.renamed', $t['code'] . ' : ' . trim($t['first_name'] . ' ' . $t['last_name']) . ' → ' . $fn . ' ' . $ln, $evId, $oid);
            }
            back(['success' => 'Nom modifié.']);
        case 'cancel_ticket':
            $t = one("SELECT * FROM tickets WHERE id = ? AND order_id = ? AND status = 'valid'", [(int)input('ticket_id'), $oid]);
            if ($t) {
                q("UPDATE tickets SET status = 'cancelled', inside = 0, updated_at = NOW() WHERE id = ?", [$t['id']]);
                Audit::log('ticket.cancelled', $t['code'] . ' (' . $t['rate_name'] . ')', $evId, $oid);
            }
            back(['success' => 'Billet annulé (pas de remboursement automatique).']);
        case 'partial_refund':
            order_partial_refund($o);
        case 'cancel':
        case 'refund':
            if ($o['status'] !== 'paid') back();
            $msg = 'Commande annulée, billets désactivés.';
            $amount = 0;
            if ($a === 'refund') {
                $full = setting('refund_fees') === '1' || $o['fee_mode'] !== 'buyer' ? (int)$o['total'] : (int)$o['total'] - (int)$o['fees'];
                $amount = max(0, $full - (int)$o['refunded_amount']);
                if ($o['method'] === 'online') {
                    try { $msg = Payments::refund($o, $amount) . ' (' . money($amount) . '). Billets invalidés.'; }
                    catch (Throwable $e) { back(['error' => 'Le remboursement a échoué : ' . $e->getMessage() . ' — la commande n\'a pas été annulée.']); }
                } else $msg = 'Commande marquée remboursée (' . money($amount) . ' à rendre à l\'acheteur). Billets invalidés.';
                update('orders', ['refunded_amount' => (int)$o['refunded_amount'] + $amount,
                    'organizer_amount' => max(0, (int)$o['organizer_amount'] - $amount)], 'id = ?', [$oid]);
            }
            Tickets::cancelOrder($o, $a === 'refund' ? 'refunded' : 'cancelled');
            Audit::log($a === 'refund' ? 'order.refunded' : 'order.cancelled', $a === 'refund' ? money($amount) : 'Sans remboursement', $evId, $oid);
            back(['success' => $msg]);
    }
    back();
}

/**
 * Remboursement partiel : un montant libre (≤ restant) et, au choix, les billets à annuler.
 * La plateforme garde sa commission ; la part organisateur baisse du montant remboursé.
 */
function order_partial_refund(array $o): never
{
    if ($o['status'] !== 'paid') back(['error' => 'Seule une commande payée peut être remboursée.']);
    $left = (int)$o['total'] - (int)$o['refunded_amount'];
    $amount = parse_money((string)input('amount'));
    $ticketIds = array_values(array_filter(array_map('intval', (array)($_POST['tickets'] ?? []))));
    if ($amount <= 0 && !$ticketIds) back(['error' => 'Indique un montant ou coche des billets']);
    if ($amount > $left) back(['error' => 'Tu ne peux pas rembourser plus que ' . money($left) . '.']);
    $msg = '';
    if ($amount > 0) {
        if ($o['method'] === 'online') {
            try { $msg = Payments::refund($o, $amount); }
            catch (Throwable $e) { back(['error' => 'Le remboursement a échoué : ' . $e->getMessage() . ' — rien n\'a été modifié.']); }
        } else $msg = 'À rendre en direct à l\'acheteur';
    }
    $cancelled = [];
    if ($ticketIds) {
        $in = implode(',', $ticketIds);
        $cancelled = all("SELECT code, rate_name FROM tickets WHERE order_id = ? AND status = 'valid' AND id IN ($in)", [$o['id']]);
        q("UPDATE tickets SET status = 'cancelled', inside = 0, updated_at = NOW() WHERE order_id = ? AND status = 'valid' AND id IN ($in)", [$o['id']]);
    }
    $refunded = (int)$o['refunded_amount'] + $amount;
    $up = ['refunded_amount' => $refunded, 'organizer_amount' => max(0, (int)$o['organizer_amount'] - $amount)];
    $stillValid = (int)val("SELECT COUNT(*) FROM tickets WHERE order_id = ? AND status = 'valid'", [$o['id']]);
    if ($refunded >= (int)$o['total'] && !$stillValid) { $up['status'] = 'refunded'; $up['cancelled_at'] = now(); }
    update('orders', $up, 'id = ?', [$o['id']]);
    $detail = ($amount ? money($amount) : 'Sans montant') . ($cancelled ? ' · billets annulés : ' . implode(', ', array_map(fn($t) => $t['code'], $cancelled)) : '');
    Audit::log('order.partial_refund', $detail, (int)$o['event_id'], (int)$o['id']);
    back(['success' => 'Remboursement partiel enregistré : ' . $detail . ($msg ? ' — ' . rtrim($msg, '.') . '.' : '.')]);
}
