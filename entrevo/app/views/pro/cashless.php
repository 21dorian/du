<?php /** @var array $ev, $bars, $products, $k, $sold, $hours, $refunds */ $pos = url('/bar/' . $ev['id']); ?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Rechargé</span><b><?= e(money((int)$k['loaded'])) ?></b><small class="muted"><?= e(money((int)$k['online'])) ?> en ligne · <?= e(money((int)$k['onsite'])) ?> sur place</small></div>
  <div class="kpi-mini"><span>Dépensé aux bars</span><b><?= e(money((int)$k['spent'])) ?></b><small class="muted"><?= (int)$k['sales'] ?> ventes · panier <?= e(money((int)$k['basket'])) ?></small></div>
  <div class="kpi-mini"><span>Soldes restants</span><b><?= e(money((int)$k['left'])) ?></b><small class="muted"><?= (int)$k['wallets'] ?> porte-monnaie · <?= e(money((int)$k['refunded'])) ?> remboursés</small></div>
  <div class="kpi-mini"><span>Caisse bar</span><b style="font-size:15px"><a href="<?= $pos ?>" target="_blank">Ouvrir la caisse</a></b><small class="muted">sur tablette ou téléphone</small></div>
</div>
<?php if (!(int)$ev['cashless_enabled']): ?><div class="notice info mb">Le cashless est désactivé : active-le ci-dessous, ajoute tes bars et ta carte, puis ouvre la caisse sur les tablettes des bars.</div><?php endif; ?>
<div class="split-3">
  <div>
    <div class="card table-card"><div class="card-head"><h2>Carte des bars</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>Produit</th><th class="num">Prix</th><th class="num">Vendus</th><th></th></tr></thead><tbody>
      <?php foreach ($products as $p): ?><tr style="<?= $p['active'] ? '' : 'opacity:.5' ?>"><td><b><?= e($p['name']) ?></b><br><small class="muted"><?= e($p['category']) ?><?= (int)$p['deposit'] ? ' · consigne ' . e(money((int)$p['deposit'])) : '' ?></small></td><td class="num"><?= e(money((int)$p['price'])) ?></td><td class="num"><?= (int)($sold[$p['name']]['qty'] ?? 0) ?></td>
        <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="product_toggle"><?= $p['active'] ? 'Retirer' : 'Remettre' ?></button></form></td></tr><?php endforeach; ?>
      <?php if (!$products): ?><tr><td colspan="4" class="empty">Ajoute ta carte : bière, soft, sandwich…</td></tr><?php endif; ?>
    </tbody></table></div>
      <form method="post" class="charge-form prod-form"><?= csrf_field() ?><input type="hidden" name="action" value="product">
        <input name="name" placeholder="Produit" required><input name="category" placeholder="Catégorie" value="boisson"><div class="input-suffix"><input name="price" inputmode="decimal" placeholder="Prix" required><span>€</span></div><div class="input-suffix"><input name="deposit" inputmode="decimal" placeholder="Consigne"><span>€</span></div><button class="btn btn-sm">Ajouter</button></form></div>
    <?php if ($hours): ?><div class="card"><h2>Ventes par heure</h2><?= Viz::chart('columns', ['labels' => array_column($hours, 'h'), 'xlabel' => 'Heure', 'series' => [['name' => 'Ventes', 'values' => array_map('intval', array_column($hours, 's'))]]], 'Ventes par heure', 'money', 200) ?></div><?php endif; ?>
    <?php if ($refunds): ?><div class="card table-card"><div class="card-head"><h2>Remboursements demandés</h2><small class="muted">à faire par virement</small></div><div class="table-wrap"><table class="table"><tbody>
      <?php foreach ($refunds as $w): ?><tr><td class="mono"><?= e($w['code']) ?><br><small class="muted"><?= e((string)$w['email']) ?></small></td><td class="mono"><?= e(trim(chunk_split((string)$w['refund_iban'], 4, ' '))) ?></td><td class="num"><b><?= e(money((int)$w['balance'])) ?></b></td>
        <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="wallet_id" value="<?= (int)$w['id'] ?>"><button class="btn btn-sm" name="action" value="refunded" data-confirm="Virement fait ?">Virement fait</button></form></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
  </div>
  <div class="stack">
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <h2>Réglages</h2>
      <label class="check mb"><input type="checkbox" name="cashless_enabled" value="1" <?= (int)$ev['cashless_enabled'] ? 'checked' : '' ?>> <span>Cashless actif : le QR du billet sert à payer aux bars</span></label>
      <div class="grid2"><div class="field"><label>Recharge minimum</label><div class="input-suffix"><input name="cashless_min_topup" inputmode="decimal" value="<?= e(money_input((int)$ev['cashless_min_topup'])) ?>"><span>€</span></div></div>
        <div class="field"><label>Remboursement du solde jusqu'au</label><input type="date" name="cashless_refund_until" value="<?= e((string)$ev['cashless_refund_until']) ?>"><small>Vide = pas de remboursement.</small></div></div>
      <button class="btn btn-block">Enregistrer</button></form>
    <div class="card"><h2>Bars</h2>
      <?php foreach ($bars as $b): ?><div class="list-item"><span class="grow"><b><?= e($b['name']) ?></b><?= $b['pin'] ? ' <small class="muted">· code</small>' : '' ?><br><small class="muted"><?= e(money((int)$b['sales'])) ?> de ventes</small></span>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="bar_id" value="<?= (int)$b['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="bar_toggle"><?= $b['active'] ? 'Fermer' : 'Ouvrir' ?></button></form></div><?php endforeach; ?>
      <form method="post" class="row mt"><?= csrf_field() ?><input type="hidden" name="action" value="bar"><input name="name" placeholder="Bar principal" required><input name="pin" placeholder="Code (facultatif)" inputmode="numeric" style="max-width:130px"><button class="btn btn-sm">Ajouter</button></form>
      <p class="muted mt mb0" style="font-size:13px">Les bénévoles et contrôleurs ouvrent la caisse avec leur compte, choisissent leur bar (et son code s'il y en a un).</p></div>
    <?php if ($sold): ?><div class="card"><h2>Meilleures ventes</h2><?php foreach (array_slice($sold, 0, 8, true) as $n => $s): ?><div class="list-item"><span class="grow"><?= e($n) ?></span><small class="muted"><?= (int)$s['qty'] ?> · <?= e(money((int)$s['sum'])) ?></small></div><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
