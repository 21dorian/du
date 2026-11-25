<?php /** @var array $ops, $bars, $st, $own, $ev; string $base */
$totDue = array_sum(array_map(fn($s) => $s['left'], $st)); $totCom = array_sum(array_map(fn($s) => $s['commission'], $st)); ?>
<div class="pk">
  <div><span>Exploitants</span><b><?= count(array_filter($ops, fn($o) => (int)$o['active'])) ?></b></div>
  <div><span>CA des exploitants</span><b><?= e(money(array_sum(array_map(fn($s) => $s['ttc'], $st)))) ?></b></div>
  <div class="good"><span>Commissions</span><b><?= e(money($totCom)) ?></b><small>revenu de l'organisateur</small></div>
  <div class="<?= $totDue > 0 ? 'warn' : '' ?>"><span>Reste à reverser</span><b><?= e(money($totDue)) ?></b></div>
  <div><span>Bars de l'organisateur</span><b><?= e(money($own['ttc'])) ?></b><small>stands sans exploitant</small></div>
</div>
<div class="card table-card"><div class="card-head"><h2>Relevés de l'événement</h2><a class="btn btn-sm btn-light" href="<?= Pay::url('/pro/exploitants') ?>">Fiches exploitants</a></div><div class="table-wrap"><table class="table">
  <thead><tr><th>Exploitant</th><th class="num">Ventes</th><th class="num">CA TTC</th><th class="num">Encaissé par toi</th><th class="num">Commission</th><th class="num">Dû</th><th class="num">Versé</th><th class="num">Reste</th><th></th></tr></thead><tbody>
  <?php foreach ($ops as $o): $s = $st[(int)$o['id']]; if (!$s['bars'] && !(int)$o['active']) continue; ?><tr>
    <td><b><?= e($o['name']) ?></b><br><small class="muted"><?= $s['bars'] ? e(implode(', ', array_column($s['bars'], 'name'))) : 'aucun stand sur cet événement' ?></small></td>
    <td class="num"><?= (int)$s['n'] ?></td><td class="num"><b><?= e(money($s['ttc'])) ?></b></td><td class="num"><?= e(money($s['collected_by_org'])) ?></td><td class="num"><?= e(money($s['commission'])) ?></td>
    <td class="num"><?= e(money($s['due'])) ?></td><td class="num"><?= e(money($s['paid'])) ?></td><td class="num"><b class="<?= $s['left'] < 0 ? 'text-red' : '' ?>"><?= e(money($s['left'])) ?></b></td>
    <td class="num"><a class="btn btn-sm btn-light" href="<?= url($base . '/exploitants/' . $o['id']) ?>">Relevé</a></td></tr><?php endforeach; ?>
  <?php if (!$ops): ?><tr><td colspan="9" class="pempty">Aucun exploitant. Crée les fiches (food trucks, associations, brasseurs…) dans <a href="<?= Pay::url('/pro/exploitants') ?>">Fiches exploitants</a>.</td></tr><?php endif; ?>
</tbody></table></div></div>
<form method="post" class="card mt"><?= csrf_field() ?><input type="hidden" name="action" value="assign"><h2>Qui tient chaque point de vente ?</h2>
  <p class="pnote">Les ventes d'un point de vente rattaché à un exploitant vont dans son relevé. Sans exploitant, le stand est tenu par l'organisateur.</p>
  <div class="pactions"><?php foreach ($bars as $b): ?><div class="field"><label><?= e($b['name']) ?></label><select name="bar_op[<?= (int)$b['id'] ?>]"><option value="">Organisateur</option><?php foreach ($ops as $o): if (!(int)$o['active']) continue; ?><option value="<?= (int)$o['id'] ?>" <?= (int)$b['operator_id'] === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div><?php endforeach; ?></div>
  <button class="btn btn-sm mt">Enregistrer</button></form>
