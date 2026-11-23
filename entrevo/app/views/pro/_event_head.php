<?php
$tabs = [
    'overview' => ['Vue d\'ensemble', ''], 'edit' => ['Infos & réglages', '/modifier'], 'sessions' => ['Dates', '/dates'], 'rates' => ['Tarifs', '/tarifs'],
    'products' => ['Options', '/options'], 'form' => ['Formulaire', '/formulaire'], 'promos' => ['Codes promo', '/promos'], 'seating' => ['Plan de salle', '/plan'],
    'attendees' => ['Participants', '/participants'], 'invites' => ['Invitations & partenaires', '/invitations'], 'accred' => ['Accréditations', '/accreditations'],
    'box' => ['Guichet', '/guichet'], 'waitlist' => ['Attente & revente', '/attente'], 'messages' => ['Messages', '/messages'],
    'access' => ['Contrôle d\'accès', '/acces'], 'live' => ['En direct', '/controle'], 'stats' => ['Stats', '/stats'],
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
      <button type="button" class="btn btn-sm btn-ghost" data-copy="<?= e($link) ?>">🔗 Copier le lien</button>
      <form method="post" action="<?= url('/pro/evenements/' . $ev['id'] . '/statut') ?>" class="inline-form"><?= csrf_field() ?>
        <?php if ($ev['status'] === 'published'): ?>
          <button class="btn btn-sm btn-ghost" name="status" value="closed" data-confirm="Clôturer les ventes ? Les billets déjà vendus restent valables.">Clôturer les ventes</button>
        <?php else: ?>
          <button class="btn btn-sm btn-success" name="status" value="published"><?= $ev['status'] === 'closed' ? 'Rouvrir les ventes' : 'Publier 🚀' ?></button>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>
<nav class="tabs"><?php foreach ($tabs as $k => [$l, $p]): ?><a class="<?= $k === $tab ? 'on' : '' ?>" href="<?= url('/pro/evenements/' . $ev['id'] . $p) ?>"><?= e($l) ?></a><?php endforeach; ?>
  <?php foreach (['mini-site' => 'Mini-site', 'benevoles' => 'Bénévoles', 'avis' => 'Avis'] as $k => $l): ?><a class="tab-soon" href="<?= url('/pro/bientot/' . $k) ?>"><?= e($l) ?> <span class="soon-pill">Bientôt</span></a><?php endforeach; ?></nav>
