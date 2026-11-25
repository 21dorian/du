<?php /** @var string $btab, $bbase */ ?>
<nav class="bars-nav"><?php foreach (BARS_TABS as $k => $l): ?><a href="<?= url($bbase . ($k ? '/' . $k : '')) ?>" class="<?= $k === $btab ? 'on' : '' ?>"><?= e($l) ?></a><?php endforeach; ?>
  <a class="bars-app" href="<?= url('/caisse/') ?>" target="_blank">Ouvrir la caisse ↗</a></nav>
