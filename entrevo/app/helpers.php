<?php
declare(strict_types=1);

// ---------- Affichage ----------
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function base_path(): string
{
    static $b = null;
    if ($b === null) {
        $b = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    }
    return $b;
}

function url(string $path = '/', array $query = []): string
{
    $u = base_path() . '/' . ltrim($path, '/');
    if ($query) $u .= (str_contains($u, '?') ? '&' : '?') . http_build_query($query);
    return $u;
}

function abs_url(string $path = '/', array $query = []): string
{
    $base = rtrim((string)setting('base_url', ''), '/');
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $base = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path();
    }
    $u = $base . '/' . ltrim($path, '/');
    if ($query) $u .= (str_contains($u, '?') ? '&' : '?') . http_build_query($query);
    return $u;
}

function asset(string $path): string
{
    $f = ROOT . '/assets/' . $path;
    return url('assets/' . $path) . '?v=' . (is_file($f) ? filemtime($f) : app_version());
}

function app_version(): string
{
    static $v = null;
    if ($v === null) $v = trim((string)@file_get_contents(ROOT . '/VERSION')) ?: '0.0.0';
    return $v;
}

function redirect(string $to, array $flash = []): never
{
    foreach ($flash as $type => $msg) flash($type, $msg);
    if (!preg_match('~^https?://~', $to)) $to = url($to);
    header('Location: ' . $to, true, 303);
    exit;
}

function back(array $flash = []): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === explode(':', $host)[0]) {
        foreach ($flash as $type => $msg) flash($type, $msg);
        header('Location: ' . $ref, true, 303);
        exit;
    }
    redirect('/', $flash);
}

function flash(?string $type = null, ?string $msg = null)
{
    if ($type !== null) { $_SESSION['flash'][] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function render_flash(): string
{
    $h = '';
    foreach (flash() as [$t, $m]) $h .= '<div class="flash ' . e($t) . '">' . e($m) . '</div>';
    return $h;
}

/** Rend une vue (app/views/NAME.php) avec des variables. */
function view(string $name, array $vars = []): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require APP . '/views/' . $name . '.php';
    return (string)ob_get_clean();
}

function json_out($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function abort(int $code, string $msg = ''): never
{
    http_response_code($code);
    $titles = [403 => '🚫 Pas par ici', 404 => '🤷 Page introuvable', 500 => '😵 Oups'];
    $default = [403 => 'Cette porte est réservée au staff.', 404 => 'Cette page a quitté la soirée sans prévenir.'][$code] ?? 'Un truc a coincé.';
    echo view('layout_public', ['title' => strip_tags($titles[$code] ?? 'Erreur'), 'content' =>
        '<div class="card center auth-box"><h1>' . e($titles[$code] ?? 'Erreur') . '</h1><p class="muted">' . e($msg ?: $default) . '</p><p><a class="btn" href="' . url('/') . '">Retour à l\'accueil</a></p></div>']);
    exit;
}

// ---------- Requête ----------
function input(string $key, $default = '')
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function checked(string $key): int { return !empty($_POST[$key]) ? 1 : 0; }

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Ta session a expiré ⏳ Recharge la page et recommence.');
    }
}

// ---------- Réglages de la plateforme ----------
function setting_defaults(): array
{
    return [
        'platform_name' => APP_NAME,
        'tagline' => 'Des billets, des QR codes, et des soirées réussies 🎟️',
        'contact_email' => '',
        'base_url' => '',
        'company_info' => '',
        'fee_percent' => '3',
        'fee_fixed' => '50',
        'fee_mode_default' => 'buyer',
        'pending_minutes' => '20',
        'refund_fees' => '0',
        'demo_enabled' => '1',
        'stripe_enabled' => '0', 'stripe_secret' => '', 'stripe_public' => '',
        'paypal_enabled' => '0', 'paypal_client_id' => '', 'paypal_secret' => '', 'paypal_sandbox' => '1',
        'sumup_enabled' => '0', 'sumup_api_key' => '', 'sumup_merchant_code' => '',
        'mollie_enabled' => '0', 'mollie_api_key' => '',
        'smtp_host' => '', 'smtp_port' => '587', 'smtp_secure' => 'tls', 'smtp_user' => '', 'smtp_pass' => '',
        'mail_from' => '', 'mail_from_name' => '',
        'cgv' => '',
        'legal' => '',
        'signup_open' => '1',
        'primary_color' => '#0032fa',
    ];
}

