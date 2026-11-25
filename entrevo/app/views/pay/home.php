<?php /** @var array $events, $m */ ?>
<div class="pk">
  <div><span>Événements</span><b><?= count($events) ?></b></div>
  <div><span>Ventes cumulées</span><b><?= e(money(array_sum(array_map(fn($e) => (int)$e['ttc'], $events)))) ?></b></div>
  <div><span>Porte-monnaie</span><b><?= array_sum(array_map(fn($e) => (int)$e['wallets'], $events)) ?></b></div>
  <div><span>Terminaux en ligne</span><b><?= array_sum(array_map(fn($e) => (int)$e['online'], $events)) ?></b></div>
</div>
<div class="card table-card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Événement</th><th>Date</th><th>Cashless</th><th class="num">Ventes</th><th class="num">Porte-monnaie</th><th class="num">En ligne</th><th></th></tr></thead><tbody>
  <?php foreach ($events as $e): ?><tr>
    <td><b><?= e($e['name']) ?></b></td><td><?= e(fr_date($e['starts_at'], false)) ?></td>
    <td><?= (int)$e['cashless_enabled'] ? '<span class="ptag ok">actif</span>' : '<span class="ptag">désactivé</span>' ?></td>
    <td class="num"><b><?= e(money((int)$e['ttc'])) ?></b></td><td class="num"><?= (int)$e['wallets'] ?></td><td class="num"><?= (int)$e['online'] ? '<span class="ptag ok">' . (int)$e['online'] . '</span>' : '—' ?></td>
    <td class="num"><a class="btn btn-sm" href="<?= Pay::url('/pro/e/' . $e['id']) ?>">Piloter</a></td></tr><?php endforeach; ?>
  <?php if (!$events): ?><tr><td colspan="7" class="pempty">Aucun événement. Crée-le dans la billetterie, il apparaîtra ici.</td></tr><?php endif; ?>
</tbody></table></div></div>
