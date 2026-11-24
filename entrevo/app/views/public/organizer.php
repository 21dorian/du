<?php $socIcons = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'spotify' => 'Spotify', 'linkedin' => 'LinkedIn']; ?>
<section class="org-hero<?= !empty($org['cover']) ? ' has-cover' : '' ?>">
  <?php if (!empty($org['cover'])): ?><div class="org-cover"><img src="<?= url($org['cover']) ?>" alt=""></div><?php endif; ?>
  <div class="org-id">
    <div class="org-logo"><?php if ($org['logo']): ?><img src="<?= url($org['logo']) ?>" alt="<?= e($org['name']) ?>"><?php else: ?><span><?= e(mb_strtoupper(mb_substr($org['name'], 0, 1))) ?></span><?php endif; ?></div>
    <div class="org-title">
      <h1><?= e($org['name']) ?></h1>
      <?php if (!empty($org['tagline'])): ?><p class="org-tagline"><?= e($org['tagline']) ?></p><?php endif; ?>
      <div class="org-meta">
        <?php if ($rating['count']): ?><a class="org-rating" href="#avis"><span class="rv-stars" aria-hidden="true"><?= Buyers::stars($rating['avg']) ?></span> <b><?= e(number_format($rating['avg'], 1, ',', '')) ?></b> · <?= $rating['count'] ?> avis</a><?php endif; ?>
        <span><?= (int)$figures['events'] ?> événement<?= $figures['events'] > 1 ? 's' : '' ?></span>
        <?php if ((int)$figures['tickets'] >= 50): ?><span><?= number_format((int)$figures['tickets'], 0, ',', ' ') ?> billets émis</span><?php endif; ?>
      </div>
    </div>
    <div class="org-links">
      <?php foreach ($socials as $k => $u): ?><a class="btn btn-sm btn-light" href="<?= e($u) ?>" target="_blank" rel="noopener"><?= $socIcons[$k] ?? e($k) ?></a><?php endforeach; ?>
      <?php if ($org['website']): ?><a class="btn btn-sm btn-light" href="<?= e($org['website']) ?>" target="_blank" rel="noopener">Site web</a><?php endif; ?>
      <?= pub_soon('suivre-orga', 'Suivre', 'btn btn-sm') ?>
    </div>
  </div>
  <?php if ($org['description']): ?><p class="org-about"><?= nl2br(e($org['description'])) ?></p><?php endif; ?>
</section>
<h2 class="org-h">À venir</h2>
<?php if (!$events): ?><div class="card empty">Aucun événement en vente pour le moment.</div>
<?php else: ?><div class="events-grid"><?php foreach ($events as $ev) echo view('partials/event_card', ['ev' => $ev]); ?></div><?php endif; ?>
<?php if ($past): ?>
<h2 class="org-h">Déjà passés</h2>
<div class="events-grid past-grid"><?php foreach ($past as $ev) echo view('partials/event_card', ['ev' => $ev]); ?></div>
<?php endif; ?>
<?php if ($reviews): ?>
<h2 id="avis" class="org-h">Ce qu'en disent les participants</h2>
<div class="rv-public">
<?php foreach ($reviews as $r): ?>
  <figure class="card rv-quote">
    <span class="rv-stars" aria-label="<?= (int)$r['rating'] ?> sur 5"><?= Buyers::stars((float)$r['rating']) ?></span>
    <blockquote><?= nl2br(e($r['comment'])) ?></blockquote>
    <figcaption><b><?= e($r['author'] ?: 'Un·e participant·e') ?></b><?= $r['verified'] ? ' · venu·e' : '' ?><br><small class="muted"><?= e($r['ev_name']) ?></small></figcaption>
    <?php if ($r['reply']): ?><div class="rv-org-reply"><b><?= e($org['name']) ?></b><p><?= nl2br(e($r['reply'])) ?></p></div><?php endif; ?>
  </figure>
<?php endforeach; ?>
</div>
<?php endif; ?>
