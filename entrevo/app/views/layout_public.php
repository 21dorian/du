<?php $u = user(); $pname = (string)setting('platform_name'); ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? '') ? $title . ' · ' . $pname : $pname) ?></title>
<?php if (!empty($description)): ?><meta name="description" content="<?= e($description) ?>"><?php endif; ?>
<?php if (!empty($og_image)): ?><meta property="og:image" content="<?= e($og_image) ?>"><?php endif; ?>
<script>document.documentElement.classList.add('js')</script>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="icon" href="<?= asset('img/icon.svg') ?>" type="image/svg+xml">
<?php $siteOn = !empty($site) && class_exists('Site') && Site::on($site); $siteS = $siteOn ? Site::settings($site) : []; ?>
<style>:root{--primary:<?= e($siteOn && !empty($site['accent']) && preg_match('~^#[0-9a-fA-F]{6}$~', (string)$site['accent']) ? $site['accent'] : setting('primary_color', '#0071e3')) ?>}</style>
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
        <?php if (setting('signup_open') === '1'): ?><a class="btn btn-sm btn-ghost" href="<?= url('/organisateurs') ?>">Organisateurs</a><?php endif; ?>
      <?php endif; ?>
    </nav>
  </div>
</header>
<?php if ($siteOn): $here = route_path(); ?>
<div class="site-bar"><div class="wrap site-bar-in">
  <a class="site-brand" href="<?= url('/o/' . $site['slug']) ?>"><?php if ($site['logo']): ?><img src="<?= e(url($site['logo'])) ?>" alt=""><?php endif; ?><b><?= e($site['name']) ?></b></a>
  <nav class="site-nav"><?php foreach (Site::menu($site) as [$label, $href]): $on = $here === $href || ($href !== '/o/' . $site['slug'] && str_starts_with($here, $href)); ?><a href="<?= url($href) ?>"<?= $on ? ' class="on" aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
</div></div>
<?php endif; ?>
<?= $hero ?? '' ?>
<main class="wrap main"><?= render_flash() ?><?= $content ?></main>
<?php if ($siteOn): $soc = json_decode((string)($site['socials'] ?? ''), true) ?: []; ?>
<section class="site-foot"><div class="wrap site-foot-in">
  <div class="site-foot-id"><b><?= e($site['name']) ?></b><?php if ($siteS['footer']): ?><p><?= nl2br(e($siteS['footer'])) ?></p><?php endif; ?>
    <?php if ($soc): ?><div class="site-soc"><?php foreach ($soc as $k => $u): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener"><?= e(ucfirst((string)$k)) ?></a><?php endforeach; ?></div><?php endif; ?></div>
  <?php if ($siteS['newsletter']): ?><form class="site-nl" method="post" action="<?= url('/o/' . $site['slug'] . '/newsletter') ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e(route_path()) ?>"><input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <b>Lettre d'information</b><small><?= e($siteS['newsletter_text']) ?></small>
    <div class="site-nl-row"><input type="email" name="email" required placeholder="ton@email.fr" aria-label="Ton adresse e-mail"><button class="btn">S'inscrire</button></div></form><?php endif; ?>
</div></section>
<?php endif; ?>
<footer class="footer">
  <div class="wrap footer-in">
    <div><b><?= e($pname) ?></b> — <?= e(setting('tagline')) ?></div>
    <div class="footer-links">
      <a href="<?= url('/mes-billets') ?>">Billets perdus ?</a>
      <?php if (setting('cgv')): ?><a href="<?= url('/cgv') ?>">CGV</a><?php endif; ?>
      <?php if (setting('legal')): ?><a href="<?= url('/mentions-legales') ?>">Mentions légales</a><?php endif; ?>
      <a href="<?= url('/contact') ?>">Contact</a>
      <a href="<?= url('/donnees-personnelles') ?>">Mes données</a>
      <?php try { foreach (all('SELECT slug, title FROM pages WHERE published = 1 AND in_footer = 1 ORDER BY sort, title LIMIT 8') as $fp): ?><a href="<?= url('/page/' . $fp['slug']) ?>"><?= e($fp['title']) ?></a><?php endforeach; } catch (Throwable $ex) { /* avant migration */ } ?>
      <a href="<?= url('/organisateurs') ?>">Vendre mes billets</a>
    </div>
    <div class="footer-links footer-soon">
      <a href="<?= url('/bientot/aide') ?>">Centre d'aide <em>bientôt</em></a>
      <a href="<?= url('/bientot/appli') ?>">Appli mobile <em>bientôt</em></a>
      <a href="<?= url('/bientot/langues-public') ?>">English <em>bientôt</em></a>
    </div>
  </div>
</footer>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
