<?php /** @var array $ev, $bars, $products, $k, $sold, $hours, $refunds, $devices, $pstats; int $focus */ $pos = url('/caisse/'); ?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Rechargé</span><b><?= e(money((int)$k['loaded'])) ?></b><small class="muted"><?= e(money((int)$k['online'])) ?> en ligne · <?= e(money((int)$k['onsite'])) ?> sur place</small></div>
  <div class="kpi-mini"><span>Dépensé aux bars</span><b><?= e(money((int)$k['spent'])) ?></b><small class="muted"><?= (int)$k['sales'] ?> ventes · panier <?= e(money((int)$k['basket'])) ?></small></div>
  <div class="kpi-mini"><span>Soldes restants</span><b><?= e(money((int)$k['left'])) ?></b><small class="muted"><?= (int)$k['wallets'] ?> porte-monnaie · <?= e(money((int)$k['refunded'])) ?> remboursés</small></div>
  <div class="kpi-mini"><span>Ventes aux bars</span><b><?= e(money((int)$pstats['total'])) ?></b><small class="muted"><?php foreach (Pos::METHODS as $m => $l): ?><?= e($l) ?> <?= e(money((int)($pstats['methods'][$m]['total'] ?? 0))) ?> · <?php endforeach; ?></small></div>
</div>
<?php if (!(int)$ev['cashless_enabled']): ?><div class="notice info mb">Le cashless est désactivé : active-le ci-dessous, ajoute tes bars et ta carte, puis ouvre la caisse sur les tablettes des bars.</div><?php endif; ?>
<section class="card pos-link"><div><h2>Bars & caisses</h2><p class="muted mb0">Catalogue avec photos, équipe, appareils, happy hours, consignes, crédits offerts, clôtures de caisse et stocks se gèrent dans l'espace dédié.</p></div><a class="btn" href="<?= url('/pro/evenements/' . $ev['id'] . '/bars') ?>">Ouvrir Bars & caisses</a></section>

<div class="split-3">
  <div>
    <div class="card"><h2>Carte des bars</h2><p class="muted mb0">Produits, photos, variantes, prix par bar et consignes se gèrent dans <a href="<?= url('/pro/evenements/' . $ev['id'] . '/bars/catalogue') ?>">Bars & caisses › Catalogue</a>.</p></div>
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
      <p class="muted mt mb0 u-fs13">Les points de vente et les appareils se gèrent dans Bars & caisses › Points de vente.</p></div>
    <?php if ($sold): ?><div class="card"><h2>Meilleures ventes</h2><?php foreach (array_slice($sold, 0, 8, true) as $n => $s): ?><div class="list-item"><span class="grow"><?= e($n) ?></span><small class="muted"><?= (int)$s['qty'] ?> · <?= e(money((int)$s['sum'])) ?></small></div><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
