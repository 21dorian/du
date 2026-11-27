<?php
/** @var array $list, $tabs, $counts */
$qs = array_filter(['f' => $filter !== 'avenir' ? $filter : null, 'q' => $q ?: null, 'tri' => $sort !== 'date' ? $sort : null]);
$cap = fn($ev) => (int)$ev['capacity'] ?: (int)$ev['sess_cap'];
?>
<nav class="pill-tabs" aria-label="Filtrer les événements">
  <?php foreach ($tabs as $k => $l): ?><a class="<?= $filter === $k ? 'on' : '' ?>" href="<?= url('/pro/evenements', array_filter(['f' => $k !== 'avenir' ? $k : null, 'q' => $q ?: null])) ?>"><?= e($l) ?> <small><?= (int)$counts[$k] ?></small></a><?php endforeach; ?>
</nav>
<form class="filters card" method="get">
  <?php if ($filter !== 'avenir'): ?><input type="hidden" name="f" value="<?= e($filter) ?>"><?php endif; ?>
  <div class="filters-main">
    <label class="search-field"><span aria-hidden="true"></span><input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, lieu, ville…" aria-label="Rechercher un événement"></label>
    <select name="tri" aria-label="Trier par" onchange="this.form.submit()"><?php foreach (['date' => 'Trier par date', 'ventes' => 'Les plus vendus', 'recettes' => 'Les plus rentables', 'nom' => 'Ordre alphabétique'] as $k => $l): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm">Filtrer</button>
    <span class="grow"></span>
    <div class="seg-mini" role="group" aria-label="Affichage">
      <a class="<?= $view === 'cartes' ? 'on' : '' ?>" href="<?= url('/pro/evenements', $qs + ['vue' => 'cartes']) ?>">▦ Cartes</a>
      <a class="<?= $view === 'liste' ? 'on' : '' ?>" href="<?= url('/pro/evenements', $qs + ['vue' => 'liste']) ?>">Liste</a>
    </div>
  </div>
</form>

<?php if ($view === 'cartes'): ?>
<div class="evg">
  <?php foreach ($list as $ev): $c = $cap($ev); $pct = $c ? min(100, round($ev['sold'] / $c * 100)) : null; $goal = (int)($ev['revenue_goal'] ?? 0);
    $ts = strtotime($ev['starts_at']); $link = abs_url('/e/' . $ev['slug']); $past = strtotime((string)($ev['ends_at'] ?: $ev['starts_at'])) < time() - 6 * 3600; ?>
    <article class="evg-card">
      <a class="evg-img" href="<?= url('/pro/evenements/' . $ev['id']) ?>">
        <?php if ($ev['image']): ?><img src="<?= url($ev['image']) ?>" alt="" loading="lazy"><?php else: ?><span class="evg-noimg"><small>Pas d'affiche</small></span><?php endif; ?>
        <span class="evg-date"><b><?= date('j', $ts) ?></b><?= e(['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'][(int)date('n', $ts)]) ?></span>
        <span class="evg-status"><?= badge($ev['status']) ?></span>
        <?php if (!$past && $ts > time()): ?><span class="evg-count"><?= e(ago_until($ts)) ?></span><?php endif; ?>
      </a>
      <div class="evg-body">
        <a class="evg-title" href="<?= url('/pro/evenements/' . $ev['id']) ?>"><?= e($ev['name']) ?></a>
        <small class="muted"><?= e(fr_date($ev['starts_at'])) ?><?= (int)$ev['sess_n'] > 1 ? ' · ' . (int)$ev['sess_n'] . ' dates' : '' ?><?= $ev['venue'] ? ' · ' . e($ev['venue']) : '' ?></small>
        <div class="evg-fill"><div class="goal-bar"><i style="width:<?= (int)($pct ?? 0) ?>%"></i></div>
          <span><b><?= (int)$ev['sold'] ?></b><?= $c ? ' / ' . $c : '' ?> billets<?= $pct !== null ? ' · ' . $pct . ' %' : '' ?></span></div>
        <div class="evg-kpis">
          <span><small>Recettes</small><b><?= e(money((int)$ev['revenue'])) ?></b></span>
          <span><small>7 jours</small><b><?= (int)$ev['week'] ? '+' . (int)$ev['week'] : '0' ?></b></span>
          <span><small><?= $past ? 'Venus' : ($goal ? 'Objectif' : 'Entrés') ?></small><b><?= $past || !$goal ? (int)$ev['checked'] : min(999, round($ev['revenue'] / max(1, $goal) * 100)) . ' %' ?></b></span>
        </div>
      </div>
      <div class="evg-actions">
        <a class="btn btn-sm" href="<?= url('/pro/evenements/' . $ev['id']) ?>">Gérer</a>
        <a class="icon-act" href="<?= e($link) ?>" target="_blank" title="Voir la page publique" aria-label="Voir la page publique"></a>
        <button type="button" class="icon-act" data-copy="<?= e($link) ?>" title="Copier le lien" aria-label="Copier le lien"></button>
        <a class="icon-act" href="<?= url('/pro/evenements/' . $ev['id'] . '/guichet') ?>" title="Guichet" aria-label="Guichet"></a>
        <a class="icon-act" href="<?= url('/pro/evenements/' . $ev['id'] . '/stats') ?>" title="Statistiques" aria-label="Statistiques"></a>
        <form method="post" action="<?= url('/pro/evenements/' . $ev['id'] . '/dupliquer') ?>" class="inline-form"><?= csrf_field() ?><button class="icon-act" title="Dupliquer" aria-label="Dupliquer" data-confirm="Dupliquer « <?= e($ev['name']) ?> » (tarifs, formulaire et accès compris) ?"></button></form>
      </div>
    </article>
  <?php endforeach; ?>
  <a class="evg-new" href="<?= url('/pro/evenements/nouveau') ?>"><span></span><b>Nouvel événement</b><small>5 minutes chrono</small></a>
