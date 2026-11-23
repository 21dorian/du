<div class="tabs"><a class="<?= $filter !== 'passes' ? 'on' : '' ?>" href="<?= url('/pro/evenements') ?>">À venir</a><a class="<?= $filter === 'passes' ? 'on' : '' ?>" href="<?= url('/pro/evenements', ['f' => 'passes']) ?>">Passés</a></div>
<div class="card">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Événement</th><th>Date</th><th>Statut</th><th class="num">Vendus</th><th class="num">Entrés</th><th class="num">Recettes</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $ev): ?>
      <tr>
        <td><div class="ev-head" style="margin:0"><div class="ev-thumb" style="width:64px;height:42px"><?php if ($ev['image']): ?><img src="<?= url($ev['image']) ?>" alt=""><?php endif; ?></div>
          <div><a href="<?= url('/pro/evenements/' . $ev['id']) ?>"><b><?= e($ev['name']) ?></b></a><br><small><?= e(trim($ev['venue'] . ' ' . $ev['city'])) ?></small></div></div></td>
        <td class="nowrap"><?= e(fr_date($ev['starts_at'])) ?></td>
        <td><?= badge($ev['status']) ?></td>
        <td class="num"><?= (int)$ev['sold'] ?><?= $ev['capacity'] ? '<small> / ' . (int)$ev['capacity'] . '</small>' : '' ?></td>
        <td class="num"><?= (int)$ev['checked'] ?></td>
        <td class="num"><?= e(money((int)$ev['revenue'])) ?></td>
        <td class="right nowrap"><a class="btn btn-sm btn-ghost" href="<?= url('/pro/evenements/' . $ev['id']) ?>">Gérer</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="7" class="empty">Aucun événement <?= $filter === 'passes' ? 'passé' : 'à venir' ?>. <a href="<?= url('/pro/evenements/nouveau') ?>">Créer un événement</a></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
