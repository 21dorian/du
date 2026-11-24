<?php
$u = user();
$first = explode(' ', $u['name'])[0];
$avg = fn($t) => $t['orders'] ? intdiv($t['revenue'], $t['orders']) : 0;
$done = count(array_filter($checklist, fn($c) => $c[1]));
$rateCur = $fun['rate']; $ratePrev = $funPrev['rate'];
?>
<p class="lead">Salut <?= e($first) ?> Voici ce qui s'est passé sur les <b><?= e(mb_strtolower(Insights::PERIODS[$per['days']])) ?></b>.</p>

<?php $nextEv = $upcoming[0]['id'] ?? null; $evPath = fn($p) => $nextEv ? '/pro/evenements/' . $nextEv . $p : '/pro/evenements'; ?>
<div class="quick-actions">
  <a class="qa" href="<?= url('/pro/evenements/nouveau') ?>"><span><?= ui_icon('plus') ?></span>Créer un événement</a>
  <a class="qa" href="<?= url($evPath('/guichet')) ?>"><span><?= ui_icon('store') ?></span>Vente au guichet</a>
  <a class="qa" href="<?= url($evPath('/invitations')) ?>"><span><?= ui_icon('gift') ?></span>Envoyer des invitations</a>
  <a class="qa" href="<?= url($evPath('/messages')) ?>"><span><?= ui_icon('mail') ?></span>Écrire aux participants</a>
  <a class="qa" href="<?= url('/scan') ?>"><span><?= ui_icon('scan') ?></span>Ouvrir le scanner</a>
  <a class="qa" href="<?= url('/pro/commandes', ['csv' => 1]) ?>"><span><?= ui_icon('download') ?></span>Exporter les ventes</a>
  <a class="qa" href="<?= url('/pro/campagnes') ?>"><span><?= ui_icon('megaphone') ?></span>Lancer une campagne</a>
  <a class="qa qa-soon" href="<?= url('/pro/bientot/saison') ?>"><span><?= ui_icon('calendar') ?></span>Préparer la saison <i class="soon-pill dev">dév</i></a>
  <a class="qa" href="<?= url('/pro/budget') ?>"><span><?= ui_icon('euro') ?></span>Mon budget</a>
  <a class="qa" href="<?= url('/pro/taches') ?>"><span><?= ui_icon('sparkles') ?></span>Mes tâches</a>
  <a class="qa qa-soon" href="<?= url('/pro/bientot/app-orga') ?>"><span><?= ui_icon('sparkles') ?></span>Appli mobile <i class="soon-pill dev">dév</i></a>
</div>

<div class="today card">
  <div class="today-title"><span class="live-dot">live</span><b>Aujourd'hui</b><small class="muted"><?= e(fr_date(date('Y-m-d H:i:s'), false)) ?></small></div>
  <?php foreach ([['', 'Recettes', money((int)$today['revenue']), (int)$today['revenue'], (int)$yday['revenue']], ['', 'Billets', (string)(int)$today['tickets'], (int)$today['tickets'], (int)$yday['tickets']],
      ['', 'Visiteurs', (string)$today['visitors'], $today['visitors'], $yday['visitors']], ['', 'Entrées scannées', (string)$today['scans'], $today['scans'], $yday['scans']]] as [$ic, $l, $v, $c, $p]): ?>
    <div class="today-item"><small><?= $ic ?> <?= $l ?></small><b><?= e($v) ?></b><span class="muted"><?= Viz::delta((float)$c, (float)$p, 'vs hier') ?></span></div>
  <?php endforeach; ?>
</div>


<div class="stat-grid">
  <div class="stat"><div class="stat-top"><small>Recettes</small><?= Viz::sparkline($sCur['revenue']) ?></div><b><?= e(money($cur['revenue'])) ?></b><div class="stat-sub"><?= Viz::delta($cur['revenue'], $prev['revenue']) ?></div></div>
  <div class="stat"><div class="stat-top"><small>Billets vendus</small><?= Viz::sparkline($sCur['orders']) ?></div><b><?= number_format($cur['tickets'], 0, ',', ' ') ?></b><div class="stat-sub"><?= Viz::delta($cur['tickets'], $prev['tickets']) ?></div></div>
  <div class="stat"><div class="stat-top"><small>Commandes</small></div><b><?= $cur['orders'] ?></b><div class="stat-sub">panier moyen <?= e(money($avg($cur))) ?> · <?= Viz::delta($avg($cur), $avg($prev), 'panier') ?></div></div>
  <div class="stat"><div class="stat-top"><small>Conversion</small></div><b><?= $rateCur === null ? '—' : e(number_format($rateCur, 1, ',', ' ')) . ' %' ?></b>
    <div class="stat-sub"><?= $rateCur === null ? '<small class="muted">pas encore de visites mesurées</small>' : number_format($fun['visitors'], 0, ',', ' ') . ' visiteurs · ' . Viz::delta((float)$rateCur, (float)($ratePrev ?? 0)) ?></div></div>
</div>

