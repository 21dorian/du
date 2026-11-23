<?php
declare(strict_types=1);
// Assistant d'installation d'Entrevo. Se désactive automatiquement une fois l'installation terminée.
require __DIR__ . '/app/bootstrap.php';

$alreadyInstalled = app_installed();
$checks = [
    ['PHP 8.1 ou plus récent', version_compare(PHP_VERSION, '8.1.0', '>='), 'Version actuelle : ' . PHP_VERSION . '. Changez la version de PHP dans cPanel (« Sélectionner une version de PHP »).'],
    ['Extension PDO MySQL', extension_loaded('pdo_mysql'), 'Activez « pdo_mysql » dans cPanel.'],
    ['Extension cURL (paiements)', extension_loaded('curl'), 'Activez « curl » dans cPanel.'],
    ['Extension Zip (mises à jour)', class_exists('ZipArchive'), 'Activez « zip » dans cPanel.'],
    ['Extension mbstring', extension_loaded('mbstring'), 'Activez « mbstring » dans cPanel.'],
    ['Extension iconv (PDF)', function_exists('iconv'), 'Activez « iconv » dans cPanel.'],
    ['Dossier storage/ accessible en écriture', is_writable(ROOT . '/storage'), 'Donnez les droits 755 au dossier storage/.'],
    ['Dossier uploads/ accessible en écriture', is_writable(ROOT . '/uploads'), 'Donnez les droits 755 au dossier uploads/.'],
    ['Dossier principal accessible en écriture (config.php)', is_writable(ROOT), 'Donnez les droits 755 au dossier d\'Entrevo.'],
];
$ok = !in_array(false, array_column($checks, 1), true);
$errors = [];
$done = false;
$v = fn($k, $d = '') => htmlspecialchars((string)($_POST[$k] ?? $d), ENT_QUOTES);

