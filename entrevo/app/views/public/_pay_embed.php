<div class="dw-pay">
  <div class="dw-timer" data-expires="<?= e(date('c', strtotime((string)$o['expires_at']))) ?>">⏱️ Tes places sont gardées <b data-left>--:--</b></div>
  <?php if (!$providers): ?>
    <div class="flash warning">Aucun moyen de paiement n'est branché pour l'instant 😬 Contacte l'organisateur.</div>
  <?php else: ?>
  <form method="post" action="<?= url('/paiement/' . $o['ref'], ['t' => $o['token']]) ?>" class="dw-pay-form"><?= csrf_field() ?>
    <div class="dw-providers">
      <?php $first = true; foreach ($providers as $k => $label): ?>
        <label class="dw-provider"><input type="radio" name="provider" value="<?= e($k) ?>" <?= $first ? 'checked' : '' ?>><span><?= ['stripe' => '💳', 'paypal' => '🅿️', 'sumup' => '💳', 'mollie' => '🏦', 'demo' => '🧪'][$k] ?? '💳' ?> <?= e($label) ?></span></label>
      <?php $first = false; endforeach; ?>
    </div>
    <button class="btn btn-lg btn-block dw-paybtn">Payer <?= e(money((int)$o['total'])) ?> →</button>
  </form>
  <?php endif; ?>
  <p class="dw-secure">🔒 Paiement sécurisé chez notre prestataire. Commande <b><?= e($o['ref']) ?></b> au nom de <?= e($o['first_name'] . ' ' . $o['last_name']) ?>.</p>
</div>
