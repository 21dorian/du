<?php
declare(strict_types=1);

/* 2.2 : support — formulaire public, page de suivi, espace « Aide » organisateur, file super-admin. */

require_once APP . '/controllers/public.php';

// ---------- Public ----------
function contact_page(): string
{
    $errors = []; $done = null;
    $u = user();
    $v = ['name' => $u['name'] ?? '', 'email' => $u['email'] ?? '', 'category' => (string)input('sujet', 'billets'), 'order_ref' => (string)input('commande'), 'subject' => '', 'message' => ''];
    if (is_post()) {
        foreach ($v as $k => $_) $v[$k] = trim((string)input($k));
        if (trim((string)input('website')) !== '') $errors[] = 'Requête refusée.';
        if (!is_email(strtolower($v['email']))) $errors[] = 'Ton adresse e-mail a l\'air incorrecte.';
        if (mb_strlen($v['message']) < 15) $errors[] = 'Décris ta demande en quelques mots (15 caractères minimum).';
        if (!throttle_ok_pub('contact-' . ($_SERVER['REMOTE_ADDR'] ?? ''))) $errors[] = 'Trop de messages d\'un coup : réessaie dans quelques minutes.';
        if (!$errors) {
            $orgId = null;
            if ($v['order_ref'] !== '') $orgId = val('SELECT organizer_id FROM orders WHERE ref = ? AND LOWER(email) = LOWER(?)', [strtoupper($v['order_ref']), $v['email']]) ?: null;
            $t = Support::create(['name' => $v['name'], 'email' => $v['email'], 'subject' => $v['subject'] ?: (Support::CATEGORIES[$v['category']] ?? 'Demande'), 'category' => $v['category'],
                'order_ref' => $v['order_ref'], 'organizer_id' => $orgId, 'user_id' => $u['id'] ?? null], $v['message']);
            $done = $t;
        }
    }
    return pub('contact', compact('v', 'errors', 'done'), 'Contact');
}

function support_thread(string $token): string
{
    $t = one('SELECT * FROM support_tickets WHERE token = ?', [$token]);
    if (!$t || strlen($token) < 20) abort(404);
    if (is_post()) {
        $body = trim((string)input('message'));
        if (input('action') === 'solved') { Support::reply($t, 'note', 'La personne a indiqué que sa demande est résolue.', null, 'solved'); redirect('/support/' . $token, ['success' => 'Merci ! Demande close. Tu peux toujours répondre pour la rouvrir.']); }
        if (mb_strlen($body) < 2) redirect('/support/' . $token, ['error' => 'Écris ton message.']);
        Support::reply($t, 'customer', $body);
        redirect('/support/' . $token, ['success' => 'Message envoyé.']);
    }
    $msgs = Support::messages($t, false);
    return pub('support_thread', compact('t', 'msgs'), 'Demande ' . $t['ref']);
}

// ---------- Organisateur ----------
function pro_help(): string
{
    require_once APP . '/controllers/pro.php';
    $org = require_org(['owner', 'manager']);
    $u = user();
    if (is_post()) {
        $msg = trim((string)input('message'));
        if (trim((string)input('subject')) === '' || mb_strlen($msg) < 10) back(['error' => 'Indique un sujet et décris ta demande.']);
        $t = Support::create(['name' => $u['name'], 'email' => $u['email'], 'subject' => (string)input('subject'), 'category' => (string)input('category'), 'priority' => (string)input('priority'),
            'order_ref' => (string)input('order_ref'), 'organizer_id' => $org['id'], 'user_id' => $u['id']], $msg);
        redirect('/pro/aide/' . $t['ref'], ['success' => 'Demande envoyée. Tu reçois les réponses ici et par e-mail.']);
    }
    $list = all('SELECT t.*, (SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id = t.id AND m.author <> \'note\') n FROM support_tickets t WHERE organizer_id = ? ORDER BY updated_at DESC LIMIT 100', [$org['id']]);
    return pro_page('Aide & support', 'help', ['list' => $list, 'subject' => (string)input('sujet'), 'org' => $org], 'aide');
}

function pro_help_ticket(string $ref): string
{
    require_once APP . '/controllers/pro.php';
    $org = require_org(['owner', 'manager']);
    $t = one('SELECT * FROM support_tickets WHERE ref = ? AND organizer_id = ?', [$ref, $org['id']]);
    if (!$t) abort(404);
    if (is_post()) {
        $body = trim((string)input('message'));
        if (input('action') === 'solved') { Support::reply($t, 'note', 'L\'organisateur a indiqué que sa demande est résolue.', (int)user()['id'], 'solved'); back(['success' => 'Demande close.']); }
        if (mb_strlen($body) < 2) back(['error' => 'Écris ton message.']);
        Support::reply($t, 'customer', $body, (int)user()['id']);
        back(['success' => 'Message envoyé.']);
    }
    return pro_page('Demande ' . $t['ref'], 'help_ticket', ['t' => $t, 'msgs' => Support::messages($t, false)], 'aide', ['crumbs' => [['Aide & support', '/pro/aide']]]);
}

