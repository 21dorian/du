<p class="muted">Les modules « Bientôt » affichés aux organisateurs, classés par nombre de votes (organisations distinctes). C'est ta feuille de route : commence par le haut 😉</p>
<div class="split-3">
  <div class="card table-card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Module</th><th>Catégorie</th><th>Statut</th><th class="num">Votes</th></tr></thead><tbody>
    <?php $mx = max(1, max($counts ?: [1])); foreach ($all as $k => $f): $n = (int)($counts[$k] ?? 0); ?>
      <tr><td><?= $f['emoji'] ?> <b><?= e($f['name']) ?></b><br><small class="muted"><?= e($f['pitch']) ?></small></td>
        <td class="nowrap"><?= e(Features::CATEGORIES[$f['cat']][1]) ?></td>
        <td><?= $f['status'] === 'soon' ? '<span class="soon-pill">Bientôt</span>' : '<span class="on-pill">● Actif</span>' ?></td>
        <td class="num"><?php if ($f['status'] === 'soon'): ?><span class="vote-bar"><i style="width:<?= round($n / $mx * 100) ?>%"></i></span> <b><?= $n ?></b><?php else: ?>—<?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
  <div class="card"><h2>💬 Ce qu'ils attendent</h2>
    <?php foreach ($comments as $c): $f = Features::get($c['feature']); ?>
      <div class="list-item" style="display:block"><b><?= e($f['name'] ?? $c['feature']) ?></b> · <small class="muted"><?= e($c['org_name']) ?>, <?= e(ago($c['created_at'])) ?></small><br><?= e($c['comment']) ?></div>
    <?php endforeach; ?>
    <?php if (!$comments): ?><p class="muted">Pas encore de commentaire.</p><?php endif; ?>
  </div>
</div>