<div class="split-3">
  <div class="card">
    <div class="card-head"><h2>Recettes</h2><small><?= e($sCur['labels'][0] ?? '') ?> → <?= e(end($sCur['labels']) ?: '') ?></small></div>
    <?= Viz::chart('area', ['labels' => $sCur['labels'], 'xlabel' => 'Jour', 'series' => [
        ['name' => Insights::PERIODS[$per['days']] . ' (actuel)', 'values' => $sCur['revenue']],
        ['name' => 'Période précédente', 'values' => array_slice(array_pad($sPrev['revenue'], count($sCur['revenue']), 0), 0, count($sCur['revenue']))],
    ]], 'Recettes par jour', 'money', 260) ?>
  </div>
  <div>
    <div class="card card-dark">
      <small>À te reverser</small>
      <b class="big"><?= e(money($balance['balance'])) ?></b>
      <p>Encaissé en ligne : <?= e(money($balance['collected'])) ?> · commission <?= e(money($balance['commission'])) ?></p>
      <a class="btn btn-sm btn-accent" href="<?= url('/pro/finances') ?>">Voir les finances →</a>
    </div>
    <?php if ($done < count($checklist)): ?>
    <div class="card">
      <div class="card-head"><h2>Bien démarrer</h2><small><?= $done ?>/<?= count($checklist) ?></small></div>
      <div class="bar"><i style="width:<?= round($done / count($checklist) * 100) ?>%;background:var(--green)"></i></div>
      <ul class="checklist"><?php foreach ($checklist as [$l, $ok, $href]): ?><li class="<?= $ok ? 'ok' : '' ?>"><?= $ok ? '' : '' ?> <?= $ok ? e($l) : '<a href="' . url($href) . '">' . e($l) . '</a>' ?></li><?php endforeach; ?></ul>
    </div>
    <?php else: ?>
    <div class="card"><div class="card-head"><h2>Top événements</h2></div>
      <?php $mx = max(1, max(array_map(fn($t) => (int)$t['t'], $top ?: [['t' => 1]]))); foreach ($top as $t): ?>
        <div class="hbar"><span class="hbar-l"><a href="<?= url('/pro/evenements/' . $t['id']) ?>"><?= e($t['name']) ?></a></span><span class="hbar-b"><i style="width:<?= round($t['t'] / $mx * 100) ?>%;background:<?= Viz::SERIES ?>"></i></span><span class="hbar-v"><?= e(money((int)$t['t'])) ?></span></div>
      <?php endforeach; ?><?php if (!$top): ?><p class="muted">Pas de vente sur la période.</p><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="split-3 mt">
  <div>
    <div class="section-head"><h2>Prochains événements</h2><a href="<?= url('/pro/evenements') ?>">Tous →</a></div>
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
    <div class="card-head"><h2>En direct</h2><span class="live-dot">live</span></div>
    <div class="feed" data-feed="<?= url('/pro/activite') ?>"><?= view('pro/_activity', ['activity' => $activity]) ?></div>
  </div>
</div>

<div class="split-3 mt">
  <div class="card">
    <div class="card-head"><h2>Quand ça achète</h2><small>Commandes par jour et par heure</small></div>
    <?php $hmx = max(1, max(array_map('max', $heat))); $jours = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']; $best = [0, 0, 0];
      foreach ($heat as $d => $hs) foreach ($hs as $h => $v) if ($v > $best[2]) $best = [$d, $h, $v]; ?>
    <div class="heat" role="img" aria-label="Carte de chaleur des commandes par jour et heure">
      <span><?= ui_icon('sparkles') ?></span><?php for ($h = 0; $h < 24; $h++): ?><span class="heat-h"><?= $h % 3 === 0 ? $h . 'h' : '' ?></span><?php endfor; ?>
      <?php foreach ($heat as $d => $hs): ?>
        <span class="heat-d"><?= $jours[$d] ?></span>
        <?php foreach ($hs as $h => $v): $a = $v ? 0.12 + 0.88 * $v / $hmx : 0; ?><span class="heat-c" style="--a:<?= round($a, 2) ?>" title="<?= $jours[$d] ?> <?= $h ?>h–<?= $h + 1 ?>h : <?= $v ?> commande<?= $v > 1 ? 's' : '' ?>"></span><?php endforeach; ?>
      <?php endforeach; ?>
    </div>
    <div class="heat-legend"><span class="muted">Moins</span><i style="--a:.12"></i><i style="--a:.4"></i><i style="--a:.7"></i><i style="--a:1"></i><span class="muted">Plus</span>
      <?php if ($best[2]): ?><span class="grow"></span><span>Pic : <b><?= $jours[$best[0]] ?> <?= $best[1] ?>h</b> — c'est le bon moment pour poster sur tes réseaux</span><?php endif; ?></div>
    <details class="viz-table"><summary>Voir les données</summary><div class="table-wrap"><table class="table"><thead><tr><th>Jour</th><?php for ($h = 0; $h < 24; $h += 3): ?><th class="num"><?= $h ?>–<?= $h + 2 ?>h</th><?php endfor; ?></tr></thead><tbody>
      <?php foreach ($heat as $d => $hs): ?><tr><td><?= $jours[$d] ?></td><?php for ($h = 0; $h < 24; $h += 3): ?><td class="num"><?= $hs[$h] + $hs[$h + 1] + $hs[$h + 2] ?></td><?php endfor; ?></tr><?php endforeach; ?>
    </tbody></table></div></details>
  </div>
  <div class="card">
    <div class="card-head"><h2>À traiter</h2><?= $todo ? '<span class="count-pill">' . count($todo) . '</span>' : '' ?></div>
    <?php foreach ($todo as [$ic, $t, $sub, $href, $cls]): ?>
      <a class="todo<?= $cls ? ' ' . $cls : '' ?>" href="<?= url($href) ?>"><span class="todo-ic"><?= $ic ?></span><span><b><?= e($t) ?></b><small><?= e($sub) ?></small></span><i aria-hidden="true">→</i></a>
    <?php endforeach; ?>
    <?php if (!$todo): ?><div class="todo-empty">Rien à traiter. Tout roule, va prendre un café</div><?php endif; ?>
  </div>
