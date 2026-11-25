<?php /** @var array $op, $S, $ev; string $base */ ?>
<p><a href="<?= url($base . '/exploitants') ?>">← Exploitants & reversements</a></p>
<div class="pk">
  <div class="hero"><span>CA TTC de <?= e($op['name']) ?></span><b><?= e(money($S['ttc'])) ?></b><small><?= (int)$S['n'] ?> ventes · HT <?= e(money($S['ht'])) ?></small></div>
  <div><span>Commission</span><b><?= e(money($S['commission'])) ?></b><small><?= e(rtrim(rtrim(number_format((float)$op['commission_pct'], 2, ',', ''), '0'), ',')) ?> %<?= (int)$op['fixed_fee'] ? ' + ' . e(money((int)$op['fixed_fee'])) : '' ?></small></div>
  <div><span>Dû à l'exploitant</span><b><?= e(money($S['due'])) ?></b></div>
  <div class="<?= $S['left'] ? 'warn' : 'good' ?>"><span><?= $S['left'] >= 0 ? 'Reste à verser' : 'L\'exploitant doit' ?></span><b><?= e(money(abs($S['left']))) ?></b></div>
</div>
<div class="pgrid">
  <div class="card"><h2>Détail</h2>
    <?php foreach ($S['per_bar'] as $b): ?><div class="list-item"><span class="grow"><?= e($b['name']) ?> <small class="muted">· <?= (int)$b['n'] ?> ventes</small></span><b><?= e(money($b['ttc'])) ?></b></div><?php endforeach; ?>
    <div class="list-item"><span class="grow">Cashless (encaissé par l'organisateur)</span><b><?= e(money($S['cashless'])) ?></b></div>
    <div class="list-item"><span class="grow">Carte <?= (int)$op['keeps_card'] ? '(son propre terminal)' : '(terminal de l\'organisateur)' ?></span><b><?= e(money($S['card'])) ?></b></div>
    <div class="list-item"><span class="grow">Espèces <?= (int)$op['keeps_cash'] ? '(gardées par l\'exploitant)' : '(remises à l\'organisateur)' ?></span><b><?= e(money($S['cash'])) ?></b></div>
    <div class="list-item"><span class="grow"><b>Encaissé par l'organisateur</b></span><b><?= e(money($S['collected_by_org'])) ?></b></div>
    <div class="list-item"><span class="grow">− Commission</span><b>− <?= e(money($S['commission'])) ?></b></div>
    <div class="list-item"><span class="grow"><b>Montant dû</b></span><b><?= e(money($S['due'])) ?></b></div>
    <?php foreach ($S['payouts'] as $p): ?><div class="list-item"><span class="grow">Virement du <?= e(date('d/m/Y', strtotime($p['paid_at']))) ?><?= $p['note'] ? ' · ' . e($p['note']) : '' ?></span><b>− <?= e(money((int)$p['amount'])) ?></b></div><?php endforeach; ?>
    <div class="row mt"><a class="btn btn-sm btn-light" target="_blank" href="<?= url($base . '/exploitants/' . $op['id'], ['pdf' => 1]) ?>">Relevé PDF</a></div></div>
  <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="payout"><h2>Enregistrer un reversement</h2>
    <p class="pnote" style="margin:0"><?= $op['iban'] ? 'IBAN : <span class="mono">' . e($op['iban']) . '</span>' : 'Pas d\'IBAN sur la fiche.' ?></p>
    <div class="input-suffix"><input name="amount" inputmode="decimal" value="<?= e(money_input(max(0, $S['left']))) ?>"><span>€</span></div>
    <input type="date" name="paid_at" value="<?= date('Y-m-d') ?>"><input name="note" placeholder="Référence du virement"><button class="btn btn-sm">Enregistrer</button></form>
</div>
