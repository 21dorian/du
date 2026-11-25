<?php /** @var array $n, $org */ ?>
<a class="news-card" href="<?= url('/o/' . $org['slug'] . '/actualites/' . $n['slug']) ?>">
  <?php if ($n['image']): ?><img src="<?= e(url($n['image'])) ?>" alt="" loading="lazy"><?php else: ?><span class="news-ph"></span><?php endif; ?>
  <span class="news-txt"><small><?= e(fr_date((string)($n['published_at'] ?: $n['created_at']), false)) ?></small><b><?= e($n['title']) ?></b><?php if ($n['excerpt']): ?><span><?= e(mb_strimwidth($n['excerpt'], 0, 140, '…')) ?></span><?php endif; ?></span>
</a>
