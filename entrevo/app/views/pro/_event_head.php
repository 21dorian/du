<?php
$tabs = [
    'overview' => ['Vue d\'ensemble', ''], 'edit' => ['Informations', '/modifier'], 'rates' => ['Tarifs', '/tarifs'], 'form' => ['Formulaire', '/formulaire'],
    'promos' => ['Codes promo', '/promos'], 'attendees' => ['Participants', '/participants'], 'box' => ['Guichet', '/guichet'],
    'access' => ['Contrôle d\'accès', '/acces'], 'live' => ['En direct', '/controle'],
];
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
      <a class="btn btn-sm btn-ghost" href="<?= e($link) ?>" target="_blank">Voir la page ↗</a>
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
<nav class="tabs"><?php foreach ($tabs as $k => [$l, $p]): ?><a class="<?= $k === $tab ? 'on' : '' ?>" href="<?= url('/pro/evenements/' . $ev['id'] . $p) ?>"><?= e($l) ?></a><?php endforeach; ?></nav>
