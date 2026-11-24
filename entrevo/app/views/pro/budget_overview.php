<p class="muted">Le budget de chaque événement : dépenses prévues et réelles, recettes, billetterie reliée automatiquement et seuil de rentabilité.</p>
<div class="card table-card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Événement</th><th class="num">Dépenses</th><th class="num">Recettes</th><th class="num">Billetterie</th><th class="num">Résultat</th><th class="num">Seuil</th></tr></thead><tbody>
  <?php foreach ($rows as ['ev' => $ev, 's' => $s]): ?>
    <tr data-href="<?= url('/pro/evenements/' . $ev['id'] . '/budget') ?>"><td><a href="<?= url('/pro/evenements/' . $ev['id'] . '/budget') ?>"><b><?= e($ev['name']) ?></b></a><br><small class="muted"><?= e(fr_date($ev['starts_at'], false)) ?></small></td>
      <td class="num"><?= e(money($s['exp_a'])) ?></td><td class="num"><?= e(money($s['inc_a'])) ?></td><td class="num"><?= e(money($s['ticketing']['net'])) ?></td>
      <td class="num"><b class="<?= $s['result'] >= 0 ? 'pos' : 'neg' ?>"><?= $s['result'] >= 0 ? '+' : '' ?><?= e(money($s['result'])) ?></b></td>
      <td class="num"><?= $s['break_even'] !== null && $s['exp_a'] ? $s['ticketing']['tickets'] . ' / ' . $s['break_even'] . ($s['ticketing']['tickets'] >= $s['break_even'] ? ' ✅' : '') : '<span class="muted">—</span>' ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="empty">Pas encore de budget. Ouvre un événement › onglet « Budget ».</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php if ($events): ?><div class="soon-row"><?php foreach (array_slice($events, 0, 6) as $e2): ?><a class="btn btn-sm btn-light" href="<?= url('/pro/evenements/' . $e2['id'] . '/budget') ?>">📒 <?= e($e2['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
