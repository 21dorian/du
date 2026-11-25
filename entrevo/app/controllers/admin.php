<?php
declare(strict_types=1);

function admin_page(string $title, string $view, array $vars, string $active, array $extra = []): string
{
    return view('layout_back', ['title' => $title, 'area' => 'admin', 'active' => $active, 'content' => view('admin/' . $view, $vars)] + $extra);
}

function require_super(): array
{
    $u = require_login(['superadmin']);
    if ($u['role'] !== 'superadmin') abort(403);
    // 2.2 : droits par zone pour l'équipe plateforme, double authentification obligatoire si réglée
    if (class_exists('Security') && !Security::allowed($u, Security::areaFor(route_path()))) abort(403, 'Ton accès au super-admin ne comprend pas cette rubrique.');
    if (setting('admin_2fa') === '1' && !(int)($u['totp_enabled'] ?? 0)) redirect('/mon-compte#securite', ['info' => 'Active la double authentification pour accéder au super-admin.']);
    return $u;
}

// Vue d'ensemble : voir app/controllers/platform.php (admin_home)

function organizers(): string
{
    require_super();
    $q = trim((string)input('q'));
    $st = (string)input('st');
    $sql = "SELECT o.*, (SELECT COUNT(*) FROM events e WHERE e.organizer_id = o.id) events,
            (SELECT COALESCE(SUM(total),0) FROM orders r WHERE r.organizer_id = o.id AND r.status = 'paid') sales FROM organizers o WHERE 1";
    $p = [];
    if ($q !== '') { $sql .= ' AND (o.name LIKE ? OR o.email LIKE ? OR o.siret LIKE ?)'; $p = ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%']; }
    if (in_array($st, ['active', 'pending', 'suspended'], true)) { $sql .= ' AND o.status = ?'; $p[] = $st; }
    $list = all($sql . ' ORDER BY o.created_at DESC', $p);
    foreach ($list as &$o) $o['balance'] = Tickets::balance((int)$o['id'])['balance'];
    unset($o);
    $pending = (int)val("SELECT COUNT(*) FROM organizers WHERE status = 'pending'");
    return admin_page('Organisateurs', 'organizers', compact('list', 'q', 'st', 'pending'), 'orgs');
}

function organizer_detail(string $id): string
{
    $u = require_super();
    $org = one('SELECT * FROM organizers WHERE id = ?', [(int)$id]);
    if (!$org) abort(404);
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'fees') {
            $pct = trim((string)input('fee_percent'));
            $fixed = trim((string)input('fee_fixed'));
            update('organizers', [
                'fee_percent' => $pct === '' ? null : max(0, min(50, (float)str_replace(',', '.', $pct))),
                'fee_fixed' => $fixed === '' ? null : parse_money($fixed),
                'fee_mode' => input('fee_mode') === 'organizer' ? 'organizer' : 'buyer',
            ], 'id = ?', [$org['id']]);
            back(['success' => 'Commission mise à jour (s\'applique aux prochaines commandes).']);
        }
        if ($a === 'approve') {
            update('organizers', ['status' => 'active', 'approved_at' => now()], 'id = ?', [$org['id']]);
            try { Mailer::send((string)$org['email'], 'Ton compte ' . setting('platform_name') . ' est validé !', '<p>Bonne nouvelle : ton compte organisateur « ' . e($org['name']) . ' » est validé</p><p>Tu peux maintenant publier tes événements et vendre tes billets.</p><p><a href="' . e(abs_url('/pro')) . '">Ouvrir mon espace</a></p>'); } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
            back(['success' => 'Organisateur validé, il a été prévenu par e-mail.']);
        }
        if ($a === 'note') { update('organizers', ['admin_note' => trim((string)input('admin_note')) ?: null], 'id = ?', [$org['id']]); back(['success' => 'Note enregistrée.']); }
        if ($a === 'status') {
            update('organizers', ['status' => $org['status'] === 'active' ? 'suspended' : 'active'], 'id = ?', [$org['id']]);
            back(['success' => $org['status'] === 'active' ? 'Organisateur suspendu : ses pages sont hors ligne.' : 'Organisateur réactivé.']);
        }
        if ($a === 'payout') {
            $amount = parse_money(input('amount'));
            if ($amount <= 0) back(['error' => 'Montant invalide.']);
            insert('payouts', ['organizer_id' => $org['id'], 'amount' => $amount, 'reference' => trim((string)input('reference')) ?: null,
                'note' => trim((string)input('note')) ?: null, 'created_by' => $u['id'], 'created_at' => now()]);
            back(['success' => 'Reversement de ' . money($amount) . ' enregistré.']);
        }
        if ($a === 'delete_payout') { q('DELETE FROM payouts WHERE id = ? AND organizer_id = ?', [(int)input('payout_id'), $org['id']]); back(['success' => 'Reversement supprimé.']); }
        if ($a === 'plan') {
            $pid = (int)input('plan_id') ?: null;
            if ($pid && !val('SELECT id FROM plans WHERE id = ?', [$pid])) back();
            update('organizers', ['plan_id' => $pid, 'plan_since' => date('Y-m-d')], 'id = ?', [$org['id']]);
            $pn = $pid ? (string)val('SELECT name FROM plans WHERE id = ?', [$pid]) : 'par défaut';
            Audit::log('org.plan', 'Formule : ' . $pn, null, null, (int)$org['id']);
            if (input('notify') && $org['email']) { try { Mailer::send((string)$org['email'], 'Ta formule ' . setting('platform_name') . ' a changé', '<p>Bonjour,</p><p>Ton compte « ' . e($org['name']) . ' » est désormais sur la formule <b>' . e($pn) . '</b>. Le détail de tes conditions est dans <a href="' . e(abs_url('/pro/finances')) . '">Finances</a>.</p>'); } catch (Throwable $e) { log_line('emails', $e->getMessage()); } }
            back(['success' => 'Formule changée : ' . $pn . '.']);
        }
        if ($a === 'message') {
            $sub = trim((string)input('subject')); $body = trim((string)input('body'));
            if ($sub === '' || $body === '' || !$org['email']) back(['error' => 'Sujet et message obligatoires.']);
            try { Mailer::send((string)$org['email'], $sub, '<p>' . nl2br(e($body)) . '</p>', [], (string)(setting('support_email') ?: setting('contact_email')) ?: null); } catch (Throwable $e) { back(['error' => 'Envoi impossible : ' . $e->getMessage()]); }
            Audit::log('org.message', 'E-mail : ' . $sub, null, null, (int)$org['id']);
            back(['success' => 'Message envoyé à ' . $org['email'] . '.']);
        }
        back();
    }
    $oid = (int)$org['id'];
    $tab = in_array(input('t'), ['argent', 'conditions', 'equipe', 'activite'], true) ? (string)input('t') : 'apercu';
    $balance = Tickets::balance($oid);
    $payouts = all('SELECT p.*, u.name by_name FROM payouts p LEFT JOIN users u ON u.id = p.created_by WHERE p.organizer_id = ? ORDER BY p.created_at DESC', [$oid]);
    $events = all("SELECT e.*, (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status='valid') sold,
        (SELECT COALESCE(SUM(total), 0) FROM orders o WHERE o.event_id = e.id AND o.status = 'paid') revenue FROM events e WHERE organizer_id = ? ORDER BY starts_at DESC", [$oid]);
    $users = all('SELECT * FROM users WHERE organizer_id = ? ORDER BY role, name', [$oid]);
    [$pct, $fixed] = Tickets::feeRates($org);
    $terms = Billing::terms($org);
    $k = one("SELECT COALESCE(SUM(CASE WHEN paid_at >= NOW() - INTERVAL 12 MONTH THEN total END), 0) gmv12, COALESCE(SUM(CASE WHEN paid_at >= NOW() - INTERVAL 12 MONTH THEN commission END), 0) com12,
        COUNT(*) orders, COALESCE(SUM(total), 0) gmv, COUNT(DISTINCT LOWER(email)) buyers FROM orders WHERE organizer_id = ? AND status = 'paid'", [$oid]);
    $k['tickets'] = (int)val("SELECT COUNT(*) FROM tickets t JOIN orders o ON o.id = t.order_id WHERE o.organizer_id = ? AND t.status = 'valid'", [$oid]);
    $k['disputes'] = (int)val("SELECT COUNT(*) FROM disputes WHERE organizer_id = ? AND status IN ('open','evidence')", [$oid]);
    $k['support'] = (int)val("SELECT COUNT(*) FROM support_tickets WHERE organizer_id = ? AND status <> 'solved'", [$oid]);
    $months = []; for ($i = 11; $i >= 0; $i--) $months[date('Y-m', strtotime("first day of -$i month"))] = [0, 0];
    foreach (all("SELECT DATE_FORMAT(paid_at, '%Y-%m') m, SUM(total) t, SUM(commission) c FROM orders WHERE organizer_id = ? AND status = 'paid' AND paid_at >= ? GROUP BY m", [$oid, array_key_first($months) . '-01']) as $r) if (isset($months[$r['m']])) $months[$r['m']] = [(int)$r['t'], (int)$r['c']];
    $charges = all('SELECT c.*, u.name by_name FROM org_charges c LEFT JOIN users u ON u.id = c.created_by WHERE c.organizer_id = ? ORDER BY c.created_at DESC LIMIT 100', [$oid]);
    $contracts = all('SELECT * FROM contracts WHERE organizer_id = ? ORDER BY starts_on DESC', [$oid]);
    $plans = Billing::plans(true);
    $activity = all('SELECT a.*, u.name user_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.organizer_id = ? ORDER BY a.id DESC LIMIT 60', [$oid]);
    $support = all('SELECT id, ref, subject, status, updated_at FROM support_tickets WHERE organizer_id = ? ORDER BY id DESC LIMIT 10', [$oid]);
    $disputes = all('SELECT d.*, o.ref FROM disputes d LEFT JOIN orders o ON o.id = d.order_id WHERE d.organizer_id = ? ORDER BY d.id DESC LIMIT 10', [$oid]);
    $invoices = all('SELECT * FROM commission_invoices WHERE organizer_id = ? ORDER BY period DESC LIMIT 12', [$oid]);
    $logins = $users ? all('SELECT l.*, u.name FROM login_log l JOIN users u ON u.id = l.user_id WHERE u.organizer_id = ? ORDER BY l.id DESC LIMIT 15', [$oid]) : [];
    return admin_page($org['name'], 'organizer_detail', compact('org', 'balance', 'payouts', 'events', 'users', 'pct', 'fixed', 'terms', 'k', 'months', 'charges', 'contracts', 'plans', 'activity', 'support', 'disputes', 'invoices', 'tab', 'logins'), 'orgs',
        ['crumbs' => [['Organisateurs', '/admin/organisateurs']],
         'actions' => '<form method="post" action="' . url('/admin/organisateurs/' . $org['id'] . '/consulter') . '">' . csrf_field() . '<button class="btn">Entrer dans son espace</button></form>']);
}

function organizer_impersonate(string $id): never
{
    require_super();
    if (!val('SELECT id FROM organizers WHERE id = ?', [(int)$id])) abort(404);
    $_SESSION['as_org'] = (int)$id;
    redirect('/pro', ['info' => 'Tu consultes l\'espace de cet organisateur. Bouton « Retour super-admin » en bas à gauche.']);
}

function stop_impersonate(): never
{
    require_super();
    $id = (int)($_SESSION['as_org'] ?? 0);
    unset($_SESSION['as_org']);
    redirect($id ? '/admin/organisateurs/' . $id : '/admin');
}

function admin_orders_where(): array
{
    $w = ['1']; $p = [];
    $q = trim((string)input('q'));
    if ($q !== '') { $w[] = '(o.ref LIKE ? OR o.email LIKE ? OR o.last_name LIKE ? OR o.phone LIKE ? OR o.provider_ref LIKE ? OR o.provider_payment LIKE ?)'; array_push($p, ...array_fill(0, 6, '%' . $q . '%')); }
    $st = (string)input('st');
    if (in_array($st, ['paid', 'pending', 'refunded', 'cancelled', 'expired'], true)) { $w[] = 'o.status = ?'; $p[] = $st; }
    if ($st === 'partial') $w[] = "o.status = 'paid' AND o.refunded_amount > 0";
    if ($st === 'refund_req') $w[] = "o.status = 'paid' AND o.refund_requested_at IS NOT NULL";
    if ($st === 'review') $w[] = "o.risk_status = 'review'";
    $pv = (string)input('pv');
    if ($pv !== '') { $w[] = 'COALESCE(o.provider, o.method) = ?'; $p[] = $pv; }
    if ((int)input('org')) { $w[] = 'o.organizer_id = ?'; $p[] = (int)input('org'); }
    if (preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)input('du'))) { $w[] = 'o.created_at >= ?'; $p[] = input('du') . ' 00:00:00'; }
    if (preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)input('au'))) { $w[] = 'o.created_at <= ?'; $p[] = input('au') . ' 23:59:59'; }
    if (trim((string)input('min')) !== '') { $w[] = 'o.total >= ?'; $p[] = parse_money(input('min')); }
    if (input('kind') === 'store') $w[] = 'o.event_id = 0';
    if (input('kind') === 'tickets') $w[] = 'o.event_id > 0';
    return [implode(' AND ', $w), $p];
}

