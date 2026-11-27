<?php /** @var string $title, $content, $color; ?array $ev */ ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f0f23">
<title><?= e($title) ?> · Entrevo Pay</title>
<link rel="icon" href="<?= asset('img/pay.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('css/pay-portal.css') ?>">
<style>:root{--brand:<?= e($color) ?>}</style>
</head>
<body>
<header class="pp-top"><a href="<?= Pay::url('') ?>" class="pp-brand"><svg viewBox="0 0 32 32"><rect width="32" height="32" rx="9"/><path d="M9 22V10h7.5a4.5 4.5 0 0 1 0 9H13"/></svg><span>Entrevo Pay</span></a><?php if ($ev): ?><span class="pp-ev"><?= e($ev['name']) ?></span><?php endif; ?></header>
<main class="pp-main" id="contenu">
  <div class="pp-flash"><?= render_flash() ?></div>
  <?= $content ?>
</main>
<footer class="pp-foot"><?= e((string)setting('platform_name', 'Entrevo')) ?> · paiement sécurisé · <a href="<?= url('/donnees-personnelles') ?>">Données personnelles</a></footer>
</body>
</html>
