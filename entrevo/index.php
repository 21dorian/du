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

$routes = require APP . '/routes.php';
$path = route_path();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    foreach ($routes as [$verbs, $pattern, $handler]) {
        if (!in_array($method, explode('|', $verbs), true)) continue;
        $regex = '~^' . preg_replace('~\{(\w+)\}~', '(?P<$1>[^/]+)', $pattern) . '$~';
        if (!preg_match($regex, $path, $m)) continue;
        $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
        if ($method === 'POST' && !str_starts_with($path, '/paiement/webhook')) csrf_check();
        [$file, $fn] = explode('@', $handler);
        require_once APP . '/controllers/' . $file . '.php';
        $out = $fn(...array_values($params));
        if (is_string($out)) echo $out;
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
