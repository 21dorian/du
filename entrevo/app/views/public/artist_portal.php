<?php /** @var array $a, $ev, $org, $perfs */ ?>
<section class="store-wrap u-mw760">
  <span class="eyebrow"><?= e($org['name']) ?> · espace artiste</span>
  <h1><?= e($a['name']) ?></h1>
  <p class="lead"><?= e($ev['name']) ?> · <?= e(fr_date($ev['starts_at'])) ?></p>
  <div class="card"><h2>Tes horaires</h2>
    <?php foreach ($perfs as $p): ?><div class="artist-slot">
      <div><small class="muted">Scène</small><b><?= e($p['stage']) ?></b></div>
      <?php if ($p['arrival_at']): ?><div><small class="muted">Arrivée</small><b><?= e(date('H\hi', strtotime($p['arrival_at']))) ?></b></div><?php endif; ?>
      <?php if ($p['soundcheck_at']): ?><div><small class="muted">Balance</small><b><?= e(date('H\hi', strtotime($p['soundcheck_at']))) ?></b></div><?php endif; ?>
      <div><small class="muted">Passage · <?= e(date('d/m', strtotime($p['starts_at']))) ?></small><b><?= e(date('H\hi', strtotime($p['starts_at']))) ?> – <?= e(date('H\hi', strtotime($p['ends_at']))) ?></b></div>
      <?php if ($p['notes']): ?><p class="muted mb0" style="grid-column:1/-1;font-size:14px"><?= e($p['notes']) ?></p><?php endif; ?>
    </div><?php endforeach; ?>
    <?php if (!$perfs): ?><p class="muted mb0">Les horaires arrivent bientôt.</p><?php endif; ?>
    <p class="mt mb0"><a class="btn btn-light" href="<?= url('/artiste/' . $a['token'], ['pdf' => 1]) ?>" target="_blank">Feuille de route PDF</a></p>
  </div>
  <div class="card"><h2>Accès</h2><p class="mb0"><b><?= e((string)$ev['venue']) ?></b><?= ($ev['address'] ?? '') ? '<br>' . e($ev['address']) : '' ?><?= $ev['city'] ? '<br>' . e($ev['city']) : '' ?></p>
    <?php if ($ev['venue'] || $ev['city']): ?><p class="mt mb0"><a href="https://www.google.com/maps/search/?api=1&query=<?= rawurlencode(trim(($ev['venue'] ?? '') . ' ' . ($ev['address'] ?? '') . ' ' . ($ev['city'] ?? ''))) ?>" target="_blank" rel="noopener">Itinéraire →</a></p><?php endif; ?></div>
  <?php if ($a['hospitality']): ?><div class="card"><h2>Accueil</h2><p class="mb0"><?= nl2br(e($a['hospitality'])) ?></p></div><?php endif; ?>
  <?php if ($a['rider']): ?><div class="card"><h2>Ce qui est prévu techniquement</h2><p class="mb0"><?= nl2br(e($a['rider'])) ?></p></div><?php endif; ?>
  <div class="card"><h2>Contact organisation</h2><p class="mb0"><?= e($org['name']) ?><?= $org['phone'] ? ' · <a href="tel:' . e(preg_replace('~\s~', '', (string)$org['phone'])) . '">' . e($org['phone']) . '</a>' : '' ?><?= $org['email'] ? ' · <a href="mailto:' . e($org['email']) . '">' . e($org['email']) . '</a>' : '' ?></p></div>
</section>
