<div class="card mb">
  <h1 class="mb0"><?= e($org['name']) ?></h1>
  <?php if ($org['description']): ?><p class="prose mt"><?= e($org['description']) ?></p><?php endif; ?>
  <?php if ($org['website']): ?><a href="<?= e($org['website']) ?>" target="_blank" rel="noopener"><?= e($org['website']) ?></a><?php endif; ?>
  <div class="soon-row"><?= pub_soon('suivre-orga', '🔔 Suivre cet organisateur') ?><?= pub_soon('avis-public', '⭐ Avis des participants') ?></div>
</div>
<h2>Événements à venir</h2>
<?php if (!$events): ?><div class="card empty">Aucun événement en vente pour le moment.</div>
<?php else: ?><div class="events-grid"><?php foreach ($events as $ev) echo view('partials/event_card', ['ev' => $ev]); ?></div><?php endif; ?>
