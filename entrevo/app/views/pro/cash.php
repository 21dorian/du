<?php $labels = ['especes' => 'Espèces', 'cb' => 'Carte (TPE)', 'cheque' => 'Chèque', 'virement' => 'Virement', 'autre' => 'Autre']; ?>
<form class="toolbar" method="get"><label class="mb0">Du</label><input type="datetime-local" name="from" value="<?= e(dt_input($from)) ?>" class="u-wauto"><label class="mb0">au</label><input type="datetime-local" name="to" value="<?= e(dt_input($to)) ?>" class="u-wauto"><button class="btn btn-sm">Afficher</button>
  <span class="grow"></span><a class="btn btn-sm btn-ghost" href="<?= url('/pro/evenements/' . $ev['id'] . '/guichet') ?>">← Vendre au guichet</a><a class="btn btn-sm btn-ghost" href="?<?= e(http_build_query(['from' => dt_input($from), 'to' => dt_input($to), 'csv' => 1])) ?>">Export CSV</a></form>
<div class="kpis">
  <div class="kpi"><small class="muted">Total encaissé</small><b><?= e(money($totals['total'])) ?></b><div class="sub"><?= $totals['count'] ?> ventes · <?= $totals['tickets'] ?> billets</div></div>
  <?php foreach ($totals['by_method'] as $m => $v): ?><div class="kpi"><small class="muted"><?= e($labels[$m] ?? $m) ?></small><b><?= e(money($v)) ?></b></div><?php endforeach; ?>
</div>
<div class="split-3">
  <div class="card"><h2>Journal des ventes</h2><div class="table-wrap"><table class="table"><thead><tr><th>Heure</th><th>Commande</th><th>Vendeur</th><th class="num">Billets</th><th>Paiement</th><th class="num">Montant</th><th></th></tr></thead><tbody>
    <?php foreach ($journal as $o): ?><tr style="<?= $o['status'] === 'paid' ? '' : 'opacity:.45;text-decoration:line-through' ?>"><td class="nowrap"><?= e(short_date($o['created_at'])) ?></td><td><a href="<?= url('/pro/commandes/' . $o['id']) ?>"><?= e($o['ref']) ?></a></td><td><?= e($o['seller']) ?></td><td class="num"><?= (int)$o['n'] ?></td><td><?= e($labels[$o['pay_method']] ?? $o['pay_method']) ?></td><td class="num"><?= e(money((int)$o['total'])) ?></td>
      <td><a class="btn btn-sm btn-light" href="<?= url('/pro/commandes/' . $o['id'] . '/ticket-caisse') ?>" target="_blank"></a></td></tr><?php endforeach; ?>
    <?php if (!$journal): ?><tr><td colspan="7" class="empty">Aucune vente au guichet sur cette période.</td></tr><?php endif; ?></tbody></table></div></div>
  <div>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="close">
      <h2>Clôturer la caisse</h2>
      <?php foreach ($totals['by_seller'] as $s => $v): ?><div class="summary-line"><span><?= e($s) ?></span><b><?= e(money($v)) ?></b></div><?php endforeach; ?>
      <div class="field mt"><label>Espèces comptées dans la caisse</label><div class="input-suffix"><input name="counted" placeholder="<?= e(money_input((int)($totals['by_method']['especes'] ?? 0))) ?>"><span>€</span></div><small>On calcule l'écart avec les ventes en espèces.</small></div>
      <div class="field"><label>Note</label><input name="note"></div>
      <button class="btn btn-block" data-confirm="Clôturer la caisse pour cette période ?">Clôturer</button>
    </form>
    <div class="card"><h2>Clôtures</h2>
      <?php foreach ($closures as $c): $t = json_decode($c['totals'], true); $diff = $c['counted_cash'] !== null ? (int)$c['counted_cash'] - (int)($t['by_method']['especes'] ?? 0) : null; ?>
        <div class="list-item"><span class="grow"><b><?= e(money((int)$t['total'])) ?></b> · <?= e($c['user_name']) ?><br><small><?= e(short_date($c['period_start'])) ?> → <?= e(short_date($c['period_end'])) ?><?= $diff !== null ? ' · écart espèces ' . ($diff >= 0 ? '+' : '') . e(money($diff)) : '' ?></small></span></div>
      <?php endforeach; ?><?php if (!$closures): ?><p class="muted mb0">Aucune clôture.</p><?php endif; ?>
    </div>
  </div>
</div>
