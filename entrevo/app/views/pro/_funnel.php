<?php $steps = [['Visiteurs', $fun['visitors']], ['Paniers', $fun['carts']], ['Paiement lancé', $fun['checkouts']], ['Commandes payées', $fun['paid']]];
$top = max(1, $steps[0][1], $fun['paid']); ?>
<div class="funnel">
  <?php foreach ($steps as $i => [$l, $v]): $prevV = $i ? $steps[$i - 1][1] : null; ?>
    <div class="funnel-step">
      <small><?= $l ?></small><b><?= number_format($v, 0, ',', ' ') ?></b>
      <div class="funnel-bar"><i style="width:<?= max(2, round($v / $top * 100)) ?>%"></i></div>
      <span class="muted"><?= $prevV ? round($v / $prevV * 100) . ' % de l\'étape précédente' : ($i ? '—' : number_format($fun['views'], 0, ',', ' ') . ' pages vues') ?></span>
    </div>
  <?php endforeach; ?>
</div>
<p class="muted mb0" style="font-size:13.5px">Paniers abandonnés : <b><?= (int)$fun['abandoned'] ?></b><?= $fun['rate'] !== null ? ' · Taux de conversion global : <b>' . e(number_format($fun['rate'], 1, ',', ' ')) . ' %</b>' : '' ?> · Les visites de ton équipe ne sont pas comptées.</p>
