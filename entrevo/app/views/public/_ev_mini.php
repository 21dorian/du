<?php /** @var array $ev */ $t = strtotime($ev['starts_at']); ?>
<a class="agenda-row" href="<?= url('/e/' . $ev['slug']) ?>">
  <span class="agenda-thumb"><?php if ($ev['image']): ?><img src="<?= url($ev['image']) ?>" alt="" loading="lazy"><?php endif; ?></span>
  <span class="agenda-time"><?= date('d/m', $t) ?></span>
  <span><span class="agenda-title"><?= e($ev['name']) ?></span><span class="agenda-place"><?= e(trim($ev['venue'] . ($ev['city'] ? ' · ' . $ev['city'] : ''), ' ·')) ?><?= !empty($ev['org_name']) ? ' — ' . e($ev['org_name']) : '' ?></span></span>
  <span class="agenda-price"><?= $ev['min_price'] === null ? '' : ($ev['min_price'] === 0 ? 'Gratuit' : 'dès ' . e(money($ev['min_price']))) ?></span>
</a>
