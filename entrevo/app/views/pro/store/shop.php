<?php $form = function (?array $p) { $p = $p ?? ['id' => 0, 'name' => '', 'description' => '', 'price' => 0, 'stock' => 0, 'variants' => '', 'image' => null, 'delivery' => 'pickup', 'ship_price' => 0, 'active' => 1, 'sort' => 0]; ob_start(); ?>
  <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
    <div class="grid2">
      <div class="field"><label>Nom</label><input name="name" required value="<?= e($p['name']) ?>" placeholder="T-shirt du festival, affiche sérigraphiée…"></div>
      <div class="field"><label>Prix</label><div class="input-suffix"><input name="price" value="<?= e(money_input((int)$p['price'])) ?>" inputmode="decimal"><span>€</span></div></div>
      <div class="field"><label>Stock</label><input type="number" name="stock" min="0" value="<?= (int)$p['stock'] ?>"><small>0 = illimité</small></div>
      <div class="field"><label>Variantes</label><input name="variants" value="<?= e((string)$p['variants']) ?>" placeholder="S, M, L, XL"></div>
      <div class="field"><label>Livraison</label><select name="delivery"><?php foreach (['pickup' => 'Retrait sur place', 'ship' => 'Envoi postal', 'both' => 'Retrait ou envoi'] as $k => $l): ?><option value="<?= $k ?>" <?= $p['delivery'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="field" data-show-if="delivery=ship|both"><label>Frais d'envoi</label><div class="input-suffix"><input name="ship_price" value="<?= e(money_input((int)$p['ship_price'])) ?>" inputmode="decimal"><span>€</span></div></div>
    </div>
    <div class="field"><label>Description</label><textarea name="description" rows="2" maxlength="1000"><?= e((string)$p['description']) ?></textarea></div>
    <div class="field"><label>Photo</label><?php if ($p['image']): ?><img src="<?= url($p['image']) ?>" alt="" style="max-height:70px;border-radius:10px;display:block;margin-bottom:6px"><?php endif; ?><input type="file" name="image" accept="image/png,image/jpeg,image/webp"></div>
    <label class="check mb"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> <span>En vente</span></label>
    <div class="form-actions"><button class="btn"><?= $p['id'] ? 'Enregistrer' : 'Ajouter le produit' ?></button><?php if ($p['id']): ?><button class="btn btn-light" name="action" value="delete" formnovalidate data-confirm="Supprimer ce produit ?">Supprimer</button><?php endif; ?></div>
  </form>
<?php return ob_get_clean(); }; ?>
<div class="split-3">
  <div class="stack">
    <?php foreach ($products as $p): ?>
      <details class="box"><summary><span><?= e($p['name']) ?> <?= $p['active'] ? '' : badge('draft') ?></span><span class="muted" style="font-weight:500"><?= e(money((int)$p['price'])) ?> · <?= (int)$p['sold'] ?><?= $p['stock'] ? ' / ' . (int)$p['stock'] : '' ?> vendus</span></summary><?= $form($p) ?></details>
    <?php endforeach; ?>
    <details class="box add" <?= !$products ? 'open' : '' ?>><summary>+ Ajouter un produit</summary><?= $form(null) ?></details>
    <div class="card table-card"><div class="card-head"><h2>Commandes boutique</h2></div>
      <div class="table-wrap"><table class="table"><thead><tr><th>Commande</th><th>Articles</th><th>Livraison</th><th class="num">Total</th><th></th></tr></thead><tbody>
      <?php foreach ($orders as $o): ?><tr><td><b><?= e($o['ref']) ?></b><br><small class="muted"><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?> · <?= e(short_date($o['created_at'])) ?></small></td><td><small><?= e((string)$o['detail']) ?></small></td>
        <td><small><?= $o['delivery'] === 'ship' ? 'Envoi : ' . nl2br(e((string)$o['ship_address'])) : 'Retrait' ?></small></td><td class="num"><?= e(money((int)$o['total'])) ?></td>
        <td class="right"><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><button class="btn btn-sm <?= $o['fulfilled_at'] ? 'btn-light' : '' ?>" name="action" value="fulfil"><?= $o['fulfilled_at'] ? ($o['delivery'] === 'ship' ? 'Expédiée' : 'Retirée') . ' ✓' : ($o['delivery'] === 'ship' ? 'Marquer expédiée' : 'Marquer retirée') ?></button></form></td></tr><?php endforeach; ?>
      <?php if (!$orders): ?><tr><td colspan="5" class="empty">Pas encore de commande.</td></tr><?php endif; ?></tbody></table></div></div>
  </div>
  <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
    <h2>Ta boutique</h2>
    <label class="check mb"><input type="checkbox" name="shop_enabled" value="1" <?= $org['shop_enabled'] ? 'checked' : '' ?>> <span>Boutique ouverte sur ma page</span></label>
    <div class="field"><label>Infos de retrait</label><textarea name="pickup_info" rows="3" placeholder="Au stand merch le soir de l'événement, ou au local le mercredi 14h-18h"><?= e((string)$org['pickup_info']) ?></textarea></div>
    <button class="btn btn-block">Enregistrer</button>
    <?php if ($org['shop_enabled']): ?><p class="muted mt mb0"><a href="<?= url('/o/' . $org['slug'] . '/boutique') ?>" target="_blank">Voir la boutique</a></p><?php endif; ?>
    <p class="muted mt mb0" style="font-size:13.5px">Pour vendre un t-shirt avec un billet, utilise plutôt les « Options » de l'événement.</p>
  </form>
</div>
