<div class="steps"><span class="done">Billets</span><span class="done">Informations</span><span class="on">Paiement</span><span>Confirmation</span></div>
<div class="checkout">
  <form class="card" method="post" action="<?= url('/paiement/' . $o['ref'], ['t' => $o['token']]) ?>"><?= csrf_field() ?>
    <h2>Tu payes comment ?</h2>
    <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
    <?php if (!$providers): ?>
      <div class="flash warning">Aucun moyen de paiement n'est branché pour l'instant Contacte l'organisateur.</div>
    <?php else: ?>
      <div class="pay-options">
        <?php $first = true; foreach ($providers as $k => $label): ?>
          <label class="pay-option"><input type="radio" name="provider" value="<?= e($k) ?>" <?= $first ? 'checked' : '' ?>> <?= e($label) ?>
            <?php if ($k === 'alma'): ?><span class="alma-plans"><?php foreach (Payments::almaCounts() as $ci => $nb): $part = (int)ceil((int)$o['total'] / $nb); ?><label><input type="radio" name="installments" value="<?= $nb ?>" <?= $ci === 0 ? 'checked' : '' ?>> <?= $nb ?> × <?= e(money($part)) ?></label><?php endforeach; ?><small>Sans frais · 1re échéance aujourd'hui, puis chaque mois · billets envoyés tout de suite</small></span><?php endif; ?>
            <?php if ($k === 'stripe' && setting('stripe_klarna') === '1' && (int)$o['total'] >= 3500): ?><small class="muted u-block">Carte, Apple Pay, Google Pay ou Klarna en 3 fois</small><?php endif; ?></label>
        <?php $first = false; endforeach; ?>
      </div>
      <button class="btn btn-lg btn-block mt">Payer <?= e(money((int)$o['total'])) ?> →</button>
      <p class="muted center" style="font-size:13px;margin:10px 0 0">Direction la page sécurisée de paiement. On t'attend ici après.</p>
    <?php endif; ?>
  </form>
  <aside class="card">
    <h3><?= e($ev['name']) ?></h3>
    <p class="muted u-fs14">Commande <?= e($o['ref']) ?> · <?= e($o['first_name'] . ' ' . $o['last_name']) ?></p>
    <?php foreach ($items as $i): ?><div class="summary-line"><span><?= (int)$i['qty'] ?> × <?= e($i['name']) ?></span><span><?= e(money($i['qty'] * $i['unit_price'])) ?></span></div><?php endforeach; ?>
    <?php if ($o['discount']): ?><div class="summary-line u-green"><span><?= (int)($o['group_discount'] ?? 0) >= (int)$o['discount'] ? 'Tarif groupe' : 'Réduction' ?></span><span>−<?= e(money((int)$o['discount'])) ?></span></div><?php endif; ?>
    <?php if ($o['fee_mode'] === 'buyer' && $o['fees']): ?><div class="summary-line"><span>Frais de service</span><span><?= e(money((int)$o['fees'])) ?></span></div><?php endif; ?>
    <div class="summary-line total"><span>Total</span><span><?= e(money((int)$o['total'])) ?></span></div>
    <div class="dw-timer" data-expires="<?= e(date('c', strtotime((string)$o['expires_at']))) ?>">Places gardées <b data-left><?= e(date('H\hi', strtotime($o['expires_at']))) ?></b></div>
    <script>(function(){var el=document.querySelector('[data-expires]'),end=new Date(el.dataset.expires).getTime(),b=el.querySelector('[data-left]');var t=function(){var s=Math.max(0,Math.round((end-Date.now())/1000));b.textContent=Math.floor(s/60)+':'+String(s%60).padStart(2,'0');el.classList.toggle('hurry',s<120);};t();setInterval(t,1000);})();</script>
  </aside>
</div>
