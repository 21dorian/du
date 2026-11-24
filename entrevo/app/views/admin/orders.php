<?php /** @var array $list, $tot, $orgs, $providers; int $page, $per */
$qs = array_filter(['q' => input('q'), 'st' => input('st'), 'pv' => input('pv'), 'org' => input('org'), 'du' => input('du'), 'au' => input('au'), 'min' => input('min'), 'kind' => input('kind')], fn($v) => $v !== null && $v !== '');
$pages = max(1, (int)ceil((int)$tot['n'] / $per)); ?>
<form class="card filters-card" method="get">
  <div class="filters-grid">
    <input type="search" name="q" value="<?= e((string)input('q')) ?>" placeholder="Réf., e-mail, nom, téléphone, id paiement…" class="span2">
    <select name="st"><option value="">Tous statuts</option><?php foreach (['paid' => 'Payées', 'partial' => 'Remboursées en partie', 'refund_req' => 'Remboursement demandé', 'refunded' => 'Remboursées', 'pending' => 'En attente', 'expired' => 'Expirées', 'cancelled' => 'Annulées'] as $k => $l): ?><option value="<?= $k ?>" <?= input('st') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <select name="pv"><option value="">Tous paiements</option><?php foreach ($providers as $pv): ?><option value="<?= e($pv) ?>" <?= input('pv') === $pv ? 'selected' : '' ?>><?= e(provider_label($pv) ?: method_label($pv) ?: $pv) ?></option><?php endforeach; ?></select>
    <select name="org"><option value="">Tous organisateurs</option><?php foreach ($orgs as $g): ?><option value="<?= (int)$g['id'] ?>" <?= (int)input('org') === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option><?php endforeach; ?></select>
    <select name="kind"><option value="">Billets et boutique</option><option value="tickets" <?= input('kind') === 'tickets' ? 'selected' : '' ?>>Billets</option><option value="store" <?= input('kind') === 'store' ? 'selected' : '' ?>>Boutique, cartes, abonnements</option></select>
    <label class="inline-field">Du <input type="date" name="du" value="<?= e((string)input('du')) ?>"></label>
    <label class="inline-field">au <input type="date" name="au" value="<?= e((string)input('au')) ?>"></label>
    <div class="input-suffix"><input name="min" value="<?= e((string)input('min')) ?>" placeholder="Montant min." inputmode="decimal"><span>€</span></div>
  </div>
  <div class="row mt"><button class="btn btn-sm">Filtrer</button><?php if ($qs): ?><a class="btn btn-sm btn-ghost" href="<?= url('/admin/commandes') ?>">Tout effacer</a><?php endif; ?><a class="btn btn-sm btn-light" style="margin-left:auto" href="<?= url('/admin/commandes', $qs + ['csv' => 1]) ?>">Exporter (CSV)</a></div>
</form>
<div class="page-head-row">
  <div class="kpi-mini"><span>Commandes</span><b><?= number_format((int)$tot['n'], 0, ',', ' ') ?></b></div>
  <div class="kpi-mini"><span>Encaissé (payées)</span><b><?= e(money((int)$tot['total'])) ?></b></div>
  <div class="kpi-mini"><span>Commission</span><b><?= e(money((int)$tot['commission'])) ?></b></div>
  <div class="kpi-mini"><span>Remboursé</span><b><?= e(money((int)$tot['refunded'])) ?></b></div>
</div>
<div class="card table-card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Commande</th><th>Acheteur</th><th>Événement</th><th class="num">Total</th><th class="num">Commission</th><th>Paiement</th><th>Statut</th></tr></thead>
  <tbody>
  <?php foreach ($list as $o): ?>
    <tr data-href="<?= url('/admin/commandes/' . $o['id']) ?>"><td><a class="mono" href="<?= url('/admin/commandes/' . $o['id']) ?>"><b><?= e($o['ref']) ?></b></a><br><small class="muted"><?= e(short_date($o['created_at'])) ?></small></td>
    <td><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?><br><small class="muted"><?= e($o['email']) ?></small></td>
    <td><?= e((string)($o['ev_name'] ?: 'Boutique / cartes / abonnements')) ?><br><small><a href="<?= url('/admin/organisateurs/' . $o['organizer_id']) ?>"><?= e($o['org_name']) ?></a></small></td>
    <td class="num"><b><?= e(money((int)$o['total'])) ?></b><?= (int)$o['refunded_amount'] ? '<br><small class="muted">−' . e(money((int)$o['refunded_amount'])) . '</small>' : '' ?></td>
    <td class="num"><?= e(money((int)$o['commission'])) ?></td>
    <td><small><?= e(provider_label((string)($o['provider'] ?: $o['pay_method'])) ?: method_label((string)$o['method'])) ?></small></td>
    <td><?= badge($o['status']) ?><?= $o['refund_requested_at'] && $o['status'] === 'paid' ? '<br><span class="badge orange">Rembt demandé</span>' : '' ?><?= (int)$o['disputes'] ? '<br><span class="badge red">Litige</span>' : '' ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$list): ?><tr><td colspan="7" class="empty">Aucune commande avec ces critères.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php if ($pages > 1): ?><nav class="pager mt"><?php for ($i = max(1, $page - 4); $i <= min($pages, $page + 4); $i++): ?><a class="pager-n<?= $i === $page ? ' on' : '' ?>" href="<?= url('/admin/commandes', $qs + ['page' => $i]) ?>"><?= $i ?></a><?php endfor; ?><small class="muted"><?= $pages ?> pages</small></nav><?php endif; ?>
