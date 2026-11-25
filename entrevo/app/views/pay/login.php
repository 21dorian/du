<?php /** @var ?string $error; string $suite */ ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion · Entrevo Pay</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>"><link rel="stylesheet" href="<?= asset('css/pay.css') ?>">
<link rel="icon" href="<?= asset('img/pay.svg') ?>" type="image/svg+xml">
</head>
<body class="pay">
<div class="pay-auth">
  <div class="pay-auth-art">
    <div class="pay-brand" style="padding:0"><span class="pay-logo"><svg viewBox="0 0 32 32"><rect width="32" height="32" rx="9"/><path d="M9 22V10h7.5a4.5 4.5 0 0 1 0 9H13"/></svg></span><span><b>Entrevo Pay</b><small>Cashless & encaissement événementiel</small></span></div>
    <div>
      <h2>Le cashless de ton festival, piloté en direct.</h2>
      <p>Bracelets NFC et cartes virtuelles, bars et stands, exploitants et reversements, terminaux, stocks et clôtures : tout au même endroit.</p>
      <ul><li>Ventes à la minute, par bar et par produit</li><li>Porte-monnaie, bracelets perdus, remboursements</li><li>Relevés et reversements des exploitants</li><li>Parc de terminaux : batterie, réseau, synchronisation</li></ul>
    </div>
    <small style="color:#6c6c92"><?= e((string)setting('platform_name', 'Entrevo')) ?></small>
  </div>
  <div class="pay-auth-form">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="suite" value="<?= e($suite) ?>">
      <h1>Connexion</h1>
      <p class="muted" style="margin:0">Organisateur, équipe cashless ou exploitant.</p>
      <?= render_flash() ?>
      <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
      <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="username" required autofocus></div>
      <div class="field"><label for="password">Mot de passe</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
      <button class="btn btn-lg" style="background:var(--pay);border-color:var(--pay)">Se connecter</button>
      <a class="muted" href="<?= url('/mot-de-passe-oublie') ?>">Mot de passe oublié</a>
    </form>
  </div>
</div>
</body>
</html>
