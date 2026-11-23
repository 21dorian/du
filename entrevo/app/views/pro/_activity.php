<?php foreach ($activity as $a): ?>
  <a class="feed-item" href="<?= e($a['url']) ?>">
    <span class="feed-ico"><?= $a['icon'] ?></span>
    <span class="feed-txt"><b><?= e($a['title']) ?></b><small><?= e($a['sub']) ?> · <?= e(ago($a['at'])) ?></small></span>
    <?php if ($a['amount'] !== null): ?><span class="feed-amt <?= $a['amount'] < 0 ? 'neg' : '' ?>"><?= $a['amount'] < 0 ? '−' . e(money(-$a['amount'])) : e(money($a['amount'])) ?></span><?php endif; ?>
  </a>
<?php endforeach; ?>
<?php if (!$activity): ?><p class="muted">Calme plat. Partage tes liens, la première vente arrive 💪</p><?php endif; ?>
