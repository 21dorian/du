<?php $paid = $o['status'] === 'paid'; ?>
<?php if ($paid): ?>
  <div class="steps no-print"><span class="done">Billets</span><span class="done">Informations</span><span class="done">Paiement</span><span class="on">Confirmation</span></div>
  <div class="state-head">
    <div class="state-mark">🎉</div>
    <h1>C'est dans la poche, <?= e($o['first_name']) ?> !</h1>
    <p class="muted">Commande <b><?= e($o['ref']) ?></b> · <?= $o['email_sent_at'] ? 'tes billets sont aussi partis vers <b>' . e($o['email']) . '</b> 📬' : 'tes billets sont juste en dessous 👇' ?></p>
    <div class="row no-print" style="justify-content:center">
      <a class="btn" href="<?= url('/commande/' . $o['ref'] . '/pdf', ['t' => $o['token'], 'dl' => 1]) ?>">Télécharger (PDF)</a>
      <button class="btn btn-ghost" onclick="window.print()">Imprimer</button>
    </div>
  </div>
<?php elseif ($o['status'] === 'pending'): ?>
  <div class="card center auth-box">
    <div class="state-mark wait">⏳</div>
    <h1>On vérifie ton paiement…</h1>
    <p class="muted">La banque prend son temps. Ne bouge pas, cette page se met à jour toute seule.</p>
    <a class="btn btn-ghost" href="<?= url('/paiement/' . $o['ref'], ['t' => $o['token']]) ?>">Retour au paiement</a>
    <script>setTimeout(function(){location.reload()},6000)</script>
  </div>
<?php else: ?>
  <div class="card center auth-box"><h1>Commande <?= e($o['ref']) ?></h1><p><?= badge($o['status']) ?></p><p class="muted">Cette commande n'est plus active. Elle a fait son temps. 🫡</p></div>
<?php endif; ?>

<?php if ($tickets): $n = count($tickets); ?>
<div style="max-width:860px;margin:0 auto">
  <div class="tickets-list">
  <?php foreach ($tickets as $i => $t): ?>
    <div class="ticket <?= $t['status'] !== 'valid' ? 'cancelled' : '' ?>">
      <div class="ticket-main">
        <div class="ticket-org"><?= e($org['name']) ?> présente</div>
        <div class="ticket-event"><?= e($ev['name']) ?></div>
        <div class="ticket-grid">
          <div><small>Date</small><b><?= e(date('d.m.Y', strtotime($ev['starts_at']))) ?></b></div>
          <div><small>Heure</small><b><?= e(date('H:i', strtotime($ev['starts_at']))) ?></b></div>
          <div><small>Tarif</small><b><?= e($t['rate_name']) ?></b></div>
          <div style="grid-column:span 2"><small>Lieu</small><b><?= e(trim($ev['venue'] . ($ev['city'] ? ', ' . $ev['city'] : ''), ', ')) ?: '—' ?></b></div>
          <div><small>Prix</small><b><?= (int)$t['price'] ? e(money((int)$t['price'])) : 'Gratuit' ?></b></div>
          <div style="grid-column:1/-1"><small>Participant</small><b><?= e(trim($t['first_name'] . ' ' . $t['last_name'])) ?></b></div>
        </div>
        <div class="ticket-actions no-print">
          <?= $t['status'] === 'valid' ? '' : '<b style="color:var(--red)">BILLET ANNULÉ</b>' ?>
          <?php if ($t['status'] === 'valid'): ?><a href="<?= url('/commande/' . $o['ref'] . '/pdf', ['t' => $o['token'], 'billet' => $t['code']]) ?>" target="_blank">PDF de ce billet</a><?php endif; ?>
        </div>
      </div>
      <div class="ticket-stub">
        <span class="ticket-num">Billet <?= $i + 1 ?>/<?= $n ?></span>
        <?= $t['status'] === 'valid' ? Qr::svg($t['code'], 170) : '<div style="width:170px;height:170px"></div>' ?>
        <span class="ticket-code"><?= e($t['code']) ?></span>
        <span class="ticket-num">Commande <?= e($o['ref']) ?></span>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
  <p class="muted center mt" style="font-size:14px">Un QR code = une entrée. Le partager, c'est le perdre. 🙅</p>
</div>
<?php endif; ?>