function setting(string $key, $default = null)
{
    static $cache = null;
    if ($key === '__reset') { $cache = null; return null; }
    if ($cache === null) {
        $cache = setting_defaults();
        try { foreach (all('SELECT k, v FROM settings') as $r) $cache[$r['k']] = $r['v']; } catch (Throwable $e) { /* pas encore installé */ }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, $value): void
{
    q('REPLACE INTO settings (k, v) VALUES (?, ?)', [$key, (string)$value]);
    setting('__reset');
}

// ---------- Formats ----------
function money(?int $cents): string
{
    $v = number_format(($cents ?? 0) / 100, 2, ',', "\u{202F}");
    return $v . "\u{00A0}€";
}

function parse_money($v): int
{
    $v = str_replace([' ', "\u{00A0}", "\u{202F}", '€'], '', (string)$v);
    $v = str_replace(',', '.', $v);
    if ($v === '' || !is_numeric($v)) return 0;
    return max(0, (int)round((float)$v * 100));
}

function money_input(?int $cents): string { return number_format(($cents ?? 0) / 100, 2, ',', ''); }

function now(): string { return date('Y-m-d H:i:s'); }

function dt_input(?string $dt): string { return $dt ? date('Y-m-d\TH:i', strtotime($dt)) : ''; }
function dt_from_input($v): ?string { $v = trim((string)$v); if ($v === '') return null; $t = strtotime($v); return $t ? date('Y-m-d H:i:s', $t) : null; }

function fr_date(?string $dt, bool $time = true): string
{
    if (!$dt) return '';
    $t = strtotime($dt);
    $jours = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $s = $jours[(int)date('w', $t)] . ' ' . date('j', $t) . ' ' . $mois[(int)date('n', $t)] . ' ' . date('Y', $t);
    if ($time) $s .= ' à ' . date('H\hi', $t);
    return $s;
}

function short_date(?string $dt): string { return $dt ? date('d/m/y H:i', strtotime($dt)) : ''; }

function slugify(string $s): string
{
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = strtolower(preg_replace('~[^a-zA-Z0-9]+~', '-', $s));
    return trim(substr($s, 0, 80), '-') ?: 'evenement';
}

function unique_slug(string $table, string $base, ?int $exceptId = null): string
{
    $slug = slugify($base);
    $i = 1;
    $candidate = $slug;
    while (val("SELECT id FROM `$table` WHERE slug = ?" . ($exceptId ? ' AND id <> ' . (int)$exceptId : ''), [$candidate])) {
        $candidate = $slug . '-' . (++$i);
    }
    return $candidate;
}

function random_code(int $len): string
{
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < $len; $i++) $s .= $alpha[random_int(0, strlen($alpha) - 1)];
    return $s;
}

function token(int $bytes = 20): string { return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '='); }

function is_email(string $s): bool { return (bool)filter_var($s, FILTER_VALIDATE_EMAIL); }

function badge(string $status): string
{
    $map = [
        'paid' => ['Payée', 'green'], 'pending' => ['En attente', 'orange'], 'cancelled' => ['Annulée', 'red'],
        'refunded' => ['Remboursée', 'red'], 'expired' => ['Expirée', 'grey'], 'valid' => ['Valide', 'green'],
        'draft' => ['Brouillon', 'grey'], 'published' => ['En vente', 'green'], 'closed' => ['Clôturé', 'orange'],
        'active' => ['Actif', 'green'], 'suspended' => ['Suspendu', 'red'],
    ];
    [$l, $c] = $map[$status] ?? [$status, 'grey'];
    return '<span class="badge ' . $c . '">' . e($l) . '</span>';
}

function method_label(string $m): string { return ['online' => 'En ligne', 'guichet' => 'Guichet', 'invitation' => 'Invitation', 'partner' => 'Partenaire', 'gratuit' => 'Gratuit', 'free' => 'Gratuit', 'import' => 'Import', 'accred' => 'Accréditation', 'resale' => 'Revente'][$m] ?? $m; }
function role_label(string $r): string { return ['superadmin' => 'Super-admin', 'owner' => 'Responsable', 'manager' => 'Gestionnaire', 'scanner' => 'Contrôleur'][$r] ?? $r; }
function provider_label(?string $p): string { return ['stripe' => 'Stripe', 'paypal' => 'PayPal', 'sumup' => 'SumUp', 'mollie' => 'Mollie', 'demo' => 'Démo', 'free' => 'Gratuit'][$p ?? ''] ?? (string)$p; }

function csv_download(string $filename, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($rows as $r) fputcsv($out, $r, ';', '"', '\\');
    fclose($out);
    exit;
}

function upload_image(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Envoi de l\'image impossible (code ' . $f['error'] . ').');
    if ($f['size'] > 5 * 1024 * 1024) throw new RuntimeException('Image trop lourde (5 Mo maximum).');
    $info = @getimagesize($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$info['mime'] ?? ''] ?? null;
    if (!$ext) throw new RuntimeException('Format d\'image non accepté (JPG, PNG, WebP ou GIF).');
    $dir = ROOT . '/uploads/' . date('Y-m');
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = token(12) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Enregistrement de l\'image impossible.');
    return 'uploads/' . date('Y-m') . '/' . $name;
}

function log_line(string $file, string $line): void
{
    @file_put_contents(STORAGE . '/logs/' . $file . '.log', '[' . now() . '] ' . $line . "\n", FILE_APPEND);
}

/** « il y a 5 min », « hier », … */
function ago(?string $dt): string
{
    if (!$dt) return '';
    $s = time() - strtotime($dt);
    if ($s < 60) return 'à l\'instant';
    if ($s < 3600) return 'il y a ' . intdiv($s, 60) . ' min';
    if ($s < 86400) return 'il y a ' . intdiv($s, 3600) . ' h';
    if ($s < 172800) return 'hier';
    if ($s < 7 * 86400) return 'il y a ' . intdiv($s, 86400) . ' j';
    return date('d/m/y', strtotime($dt));
}
