<?php /** @var array $per, $cur, $prev, $todo, $top, $recent */ $P = mb_strtolower(Insights::PERIODS[$per['days']]); ?>
<p class="lead">Ta plateforme sur les <b><?= e($P) ?></b> (paiements réels en ligne uniquement<?= $demo ? ', ' . $demo . ' commande(s) de démo exclues' : '' ?>).</p>
<div class="stat-grid">
  <div class="stat"><div class="stat-top"><small>Tes commissions</small><?= Viz::sparkline($com) ?></div><b><?= e(money((int)$cur['com'])) ?></b><div class="stat-sub"><?= Viz::delta((float)$cur['com'], (float)$prev['com']) ?></div></div>
  <div class="stat"><div class="stat-top"><small>Volume encaissé</small><?= Viz::sparkline($gmv) ?></div><b><?= e(money((int)$cur['gmv'])) ?></b><div class="stat-sub"><?= (int)$cur['n'] ?> commandes · <?= Viz::delta((float)$cur['gmv'], (float)$prev['gmv']) ?></div></div>
  <div class="stat"><div class="stat-top"><small>Billets vendus</small></div><b><?= number_format((int)$cur['tickets'], 0, ',', ' ') ?></b><div class="stat-sub"><?= number_format($allTickets, 0, ',', ' ') ?> billets valides au total</div></div>
  <div class="stat"><div class="stat-top"><small>Organisateurs</small></div><b><?= (int)$cur['orgs'] ?> <small style="font-size:15px;color:var(--muted)">actifs</small></b><div class="stat-sub">+<?= $newOrgs ?> inscrit(s) · <?= Viz::delta((float)$newOrgs, (float)$newOrgsPrev, 'inscriptions') ?></div></div>
</div>
<div class="split-3">
  <div class="card">
    <div class="card-head"><h2>Commissions</h2><small><?= e($labels[0] ?? '') ?> → <?= e(end($labels) ?: '') ?></small></div>
    <?= Viz::chart('area', ['labels' => $labels, 'xlabel' => 'Jour', 'series' => [['name' => 'Commissions', 'values' => $com]]], 'Commissions par jour', 'money', 240) ?>
  </div>
  <div class="card">
    <div class="card-head"><h2>À faire</h2><?= $todo ? '<span class="count-pill">' . count($todo) . '</span>' : '' ?></div>
    <?php foreach ($todo as [$ic, $t, $sub, $href, $cls]): ?>
      <a class="todo<?= $cls ? ' ' . $cls : '' ?>" href="<?= url($href) ?>"><span class="todo-ic"><?= $ic ?></span><span><b><?= e($t) ?></b><small><?= e($sub) ?></small></span><i aria-hidden="true">→</i></a>
    <?php endforeach; ?>
    <?php if (!$todo): ?><div class="todo-empty">Tout roule sur la plateforme.</div><?php endif; ?>
  </div>
</div>
<div class="split-3 mt">
  <div class="card table-card">
    <div class="table-head-row"><h2 class="mb0">Top organisateurs (<?= e($P) ?>)</h2><a href="<?= url('/admin/organisateurs') ?>">Tous →</a></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Organisateur</th><th class="num">Commandes</th><th class="num">Volume</th><th class="num">Commission</th></tr></thead>
      <tbody><?php foreach ($top as $o): ?><tr data-href="<?= url('/admin/organisateurs/' . $o['id']) ?>"><td><a href="<?= url('/admin/organisateurs/' . $o['id']) ?>"><b><?= e($o['name']) ?></b></a></td><td class="num"><?= (int)$o['n'] ?></td><td class="num"><?= e(money((int)$o['gmv'])) ?></td><td class="num"><b><?= e(money((int)$o['com'])) ?></b></td></tr><?php endforeach; ?>
      <?php if (!$top): ?><tr><td colspan="4" class="empty">Pas de vente réelle sur la période.</td></tr><?php endif; ?></tbody>
    </table></div>
  </div>
  <div class="card">
    <div class="card-head"><h2>Derniers inscrits</h2></div>
    <?php foreach ($recent as $o): ?><a class="todo" href="<?= url('/admin/organisateurs/' . $o['id']) ?>"><span class="avatar-sm" style="--h:<?= crc32($o['name']) % 360 ?>"><?= e(mb_strtoupper(mb_substr($o['name'], 0, 1))) ?></span><span><b><?= e($o['name']) ?></b><small><?= e($o['email']) ?> · <?= e(ago($o['created_at'])) ?></small></span><?= badge($o['status']) ?></a><?php endforeach; ?>
  </div>
</div>
<div class="card mt">
  <div class="card-head"><h2>Annonce aux organisateurs</h2><small class="muted">Bandeau en haut de leur espace (chacun peut le masquer)</small></div>
  <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="announce">
    <textarea name="announce_text" rows="2" maxlength="600" placeholder="Ex. : Nouveau ! Les campagnes e-mail sont disponibles dans le menu « Campagnes »"><?= e((string)setting('announce_text')) ?></textarea>
    <div class="row" style="gap:8px;align-items:flex-end">
      <div class="field mb0"><label>Ton</label><select name="announce_level"><?php foreach (['info' => 'Info', 'success' => 'Nouveauté', 'warn' => 'Important'] as $k => $l): ?><option value="<?= $k ?>" <?= setting('announce_level', 'info') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="field mb0"><label>Jusqu'au (inclus)</label><input type="date" name="announce_until" value="<?= e(substr((string)setting('announce_until'), 0, 10)) ?>"></div>
      <button class="btn btn-sm">Publier</button><small class="muted">Vide = pas d'annonce.</small>
    </div>
  </form>
</div>
