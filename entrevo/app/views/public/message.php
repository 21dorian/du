<div class="card center auth-box">
  <div class="state-mark info">!</div>
  <h1><?= e($title) ?></h1>
  <p class="muted"><?= e($text) ?></p>
  <?php if (!empty($link)): ?><a class="btn" href="<?= e($link) ?>"><?= e($link_label ?? 'Continuer') ?></a><?php endif; ?>
</div>
