<?php $qs = array_filter(['who' => $who ?: null, 'type' => $type ?: null]); ?>
<p class="muted">Qui a fait quoi, et quand. Pratique pour savoir qui a remboursé, renommé ou annulé quelque chose 🕵️</p>
<form class="filters card" method="get">
  <div class="filters-main">
    <select name="who" aria-label="Personne"><option value="">Toute l'équipe</option><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $who === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select>
    <select name="type" aria-label="Type d'action"><option value="">Toutes les actions</option><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm">Filtrer</button>
    <?php if ($qs): ?><a class="btn btn-sm btn-light" href="<?= url('/pro/journal') ?>">Tout afficher</a><?php endif; ?>
    <span class="grow"></span><span class="muted"><?= number_format($total, 0, ',', ' ') ?> action<?= $total > 1 ? 's' : '' ?></span>
  </div>
</form>
<div class="card table-card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Quand</th><th>Qui</th><th>Action</th><th>Événement / commande</th><th>Détails</th></tr></thead><tbody>
  <?php foreach ($rows as $a): ?><tr><td class="nowrap"><?= e(short_date($a['created_at'])) ?><br><small class="muted"><?= e(ago($a['created_at'])) ?></small></td><td><?= e($a['user_name'] ?: 'Système') ?></td><td class="nowrap"><?= e(Audit::label($a['action'])) ?></td>
    <td><?= $a['event_id'] && $a['ev_name'] ? '<a href="' . url('/pro/evenements/' . $a['event_id']) . '">' . e($a['ev_name']) . '</a>' : '' ?><?= $a['order_id'] && $a['ref'] ? ' · <a class="mono" href="' . url('/pro/commandes/' . $a['order_id']) . '">' . e($a['ref']) . '</a>' : '' ?></td><td><small><?= e($a['details']) ?></small></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="empty">Rien pour l'instant : chaque action de ton équipe apparaîtra ici.</td></tr><?php endif; ?>
  </tbody></table></div>
  <?php if ($pages > 1): ?><nav class="pager" aria-label="Pages">
    <?php if ($page > 1): ?><a class="btn btn-sm btn-light" href="<?= url('/pro/journal', $qs + ['page' => $page - 1]) ?>">← Plus récentes</a><?php endif; ?>
    <span class="muted">Page <?= $page ?> / <?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-sm btn-light" href="<?= url('/pro/journal', $qs + ['page' => $page + 1]) ?>">Plus anciennes →</a><?php endif; ?>
  </nav><?php endif; ?>
</div>
