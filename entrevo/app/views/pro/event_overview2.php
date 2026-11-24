<?php
$pct = $cap ? $stats['sold'] / $cap * 100 : null;
$link = abs_url('/e/' . $ev['slug']);
$presence = $stats['sold'] ? round($stats['checked'] / $stats['sold'] * 100) : 0;
?>
<?php if ($ev['status'] === 'draft'): ?><div class="flash info">Brouillon : personne ne le voit encore. Score de préparation <b><?= $ready['score'] ?> %</b>, puis clique sur <b>Publier</b>.</div><?php endif; ?>
<div class="ev-hero">
  <div class="ev-hero-ring"><?= Viz::ring($pct, $pct === null ? (string)$stats['sold'] : '', 120) ?><small><?= $cap ? $stats['sold'] . ' / ' . $cap . ' places' : $stats['sold'] . ' billets (jauge libre)' ?></small></div>
  <div class="ev-hero-main">
    <span class="eyebrow-dark"><?= strtotime($ev['starts_at']) > time() ? 'Début dans' : 'Commencé' ?></span>
    <b class="ev-hero-count" data-countdown="<?= e($ev['starts_at']) ?>" data-done="C'est le grand jour !">—</b>
    <small><?= e(fr_date($ev['starts_at'])) ?><?= $ev['venue'] ? ' · ' . e($ev['venue']) : '' ?></small>
  </div>
  <div class="ev-hero-actions">
    <a class="btn btn-sm" href="<?= url('/pro/evenements/' . $ev['id'] . '/guichet') ?>">Guichet</a>
    <a class="btn btn-sm btn-ghost" href="<?= url('/scan') ?>" target="_blank">Scanner</a>
    <a class="btn btn-sm btn-ghost" href="<?= url('/pro/evenements/' . $ev['id'] . '/messages') ?>">Message</a>
    <a class="btn btn-sm btn-ghost" href="<?= url('/pro/evenements/' . $ev['id'] . '/stats') ?>">Stats</a>
  </div>
</div>
<div class="stat-grid">
  <div class="stat"><div class="stat-top"><small>Recettes totales</small><?= Viz::sparkline($spark['revenue']) ?></div><b><?= e(money($stats['revenue'])) ?></b><div class="stat-sub">7 j : <?= e(money($cur['revenue'])) ?> · <?= Viz::delta($cur['revenue'], $prev['revenue'], 'vs 7 j préc.') ?></div>
    <?php if ((int)($ev['revenue_goal'] ?? 0) > 0): $gp = min(100, round($stats['revenue'] / (int)$ev['revenue_goal'] * 100)); ?><div class="goal-mini" title="Objectif : <?= e(money((int)$ev['revenue_goal'])) ?>"><div class="goal-bar"><i style="width:<?= $gp ?>%"></i></div><small><?= $gp ?> % de l'objectif (<?= e(money((int)$ev['revenue_goal'])) ?>)</small></div><?php endif; ?></div>
  <div class="stat"><div class="stat-top"><small>Billets</small><?= Viz::sparkline($spark['orders']) ?></div><b><?= $stats['sold'] ?></b><div class="stat-sub">7 j : <?= $cur['tickets'] ?> · <?= Viz::delta($cur['tickets'], $prev['tickets'], 'vs 7 j préc.') ?></div></div>
  <div class="stat"><div class="stat-top"><small>Conversion</small></div><b><?= $fun['rate'] === null ? '—' : e(number_format($fun['rate'], 1, ',', ' ')) . ' %' ?></b><div class="stat-sub"><?= number_format($fun['visitors'], 0, ',', ' ') ?> visiteurs · <?= (int)$fun['abandoned'] ?> paniers abandonnés</div></div>
  <div class="stat"><div class="stat-top"><small>Présence</small></div><b><?= $presence ?> %</b><div class="stat-sub"><?= $stats['checked'] ?> entrés · <?= $stats['inside'] ?> sur place</div></div>
