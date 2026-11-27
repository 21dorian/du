<?php /** @var array $ev, $D */ $b = '/pro/evenements/' . $ev['id'] . '/bordereau'; ?>
<div class="kpi-grid"><div class="kpi-mini"><span>Entrées payantes</span><b><?= (int)$D['t']['paid'] ?></b><small class="muted"><?= (int)$D['t']['free'] ?> exonérées</small></div>
  <div class="kpi-mini"><span>Recette nette TTC</span><b><?= e(money($D['t']['net'])) ?></b><small class="muted">hors frais</small></div>
  <div class="kpi-mini"><span>Recette nette HT</span><b><?= e(money($D['t']['ht'])) ?></b><small class="muted">TVA <?= e(money($D['t']['vat'])) ?></small></div>
  <div class="kpi-mini"><span>Taxe spectacles (3,5 %)</span><b><?= e(money((int)round($D['t']['ht'] * 0.035))) ?></b><small class="muted">indicatif</small></div></div>
<div class="card table-card mt"><div class="card-head"><h2>Par tarif</h2><div class="row"><a class="btn btn-sm" target="_blank" href="<?= url($b, ['format' => 'pdf']) ?>">PDF</a><a class="btn btn-sm btn-light" href="<?= url($b, ['format' => 'csv']) ?>">CSV</a></div></div>
  <div class="table-wrap"><table class="table"><thead><tr><th>Tarif</th><th class="num">Prix TTC</th><th class="num">Billets</th><th class="num">Brut</th><th class="num">Remises</th><th class="num">Rembours.</th><th class="num">Net TTC</th><th class="num">TVA</th><th class="num">Net HT</th></tr></thead><tbody>
  <?php foreach ($D['rows'] as $r): ?><tr><td><?= e($r['name']) ?><?= $r['free'] ? ' <span class="tag">exonéré</span>' : '' ?></td><td class="num"><?= e(money($r['unit'])) ?></td><td class="num"><?= $r['n'] ?></td><td class="num"><?= e(money($r['gross'])) ?></td><td class="num"><?= $r['disc'] ? '−' . e(money($r['disc'])) : '—' ?></td><td class="num"><?= $r['refd'] ? '−' . e(money($r['refd'])) : '—' ?></td><td class="num"><b><?= e(money($r['net'])) ?></b></td><td class="num"><?= e(Vat::label($r['vat'])) ?></td><td class="num"><?= e(money($r['ht'])) ?></td></tr><?php endforeach; ?>
  <?php if (!$D['rows']): ?><tr><td colspan="9" class="empty">Aucune vente.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="card mt"><h2>Hors billetterie</h2>
  <div class="list-item"><span class="grow">Frais par billet (gestion, production…)</span><b><?= e(money($D['fees'])) ?></b></div>
  <div class="list-item"><span class="grow">Frais de service de la plateforme</span><b><?= e(money((int)$D['other']['svc'])) ?></b></div>
  <div class="list-item"><span class="grow">Dons</span><b><?= e(money((int)$D['other']['don'])) ?></b></div>
  <div class="list-item"><span class="grow">Options et produits</span><b><?= e(money($D['prod'])) ?></b></div>
  <p class="muted mb0"><small>Le bordereau sert de base aux déclarations SACEM / SACD et à la taxe sur les spectacles (CNM). Le taux de 3,5 % correspond aux variétés : vérifie celui de ton genre de spectacle.</small></p></div>