</div>

<div class="split-3 mt">
  <div class="card">
    <div class="card-head"><h2>D'où viennent les ventes</h2><small>Source UTM ou canal</small></div>
    <?php $smx = max(1, max(array_map(fn($x) => (int)$x['t'], $sources ?: [['t' => 1]]))); foreach ($sources as $src): ?>
      <div class="hbar"><span class="hbar-l"><?= e($src['src']) ?></span><span class="hbar-b"><i style="width:<?= max(2, round($src['t'] / $smx * 100)) ?>%;background:<?= Viz::SERIES ?>"></i></span><span class="hbar-v"><?= e(money((int)$src['t'])) ?> · <?= (int)$src['n'] ?></span></div>
    <?php endforeach; ?>
    <?php if (!$sources): ?><p class="muted">Pas de vente sur la période.</p><?php endif; ?>
    <p class="muted mb0" style="font-size:13px;margin-top:10px">Astuce : ajoute <code>?utm_source=instagram</code> à ton lien pour savoir ce qui marche vraiment.</p>
  </div>
  <div class="card">
    <div class="card-head"><h2>Objectifs</h2><a href="<?= url('/pro/evenements') ?>">Définir →</a></div>
    <?php foreach ($goals as $g): $pc = min(100, round($g['done'] / max(1, $g['revenue_goal']) * 100)); ?>
      <div class="goal"><div class="goal-top"><a href="<?= url('/pro/evenements/' . $g['id']) ?>"><b><?= e($g['name']) ?></b></a><span><?= $pc ?> %</span></div>
        <div class="goal-bar"><i style="width:<?= $pc ?>%"></i></div>
        <small class="muted"><?= e(money((int)$g['done'])) ?> sur <?= e(money((int)$g['revenue_goal'])) ?><?= $pc >= 100 ? ' · objectif atteint !' : ' · encore ' . e(money((int)$g['revenue_goal'] - (int)$g['done'])) ?></small></div>
    <?php endforeach; ?>
    <?php if (!$goals): ?><p class="muted">Fixe un objectif de recettes dans « Infos & réglages » d'un événement : on suit la progression ici</p><?php endif; ?>
  </div>
</div>

<div class="card mt">
  <div class="card-head"><h2>Entonnoir de conversion</h2><small>Sur les <?= e(mb_strtolower(Insights::PERIODS[$per['days']])) ?>, tous événements</small></div>
  <?= view('pro/_funnel', ['fun' => $fun]) ?>
</div>

<div class="split-3 mt">
  <div class="card soon-teaser">
    <div class="card-head"><h2>Bientôt dans Entrevo</h2><a href="<?= url('/pro/modules') ?>">Tous les modules →</a></div>
    <div class="mod-grid">
      <?php foreach ($soon as $k => $f): ?><a class="mod-card is-soon" href="<?= url('/pro/bientot/' . $k) ?>"><span class="mod-ico"><?= feature_icon($f) ?></span><span class="mod-body"><b><?= e($f['name']) ?></b><span><?= e($f['pitch']) ?></span></span><span class="mod-foot"><span class="soon-pill">En développement</span><span class="muted">Voter →</span></span></a><?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h2>Quoi de neuf</h2><small>v<?= e(app_version()) ?></small></div>
    <ul class="news">
      <li><b>Base participants</b> — tous tes acheteurs, segmentés (fidèles, gros paniers, dormants). <a href="<?= url('/pro/participants') ?>">Voir</a></li>
      <li><b>Objectifs de recettes</b> par événement, suivis ici.</li>
      <li><b>Catalogue de modules</b> — vote pour ce qui te manque. <a href="<?= url('/pro/modules') ?>">Voter</a></li>
      <li><b>Commandes</b> : filtres, actions groupées, remboursement partiel.</li>
    </ul>
  </div>
</div>
