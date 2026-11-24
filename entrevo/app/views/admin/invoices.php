<?php /** @var array $list, $totals */ ?>
<div class="page-head-row">
  <div class="kpi-mini"><span><?= $period ? e(Invoices::monthLabel($period)) : 'Année ' . date('Y') ?> · HT</span><b><?= e(money((int)$totals['ht'])) ?></b></div>
  <div class="kpi-mini"><span>TVA collectée</span><b><?= e(money((int)$totals['vat'])) ?></b></div>
  <div class="kpi-mini"><span>TTC</span><b><?= e(money((int)$totals['ttc'])) ?></b></div>
  <?php if ($pending): ?><div class="kpi-mini warn"><span>À facturer (<?= e(Invoices::monthLabel($prev)) ?>)</span><b><?= $pending ?></b></div><?php endif; ?>
</div>
<div class="card">
  <div class="card-head"><h2>Générer les factures d'un mois</h2><small class="muted">Une par organisateur ayant des commissions, numérotation continue</small></div>
  <form method="post" class="row" style="gap:10px;align-items:flex-end"><?= csrf_field() ?><input type="hidden" name="action" value="generate">
    <div class="field mb0"><label>Mois</label><input type="month" name="period" value="<?= e($prev) ?>" max="<?= e($prev) ?>"></div>
    <label class="check"><input type="checkbox" name="send" value="1" checked> Envoyer par e-mail (PDF joint)</label>
    <button class="btn">Générer</button>
    <span class="muted" style="font-size:13px"><?= setting('invoice_auto') === '1' ? 'Génération automatique le 1er de chaque mois : activée.' : 'Astuce : active la génération automatique dans Réglages › Commissions.' ?></span>
  </form>
</div>
<form class="toolbar" method="get"><input type="month" name="mois" value="<?= e($period) ?>"><button class="btn btn-sm">Filtrer</button><?php if ($period): ?><a href="<?= url('/admin/factures') ?>">Tout voir</a><?php endif; ?></form>
<div class="card table-card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Numéro</th><th>Organisateur</th><th>Période</th><th class="num">Commandes</th><th class="num">HT</th><th class="num">TVA</th><th class="num">TTC</th><th>Envoyée</th><th></th></tr></thead><tbody>
  <?php foreach ($list as $i): ?><tr>
    <td class="mono"><a href="<?= url('/admin/factures/' . $i['id'] . '/pdf') ?>" target="_blank"><?= e($i['number']) ?></a></td>
    <td><a href="<?= url('/admin/organisateurs/' . $i['organizer_id']) ?>"><?= e($i['org_name']) ?></a></td>
    <td><?= e(Invoices::monthLabel($i['period'])) ?></td><td class="num"><?= (int)$i['orders_count'] ?></td>
    <td class="num"><?= e(money((int)$i['amount_ht'])) ?></td><td class="num"><?= e(money((int)$i['vat'])) ?></td><td class="num"><b><?= e(money((int)$i['amount_ttc'])) ?></b></td>
    <td><small><?= $i['sent_at'] ? e(short_date($i['sent_at'])) : '<span class="muted">non</span>' ?></small></td>
    <td class="right"><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="resend"></button></form></td></tr><?php endforeach; ?>
  <?php if (!$list): ?><tr><td colspan="9" class="empty">Pas encore de facture.</td></tr><?php endif; ?>
</tbody></table></div></div>