function orders(): string
{
    require_super();
    [$w, $p] = admin_orders_where();
    $from = 'FROM orders o LEFT JOIN events e ON e.id = o.event_id JOIN organizers g ON g.id = o.organizer_id WHERE ' . $w;
    if (input('csv')) {
        $rows = all("SELECT o.ref, o.created_at, o.status, g.name org, COALESCE(e.name, 'Boutique / cartes / abonnements') ev, o.first_name, o.last_name, o.email, o.total, o.fees, o.commission, o.organizer_amount, o.refunded_amount, COALESCE(o.provider, o.method) pay $from ORDER BY o.id DESC LIMIT 50000", $p);
        $m = fn($c) => number_format((int)$c / 100, 2, ',', '');
        csv_download('commandes-plateforme-' . date('Y-m-d') . '.csv', array_merge([['Référence', 'Date', 'Statut', 'Organisateur', 'Événement', 'Prénom', 'Nom', 'E-mail', 'Total', 'Frais', 'Commission', 'Part organisateur', 'Remboursé', 'Paiement']],
            array_map(fn($r) => [$r['ref'], $r['created_at'], $r['status'], $r['org'], $r['ev'], $r['first_name'], $r['last_name'], $r['email'], $m($r['total']), $m($r['fees']), $m($r['commission']), $m($r['organizer_amount']), $m($r['refunded_amount']), $r['pay']], $rows)));
    }
    $page = max(1, (int)input('page', 1)); $per = 100;
    $tot = one("SELECT COUNT(*) n, COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.total END), 0) total, COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.commission END), 0) commission, COALESCE(SUM(o.refunded_amount), 0) refunded $from", $p);
    $list = all("SELECT o.*, e.name ev_name, g.name org_name, (SELECT COUNT(*) FROM disputes d WHERE d.order_id = o.id) disputes $from ORDER BY o.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
    $orgs = all('SELECT id, name FROM organizers ORDER BY name');
    $providers = array_column(all('SELECT DISTINCT COALESCE(provider, method) pv FROM orders WHERE COALESCE(provider, method) IS NOT NULL ORDER BY pv'), 'pv');
    return admin_page('Commandes', 'orders', compact('list', 'tot', 'orgs', 'providers', 'page', 'per'), 'orders');
}

/** Fiche commande super-admin : tout le contexte et les actions (mêmes que l'organisateur + geste plateforme). */
function admin_order(string $id): string
{
    $u = require_super();
    $o = one('SELECT * FROM orders WHERE id = ?', [(int)$id]);
    if (!$o) abort(404);
    if (is_post()) {
        if (input('action') === 'platform_refund') {
            if ($o['status'] !== 'paid') back(['error' => 'Seule une commande payée peut être remboursée.']);
            $max = min((int)$o['commission'], (int)$o['total'] - (int)$o['refunded_amount']);
            $amount = parse_money(input('amount'));
            if ($amount <= 0 || $amount > $max) back(['error' => 'Le geste doit être compris entre 0 et ' . money($max) . ' (commission encaissée).']);
            if ($o['method'] === 'online' && $o['provider'] !== 'demo') { try { Payments::refund($o, $amount); } catch (Throwable $e) { back(['error' => 'Remboursement refusé : ' . $e->getMessage()]); } }
            update('orders', ['refunded_amount' => (int)$o['refunded_amount'] + $amount, 'commission' => (int)$o['commission'] - $amount], 'id = ?', [$o['id']]);
            Audit::log('order.platform_refund', 'Geste plateforme ' . money($amount) . ' : ' . trim((string)input('reason')), (int)$o['event_id'] ?: null, (int)$o['id'], (int)$o['organizer_id']);
            back(['success' => 'Geste de ' . money($amount) . ' remboursé, pris sur la commission. La part de l\'organisateur n\'est pas touchée.']);
        }
        require_once APP . '/controllers/orders.php';
        order_action($o);
    }
    $ev = $o['event_id'] ? one('SELECT * FROM events WHERE id = ?', [$o['event_id']]) : null;
    $org = one('SELECT * FROM organizers WHERE id = ?', [$o['organizer_id']]);
    $items = all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
    $tickets = all('SELECT * FROM tickets WHERE order_id = ? ORDER BY id', [$o['id']]);
    $log = all('SELECT a.*, u.name user_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.order_id = ? ORDER BY a.id DESC LIMIT 50', [$o['id']]);
    $disputes = all('SELECT * FROM disputes WHERE order_id = ?', [$o['id']]);
    $tickets_support = all('SELECT id, ref, subject, status FROM support_tickets WHERE order_ref = ? OR LOWER(email) = LOWER(?) ORDER BY id DESC LIMIT 5', [$o['ref'], $o['email']]);
    $other = all("SELECT o2.id, o2.ref, o2.status, o2.total, o2.created_at, e2.name ev_name FROM orders o2 LEFT JOIN events e2 ON e2.id = o2.event_id WHERE LOWER(o2.email) = LOWER(?) AND o2.id <> ? ORDER BY o2.id DESC LIMIT 8", [$o['email'], $o['id']]);
    return admin_page('Commande ' . $o['ref'], 'order', compact('o', 'ev', 'org', 'items', 'tickets', 'log', 'disputes', 'tickets_support', 'other'), 'orders', ['crumbs' => [['Commandes', '/admin/commandes']]]);
}

function users(): string
{
    $me = require_super();
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'add_admin') {
            $email = strtolower(trim((string)input('email')));
            if (!is_email($email) || trim((string)input('name')) === '' || mb_strlen((string)input('password')) < 10) back(['error' => 'Nom, e-mail valide et mot de passe (10 caractères min.) requis.']);
            if (val('SELECT id FROM users WHERE email = ?', [$email])) back(['error' => 'Cet e-mail est déjà utilisé.']);
            $perms = array_values(array_intersect(array_keys(Security::AREAS), (array)($_POST['perms'] ?? [])));
            insert('users', ['organizer_id' => null, 'email' => $email, 'name' => trim((string)input('name')), 'password_hash' => password_hash((string)input('password'), PASSWORD_DEFAULT),
                'role' => 'superadmin', 'active' => 1, 'admin_perms' => input('scope') === 'limited' ? implode(',', $perms) : null, 'created_at' => now()]);
            Audit::log('user.admin_added', $email . (input('scope') === 'limited' ? ' (accès limité)' : ' (accès complet)'));
            back(['success' => 'Membre de l\'équipe plateforme ajouté.']);
        }
        $t = one('SELECT * FROM users WHERE id = ?', [(int)input('user_id')]);
        if (!$t) back();
        if ((int)$t['id'] === (int)$me['id'] && in_array($a, ['toggle', 'perms', 'reset_2fa'], true)) back(['error' => 'Tu ne peux pas faire ça sur ton propre compte.']);
        switch ($a) {
            case 'toggle': update('users', ['active' => $t['active'] ? 0 : 1], 'id = ?', [$t['id']]); if ($t['active']) q('UPDATE user_sessions SET revoked = 1 WHERE user_id = ?', [$t['id']]);
                Audit::log('user.toggled', $t['email'] . ($t['active'] ? ' désactivé' : ' réactivé')); back(['success' => $t['active'] ? 'Compte désactivé et déconnecté partout.' : 'Compte réactivé.']);
            case 'unlock': update('users', ['locked_until' => null, 'failed_logins' => 0], 'id = ?', [$t['id']]); back(['success' => 'Compte déverrouillé.']);
            case 'revoke': q('UPDATE user_sessions SET revoked = 1 WHERE user_id = ?', [$t['id']]); Audit::log('user.revoked', $t['email']); back(['success' => 'Toutes les sessions de ' . $t['email'] . ' sont fermées.']);
            case 'reset_2fa': update('users', ['totp_secret' => null, 'totp_enabled' => 0], 'id = ?', [$t['id']]); Audit::log('user.2fa', 'Double authentification réinitialisée pour ' . $t['email']); back(['success' => 'Double authentification réinitialisée : la personne pourra la réactiver depuis « Mon compte ».']);
            case 'reset_link':
                $tok = token(24);
                update('users', ['reset_token' => hash('sha256', $tok), 'reset_expires' => date('Y-m-d H:i:s', time() + 86400)], 'id = ?', [$t['id']]);
                try { Mailer::send($t['email'], 'Choisis un nouveau mot de passe', '<p>Bonjour ' . e(strtok($t['name'], ' ')) . ',</p><p>L\'équipe ' . e(setting('platform_name')) . ' t\'envoie un lien pour choisir un nouveau mot de passe (valable 24 h) :</p><p><a href="' . e(abs_url('/mot-de-passe/' . $tok)) . '">Choisir mon mot de passe</a></p>'); }
                catch (Throwable $e) { back(['error' => 'Envoi impossible : ' . $e->getMessage()]); }
                Audit::log('user.reset_link', $t['email']); back(['success' => 'Lien de réinitialisation envoyé à ' . $t['email'] . '.']);
            case 'perms':
                if ($t['role'] !== 'superadmin') back();
                $perms = array_values(array_intersect(array_keys(Security::AREAS), (array)($_POST['perms'] ?? [])));
                update('users', ['admin_perms' => input('scope') === 'limited' ? implode(',', $perms) : null], 'id = ?', [$t['id']]);
                Audit::log('user.perms', $t['email'] . ' : ' . (input('scope') === 'limited' ? implode(', ', $perms) ?: 'aucune zone' : 'accès complet'));
                back(['success' => 'Droits mis à jour.']);
        }
        back();
    }
    $q = trim((string)input('q')); $role = (string)input('role'); $f = (string)input('f');
    $w = ['1']; $p = [];
    if ($q !== '') { $w[] = '(u.name LIKE ? OR u.email LIKE ? OR o.name LIKE ?)'; array_push($p, ...array_fill(0, 3, '%' . $q . '%')); }
    if (in_array($role, ['superadmin', 'owner', 'manager', 'scanner'], true)) { $w[] = 'u.role = ?'; $p[] = $role; }
    if ($f === 'locked') $w[] = 'u.locked_until > NOW()';
    if ($f === 'no2fa') $w[] = "u.role IN ('superadmin','owner') AND u.totp_enabled = 0";
    if ($f === 'inactive') $w[] = 'u.active = 0';
    if ($f === 'dormant') $w[] = '(u.last_login IS NULL OR u.last_login < NOW() - INTERVAL 6 MONTH) AND u.active = 1';
    $list = all('SELECT u.*, o.name org_name, (SELECT COUNT(*) FROM user_sessions s WHERE s.user_id = u.id AND s.revoked = 0 AND s.last_seen > NOW() - INTERVAL 14 DAY) sessions
        FROM users u LEFT JOIN organizers o ON o.id = u.organizer_id WHERE ' . implode(' AND ', $w) . " ORDER BY u.role = 'superadmin' DESC, u.last_login IS NULL, u.last_login DESC LIMIT 300", $p);
    $k = one("SELECT COUNT(*) n, SUM(active = 1) active, SUM(role = 'superadmin') admins, SUM(totp_enabled = 1) mfa, SUM(locked_until > NOW()) locked FROM users");
    $fails = one("SELECT SUM(result <> 'ok') fails, COUNT(DISTINCT CASE WHEN result <> 'ok' THEN ip END) ips FROM login_log WHERE created_at >= NOW() - INTERVAL 24 HOUR");
    $suspicious = all("SELECT ip, COUNT(*) n, COUNT(DISTINCT email) emails, MAX(created_at) last FROM login_log WHERE result <> 'ok' AND created_at >= NOW() - INTERVAL 24 HOUR GROUP BY ip HAVING n >= 5 ORDER BY n DESC LIMIT 8");
    $log = all('SELECT l.*, u.name FROM login_log l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 30');
    return admin_page('Utilisateurs & sécurité', 'users', compact('list', 'q', 'me', 'k', 'fails', 'suspicious', 'log', 'role', 'f'), 'users');
}

