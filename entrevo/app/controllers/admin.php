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
            back(['success' => '✅ Commission mise à jour (s\'applique aux prochaines commandes).']);
        }
        if ($a === 'approve') {
            update('organizers', ['status' => 'active', 'approved_at' => now()], 'id = ?', [$org['id']]);
            try { Mailer::send((string)$org['email'], '✅ Ton compte ' . setting('platform_name') . ' est validé !', '<p>Bonne nouvelle : ton compte organisateur « ' . e($org['name']) . ' » est validé 🎉</p><p>Tu peux maintenant publier tes événements et vendre tes billets.</p><p><a href="' . e(abs_url('/pro')) . '">Ouvrir mon espace</a></p>'); } catch (Throwable $e) { log_line('emails', $e->getMessage()); }
            back(['success' => '✅ Organisateur validé, il a été prévenu par e-mail.']);
        }
        if ($a === 'note') { update('organizers', ['admin_note' => trim((string)input('admin_note')) ?: null], 'id = ?', [$org['id']]); back(['success' => '📝 Note enregistrée.']); }
        if ($a === 'status') {
            update('organizers', ['status' => $org['status'] === 'active' ? 'suspended' : 'active'], 'id = ?', [$org['id']]);
            back(['success' => $org['status'] === 'active' ? '⛔ Organisateur suspendu : ses pages sont hors ligne.' : '✅ Organisateur réactivé.']);
        }
        if ($a === 'payout') {
            $amount = parse_money(input('amount'));
            if ($amount <= 0) back(['error' => 'Montant invalide.']);
            insert('payouts', ['organizer_id' => $org['id'], 'amount' => $amount, 'reference' => trim((string)input('reference')) ?: null,
                'note' => trim((string)input('note')) ?: null, 'created_by' => $u['id'], 'created_at' => now()]);
            back(['success' => '💸 Reversement de ' . money($amount) . ' enregistré.']);
        }
        if ($a === 'delete_payout') { q('DELETE FROM payouts WHERE id = ? AND organizer_id = ?', [(int)input('payout_id'), $org['id']]); back(['success' => 'Reversement supprimé.']); }
        back();
    }
    $balance = Tickets::balance((int)$org['id']);
    $payouts = all('SELECT p.*, u.name by_name FROM payouts p LEFT JOIN users u ON u.id = p.created_by WHERE p.organizer_id = ? ORDER BY p.created_at DESC', [$org['id']]);
    $events = all("SELECT e.*, (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status='valid') sold FROM events e WHERE organizer_id = ? ORDER BY starts_at DESC", [$org['id']]);
    $users = all('SELECT * FROM users WHERE organizer_id = ? ORDER BY role, name', [$org['id']]);
    [$pct, $fixed] = Tickets::feeRates($org);
    return admin_page($org['name'], 'organizer_detail', compact('org', 'balance', 'payouts', 'events', 'users', 'pct', 'fixed'), 'orgs',
        ['crumbs' => [['Organisateurs', '/admin/organisateurs']],
         'actions' => '<form method="post" action="' . url('/admin/organisateurs/' . $org['id'] . '/consulter') . '">' . csrf_field() . '<button class="btn">👀 Entrer dans son espace</button></form>']);
}

function organizer_impersonate(string $id): never
{
    require_super();
    if (!val('SELECT id FROM organizers WHERE id = ?', [(int)$id])) abort(404);
    $_SESSION['as_org'] = (int)$id;
    redirect('/pro', ['info' => '👀 Tu consultes l\'espace de cet organisateur. Bouton « Retour super-admin » en bas à gauche.']);
}

function stop_impersonate(): never
{
    require_super();
    $id = (int)($_SESSION['as_org'] ?? 0);
    unset($_SESSION['as_org']);
    redirect($id ? '/admin/organisateurs/' . $id : '/admin');
}

function orders(): string
{
    require_super();
    $q = trim((string)input('q'));
    $sql = 'SELECT o.*, e.name ev_name, g.name org_name FROM orders o JOIN events e ON e.id = o.event_id JOIN organizers g ON g.id = o.organizer_id';
    $p = [];
    if ($q !== '') { $sql .= ' WHERE o.ref LIKE ? OR o.email LIKE ? OR o.last_name LIKE ? OR g.name LIKE ?'; $p = array_fill(0, 4, '%' . $q . '%'); }
    $list = all($sql . ' ORDER BY o.created_at DESC LIMIT 300', $p);
    return admin_page('Toutes les commandes', 'orders', compact('list', 'q'), 'orders');
}

