<?php
declare(strict_types=1);

/* 2.2 : super-admin › litiges & impayés. */

require_once APP . '/controllers/admin.php';

function admin_disputes(): string
{
    $u = require_super();
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'create') {
            $o = one('SELECT * FROM orders WHERE ref = ?', [strtoupper(trim((string)input('order_ref')))]);
            if (!$o || !in_array($o['status'], ['paid', 'refunded'], true)) back(['error' => 'Commande payée introuvable avec cette référence.']);
            $amount = trim((string)input('amount')) === '' ? (int)$o['total'] : parse_money(input('amount'));
            if ($amount <= 0 || $amount > (int)$o['total']) back(['error' => 'Le montant contesté doit être compris entre 0 et ' . money((int)$o['total']) . '.']);
            $id = Disputes::create($o, $amount, array_key_exists((string)input('reason'), Disputes::REASONS) ? (string)input('reason') : 'autre', input('due_on') ?: null, (int)$u['id'],
                ['bank_fee' => parse_money(input('bank_fee')), 'provider_ref' => trim((string)input('provider_ref')) ?: null]);
            redirect('/admin/litiges/' . $id, ['success' => 'Litige ouvert, l\'organisateur est prévenu.']);
        }
        if ($a === 'settings') { set_setting('dispute_fee', (string)parse_money(input('dispute_fee'))); back(['success' => 'Frais par défaut enregistrés.']); }
    }
    $f = (string)input('f', 'ouverts');
    $where = match ($f) { 'clos' => "d.status IN ('won','lost','accepted')", 'tous' => '1', default => "d.status IN ('open','evidence')" };
    $list = all("SELECT d.*, o.ref, o.email buyer, o.total order_total, g.name org_name, e.name ev_name FROM disputes d LEFT JOIN orders o ON o.id = d.order_id
        JOIN organizers g ON g.id = d.organizer_id LEFT JOIN events e ON e.id = o.event_id WHERE $where ORDER BY d.status = 'open' DESC, d.due_on IS NULL, d.due_on, d.id DESC LIMIT 300");
    $k = Disputes::kpis();
    $volume = (int)val("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status IN ('paid','refunded') AND method = 'online' AND paid_at >= NOW() - INTERVAL 12 MONTH");
    $rate = $volume ? (int)val("SELECT COALESCE(SUM(amount), 0) FROM disputes WHERE created_at >= NOW() - INTERVAL 12 MONTH") / $volume * 100 : 0;
    $byOrg = all("SELECT g.id, g.name, COUNT(*) n, SUM(d.amount) amount FROM disputes d JOIN organizers g ON g.id = d.organizer_id WHERE d.created_at >= NOW() - INTERVAL 12 MONTH GROUP BY g.id ORDER BY n DESC LIMIT 5");
    return admin_page('Litiges & impayés', 'disputes', compact('list', 'k', 'f', 'rate', 'byOrg'), 'litiges');
}

function admin_dispute(string $id): string
{
    $u = require_super();
    $d = one('SELECT * FROM disputes WHERE id = ?', [(int)$id]);
    if (!$d) abort(404);
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'status') { Disputes::setStatus($d, (string)input('status'), (int)$u['id']); back(['success' => 'Statut mis à jour.' . (in_array(input('status'), ['lost', 'accepted'], true) ? ' Le montant sera retenu sur le prochain reversement.' : '')]); }
        if ($a === 'regen') { update('disputes', ['evidence' => Disputes::evidence(one('SELECT * FROM orders WHERE id = ?', [$d['order_id']]))], 'id = ?', [$d['id']]); back(['success' => 'Dossier de preuves régénéré.']); }
        update('disputes', ['evidence' => trim((string)input('evidence')) ?: null, 'notes' => trim((string)input('notes')) ?: null, 'due_on' => input('due_on') ?: null,
            'bank_fee' => parse_money(input('bank_fee')), 'reason' => array_key_exists((string)input('reason'), Disputes::REASONS) ? (string)input('reason') : $d['reason']], 'id = ?', [$d['id']]);
        back(['success' => 'Litige enregistré.']);
    }
    $o = $d['order_id'] ? one('SELECT * FROM orders WHERE id = ?', [$d['order_id']]) : null;
    $org = one('SELECT * FROM organizers WHERE id = ?', [$d['organizer_id']]);
    $ev = $o && $o['event_id'] ? one('SELECT * FROM events WHERE id = ?', [$o['event_id']]) : null;
    $tickets = $o ? all('SELECT * FROM tickets WHERE order_id = ?', [$o['id']]) : [];
    $other = $o ? all('SELECT d.id, d.status, d.amount, o.ref FROM disputes d JOIN orders o ON o.id = d.order_id WHERE LOWER(o.email) = LOWER(?) AND d.id <> ?', [$o['email'], $d['id']]) : [];
    return admin_page('Litige #' . $d['id'], 'dispute', compact('d', 'o', 'org', 'ev', 'tickets', 'other'), 'litiges', ['crumbs' => [['Litiges & impayés', '/admin/litiges']]]);
}