if (!$alreadyInstalled && $ok && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $db = ['host' => trim($_POST['db_host'] ?? 'localhost'), 'port' => (int)($_POST['db_port'] ?? 3306) ?: 3306,
        'name' => trim($_POST['db_name'] ?? ''), 'user' => trim($_POST['db_user'] ?? ''), 'pass' => (string)($_POST['db_pass'] ?? '')];
    $platform = trim($_POST['platform'] ?? 'Entrevo') ?: 'Entrevo';
    $name = trim($_POST['admin_name'] ?? '');
    $email = trim($_POST['admin_email'] ?? '');
    $pass = (string)($_POST['admin_pass'] ?? '');
    if (!$db['name'] || !$db['user']) $errors[] = 'Indiquez le nom de la base et l\'utilisateur MySQL.';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Indiquez votre nom et une adresse e-mail valide.';
    if (strlen($pass) < 8) $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';
    if (!$errors) {
        try {
            $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']), $db['user'], $db['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            if ((int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'orders'")->fetchColumn() > 0) {
                throw new RuntimeException('Cette base contient déjà une installation d\'Entrevo. Utilisez une base vide.');
            }
        } catch (Throwable $e) {
            $errors[] = 'Connexion à la base impossible : ' . $e->getMessage();
        }
    }
    if (!$errors) {
        $config = "<?php\n// Configuration d'Entrevo (générée par l'installation). Ne partagez jamais ce fichier.\nreturn " . var_export([
            'db' => $db,
            'secret' => bin2hex(random_bytes(32)),
            'debug' => false,
        ], true) . ";\n";
        file_put_contents(ROOT . '/config.php', $config);
        @chmod(ROOT . '/config.php', 0640);
        try {
            run_migrations();
            setting('__reset');
            set_setting('platform_name', $platform);
            set_setting('contact_email', $email);
            if (!empty($_POST['base_url'])) set_setting('base_url', rtrim(trim($_POST['base_url']), '/'));
            insert('users', ['organizer_id' => null, 'email' => $email, 'name' => $name, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                'role' => 'superadmin', 'active' => 1, 'created_at' => now()]);
            if (!empty($_POST['demo'])) Demo::seed($email);
            $done = true;
        } catch (Throwable $e) {
            @unlink(ROOT . '/config.php');
            $errors[] = 'Erreur pendant l\'installation : ' . $e->getMessage();
        }
    }
}
$self = htmlspecialchars(base_path() . '/install.php');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$guessUrl = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . base_path();
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation d'Entrevo</title>
<link rel="stylesheet" href="<?= htmlspecialchars(base_path()) ?>/assets/css/app.css">
</head><body class="pub"><main class="wrap main">
<div class="auth-box wide">
  <div class="logo" style="justify-content:center;margin-bottom:20px"><?php require APP . '/views/partials/logo.php'; ?><span>Entrevo</span></div>
  <?php if ($done): ?>
    <div class="card center">
      <div class="state-mark">✓</div>
      <h1>Installation terminée !</h1>
      <p class="muted">Votre billetterie est prête. Connectez-vous avec votre adresse e-mail et votre mot de passe.</p>
      <p><a class="btn btn-lg" href="<?= htmlspecialchars(base_path()) ?>/connexion">Se connecter</a></p>
      <p class="muted" style="font-size:13px">Par sécurité, ce fichier d'installation est désormais désactivé.</p>
    </div>
  <?php elseif ($alreadyInstalled): ?>
    <div class="card center"><h1>Déjà installé</h1><p class="muted">Entrevo est déjà installé. Pour réinstaller, supprimez le fichier config.php (attention : utilisez une base vide).</p>
    <a class="btn" href="<?= htmlspecialchars(base_path()) ?>/">Aller à la billetterie</a></div>
  <?php else: ?>
    <div class="card">
      <h1>Installation</h1>
      <p class="muted">Bienvenue ! Ce formulaire configure votre billetterie en une minute. Créez d'abord une base MySQL vide dans cPanel (« Bases de données MySQL ») avec un utilisateur ayant tous les droits.</p>
      <h3>1. Vérification de l'hébergement</h3>
      <?php foreach ($checks as [$label, $pass, $help]): ?>
        <div class="list-item"><span style="font-size:18px"><?= $pass ? '✅' : '❌' ?></span><div class="grow"><?= htmlspecialchars($label) ?><?php if (!$pass): ?><br><small style="color:var(--red)"><?= htmlspecialchars($help) ?></small><?php endif; ?></div></div>
      <?php endforeach; ?>
    </div>
    <?php if ($ok): ?>
    <form class="card" method="post" action="<?= $self ?>">
      <?php if ($errors): ?><div class="flash error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div><?php endif; ?>
      <h3>2. Base de données MySQL</h3>
      <div class="grid2">
        <div class="field"><label>Serveur</label><input name="db_host" value="<?= $v('db_host', 'localhost') ?>" required><small>Presque toujours « localhost ».</small></div>
        <div class="field"><label>Port</label><input name="db_port" value="<?= $v('db_port', '3306') ?>"></div>
        <div class="field"><label>Nom de la base</label><input name="db_name" value="<?= $v('db_name') ?>" required placeholder="moncompte_entrevo"></div>
        <div class="field"><label>Utilisateur</label><input name="db_user" value="<?= $v('db_user') ?>" required placeholder="moncompte_entrevo"></div>
        <div class="field"><label>Mot de passe de la base</label><input type="password" name="db_pass" value="<?= $v('db_pass') ?>"></div>
      </div>
      <h3>3. Votre plateforme</h3>
      <div class="grid2">
        <div class="field"><label>Nom de la plateforme</label><input name="platform" value="<?= $v('platform', 'Entrevo') ?>" required></div>
        <div class="field"><label>Adresse du site</label><input name="base_url" value="<?= $v('base_url', $guessUrl) ?>"><small>Utilisée dans les e-mails et pour les paiements.</small></div>
      </div>
      <h3>4. Votre compte super-administrateur</h3>
      <div class="grid2">
        <div class="field"><label>Votre nom</label><input name="admin_name" value="<?= $v('admin_name') ?>" required></div>
        <div class="field"><label>E-mail</label><input type="email" name="admin_email" value="<?= $v('admin_email') ?>" required></div>
        <div class="field"><label>Mot de passe (8 caractères min.)</label><input type="password" name="admin_pass" required minlength="8"></div>
      </div>
      <label class="check"><input type="checkbox" name="demo" value="1" <?= isset($_POST['demo']) || !$_POST ? 'checked' : '' ?>> Ajouter des données de démonstration (un organisateur et quelques événements, supprimables ensuite)</label>
      <div style="height:16px"></div>
      <button class="btn btn-lg btn-block">Installer Entrevo</button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
</main></body></html>