function users(): string
{
    $me = require_super();
    if (is_post()) {
        $a = (string)input('action');
        if ($a === 'add_admin') {
            $email = strtolower(trim((string)input('email')));
            if (!is_email($email) || trim((string)input('name')) === '' || mb_strlen((string)input('password')) < 8) back(['error' => 'Nom, e-mail valide et mot de passe (8 caractères min.) requis.']);
            if (val('SELECT id FROM users WHERE email = ?', [$email])) back(['error' => 'Cet e-mail est déjà utilisé.']);
            insert('users', ['organizer_id' => null, 'email' => $email, 'name' => trim((string)input('name')), 'password_hash' => password_hash((string)input('password'), PASSWORD_DEFAULT),
                'role' => 'superadmin', 'active' => 1, 'created_at' => now()]);
            back(['success' => '✅ Super-admin ajouté.']);
        }
        $t = one('SELECT * FROM users WHERE id = ?', [(int)input('user_id')]);
        if ($t && $t['id'] != $me['id']) {
            if ($a === 'toggle') update('users', ['active' => $t['active'] ? 0 : 1], 'id = ?', [$t['id']]);
            if ($a === 'password') {
                $pass = strtolower(random_code(10));
                update('users', ['password_hash' => password_hash($pass, PASSWORD_DEFAULT)], 'id = ?', [$t['id']]);
                back(['success' => '🔑 Nouveau mot de passe pour ' . $t['email'] . ' : ' . $pass]);
            }
        }
        back(['success' => '✅ C\'est fait.']);
    }
    $q = trim((string)input('q'));
    $sql = 'SELECT u.*, o.name org_name FROM users u LEFT JOIN organizers o ON o.id = u.organizer_id';
    $p = [];
    if ($q !== '') { $sql .= ' WHERE u.name LIKE ? OR u.email LIKE ? OR o.name LIKE ?'; $p = array_fill(0, 3, '%' . $q . '%'); }
    $list = all($sql . " ORDER BY u.role = 'superadmin' DESC, u.created_at DESC LIMIT 300", $p);
    return admin_page('Utilisateurs', 'users', compact('list', 'q', 'me'), 'users');
}

function settings(): string
{
    $u = require_super();
    $tab = (string)input('tab', 'general');
    if (is_post()) {
        $fields = [
            'general' => ['platform_name', 'tagline', 'contact_email', 'base_url', 'primary_color', 'pending_minutes'],
            'fees' => ['fee_percent', 'fee_fixed', 'fee_mode_default'],
            'payments' => ['stripe_secret', 'stripe_public', 'paypal_client_id', 'paypal_secret', 'sumup_api_key', 'sumup_merchant_code', 'mollie_api_key'],
            'emails' => ['smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user', 'smtp_pass', 'mail_from', 'mail_from_name'],
            'legal' => ['cgv', 'legal', 'company_info'],
        ][$tab] ?? [];
        $checks = ['general' => ['signup_open', 'org_approval'], 'fees' => ['refund_fees'], 'payments' => ['demo_enabled', 'stripe_enabled', 'paypal_enabled', 'paypal_sandbox', 'sumup_enabled', 'mollie_enabled']][$tab] ?? [];
        foreach ($fields as $f) {
            if (!array_key_exists($f, $_POST)) continue;
            $v = trim((string)$_POST[$f]);
            if (in_array($f, ['stripe_secret', 'paypal_secret', 'sumup_api_key', 'mollie_api_key', 'smtp_pass'], true) && $v === '') continue; // vide = inchangé
            if ($f === 'fee_fixed') $v = (string)parse_money($v);
            if ($f === 'fee_percent') $v = (string)max(0, min(50, (float)str_replace(',', '.', $v)));
            if ($f === 'base_url') $v = rtrim($v, '/');
            if ($f === 'primary_color' && !preg_match('~^#[0-9a-fA-F]{6}$~', $v)) continue;
            set_setting($f, $v);
        }
        foreach ($checks as $c) set_setting($c, checked($c));
        if (input('test_email')) {
            try { Mailer::send((string)input('test_email'), '📬 Test ' . setting('platform_name'), '<p>Si tu lis ça, les e-mails marchent. 🎉</p>'); redirect('/admin/reglages?tab=emails', ['success' => '📬 E-mail de test envoyé à ' . input('test_email') . '.']); }
            catch (Throwable $e) { redirect('/admin/reglages?tab=emails', ['error' => 'Échec de l\'envoi : ' . $e->getMessage()]); }
        }
        redirect('/admin/reglages?tab=' . urlencode($tab), ['success' => '✅ Réglages enregistrés.']);
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
                redirect('/admin/mises-a-jour', ['error' => '😬 ' . $e->getMessage()]);
            }
        }
        if ($a === 'rollback') {
            try {
                $meta = Updater::rollback((string)input('backup'));
                insert('update_log', ['version' => (string)($meta['version_avant'] ?? '?'), 'previous' => (string)($meta['version_apres'] ?? '?'), 'action' => 'rollback',
                    'notes' => 'Retour arrière', 'backup' => (string)input('backup'), 'user_id' => $u['id'], 'created_at' => now()]);
                redirect('/admin/mises-a-jour', ['success' => '↩️ Retour à la version ' . ($meta['version_avant'] ?? '?') . ' effectué.']);
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
