<?php /** @var array $live, $ev; string $btab, $bbase; int $cashlessOn */ echo view('pro/bars/_nav', compact('btab', 'bbase'));
$L = $live; $M = fn($k) => $L['methods'][$k] ?? ['n' => 0, 't' => 0, 'o' => 0]; ?>
<div class="bars-live" data-live="<?= url($bbase, ['live' => 1]) ?>">
  <div class="row between mb"><div class="seg"><a class="seg-a <?= $L['day'] ? 'on' : '' ?>" href="<?= url($bbase) ?>">Aujourd'hui</a><a class="seg-a <?= $L['day'] ? '' : 'on' ?>" href="<?= url($bbase, ['jour' => 'tout']) ?>">Tout l'événement</a></div>
    <small class="muted"><span class="live-dot"></span> En direct · mis à jour à <b data-k="at"><?= e($L['at']) ?></b> · <?= (int)$L['online'] ?>/<?= (int)$L['devices'] ?> caisse(s) connectée(s)</small></div>
  <div class="kpi-grid bars-kpis">
    <div class="kpi-mini kpi-big"><span>Chiffre d'affaires</span><b data-k="total"><?= e(money((int)$L['total'])) ?></b><small class="muted"><span data-k="count"><?= (int)$L['count'] ?></span> ventes · panier moyen <span data-k="basket"><?= e(money((int)$L['basket'])) ?></span></small></div>
    <?php foreach (['cashless' => 'Cashless', 'card' => 'Carte', 'cash' => 'Espèces'] as $k => $l): ?><div class="kpi-mini"><span><?= $l ?></span><b data-k="m-<?= $k ?>"><?= e(money($M($k)['t'])) ?></b><small class="muted"><?= $M($k)['n'] ?> vente(s)</small></div><?php endforeach; ?>
    <div class="kpi-mini"><span>Offert</span><b><?= e(money($M('offered')['o'] + $M('allowance')['o'] + $M('cash')['o'] + $M('card')['o'] + $M('cashless')['o'])) ?></b><small class="muted">dont crédits staff <?= e(money($M('allowance')['o'])) ?></small></div>
  </div>
  <div class="split mt">
    <div class="card"><div class="card-head"><h2>Ventes par heure</h2></div>
      <?php if ($L['hours']): $mx = max(1, max(array_column($L['hours'], 't'))); ?><div class="hour-bars"><?php foreach ($L['hours'] as $h => $v): ?><div class="hb" title="<?= e($h . ' · ' . money($v['t']) . ' · ' . $v['n'] . ' ventes') ?>"><i style="height:<?= max(2, round($v['t'] / $mx * 100)) ?>%"></i><span><?= e($h) ?></span></div><?php endforeach; ?></div>
      <?php else: ?><p class="muted mb0">Pas encore de vente.</p><?php endif; ?></div>
    <div class="card"><div class="card-head"><h2>Meilleures ventes</h2></div>
      <?php foreach ($L['products'] as $n => $p): ?><div class="list-item"><span class="grow"><?= e($n) ?></span><small class="muted">× <?= (int)$p['q'] ?></small><b style="min-width:84px;text-align:right"><?= e(money((int)$p['t'])) ?></b></div><?php endforeach; ?>
      <?php if (!$L['products']): ?><p class="muted mb0">Rien pour l'instant.</p><?php endif; ?></div>
  </div>
  <div class="split-3 mt">
    <div class="card"><h2>Points de vente</h2>
      <?php foreach ($L['bars'] as $b): ?><div class="list-item"><span class="grow"><b><?= e($b['name']) ?></b><br><small class="muted"><?= (int)$b['n'] ?> ventes · <?= (int)$b['rate'] ?> sur 15 min<?= $b['last'] ? ' · dernière ' . e(ago($b['last'])) : '' ?></small></span><b><?= e(money((int)$b['t'])) ?></b></div><?php endforeach; ?></div>
    <div class="card"><h2>Caisses ouvertes</h2>
      <?php foreach ($L['sessions'] as $s): ?><div class="list-item"><span class="grow"><b><?= e($s['bar']) ?></b><?= $s['full'] ? ' <span class="badge red">caisse pleine</span>' : '' ?><br><small class="muted"><?= e((string)$s['device']) ?> · depuis <?= e(date('H:i', strtotime($s['since']))) ?></small></span><span class="num"><small class="muted">espèces</small><br><b><?= e(money($s['cash'])) ?></b></span></div><?php endforeach; ?>
      <?php if (!$L['sessions']): ?><p class="muted mb0">Aucune caisse ouverte.</p><?php endif; ?>
      <a class="btn btn-sm btn-light mt" href="<?= url($bbase . '/caisses') ?>">Caisses & clôtures</a></div>
    <div class="card"><h2>Alertes</h2>
      <?php foreach ($L['low'] as $l): ?><div class="list-item"><span class="grow"><?= e($l['name']) ?><br><small class="muted"><?= e($l['bar']) ?></small></span><span class="badge <?= (int)$l['qty'] <= 0 ? 'red' : 'orange' ?>"><?= (int)$l['qty'] ?> en stock</span></div><?php endforeach; ?>
      <?php foreach ($L['cups'] as $c): if ($c['left'] !== null && $c['left'] <= max(20, (int)$c['stock_initial'] * .1)): ?><div class="list-item"><span class="grow"><?= e($c['name']) ?></span><span class="badge orange"><?= (int)$c['left'] ?> restants</span></div><?php endif; endforeach; ?>
      <?php if (!$L['low'] && !array_filter($L['cups'], fn($c) => $c['left'] !== null && $c['left'] <= max(20, (int)$c['stock_initial'] * .1)) && !array_filter($L['sessions'], fn($s) => $s['full'])): ?><p class="muted mb0">Tout va bien.</p><?php endif; ?></div>
  </div>
  <?php if ($L['cups']): ?><div class="card mt"><h2>Consignes</h2><div class="cups-row"><?php foreach ($L['cups'] as $c): ?><div><span><?= e($c['name']) ?></span><b><?= (int)$c['out'] ?></b><small class="muted">en circulation · <?= (int)$c['issued'] ?> sortis · <?= (int)$c['returned'] ?> rendus</small></div><?php endforeach; ?></div></div><?php endif; ?>
</div>
<script>
(() => { const el = document.querySelector('[data-live]'); if (!el) return;
  setInterval(() => { if (document.hidden) return; fetch(el.dataset.live + (location.search.includes('jour=tout') ? '&jour=tout' : ''), { credentials: 'same-origin' }).then(r => r.json()).then(j => {
    const f = c => (c / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' }), set = (k, v) => { const n = el.querySelector('[data-k="' + k + '"]'); if (n) n.textContent = v; };
    set('at', j.at); set('total', f(j.total)); set('count', j.count); set('basket', f(j.basket)); ['cashless', 'card', 'cash'].forEach(m => set('m-' + m, f((j.methods[m] || { t: 0 }).t)));
    if (j.count !== +(el.dataset.count || j.count)) location.reload(); el.dataset.count = j.count; }).catch(() => {}); }, 15000); })();
</script>
