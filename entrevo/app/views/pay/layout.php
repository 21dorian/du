<?php /** @var array $m, $events; ?array $ev; string $active, $title, $content */
$u = user(); $org = $m['org'];
$ic = [
    'live' => 'M3 12h4l3-8 4 16 3-8h4', 'wallets' => 'M3 7h18v12H3zM3 7l3-4h12l3 4M16 13h2', 'refunds' => 'M4 12a8 8 0 1 0 3-6.2M4 4v4h4', 'packs' => 'M4 10h16v10H4zM2 7h20v3H2zM12 7v13',
    'credits' => 'M4 4h16v16H4zM8 9h8M8 13h8M8 17h4', 'ecocups' => 'M7 4h10l-1.5 16h-7zM8 9h8', 'catalogue' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z', 'consignes' => 'M8 3h8l-1 18H9zM8 8h8',
    'remises' => 'M5 19L19 5M7 9a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z', 'appareils' => 'M4 21V5l8-3 8 3v16M9 21v-5h6v5', 'caisses' => 'M3 6h18v12H3zM3 10h18M7 15h3',
    'stocks' => 'M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4M12 11v10', 'materiel' => 'M7 2h10v20H7zM11 18h2', 'equipe' => 'M16 11a4 4 0 1 0-4-4M8 11a3 3 0 1 0-3-3M2 21v-1a5 5 0 0 1 5-5h2M10 21v-1a6 6 0 0 1 12 0v1',
    'exploitants' => 'M3 21V9l9-6 9 6v12M9 21v-6h6v6', 'rapport' => 'M4 20V10M10 20V4M16 20v-7M22 20H2', 'ventes' => 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6', 'exports' => 'M12 3v12m0 0l-5-5m5 5l5-5M4 17v3h16v-3',
    'reglages' => 'M12 15.5A3.5 3.5 0 1 1 15.5 12 3.5 3.5 0 0 1 12 15.5zM12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M5 19l2-2M17 7l2-2', 'bracelets' => 'M5 8a7 4 0 0 1 14 0v8a7 4 0 0 1-14 0zM5 8a7 4 0 0 0 14 0',
    'reassorts' => 'M3 7l9-4 9 4v10l-9 4-9-4zM12 11v6M9 14l3 3 3-3', 'bilan' => 'M6 3h9l4 4v14H6zM9 12h7M9 16h5M9 8h3', 'journal' => 'M12 8v4l3 2M12 3a9 9 0 1 0 9 9M21 3v5h-5',
    'team' => 'M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z', 'home' => 'M3 11l9-8 9 8v10H3z', 'portal' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18',
];
$svg = fn($k) => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' . ($ic[$k] ?? $ic['live']) . '"/></svg>';
$groups = [];
if ($ev) foreach (PAY_SECTIONS as $slug => [$label, $perm, $group]) if (Pay::can($m, $perm)) $groups[$group][] = [$slug, $label, $perm];
$isAdmin = in_array('*', Pay::ROLES[$m['role']][1], true);
$evLink = fn($slug) => Pay::url('/pro/e/' . $ev['id'] . ($slug !== '' ? '/' . $slug : ''));
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Entrevo Pay</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/pay.css') ?>">
<link rel="icon" href="<?= asset('img/pay.svg') ?>" type="image/svg+xml">
</head>
<body class="back pay">
<input type="checkbox" id="menu" class="menu-toggle" hidden>
<aside class="pay-side">
  <a class="pay-brand" href="<?= Pay::url('/pro', ['tous' => 1]) ?>"><span class="pay-logo"><svg viewBox="0 0 32 32"><rect width="32" height="32" rx="9"/><path d="M9 22V10h7.5a4.5 4.5 0 0 1 0 9H13" /></svg></span><span><b>Entrevo Pay</b><small><?= e($org['name']) ?></small></span></a>
  <?php if ($ev): ?>
    <details class="pay-evsel"><summary><span class="dot<?= (int)$ev['cashless_enabled'] ? ' on' : '' ?>"></span><span class="grow"><b><?= e($ev['name']) ?></b><small><?= e(fr_date($ev['starts_at'], false)) ?></small></span><i>▾</i></summary>
      <div class="pay-evlist"><?php foreach ($events as $x): ?><a href="<?= Pay::url('/pro/e/' . $x['id']) ?>" class="<?= (int)$x['id'] === (int)$ev['id'] ? 'on' : '' ?>"><?= e($x['name']) ?><small><?= e(fr_date($x['starts_at'], false)) ?></small></a><?php endforeach; ?>
        <a class="all" href="<?= Pay::url('/pro', ['tous' => 1]) ?>">Tous les événements</a></div></details>
  <?php endif; ?>
  <nav class="pay-nav">
    <?php if (!$ev): ?><a href="<?= Pay::url('/pro', ['tous' => 1]) ?>" class="<?= $active === 'home' ? 'on' : '' ?>"><?= $svg('home') ?>Événements</a><?php endif; ?>
    <?php foreach ($groups as $g => $items): ?>
      <div class="pay-group"><?= e($g) ?></div>
      <?php foreach ($items as [$slug, $label, $perm]): ?><a href="<?= $evLink($slug) ?>" class="<?= $active === $slug ? 'on' : '' ?>"><?= $svg($slug === '' ? 'live' : ($slug === 'porte-monnaie' ? 'wallets' : ($slug === 'remboursements' ? 'refunds' : ($slug === 'portail' ? 'portal' : $slug)))) ?><?= e($label) ?><?= $slug === '' ? '<i class="pay-live-dot"></i>' : '' ?></a><?php endforeach; ?>
    <?php endforeach; ?>
    <div class="pay-group">Organisation</div>
    <?php if (Pay::can($m, 'bracelets')): ?><a href="<?= Pay::url('/pro/bracelets') ?>" class="<?= $active === 'org-bracelets' ? 'on' : '' ?>"><?= $svg('bracelets') ?>Stock de bracelets</a><?php endif; ?>
    <?php if (Pay::can($m, 'exploitants')): ?><a href="<?= Pay::url('/pro/exploitants') ?>" class="<?= $active === 'org-exploitants' ? 'on' : '' ?>"><?= $svg('exploitants') ?>Fiches exploitants</a><?php endif; ?>
    <?php if ($isAdmin): ?><a href="<?= Pay::url('/pro/equipe') ?>" class="<?= $active === 'org-equipe' ? 'on' : '' ?>"><?= $svg('team') ?>Équipe cashless</a><?php endif; ?>
  </nav>
  <div class="pay-foot">
    <div class="pay-user"><span class="avatar"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></span><span class="grow"><?= e($u['name']) ?><small><?= e(Pay::ROLES[$m['role']][0]) ?></small></span></div>
    <div class="pay-links">
      <?php if (in_array($u['role'], ['owner', 'manager', 'superadmin'], true)): ?><a href="<?= e(abs_url('/pro')) ?>">Billetterie</a><?php endif; ?>
      <a href="<?= Pay::url('') ?>" target="_blank">Portail public</a>
      <form method="post" action="<?= Pay::url('/deconnexion') ?>"><?= csrf_field() ?><button class="linklike">Déconnexion</button></form>
    </div>
    <small class="ver">Entrevo Pay · v<?= e(app_version()) ?></small>
  </div>
</aside>
<label for="menu" class="menu-btn" aria-label="Menu"><span></span><span></span><span></span></label>
<label for="menu" class="menu-overlay"></label>
<main class="pay-main">
  <header class="pay-top">
    <div class="grow"><?php if ($ev): ?><small class="pay-crumb"><?= e($ev['name']) ?></small><?php endif; ?><h1><?= e($title) ?></h1></div>
    <?php if (!empty($live)): ?><span class="pay-livebadge"><i></i>En direct · <b data-live="at">—</b></span><?php endif; ?>
    <?php if ($ev): ?><a class="btn btn-sm btn-light" href="<?= url('/caisse/') ?>" target="_blank">Application caisse</a><?php endif; ?>
  </header>
  <div class="toasts" aria-live="polite"><?= render_flash() ?></div>
  <?= $content ?>
</main>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/charts.js') ?>"></script>
<script src="<?= asset('js/pay.js') ?>"></script>
</body>
</html>
