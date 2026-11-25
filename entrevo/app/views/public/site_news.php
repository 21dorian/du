<?php /** @var array $org, $news */ ?>
<h1 class="site-h1">Actualités</h1>
<?php if ($news): ?><div class="news-grid"><?php foreach ($news as $n) echo view('public/_news_card', ['n' => $n, 'org' => $org]); ?></div>
<?php else: ?><div class="card empty">Pas encore d'actualité.</div><?php endif; ?>
