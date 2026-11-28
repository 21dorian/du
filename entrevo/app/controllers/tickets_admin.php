<?php
declare(strict_types=1);

/* 4.4 : fiche billet (participant) et stock de l'événement (disponibilités, blocages de places, limite par acheteur). */

require_once APP . '/controllers/pro.php';

function ticket_page(string $id): string
{
    $org = require_org();
    $t = one('SELECT t.* FROM tickets t JOIN events e ON e.id = t.event_id WHERE t.id = ? AND e.organizer_id = ?', [(int)$id, $org['id']]) ?: abort(404);
    $o = one('SELECT * FROM orders WHERE id = ?', [$t['order_id']]);
    $ev = one('SELECT * FROM events WHERE id = ?', [$t['event_id']]);
    $base = '/pro/billets/' . $t['id'];
    if (is_post()) {
        $a = (string)input('action');
        try {
            switch ($a) {
                case 'holder':
                    $fn = mb_substr(trim((string)input('first_name')), 0, 80); $ln = mb_substr(trim((string)input('last_name')), 0, 80);
                    $em = trim((string)input('holder_email'));
                    if ($em !== '' && !is_email($em)) throw new RuntimeException('Adresse e-mail invalide.');
                    update('tickets', ['first_name' => $fn, 'last_name' => $ln, 'holder_email' => $em !== '' ? strtolower($em) : null, 'updated_at' => now()], 'id = ?', [$t['id']]);
                    Audit::log('ticket.holder', $t['code'] . ' : ' . trim($t['first_name'] . ' ' . $t['last_name']) . ' → ' . trim($fn . ' ' . $ln), (int)$ev['id'], (int)$o['id']);
                    back(['success' => 'Participant mis à jour.']);
                case 'reissue': // nouveau code : l'ancien billet (imprimé, transféré, perdu) est refusé à l'entrée
                    if ($t['status'] !== 'valid') throw new RuntimeException('Seul un billet valide peut être réédité.');
                    $code = Tickets::newCode();
                    update('tickets', ['code' => $code, 'updated_at' => now()], 'id = ?', [$t['id']]);
                    insert('ticket_transfers', ['ticket_id' => $t['id'], 'order_id' => $o['id'], 'event_id' => $ev['id'], 'from_name' => 'Réédition', 'from_email' => (string)(user()['email'] ?? ''),
                        'to_name' => trim($t['first_name'] . ' ' . $t['last_name']), 'to_email' => (string)($t['holder_email'] ?: $o['email']), 'old_code' => $t['code'], 'created_at' => now()]);
                    Audit::log('ticket.reissued', $t['code'] . ' → ' . $code . ' · ' . mb_substr(trim((string)input('reason')), 0, 120), (int)$ev['id'], (int)$o['id']);
                    if (checked('send')) ticket_send(one('SELECT * FROM tickets WHERE id = ?', [$t['id']]), $o, $ev);
                    back(['success' => 'Billet réédité : nouveau code ' . $code . '. L\'ancien sera refusé à l\'entrée.' . (checked('send') ? ' Nouveau billet envoyé.' : '')]);
                case 'transfer':
                    $em = trim((string)input('email')); $fn = trim((string)input('first_name')); $ln = trim((string)input('last_name'));
                    if (!is_email($em) || $fn === '') throw new RuntimeException('Indique le prénom et l\'e-mail de la personne.');
                    if ($t['status'] !== 'valid') throw new RuntimeException('Ce billet n\'est plus valable.');
                    Buyers::transfer($t, $o, $ev, $fn, $ln, $em, (string)($org['name']), (string)($o['email']));
                    back(['success' => 'Billet transféré à ' . trim($fn . ' ' . $ln) . ' : nouveau code envoyé par e-mail.']);
                case 'resend':
                    $to = trim((string)input('email')) ?: (string)($t['holder_email'] ?: $o['email']);
                    if (!is_email($to)) throw new RuntimeException('Adresse e-mail invalide.');
                    ticket_send($t, $o, $ev, $to);
                    back(['success' => 'Billet envoyé à ' . $to . '.']);
                case 'checkin': case 'checkout':
                    $r = Tickets::scan(['event' => $ev, 'code' => $t['code'], 'direction' => $a === 'checkin' ? 'in' : 'out', 'user' => user(), 'uuid' => bin2hex(random_bytes(8)), 'device' => 'back-office',
                        'session_id' => $t['session_id'] ?: null, 'force' => true]);
                    back([$r['result'] === 'ok' || $r['result'] === 'exit' ? 'success' : 'warning' => (Tickets::LABELS[$r['result']] ?? $r['result']) . '.']);
                case 'cancel':
                    if ($t['status'] !== 'valid') throw new RuntimeException('Billet déjà annulé.');
                    q("UPDATE tickets SET status = 'cancelled', inside = 0, updated_at = NOW() WHERE id = ?", [$t['id']]);
                    Audit::log('ticket.cancelled', $t['code'] . ' · ' . mb_substr(trim((string)input('reason')), 0, 120), (int)$ev['id'], (int)$o['id']);
                    back(['success' => 'Billet annulé (sans remboursement : pour rembourser, passe par la commande).']);
                case 'reactivate':
                    if ($t['status'] === 'valid') throw new RuntimeException('Le billet est déjà valable.');
                    if ($o['status'] !== 'paid') throw new RuntimeException('La commande n\'est plus payée : impossible de réactiver ce billet.');
                    $rate = one('SELECT * FROM rates WHERE id = ?', [$t['rate_id']]);
                    $session = $t['session_id'] ? one('SELECT * FROM sessions WHERE id = ?', [$t['session_id']]) : null;
                    if ($rate && Tickets::remaining($rate, $ev, $session) < 1 && !checked('force')) throw new RuntimeException('Plus de place sur ce tarif : coche « dépasser la jauge » pour réactiver quand même.');
                    update('tickets', ['status' => 'valid', 'updated_at' => now()], 'id = ?', [$t['id']]);
                    Audit::log('ticket.reactivated', $t['code'], (int)$ev['id'], (int)$o['id']);
                    back(['success' => 'Billet réactivé.']);
                case 'session':
                    back(['success' => OrderOps::changeSession($o, [(int)$t['id']], (int)input('session_id'), (bool)checked('resend'))]);
            }
        } catch (RuntimeException $e) { back(['error' => $e->getMessage()]); }
        back();
    }
    $scans = all('SELECT s.*, ap.name ap, u.name who FROM scans s LEFT JOIN access_points ap ON ap.id = s.access_point_id LEFT JOIN users u ON u.id = s.user_id WHERE s.ticket_id = ? ORDER BY s.id DESC LIMIT 50', [$t['id']]);
    $transfers = all('SELECT * FROM ticket_transfers WHERE ticket_id = ? ORDER BY id DESC', [$t['id']]);
    $history = all("SELECT a.*, u.name user_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.order_id = ? AND (a.details LIKE ? OR a.details LIKE ?) ORDER BY a.id DESC LIMIT 20", [$o['id'], '%' . $t['code'] . '%', '%' . ($transfers[0]['old_code'] ?? '~none~') . '%']);
    $sessions = Tickets::sessions((int)$ev['id'], false);
    $answers = json_decode((string)$t['answers'], true) ?: [];
    $session = $t['session_id'] ? one('SELECT * FROM sessions WHERE id = ?', [$t['session_id']]) : null;
    $wallet = one('SELECT id, code, balance, nfc_uid FROM wallets WHERE ticket_id = ?', [$t['id']]);
    return ev_page($ev, 'attendees', trim($t['first_name'] . ' ' . $t['last_name']) ?: 'Billet ' . $t['code'], 'ticket_detail', compact('t', 'o', 'ev', 'scans', 'transfers', 'history', 'sessions', 'answers', 'session', 'wallet', 'base'));
}

