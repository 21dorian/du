<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Badge bénévole</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>"></head>
<body class="badge-page"><div class="vbadge">
  <div class="vbadge-top"><b><?= e($org['name']) ?></b><span>BÉNÉVOLE</span></div>
  <div class="vbadge-name"><?= e($m['first_name']) ?><br><small><?= e($m['last_name']) ?></small></div>
  <?php if ($m['team']): ?><div class="vbadge-team"><?= e($m['team']) ?></div><?php endif; ?>
  <div class="vbadge-qr"><?= Qr::svg($m['token'], 220) ?></div>
  <small class="vbadge-foot">À montrer en arrivant et en repartant pour le pointage · <?= e(setting('platform_name')) ?></small>
  <button class="btn no-print" onclick="window.print()">Imprimer</button>
</div></body></html>
