<?php
declare(strict_types=1);

require_once APP . '/controllers/public.php'; // pub(), order helpers

function home_for(array $u): string
{
    return match ($u['role']) { 'superadmin' => '/admin', 'scanner' => '/scan', default => '/pro' };
}

function safe_next(string $next, string $fallback): string
{
    // on n'accepte qu'un chemin interne
    if ($next !== '' && str_starts_with($next, '/') && !str_starts_with($next, '//')) {
        $b = base_path();
        if ($b !== '' && str_starts_with($next, $b)) $next = substr($next, strlen($b)) ?: '/';
        return $next;
    }
    return $fallback;
}

function login(): string
{
    if ($u = user()) redirect(home_for($u));
    $error = null;
    if (is_post()) {
        if (too_many_attempts()) {
            $error = 'Trop d\'essais Pause café de 15 minutes, et on réessaie.';
        } else {
            $u = one('SELECT * FROM users WHERE email = ?', [strtolower(trim((string)input('email')))]);
            if ($u && $u['active'] && password_verify((string)input('password'), $u['password_hash'])) {
                clear_attempts();
                if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                    update('users', ['password_hash' => password_hash((string)input('password'), PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
                    $u = one('SELECT * FROM users WHERE id = ?', [$u['id']]);
                }
                login_user($u);
                header('Location: ' . url(safe_next((string)input('suite'), home_for($u))), true, 303);
                exit;
            }
            record_failed_attempt();
            $error = $u && !$u['active'] ? 'Ce compte est désactivé' : 'E-mail ou mot de passe incorrect. Respire, et réessaie';
        }
    }
    return pub('login', ['error' => $error], 'Connexion');
}

function logout(): never
{
    logout_user();
    redirect('/connexion', ['success' => 'À plus Tu es déconnecté.']);
}

function signup(): string
{
    if (setting('signup_open') !== '1') abort(404, 'Les inscriptions sont fermées pour le moment.');
    if ($u = user()) redirect(home_for($u));
    $errors = [];
    if (is_post()) {
        $org = trim((string)input('org_name'));
        $name = trim((string)input('name'));
        $email = strtolower(trim((string)input('email')));
        $pass = (string)input('password');
        if ($org === '') $errors[] = 'Il manque le nom de ton organisation.';
        if ($name === '') $errors[] = 'Il manque ton nom.';
        if (!is_email($email)) $errors[] = 'Cette adresse e-mail a l\'air bizarre';
        elseif (val('SELECT id FROM users WHERE email = ?', [$email])) $errors[] = 'Tu as déjà un compte avec cet e-mail Connecte-toi (ou fais « mot de passe oublié »).';
        if (mb_strlen($pass) < 8) $errors[] = 'Mot de passe trop court : 8 caractères minimum. « 1234 », c\'est non.';
        if (empty($_POST['terms'])) $errors[] = 'Coche les conditions d\'utilisation pour continuer.';
        if (!$errors) {
            $orgId = insert('organizers', ['name' => $org, 'slug' => unique_slug('organizers', $org), 'email' => $email,
                'phone' => trim((string)input('phone')), 'fee_mode' => setting('fee_mode_default') === 'organizer' ? 'organizer' : 'buyer',
                'status' => setting('org_approval') === '1' ? 'pending' : 'active', 'created_at' => now()]);
            $uid = insert('users', ['organizer_id' => $orgId, 'email' => $email, 'name' => $name,
                'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'role' => 'owner', 'active' => 1, 'created_at' => now()]);
            login_user(one('SELECT * FROM users WHERE id = ?', [$uid]));
            try {
                Mailer::send($email, 'Bienvenue sur ' . setting('platform_name'), '<p>Salut ' . e($name) . '</p><p>Ton espace <b>' . e($org) . '</b> est prêt. Crée ton premier événement, publie-le, et regarde les ventes tomber.</p><p><a href="' . e(abs_url('/pro')) . '">J\'y vais</a></p>');
                if (setting('contact_email')) Mailer::send((string)setting('contact_email'), 'Nouvel organisateur : ' . $org, '<p>' . e($org) . ' (' . e($name) . ', ' . e($email) . ') vient de créer son compte.</p>');
            } catch (Throwable $e) { log_line('emails', 'Inscription : ' . $e->getMessage()); }
            redirect('/pro/evenements/nouveau', ['success' => 'Bienvenue ! On commence par ton premier événement ?']);
        }
    }
    [$pct, $fixed] = Tickets::feeRates(['fee_percent' => null, 'fee_fixed' => null]);
    return pub('signup', ['errors' => $errors, 'pct' => $pct, 'fixed' => $fixed], 'Créer mon espace organisateur');
}

function forgot(): string
{
    $sent = false;
    if (is_post()) {
        $u = one('SELECT * FROM users WHERE email = ? AND active = 1', [strtolower(trim((string)input('email')))]);
        if ($u) {
            $tok = token(24);
            update('users', ['reset_token' => hash('sha256', $tok), 'reset_expires' => date('Y-m-d H:i:s', time() + 3600)], 'id = ?', [$u['id']]);
            try {
                Mailer::send($u['email'], 'Ton nouveau mot de passe', '<p>Salut ' . e($u['name']) . ',</p><p>Trou de mémoire ? Ça arrive. Clique ici pour en choisir un nouveau (lien valable 1 heure) :</p><p><a href="' . e(abs_url('/mot-de-passe/' . $tok)) . '">Choisir mon nouveau mot de passe</a></p><p>Pas toi ? Ignore cet e-mail, rien ne change.</p>');
            } catch (Throwable $e) { log_line('emails', 'Mot de passe : ' . $e->getMessage()); }
        }
        $sent = true;
    }
    return pub('forgot', ['sent' => $sent], 'Mot de passe oublié');
}

function reset_password(string $tok): string
{
    $u = one('SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()', [hash('sha256', $tok)]);
    if (!$u) return pub('message', ['title' => 'Lien expiré', 'text' => 'Ce lien a fait son temps. Redemande-en un, c\'est rapide.', 'link' => url('/mot-de-passe-oublie'), 'link_label' => 'Nouveau lien'], 'Lien expiré');
    $error = null;
    if (is_post()) {
        $p = (string)input('password');
        if (mb_strlen($p) < 8) $error = 'Mot de passe trop court : 8 caractères minimum.';
        elseif ($p !== (string)input('password2')) $error = 'Les deux mots de passe ne sont pas pareils';
        else {
            update('users', ['password_hash' => password_hash($p, PASSWORD_DEFAULT), 'reset_token' => null, 'reset_expires' => null], 'id = ?', [$u['id']]);
            login_user(one('SELECT * FROM users WHERE id = ?', [$u['id']]));
            redirect(home_for($u), ['success' => 'Nouveau mot de passe enregistré. Note-le quelque part (pas sur un post-it).']);
        }
    }
    return pub('reset', ['error' => $error], 'Nouveau mot de passe');
}

function profile(): string
{
    $u = require_login();
    if (is_post()) {
        $name = trim((string)input('name'));
        $email = strtolower(trim((string)input('email')));
        if ($name === '' || !is_email($email)) back(['error' => 'Nom ou e-mail invalide.']);
        if (val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $u['id']])) back(['error' => 'Cette adresse est déjà utilisée par un autre compte.']);
        $data = ['name' => $name, 'email' => $email];
        $new = (string)input('new_password');
        if ($new !== '') {
            if (!password_verify((string)input('current_password'), $u['password_hash'])) back(['error' => 'Mot de passe actuel incorrect.']);
            if (mb_strlen($new) < 8) back(['error' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.']);
            $data['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
        }
        update('users', $data, 'id = ?', [$u['id']]);
        if (isset($data['password_hash'])) login_user(one('SELECT * FROM users WHERE id = ?', [$u['id']]));
        redirect('/mon-compte', ['success' => 'C\'est enregistré.']);
    }
    $area = $u['role'] === 'superadmin' ? 'admin' : 'pro';
    if ($u['role'] === 'scanner') return pub('profile', ['u' => $u], 'Mon compte');
    return view('layout_back', ['title' => 'Mon compte', 'area' => $area, 'content' => view('public/profile', ['u' => $u])]);
}
