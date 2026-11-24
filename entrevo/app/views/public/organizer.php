<div class="card mb">
  <h1 class="mb0"><?= e($org['name']) ?></h1>
  <?php if ($rating['count']): ?><a class="org-rating" href="#avis"><span class="rv-stars" aria-hidden="true"><?= Buyers::stars($rating['avg']) ?></span> <b><?= e(number_format($rating['avg'], 1, ',', '')) ?></b>/5 · <?= $rating['count'] ?> avis de participants</a><?php endif; ?>
  <?php if ($org['description']): ?><p class="prose mt"><?= e($org['description']) ?></p><?php endif; ?>
  <?php if ($org['website']): ?><a href="<?= e($org['website']) ?>" target="_blank" rel="noopener"><?= e($org['website']) ?></a><?php endif; ?>
  <div class="soon-row"><?= pub_soon('suivre-orga', '🔔 Suivre cet organisateur') ?></div>
</div>
<h2>Événements à venir</h2>
<?php if (!$events): ?><div class="card empty">Aucun événement en vente pour le moment.</div>
<?php else: ?><div class="events-grid"><?php foreach ($events as $ev) echo view('partials/event_card', ['ev' => $ev]); ?></div><?php endif; ?>
<?php if ($reviews): ?>
<h2 id="avis">⭐ Ce qu'en disent les participants</h2>
<div class="rv-public">
<?php foreach ($reviews as $r): ?>
  <figure class="card rv-quote">
    <span class="rv-stars" aria-label="<?= (int)$r['rating'] ?> sur 5"><?= Buyers::stars((float)$r['rating']) ?></span>
    <blockquote><?= nl2br(e($r['comment'])) ?></blockquote>
    <figcaption><b><?= e($r['author'] ?: 'Un·e participant·e') ?></b><?= $r['verified'] ? ' · ✅ venu·e' : '' ?><br><small class="muted"><?= e($r['ev_name']) ?></small></figcaption>
  </figure>
<?php endforeach; ?>
</div>
<?php endif; ?>
