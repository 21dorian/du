<?php
// Entrevo — retour arrière de secours après une mise à jour.
// Ce fichier est volontairement autonome (aucune dépendance) : il fonctionne même si la mise à jour a cassé le site.
// Il n'est utilisable qu'avec le jeton temporaire (1 heure) affiché après une installation.
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
$dir = __DIR__ . '/storage/maj';
$file = $dir . '/secours.json';
$msg = '';
$ok = false;
$info = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
$jeton = (string)($_GET['jeton'] ?? $_POST['jeton'] ?? '');
if (!$info || empty($info['token']) || !hash_equals((string)$info['token'], $jeton) || (int)$info['expires'] < time()) {
    http_response_code(403);
    $msg = 'Lien de secours invalide ou expiré. Pour revenir en arrière, utilise la page Super-admin › Mises à jour.';
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $zipFile = $dir . '/' . basename((string)$info['backup']);
    $zip = new ZipArchive();
    if (!preg_match('~^avant-[\w.-]+\.zip$~', basename($zipFile)) || $zip->open($zipFile) !== true) {
        $msg = 'Sauvegarde introuvable.';
    } else {
        $meta = json_decode((string)$zip->getFromName('restauration.json'), true) ?: [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (!str_starts_with($n, 'fichiers/') || str_ends_with($n, '/') || str_contains($n, '..')) continue;
            $abs = __DIR__ . '/' . substr($n, 9);
            if (!is_dir(dirname($abs))) mkdir(dirname($abs), 0755, true);
            file_put_contents($abs, $zip->getFromIndex($i));
        }
        $zip->close();
        foreach ($meta['crees'] ?? [] as $rel) {
            $rel = (string)$rel;
            if ($rel !== '' && !str_contains($rel, '..') && is_file(__DIR__ . '/' . $rel)) unlink(__DIR__ . '/' . $rel);
        }
        if (function_exists('opcache_reset')) @opcache_reset();
        @unlink($file);
        @file_put_contents(__DIR__ . '/storage/logs/mises-a-jour.log', '[' . date('Y-m-d H:i:s') . '] Retour arrière de secours vers ' . ($meta['version_avant'] ?? '?') . "\n", FILE_APPEND);
        $ok = true;
        $msg = 'Retour à la version ' . ($meta['version_avant'] ?? 'précédente') . ' effectué.';
    }
}
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Secours — Entrevo</title>
<style>body{font-family:system-ui,Arial,sans-serif;background:#0c0c0c;color:#f3f1ec;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:20px}
.box{max-width:480px;border:1px solid #333;padding:28px;background:#151515}h1{margin-top:0;text-transform:uppercase}button,a.b{background:#ff5a4e;color:#111;border:0;padding:14px 22px;font-weight:800;text-transform:uppercase;cursor:pointer;text-decoration:none;display:inline-block}</style></head>
<body><div class="box">
<h1>🛟 Secours</h1>
<?php if ($msg): ?><p><?= $h($msg) ?></p><?php if ($ok): ?><a class="b" href="./admin/mises-a-jour">Retour à l'administration</a><?php endif; ?>
<?php else: ?>
<p>La mise à jour vers la version <b><?= $h($info['version'] ?? '') ?></b> pose problème ? Ce bouton remet les fichiers d'avant. Tes données ne sont pas touchées.</p>
<form method="post"><input type="hidden" name="jeton" value="<?= $h($jeton) ?>"><button>Revenir à la version précédente</button></form>
<?php endif; ?>
</div></body></html>
