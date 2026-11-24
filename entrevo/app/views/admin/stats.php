<?php $avg = $tot['n'] ? (int)round($tot['gmv'] / $tot['n']) : 0; $maxSrc = max(1, max(array_map(fn($s) => (int)$s['gmv'], $src ?: [['gmv' => 1]]))); $maxProv = max(1, max(array_map(fn($s) => (int)$s['gmv'], $prov ?: [['gmv' => 1]]))); ?>
<div class="page-head-row stats-filters">
  <div class="seg"><?php foreach ([3 => '3 mois', 6 => '6 mois', 12 => '12 mois', 24 => '24 mois'] as $k => $l): ?><a class="seg-a <?= $months === $k ? 'on' : '' ?>" href="<?= url('/admin/statistiques', array_filter(['m' => $k, 'demo' => $demo ? 1 : null])) ?>"><?= $l ?></a><?php endforeach; ?></div>
  <a class="btn btn-sm btn-light" href="<?= url('/admin/statistiques', array_filter(['m' => $months, 'demo' => $demo ? null : 1])) ?>"><?= $demo ? 'Masquer les paiements de démo' : 'Inclure les paiements de démo' ?></a>
</div>
<?php if ($demo): ?><p class="notice">Les paiements de démonstration sont inclus : ces chiffres ne correspondent pas à de l'argent réel.</p><?php endif; ?>
<div class="kpis kpis-3">
  <div class="kpi"><small>Volume encaissé</small><b><?= e(money((int)$tot['gmv'])) ?></b><span class="muted"><?= (int)$tot['n'] ?> commandes</span></div>
  <div class="kpi"><small>Tes commissions</small><b><?= e(money((int)$tot['com'])) ?></b><span class="muted"><?= $tot['gmv'] ? e(number_format($tot['com'] / $tot['gmv'] * 100, 1, ',', ' ')) . ' % du volume' : '—' ?></span></div>
  <div class="kpi"><small>Billets</small><b><?= number_format((int)$tot['tickets'], 0, ',', ' ') ?></b><span class="muted">panier moyen <?= e(money($avg)) ?></span></div>
  <div class="kpi"><small>Acheteurs uniques</small><b><?= number_format((int)$tot['buyers'], 0, ',', ' ') ?></b><span class="muted"><?= (int)$repeat ?> revenus sur 2 événements ou plus</span></div>
  <div class="kpi"><small>Organisateurs qui vendent</small><b><?= (int)$tot['orgs'] ?></b><span class="muted"><?= (int)$tot['events'] ?> événements</span></div>
  <div class="kpi"><small>Remboursé</small><b><?= e(money((int)$tot['ref'])) ?></b><span class="muted"><?= $tot['gmv'] ? e(number_format($tot['ref'] / $tot['gmv'] * 100, 1, ',', ' ')) . ' % du volume' : '—' ?></span></div>
</div>
<div class="split">
  <div class="card"><div class="card-head"><h2>Volume et commissions par mois</h2></div>
    <?= Viz::chart('area', ['labels' => $series['labels'], 'xlabel' => 'Mois', 'series' => [['name' => 'Volume encaissé', 'values' => $series['gmv']], ['name' => 'Commissions', 'values' => $series['com']]]], 'Volume et commissions par mois', 'money', 260) ?></div>
  <div class="card"><div class="card-head"><h2>Billets vendus par mois</h2></div>
    <?= Viz::chart('columns', ['labels' => $series['labels'], 'xlabel' => 'Mois', 'series' => [['name' => 'Billets', 'values' => $series['tickets']]]], 'Billets vendus par mois', 'int', 260) ?></div>
</div>
<div class="split">
  <div class="card"><div class="card-head"><h2>D'où viennent les ventes</h2></div>
    <?php foreach ($src as $s): ?><div class="hbar"><span class="hbar-l"><?= e($s['s']) ?></span><span class="hbar-b"><i style="width:<?= round($s['gmv'] / $maxSrc * 100) ?>%"></i></span><span class="hbar-v"><?= e(money((int)$s['gmv'])) ?> · <?= (int)$s['n'] ?></span></div><?php endforeach; ?>
    <?php if (!$src): ?><p class="muted">Pas encore de vente sur la période.</p><?php endif; ?></div>
  <div class="card"><div class="card-head"><h2>Moyens de paiement</h2></div>
    <?php foreach ($prov as $s): ?><div class="hbar"><span class="hbar-l"><?= e(provider_label((string)$s['p'])) ?></span><span class="hbar-b"><i style="width:<?= round($s['gmv'] / $maxProv * 100) ?>%"></i></span><span class="hbar-v"><?= e(money((int)$s['gmv'])) ?> · <?= (int)$s['n'] ?></span></div><?php endforeach; ?>
    <?php if (!$prov): ?><p class="muted">Aucun paiement en ligne réel pour l'instant. <a href="<?= url('/admin/statistiques', ['m' => $months, 'demo' => 1]) ?>">Voir avec la démo</a></p><?php endif; ?>
    <h3 class="mt">Nouveaux organisateurs</h3>
    <?= Viz::chart('columns', ['labels' => $series['labels'], 'xlabel' => 'Mois', 'series' => [['name' => 'Inscriptions', 'values' => $series['orgs']]]], 'Nouveaux organisateurs par mois', 'int', 160) ?></div>
</div>
<div class="card table-card"><div class="card-head"><h2>Meilleurs événements</h2></div>
  <div class="table-wrap"><table class="table"><thead><tr><th>Événement</th><th>Organisateur</th><th class="num">Commandes</th><th class="num">Volume</th><th class="num">Commission</th></tr></thead><tbody>
  <?php foreach ($topEv as $e): ?><tr><td><b><?= e($e['name']) ?></b></td><td><a href="<?= url('/admin/organisateurs/' . $e['org_id']) ?>"><?= e($e['org']) ?></a></td><td class="num"><?= (int)$e['n'] ?></td><td class="num"><?= e(money((int)$e['gmv'])) ?></td><td class="num"><b><?= e(money((int)$e['com'])) ?></b></td></tr><?php endforeach; ?>
  <?php if (!$topEv): ?><tr><td colspan="5" class="empty">Rien sur la période.</td></tr><?php endif; ?></tbody></table></div></div>
