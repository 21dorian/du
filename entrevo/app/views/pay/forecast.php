<?php /** @var array $F, $ev; int $win, $cover; string $base */
$soon = array_filter($F, fn($f) => $f['mins'] !== null && $f['mins'] <= 60 && $f['qty'] > 0);
$out = array_filter($F, fn($f) => $f['qty'] <= 0); ?>
<div class="pk">
  <div class="<?= $soon ? 'warn' : 'good' ?>"><span>Rupture dans l'heure</span><b><?= count($soon) ?></b><small>au rythme actuel</small></div>
  <div class="<?= $out ? 'warn' : '' ?>"><span>Déjà en rupture</span><b><?= count($out) ?></b></div>
  <div><span>Produits suivis</span><b><?= count($F) ?></b><small>stock par point de vente</small></div>
</div>
<div class="card"><form class="psearch"><label class="pnote">Rythme calculé sur</label><select name="w" onchange="this.form.submit()"><?php foreach ([30 => '30 dernières min', 60 => 'dernière heure', 120 => '2 dernières heures'] as $k => $l): ?><option value="<?= $k ?>" <?= $win === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
  <label class="pnote">Réassort pour tenir</label><select name="c" onchange="this.form.submit()"><?php foreach ([60 => '1 h', 120 => '2 h', 180 => '3 h', 240 => '4 h'] as $k => $l): ?><option value="<?= $k ?>" <?= $cover === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></form></div>
<div class="card table-card mt"><div class="table-wrap"><table class="table"><thead><tr><th>Produit</th><th>Point de vente</th><th class="num">Stock</th><th class="num">Vendu / h</th><th>Rupture estimée</th><th class="num">Réassort suggéré</th><th></th></tr></thead><tbody>
  <?php foreach ($F as $f): ?><tr>
    <td><b><?= e($f['name']) ?></b><?= $f['requested'] ? '<br><span class="ptag warn">' . (int)$f['requested'] . ' demandé(s) par le bar</span>' : '' ?></td><td><?= e($f['bar']) ?></td>
    <td class="num"><b class="<?= $f['qty'] <= 0 ? 'text-red' : '' ?>"><?= (int)$f['qty'] ?></b></td><td class="num"><?= (int)$f['per_hour'] ?: '—' ?></td>
    <td><?= $f['qty'] <= 0 ? '<span class="ptag bad">en rupture</span>' : ($f['mins'] === null ? '<span class="muted">pas de vente récente</span>' : '<span class="ptag ' . e($f['level']) . '">vers ' . e($f['eta']) . ' · ' . ($f['mins'] >= 120 ? floor($f['mins'] / 60) . ' h' : (int)$f['mins'] . ' min') . '</span>') ?></td>
    <td class="num"><?= $f['suggest'] ? '<b>' . (int)$f['suggest'] . '</b>' : '—' ?></td>
    <td class="num"><?php if ($f['suggest'] || $f['qty'] <= 0): ?><form method="post" class="row" style="justify-content:flex-end"><?= csrf_field() ?><input type="hidden" name="action" value="deliver"><input type="hidden" name="product_id" value="<?= (int)$f['product_id'] ?>"><input type="hidden" name="bar_id" value="<?= (int)$f['bar_id'] ?>"><input name="qty" inputmode="numeric" value="<?= max((int)$f['suggest'], (int)$f['requested'], 6) ?>" style="max-width:64px"><button class="btn btn-sm">Livré</button></form><?php endif; ?></td></tr><?php endforeach; ?>
  <?php if (!$F): ?><tr><td colspan="7" class="pempty">Aucun produit suivi en stock. Active « Suivre le stock » sur les produits (Carte › Produits) et fais une première entrée.</td></tr><?php endif; ?>
</tbody></table></div></div>
<p class="pnote mt">Le rythme compte les ventes moins les annulations et remboursements sur la période choisie. Les ruptures de moins d'une heure apparaissent aussi dans les alertes du direct. « Livré » ajoute le stock depuis la réserve et clôt la demande du bar.</p>