/** Envoie un billet seul (PDF) au porteur ou à l'adresse donnée. */
function ticket_send(array $t, array $o, array $ev, ?string $to = null): void
{
    $to = $to ?: (string)($t['holder_email'] ?: $o['email']);
    if (!is_email($to)) return;
    $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
    $pdf = TicketPdf::build($o, $ev, $org, [$t]);
    Mailer::send($to, 'Ton billet pour ' . $ev['name'], '<p>Bonjour ' . e((string)$t['first_name']) . ',</p><p>Voici ton billet « ' . e($t['rate_name']) . ' » pour <b>' . e($ev['name']) . '</b> (' . e(fr_date($ev['starts_at'])) . '), en pièce jointe.</p><p>Présente le QR code à l\'entrée, imprimé ou sur ton téléphone.</p><p style="color:#86868b">' . e($org['name']) . '</p>',
        [['data' => $pdf, 'name' => 'billet-' . $t['code'] . '.pdf']], $org['email'] ?? null);
}

/** Stock de l'événement : capacité, vendus, paniers en cours, bloqués, disponibles ; blocages et limite par acheteur. */
function stock_page(string $id): string
{
    $org = require_org();
    $ev = org_event((int)$id);
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'hold') {
            $qty = (int)input('qty'); $label = mb_substr(trim((string)input('label')), 0, 120);
            $rid = (int)input('rate_id') ?: null; $sid = (int)input('session_id') ?: null;
            if ($qty < 1 || $label === '') back(['error' => 'Indique un nombre de places et à qui elles sont réservées.']);
            if ($rid && !val('SELECT id FROM rates WHERE id = ? AND event_id = ?', [$rid, $ev['id']])) back();
            if ($rid) { $r = one('SELECT * FROM rates WHERE id = ?', [$rid]); $s = $sid ? one('SELECT * FROM sessions WHERE id = ?', [$sid]) : null;
                if (Tickets::remaining($r, $ev, $s) < $qty) back(['error' => 'Pas assez de places libres sur ce tarif pour en bloquer ' . $qty . '.']); }
            insert('place_holds', ['event_id' => $ev['id'], 'rate_id' => $rid, 'session_id' => $sid, 'qty' => $qty, 'label' => $label, 'note' => mb_substr(trim((string)input('note')), 0, 255) ?: null, 'user_id' => user()['id'], 'created_at' => now()]);
            Audit::log('stock.hold', $qty . ' place(s) bloquée(s) · ' . $label, (int)$ev['id']);
            back(['success' => $qty . ' place(s) bloquée(s) pour « ' . $label . ' » : elles ne sont plus en vente.']);
        }
        $h = one('SELECT * FROM place_holds WHERE id = ? AND event_id = ? AND released_at IS NULL', [(int)input('hold_id'), $ev['id']]);
        if ($a === 'release' && $h) {
            $n = max(1, min((int)$h['qty'], (int)input('qty') ?: (int)$h['qty']));
            if ($n >= (int)$h['qty']) update('place_holds', ['released_at' => now()], 'id = ?', [$h['id']]); else update('place_holds', ['qty' => (int)$h['qty'] - $n], 'id = ?', [$h['id']]);
            Audit::log('stock.release', $n . ' place(s) libérée(s) · ' . $h['label'], (int)$ev['id']);
            back(['success' => $n . ' place(s) remise(s) en vente.']);
        }
        if ($a === 'invite' && $h) { // transformer un blocage en invitations nominatives
            $name = trim((string)input('name')); $email = trim((string)input('email'));
            $n = max(1, min((int)$h['qty'], (int)input('qty') ?: 1));
            if ($name === '') back(['error' => 'Indique le nom de la personne invitée.']);
            if (!$h['rate_id']) back(['error' => 'Ce blocage ne vise pas un tarif précis : crée l\'invitation depuis le guichet.']);
            $parts = explode(' ', $name, 2);
            if ($n >= (int)$h['qty']) update('place_holds', ['released_at' => now()], 'id = ?', [$h['id']]); else update('place_holds', ['qty' => (int)$h['qty'] - $n], 'id = ?', [$h['id']]);
            $session = $h['session_id'] ? one('SELECT * FROM sessions WHERE id = ?', [$h['session_id']]) : null;
            $r = Tickets::createOrder(['event' => $ev, 'org' => $org, 'session' => $session, 'seats' => [], 'quantities' => [(int)$h['rate_id'] => $n], 'buyer' => ['first_name' => $parts[0], 'last_name' => $parts[1] ?? '', 'email' => is_email($email) ? $email : '', 'phone' => ''],
                'method' => 'invitation', 'channel' => 'invitation', 'note' => 'Sur le blocage « ' . $h['label'] . ' »', 'user_id' => user()['id']]);
            if (!empty($r['errors'])) { update('place_holds', ['released_at' => null, 'qty' => (int)$h['qty']], 'id = ?', [$h['id']]); back(['error' => implode(' ', $r['errors'])]); }
            $o = Tickets::confirm((int)$r['order']['id'], [], is_email($email));
            Audit::log('stock.invite', $n . ' invitation(s) pour ' . $name . ' sur « ' . $h['label'] . ' »', (int)$ev['id'], (int)$o['id']);
            back(['success' => $n . ' invitation(s) créée(s) pour ' . $name . (is_email($email) ? ', envoyée(s) par e-mail' : '') . '.']);
        }
        if ($a === 'limit') {
            update('events', ['max_per_buyer' => max(0, min(1000, (int)input('max_per_buyer')))], 'id = ?', [$ev['id']]);
            back(['success' => (int)input('max_per_buyer') ? 'Limite enregistrée : ' . (int)input('max_per_buyer') . ' billets maximum par acheteur.' : 'Pas de limite par acheteur.']);
        }
        back();
    }
    $sessions = Tickets::sessions((int)$ev['id'], false);
    $scope = $sessions ? $sessions : [null];
    $rates = all('SELECT * FROM rates WHERE event_id = ? ORDER BY sort, id', [$ev['id']]);
    $grid = [];
    foreach ($scope as $s) {
        $sid = $s ? (int)$s['id'] : null; $ss = $sid ? ' AND t.session_id = ' . $sid : ''; $os = $sid ? ' AND o.session_id = ' . $sid : '';
        $rows = [];
        foreach ($rates as $r) {
            $sold = (int)val("SELECT COUNT(*) FROM tickets t WHERE t.rate_id = ? AND t.status = 'valid'" . $ss, [$r['id']]);
            $cart = (int)val("SELECT COALESCE(SUM(i.qty * GREATEST(1, r.pack_size)), 0) FROM order_items i JOIN orders o ON o.id = i.order_id JOIN rates r ON r.id = i.rate_id WHERE i.kind = 'rate' AND i.rate_id = ? AND o.status = 'pending' AND o.expires_at > NOW()" . $os, [$r['id']]);
            $blocked = Tickets::blocked(null, (int)$r['id'], $sid);
            $left = Tickets::remaining($r, $ev, $s);
            $rows[] = ['rate' => $r, 'sold' => $sold, 'cart' => $cart, 'blocked' => $blocked, 'left' => $left === PHP_INT_MAX ? null : $left];
        }
        $cap = Tickets::capacity($ev, $s);
        $grid[] = ['session' => $s, 'rows' => $rows, 'cap' => $cap, 'used' => Tickets::eventUsed((int)$ev['id'], $sid), 'blocked_all' => Tickets::blocked((int)$ev['id'], null, $sid)];
    }
    $holds = all('SELECT h.*, r.name rate_name, s.starts_at, u.name who FROM place_holds h LEFT JOIN rates r ON r.id = h.rate_id LEFT JOIN sessions s ON s.id = h.session_id LEFT JOIN users u ON u.id = h.user_id WHERE h.event_id = ? AND h.released_at IS NULL ORDER BY h.id DESC', [$ev['id']]);
    return ev_page($ev, 'stock', 'Stock et blocages', 'stock', compact('ev', 'grid', 'holds', 'rates', 'sessions'));
}
