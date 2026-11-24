<div class="store-head">
  <span class="eyebrow"><?= e($org['name']) ?></span>
  <h1>Abonnements</h1>
  <p class="lead">Réserve ta place à chaque événement en un clic, sans repayer. Ton billet arrive par e-mail comme d'habitude.</p>
</div>
<?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" class="stack"><?= csrf_field() ?>
  <div class="pass-grid">
    <?php foreach ($types as $i => $t): $full = $t['left'] === 0; ?>
      <label class="pass-card<?= $full ? ' full' : '' ?>">
        <input type="radio" name="pass_type" value="<?= (int)$t['id'] ?>" <?= ($sel ? $sel === (int)$t['id'] : $i === 0) && !$full ? 'checked' : '' ?> <?= $full ? 'disabled' : '' ?>>
        <span class="pass-kind"><?= $t['kind'] === 'carnet' ? 'Carnet ' . (int)$t['entries'] . ' entrées' : 'Pass saison' ?></span>
        <b class="pass-name"><?= e($t['name']) ?></b>
        <span class="pass-price"><?= e(money((int)$t['price'])) ?></span>
        <?php if ($t['description']): ?><span class="pass-desc"><?= e($t['description']) ?></span><?php endif; ?>
        <span class="pass-ev"><?= count($t['events']) ?> événement<?= count($t['events']) > 1 ? 's' : '' ?> compris<?= $t['valid_until'] ? ' · jusqu\'au ' . e(date('d/m/Y', strtotime($t['valid_until']))) : '' ?></span>
        <?php if ($t['events']): ?><ul class="pass-list"><?php foreach (array_slice($t['events'], 0, 4) as $ev): ?><li><?= e(short_date($ev['starts_at'])) ?> · <?= e($ev['name']) ?></li><?php endforeach; ?><?= count($t['events']) > 4 ? '<li class="muted">et ' . (count($t['events']) - 4) . ' autres</li>' : '' ?></ul><?php endif; ?>
        <?php if ($full): ?><span class="badge grey">Complet</span><?php elseif ($t['left'] !== null && $t['left'] <= 20): ?><span class="badge orange">Plus que <?= (int)$t['left'] ?></span><?php endif; ?>
      </label>
    <?php endforeach; ?>
  </div>
  <div class="card"><h2>Tes coordonnées</h2><?= view('partials/buyer_fields') ?><button class="btn btn-lg">Continuer vers le paiement</button></div>
</form>
