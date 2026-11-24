<div class="psoon">
  <div class="psoon-card">
    <span class="soon-tag big">Bientôt disponible</span>
    <div class="psoon-emoji"><?= $f['emoji'] ?></div>
    <h1><?= e($f['name']) ?></h1>
    <p class="lead"><?= e($f['pitch']) ?></p>
    <?php if (!empty($f['points'])): ?><ul class="soon-points"><?php foreach ($f['points'] as $pt): ?><li><?= e($pt) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post" class="psoon-form"><?= csrf_field() ?>
      <label for="ps-mail">Préviens-moi quand c'est prêt</label>
      <div class="psoon-row"><input type="email" id="ps-mail" name="email" placeholder="ton@email.fr" autocomplete="email"><button class="btn">Me prévenir</button></div>
      <small class="muted">Un seul e-mail, le jour du lancement. Pas de spam, promis 🤞 Ou <button class="link-btn" name="email" value="">vote juste pour cette idée</button>.</small>
    </form>
    <?php if ($count >= 3): ?><p class="muted"><?= $count ?> personnes attendent déjà cette fonction 🔥</p><?php endif; ?>
  </div>
  <?php if ($others): ?>
  <h2 class="psoon-more">Ça arrive aussi bientôt</h2>
  <div class="psoon-grid"><?php foreach ($others as $k => $o): ?><a href="<?= url('/bientot/' . $k) ?>"><span><?= $o['emoji'] ?></span><b><?= e($o['name']) ?></b><small><?= e($o['pitch']) ?></small></a><?php endforeach; ?></div>
  <?php endif; ?>
</div>
