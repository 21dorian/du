<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
<meta name="theme-color" content="#0c0c0c">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>Scan · <?= e(setting('platform_name')) ?></title>
<link rel="manifest" href="<?= url('/scan/manifest.webmanifest') ?>">
<link rel="apple-touch-icon" href="<?= url('assets/img/icon-192.png') ?>">
<link rel="icon" href="<?= url('assets/img/icon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/scan.css') ?>">
<style>:root{--primary:<?= e(setting('primary_color', '#ff5a4e')) ?>}</style>
</head>
<body class="pub scan-app" data-base="<?= e(base_path()) ?>" data-logged="<?= $u ? '1' : '0' ?>" data-csrf="<?= e($u ? csrf_token() : '') ?>">

<!-- Pas connecté -->
<section class="screen" id="s-login" hidden>
  <div class="screen-center">
    <div class="logo" style="justify-content:center;margin-bottom:26px"><?= view('partials/logo') ?><span><?= e(setting('platform_name')) ?> Scan</span></div>
    <h1>Prêt à scanner ? 📷</h1>
    <p class="muted">Connecte-toi avec ton compte contrôleur pour ouvrir les portes.</p>
    <a class="btn btn-lg btn-block" href="<?= url('/connexion', ['suite' => '/scan']) ?>">Me connecter</a>
  </div>
</section>

<!-- Choix de l'événement -->
<section class="screen" id="s-events" hidden>
  <header class="scan-head"><div class="logo"><?= view('partials/logo') ?><span>Scan</span></div><button class="icon-btn" data-go="menu" aria-label="Menu">☰</button></header>
  <div class="scan-pad">
    <p class="muted mb0" id="hello"></p>
    <h1>Quel événement ?</h1>
    <div id="events-list" class="pick-list"></div>
    <p class="muted center" id="events-empty" hidden>Aucun événement à contrôler pour l'instant 🦗</p>
  </div>
</section>

<!-- Préparation : point d'accès + sens -->
<section class="screen" id="s-setup" hidden>
  <header class="scan-head"><button class="icon-btn" data-go="events" aria-label="Retour">←</button><b id="setup-title" class="head-title"></b><span></span></header>
  <div class="scan-pad">
    <div id="session-box" hidden><h2>Quelle date ? 📅</h2><div id="session-list" class="pick-list"></div><div class="mt"></div></div>
    <h2>Quelle porte ? 🚪</h2>
    <div id="ap-list" class="pick-list"></div>
    <h2 class="mt">Dans quel sens ?</h2>
    <div class="seg"><button data-dir="in" class="on">⬇️ Entrée</button><button data-dir="out">⬆️ Sortie</button></div>
    <p class="muted" id="reentry-note" style="font-size:14px"></p>
    <div class="dl-box" id="dl-box"><span id="dl-text">Téléchargement des billets…</span><div class="bar"><i id="dl-bar" style="width:0"></i></div></div>
    <button class="btn btn-lg btn-block mt" id="start-btn" disabled>C'est parti 🚀</button>
  </div>
</section>

<!-- Scanner -->
<section class="screen" id="s-scan" hidden>
  <header class="scan-head">
    <button class="icon-btn" data-go="setup" aria-label="Changer de porte">←</button>
    <div class="head-title"><b id="scan-ev"></b><small id="scan-ap"></small></div>
    <span class="net" id="net" title="Réseau"><i></i><em id="pending"></em></span>
  </header>
  <div class="cam" id="cam">
    <video id="video" playsinline muted></video>
    <canvas id="canvas" hidden></canvas>
    <div class="cam-frame"></div>
    <div class="cam-off" id="cam-off" hidden>
      <p>Caméra éteinte 📷</p>
      <button class="btn" id="cam-start">Activer la caméra</button>
      <small class="muted" id="cam-msg"></small>
    </div>
    <button class="icon-btn torch" id="torch" hidden aria-label="Lampe">🔦</button>
    <span class="dir-pill" id="dir-pill">Entrée</span>
  </div>
  <div class="result idle" id="result">
    <div class="result-label" id="r-label">Vise un QR code</div>
    <div class="result-name" id="r-name">Ou tape le code en dessous 👇</div>
    <div class="result-info" id="r-info"></div>
    <button class="linklike result-undo" id="r-undo" hidden>Annuler ce passage</button>
  </div>
  <form class="manual" id="manual" autocomplete="off">
    <input id="manual-input" placeholder="Code billet ou douchette" autocapitalize="characters" spellcheck="false" enterkeyhint="go">
    <button class="btn" type="submit">OK</button>
    <button class="btn btn-ghost" type="button" id="search-btn" aria-label="Chercher par nom">🔍</button>
  </form>
  <div class="counters">
    <div><b id="c-checked">0</b><small>entrés</small></div>
    <div><b id="c-sold">0</b><small>billets</small></div>
    <div><b id="c-inside">0</b><small>sur place</small></div>
    <div><b id="c-mine">0</b><small>par toi</small></div>
  </div>
</section>

<!-- Recherche par nom -->
<section class="screen overlay" id="s-search" hidden>
  <header class="scan-head"><button class="icon-btn" data-close="search" aria-label="Fermer">✕</button><b class="head-title">Chercher un nom</b><span></span></header>
  <div class="scan-pad">
    <input id="search-input" type="search" placeholder="Nom, prénom ou code…" autocomplete="off">
    <div id="search-list" class="search-list"></div>
  </div>
</section>

<!-- Menu -->
<section class="screen overlay" id="s-menu" hidden>
  <header class="scan-head"><button class="icon-btn" data-close="menu" aria-label="Fermer">✕</button><b class="head-title">Menu</b><span></span></header>
  <div class="scan-pad pick-list">
    <button class="pick" id="m-sync"><b>🔄 Synchroniser maintenant</b><small id="m-sync-info"></small></button>
    <button class="pick" id="m-sound"><b>🔊 Son</b><small id="m-sound-info"></small></button>
    <button class="pick" data-go="events"><b>📅 Changer d'événement</b></button>
    <a class="pick" href="<?= url('/') ?>"><b>🏠 Retour au site</b></a>
    <form method="post" action="<?= url('/deconnexion') ?>" id="logout-form"><input type="hidden" name="_csrf" value="<?= e($u ? csrf_token() : '') ?>"><button class="pick" style="width:100%"><b>👋 Me déconnecter</b><small>Les passages pas encore envoyés seront perdus : synchronise avant.</small></button></form>
  </div>
</section>

<div class="flash-screen" id="flash"></div>
<script src="<?= asset('js/jsQR.js') ?>"></script>
<script src="<?= asset('js/scan.js') ?>"></script>
</body>
</html>
