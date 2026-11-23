<?php $left = (int)$pa['quota'] > 0 ? max(0, (int)$pa['quota'] - $used) : 50; ?>
<div class="auth-box wide card">
  <span class="eyebrow">🤝 Espace partenaire · <?= e($pa['name']) ?></span>
  <h1><?= e($ev['name']) ?></h1>
  <p class="muted"><?= e(fr_date($ev['starts_at'])) ?> · <?= e($ev['venue']) ?><br>Proposé par <?= e($org['name']) ?></p>
  <div class="notice mb">Tarif : <b><?= e($rate['name']) ?></b> — <?= $pa['free'] ? 'offert 🎁' : e(money((int)$rate['price'])) ?><?= (int)$pa['quota'] > 0 ? ' · ' . $left . ' place(s) restante(s) sur ton lot' : '' ?></div>
  <?php if ($left > 0): ?>
  <form method="post"><?= csrf_field() ?>
    <?php if ($sessions): ?><div class="field"><label>Date</label><select name="seance" required><?php foreach ($sessions as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e(fr_date($x['starts_at'])) ?><?= $x['label'] ? ' · ' . e($x['label']) : '' ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div class="field"><label>Nombre de billets</label><input type="number" name="qty" min="1" max="<?= min(50, $left) ?>" value="1"></div>
    <button class="btn btn-lg btn-block">Continuer →</button>
  </form>
  <?php else: ?><p class="flash warning">Ton lot est épuisé. Contacte l'organisateur pour en avoir plus.</p><?php endif; ?>
</div>
