<?php /** @var array $E, $ev; string $base */
$issued = array_sum(array_column($E['types'], 'issued')); $ret = array_sum(array_column($E['types'], 'returned')); $kept = array_sum(array_map(fn($t) => $t['value_in'] - $t['value_out'], $E['types'])); ?>
<div class="pk">
  <div class="hero"><span>Taux de retour</span><b><?= $issued ? (int)round($ret / $issued * 100) : 0 ?> %</b><small><?= $ret ?> rendus sur <?= $issued ?> remis</small></div>
  <div><span>Consignes en circulation</span><b><?= max(0, $issued - $ret) ?></b></div>
  <div><span>Consignes conservées</span><b><?= e(money($kept)) ?></b><small>non rendues : recette de l'événement</small></div>
  <div><span>Remboursées sur le cashless</span><b><?= e(money($E['to_wallet'])) ?></b><small>retour crédité automatiquement</small></div>
</div>
<div class="pgrid-2">
  <div class="card table-card"><div class="card-head"><h2>Par type de consigne</h2><a class="btn btn-sm btn-light" href="<?= url($base . '/consignes') ?>">Gérer les types</a></div><div class="table-wrap"><table class="table"><thead><tr><th>Type</th><th class="num">Remis</th><th class="num">Rendus</th><th class="num">Taux</th><th class="num">Stock restant</th></tr></thead><tbody>
    <?php foreach ($E['types'] as $t): ?><tr><td><b><?= e($t['name']) ?></b><br><small class="muted"><?= e(money((int)$t['amount'])) ?><?= (int)$t['refundable'] ? ' · remboursable' : ' · non remboursable' ?></small></td><td class="num"><?= (int)$t['issued'] ?></td><td class="num"><?= (int)$t['returned'] ?></td><td class="num"><?= $t['issued'] ? (int)round($t['returned'] / $t['issued'] * 100) . ' %' : '—' ?></td><td class="num"><?= $t['left'] !== null ? (int)$t['left'] : '—' ?></td></tr><?php endforeach; ?>
    <?php if (!$E['types']): ?><tr><td colspan="5" class="pempty">Aucun type de consigne.</td></tr><?php endif; ?></tbody></table></div></div>
  <div class="card table-card"><div class="card-head"><h2>Par point de vente</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>Point de vente</th><th class="num">Remis</th><th class="num">Rendus</th><th class="num">Solde</th></tr></thead><tbody>
    <?php foreach ($E['bars'] as $n => $x): ?><tr><td><?= e($n) ?></td><td class="num"><?= (int)($x['deposit'] ?? 0) ?></td><td class="num"><?= (int)($x['return'] ?? 0) ?></td><td class="num"><b><?= (int)($x['deposit'] ?? 0) - (int)($x['return'] ?? 0) ?></b></td></tr><?php endforeach; ?>
    <?php if (!$E['bars']): ?><tr><td colspan="4" class="pempty">Pas encore de mouvement.</td></tr><?php endif; ?></tbody></table></div></div>
</div>
<p class="pnote mt">Au bar, « Consigne rendue » rend le gobelet : payé en cashless, le montant est recrédité directement sur le bracelet ou la carte du festivalier.</p>
