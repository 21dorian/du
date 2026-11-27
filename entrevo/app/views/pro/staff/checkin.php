<?php /** @var array $rows, $days */ $here = count(array_filter($rows, fn($r) => $r['checked_in_at'] && !$r['checked_out_at'])); ?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Attendus</span><b><?= count($rows) ?></b></div>
  <div class="kpi-mini"><span>Sur place</span><b><?= $here ?></b></div>
  <div class="kpi-mini"><span>Pas encore arrivés</span><b><?= count(array_filter($rows, fn($r) => !$r['checked_in_at'])) ?></b></div>
</div>
<?php if (count($days) > 1): ?><nav class="pill-tabs"><?php foreach ($days as $d): ?><a class="<?= $d === $day ? 'on' : '' ?>" href="?jour=<?= e($d) ?>"><?= e(date('d/m', strtotime($d))) ?></a><?php endforeach; ?></nav><?php endif; ?>
<div class="card">
  <form method="post" class="row badge-scan u-gap8"><?= csrf_field() ?>
    <input name="badge" placeholder="Scanne ou tape le code du badge bénévole" autofocus autocomplete="off" aria-label="Code du badge">
    <button class="btn">Pointer</button></form>
  <small class="muted">La douchette fonctionne ici. 1er passage = arrivée, 2e = départ. Ou clique sur les boutons ci-dessous.</small>
</div>
<div class="card table-card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Créneau</th><th>Bénévole</th><th>Arrivée</th><th>Départ</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr class="<?= $r['checked_in_at'] && !$r['checked_out_at'] ? 'selected' : '' ?>">
      <td class="nowrap"><b><?= e(date('H:i', strtotime($r['starts_at']))) ?>–<?= e(date('H:i', strtotime($r['ends_at']))) ?></b><br><small><?= e($r['title']) ?><?= $r['location'] ? ' · ' . e($r['location']) : '' ?></small></td>
      <td><b><?= e($r['first_name'] . ' ' . $r['last_name']) ?></b><?= $r['status'] !== 'confirmed' ? ' <span class="badge grey">non confirmé</span>' : '' ?><?= $r['phone'] ? '<br><small><a href="tel:' . e($r['phone']) . '">' . e($r['phone']) . '</a></small>' : '' ?></td>
      <td><?= $r['checked_in_at'] ? '' . e(date('H:i', strtotime($r['checked_in_at']))) : '<span class="muted">—</span>' ?></td>
      <td><?= $r['checked_out_at'] ? '' . e(date('H:i', strtotime($r['checked_out_at']))) : '<span class="muted">—</span>' ?></td>
      <td class="right nowrap"><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="assignment_id" value="<?= (int)$r['id'] ?>">
        <?php if (!$r['checked_out_at']): ?><button class="btn btn-sm <?= $r['checked_in_at'] ? 'btn-light' : '' ?>"><?= $r['checked_in_at'] ? 'Départ' : 'Arrivée' ?></button><?php endif; ?>
        <?php if ($r['checked_in_at']): ?><button class="btn btn-sm btn-light" name="undo" value="1" data-confirm="Annuler le pointage ?"></button><?php endif; ?></form></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="empty">Aucun bénévole prévu ce jour-là.</td></tr><?php endif; ?>
</tbody></table></div></div>