function settings(): string
{
    $u = require_super();
    $tab = (string)input('tab', 'general');
    if (is_post()) {
        $fields = [
            'general' => ['platform_name', 'tagline', 'contact_email', 'base_url', 'primary_color', 'pending_minutes'],
            'fees' => ['fee_percent', 'fee_fixed', 'fee_mode_default', 'invoice_name', 'invoice_prefix', 'invoice_address', 'invoice_siret', 'invoice_vat_number', 'invoice_vat_rate', 'invoice_footer'],
            'payments' => ['stripe_secret', 'stripe_public', 'paypal_client_id', 'paypal_secret', 'sumup_api_key', 'sumup_merchant_code', 'mollie_api_key'],
            'emails' => ['smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user', 'smtp_pass', 'mail_from', 'mail_from_name'],
            'legal' => ['cgv', 'legal', 'company_info'],
            'sms' => ['sms_api_key', 'sms_price', 'sms_stop'],
            'plateforme' => ['maintenance_message', 'max_events', 'max_upload_mb'],
            'securite' => ['lock_after', 'lock_minutes'],
            'apparence' => ['platform_name', 'tagline', 'primary_color'],
        ][$tab] ?? [];
        $checks = ['general' => ['signup_open', 'org_approval'], 'fees' => ['refund_fees', 'kyc_required', 'invoice_auto'], 'payments' => ['demo_enabled', 'stripe_enabled', 'paypal_enabled', 'paypal_sandbox', 'sumup_enabled', 'mollie_enabled'],
            'plateforme' => ['maintenance'], 'securite' => ['admin_2fa'], 'sms' => ['sms_deduct']][$tab] ?? [];
        if ($tab === 'plateforme') set_setting('disabled_modules', implode(',', array_values(array_intersect(array_keys(Billing::MODULES), (array)($_POST['disabled_modules'] ?? [])))));
        if ($tab === 'securite' && checked('admin_2fa') && !(int)($u['totp_enabled'] ?? 0)) redirect('/admin/reglages?tab=securite', ['error' => 'Active d\'abord la double authentification sur ton propre compte (Mon compte), sinon tu te bloquerais.']);
        if ($tab === 'apparence') {
            if (input('logo_remove')) set_setting('platform_logo', '');
            try { if ($img = upload_image('platform_logo')) set_setting('platform_logo', $img); } catch (RuntimeException $e) { redirect('/admin/reglages?tab=apparence', ['error' => $e->getMessage()]); }
        }
        foreach ($fields as $f) {
            if (!array_key_exists($f, $_POST)) continue;
            $v = trim((string)$_POST[$f]);
            if (in_array($f, ['stripe_secret', 'paypal_secret', 'sumup_api_key', 'mollie_api_key', 'smtp_pass', 'sms_api_key'], true) && $v === '') continue;
            if ($f === 'sms_price') $v = (string)parse_money($v);
            if (in_array($f, ['max_events', 'max_upload_mb', 'lock_after', 'lock_minutes'], true)) $v = (string)max(0, (int)$v); // vide = inchangé
            if ($f === 'fee_fixed') $v = (string)parse_money($v);
            if ($f === 'fee_percent') $v = (string)max(0, min(50, (float)str_replace(',', '.', $v)));
            if ($f === 'base_url') $v = rtrim($v, '/');
            if ($f === 'primary_color' && !preg_match('~^#[0-9a-fA-F]{6}$~', $v)) continue;
            set_setting($f, $v);
        }
        foreach ($checks as $c) set_setting($c, checked($c));
        if (input('test_email')) {
            try { Mailer::send((string)input('test_email'), 'Test ' . setting('platform_name'), '<p>Si tu lis ça, les e-mails marchent.</p>'); redirect('/admin/reglages?tab=emails', ['success' => 'E-mail de test envoyé à ' . input('test_email') . '.']); }
            catch (Throwable $e) { redirect('/admin/reglages?tab=emails', ['error' => 'Échec de l\'envoi : ' . $e->getMessage()]); }
        }
        if ($tab === 'sms' && input('sms_key_clear')) set_setting('sms_api_key', '');
        if ($tab === 'sms' && input('test_sms')) {
            $to = Sms::normalize((string)input('test_sms'));
            try { if (!$to) throw new RuntimeException('numéro de mobile invalide'); $st = Sms::send($to, 'Test ' . setting('platform_name') . ' : les SMS marchent.', 'Entrevo', 'test');
                redirect('/admin/reglages?tab=sms', ['success' => $st === 'test' ? 'Pas de clé : SMS écrit dans le journal « sms » (mode test).' : 'SMS de test envoyé.']); }
            catch (Throwable $e) { redirect('/admin/reglages?tab=sms', ['error' => 'Échec : ' . $e->getMessage()]); }
        }
        redirect('/admin/reglages?tab=' . urlencode($tab), ['success' => 'Réglages enregistrés.']);
    }
    return admin_page('Réglages', 'settings', ['tab' => $tab], 'settings');
}

