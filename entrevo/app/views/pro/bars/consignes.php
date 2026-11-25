<?php /** @var array $cups, $byBar, $products; string $btab, $bbase */ echo view('pro/bars/_nav', compact('btab', 'bbase')); ?>
<p class="muted lead-sm">Gobelets, verres, pichets… La consigne s'ajoute automatiquement à chaque produit concerné ; au retour, le serveur la rend en espèces ou sur le cashless. Une consigne non remboursable devient un gobelet souvenir vendu.</p>
<div class="dep-grid">
  <?php foreach ($cups as $c): ?>
    <div class="card dep-card<?= (int)$c['active'] ? '' : ' off' ?>">
      <div class="dep-head"><?php if ($c['photo']): ?><img src="<?= e(url($c['photo'])) ?>" alt=""><?php else: ?><span class="dep-ph"></span><?php endif; ?><div class="grow"><b><?= e($c['name']) ?></b><small class="muted"><?= e(money((int)$c['amount'])) ?> · <?= (int)$c['refundable'] ? 'remboursable' : 'non remboursable (souvenir)' ?></small></div></div>
      <div class="dep-stats"><div><span>Sortis</span><b><?= (int)$c['issued'] ?></b></div><div><span>Rendus</span><b><?= (int)$c['returned'] ?></b></div><div><span>En circulation</span><b><?= (int)$c['out'] ?></b></div><div><span>Restants</span><b><?= $c['left'] === null ? '—' : (int)$c['left'] ?></b></div></div>
      <p class="muted mb0"><small>Encaissé <?= e(money((int)$c['value_in'])) ?> · rendu <?= e(money((int)$c['value_out'])) ?> · gardé <?= e(money((int)$c['value_in'] - (int)$c['value_out'])) ?></small></p>
      <details class="mt"><summary class="linklike">Modifier</summary><form method="post" enctype="multipart/form-data" class="mt"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="deposit_id" value="<?= (int)$c['id'] ?>">
        <div class="grid2"><div class="field"><label>Nom</label><input name="name" value="<?= e($c['name']) ?>"></div><div class="field"><label>Montant</label><div class="input-suffix"><input name="amount" inputmode="decimal" value="<?= e(money_input((int)$c['amount'])) ?>"><span>€</span></div></div>
          <div class="field"><label>Stock de départ</label><input name="stock_initial" inputmode="numeric" value="<?= $c['stock_initial'] !== null ? (int)$c['stock_initial'] : '' ?>" placeholder="facultatif"></div><div class="field"><label>Photo</label><input type="file" name="photo" accept="image/*"></div></div>
        <label class="check"><input type="checkbox" name="refundable" value="1" <?= (int)$c['refundable'] ? 'checked' : '' ?>> <span>Remboursable au retour</span></label>
        <div class="row mt"><button class="btn btn-sm">Enregistrer</button><button class="btn btn-sm btn-light" name="action" value="toggle"><?= (int)$c['active'] ? 'Désactiver' : 'Réactiver' ?></button></div></form></details>
    </div>
  <?php endforeach; ?>
  <form method="post" enctype="multipart/form-data" class="card dep-card dep-new"><?= csrf_field() ?><input type="hidden" name="action" value="save"><h2>Nouvelle consigne</h2>
    <div class="grid2"><div class="field"><label>Nom</label><input name="name" required placeholder="Ecocup 25 cl"></div><div class="field"><label>Montant</label><div class="input-suffix"><input name="amount" inputmode="decimal" required placeholder="1,00"><span>€</span></div></div>
      <div class="field"><label>Stock de départ</label><input name="stock_initial" inputmode="numeric" placeholder="3000"></div><div class="field"><label>Photo</label><input type="file" name="photo" accept="image/*"></div></div>
    <label class="check"><input type="checkbox" name="refundable" value="1" checked> <span>Remboursable au retour</span></label><button class="btn btn-sm mt">Créer</button></form>
</div>
<div class="split mt">
  <div class="card"><h2>Par point de vente</h2>
    <?php foreach ($byBar as $bar => $v): ?><div class="list-item"><span class="grow"><?= e($bar) ?></span><small class="muted"><?= (int)($v['deposit'] ?? 0) ?> sortis · <?= (int)($v['return'] ?? 0) ?> rendus</small></div><?php endforeach; ?>
    <?php if (!$byBar): ?><p class="muted mb0">Pas encore de mouvement.</p><?php endif; ?></div>
  <div class="card"><h2>Produits consignés</h2>
    <?php foreach ($products as $p): ?><div class="list-item"><span class="grow"><?= e($p['name']) ?></span><small class="muted"><?= e($p['dep']) ?></small></div><?php endforeach; ?>
    <?php if (!$products): ?><p class="muted mb0">Choisis la consigne de chaque produit dans le <a href="<?= url($bbase . '/catalogue') ?>">catalogue</a>.</p><?php endif; ?></div>
</div>
