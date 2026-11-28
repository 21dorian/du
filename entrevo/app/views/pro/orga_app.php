<?php /** @var array $org, $events, $rates, $u; ?array $ev */ $pn = (string)setting('platform_name', 'Entrevo'); ?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#f5f5f7">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?= e($pn) ?> Pro">
<title><?= e($pn) ?> Pro</title>
<link rel="manifest" href="<?= url('/pro/app/manifest.webmanifest') ?>">
<link rel="apple-touch-icon" href="<?= url('assets/img/icon-192.png') ?>">
<link rel="icon" href="<?= url('assets/img/icon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/orga-app.css') ?>">
<style>:root{--primary:<?= e(setting('primary_color', '#0071e3')) ?>}</style>
</head>
<body class="oa" data-base="<?= e(url('/pro/app')) ?>" data-csrf="<?= e(csrf_token()) ?>" data-ev="<?= (int)($ev['id'] ?? 0) ?>" data-sw="<?= e(url('/pro/app/sw.js')) ?>">
<a class="skip-link" href="#contenu">Aller au contenu</a>
<header class="oa-top">
  <div class="oa-org"><small><?= e($org['name']) ?></small>
    <?php if ($events): ?><label class="sr-only" for="oa-ev">Événement</label><select id="oa-ev" class="oa-evsel"><?php foreach ($events as $x): ?><option value="<?= (int)$x['id'] ?>" <?= $ev && (int)$ev['id'] === (int)$x['id'] ? 'selected' : '' ?>><?= e($x['name']) ?> · <?= e(date('d/m', strtotime($x['starts_at']))) ?></option><?php endforeach; ?></select>
    <?php else: ?><b>Aucun événement en cours</b><?php endif; ?></div>
  <span class="oa-live" data-live title="Mise à jour en direct"><i></i>Direct</span>
</header>

<main id="contenu" class="oa-main">
<?php if (!$ev): ?>
  <section class="oa-card oa-empty"><h1>Rien à suivre pour l'instant</h1><p>Crée et publie un événement depuis l'espace complet : il apparaîtra ici avec ses ventes en direct.</p><a class="btn" href="<?= url('/pro/evenements') ?>">Ouvrir l'espace complet</a></section>
