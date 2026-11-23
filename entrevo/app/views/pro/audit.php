<div class="card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Quand</th><th>Qui</th><th>Action</th><th>Événement / commande</th><th>Détails</th></tr></thead><tbody>
  <?php foreach ($rows as $a): ?><tr><td class="nowrap"><?= e(short_date($a['created_at'])) ?></td><td><?= e($a['user_name'] ?: '—') ?></td><td class="nowrap"><?= e(Audit::label($a['action'])) ?></td>
    <td><?= $a['event_id'] ? '<a href="' . url('/pro/evenements/' . $a['event_id']) . '">' . e($a['ev_name']) . '</a>' : '' ?><?= $a['order_id'] ? ' · <a href="' . url('/pro/commandes/' . $a['order_id']) . '">' . e($a['ref']) . '</a>' : '' ?></td><td><small><?= e($a['details']) ?></small></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="empty">Rien pour l'instant : chaque action de ton équipe apparaîtra ici.</td></tr><?php endif; ?>
  </tbody></table></div></div>