// ---------- Super-admin ----------
function admin_support(): string
{
    require_once APP . '/controllers/admin.php';
    $u = require_super();
    if (is_post() && input('action') === 'macros') {
        $m = [];
        foreach ((array)($_POST['m_title'] ?? []) as $i => $title) { $title = trim((string)$title); $b = trim((string)($_POST['m_body'][$i] ?? '')); if ($title !== '' && $b !== '') $m[] = [mb_substr($title, 0, 80), mb_substr($b, 0, 3000)]; }
        set_setting('support_macros', json_encode($m, JSON_UNESCAPED_UNICODE));
        set_setting('support_email', trim((string)input('support_email')));
        back(['success' => 'Réglages du support enregistrés.']);
    }
    $f = (string)input('f', 'actives');
    $q = trim((string)input('q'));
    $w = match ($f) { 'moi' => "t.status <> 'solved' AND t.assigned_to = " . (int)$u['id'], 'attente' => "t.status = 'pending'", 'resolues' => "t.status = 'solved'", 'toutes' => '1', default => "t.status = 'open'" };
    $p = [];
    if ($q !== '') { $w .= ' AND (t.ref LIKE ? OR t.email LIKE ? OR t.subject LIKE ? OR t.order_ref LIKE ?)'; $p = array_fill(0, 4, '%' . $q . '%'); }
    $list = all("SELECT t.*, g.name org_name, u.name agent, (SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id = t.id AND m.author <> 'note') n,
        (SELECT author FROM support_messages m WHERE m.ticket_id = t.id AND m.author <> 'note' ORDER BY m.id DESC LIMIT 1) last_author
        FROM support_tickets t LEFT JOIN organizers g ON g.id = t.organizer_id LEFT JOIN users u ON u.id = t.assigned_to WHERE $w
        ORDER BY FIELD(t.priority, 'urgent', 'high', 'normal', 'low'), t.updated_at " . ($f === 'resolues' || $f === 'toutes' ? 'DESC' : 'ASC') . ' LIMIT 300', $p);
    return admin_page('Support', 'support', ['list' => $list, 'k' => Support::kpis(), 'f' => $f, 'q' => $q, 'me' => $u], 'support');
}

function admin_support_ticket(string $id): string
{
    require_once APP . '/controllers/admin.php';
    $u = require_super();
    $t = one('SELECT * FROM support_tickets WHERE id = ?', [(int)$id]);
    if (!$t) abort(404);
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'meta') {
            update('support_tickets', ['priority' => array_key_exists((string)input('priority'), Support::PRIORITY) ? (string)input('priority') : $t['priority'],
                'category' => array_key_exists((string)input('category'), Support::CATEGORIES) ? (string)input('category') : $t['category'],
                'assigned_to' => (int)input('assigned_to') ?: null, 'organizer_id' => (int)input('organizer_id') ?: null, 'updated_at' => now()], 'id = ?', [$t['id']]);
            back(['success' => 'Demande mise à jour.']);
        }
        if ($a === 'status') { Support::reply($t, 'note', 'Statut : ' . (Support::STATUS[(string)input('status')][0] ?? ''), (int)$u['id'], (string)input('status')); back(); }
        $body = trim((string)input('message'));
        if ($body === '') back(['error' => 'Le message est vide.']);
        $isNote = $a === 'note';
        Support::reply($t, $isNote ? 'note' : 'staff', $body, (int)$u['id'], $isNote ? null : (input('then') === 'solved' ? 'solved' : 'pending'));
        if (!$t['assigned_to']) update('support_tickets', ['assigned_to' => $u['id']], 'id = ?', [$t['id']]);
        back(['success' => $isNote ? 'Note ajoutée (invisible pour la personne).' : 'Réponse envoyée par e-mail.']);
    }
    $msgs = Support::messages($t, true);
    $org = $t['organizer_id'] ? one('SELECT * FROM organizers WHERE id = ?', [$t['organizer_id']]) : null;
    $order = $t['order_ref'] ? one('SELECT o.*, e.name ev_name FROM orders o LEFT JOIN events e ON e.id = o.event_id WHERE o.ref = ?', [$t['order_ref']]) : null;
    $orders = all('SELECT o.id, o.ref, o.status, o.total, o.created_at, e.name ev_name FROM orders o LEFT JOIN events e ON e.id = o.event_id WHERE LOWER(o.email) = LOWER(?) ORDER BY o.id DESC LIMIT 8', [$t['email']]);
    $history = all('SELECT id, ref, subject, status, created_at FROM support_tickets WHERE LOWER(email) = LOWER(?) AND id <> ? ORDER BY id DESC LIMIT 6', [$t['email'], $t['id']]);
    $agents = all("SELECT id, name FROM users WHERE role = 'superadmin' AND active = 1 ORDER BY name");
    $orgs = all("SELECT id, name FROM organizers ORDER BY name");
    return admin_page($t['subject'], 'support_ticket', compact('t', 'msgs', 'org', 'order', 'orders', 'history', 'agents', 'orgs'), 'support', ['crumbs' => [['Support', '/admin/support']]]);
}