</div>
<div class="split-3">
  <div>
    <div class="card">
      <div class="card-head"><h2>Remplissage</h2><small>Billets cumulés depuis l'ouverture des ventes</small></div>
      <?php if ($cum['values']): ?>
        <?= Viz::chart('area', ['labels' => $cum['labels'], 'xlabel' => 'Jour', 'series' => [['name' => 'Billets vendus (cumul)', 'values' => $cum['values']]], 'ref' => $cap && max($cum['values']) >= $cap * 0.3 ? ['value' => $cap, 'label' => 'Jauge ' . $cap] : null], 'Billets vendus cumulés', 'int', 250) ?>
        <?php if ($cap && max($cum['values']) < $cap * 0.3): ?><p class="muted mb0" style="font-size:13px">Jauge : <?= $cap ?> places (ligne affichée à partir de 30 % de remplissage).</p><?php endif; ?>
      <?php else: ?><p class="muted">Pas encore de vente.</p><?php endif; ?>
    </div>
    <div class="card"><div class="card-head"><h2>Par tarif</h2><a href="<?= url('/pro/evenements/' . $ev['id'] . '/tarifs') ?>">Gérer</a></div>
      <?php foreach ($stats['by_rate'] as $r): $q = (int)$r['quota']; ?>
        <div class="rate-row"><span class="rate-row-name"><b><?= e($r['name']) ?></b><small><?= (int)$r['price'] ? e(money((int)$r['price'])) : 'Gratuit' ?></small></span>
          <span class="rate-row-bar"><span class="bar"><i style="width:<?= $q ? min(100, round($r['sold'] / $q * 100)) : ($stats['sold'] ? round($r['sold'] / max(1, $stats['sold']) * 100) : 0) ?>%;background:<?= Viz::SERIES ?>"></i></span><small><?= (int)$r['sold'] ?><?= $q ? ' / ' . $q : '' ?> vendus · <?= (int)$r['checked'] ?> entrés</small></span>
          <span class="rate-row-amt"><?= e(money((int)$r['revenue'])) ?></span></div>
      <?php endforeach; ?>
    </div>
    <div class="card"><div class="card-head"><h2>Entonnoir</h2><small>Depuis la mise en vente</small></div><?= view('pro/_funnel', ['fun' => $fun]) ?></div>
  </div>
  <div>
    <div class="card">
      <div class="card-head"><h2>Préparation</h2><b class="score <?= $ready['score'] >= 100 ? 'ok' : '' ?>"><?= $ready['score'] ?> %</b></div>
      <ul class="checklist"><?php foreach ($ready['checks'] as [$l, $ok, $href]): ?><li class="<?= $ok ? 'ok' : '' ?>"><?= $ok ? '' : '' ?> <?= $ok ? e($l) : '<a href="' . url('/pro/evenements/' . $ev['id'] . $href) . '">' . e($l) . '</a>' ?></li><?php endforeach; ?></ul>
    </div>
    <div class="card"><div class="card-head"><h2>Dernières ventes</h2><span class="live-dot">live</span></div>
      <div class="feed" data-feed="<?= url('/pro/activite', ['ev' => $ev['id']]) ?>"><?= view('pro/_activity', ['activity' => $activity]) ?></div></div>
    <div class="card"><h2>Canaux</h2>
      <?php $labels = ['web' => 'Site', 'widget' => 'Widget', 'guichet' => 'Guichet', 'invitation' => 'Invitations', 'partenaire' => 'Partenaires', 'revente' => 'Revente', 'api' => 'API', 'partner' => 'Partenaires'];
      $mx = max(1, max(array_map(fn($c) => (int)$c['n'], $channels ?: [['n' => 1]]))); foreach ($channels as $c): ?>
        <div class="hbar"><span class="hbar-l"><?= e($labels[$c['c']] ?? $c['c']) ?></span><span class="hbar-b"><i style="width:<?= round($c['n'] / $mx * 100) ?>%;background:<?= Viz::SERIES ?>"></i></span><span class="hbar-v"><?= (int)$c['n'] ?> · <?= e(money((int)$c['t'])) ?></span></div>
      <?php endforeach; ?><?php if (!$channels): ?><p class="muted mb0">—</p><?php endif; ?>
    </div>
    <div class="card">
      <h2>Fais tourner</h2>
      <input readonly value="<?= e($link) ?>" onclick="this.select()">
      <div class="row mt"><button type="button" class="btn btn-sm btn-light" data-copy="<?= e($link) ?>">Copier</button><a class="btn btn-sm btn-light" href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($link) ?>" target="_blank" rel="noopener">Facebook</a><a class="btn btn-sm btn-light" href="https://wa.me/?text=<?= urlencode($ev['name'] . ' ' . $link) ?>" target="_blank" rel="noopener">WhatsApp</a></div>
      <div class="center mt"><?= Qr::svg($link . '?utm_source=affiche', 130) ?><br><small>QR code pour tes affiches (suivi « affiche » dans les stats)</small></div>
      <?php $w = '<iframe src="' . abs_url('/widget/e/' . $ev['slug']) . '" style="width:100%;max-width:480px;min-height:460px;border:0" loading="lazy" title="Billetterie"></iframe>'; ?>
      <div class="soon-row"><?= soon_btn('reseaux', 'Visuels réseaux sociaux') ?><?= soon_btn('partage-stats', 'Partager les stats') ?><?= soon_btn('rapport-fin', 'Rapport de fin') ?><?= soon_btn('mini-site', 'Mini-site') ?></div>
      <details class="mt"><summary class="linklike">Code du widget pour ton site</summary><textarea readonly rows="3" onclick="this.select()" class="mono mt" style="font-size:12px"><?= e($w) ?></textarea></details>
    </div>
  </div>
</div>
