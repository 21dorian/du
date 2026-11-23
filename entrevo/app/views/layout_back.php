<?php
// Gabarit de l'espace organisateur (area = pro) et du super-admin (area = admin)
$u = user();
$org = current_org();
$area = $area ?? 'pro';
$active = $active ?? '';
if ($area === 'admin') {
    $nav = [
        ['dash', '/admin', 'Vue d\'ensemble', 'M3 13h8V3H3zm10 8h8V11h-8zM3 21h8v-6H3zm10-18v6h8V3z'],
        ['orgs', '/admin/organisateurs', 'Organisateurs', 'M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm-8 0a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm0 2c-2.7 0-8 1.3-8 4v3h8v-3c0-1 .4-2.1 1.3-3A11 11 0 0 0 8 13zm8 0c-2.7 0-8 1.3-8 4v3h16v-3c0-2.7-5.3-4-8-4z'],
        ['orders', '/admin/commandes', 'Commandes', 'M7 4h10l3 4v12H4V8zm0 0v4h13M8 12h8M8 16h5'],
        ['users', '/admin/utilisateurs', 'Utilisateurs', 'M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z'],
        ['settings', '/admin/reglages', 'Réglages', 'M12 15.5A3.5 3.5 0 1 1 15.5 12 3.5 3.5 0 0 1 12 15.5zm7.4-2.5a7.6 7.6 0 0 0 0-2l2.1-1.6-2-3.5-2.5 1a7.4 7.4 0 0 0-1.7-1L15 3h-4l-.4 2.7a7.4 7.4 0 0 0-1.7 1l-2.5-1-2 3.5L6.5 11a7.6 7.6 0 0 0 0 2l-2.1 1.6 2 3.5 2.5-1a7.4 7.4 0 0 0 1.7 1L11 21h4l.4-2.7a7.4 7.4 0 0 0 1.7-1l2.5 1 2-3.5z'],
        ['updates', '/admin/mises-a-jour', 'Mises à jour', 'M12 3v12m0 0l-5-5m5 5l5-5M4 17v3h16v-3'],
    ];
} else {
    $nav = [
        ['dash', '/pro', 'Tableau de bord', 'M3 13h8V3H3zm10 8h8V11h-8zM3 21h8v-6H3zm10-18v6h8V3z'],
        ['events', '/pro/evenements', 'Événements', 'M7 2v3M17 2v3M3 8h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z'],
        ['orders', '/pro/commandes', 'Commandes', 'M7 4h10l3 4v12H4V8zm0 0v4h13M8 12h8M8 16h5'],
        ['scan', '/scan', 'Contrôle d\'accès', 'M3 7V3h4M17 3h4v4M21 17v4h-4M7 21H3v-4M7 12h10'],
        ['finances', '/pro/finances', 'Finances', 'M3 6h18v12H3zM3 10h18M7 15h3'],
        ['team', '/pro/equipe', 'Équipe', 'M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z'],
        ['org', '/pro/organisation', 'Mon organisation', 'M4 21V5l8-3 8 3v16M9 21v-5h6v5M8 9h2M14 9h2M8 13h2M14 13h2'],
    ];
    if ($u['role'] === 'manager') $nav = array_values(array_filter($nav, fn($n) => !in_array($n[0], ['team', 'org', 'finances'], true)));
}
$pname = (string)setting('platform_name');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($pname) ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="icon" href="<?= asset('img/icon.svg') ?>" type="image/svg+xml">
<style>:root{--primary:<?= e(setting('primary_color', '#0032fa')) ?>}</style>
</head>
<body class="back">
<input type="checkbox" id="menu" class="menu-toggle" hidden>
<aside class="side">
  <a class="logo side-logo" href="<?= url($area === 'admin' ? '/admin' : '/pro') ?>"><?= view('partials/logo') ?><span><?= e($pname) ?></span></a>
  <?php if ($area === 'pro' && $org): ?>
    <div class="side-org"><small><?= $area === 'pro' ? 'Organisateur' : '' ?></small><b><?= e($org['name']) ?></b></div>
  <?php elseif ($area === 'admin'): ?>
    <div class="side-org"><small>Espace</small><b>Super-admin</b></div>
  <?php endif; ?>
  <nav class="side-nav">
    <?php foreach ($nav as [$key, $href, $label, $icon]): ?>
      <a href="<?= url($href) ?>" class="<?= $key === $active ? 'on' : '' ?>"><svg viewBox="0 0 24 24" width="20" height="20"><path d="<?= $icon ?>" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"/></svg><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="side-foot">
    <?php if (is_super() && $area === 'pro'): ?>
      <form method="post" action="<?= url('/admin/quitter') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost-light">← Retour super-admin</button></form>
    <?php elseif (is_super() && $org): ?>
      <a class="btn btn-sm btn-ghost-light" href="<?= url('/pro') ?>">Espace de <?= e($org['name']) ?></a>
    <?php endif; ?>
    <a class="side-user" href="<?= url('/mon-compte') ?>"><span class="avatar"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></span><span><?= e($u['name']) ?><small><?= e(role_label($u['role'])) ?></small></span></a>
    <div class="side-links">
      <a href="<?= url('/') ?>" target="_blank">Voir le site ↗</a>
      <form method="post" action="<?= url('/deconnexion') ?>"><?= csrf_field() ?><button class="linklike">Déconnexion</button></form>
    </div>
    <small class="ver">v<?= e(app_version()) ?></small>
  </div>
