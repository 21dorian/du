<?php /** @var array $p */ ?>
<article class="card page-card">
  <h1><?= e($p['title']) ?></h1>
  <div class="prose"><?= trim((string)$p['body']) !== '' ? rich_text((string)$p['body']) : '<p class="muted">Non renseigné.</p>' ?></div>
  <?php if (!empty($p['updated_at'])): ?><p class="muted mb0" style="font-size:13px">Mis à jour le <?= e(date('d/m/Y', strtotime($p['updated_at']))) ?></p><?php endif; ?>
</article>