</div>
<?php if (!$list): ?><p class="muted center"><?= $q !== '' ? 'Rien ne correspond à « ' . e($q) . ' ».' : ($filter === 'passes' ? 'Pas encore d\'historique.' : 'Rien par ici') ?></p><?php endif; ?>

<?php else: ?>
<div class="card table-card">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Événement</th><th>Date</th><th>Statut</th><th class="num">Vendus</th><th class="num">Remplissage</th><th class="num">Entrés</th><th class="num">Recettes</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $ev): $c = $cap($ev); $pct = $c ? min(100, round($ev['sold'] / $c * 100)) : null; ?>
      <tr data-href="<?= url('/pro/evenements/' . $ev['id']) ?>">
        <td><div class="ev-head u-m0"><div class="ev-thumb" style="width:64px;height:42px"><?php if ($ev['image']): ?><img src="<?= url($ev['image']) ?>" alt=""><?php endif; ?></div>
          <div><a href="<?= url('/pro/evenements/' . $ev['id']) ?>"><b><?= e($ev['name']) ?></b></a><br><small><?= e(trim($ev['venue'] . ' ' . $ev['city'])) ?></small></div></div></td>
        <td class="nowrap"><?= e(fr_date($ev['starts_at'])) ?></td>
        <td><?= badge($ev['status']) ?></td>
        <td class="num"><?= (int)$ev['sold'] ?><?= $c ? '<small> / ' . $c . '</small>' : '' ?></td>
        <td class="num"><?php if ($pct !== null): ?><span class="mini-meter"><i style="width:<?= $pct ?>%;background:var(--primary)"></i></span> <small><?= $pct ?> %</small><?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td class="num"><?= (int)$ev['checked'] ?></td>
        <td class="num"><?= e(money((int)$ev['revenue'])) ?></td>
        <td class="right nowrap"><a class="btn btn-sm btn-ghost" href="<?= url('/pro/evenements/' . $ev['id']) ?>">Gérer</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="8" class="empty"><?= $filter === 'passes' ? 'Pas encore d\'historique.' : 'Le calendrier est vide' ?> <a href="<?= url('/pro/evenements/nouveau') ?>">Créer un événement</a></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
