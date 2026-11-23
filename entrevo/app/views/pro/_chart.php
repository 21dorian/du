<?php $max = max(1, max($chart)); $keys = array_keys($chart); ?>
<div class="chart" role="img" aria-label="Ventes des 30 derniers jours">
  <?php foreach ($chart as $d => $v): ?><div style="height:<?= max(1, round($v / $max * 100)) ?>%" title="<?= e(date('d/m', strtotime($d))) ?> : <?= e(money($v)) ?>"></div><?php endforeach; ?>
</div>
<div class="chart-labels"><span style="text-align:left"><?= e(date('d/m', strtotime($keys[0]))) ?></span><span><?= e(date('d/m', strtotime($keys[intdiv(count($keys), 2)]))) ?></span><span style="text-align:right">Aujourd'hui</span></div>