</aside>
<label for="menu" class="menu-btn" aria-label="Menu"><span></span><span></span><span></span></label>
<label for="menu" class="menu-overlay"></label>
<main class="back-main">
  <div class="probar">
    <button type="button" class="probar-search" id="cmdk-open"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 20l-4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Rechercher un événement, une commande, un participant…</span><kbd>Ctrl K</kbd></button>
    <div class="probar-actions">
      <?php if ($area === 'pro'): ?>
      <details class="menu"><summary class="btn btn-sm">+ Créer</summary><div class="menu-list">
        <a href="<?= url('/pro/evenements/nouveau') ?>">📅 Un événement</a>
        <a href="<?= url('/pro/evenements') ?>">💵 Une vente au guichet</a>
        <a href="<?= url('/pro/evenements') ?>">🎁 Des invitations</a>
        <a href="<?= url('/pro/equipe') ?>">👥 Un compte d'équipe</a>
      </div></details>
      <?php endif; ?>
      <details class="menu notif" id="notif"><summary class="probar-icon" aria-label="Notifications"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4zM10 20a2 2 0 0 0 4 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg><b class="notif-count" hidden></b></summary>
        <div class="menu-list notif-list"><p class="muted">Chargement…</p></div></details>
    </div>
  </div>
  <?php if (!empty($crumbs)): ?><div class="crumbs"><?php foreach ($crumbs as $i => [$l, $h]): ?><?= $i ? ' <span>/</span> ' : '' ?><?= $h ? '<a href="' . url($h) . '">' . e($l) . '</a>' : e($l) ?><?php endforeach; ?></div><?php endif; ?>
  <div class="page-head">
    <h1><?= e($title) ?></h1>
    <?php if (!empty($actions)): ?><div class="page-actions"><?= $actions ?></div><?php endif; ?>
  </div>
  <div class="toasts" aria-live="polite"><?= render_flash() ?></div>
  <?= $content ?>
</main>
<div class="cmdk" id="cmdk" hidden><div class="cmdk-box" role="dialog" aria-label="Recherche">
  <input id="cmdk-input" placeholder="Rechercher… (événement, n° de commande, nom, e-mail, code billet)" autocomplete="off">
  <div id="cmdk-results" class="cmdk-results"><p class="muted">Tape au moins 2 caractères. <kbd>Échap</kbd> pour fermer.</p></div>
</div></div>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/charts.js') ?>"></script>
<script src="<?= asset('js/pro.js') ?>" data-base="<?= e(base_path()) ?>" data-area="<?= e($area) ?>" id="pro-js"></script>
</body>
</html>
