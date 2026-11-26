<?php /** @var array $r, $daysList, $barsList; ?string $day; ?int $bar; string $label, $btab, $bbase */ echo view('pro/bars/_nav', compact('btab', 'bbase'));
$T = $r['tot']; $q = array_filter(['jour' => $day, 'bar' => $bar]); ?>
<form class="filters card"><select name="jour" onchange="this.form.submit()"><option value="">Tout l'événement</option><?php foreach ($daysList as $d): ?><option value="<?= e($d) ?>" <?= $day === $d ? 'selected' : '' ?>><?= e(fr_date($d, false)) ?></option><?php endforeach; ?></select>
  <select name="bar" onchange="this.form.submit()"><option value="">Tous les points de vente</option><?php foreach ($barsList as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $bar === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select>
  <span class="grow"></span><a class="btn btn-sm btn-light" href="<?= url($bbase . '/rapport', $q + ['export' => 'csv']) ?>">CSV</a><a class="btn btn-sm" target="_blank" href="<?= url($bbase . '/rapport', $q + ['export' => 'pdf']) ?>">Rapport PDF</a></form>
<div class="kpi-grid bars-kpis mt">
  <div class="kpi-mini kpi-big"><span>Chiffre d'affaires TTC</span><b><?= e(money($T['ttc'])) ?></b><small class="muted">HT <?= e(money($T['ht'])) ?> · TVA <?= e(money($T['tva'])) ?> · <?= (int)$T['n'] ?> tickets</small></div>
  <div class="kpi-mini"><span>Cashless</span><b><?= e(money($T['cashless'])) ?></b></div>
  <div class="kpi-mini"><span>Carte</span><b><?= e(money($T['card'])) ?></b></div>
  <div class="kpi-mini"><span>Espèces</span><b><?= e(money($T['cash'])) ?></b></div>
  <?php $costed = array_sum(array_map(fn($x) => (int)($x['cht'] ?? 0), $r['prods'])); $marg = $costed - (int)$T['cost']; ?>
  <div class="kpi-mini"><span>Marge brute</span><b><?= $T['cost'] ? e(money($marg)) : '—' ?></b><small class="muted"><?= $T['cost'] && $costed ? (int)round($marg / $costed * 100) . ' % du HT des produits chiffrés' : 'renseigne les prix d\'achat' ?></small></div>
  <div class="kpi-mini"><span>Pourboires</span><b><?= e(money((int)$T['tip'])) ?></b><small class="muted">remboursé <?= e(money((int)$T['refund'])) ?></small></div>
  <div class="kpi-mini"><span>Offert / remises</span><b><?= e(money($T['offered'])) ?></b><small class="muted">remises <?= e(money($T['discount'])) ?></small></div>
</div>
<p class="muted mt"><small>Le chiffre d'affaires compte les consommations payées (hors consignes, offerts et crédits). Les recharges cashless ne sont pas du chiffre d'affaires : il est compté quand le solde est dépensé au bar.</small></p>
<div class="split mt">
  <div class="card table-card"><div class="card-head"><h2>TVA par taux</h2></div><div class="table-wrap"><table class="table table-sm"><thead><tr><th>Taux</th><th class="num">HT</th><th class="num">TVA</th><th class="num">TTC</th></tr></thead><tbody>
    <?php foreach ($r['vat'] as $rate => $v): ?><tr><td><?= e($rate) ?> %</td><td class="num"><?= e(money($v['ht'])) ?></td><td class="num"><?= e(money($v['tva'])) ?></td><td class="num"><b><?= e(money($v['ttc'])) ?></b></td></tr><?php endforeach; ?>
    <?php if (!$r['vat']): ?><tr><td colspan="4" class="empty">Aucune vente.</td></tr><?php endif; ?></tbody></table></div></div>
  <div class="card"><h2>Consignes et cashless</h2>
    <div class="list-item"><span class="grow">Consignes encaissées</span><b><?= e(money($T['dep_in'])) ?></b></div>
    <div class="list-item"><span class="grow">Consignes rendues</span><b>− <?= e(money($T['dep_out'])) ?></b></div>
    <div class="list-item"><span class="grow">Consignes conservées</span><b><?= e(money($T['dep_in'] - $T['dep_out'])) ?></b></div>
    <div class="list-item"><span class="grow">Recharges sur place</span><b><?= e(money($T['topup'])) ?></b></div>
    <div class="list-item"><span class="grow">Soldes remboursés sur place</span><b>− <?= e(money($T['cashout'])) ?></b></div></div>
</div>
<div class="card table-card mt"><div class="card-head"><h2>Par jour</h2></div><div class="table-wrap"><table class="table table-sm"><thead><tr><th>Jour</th><th class="num">Tickets</th><th class="num">CA TTC</th><th class="num">TVA</th><th class="num">Cashless</th><th class="num">Carte</th><th class="num">Espèces</th><th class="num">Offert</th><th class="num">Recharges</th></tr></thead><tbody>
  <?php foreach ($r['days'] as $d => $x): ?><tr><td><?= e(fr_date($d, false)) ?></td><td class="num"><?= (int)$x['n'] ?></td><td class="num"><b><?= e(money($x['ttc'])) ?></b></td><td class="num"><?= e(money($x['tva'])) ?></td><td class="num"><?= e(money($x['cashless'])) ?></td><td class="num"><?= e(money($x['card'])) ?></td><td class="num"><?= e(money($x['cash'])) ?></td><td class="num"><?= e(money($x['offered'])) ?></td><td class="num"><?= e(money($x['topup'])) ?></td></tr><?php endforeach; ?>
  <?php if (!$r['days']): ?><tr><td colspan="9" class="empty">Aucune vente.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="split mt">
  <div class="card table-card"><div class="card-head"><h2>Par point de vente</h2></div><div class="table-wrap"><table class="table table-sm"><thead><tr><th>Point de vente</th><th class="num">Tickets</th><th class="num">CA TTC</th><th class="num">Espèces</th></tr></thead><tbody>
    <?php foreach ($r['bars'] as $n => $x): ?><tr><td><?= e($n) ?></td><td class="num"><?= (int)$x['n'] ?></td><td class="num"><b><?= e(money($x['ttc'])) ?></b></td><td class="num"><?= e(money($x['cash'])) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
  <div class="card"><h2>Par catégorie</h2>
    <?php foreach ($r['cats'] as $n => $x): ?><div class="list-item"><span class="grow"><?= e($n) ?></span><small class="muted">× <?= (int)$x['q'] ?></small><b style="min-width:90px;text-align:right"><?= e(money((int)$x['t'])) ?></b></div><?php endforeach; ?></div>
</div>
<div class="card table-card mt"><div class="card-head"><h2>Par serveur</h2></div><div class="table-wrap"><table class="table table-sm"><thead><tr><th>Serveur</th><th class="num">Tickets</th><th class="num">CA TTC</th><th class="num">Cashless</th><th class="num">Carte</th><th class="num">Espèces</th><th class="num">Offert</th><th class="num">Remises</th></tr></thead><tbody>
  <?php foreach ($r['staff'] as $n => $x): ?><tr><td><?= e($n) ?></td><td class="num"><?= (int)$x['n'] ?></td><td class="num"><b><?= e(money($x['ttc'])) ?></b></td><td class="num"><?= e(money($x['cashless'])) ?></td><td class="num"><?= e(money($x['card'])) ?></td><td class="num"><?= e(money($x['cash'])) ?></td><td class="num"><?= e(money($x['offered'])) ?></td><td class="num"><?= e(money($x['discount'])) ?></td></tr><?php endforeach; ?>
  <?php if (!$r['staff']): ?><tr><td colspan="8" class="empty">Aucune vente.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="card table-card mt"><div class="card-head"><h2>Par produit · marges</h2></div><div class="table-wrap"><table class="table table-sm"><thead><tr><th>Produit</th><th class="num">Qté</th><th class="num">CA TTC</th><th class="num">CA HT</th><th class="num">Coût</th><th class="num">Marge</th><th class="num">Taux</th></tr></thead><tbody>
  <?php foreach ($r['prods'] as $n => $x): $hasC = isset($x['c']); $mg = $hasC ? (int)$x['cht'] - (int)$x['c'] : null; ?><tr><td><?= e($n) ?></td><td class="num"><?= (int)$x['q'] ?></td><td class="num"><?= e(money((int)$x['t'])) ?></td><td class="num"><?= e(money((int)($x['ht'] ?? 0))) ?></td><td class="num"><?= $hasC ? e(money((int)$x['c'])) : '—' ?></td><td class="num"><b><?= $mg !== null ? e(money($mg)) : '—' ?></b></td><td class="num"><?= $mg !== null && (int)$x['cht'] ? (int)round($mg / (int)$x['cht'] * 100) . ' %' : '—' ?></td></tr><?php endforeach; ?>
  <?php if (!$r['prods']): ?><tr><td colspan="7" class="empty">Aucune vente.</td></tr><?php endif; ?></tbody></table></div></div>
