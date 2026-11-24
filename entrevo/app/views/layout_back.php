<?php
// Gabarit de l'espace organisateur (area = pro) et du super-admin (area = admin)
$u = user();
$org = current_org();
$area = $area ?? 'pro';
$active = $active ?? '';
// Icônes du menu (tracés SVG 24×24)
$I = [
    'dash' => 'M3 13h8V3H3zm10 8h8V11h-8zM3 21h8v-6H3zm10-18v6h8V3z',
    'events' => 'M7 2v3M17 2v3M3 8h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z',
    'orders' => 'M7 4h10l3 4v12H4V8zm0 0v4h13M8 12h8M8 16h5',
    'people' => 'M16 11a4 4 0 1 0-4-4M8 11a3 3 0 1 0-3-3M2 21v-1a5 5 0 0 1 5-5h2M10 21v-1a6 6 0 0 1 12 0v1',
    'scan' => 'M3 7V3h4M17 3h4v4M21 17v4h-4M7 21H3v-4M7 12h10',
    'radar' => 'M12 12l6-6M12 3a9 9 0 1 0 9 9M12 7a5 5 0 1 0 5 5',
    'hand' => 'M8 13V5a2 2 0 0 1 4 0v6m0-1V4a2 2 0 0 1 4 0v7m0-3a2 2 0 0 1 4 0v5a8 8 0 0 1-8 8h-1a7 7 0 0 1-6-3l-3-5a2 2 0 0 1 3-2l2 2',
    'mail' => 'M3 6h18v12H3zM3 7l9 6 9-6',
    'sms' => 'M4 5h16v11H9l-5 4zM8 10h8',
    'globe' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18',
    'bag' => 'M5 8h14l-1 13H6zM9 8V6a3 3 0 0 1 6 0v2',
    'card' => 'M3 6h18v12H3zM7 6v12M11 10h6M11 14h4',
    'gift' => 'M4 10h16v10H4zM2 7h20v3H2zM12 7v13M12 7c-2-4-6-3-5 0M12 7c2-4 6-3 5 0',
    'quote' => 'M6 3h9l4 4v14H6zM9 12h7M9 16h5',
    'star' => 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z',
    'share' => 'M18 8a3 3 0 1 0-3-3M6 15a3 3 0 1 0 3 3M18 22a3 3 0 1 0-3-3M8.6 13.5l6.8 4M15.4 6.5l-6.8 4',
    'mic' => 'M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3zM5 11a7 7 0 0 0 14 0M12 18v3',
    'wallet' => 'M3 7h18v12H3zM3 7l3-4h12l3 4M16 13h2',
    'box' => 'M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4M12 11v10',
    'money' => 'M3 6h18v12H3zM3 10h18M7 15h3',
    'invoice' => 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6M9 16h3',
    'chart' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
    'budget' => 'M12 3v18M17 7H9.5a3 3 0 0 0 0 6h5a3 3 0 0 1 0 6H6',
    'check' => 'M9 11l3 3 8-8M20 12v7H4V5h11',
    'doc' => 'M6 3h9l4 4v14H6zM14 3v5h5',
    'team' => 'M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z',
    'audit' => 'M12 8v4l3 2M12 3a9 9 0 1 0 9 9M21 3v5h-5',
    'org' => 'M4 21V5l8-3 8 3v16M9 21v-5h6v5M8 9h2M14 9h2M8 13h2M14 13h2',
    'apps' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
    'plug' => 'M9 2v6M15 2v6M6 8h12v4a6 6 0 0 1-12 0zM12 18v4',
    'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',
    'alert' => 'M12 3l10 18H2zM12 10v5M12 18v.5',
    'help' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.7M12 17v.5',
    'pages' => 'M4 4h16v16H4zM4 9h16M9 9v11',
    'brush' => 'M14 4l6 6-9 9H5v-6zM12 6l6 6',
    'lang' => 'M4 5h10M9 3v2M6 5c1 4 4 7 8 8M12 5c-1 4-4 7-8 9M14 21l4-9 4 9M15.5 18h5',
    'backup' => 'M4 7c0-2 16-2 16 0v10c0 2-16 2-16 0zM4 7c0 2 16 2 16 0M4 12c0 2 16 2 16 0',
    'settings' => 'M12 15.5A3.5 3.5 0 1 1 15.5 12 3.5 3.5 0 0 1 12 15.5zm7.4-2.5a7.6 7.6 0 0 0 0-2l2.1-1.6-2-3.5-2.5 1a7.4 7.4 0 0 0-1.7-1L15 3h-4l-.4 2.7a7.4 7.4 0 0 0-1.7 1l-2.5-1-2 3.5L6.5 11a7.6 7.6 0 0 0 0 2l-2.1 1.6 2 3.5 2.5-1a7.4 7.4 0 0 0 1.7 1L11 21h4l.4-2.7a7.4 7.4 0 0 0 1.7-1l2.5 1 2-3.5z',
    'health' => 'M3 12h4l3-8 4 16 3-8h4',
    'download' => 'M12 3v12m0 0l-5-5m5 5l5-5M4 17v3h16v-3',
    'user' => 'M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z',
    'orgs' => 'M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm-8 0a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm0 2c-2.7 0-8 1.3-8 4v3h8v-3c0-1 .4-2.1 1.3-3A11 11 0 0 0 8 13zm8 0c-2.7 0-8 1.3-8 4v3h16v-3c0-2.7-5.3-4-8-4z',
    'kyc' => 'M4 5h16v14H4zM8 10a2 2 0 1 0 4 0 2 2 0 0 0-4 0M6.5 16c.8-1.8 4.7-1.8 5.5 0M14 9h4M14 13h4',
    'handshake' => 'M2 12l5-5 4 2 4-2 7 5-3 3-4-3-3 3-3-1-4 4z',
    'crm' => 'M3 4h18v6H3zM3 14h18v6H3zM7 7h.5M7 17h.5',
];
// 5e élément = module en développement (lien vers sa page « Bientôt »)
if ($area === 'admin') {
    $soonA = fn(string $k, string $label, string $ic) => [$k, '/admin/bientot/' . $k, $label, $I[$ic], true];
    $navGroups = [
        ['', [
            ['dash', '/admin', 'Vue d\'ensemble', $I['dash']],
            $soonA('stats-plateforme', 'Statistiques', 'chart'),
        ]],
        ['Organisateurs', [
            ['orgs', '/admin/organisateurs', 'Organisateurs', $I['orgs']],
            ['kyc', '/admin/kyc', 'Vérification KYC', $I['kyc']],
            $soonA('contrats', 'Contrats & tarifs', 'handshake'),
            $soonA('prospection', 'Prospection', 'crm'),
            $soonA('formules', 'Formules & abonnements', 'card'),
        ]],
        ['Argent', [
            ['orders', '/admin/commandes', 'Commandes', $I['orders']],
            ['payouts', '/admin/reversements', 'Reversements', $I['money']],
            $soonA('litiges', 'Litiges & impayés', 'alert'),
            ['factures-commission', '/admin/factures', 'Factures de commission', $I['invoice']],
            $soonA('fraude', 'Fraude & risques', 'shield'),
            $soonA('revendeurs', 'Revendeurs', 'bag'),
        ]],
        ['Plateforme', [
            ['users', '/admin/utilisateurs', 'Utilisateurs', $I['user']],
            ['modules', '/admin/modules', 'Feuille de route', $I['apps']],
            $soonA('support', 'Support', 'help'),
            $soonA('contenus', 'Accueil & contenus', 'pages'),
            $soonA('emails-modeles', 'Modèles d\'e-mails', 'mail'),
            $soonA('marque-blanche', 'Marque blanche', 'brush'),
            $soonA('langues', 'Langues', 'lang'),
            $soonA('rgpd', 'Demandes RGPD', 'doc'),
        ]],
        ['Technique', [
            ['health', '/admin/sante', 'Santé', $I['health']],
            $soonA('sauvegardes', 'Sauvegardes auto', 'backup'),
            $soonA('alertes', 'Alertes', 'alert'),
            ['updates', '/admin/mises-a-jour', 'Mises à jour', $I['download']],
            ['settings', '/admin/reglages', 'Réglages', $I['settings']],
        ]],
    ];
} else {
    $soonP = fn(string $k, string $label, string $ic) => [$k, '/pro/bientot/' . $k, $label, $I[$ic], true];
    $navGroups = [
        ['', [
            ['dash', '/pro', 'Tableau de bord', $I['dash']],
            ['events', '/pro/evenements', 'Événements', $I['events']],
            ['orders', '/pro/commandes', 'Commandes', $I['orders']],
            ['participants', '/pro/participants', 'Participants', $I['people']],
        ]],
        ['Vendre plus', [
            $soonP('boutique', 'Boutique & merch', 'bag'),
            $soonP('abonnements', 'Abonnements', 'card'),
            $soonP('cartes-cadeaux', 'Cartes cadeaux', 'gift'),
            $soonP('devis', 'Groupes & devis', 'quote'),
        ]],
        ['Faire venir du monde', [
            ['campagnes', '/pro/campagnes', 'Campagnes e-mail', $I['mail']],
            $soonP('sms', 'Campagnes SMS', 'sms'),
            $soonP('mini-site', 'Mini-site', 'globe'),
            $soonP('reseaux', 'Réseaux sociaux', 'share'),
            $soonP('affiliation', 'Ambassadeurs', 'star'),
            $soonP('avis', 'Avis & satisfaction', 'star'),
        ]],
        ['Le jour J', [
            ['scan', '/scan', 'Contrôle d\'accès', $I['scan']],
            $soonP('controle-pro', 'PC sécurité', 'radar'),
            $soonP('benevoles', 'Bénévoles & staff', 'hand'),
            $soonP('artistes', 'Artistes & planning', 'mic'),
            $soonP('cashless', 'Cashless & bars', 'wallet'),
            $soonP('objets-trouves', 'Objets trouvés', 'box'),
        ]],
        ['Gestion', [
            ['finances', '/pro/finances', 'Finances', $I['money']],
            $soonP('factures', 'Factures & compta', 'invoice'),
            $soonP('budget', 'Budget prévisionnel', 'budget'),
            $soonP('subventions', 'Bilans & subventions', 'chart'),
            $soonP('taches', 'Rétroplanning', 'check'),
            $soonP('documents', 'Documents', 'doc'),
        ]],
        ['Mon organisation', [
            ['team', '/pro/equipe', 'Équipe', $I['team']],
            ['audit', '/pro/journal', 'Journal d\'activité', $I['audit']],
            ['org', '/pro/organisation', 'Réglages', $I['org']],
            ['kyc', '/pro/verification', 'Vérification du compte', $I['kyc']],
            $soonP('zapier', 'Intégrations', 'plug'),
        ]],
    ];
    if ($u['role'] === 'manager') foreach ($navGroups as &$g) $g[1] = array_values(array_filter($g[1], fn($n) => !in_array($n[0], ['team', 'org', 'finances', 'audit', 'factures', 'budget', 'documents', 'zapier', 'kyc'], true)));
    unset($g);
}
$nav = [];
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
  <nav class="side-nav" data-side-nav>
    <?php $icon = fn($d) => '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="' . $d . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"/></svg>'; ?>
    <?php foreach ($navGroups as $gi => [$gt, $items]): if (!$items) continue;
      $hasOn = (bool)array_filter($items, fn($it) => $it[0] === $active); $nSoon = count(array_filter($items, fn($it) => !empty($it[4]))); ?>
      <?php if ($gt === ''): ?>
        <?php foreach ($items as $it): [$key, $href, $label, $ic] = $it; $soon = !empty($it[4]); ?>
          <a href="<?= url($href) ?>" class="<?= $key === $active ? 'on' : '' ?><?= $soon ? ' soon' : '' ?>"><?= $icon($ic) ?><span class="grow"><?= e($label) ?></span><?= $soon ? '<span class="soon-pill dev">dév</span>' : '' ?></a>
        <?php endforeach; ?>
      <?php else: ?>
        <details class="side-sec" data-sec="<?= e($area . '-' . $gi) ?>" open<?= $hasOn ? ' data-has-on' : '' ?>>
          <summary class="side-group"><span class="grow"><?= e($gt) ?></span><?= $nSoon === count($items) ? '<span class="sec-soon">en dév.</span>' : '' ?><i aria-hidden="true">▾</i></summary>
          <?php foreach ($items as $it): [$key, $href, $label, $ic] = $it; $soon = !empty($it[4]); ?>
            <a href="<?= url($href) ?>" class="<?= $key === $active ? 'on' : '' ?><?= $soon ? ' soon' : '' ?>" <?= $soon ? 'title="En développement : bientôt disponible"' : '' ?>><?= $icon($ic) ?><span class="grow"><?= e($label) ?></span><?= $soon ? '<span class="soon-pill dev">dév</span>' : '' ?></a>
          <?php endforeach; ?>
        </details>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($area === 'pro'): ?><a href="<?= url('/pro/modules') ?>" class="side-apps<?= $active === 'modules' ? ' on' : '' ?>"><?= $icon($I['apps']) ?><span class="grow">Tous les modules</span></a><?php endif; ?>
    <label class="side-toggle"><input type="checkbox" data-hide-soon> Masquer les modules en développement</label>
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
        <a href="<?= url('/pro/campagnes') ?>">✉️ Une campagne e-mail</a>
        <div class="menu-sep">En développement</div>
        <a class="menu-soon" href="<?= url('/pro/bientot/boutique') ?>">🛍️ Un produit boutique</a>
        <a class="menu-soon" href="<?= url('/pro/bientot/cartes-cadeaux') ?>">🎁 Une carte cadeau</a>
        <a class="menu-soon" href="<?= url('/pro/bientot/abonnements') ?>">🎫 Un abonnement</a>
      </div></details>
      <?php endif; ?>
      <details class="menu notif" id="notif"><summary class="probar-icon" aria-label="Notifications"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4zM10 20a2 2 0 0 0 4 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg><b class="notif-count" hidden></b></summary>
        <div class="menu-list notif-list"><p class="muted">Chargement…</p></div></details>
    </div>
  </div>
  <?php if ($area === 'pro' && $org && $org['status'] === 'pending'): ?><div class="flash warning">⏳ Ton compte est en cours de validation par l'équipe <?= e($pname) ?>. Tu peux tout préparer ; la publication sera possible dès la validation (on te prévient par e-mail).</div><?php endif; ?>
  <?php if ($area === 'pro' && ($ann = trim((string)setting('announce_text'))) !== '' && (!setting('announce_until') || strtotime((string)setting('announce_until')) >= time())): $annId = substr(md5($ann), 0, 8); ?>
    <div class="announce <?= e((string)setting('announce_level', 'info')) ?>" data-announce="<?= $annId ?>"><span>📣</span><div class="grow"><?= nl2br(e($ann)) ?></div><button type="button" class="announce-x" aria-label="Masquer l'annonce">✕</button></div>
  <?php endif; ?>
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
