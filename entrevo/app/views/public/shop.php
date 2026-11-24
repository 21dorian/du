<div class="store-head">
  <span class="eyebrow"><?= e($org['name']) ?></span>
  <h1>Boutique</h1>
  <?php if ($org['pickup_info']): ?><p class="lead">Retrait : <?= e($org['pickup_info']) ?></p><?php endif; ?>
</div>
<?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" class="stack" id="shop"><?= csrf_field() ?>
  <div class="shop-grid">
    <?php foreach ($products as $p): $vars = array_filter(array_map('trim', explode(',', (string)$p['variants']))); $out = $p['left'] === 0; ?>
      <div class="shop-card<?= $out ? ' out' : '' ?>">
        <div class="shop-img"><?php if ($p['image']): ?><img src="<?= url($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy"><?php else: ?><?= ui_icon('bag', 40) ?><?php endif; ?></div>
        <div class="shop-body">
          <b><?= e($p['name']) ?></b>
          <?php if ($p['description']): ?><p class="muted"><?= e($p['description']) ?></p><?php endif; ?>
          <div class="shop-row"><span class="shop-price"><?= e(money((int)$p['price'])) ?></span>
            <?php if ($out): ?><span class="badge grey">Épuisé</span><?php else: ?>
            <div class="qty" data-base="<?= (int)$p['price'] ?>"><button type="button" data-d="-1">−</button><input type="number" name="q[<?= (int)$p['id'] ?>]" value="0" min="0" max="<?= min(20, $p['left'] === PHP_INT_MAX ? 20 : (int)$p['left']) ?>" aria-label="Quantité"><button type="button" data-d="1">+</button></div><?php endif; ?></div>
          <?php if ($vars && !$out): ?><label class="variant-pick" style="justify-self:start">Taille / modèle <select name="v[<?= (int)$p['id'] ?>]"><?php foreach ($vars as $v): ?><option><?= e($v) ?></option><?php endforeach; ?></select></label><?php endif; ?>
          <small class="muted"><?= ['pickup' => 'Retrait sur place', 'ship' => 'Envoi postal', 'both' => 'Retrait ou envoi'][$p['delivery']] ?? '' ?><?= $p['delivery'] !== 'pickup' && (int)$p['ship_price'] ? ' · envoi ' . e(money((int)$p['ship_price'])) : '' ?></small>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$products): ?><div class="card empty">La boutique est vide pour l'instant.</div><?php endif; ?>
  </div>
  <?php if ($products): ?>
  <div class="card">
    <h2>Livraison</h2>
    <div class="seg seg-full"><label class="seg-r"><input type="radio" name="delivery" value="pickup" checked><span>Retrait sur place</span></label><label class="seg-r"><input type="radio" name="delivery" value="ship"><span>Envoi postal</span></label></div>
    <div class="field mt" data-show-if="delivery=ship"><label>Adresse de livraison</label><textarea name="address" rows="3" placeholder="Nom, rue, code postal, ville"></textarea></div>
    <h2 class="mt">Tes coordonnées</h2><?= view('partials/buyer_fields') ?>
    <button class="btn btn-lg">Continuer vers le paiement</button>
  </div>
  <?php endif; ?>
</form>
<script>
document.querySelectorAll('#shop .qty').forEach(function (q) { var i = q.querySelector('input');
  q.querySelectorAll('button').forEach(function (b) { b.addEventListener('click', function () { i.value = Math.max(0, Math.min(+i.max || 20, (+i.value || 0) + +b.dataset.d)); }); }); });
</script>
