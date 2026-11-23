<div class="steps"><span class="done">Billets</span><span class="done">Informations</span><span class="on">Paiement</span><span>Confirmation</span></div>
<div class="checkout">
  <form class="card" method="post" action="<?= url('/paiement/' . $o['ref'], ['t' => $o['token']]) ?>"><?= csrf_field() ?>
    <h2>Tu payes comment ? 💳</h2>
    <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
    <?php if (!$providers): ?>
      <div class="flash warning">Aucun moyen de paiement n'est branché pour l'instant 😬 Contacte l'organisateur.</div>
    <?php else: ?>
      <div class="pay-options">
        <?php $first = true; foreach ($providers as $k => $label): ?>
          <label class="pay-option"><input type="radio" name="provider" value="<?= e($k) ?>" <?= $first ? 'checked' : '' ?>> <?= e($label) ?></label>
        <?php $first = false; endforeach; ?>
      </div>
      <button class="btn btn-lg btn-block mt">Payer <?= e(money((int)$o['total'])) ?> →</button>
      <p class="muted center" style="font-size:13px;margin:10px 0 0">🔒 Direction la page sécurisée de paiement. On t'attend ici après.</p>
    <?php endif; ?>
  </form>
  <aside class="card">
    <h3><?= e($ev['name']) ?></h3>
    <p class="muted" style="font-size:14px">Commande <?= e($o['ref']) ?> · <?= e($o['first_name'] . ' ' . $o['last_name']) ?></p>
    <?php foreach ($items as $i): ?><div class="summary-line"><span><?= (int)$i['qty'] ?> × <?= e($i['name']) ?></span><span><?= e(money($i['qty'] * $i['unit_price'])) ?></span></div><?php endforeach; ?>
    <?php if ($o['discount']): ?><div class="summary-line" style="color:var(--green)"><span>Réduction</span><span>−<?= e(money((int)$o['discount'])) ?></span></div><?php endif; ?>
    <?php if ($o['fee_mode'] === 'buyer' && $o['fees']): ?><div class="summary-line"><span>Frais de service</span><span><?= e(money((int)$o['fees'])) ?></span></div><?php endif; ?>
    <div class="summary-line total"><span>Total</span><span><?= e(money((int)$o['total'])) ?></span></div>
    <small>⏱️ Places gardées pour toi jusqu'à <?= e(date('H\hi', strtotime($o['expires_at']))) ?>.</small>
  </aside>
</div>
