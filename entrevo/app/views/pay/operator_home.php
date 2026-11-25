<?php /** @var array $m, $op, $events, $sales, $stock, $prods; ?array $ev, $S */ $u = user(); ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($op['name']) ?> · Entrevo Pay</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>"><link rel="stylesheet" href="<?= asset('css/pay.css') ?>">
<link rel="icon" href="<?= asset('img/pay.svg') ?>" type="image/svg+xml">
</head>
<body class="pay">
<div class="pay-op">
  <div class="pay-op-top">
    <span class="pay-logo"><svg viewBox="0 0 32 32"><rect width="32" height="32" rx="9"/><path d="M9 22V10h7.5a4.5 4.5 0 0 1 0 9H13"/></svg></span>
    <div class="grow"><small class="pay-crumb">Espace exploitant · <?= e($m['org']['name']) ?></small><h1 style="margin:0;font-size:28px"><?= e($op['name']) ?></h1></div>
    <?php if ($m['role'] !== 'operator'): ?><a class="btn btn-sm btn-light" href="<?= Pay::url('/pro/exploitants') ?>">← Retour</a><?php endif; ?>
    <form method="post" action="<?= Pay::url('/deconnexion') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-light">Déconnexion</button></form>
  </div>
  <?= render_flash() ?>
  <?php if ($events): ?><div class="pchips mb"><?php foreach ($events as $e2): ?><a class="<?= $ev && (int)$ev['id'] === (int)$e2['id'] ? 'on' : '' ?>" href="<?= Pay::url('/exploitant', array_filter(['e' => $e2['id'], 'op' => $m['role'] !== 'operator' ? $op['id'] : null])) ?>"><?= e($e2['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
  <?php if (!$ev): ?><div class="card pempty">Aucun stand ne t'est encore rattaché. L'organisateur le fait dans Entrevo Pay.</div>
  <?php else: ?>
  <div class="pk">
    <div class="hero"><span>Ton chiffre d'affaires · <?= e($ev['name']) ?></span><b><?= e(money($S['ttc'])) ?></b><small><?= (int)$S['n'] ?> ventes · HT <?= e(money($S['ht'])) ?></small></div>
    <div><span>Cashless</span><b><?= e(money($S['cashless'])) ?></b></div><div><span>Carte</span><b><?= e(money($S['card'])) ?></b></div><div><span>Espèces</span><b><?= e(money($S['cash'])) ?></b></div>
    <div><span>Commission</span><b><?= e(money($S['commission'])) ?></b></div>
    <div class="<?= $S['left'] > 0 ? 'good' : '' ?>"><span><?= $S['left'] >= 0 ? 'À recevoir' : 'À régler à l\'organisateur' ?></span><b><?= e(money(abs($S['left']))) ?></b><small>déjà versé <?= e(money($S['paid'])) ?></small></div>
  </div>
  <div class="pgrid">
    <div class="card table-card"><div class="card-head"><h2>Dernières ventes</h2><a class="btn btn-sm btn-light" target="_blank" href="<?= Pay::url('/exploitant', array_filter(['e' => $ev['id'], 'pdf' => 1, 'op' => $m['role'] !== 'operator' ? $op['id'] : null])) ?>">Relevé PDF</a></div><div class="table-wrap"><table class="table table-sm"><tbody>
      <?php foreach ($sales as $s): ?><tr><td><?= e(date('d/m H:i', strtotime($s['created_at']))) ?></td><td><?= e($s['bar']) ?></td><td><?= e(Till::METHODS[$s['method']] ?? $s['method']) ?></td><td><small class="muted"><?= e((string)$s['staff']) ?></small></td><td class="num"><b><?= e(money((int)$s['amount'])) ?></b></td></tr><?php endforeach; ?>
      <?php if (!$sales): ?><tr><td class="pempty">Pas encore de vente.</td></tr><?php endif; ?></tbody></table></div></div>
    <div class="stack">
      <div class="card"><h2>Tes stands</h2><?php foreach ($S['per_bar'] as $b): ?><div class="list-item"><span class="grow"><?= e($b['name']) ?></span><small class="muted"><?= (int)$b['n'] ?> ventes</small><b style="min-width:90px;text-align:right"><?= e(money($b['ttc'])) ?></b></div><?php endforeach; ?></div>
      <div class="card"><h2>Meilleures ventes</h2><?php foreach (array_slice($prods, 0, 8, true) as $n => $p): ?><div class="list-item"><span class="grow"><?= e($n) ?></span><small class="muted">× <?= (int)$p['q'] ?></small><b style="min-width:80px;text-align:right"><?= e(money((int)$p['t'])) ?></b></div><?php endforeach; ?><?php if (!$prods): ?><p class="pnote">—</p><?php endif; ?></div>
      <?php if ($stock): ?><div class="card"><h2>Stocks</h2><?php foreach ($stock as $st): ?><div class="list-item"><span class="grow"><?= e($st['name']) ?> <small class="muted">· <?= e($st['bar']) ?></small></span><b class="<?= (int)$st['qty'] <= 5 ? 'text-orange' : '' ?>"><?= (int)$st['qty'] ?></b></div><?php endforeach; ?></div><?php endif; ?>
    </div>
  </div>
  <p class="pnote mt">Conditions : commission <?= e(rtrim(rtrim(number_format((float)$op['commission_pct'], 2, ',', ''), '0'), ',')) ?> % du CA TTC<?= (int)$op['fixed_fee'] ? ' + forfait ' . e(money((int)$op['fixed_fee'])) : '' ?>. Le cashless est encaissé par l'organisateur et te revient, commission déduite.</p>
  <?php endif; ?>
</div>
</body>
</html>
