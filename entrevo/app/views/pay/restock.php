<?php /** @var array $open, $done, $bars, $stockBy, $losses, $byReason, $ev; int $lossValue, $value; string $base */
$RS = ['casse' => 'Casse', 'perime' => 'Périmé', 'offert' => 'Offert hors caisse', 'vol' => 'Vol', 'autre' => 'Autre']; ?>
<div class="pk">
  <div class="<?= $open ? 'warn' : '' ?>"><span>Demandes en attente</span><b><?= count($open) ?></b><small>envoyées depuis l'appli caisse</small></div>
  <div><span>Valeur du stock</span><b><?= e(money($value)) ?></b><small>au prix d'achat HT</small></div>
  <div class="<?= $lossValue ? 'warn' : '' ?>"><span>Pertes valorisées</span><b><?= e(money($lossValue)) ?></b><small><?= array_sum(array_column($byReason, 'q')) ?> article(s)</small></div>
</div>
<div class="card table-card"><div class="card-head"><h2>Demandes de réassort</h2><a class="btn btn-sm btn-light" href="<?= url($base . '/stocks') ?>">Stocks</a></div><div class="table-wrap"><table class="table">
  <thead><tr><th>Demande</th><th>Point de vente</th><th class="num">Stock actuel</th><th>Livrer</th><th></th></tr></thead><tbody>
  <?php foreach ($open as $r): ?><tr>
    <td><b><?= (int)$r['qty'] ?> × <?= e($r['pname']) ?></b><br><small class="muted"><?= e(ago($r['created_at'])) ?><?= $r['staff'] ? ' · ' . e($r['staff']) : '' ?><?= $r['note'] ? ' · « ' . e($r['note']) . ' »' : '' ?></small></td>
    <td><?= e($r['bname']) ?></td><td class="num"><b class="<?= (int)$r['cur'] <= 0 ? 'text-red' : '' ?>"><?= (int)$r['cur'] ?></b></td>
    <td><form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="deliver">
      <input name="qty" inputmode="numeric" value="<?= (int)$r['qty'] ?>" style="max-width:70px">
      <select name="source"><option value="reserve">depuis la réserve</option><?php foreach ($bars as $b): if ((int)$b['id'] === (int)$r['bar_id']) continue; ?><option value="<?= (int)$b['id'] ?>">depuis <?= e($b['name']) ?> (<?= (int)($stockBy[(int)$r['product_id']][(int)$b['id']] ?? 0) ?>)</option><?php endforeach; ?></select>
      <button class="btn btn-sm">Livré</button></form></td>
    <td class="num"><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="refuse">Refuser</button></form></td></tr><?php endforeach; ?>
  <?php if (!$open): ?><tr><td colspan="5" class="pempty">Aucune demande en attente. Les bars en envoient depuis l'appli : menu › Stock du bar › Réassort.</td></tr><?php endif; ?>
</tbody></table></div></div>
<div class="pgrid mt">
  <div class="card table-card"><div class="card-head"><h2>Pertes déclarées</h2></div><div class="table-wrap"><table class="table table-sm"><tbody>
    <?php foreach ($losses as $l): $why = $l['kind'] === 'loss' ? explode(' · ', (string)$l['note']) : ['casse']; ?><tr><td><?= e(date('d/m H:i', strtotime($l['created_at']))) ?></td><td><b><?= -(int)$l['delta'] ?> × <?= e($l['pname']) ?></b><br><small class="muted"><?= e($l['bname']) ?><?= isset($why[1]) ? ' · ' . e(implode(' · ', array_slice($why, 1))) : '' ?></small></td><td><span class="ptag warn"><?= e($RS[$why[0]] ?? $why[0]) ?></span></td><td class="num"><?= $l['cost'] !== null ? e(money(-(int)$l['delta'] * (int)$l['cost'])) : '—' ?></td></tr><?php endforeach; ?>
    <?php if (!$losses): ?><tr><td class="pempty">Aucune perte déclarée.</td></tr><?php endif; ?></tbody></table></div></div>
  <div class="stack">
    <div class="card"><h2>Pertes par motif</h2><?php foreach ($byReason as $r => $x): ?><div class="list-item"><span class="grow"><?= e($RS[$r] ?? $r) ?></span><small class="muted"><?= (int)$x['q'] ?> art.</small><b style="min-width:80px;text-align:right"><?= e(money((int)$x['v'])) ?></b></div><?php endforeach; ?><?php if (!$byReason): ?><p class="pnote">—</p><?php endif; ?></div>
    <div class="card"><h2>Dernières livraisons</h2><?php foreach ($done as $r): ?><div class="list-item"><span class="grow"><?= e($r['pname']) ?> · <?= e($r['bname']) ?><br><small class="muted"><?= e(ago((string)$r['done_at'])) ?><?= $r['who'] ? ' · ' . e($r['who']) : '' ?></small></span><?= $r['status'] === 'done' ? '<b>+' . (int)$r['done_qty'] . '</b>' : '<span class="ptag">refusée</span>' ?></div><?php endforeach; ?><?php if (!$done): ?><p class="pnote">—</p><?php endif; ?></div>
  </div>
</div>
