<?php $form = function (?array $p) { $p = $p ?? ['id' => 0, 'name' => '', 'description' => '', 'price' => 0, 'stock' => 0, 'max_per_order' => 0, 'active' => 1, 'sort' => 0, 'variants' => '']; ob_start(); ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
    <div class="grid3"><div class="field u-span2"><label>Nom *</label><input name="name" value="<?= e($p['name']) ?>" required placeholder="T-shirt, parking, repas, vestiaire…"></div>
      <div class="field"><label>Prix</label><div class="input-suffix"><input name="price" value="<?= e(money_input((int)$p['price'])) ?>"><span>€</span></div></div></div>
    <div class="field"><label>Description</label><input name="description" value="<?= e($p['description']) ?>"></div>
    <div class="field"><label>Variantes (tailles, couleurs…)</label><input name="variants" value="<?= e((string)($p['variants'] ?? '')) ?>" placeholder="Ex. : S, M, L, XL"><small>Séparées par des virgules. L'acheteur choisit la sienne ; tu retrouves le détail par variante dans tes ventes.</small></div>
    <div class="grid3"><div class="field"><label>Stock</label><input type="number" name="stock" min="0" value="<?= (int)$p['stock'] ?>"><small>0 = illimité</small></div>
      <div class="field"><label>Max. par commande</label><input type="number" name="max_per_order" min="0" value="<?= (int)$p['max_per_order'] ?>"></div>
      <div class="field"><label>Ordre</label><input type="number" name="sort" value="<?= (int)$p['sort'] ?>"></div></div>
    <label class="check mb"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> En vente</label>
    <div class="form-actions"><button class="btn"><?= $p['id'] ? 'Enregistrer' : 'Ajouter' ?></button><?php if ($p['id']): ?><button class="btn btn-ghost" name="action" value="delete" data-confirm="Supprimer cette option ?">Supprimer</button><?php endif; ?></div>
  </form>
<?php return ob_get_clean(); }; ?>
<p class="muted">Ventes additionnelles proposées avec les billets : merch, parking, repas, consigne, navette… Elles apparaissent dans la commande et l'export participants.</p>
<?php foreach ($products as $p): ?>
  <details class="box"><summary><span><?= e($p['name']) ?> <?= $p['active'] ? '' : badge('draft') ?></span><span class="muted u-fw500"><?= e(money((int)$p['price'])) ?> · <?= (int)$p['sold'] ?><?= $p['stock'] ? ' / ' . (int)$p['stock'] : '' ?> vendus · <?= e(money((int)$p['revenue'])) ?></span></summary>
    <?php $byVar = $p['variants'] ? all("SELECT COALESCE(i.variant, '—') v, SUM(i.qty) n FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.product_id = ? AND o.status = 'paid' GROUP BY i.variant ORDER BY n DESC", [$p['id']]) : []; if ($byVar): ?>
      <div class="variant-sales"><?php foreach ($byVar as $v): ?><span class="chip"><?= e($v['v']) ?> <b><?= (int)$v['n'] ?></b></span><?php endforeach; ?></div>
    <?php endif; ?><?= $form($p) ?></details>
<?php endforeach; ?>
<details class="box add" <?= !$products ? 'open' : '' ?>><summary>+ Ajouter une option</summary><?= $form(null) ?></details>
