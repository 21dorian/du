<?php
$u = user();
$first = explode(' ', $u['name'])[0];
$avg = fn($t) => $t['orders'] ? intdiv($t['revenue'], $t['orders']) : 0;
$done = count(array_filter($checklist, fn($c) => $c[1]));
$rateCur = $fun['rate']; $ratePrev = $funPrev['rate'];
?>
<p class="lead">Salut <?= e($first) ?> 👋 Voici ce qui s'est passé sur les <b><?= e(mb_strtolower(Insights::PERIODS[$per['days']])) ?></b>.</p>

<div class="stat-grid">
  <div class="stat"><div class="stat-top"><small>💶 Recettes</small><?= Viz::sparkline($sCur['revenue']) ?></div><b><?= e(money($cur['revenue'])) ?></b><div class="stat-sub"><?= Viz::delta($cur['revenue'], $prev['revenue']) ?></div></div>
  <div class="stat"><div class="stat-top"><small>🎟️ Billets vendus</small><?= Viz::sparkline($sCur['orders']) ?></div><b><?= number_format($cur['tickets'], 0, ',', ' ') ?></b><div class="stat-sub"><?= Viz::delta($cur['tickets'], $prev['tickets']) ?></div></div>
  <div class="stat"><div class="stat-top"><small>🧾 Commandes</small></div><b><?= $cur['orders'] ?></b><div class="stat-sub">panier moyen <?= e(money($avg($cur))) ?> · <?= Viz::delta($avg($cur), $avg($prev), 'panier') ?></div></div>
  <div class="stat"><div class="stat-top"><small>🎯 Conversion</small></div><b><?= $rateCur === null ? '—' : e(number_format($rateCur, 1, ',', ' ')) . ' %' ?></b>
    <div class="stat-sub"><?= $rateCur === null ? '<small class="muted">pas encore de visites mesurées</small>' : number_format($fun['visitors'], 0, ',', ' ') . ' visiteurs · ' . Viz::delta((float)$rateCur, (float)($ratePrev ?? 0)) ?></div></div>
</div>

<div class="split-3">
  <div class="card">
    <div class="card-head"><h2>📈 Recettes</h2><small><?= e($sCur['labels'][0] ?? '') ?> → <?= e(end($sCur['labels']) ?: '') ?></small></div>
    <?= Viz::chart('area', ['labels' => $sCur['labels'], 'xlabel' => 'Jour', 'series' => [
        ['name' => Insights::PERIODS[$per['days']] . ' (actuel)', 'values' => $sCur['revenue']],
        ['name' => 'Période précédente', 'values' => array_slice(array_pad($sPrev['revenue'], count($sCur['revenue']), 0), 0, count($sCur['revenue']))],
    ]], 'Recettes par jour', 'money', 260) ?>
  </div>
  <div>
    <div class="card card-dark">
      <small>💰 À te reverser</small>
      <b class="big"><?= e(money($balance['balance'])) ?></b>
      <p>Encaissé en ligne : <?= e(money($balance['collected'])) ?> · commission <?= e(money($balance['commission'])) ?></p>
      <a class="btn btn-sm btn-accent" href="<?= url('/pro/finances') ?>">Voir les finances →</a>
    </div>
    <?php if ($done < count($checklist)): ?>
    <div class="card">
      <div class="card-head"><h2>🚀 Bien démarrer</h2><small><?= $done ?>/<?= count($checklist) ?></small></div>
      <div class="bar"><i style="width:<?= round($done / count($checklist) * 100) ?>%;background:var(--green)"></i></div>
      <ul class="checklist"><?php foreach ($checklist as [$l, $ok, $href]): ?><li class="<?= $ok ? 'ok' : '' ?>"><?= $ok ? '✅' : '⬜' ?> <?= $ok ? e($l) : '<a href="' . url($href) . '">' . e($l) . '</a>' ?></li><?php endforeach; ?></ul>
    </div>
    <?php else: ?>
    <div class="card"><div class="card-head"><h2>🏆 Top événements</h2></div>
      <?php $mx = max(1, max(array_map(fn($t) => (int)$t['t'], $top ?: [['t' => 1]]))); foreach ($top as $t): ?>
        <div class="hbar"><span class="hbar-l"><a href="<?= url('/pro/evenements/' . $t['id']) ?>"><?= e($t['name']) ?></a></span><span class="hbar-b"><i style="width:<?= round($t['t'] / $mx * 100) ?>%;background:<?= Viz::SERIES ?>"></i></span><span class="hbar-v"><?= e(money((int)$t['t'])) ?></span></div>
      <?php endforeach; ?><?php if (!$top): ?><p class="muted">Pas de vente sur la période.</p><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="split-3 mt">
  <div>
    <div class="section-head"><h2>📅 Prochains événements</h2><a href="<?= url('/pro/evenements') ?>">Tous →</a></div>
    <div class="ev-cards">
    <?php foreach ($upcoming as $ev): $pct = $ev['cap'] ? $ev['stats']['sold'] / $ev['cap'] * 100 : null; ?>
      <a class="ev-card2" href="<?= url('/pro/evenements/' . $ev['id']) ?>">
        <span class="ev-card2-img"><?php if ($ev['image']): ?><img src="<?= url($ev['image']) ?>" alt=""><?php endif; ?><span class="ev-card2-count" data-countdown="<?= e($ev['starts_at']) ?>"></span></span>
        <span class="ev-card2-body">
          <span class="ev-card2-head"><b><?= e($ev['name']) ?></b><?= badge($ev['status']) ?></span>
          <small><?= e(fr_date($ev['starts_at'])) ?></small>
          <span class="ev-card2-stats"><?= Viz::ring($pct, $pct === null ? (string)$ev['stats']['sold'] : '', 64) ?>
            <span><b><?= $ev['stats']['sold'] ?></b><?= $ev['cap'] ? ' / ' . $ev['cap'] : '' ?> billets<br><b><?= e(money($ev['stats']['revenue'])) ?></b><br><?= $ev['today'] ? '<span class="delta up">+' . $ev['today'] . ' aujourd\'hui</span>' : '<small class="muted">0 aujourd\'hui</small>' ?></span></span>
        </span>
      </a>
    <?php endforeach; ?>
    <?php if (!$upcoming): ?><div class="card empty">Aucun événement à venir. <a href="<?= url('/pro/evenements/nouveau') ?>">On en crée un ?</a></div><?php endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h2>⚡ En direct</h2><span class="live-dot">live</span></div>
    <div class="feed" data-feed="<?= url('/pro/activite') ?>"><?= view('pro/_activity', ['activity' => $activity]) ?></div>
  </div>
</div>

<div class="card mt">
  <div class="card-head"><h2>🎯 Entonnoir de conversion</h2><small>Sur les <?= e(mb_strtolower(Insights::PERIODS[$per['days']])) ?>, tous événements</small></div>
  <?= view('pro/_funnel', ['fun' => $fun]) ?>
</div>
