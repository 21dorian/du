<?php $t = strtotime($ev['starts_at']); $mois = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.']; ?>
<a class="ev-card" href="<?= url('/e/' . $ev['slug']) ?>">
  <div class="ev-img">
    <?php if ($ev['image']): ?><img src="<?= url($ev['image']) ?>" alt="" loading="lazy"><?php else: ?><div class="ph"><?= e($ev['name']) ?></div><?php endif; ?>
    <div class="ev-date"><b><?= date('j', $t) ?></b><span><?= $mois[(int)date('n', $t)] ?></span></div>
  </div>
  <div class="ev-body">
    <h3><?= e($ev['name']) ?></h3>
    <div class="ev-meta"><?= e(fr_date($ev['starts_at'])) ?></div>
    <div class="ev-meta"><?= e(trim(($ev['venue'] ?? '') . ($ev['city'] ? ' · ' . $ev['city'] : ''), ' ·')) ?></div>
    <div class="ev-foot">
      <small><?= e($ev['org_name'] ?? '') ?></small>
      <span class="ev-price"><?= $ev['min_price'] === null ? '' : ($ev['min_price'] === 0 ? 'Gratuit' : 'Dès ' . e(money($ev['min_price']))) ?></span>
    </div>
  </div>
</a>