<?php else: ?>
  <!-- Direct -->
  <section class="oa-tab" data-tab="live">
    <div class="oa-hero oa-card">
      <div class="oa-ring" data-ring style="--p:0"><svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="52" class="bg"/><circle cx="60" cy="60" r="52" class="fg"/></svg>
        <div><b data-k="sold">—</b><small data-k="cap"></small></div></div>
      <div class="oa-hero-txt"><small data-k="date"></small><h1 data-k="name"><?= e($ev['name']) ?></h1>
        <div class="oa-state"><span class="oa-chip" data-k="status"></span><button type="button" class="btn btn-sm btn-light" data-toggle-sales></button></div></div>
    </div>
    <div class="oa-kpis">
      <div class="oa-kpi"><small>Aujourd'hui</small><b data-k="today">—</b><span data-k="today_n"></span></div>
      <div class="oa-kpi"><small>Recette nette</small><b data-k="net">—</b><span>billetterie</span></div>
      <div class="oa-kpi"><small>Sur place</small><b data-k="inside">—</b><span data-k="came"></span></div>
    </div>
    <div class="oa-card"><div class="oa-h"><h2>14 derniers jours</h2><small>billets par jour</small></div><div class="oa-spark" data-spark role="img" aria-label="Ventes des 14 derniers jours"></div></div>
    <div class="oa-card"><div class="oa-h"><h2>Tarifs</h2></div><div data-rates class="oa-rates"></div></div>
    <div class="oa-card"><div class="oa-h"><h2>Dernières commandes</h2></div><ul data-orders class="oa-list"></ul></div>
  </section>

  <!-- Vendre -->
  <section class="oa-tab" data-tab="sell" hidden>
    <form class="oa-card oa-sell" data-sell>
      <div class="oa-h"><h2>Vente rapide</h2><small>billets encaissés sur place</small></div>
      <?php foreach ($rates as $r): ?>
        <div class="oa-rate-row"><span><b><?= e($r['name']) ?></b><small><?= e(money((int)$r['price'])) ?></small></span>
          <span class="oa-step"><button type="button" data-d="-1" aria-label="Retirer un billet <?= e($r['name']) ?>">−</button><input type="number" inputmode="numeric" min="0" max="50" value="0" name="qty[<?= (int)$r['id'] ?>]" data-price="<?= (int)$r['price'] ?>" aria-label="Quantité <?= e($r['name']) ?>"><button type="button" data-d="1" aria-label="Ajouter un billet <?= e($r['name']) ?>">+</button></span></div>
      <?php endforeach; ?>
      <fieldset class="oa-pay"><legend>Encaissé en</legend>
        <?php foreach (['especes' => 'Espèces', 'cb' => 'Carte', 'cheque' => 'Chèque', 'offert' => 'Offert'] as $k => $l): ?><label><input type="radio" name="pay_method" value="<?= $k ?>" <?= $k === 'especes' ? 'checked' : '' ?>><span><?= $l ?></span></label><?php endforeach; ?></fieldset>
      <details class="oa-more"><summary>Nom et e-mail (facultatif)</summary>
        <div class="grid2"><div class="field mb0"><label for="oa-fn">Prénom</label><input id="oa-fn" name="first_name" autocomplete="off"></div><div class="field mb0"><label for="oa-ln">Nom</label><input id="oa-ln" name="last_name" autocomplete="off"></div></div>
        <div class="field mb0 u-mt12"><label for="oa-em">E-mail pour envoyer les billets</label><input id="oa-em" type="email" name="email" inputmode="email" autocomplete="off"></div></details>
      <button class="btn btn-lg btn-block" data-sell-btn disabled>Encaisser</button>
      <div class="oa-done" data-sell-done hidden></div>
    </form>
  </section>

  <!-- Alertes -->
  <section class="oa-tab" data-tab="alerts" hidden>
    <div class="oa-card oa-notif" data-notif>
      <div class="oa-h"><h2>Notifications</h2><small data-notif-state>…</small></div>
      <p class="muted u-m0" data-notif-help>Reçois les ventes et les alertes même quand l'appli est fermée.</p>
      <div class="oa-prefs" data-prefs hidden>
        <?php foreach (AppPush::KINDS as $k => $l): ?><label class="oa-switch"><span><?= e($l) ?></span><input type="checkbox" data-pref="<?= $k ?>"><i aria-hidden="true"></i></label><?php endforeach; ?>
        <label class="oa-switch"><span>Un palier tous les</span><select data-pref="step"><?php foreach ([10, 25, 50, 100, 250, 500, 1000] as $s): ?><option value="<?= $s ?>"><?= $s ?> billets</option><?php endforeach; ?></select></label>
      </div>
      <div class="oa-row"><button type="button" class="btn" data-notif-on>Activer sur cet appareil</button><button type="button" class="btn btn-light" data-notif-test hidden>Tester</button><button type="button" class="btn btn-ghost" data-notif-off hidden>Désactiver</button></div>
    </div>
    <div class="oa-card"><div class="oa-h"><h2>Activité</h2></div><ul data-alerts class="oa-list oa-alerts"><li class="muted">Les ventes et alertes s'afficheront ici.</li></ul></div>
  </section>

  <!-- Plus -->
  <section class="oa-tab" data-tab="more" hidden>
    <form class="oa-card" data-msg>
      <div class="oa-h"><h2>Message express</h2><small>par e-mail aux participants</small></div>
      <div class="field"><label for="oa-who">Destinataires</label><select id="oa-who" name="who"><option value="all">Tous les participants</option><option value="in">Ceux déjà entrés</option></select></div>
      <div class="field"><label for="oa-subj">Objet</label><input id="oa-subj" name="subject" maxlength="190" required placeholder="Ouverture des portes à 19 h 30"></div>
      <div class="field"><label for="oa-body">Message</label><textarea id="oa-body" name="body" rows="4" required></textarea></div>
      <button class="btn btn-block" data-confirm-msg>Envoyer</button>
    </form>
    <div class="oa-card oa-links">
      <a href="<?= url('/scan') ?>"><?= ui_icon('scan') ?><span>Scanner les billets</span></a>
      <a href="<?= url('/pro/evenements/' . $ev['id'] . '/guichet') ?>"><?= ui_icon('cart') ?><span>Guichet complet</span></a>
      <button type="button" data-share="<?= e(abs_url('/e/' . $ev['slug'])) ?>"><?= ui_icon('link') ?><span>Partager la billetterie</span></button>
      <a href="<?= url('/pro/evenements/' . $ev['id'] . '/pc') ?>"><?= ui_icon('shield') ?><span>PC sécurité</span></a>
      <a href="<?= url('/pro/evenements/' . $ev['id']) ?>"><?= ui_icon('chart') ?><span>Espace complet</span></a>
      <button type="button" data-native-home hidden><?= ui_icon('settings') ?><span>Changer d'appli ou de site</span></button>
    </div>
    <p class="oa-foot muted"><?= e($u['name'] ?? '') ?> · <button type="button" class="linklike" data-logout>Se déconnecter</button></p>
  </section>
<?php endif; ?>
</main>

<nav class="oa-nav" aria-label="Sections">
  <button type="button" data-go="live" class="on"><?= ui_icon('chart', 22) ?><span>Direct</span></button>
  <button type="button" data-go="sell"><?= ui_icon('cart', 22) ?><span>Vendre</span></button>
  <button type="button" data-go="alerts"><?= ui_icon('megaphone', 22) ?><span>Alertes</span><i class="oa-badge" data-badge hidden></i></button>
  <button type="button" data-go="more"><?= ui_icon('settings', 22) ?><span>Plus</span></button>
</nav>
<div class="oa-toasts" aria-live="polite" data-toasts></div>
<form method="post" action="<?= url('/deconnexion') ?>" hidden data-logout-form><?= csrf_field() ?></form>
<script src="<?= asset('js/orga-app.js') ?>"></script>
</body>
</html>
