<?php /** @var array $ev, $stocks, $rates */ $base = '/pro/evenements/' . $ev['id']; ?>
<div class="page-head"><div><p class="muted" style="margin-top:0">Des stocks de billets au porteur, numérotés et contrôlables, pour un guichet hors ligne, un revendeur ou un dépositaire. À la fin, déclare les invendus : ils sont annulés et la recette est comptée.</p></div></div>
<form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="create">
  <h2>Nouveau stock</h2>
  <div class="grid4">
    <div class="field"><label>Tarif</label><select name="rate_id" required><?php foreach ($rates as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?> · <?= e(money((int)$r['price'])) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Nombre de billets</label><input type="number" name="qty" min="1" max="500" value="50" required></div>
    <div class="field"><label>Prix imprimé</label><div class="input-suffix"><input name="price" inputmode="decimal" placeholder="prix du tarif"><span>€</span></div></div>
    <div class="field"><label>Destination</label><input name="label" maxlength="160" placeholder="Office de tourisme, guichet du soir…"></div>
  </div>
  <div><button class="btn">Générer les billets</button></div>
</form>
<div class="card table-card mt"><div class="card-head"><h2>Stocks</h2></div>
<?php if (!$stocks): ?><p class="muted" style="padding:0 20px 20px">Aucun stock pour l'instant.</p><?php else: ?>
<table class="table"><thead><tr><th>Destination</th><th>Tarif</th><th class="num">Billets</th><th class="num">Scannés</th><th>État</th><th>Imprimer</th><th></th></tr></thead><tbody>
<?php foreach ($stocks as $s): ?><tr>
  <td><b><?= e($s['label']) ?></b><br><small class="muted"><?= e(short_date($s['created_at'])) ?></small></td>
  <td><?= e($s['rate_name']) ?><br><small class="muted"><?= e(money((int)$s['unit_price'])) ?></small></td>
  <td class="num"><?= (int)$s['valid'] ?> / <?= (int)$s['qty'] ?></td><td class="num"><?= (int)$s['scanned'] ?></td>
  <td><?= $s['closed_at'] ? '<span class="badge ok">Soldé · ' . (int)$s['sold'] . ' vendus · ' . e(money((int)$s['sold'] * (int)$s['unit_price'])) . '</span>' : '<span class="badge">En circulation</span>' ?></td>
  <td class="nowrap"><a class="btn btn-sm btn-light" target="_blank" href="<?= url($base . '/stocks/' . $s['id'] . '.pdf') ?>">A4</a> <a class="btn btn-sm btn-light" target="_blank" href="<?= url($base . '/stocks/' . $s['id'] . '.pdf', ['format' => 'boca']) ?>">Boca</a> <a class="btn btn-sm btn-light" target="_blank" href="<?= url($base . '/stocks/' . $s['id'] . '.pdf', ['format' => 'ticket']) ?>">80 mm</a></td>
  <td><?php if (!$s['closed_at']): ?><details class="menu"><summary class="btn btn-sm">Solder</summary><div class="menu-list" style="min-width:300px;padding:12px">
    <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="stock_id" value="<?= (int)$s['id'] ?>">
      <div class="field mb0"><label>Billets invendus rendus</label><input type="number" name="unsold" min="0" max="<?= (int)$s['valid'] ?>" value="0"></div>
      <div class="field mb0"><label>Numéros des invendus <small class="muted">(facultatif)</small></label><textarea name="codes" rows="2" placeholder="Codes séparés par des espaces"></textarea><small>Sinon, les derniers billets non scannés sont annulés.</small></div>
      <div class="field mb0"><label>Encaissé en</label><select name="pay_method"><option value="especes">Espèces</option><option value="cb">CB</option><option value="cheque">Chèque</option><option value="virement">Virement</option></select></div>
      <button class="btn btn-sm" name="action" value="close">Solder le stock</button>
      <button class="btn btn-sm btn-ghost" name="action" value="cancel" data-confirm="Annuler tout le stock ? Les billets imprimés ne passeront plus.">Annuler tout le stock</button>
    </form></div></details><?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table><?php endif; ?>
</div>
