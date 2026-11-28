<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (!app_installed()) {
    header('Location: ' . base_path() . '/install.php');
    exit;
}

function route_path(): string
{
    static $p = null;
    if ($p === null) {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = base_path();
        if ($base !== '' && str_starts_with($uri, $base)) $uri = substr($uri, strlen($base));
        if (str_starts_with($uri, '/index.php')) $uri = substr($uri, 10);
        $p = '/' . trim(rawurldecode($uri), '/');
        // 2.5 : marque blanche — la racine d'un domaine personnalisé sert la page de l'organisateur
        if (in_array($p, ['/', '/evenements'], true) && class_exists('WhiteLabel') && ($wl = WhiteLabel::org())) $p = '/o/' . $wl['slug'];
        // 3.0 : sous-domaine Entrevo Pay — toutes les adresses sont servies par l'espace cashless
        elseif (class_exists('Pay') && Pay::isHost()) {
            $shared = ['/pay', '/assets', '/caisse', '/api/caisse', '/recu', '/paiement', '/commande', '/uploads', '/cron', '/connexion/code', '/mot-de-passe'];
            $keep = false; foreach ($shared as $x) if ($p === $x || str_starts_with($p, $x . '/')) $keep = true;
            if (!$keep) $p = rtrim('/pay' . $p, '/');
        }
    }
    return $p;
}

start_session();

// Mise à jour de la base de données après une mise à jour des fichiers
try {
    if ((int)setting('db_version', 0) < latest_migration()) { run_migrations(); setting('__reset'); }
} catch (Throwable $e) {
    log_line('erreurs', 'Migration : ' . $e->getMessage());
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// tâches de fond après l'envoi de la page (sans ralentir le visiteur)
register_shutdown_function(function () {
    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
    try { Jobs::run(); } catch (Throwable $e) { log_line('taches', $e->getMessage()); }
});

// 2.5 : marque blanche — les pages « plateforme » renvoient vers l'accueil de l'organisateur
if (class_exists('WhiteLabel') && ($wlOrg = WhiteLabel::org()) && (in_array(route_path(), ['/organisateurs', '/inscription'], true) || (str_starts_with(route_path(), '/o/') && !str_starts_with(route_path() . '/', '/o/' . $wlOrg['slug'] . '/')))) redirect('/');
$routes = require APP . '/routes.php';
$path = route_path();
I18n::boot($path); // 4.2 : billetterie FR / EN
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 2.6 : application caisse native (Capacitor) — requêtes d'une autre origine, authentifiées par jeton d'appareil (sans cookie)
if ($path === '/api/caisse' || $path === '/sante') { // 4.3 : /sante sert aussi au premier lancement de l'appli Entrevo Pro
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if (in_array($origin, ['capacitor://localhost', 'https://localhost', 'http://localhost', 'ionic://localhost'], true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Headers: Content-Type, X-Pos-Token');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Max-Age: 86400');
    }
    if ($method === 'OPTIONS') { http_response_code(204); exit; }
}

// 2.2 : mode maintenance — le public voit une page d'attente ; super-admin, contrôle d'accès et paiements continuent de fonctionner
if (setting('maintenance') === '1' && !is_super()) {
    $open = ['/connexion', '/deconnexion', '/mon-compte', '/admin', '/scan', '/api/scan', '/api/caisse', '/caisse', '/cron', '/paiement/webhook', '/paiement/retour', '/mot-de-passe'];
    $exempt = false;
    foreach ($open as $o) if ($path === $o || str_starts_with($path, $o . '/')) $exempt = true;
    $u = user();
    if ($u && $u['role'] === 'scanner') $exempt = true;
    if (!$exempt) {
        http_response_code(503);
        header('Retry-After: 1800');
        echo view('layout_public', ['title' => 'Maintenance', 'content' => '<div class="card center auth-box"><h1>On revient très vite</h1><p class="muted">' . nl2br(e((string)(setting('maintenance_message') ?: 'La billetterie fait une courte pause pour maintenance. Tes billets restent valables et le contrôle à l\'entrée fonctionne normalement.'))) . '</p></div>']);
        exit;
    }
}

try {
    foreach ($routes as [$verbs, $pattern, $handler]) {
        if (!in_array($method, explode('|', $verbs), true)) continue;
        $regex = '~^' . preg_replace('~\{(\w+)\}~', '(?P<$1>[^/]+)', $pattern) . '$~';
        if (!preg_match($regex, $path, $m)) continue;
        $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
        // exemptés : notifications de paiement, API à clé, désinscription « en un clic » (jeton secret dans l'URL)
        if ($method === 'POST' && !str_starts_with($path, '/paiement/webhook') && !str_starts_with($path, '/api/v1/') && !str_starts_with($path, '/widget/achat/') && $path !== '/api/caisse' && !str_starts_with($path, '/desinscription/')) csrf_check();
        [$file, $fn] = explode('@', $handler);
        require_once APP . '/controllers/' . $file . '.php';
        $out = $fn(...array_values($params));
        if (is_string($out)) echo a11y_html(I18n::html($out));
        exit;
    }
    abort(404);
} catch (Throwable $e) {
    log_line('erreurs', get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    if (str_starts_with($path, '/api/')) json_out(['error' => 'Erreur serveur : ' . $e->getMessage()], 500);
    $msg = config('debug') ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : 'Une erreur est survenue. Elle a été enregistrée ; réessayez dans un instant.';
    echo view('layout_public', ['title' => 'Erreur', 'content' => '<div class="card center"><h1>Oups…</h1><p>' . e($msg) . '</p><p><a class="btn" href="' . url('/') . '">Accueil</a></p></div>']);
}
