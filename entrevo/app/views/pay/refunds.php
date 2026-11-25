<?php /** @var array $requests, $topups, $refunded, $k, $sum, $ev; int $fee; string $base */ ?>
<div class="pk">
  <div><span>Recharges en ligne</span><b><?= e(money((int)$sum['online'])) ?></b></div>
  <div><span>Recharges espèces</span><b><?= e(money((int)$sum['cash'])) ?></b></div>
  <div><span>Recharges carte</span><b><?= e(money((int)$sum['card'])) ?></b></div>
  <div><span>Crédits offerts</span><b><?= e(money((int)$sum['gift'])) ?></b></div>
  <div><span>Remboursé</span><b><?= e(money((int)$k['refunded'])) ?></b></div>
  <div class="<?= $requests ? 'warn' : '' ?>"><span>À rembourser</span><b><?= e(money(array_sum(array_map(fn($w) => (int)$w['balance'], $requests)))) ?></b><small><?= count($requests) ?> demande(s)</small></div>
</div>
<form method="post" class="card table-card"><?= csrf_field() ?><input type="hidden" name="action" value="done_all">
  <div class="card-head"><h2>Demandes de remboursement du solde</h2><div class="row"><a class="btn btn-sm btn-light" href="<?= url($base . '/remboursements', ['export' => 'sepa']) ?>">Export pour virements (CSV)</a><button class="btn btn-sm" <?= $requests ? '' : 'disabled' ?>>Marquer la sélection comme virée</button></div></div>
  <p class="pnote" style="padding:0 20px">Le festivalier demande le remboursement depuis le portail après l'événement, avant la date limite fixée dans les réglages.<?= $fee ? ' Frais retenus : ' . e(money($fee)) . ' par remboursement.' : '' ?></p>
  <div class="table-wrap"><table class="table"><thead><tr><th style="width:30px"><input type="checkbox" onclick="document.querySelectorAll('[name=&quot;ids[]&quot;]').forEach(c => c.checked = this.checked)"></th><th>Porte-monnaie</th><th>Titulaire</th><th>IBAN</th><th class="num">Solde</th><th class="num">À virer</th></tr></thead><tbody>
    <?php foreach ($requests as $w): ?><tr><td><input type="checkbox" name="ids[]" value="<?= (int)$w['id'] ?>"></td><td><a href="<?= url($base . '/porte-monnaie/' . $w['id']) ?>" class="mono"><?= e($w['code']) ?></a></td><td><?= e(Pay::holder($w) ?: (string)$w['email']) ?></td><td class="mono"><?= e((string)$w['refund_iban']) ?></td><td class="num"><?= e(money((int)$w['balance'])) ?></td><td class="num"><b><?= e(money(max(0, (int)$w['balance'] - $fee))) ?></b></td></tr><?php endforeach; ?>
    <?php if (!$requests): ?><tr><td colspan="6" class="pempty">Aucune demande en attente.</td></tr><?php endif; ?></tbody></table></div></form>
<div class="pgrid-2 mt">
  <div class="card table-card"><div class="card-head"><h2>Dernières recharges</h2></div><div class="table-wrap"><table class="table table-sm"><tbody>
    <?php foreach ($topups as $t): ?><tr><td><?= e(date('d/m H:i', strtotime($t['created_at']))) ?></td><td class="mono"><?= e($t['code']) ?></td><td><?= e(Pay::TX[$t['kind']] ?? $t['kind']) ?></td><td class="num"><b>+<?= e(money((int)$t['amount'])) ?></b></td></tr><?php endforeach; ?>
    <?php if (!$topups): ?><tr><td class="pempty">Aucune recharge.</td></tr><?php endif; ?></tbody></table></div></div>
  <div class="card table-card"><div class="card-head"><h2>Remboursements effectués</h2></div><div class="table-wrap"><table class="table table-sm"><tbody>
    <?php foreach ($refunded as $t): ?><tr><td><?= e(date('d/m H:i', strtotime($t['created_at']))) ?></td><td class="mono"><?= e($t['code']) ?></td><td><?= e((string)$t['email']) ?></td><td class="num"><b><?= e(money((int)$t['amount'])) ?></b></td></tr><?php endforeach; ?>
    <?php if (!$refunded): ?><tr><td class="pempty">Aucun remboursement.</td></tr><?php endif; ?></tbody></table></div></div>
</div>
