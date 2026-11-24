<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($org['name']) ?></title><link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>:root{--primary:<?= e(setting('primary_color', '#0071e3')) ?>}body{background:transparent}.w{padding:12px}</style></head>
<body class="pub"><div class="w">
<?php if (!$events): ?><p class="muted">Aucun événement en vente pour le moment.</p><?php endif; ?>
<?php foreach ($events as $ev): $t = strtotime($ev['starts_at']); ?>
  <a class="agenda-row" href="<?= e(abs_url('/e/' . $ev['slug'], ['ref' => 'widget'])) ?>" target="_blank">
    <span class="agenda-thumb"><?php if ($ev['image']): ?><img src="<?= url($ev['image']) ?>" alt=""><?php endif; ?></span>
    <span class="agenda-time"><?= date('d/m', $t) ?></span>
    <span><span class="agenda-title"><?= e($ev['name']) ?></span><span class="agenda-place"><?= $ev['session_count'] > 1 ? $ev['session_count'] . ' dates · ' : '' ?><?= e(trim($ev['venue'] . ' · ' . $ev['city'], ' ·')) ?></span></span>
    <span class="agenda-price"><?= $ev['min_price'] === null ? '' : ($ev['min_price'] === 0 ? 'Gratuit' : 'Dès ' . e(money($ev['min_price']))) ?></span>
  </a>
<?php endforeach; ?>
</div></body></html>
