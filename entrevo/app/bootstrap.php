<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP', __DIR__);
define('STORAGE', ROOT . '/storage');
define('APP_NAME', 'Entrevo');

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');

// Chargement automatique des classes (bibliothèques intégrées + classes de l'application)
spl_autoload_register(function (string $class): void {
    $map = [
        'chillerlan\\QRCode\\' => ROOT . '/vendor/chillerlan/php-qrcode/',
        'chillerlan\\Settings\\' => ROOT . '/vendor/chillerlan/settings/',
        'PHPMailer\\PHPMailer\\' => ROOT . '/vendor/phpmailer/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) require $file;
            return;
        }
    }
    if ($class === 'FPDF') { require ROOT . '/vendor/fpdf/fpdf.php'; return; }
    $file = APP . '/lib/' . $class . '.php';
    if (is_file($file)) require $file;
});

require APP . '/helpers.php';
require APP . '/db.php';
require APP . '/auth.php';

function app_installed(): bool { return is_file(ROOT . '/config.php'); }

function config(?string $key = null, $default = null)
{
    static $cfg = null;
    if ($cfg === null) $cfg = app_installed() ? (require ROOT . '/config.php') : [];
    if ($key === null) return $cfg;
    return $cfg[$key] ?? $default;
}

if (config('debug')) { ini_set('display_errors', '1'); error_reporting(E_ALL); }
else { ini_set('display_errors', '0'); }
ini_set('log_errors', '1');
if (is_dir(STORAGE . '/logs')) ini_set('error_log', STORAGE . '/logs/php-errors.log');
