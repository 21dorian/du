<?php
declare(strict_types=1);

/* 2.4 : revendeurs — super-admin (gestion, relevés), organisateur (autorisations), portail de vente du revendeur. */

require_once APP . '/controllers/admin.php';
require_once APP . '/controllers/pro.php';
require_once APP . '/controllers/public.php';

// ---------- Super-admin ----------
function admin_resellers(): string
{
    $u = require_super();
    $month = preg_match('~^\d{4}-\d{2}$~', (string)input('mois')) ? (string)input('mois') : date('Y-m');
    $sel = input('r') === 'new' ? [] : ((int)input('r') ? one('SELECT * FROM resellers WHERE id = ?', [(int)input('r')]) : null);
    if (is_post()) {
        $a = (string)input('action');
        $r = (int)input('reseller_id') ? one('SELECT * FROM resellers WHERE id = ?', [(int)input('reseller_id')]) : null;
        $go = fn(array $flash) => redirect('/admin/revendeurs?r=' . ($r['id'] ?? '') . '&mois=' . $month, $flash);
        if ($a === 'toggle' && $r) { update('resellers', ['active' => (int)$r['active'] ? 0 : 1], 'id = ?', [$r['id']]); $go(['success' => (int)$r['active'] ? 'Revendeur suspendu : son portail est fermé.' : 'Revendeur réactivé.']); }
        if ($a === 'token' && $r) { update('resellers', ['token' => token(24)], 'id = ?', [$r['id']]); Audit::log('reseller.token', 'Nouveau lien pour ' . $r['name']); $go(['success' => 'Nouveau lien créé : l\'ancien ne marche plus.']); }
        if ($a === 'send' && $r) {
            $r = one('SELECT * FROM resellers WHERE id = ?', [$r['id']]);
            try { Mailer::send($r['email'], 'Ton portail de vente ' . setting('platform_name'), '<p>Bonjour ' . e(strtok((string)($r['contact_name'] ?: $r['name']), ' ')) . ',</p><p>Voici ton accès pour vendre les billets des organisateurs partenaires. Garde ce lien pour toi : il donne accès à la vente.</p>'
                . '<p><a href="' . e(abs_url('/revendeur/' . $r['token'])) . '" style="display:inline-block;background:#1d1d1f;color:#fff;padding:12px 22px;border-radius:980px;text-decoration:none">Ouvrir mon portail</a></p><p>Commission : ' . e(rtrim(rtrim(number_format((float)$r['commission_pct'], 2, ',', ''), '0'), ',')) . ' % des ventes, déduite du relevé mensuel.</p>'); }
            catch (Throwable $e) { $go(['error' => 'Envoi impossible : ' . $e->getMessage()]); }
            $go(['success' => 'Lien envoyé à ' . $r['email'] . '.']);
        }
        if ($a === 'access' && $r) {
            $oid = (int)input('organizer_id');
            if (!val('SELECT id FROM organizers WHERE id = ?', [$oid])) $go(['error' => 'Organisateur inconnu.']);
            $pct = trim((string)input('pct')) === '' ? null : max(0, min(50, (float)str_replace(',', '.', (string)input('pct'))));
            q('INSERT INTO reseller_access (reseller_id, organizer_id, active, commission_pct, created_at) VALUES (?, ?, 1, ?, NOW()) ON DUPLICATE KEY UPDATE active = 1, commission_pct = VALUES(commission_pct)', [$r['id'], $oid, $pct]);
            $go(['success' => 'Accès ouvert.']);
        }
        if ($a === 'revoke' && $r) { q('UPDATE reseller_access SET active = 0 WHERE reseller_id = ? AND organizer_id = ?', [$r['id'], (int)input('organizer_id')]); $go(['success' => 'Accès fermé.']); }
        if ($a === 'delete' && $r) {
            if (val('SELECT COUNT(*) FROM orders WHERE reseller_id = ?', [$r['id']])) $go(['error' => 'Ce revendeur a déjà vendu : suspends-le plutôt que de le supprimer.']);
            q('DELETE FROM reseller_access WHERE reseller_id = ?', [$r['id']]); q('DELETE FROM resellers WHERE id = ?', [$r['id']]);
            redirect('/admin/revendeurs', ['success' => 'Revendeur supprimé.']);
        }
        // création / modification
        $d = ['name' => mb_substr(trim((string)input('name')), 0, 190), 'kind' => array_key_exists((string)input('kind'), Resellers::KINDS) ? (string)input('kind') : null,
            'contact_name' => trim((string)input('contact_name')) ?: null, 'email' => strtolower(trim((string)input('email'))), 'phone' => trim((string)input('phone')) ?: null,
            'city' => trim((string)input('city')) ?: null, 'commission_pct' => max(0, min(50, (float)str_replace(',', '.', (string)input('commission_pct'))))];
        if ($d['name'] === '' || !is_email($d['email'])) back(['error' => 'Il faut un nom et un e-mail valide.']);
        if ($r) { update('resellers', $d, 'id = ?', [$r['id']]); $go(['success' => 'Revendeur enregistré.']); }
        $id = insert('resellers', $d + ['token' => token(24), 'active' => 1, 'created_at' => now()]);
        Audit::log('reseller.created', $d['name']);
        redirect('/admin/revendeurs?r=' . $id, ['success' => 'Revendeur créé. Ouvre-lui l\'accès à des organisateurs, puis envoie-lui son lien.']);
    }
    if ($sel && input('export') === 'csv') Resellers::csv($sel, $month, Resellers::orders((int)$sel['id'], $month));
    $list = all("SELECT r.*, (SELECT COUNT(*) FROM reseller_access a WHERE a.reseller_id = r.id AND a.active = 1) orgs,
            (SELECT COALESCE(SUM(total), 0) FROM orders o WHERE o.reseller_id = r.id AND o.status = 'paid' AND o.paid_at >= ?) month_total,
            (SELECT COALESCE(SUM(reseller_commission), 0) FROM orders o WHERE o.reseller_id = r.id AND o.status = 'paid' AND o.paid_at >= ?) month_comm,
            (SELECT MAX(created_at) FROM orders o WHERE o.reseller_id = r.id) last_sale
        FROM resellers r ORDER BY r.active DESC, r.name", [date('Y-m-01'), date('Y-m-01')]);
    $k = ['n' => count(array_filter($list, fn($x) => (int)$x['active'])), 'total' => array_sum(array_column($list, 'month_total')), 'comm' => array_sum(array_column($list, 'month_comm')),
        'year' => (int)val("SELECT COALESCE(SUM(total), 0) FROM orders WHERE reseller_id IS NOT NULL AND status = 'paid' AND paid_at >= ?", [date('Y-01-01')])];
    $detail = null;
    if ($sel) {
        $detail = ['access' => all('SELECT a.*, o.name org_name FROM reseller_access a JOIN organizers o ON o.id = a.organizer_id WHERE a.reseller_id = ? ORDER BY a.active DESC, o.name', [$sel['id']]),
            'statement' => Resellers::statement((int)$sel['id'], $month), 'orders' => Resellers::orders((int)$sel['id'], $month, null, 50)];
    }
    $orgs = all("SELECT id, name FROM organizers WHERE status = 'active' ORDER BY name");
    return admin_page('Revendeurs', 'resellers', compact('list', 'k', 'sel', 'detail', 'orgs', 'month'), 'revendeurs');
}

// ---------- Organisateur ----------
function pro_resellers(): string
{
    $org = require_org(['owner', 'manager']);
    if (is_post()) {
        $r = one('SELECT * FROM resellers WHERE id = ? AND active = 1', [(int)input('reseller_id')]);
        if (!$r) back(['error' => 'Revendeur inconnu.']);
        if (input('action') === 'on') {
            $pct = trim((string)input('pct')) === '' ? null : max(0, min(50, (float)str_replace(',', '.', (string)input('pct'))));
            q('INSERT INTO reseller_access (reseller_id, organizer_id, active, commission_pct, created_at) VALUES (?, ?, 1, ?, NOW()) ON DUPLICATE KEY UPDATE active = 1, commission_pct = VALUES(commission_pct)', [$r['id'], $org['id'], $pct]);
            Audit::log('reseller.on', $r['name']);
            back(['success' => $r['name'] . ' peut maintenant vendre tes billets.']);
        }
        q('UPDATE reseller_access SET active = 0 WHERE reseller_id = ? AND organizer_id = ?', [$r['id'], $org['id']]);
        Audit::log('reseller.off', $r['name']);
        back(['success' => $r['name'] . ' ne peut plus vendre tes billets.']);
    }
    $month = preg_match('~^\d{4}-\d{2}$~', (string)input('mois')) ? (string)input('mois') : date('Y-m');
    $list = all("SELECT r.id, r.name, r.kind, r.city, r.commission_pct, a.active on_, a.commission_pct my_pct,
            (SELECT COALESCE(SUM(total), 0) FROM orders o WHERE o.reseller_id = r.id AND o.organizer_id = ? AND o.status = 'paid') sold,
            (SELECT COALESCE(SUM(reseller_commission), 0) FROM orders o WHERE o.reseller_id = r.id AND o.organizer_id = ? AND o.status = 'paid') comm
        FROM resellers r LEFT JOIN reseller_access a ON a.reseller_id = r.id AND a.organizer_id = ? WHERE r.active = 1 ORDER BY (a.active = 1) DESC, r.name", [$org['id'], $org['id'], $org['id']]);
    $statement = [];
    foreach ($list as $r) foreach (Resellers::statement((int)$r['id'], $month, (int)$org['id']) as $s) $statement[] = $s + ['reseller' => $r['name']];
    $orders = all("SELECT o.*, r.name reseller, e.name event_name, (SELECT COALESCE(SUM(qty), 0) FROM order_items i WHERE i.order_id = o.id) n FROM orders o JOIN resellers r ON r.id = o.reseller_id LEFT JOIN events e ON e.id = o.event_id
        WHERE o.organizer_id = ? AND o.reseller_id IS NOT NULL ORDER BY o.id DESC LIMIT 30", [$org['id']]);
    return pro_page('Revendeurs', 'resellers', compact('list', 'statement', 'orders', 'month'), 'revendeurs');
}

// ---------- Portail du revendeur ----------
function reseller_ctx(string $token): array
{
    $r = Resellers::byToken($token);
    if (!$r) abort(404);
    return $r;
}

function reseller_portal(string $token): string
{
    $r = reseller_ctx($token);
    $month = preg_match('~^\d{4}-\d{2}$~', (string)input('mois')) ? (string)input('mois') : date('Y-m');
    if (is_post() && input('action') === 'cancel') {
        $o = one("SELECT * FROM orders WHERE id = ? AND reseller_id = ? AND status = 'paid'", [(int)input('order_id'), $r['id']]);
        if (!$o || strtotime($o['paid_at']) < time() - 1800) redirect('/revendeur/' . $token, ['error' => 'Une vente ne peut être annulée que dans les 30 minutes. Au-delà, contacte l\'organisateur.']);
        if ((int)val("SELECT COUNT(*) FROM tickets WHERE order_id = ? AND checked_in_at IS NOT NULL", [$o['id']])) redirect('/revendeur/' . $token, ['error' => 'Un billet de cette vente a déjà été scanné.']);
        Tickets::cancelOrder($o);
        log_line('revendeur', $r['name'] . ' annule ' . $o['ref']);
        redirect('/revendeur/' . $token, ['success' => 'Vente ' . $o['ref'] . ' annulée : les billets ne sont plus valables.']);
    }
    if (input('export') === 'csv') Resellers::csv($r, $month, Resellers::orders((int)$r['id'], $month));
    $events = Resellers::events((int)$r['id']);
    $statement = Resellers::statement((int)$r['id'], $month);
    $orders = Resellers::orders((int)$r['id'], null, null, 15);
    $today = one("SELECT COUNT(*) n, COALESCE(SUM(total), 0) t FROM orders WHERE reseller_id = ? AND status = 'paid' AND paid_at >= CURDATE()", [$r['id']]);
    return pub('reseller', compact('r', 'events', 'statement', 'orders', 'month', 'today', 'token'), 'Portail revendeur · ' . $r['name'], ['noindex' => true]);
}

function reseller_sell(string $token, string $id): string
{
    $r = reseller_ctx($token);
    $ev = one("SELECT * FROM events WHERE id = ? AND status = 'published'", [(int)$id]);
    $access = $ev ? Resellers::allowed((int)$r['id'], (int)$ev['organizer_id']) : null;
    if (!$ev || !$access) abort(404);
    $org = one('SELECT * FROM organizers WHERE id = ?', [$ev['organizer_id']]);
    $rates = Resellers::rates($ev);
    $sessions = Tickets::sessions((int)$ev['id']);
    $left = fn(?array $s) => array_sum(array_map(fn($x) => min(1, Tickets::remaining($x, $ev, $s)), $rates));
    foreach ($sessions as &$s) $s['full'] = !$left($s);
    unset($s);
    $sel = $sessions ? (one('SELECT * FROM sessions WHERE id = ? AND event_id = ?', [(int)input('session_id'), $ev['id']]) ?: (array_values(array_filter($sessions, fn($s) => !$s['full']))[0] ?? $sessions[0])) : null;
    $errors = [];
    if (is_post()) {
        $ok = array_column($rates, null, 'id'); $q = [];
        foreach ((array)($_POST['qty'] ?? []) as $rid => $n) if ((int)$n > 0 && isset($ok[(int)$rid])) $q[(int)$rid] = min(50, (int)$n);
        $buyer = ['first_name' => trim((string)input('first_name')), 'last_name' => trim((string)input('last_name')), 'email' => strtolower(trim((string)input('email'))), 'phone' => trim((string)input('phone'))];
        if (!$q) $errors[] = 'Choisis au moins un billet.';
        if ($buyer['last_name'] === '') $errors[] = 'Il faut le nom de l\'acheteur.';
        if (!is_email($buyer['email'])) $errors[] = 'Il faut l\'e-mail de l\'acheteur : c\'est là qu\'il reçoit ses billets.';
        $seats = [];
        if (!$errors && Seating::map($ev)) {
            $got = [];
            foreach ($q as $rid => $n) {
                $pick = Seating::best($ev, $sel, $ok[$rid], $n * max(1, (int)$ok[$rid]['pack_size']), $got);
                if ($pick === null) { $errors[] = $ok[$rid]['name'] . ' : plus assez de places libres.'; break; }
                $seats[$rid] = $pick; $got = array_merge($got, $pick);
            }
        }
        if (!$errors) {
            $res = Tickets::createOrder(['event' => $ev, 'org' => $org, 'session' => $sel, 'quantities' => $q, 'seats' => $seats, 'buyer' => $buyer, 'method' => 'guichet', 'channel' => 'revendeur',
                'pay_method' => 'revendeur', 'note' => 'Vendu par ' . $r['name']]);
            if (!empty($res['errors'])) $errors = $res['errors'];
            else {
                $oid = (int)$res['order']['id'];
                $total = (int)val('SELECT total FROM orders WHERE id = ?', [$oid]);
                update('orders', ['reseller_id' => $r['id'], 'reseller_commission' => (int)round($total * Resellers::pct($r, $access) / 100)], 'id = ?', [$oid]);
                $o = Tickets::confirm($oid, [], true);
                log_line('revendeur', $r['name'] . ' vend ' . $o['ref'] . ' ' . money($total));
                redirect('/revendeur/' . $token . '/vente/' . $o['ref'], ['success' => 'Vente enregistrée, billets envoyés à ' . $buyer['email'] . '.']);
            }
        }
    }
    foreach ($rates as &$x) $x['remaining'] = Tickets::remaining($x, $ev, $sel);
    unset($x);
    $pct = Resellers::pct($r, $access);
    return pub('reseller_sell', compact('r', 'ev', 'org', 'rates', 'sessions', 'sel', 'errors', 'token', 'pct'), 'Vendre · ' . $ev['name'], ['noindex' => true]);
}

function reseller_receipt(string $token, string $ref): string
{
    $r = reseller_ctx($token);
    $o = one('SELECT o.*, e.name event_name, COALESCE(se.starts_at, e.starts_at) starts_at, e.venue FROM orders o LEFT JOIN events e ON e.id = o.event_id LEFT JOIN sessions se ON se.id = o.session_id WHERE o.ref = ? AND o.reseller_id = ?', [$ref, $r['id']]);
    if (!$o) abort(404);
    $items = all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
    return pub('reseller_receipt', compact('r', 'o', 'items', 'token'), 'Vente ' . $o['ref'], ['noindex' => true]);
}
