<?php
/** 4.1 : colonne de l'événement (façon Weezticket) : en-tête, état, liens, actions, menu groupé. @var array $ev; string $tab */
$base = '/pro/evenements/' . $ev['id'];
$groups = [
    '' => ['', '', [['overview', 'Tableau de bord', ''], ['360', 'Vue 360', '/360']]],
    'config' => ['Configurer', 'settings', [['edit', 'Données de l\'événement', '/modifier'], ['rates', 'Tarifs et mise en vente', '/tarifs'], ['sessions', 'Dates & séances', '/dates'], ['products', 'Options & produits', '/options'],
        ['seating', 'Plan de salle', '/plan'], ['ticket', 'Visuel des billets', '/billet'], ['form', 'Formulaires', '/formulaire'], ['page', 'Page publique', '/page'], ['blocks', 'Page par blocs', '/blocs']]],
    'sell' => ['Vendre', 'cart', [['box', 'Guichet', '/guichet'], ['widget', 'Widget de vente', '/widget'], ['promos', 'Codes promo', '/promos'], ['waitlist', 'Attente & revente', '/attente'], ['links', 'Liens & ambassadeurs', '/liens']]],
    'comm' => ['Communiquer', 'megaphone', [['messages', 'Messages aux participants', '/messages'], ['invites', 'Invitations & partenaires', '/invitations'], ['visuals', 'Visuels réseaux sociaux', '/visuels'], ['share', 'Partage & QR code', '/partage']]],
    'people' => ['Participants', 'users', [['attendees', 'Liste des participants', '/participants'], ['accred', 'Accréditations', '/accreditations'], ['badges', 'Badges', '/badges-nominatifs']]],
    'orga' => ['Organiser', 'calendar', [['budget', 'Budget', '/budget'], ['tasks', 'Tâches', '/taches'], ['artists', 'Artistes', '/artistes'], ['staff', 'Bénévoles', '/benevoles'], ['docs', 'Documents', '/documents'], ['season', 'Saison', '/saison']]],
    'day' => ['Jour J', 'scan', [['access', 'Points d\'accès', '/acces'], ['live', 'Contrôle en direct', '/controle'], ['cloak', 'Vestiaire', '/vestiaire'], ['pc', 'PC sécurité', '/pc'], ['bars', 'Entrevo Pay', '/bars']]],
    'stats' => ['Analyser', 'chart', [['stats', 'Statistiques', '/stats'], ['report', 'Bilan PDF', '/rapport.pdf']]],
];
$cur = '';
foreach ($groups as $g => [, , $items]) foreach ($items as $it) if ($it[0] === $tab) $cur = $g;
$link = abs_url('/e/' . $ev['slug']);
$st = ['draft' => ['Brouillon', 'draft'], 'published' => ['Publié', 'pub'], 'closed' => ['Ventes clôturées', 'closed']][$ev['status']] ?? [$ev['status'], 'draft'];
$onSaleAt = null;
if ($ev['status'] === 'published' && !empty($ev['on_sale_at']) && strtotime((string)$ev['on_sale_at']) > time()) $onSaleAt = $ev['on_sale_at'];
?>
<aside class="evnav" aria-label="Menu de l'événement">
  <div class="evnav-head">
    <small class="evnav-kicker">Mon événement · E<?= str_pad((string)$ev['id'], 6, '0', STR_PAD_LEFT) ?></small>
    <a class="evnav-title" href="<?= url($base) ?>"><?= e($ev['name']) ?></a>
    <span class="evnav-state <?= $st[1] ?>"><?= e($st[0]) ?></span><?php if ($onSaleAt): ?><span class="evnav-state soon">Ouverture <?= e(short_date($onSaleAt)) ?></span><?php endif; ?>
    <p class="evnav-date"><?= e(fr_date($ev['starts_at'])) ?><?= $ev['venue'] ? '<br>' . e($ev['venue']) : '' ?></p>
    <div class="evnav-links">
      <a href="<?= e($link) ?>" target="_blank" rel="noopener">Page publique <?= ui_icon('link', 14) ?></a>
      <button type="button" class="linklike" data-copy="<?= e($link) ?>" title="Copier le lien">Copier</button>
      <a href="<?= url($base . '/page') ?>" title="Modifier la page">Modifier</a>
    </div>
    <div class="evnav-actions">
      <form method="post" action="<?= url($base . '/statut') ?>" class="grow"><?= csrf_field() ?>
        <?php if ($ev['status'] === 'published'): ?>
          <button class="btn btn-sm btn-light btn-block" name="status" value="draft" data-confirm="Repasser en brouillon ? La page ne sera plus visible et les ventes s'arrêtent.">Repasser en brouillon</button>
        <?php else: ?>
          <button class="btn btn-sm btn-block" name="status" value="published"><?= $ev['status'] === 'closed' ? 'Rouvrir les ventes' : 'Publier' ?></button>
        <?php endif; ?>
      </form>
      <details class="menu evnav-more"><summary class="btn btn-sm btn-light" aria-label="Plus d'actions">•••</summary><div class="menu-list">
        <?php if ($ev['status'] === 'published'): ?><form method="post" action="<?= url($base . '/statut') ?>"><?= csrf_field() ?><button class="linklike" name="status" value="closed" data-confirm="Clôturer les ventes ? Les billets vendus restent valables.">Clôturer les ventes</button></form><?php endif; ?>
        <a href="<?= url($base . '/tarifs#lancement') ?>">Programmer l'ouverture des ventes</a>
        <a href="<?= url($base . '/dupliquer') ?>">Dupliquer / reprendre</a>
        <a href="<?= url($base . '/saison') ?>">Créer une saison</a>
        <a href="<?= url($base . '/partage') ?>">Partager les chiffres</a>
        <a href="<?= url($base . '/rapport.pdf') ?>" target="_blank">Bilan PDF</a>
        <a href="<?= url('/scan') ?>" target="_blank">Ouvrir le scan</a>
      </div></details>
    </div>
  </div>
  <nav class="evnav-menu">
    <?php foreach ($groups as $g => [$gl, $gi, $items]): ?>
      <?php if ($gl === ''): foreach ($items as [$k, $l, $p]): ?>
        <a class="evnav-top <?= $k === $tab ? 'on' : '' ?>" href="<?= url($base . $p) ?>"><?= ui_icon($k === 'overview' ? 'sparkles' : 'chart', 18) ?><span><?= e($l) ?></span></a>
      <?php endforeach; else: ?>
        <details class="evnav-sec" <?= $g === $cur || in_array($g, ['config', 'sell'], true) ? 'open' : '' ?>>
          <summary><?= ui_icon($gi, 18) ?><span class="grow"><?= e($gl) ?></span><i aria-hidden="true">▾</i></summary>
          <?php foreach ($items as [$k, $l, $p]): ?><a class="<?= $k === $tab ? 'on' : '' ?>" href="<?= url($base . $p) ?>" <?= in_array($k, ['pc', 'report'], true) ? 'target="_blank"' : '' ?>><?= e($l) ?></a><?php endforeach; ?>
        </details>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
</aside>
