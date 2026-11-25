<?php /** @var array $org, $p; ?array $more */ $news = $p['kind'] === 'news'; ?>
<article class="site-article">
  <?php if ($news): ?><a class="back-link" href="<?= url('/o/' . $org['slug'] . '/actualites') ?>">‹ Actualités</a><span class="eyebrow"><?= e(fr_date((string)($p['published_at'] ?: $p['created_at']), false)) ?></span><?php endif; ?>
  <h1><?= e($p['title']) ?></h1>
  <?php if ($p['excerpt']): ?><p class="lead"><?= e($p['excerpt']) ?></p><?php endif; ?>
  <?php if ($p['image']): ?><img class="site-cover" src="<?= e(url($p['image'])) ?>" alt=""><?php endif; ?>
  <div class="prose"><?= rich_text((string)$p['body']) ?></div>
</article>
<?php if (!empty($more)): ?><section class="site-more"><h2 class="org-h">À lire aussi</h2><div class="news-grid"><?php foreach ($more as $n) echo view('public/_news_card', ['n' => $n, 'org' => $org]); ?></div></section><?php endif; ?>
