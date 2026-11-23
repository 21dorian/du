<?php
// Routeur pour le serveur PHP intégré (développement uniquement) : php -S 127.0.0.1:8080 entrevo-outils/routeur-dev.php
$root = dirname(__DIR__) . '/entrevo';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('~^/(app|vendor|storage)(/|$)|^/config\.php~', $path)) { http_response_code(403); exit('403'); }
if ($path !== '/' && is_file($root . $path) && !str_ends_with($path, '.php')) {
    $types = ['css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml', 'woff2' => 'font/woff2', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'webmanifest' => 'application/manifest+json'];
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if (isset($types[$ext])) header('Content-Type: ' . $types[$ext]);
    readfile($root . $path);
    return true;
}
if (in_array($path, ['/install.php', '/maj-secours.php'], true)) { $_SERVER['SCRIPT_NAME'] = $path; require $root . $path; return true; }
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
