<?php $u = user(); $pname = (string)setting('platform_name'); ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? '') ? $title . ' · ' . $pname : $pname) ?></title>
<?php if (!empty($description)): ?><meta name="description" content="<?= e($description) ?>"><?php endif; ?>
<?php if (!empty($og_image)): ?><meta property="og:image" content="<?= e($og_image) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="icon" href="<?= asset('img/icon.svg') ?>" type="image/svg+xml">
<style>:root{--primary:<?= e(setting('primary_color', '#ff5a4e')) ?>}</style>
<?php if (!empty($org_tracking)) echo view('partials/tracking', ['o' => $org_tracking]); ?>
</head>
<body class="pub">
<header class="topbar">
  <div class="wrap topbar-in">
    <a class="logo" href="<?= url('/') ?>"><?= view('partials/logo') ?><span><?= e($pname) ?></span></a>
    <nav class="topnav">
      <a href="<?= url('/') ?>">Événements</a>
      <a href="<?= url('/mes-billets') ?>">Mes billets</a>
      <?php if ($u): ?>
        <a class="btn btn-sm" href="<?= url($u['role'] === 'superadmin' ? '/admin' : ($u['role'] === 'scanner' ? '/scan' : '/pro')) ?>">Mon espace</a>
      <?php else: ?>
        <a href="<?= url('/connexion') ?>">Connexion</a>
        <?php if (setting('signup_open') === '1'): ?><a class="btn btn-sm btn-ghost" href="<?= url('/inscription') ?>">Organisateurs</a><?php endif; ?>
      <?php endif; ?>
    </nav>
  </div>
</header>
<?= $hero ?? '' ?>
<main class="wrap main"><?= render_flash() ?><?= $content ?></main>
<footer class="footer">
  <div class="wrap footer-in">
    <div><b><?= e($pname) ?></b> — <?= e(setting('tagline')) ?></div>
    <div class="footer-links">
      <a href="<?= url('/mes-billets') ?>">Billets perdus ?</a>
      <?php if (setting('cgv')): ?><a href="<?= url('/cgv') ?>">CGV</a><?php endif; ?>
      <?php if (setting('legal')): ?><a href="<?= url('/mentions-legales') ?>">Mentions légales</a><?php endif; ?>
      <?php if (setting('contact_email')): ?><a href="mailto:<?= e(setting('contact_email')) ?>">Contact</a><?php endif; ?>
      <a href="<?= url('/inscription') ?>">Vendre mes billets</a>
    </div>
  </div>
</footer>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
