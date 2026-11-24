<?php
/** @var array $tasks, $events, $users, $f, $edit, $ev */
$base = $ev ? '/pro/evenements/' . $ev['id'] . '/taches' : '/pro/taches';
$today = date('Y-m-d');
$late = count(array_filter($tasks, fn($t) => $t['status'] !== 'done' && $t['due_date'] && $t['due_date'] < $today));
$doneN = count(array_filter($tasks, fn($t) => $t['status'] === 'done'));
$card = function (array $t) use ($today, $base, $ev) {
    $lateT = $t['status'] !== 'done' && $t['due_date'] && $t['due_date'] < $today;
    $soon = $t['status'] !== 'done' && $t['due_date'] && !$lateT && $t['due_date'] <= date('Y-m-d', strtotime('+7 days'));
    ob_start(); ?>
    <article class="task<?= $lateT ? ' late' : '' ?><?= $t['priority'] ? ' prio' : '' ?>" draggable="true" data-task="<?= (int)$t['id'] ?>">
      <div class="task-top"><b><?= $t['priority'] ? '🔥 ' : '' ?><?= e($t['title']) ?></b><a class="task-edit" href="<?= url($base, ['modifier' => $t['id']]) ?>#tache" aria-label="Modifier">✏️</a></div>
      <?php if ($t['description']): ?><p><?= e(mb_strimwidth($t['description'], 0, 140, '…')) ?></p><?php endif; ?>
      <div class="task-meta">
        <?php if ($t['due_date']): ?><span class="task-due<?= $lateT ? ' late' : ($soon ? ' soon' : '') ?>">📅 <?= e(date('d/m', strtotime($t['due_date']))) ?><?= $lateT ? ' · en retard' : '' ?></span><?php endif; ?>
        <?php if (!$ev && $t['ev_name']): ?><span class="tag"><?= e(mb_strimwidth($t['ev_name'], 0, 26, '…')) ?></span><?php endif; ?>
        <?php if ($t['assignee']): ?><span class="avatar-xs" title="<?= e($t['assignee']) ?>" style="--h:<?= crc32($t['assignee']) % 360 ?>"><?= e(mb_strtoupper(mb_substr($t['assignee'], 0, 1))) ?></span><?php endif; ?>
      </div>
      <form method="post" class="task-move"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
        <?php foreach (TASK_STATUS as $k => $l): if ($k === $t['status']) continue; ?><button class="link-btn" name="status" value="<?= $k ?>"><?= $l ?></button><?php endforeach; ?></form>
    </article>
    <?php return ob_get_clean();
};
$edit = $edit ?: ['id' => 0, 'title' => '', 'description' => '', 'due_date' => '', 'assignee_id' => null, 'event_id' => $ev['id'] ?? ($f['ev'] ?: null), 'priority' => 0];
$qs = array_filter(['ev' => !$ev && $f['ev'] ? $f['ev'] : null, 'qui' => $f['who'] ?: null]);
?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Tâches</span><b><?= count($tasks) ?></b></div>
  <div class="kpi-mini"><span>Faites</span><b><?= $doneN ?><?= $tasks ? ' <small style="font-size:14px;color:var(--muted)">(' . round($doneN / count($tasks) * 100) . ' %)</small>' : '' ?></b></div>
  <div class="kpi-mini<?= $late ? ' warn' : '' ?>"><span>En retard</span><b><?= $late ?></b></div>
