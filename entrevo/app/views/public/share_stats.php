<?php /** @var array $ev, $org, $l, $secs, $D */ $has = fn($k) => in_array($k, $secs, true); $cap = (int)$D['cap']; $sold = (int)$D['tk']['n']; ?>
<div class="container" style="max-width:920px;padding:28px 16px">
  <p class="muted mb0"><small>Partagé par <?= e($org['name']) ?> avec <?= e($l['label']) ?> · mis à jour en direct · <?= date('d/m/Y H:i') ?></small></p>
  <h1 style="margin:6px 0 2px"><?= e($ev['name']) ?></h1>
  <p class="muted"><?= e(fr_date($ev['starts_at'])) ?><?= $ev['venue'] ? ' · ' . e($ev['venue']) : '' ?></p>
  <div class="kpi-grid mt">
    <?php if ($has('ventes')): ?><div class="kpi-mini"><span>Billets</span><b><?= $sold ?><?= $cap ? '<small> / ' . $cap . '</small>' : '' ?></b><?php if ($cap): ?><small class="muted"><?= (int)round($sold / max(1, $cap) * 100) ?> % de remplissage</small><?php endif; ?></div><?php endif; ?>
    <?php if ($has('recette')): ?><div class="kpi-mini"><span>Recette billetterie</span><b><?= e(money($D['ticketRev'])) ?></b></div><?php endif; ?>
    <?php if ($has('presence')): ?><div class="kpi-mini"><span>Présents</span><b><?= (int)$D['tk']['inside_ever'] ?></b><small class="muted"><?= e((string)$D['show_rate']) ?> % des billets</small></div><?php endif; ?>
    <?php if ($has('bars')): ?><div class="kpi-mini"><span>Bars</span><b><?= e(money($D['barRev'])) ?></b><small class="muted"><?= e(money($D['per_head'])) ?> par présent</small></div><?php endif; ?>
  </div>
  <?php if ($has('courbe') && $D['byDay']): $max = max(array_map(fn($r) => (int)$r['n'], $D['byDay'])) ?: 1; ?>
    <div class="card mt"><h2>Commandes sur 30 jours</h2><div class="share-bars"><?php foreach ($D['byDay'] as $r): ?><span title="<?= e(fr_date($r['d'], false)) ?> : <?= (int)$r['n'] ?> commande(s)"><i style="height:<?= max(3, (int)round($r['n'] / $max * 100)) ?>%"></i></span><?php endforeach; ?></div></div>
  <?php endif; ?>
  <?php if ($has('tarifs')): ?><div class="card table-card mt"><div class="card-head"><h2>Par tarif</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>Tarif</th><th class="num">Vendus</th><th class="num">Quota</th></tr></thead><tbody>
    <?php foreach ($D['byRate'] as $r): ?><tr><td><?= e($r['name']) ?></td><td class="num"><?= (int)$r['sold'] ?></td><td class="num"><?= (int)$r['quota'] ?: '—' ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
  <?php if ($has('bars') && $D['topBars']): ?><div class="card table-card mt"><div class="card-head"><h2>Points de vente</h2></div><div class="table-wrap"><table class="table"><tbody>
    <?php foreach ($D['topBars'] as $b): ?><tr><td><?= e($b['name']) ?></td><td class="num"><?= (int)$b['n'] ?> tickets</td><td class="num"><?= e(money((int)$b['ttc'])) ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>
  <p class="muted center mt"><small>Page en lecture seule. Aucune donnée personnelle n'est affichée.</small></p>
</div>
<style>.share-bars{display:flex;align-items:flex-end;gap:3px;height:140px}.share-bars span{flex:1;height:100%;display:flex;align-items:flex-end}.share-bars i{display:block;width:100%;border-radius:4px 4px 0 0;background:var(--accent,#0071e3)}</style>