function updates(): string
{
    $u = require_super();
    $result = null;
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'install') {
            $f = $_FILES['package'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
                $max = ini_get('upload_max_filesize');
                redirect('/admin/mises-a-jour', ['error' => 'Fichier non reçu' . ($f && in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? ' : trop lourd (max ' . $max . ' sur cet hébergement).' : '.')]);
            }
            try {
                $r = Updater::install($f['tmp_name'], checked('force') === 1, (int)$u['id']);
                $_SESSION['update_done'] = $r;
                redirect('/admin/mises-a-jour');
            } catch (Throwable $e) {
                log_line('mises-a-jour', 'Échec : ' . $e->getMessage());
                redirect('/admin/mises-a-jour', ['error' => '' . $e->getMessage()]);
            }
        }
        if ($a === 'rollback') {
            try {
                $meta = Updater::rollback((string)input('backup'));
                insert('update_log', ['version' => (string)($meta['version_avant'] ?? '?'), 'previous' => (string)($meta['version_apres'] ?? '?'), 'action' => 'rollback',
                    'notes' => 'Retour arrière', 'backup' => (string)input('backup'), 'user_id' => $u['id'], 'created_at' => now()]);
                redirect('/admin/mises-a-jour', ['success' => 'Retour à la version ' . ($meta['version_avant'] ?? '?') . ' effectué.']);
            } catch (Throwable $e) { redirect('/admin/mises-a-jour', ['error' => $e->getMessage()]); }
        }
        redirect('/admin/mises-a-jour');
    }
    $result = $_SESSION['update_done'] ?? null;
    unset($_SESSION['update_done']);
    $log = all('SELECT l.*, u.name user_name FROM update_log l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.created_at DESC, l.id DESC LIMIT 30');
    $backups = Updater::backups();
    $limits = ['upload' => ini_get('upload_max_filesize'), 'post' => ini_get('post_max_size'), 'zip' => class_exists('ZipArchive')];
    return admin_page('Mises à jour', 'updates', compact('result', 'log', 'backups', 'limits'), 'updates');
}

function db_backup(): never
{
    require_super();
    $sql = Updater::dumpDatabase();
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="entrevo-sauvegarde-' . date('Ymd-His') . '.sql"');
    header('Content-Length: ' . strlen($sql));
    echo $sql;
    exit;
}