</div>
<form class="filters card" method="get">
  <div class="filters-main">
    <?php if (!$ev): ?><select name="ev" aria-label="Événement" onchange="this.form.submit()"><option value="">Tous les événements</option><?php foreach ($events as $e2): ?><option value="<?= (int)$e2['id'] ?>" <?= $f['ev'] === (int)$e2['id'] ? 'selected' : '' ?>><?= e($e2['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <select name="qui" aria-label="Personne" onchange="this.form.submit()"><option value="">Toute l'équipe</option><option value="moi" <?= $f['who'] === 'moi' ? 'selected' : '' ?>>Mes tâches</option></select>
    <span class="grow"></span>
    <div class="seg-mini" role="group" aria-label="Affichage"><a class="<?= $f['view'] === 'tableau' ? 'on' : '' ?>" href="<?= url($base, $qs) ?>">▦ Tableau</a><a class="<?= $f['view'] === 'liste' ? 'on' : '' ?>" href="<?= url($base, $qs + ['vue' => 'liste']) ?>">☰ Liste</a></div>
  </div>
</form>
<div class="split-3">
  <div>
  <?php if ($f['view'] === 'tableau'): ?>
    <div class="kanban" data-kanban data-url="<?= url($base) ?>" data-csrf="<?= e(csrf_token()) ?>">
      <?php foreach (TASK_STATUS as $k => $l): $col = array_filter($tasks, fn($t) => $t['status'] === $k); ?>
        <section class="kcol" data-status="<?= $k ?>"><h3><?= $l ?> <small><?= count($col) ?></small></h3>
          <div class="kcol-list"><?php foreach ($col as $t) echo $card($t); ?></div></section>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="card table-card"><div class="table-wrap"><table class="table">
      <thead><tr><th>Échéance</th><th>Tâche</th><?php if (!$ev): ?><th>Événement</th><?php endif; ?><th>Qui</th><th>Statut</th></tr></thead><tbody>
      <?php foreach ($tasks as $t): $lateT = $t['status'] !== 'done' && $t['due_date'] && $t['due_date'] < $today; ?>
        <tr data-href="<?= url($base, ['modifier' => $t['id'], 'vue' => 'liste']) ?>#tache"><td class="nowrap <?= $lateT ? 'neg' : '' ?>"><?= $t['due_date'] ? e(date('d/m/Y', strtotime($t['due_date']))) : '—' ?></td>
          <td><b style="<?= $t['status'] === 'done' ? 'text-decoration:line-through;opacity:.6' : '' ?>"><?= $t['priority'] ? '🔥 ' : '' ?><?= e($t['title']) ?></b></td><?php if (!$ev): ?><td><small><?= e((string)$t['ev_name']) ?></small></td><?php endif; ?>
          <td><small><?= e((string)$t['assignee']) ?></small></td><td><?= TASK_STATUS[$t['status']] ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$tasks): ?><tr><td colspan="5" class="empty">Aucune tâche. Lance un rétroplanning prêt à l'emploi à droite 👉</td></tr><?php endif; ?>
    </tbody></table></div></div>
  <?php endif; ?>
  </div>
  <div>
    <div class="card" id="tache">
      <h2><?= $edit['id'] ? '✏️ Modifier la tâche' : '➕ Nouvelle tâche' ?></h2>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="task_id" value="<?= (int)$edit['id'] ?>">
        <div class="field mb0"><label>Intitulé</label><input name="title" value="<?= e($edit['title']) ?>" required placeholder="Ex. : Envoyer le contrat à l'artiste"></div>
        <div class="field mb0"><label>Détails</label><textarea name="description" rows="2"><?= e((string)$edit['description']) ?></textarea></div>
        <div class="grid2"><div class="field mb0"><label>Échéance</label><input type="date" name="due_date" value="<?= e((string)$edit['due_date']) ?>"></div>
          <div class="field mb0"><label>Pour</label><select name="assignee_id"><option value="">Personne</option><?php foreach ($users as $u2): ?><option value="<?= (int)$u2['id'] ?>" <?= (int)$edit['assignee_id'] === (int)$u2['id'] ? 'selected' : '' ?>><?= e($u2['name']) ?></option><?php endforeach; ?></select></div></div>
        <?php if (!$ev): ?><div class="field mb0"><label>Événement</label><select name="event_id"><option value="">Aucun (général)</option><?php foreach ($events as $e2): ?><option value="<?= (int)$e2['id'] ?>" <?= (int)$edit['event_id'] === (int)$e2['id'] ? 'selected' : '' ?>><?= e($e2['name']) ?></option><?php endforeach; ?></select></div>
        <?php else: ?><input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>"><?php endif; ?>
        <label class="check"><input type="checkbox" name="priority" value="1" <?= $edit['priority'] ? 'checked' : '' ?>> 🔥 Prioritaire</label>
        <div class="row" style="gap:8px"><button class="btn btn-sm"><?= $edit['id'] ? 'Enregistrer' : 'Ajouter' ?></button>
          <?php if ($edit['id']): ?><a href="<?= url($base) ?>">Annuler</a><span class="grow"></span><button class="link-btn neg" name="action" value="delete" data-confirm="Supprimer cette tâche ?">Supprimer</button><?php endif; ?></div>
      </form>
    </div>
    <div class="card">
      <h2>🗓️ Rétroplanning tout prêt</h2>
      <p class="muted" style="font-size:14px">Des tâches types calées automatiquement sur la date de l'événement (J-60, J-30, J-7…).</p>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="template">
        <select name="template" aria-label="Modèle"><?php foreach (task_templates() as $k => [$l, $items]): ?><option value="<?= $k ?>"><?= e($l) ?> (<?= count($items) ?> tâches)</option><?php endforeach; ?></select>
        <?php if (!$ev): ?><select name="event_id" required aria-label="Événement"><option value="">Pour quel événement ?</option><?php foreach ($events as $e2): ?><option value="<?= (int)$e2['id'] ?>"><?= e($e2['name']) ?></option><?php endforeach; ?></select>
        <?php else: ?><input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>"><?php endif; ?>
        <button class="btn btn-sm btn-light">Ajouter le rétroplanning</button>
      </form>
    </div>
  </div>
</div>
