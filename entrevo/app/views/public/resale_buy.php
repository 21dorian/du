<div class="checkout">
  <form class="card" method="post"><?= csrf_field() ?>
    <span class="eyebrow">🔁 Bourse aux billets officielle</span>
    <h1><?= e($ev['name']) ?></h1>
    <?php if ($errors): ?><div class="flash error"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
    <p class="muted">Ce billet est revendu par un autre spectateur au prix d'origine. Après ton paiement, son QR code est annulé et un <b>nouveau billet à ton nom</b> t'est envoyé. 100 % légal, 0 % arnaque.</p>
    <div class="grid2">
      <div class="field"><label>Prénom *</label><input name="first_name" value="<?= e(input('first_name')) ?>" required></div>
      <div class="field"><label>Nom *</label><input name="last_name" value="<?= e(input('last_name')) ?>" required></div>
      <div class="field" style="grid-column:1/-1"><label>E-mail *</label><input type="email" name="email" value="<?= e(input('email')) ?>" required></div>
    </div>
    <?php if (setting('cgv')): ?><label class="check mb"><input type="checkbox" name="cgv" value="1" required> J'accepte les <a href="<?= url('/cgv') ?>" target="_blank">CGV</a>.</label><?php endif; ?>
    <button class="btn btn-lg btn-block">Payer <?= e(money((int)$x['price'] + $fee)) ?> →</button>
  </form>
  <aside class="card">
    <h3><?= e($x['rate_name']) ?></h3>
    <?php if ($x['seat']): ?><p class="ticket-seat">💺 <?= e(Seating::label($x['seat'], Seating::map($ev))) ?></p><?php endif; ?>
    <div class="summary-line"><span>Billet (prix d'origine)</span><span><?= e(money((int)$x['price'])) ?></span></div>
    <?php if ($fee): ?><div class="summary-line"><span>Frais de service</span><span><?= e(money($fee)) ?></span></div><?php endif; ?>
    <div class="summary-line total"><span>Total</span><span><?= e(money((int)$x['price'] + $fee)) ?></span></div>
  </aside>
</div>
