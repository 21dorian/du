<div class="vol-wrap">
  <div class="vol-hero"><span class="eyebrow">🙋 Espace bénévole · <?= e($org['name']) ?></span><h1>Salut <?= e($m['first_name']) ?> !</h1>
    <p class="muted"><?= count(array_filter($mine, fn($s) => $s['status'] === 'confirmed')) ?> créneau(x) confirmé(s) · <?= e(Staff::hm($minutes)) ?> prévues. Garde ce lien précieusement, il est personnel.</p>
    <a class="btn" href="<?= url('/benevole/' . $m['token'] . '/badge') ?>">🪪 Mon badge (à montrer en arrivant)</a></div>
  <div class="card"><h2>🗓️ Mes créneaux</h2>
    <?php foreach ($mine as $s): ?>
      <div class="vol-mine <?= e($s['status']) ?>">
        <div class="grow"><b><?= e($s['title']) ?></b> <?= Staff::badge($s['status']) ?><br><small><?= e($s['ev_name']) ?> · <?= e(fr_date($s['starts_at'])) ?> → <?= e(date('H:i', strtotime($s['ends_at']))) ?><?= $s['location'] ? ' · 📍 ' . e($s['location']) : '' ?></small>
          <?php if ($s['description']): ?><small class="muted" style="display:block;white-space:pre-line"><?= e($s['description']) ?></small><?php endif; ?></div>
        <?php if ($s['status'] === 'proposed' || $s['status'] === 'requested' || ($s['status'] === 'confirmed' && !$s['checked_in_at'])): ?>
        <form method="post" class="row" style="gap:6px"><?= csrf_field() ?><input type="hidden" name="assignment_id" value="<?= (int)$s['id'] ?>">
          <?php if ($s['status'] === 'proposed'): ?><button class="btn btn-sm btn-success" name="action" value="confirm">Je confirme</button><?php endif; ?>
          <?php if ($s['status'] !== 'declined'): ?><button class="btn btn-sm btn-light" name="action" value="decline" data-confirm="Tu ne pourras pas venir sur ce créneau ?">Je ne peux pas</button><?php endif; ?></form>
        <?php elseif ($s['checked_in_at']): ?><span class="badge green">✅ Pointé <?= e(date('H:i', strtotime($s['checked_in_at']))) ?></span><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$mine): ?><p class="muted">Pas encore de créneau. Inscris-toi ci-dessous 👇</p><?php endif; ?>
  </div>
  <?php if ($open): ?>
  <div class="card"><h2>🙋 Créneaux où on a besoin de monde</h2>
    <?php foreach ($open as $s): ?>
      <div class="vol-mine"><div class="grow"><b><?= e($s['title']) ?></b><br><small><?= e($s['ev_name']) ?> · <?= e(fr_date($s['starts_at'])) ?> → <?= e(date('H:i', strtotime($s['ends_at']))) ?> · encore <?= (int)$s['needed'] - (int)$s['taken'] ?> place(s)</small></div>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="shift_id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm" name="action" value="signup">Je m'inscris</button></form></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
