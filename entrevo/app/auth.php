<?php
declare(strict_types=1);

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('entrevo');
    session_set_cookie_params([
        'lifetime' => 14 * 86400, 'path' => base_path() . '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $https,
    ]);
    $dir = STORAGE . '/sessions';
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);
        ini_set('session.gc_maxlifetime', (string)(14 * 86400));
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
    }
    session_start();
}

function user(): ?array
{
    static $u = false;
    if ($u === false || isset($GLOBALS['__reload_user'])) {
        unset($GLOBALS['__reload_user']);
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $u = one('SELECT * FROM users WHERE id = ? AND active = 1', [$_SESSION['uid']]);
            if ($u && ($_SESSION['pwv'] ?? '') !== substr(hash('sha256', $u['password_hash']), 0, 16)) $u = null; // mot de passe changé
            if ($u && class_exists('Security') && !Security::touch($u)) $u = null; // session révoquée à distance
            if (!$u) unset($_SESSION['uid']);
        }
    }
    return $u;
}

function login_user(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['pwv'] = substr(hash('sha256', $u['password_hash']), 0, 16);
    unset($_SESSION['as_org']);
    update('users', ['last_login' => now()], 'id = ?', [$u['id']]);
    if (class_exists('Security')) Security::openSession($u);
    $GLOBALS['__reload_user'] = true;
}

function logout_user(): void
{
    if (class_exists('Security')) Security::closeSession();
    $_SESSION = [];
    session_regenerate_id(true);
}

function is_super(): bool { $u = user(); return $u && $u['role'] === 'superadmin'; }

/** Organisateur courant (pour un super-admin : celui qu'il consulte). */
function current_org(): ?array
{
    static $cache = [];
    $u = user();
    if (!$u) return null;
    $id = $u['role'] === 'superadmin' ? (int)($_SESSION['as_org'] ?? 0) : (int)$u['organizer_id'];
    if (!$id) return null;
    return $cache[$id] ??= one('SELECT * FROM organizers WHERE id = ?', [$id]);
}

function require_login(array $roles = []): array
{
    $u = user();
    if (!$u) {
        if (str_starts_with(route_path(), '/api/')) json_out(['error' => 'Non connecté'], 401);
        redirect('/connexion?suite=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }
    if ($roles && !in_array($u['role'], $roles, true) && $u['role'] !== 'superadmin') {
        if (str_starts_with(route_path(), '/api/')) json_out(['error' => 'Accès refusé'], 403);
        if ($u['role'] === 'scanner') redirect('/scan');
        abort(403, 'Votre rôle ne permet pas d\'accéder à cette page.');
    }
    return $u;
}

/** Espace organisateur : renvoie l'organisateur, ou redirige le super-admin vers la liste. */
function require_org(array $roles = ['owner', 'manager']): array
{
    require_login($roles);
    $org = current_org();
    if (!$org) {
        if (is_super()) redirect('/admin/organisateurs', ['info' => 'Choisissez un organisateur à consulter.']);
        abort(403);
    }
    if ($org['status'] === 'suspended' && !is_super()) {
        abort(403, 'Ce compte organisateur est suspendu. Contactez la plateforme.');
    }
    return $org;
}

/** Charge un événement appartenant à l'organisateur courant. */
function org_event(int $id): array
{
    $org = current_org();
    $ev = $org ? one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [$id, $org['id']]) : null;
    if (!$ev) abort(404, 'Événement introuvable.');
    return $ev;
}

// Limitation des tentatives de connexion (par IP)
function throttle_key(): string { return STORAGE . '/logs/.throttle-' . md5($_SERVER['REMOTE_ADDR'] ?? 'x'); }
function too_many_attempts(): bool
{
    $f = throttle_key();
    if (!is_file($f)) return false;
    [$n, $t] = array_map('intval', explode(':', (string)file_get_contents($f)) + [0, 0]);
    if (time() - $t > 900) { @unlink($f); return false; }
    return $n >= 8;
}
function record_failed_attempt(): void
{
    $f = throttle_key();
    [$n, $t] = is_file($f) ? array_map('intval', explode(':', (string)file_get_contents($f)) + [0, 0]) : [0, time()];
    if (time() - $t > 900) { $n = 0; $t = time(); }
    @file_put_contents($f, ($n + 1) . ':' . $t);
}
function clear_attempts(): void { @unlink(throttle_key()); }
