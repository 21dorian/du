<?php /** @var array $org, $today, $yday, $events, $last */ $delta = (int)$today['t'] - (int)$yday['t']; ?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title><?= e($org['name']) ?> · Pro</title>
<meta name="theme-color" content="#1d1d1f"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="manifest" href="<?= url('/pro/manifest.webmanifest') ?>"><link rel="apple-touch-icon" href="<?= url('/caisse/icon-192.png') ?>">
<style>
:root{--bg:#f5f5f7;--card:#fff;--t:#1d1d1f;--m:#6e6e73;--l:#e5e5ea;--a:#0071e3;--g:#34c759}
@media (prefers-color-scheme:dark){:root{--bg:#000;--card:#1c1c1e;--t:#f5f5f7;--m:#98989d;--l:#2c2c2e;--a:#0a84ff;--g:#30d158}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--t);font:15px/1.4 -apple-system,BlinkMacSystemFont,"SF Pro Text","Helvetica Neue",Arial,sans-serif;padding:env(safe-area-inset-top) 0 env(safe-area-inset-bottom)}
header{padding:20px 16px 6px;display:flex;justify-content:space-between;align-items:flex-end}header h1{margin:0;font-size:28px;letter-spacing:-.02em}header a{color:var(--a);text-decoration:none;font-size:15px}
.wrap{padding:0 16px 24px;max-width:560px;margin:0 auto}.c{background:var(--card);border-radius:18px;padding:16px;margin-top:12px}
.big{font-size:40px;font-weight:800;letter-spacing:-.03em}.m{color:var(--m);font-size:13px}.up{color:var(--g)}
.ev{display:block;color:inherit;text-decoration:none;padding:12px 0;border-top:1px solid var(--l)}.ev:first-of-type{border-top:0}
.bar{height:6px;border-radius:6px;background:var(--l);overflow:hidden;margin-top:6px}.bar i{display:block;height:100%;background:var(--a)}
.row{display:flex;justify-content:space-between;gap:8px;align-items:center}.o{padding:10px 0;border-top:1px solid var(--l)}.o:first-of-type{border-top:0}
h2{font-size:17px;margin:0 0 6px}.btns{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px}.btns a{background:var(--card);border-radius:14px;padding:14px;text-align:center;color:var(--a);text-decoration:none;font-weight:600}
</style></head><body>
<header><div><div class="m"><?= e($org['name']) ?></div><h1>Aujourd'hui</h1></div><a href="<?= url('/pro') ?>">Ordinateur</a></header>
<div class="wrap">
  <div class="c"><div class="m">Ventes du jour</div><div class="big" id="m-total"><?= e(money((int)$today['t'])) ?></div>
    <div class="m"><span id="m-n"><?= (int)$today['n'] ?></span> commande(s) · <?= $delta >= 0 ? '<span class="up">+' . e(money($delta)) . '</span>' : e(money($delta)) ?> par rapport à hier</div></div>
  <div class="c"><h2>Événements</h2><?php foreach ($events as $e2): $pct = $e2['capacity'] ? min(100, (int)round($e2['sold'] / $e2['capacity'] * 100)) : null; ?>
    <a class="ev" href="<?= url('/pro/evenements/' . $e2['id']) ?>"><div class="row"><b><?= e($e2['name']) ?></b><span class="m"><?= e(short_date($e2['starts_at'])) ?></span></div>
      <div class="m"><?= (int)$e2['sold'] ?><?= $e2['capacity'] ? ' / ' . (int)$e2['capacity'] : '' ?> billets<?= (int)$e2['today'] ? ' · ' . e(money((int)$e2['today'])) . ' aujourd\'hui' : '' ?><?= (int)$e2['inside'] ? ' · <b>' . (int)$e2['inside'] . ' sur place</b>' : '' ?></div>
      <?php if ($pct !== null): ?><div class="bar"><i style="width:<?= $pct ?>%"></i></div><?php endif; ?></a><?php endforeach; ?>
    <?php if (!$events): ?><p class="m">Aucun événement à venir.</p><?php endif; ?></div>
  <div class="c"><h2>Dernières commandes</h2><?php foreach ($last as $o): ?><div class="o row"><div><b><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?></b><div class="m"><?= e($o['ev']) ?> · <?= (int)$o['nt'] ?> billet(s) · <?= e(short_date($o['paid_at'])) ?></div></div><b><?= e(money((int)$o['total'])) ?></b></div><?php endforeach; ?>
    <?php if (!$last): ?><p class="m">Pas encore de commande.</p><?php endif; ?></div>
  <div class="btns"><a href="<?= url('/scan') ?>">Scanner</a><a href="<?= url('/pro/commandes') ?>">Commandes</a></div>
  <p class="m" style="text-align:center;margin-top:16px">Ajoute cette page à ton écran d'accueil : Partager › Sur l'écran d'accueil (iPhone) ou menu › Installer (Android). Mise à jour toutes les 30 secondes.</p>
</div>
<script>
var last = <?= json_encode($last[0]['ref'] ?? null) ?>;
setInterval(function(){fetch(location.pathname+'?json=1',{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
  if (j.last && j.last !== last) { if (navigator.vibrate) navigator.vibrate(30); location.reload(); }
}).catch(function(){})},30000);
</script>
</body></html>
