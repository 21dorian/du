<?php
// 2.2 : onglets regroupés par rubrique (ligne 1 = rubriques, ligne 2 = pages de la rubrique)
$groups = [
    'apercu' => ['Vue d\'ensemble', ['overview' => ['Vue d\'ensemble', ''], '360' => ['Vue 360', '/360']]],
    'vendre' => ['Vendre', ['rates' => ['Tarifs', '/tarifs'], 'sessions' => ['Dates', '/dates'], 'products' => ['Options', '/options'], 'promos' => ['Codes promo', '/promos'],
        'seating' => ['Plan de salle', '/plan'], 'waitlist' => ['Attente & revente', '/attente'], 'box' => ['Guichet', '/guichet']]],
    'communiquer' => ['Communiquer', ['page' => ['Page publique', '/page'], 'form' => ['Formulaire', '/formulaire'], 'links' => ['Liens & ambassadeurs', '/liens'], 'visuals' => ['Visuels', '/visuels'], 'messages' => ['Messages', '/messages']]],
    'public' => ['Participants', ['attendees' => ['Participants', '/participants'], 'invites' => ['Invitations & partenaires', '/invitations'], 'accred' => ['Accréditations', '/accreditations']]],
    'organiser' => ['Organiser', ['edit' => ['Infos & réglages', '/modifier'], 'budget' => ['Budget', '/budget'], 'tasks' => ['Tâches', '/taches'], 'artists' => ['Artistes', '/artistes'], 'staff' => ['Bénévoles', '/benevoles'], 'docs' => ['Documents', '/documents']]],
    'jourj' => ['Jour J', ['access' => ['Points d\'accès', '/acces'], 'live' => ['En direct', '/controle'], 'bars' => ['Entrevo Pay (cashless & bars) ↗', '/bars'], 'pc' => ['PC sécurité', '/pc']]],
    'stats' => ['Stats', ['stats' => ['Statistiques', '/stats']]],
];
$cur = 'apercu';
foreach ($groups as $g => [, $items]) if (isset($items[$tab])) $cur = $g;
$link = abs_url('/e/' . $ev['slug']);
?>
<div class="card" style="padding:16px 20px;margin-bottom:18px">
  <div class="row between">
    <div class="ev-head grow" style="margin:0">
      <div class="ev-thumb"><?php if ($ev['image']): ?><img src="<?= url($ev['image']) ?>" alt=""><?php endif; ?></div>
      <div style="min-width:0">
        <div class="row" style="gap:8px"><b style="font-size:17px"><?= e($ev['name']) ?></b><?= badge($ev['status']) ?></div>
        <div class="muted" style="font-size:14px"><?= e(fr_date($ev['starts_at'])) ?><?= $ev['venue'] ? ' · ' . e($ev['venue']) : '' ?></div>
      </div>
    </div>
    <div class="row">
      <a class="btn btn-sm btn-ghost" href="<?= e($link) ?>" target="_blank">Voir la page</a>
      <button type="button" class="btn btn-sm btn-ghost" data-copy="<?= e($link) ?>">Copier le lien</button>
      <form method="post" action="<?= url('/pro/evenements/' . $ev['id'] . '/statut') ?>" class="inline-form"><?= csrf_field() ?>
        <?php if ($ev['status'] === 'published'): ?>
          <button class="btn btn-sm btn-ghost" name="status" value="closed" data-confirm="Clôturer les ventes ? Les billets déjà vendus restent valables.">Clôturer les ventes</button>
        <?php else: ?>
          <button class="btn btn-sm btn-success" name="status" value="published"><?= $ev['status'] === 'closed' ? 'Rouvrir les ventes' : 'Publier' ?></button>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>
<nav class="ev-nav" aria-label="Rubriques de l'événement">
  <div class="ev-nav-groups"><?php foreach ($groups as $g => [$gl, $items]): $first = reset($items); ?><a class="<?= $g === $cur ? 'on' : '' ?>" href="<?= url('/pro/evenements/' . $ev['id'] . $first[1]) ?>"><?= e($gl) ?></a><?php endforeach; ?></div>
  <?php if (count($groups[$cur][1]) > 1): ?><div class="ev-nav-sub"><?php foreach ($groups[$cur][1] as $k => [$l, $p]): ?><a class="<?= $k === $tab ? 'on' : '' ?>" href="<?= url('/pro/evenements/' . $ev['id'] . $p) ?>" <?= $k === 'pc' ? 'target="_blank"' : '' ?>><?= e($l) ?></a><?php endforeach; ?></div><?php endif; ?>
</nav>
