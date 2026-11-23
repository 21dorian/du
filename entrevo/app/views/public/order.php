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
      <?php if ((int)$o['total'] > 0): ?><a class="btn btn-ghost" href="<?= url('/commande/' . $o['ref'] . '/recu', ['t' => $o['token']]) ?>" target="_blank">Reçu</a><?php endif; ?>
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

<?php if ($tickets): $n = count($tickets); $start = $session['starts_at'] ?? $ev['starts_at']; ?>
<div style="max-width:860px;margin:0 auto">
  <div class="tickets-list">
  <?php foreach ($tickets as $i => $t): ?>
    <div class="ticket <?= $t['status'] !== 'valid' ? 'cancelled' : '' ?>">
      <div class="ticket-main">
        <div class="ticket-org"><?= e($org['name']) ?> présente<?= $t['type'] === 'invitation' ? ' · 🎁 INVITATION' : '' ?></div>
        <div class="ticket-event"><?= e($ev['name']) ?></div>
        <div class="ticket-grid">
          <div><small>Date</small><b><?= e(date('d.m.Y', strtotime($start))) ?></b></div>
          <div><small>Heure</small><b><?= e(date('H:i', strtotime($start))) ?></b></div>
          <div><small>Tarif</small><b><?= e($t['rate_name']) ?></b></div>
          <div style="grid-column:span 2"><small>Lieu</small><b><?= e(trim($ev['venue'] . ($ev['city'] ? ', ' . $ev['city'] : ''), ', ')) ?: '—' ?></b></div>
          <div><small>Prix</small><b><?= (int)$t['price'] ? e(money((int)$t['price'])) : 'Gratuit' ?></b></div>
          <div style="grid-column:1/-1"><small>Participant</small><b><?= e(trim($t['first_name'] . ' ' . $t['last_name'])) ?></b></div>
        </div>
        <?php if ($t['seat']): ?><span class="ticket-seat">💺 <?= e(Seating::label($t['seat'], $map)) ?></span><?php endif; ?>
        <?php if ((int)$t['max_entries'] > 1 || $t['per_day']): ?><span class="ticket-seat">🔁 <?= $t['per_day'] ? '1 entrée par jour' : (int)$t['max_entries'] . ' entrées' ?></span><?php endif; ?>
        <div class="ticket-actions no-print">
          <?= $t['status'] === 'valid' ? '' : '<b style="color:var(--red)">BILLET ANNULÉ</b>' ?>
          <?php if ($t['status'] === 'valid'): ?><a href="<?= url('/commande/' . $o['ref'] . '/pdf', ['t' => $o['token'], 'billet' => $t['code']]) ?>" target="_blank">PDF de ce billet</a><?php endif; ?>
          <?php if ($t['replaced_by']): ?><b>🔁 Revendu</b><?php endif; ?>
          <?php if ($ev['resale_enabled'] && $t['status'] === 'valid' && $t['type'] === 'ticket' && (int)$t['entries'] === 0 && (int)$t['price'] > 0): ?>
            <form method="post" action="<?= url('/commande/' . $o['ref'] . '/revendre', ['t' => $o['token']]) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
            <?php if ($t['resale_status'] === 'listed'): ?><span>🔁 En vente sur la bourse ·</span> <button class="linklike" name="action" value="cancel">annuler la revente</button>
            <?php else: ?><button class="linklike" name="action" value="list" data-confirm="Mettre ce billet en vente au prix payé (<?= e(money((int)$t['price'])) ?>) ? Tu seras remboursé moins <?= e(rtrim(rtrim((string)$ev['resale_fee_percent'], '0'), '.')) ?> % de frais quand il sera racheté.">🔁 Je ne peux plus venir : revendre</button><?php endif; ?></form>
          <?php endif; ?>
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
  <?php $extras = array_filter($items, fn($i) => ($i['kind'] ?? 'rate') === 'product'); if ($extras || (int)$o['donation'] > 0): ?>
    <div class="card mt"><h3>✨ Avec ta commande</h3>
      <?php foreach ($extras as $i): ?><div class="summary-line"><span><?= (int)$i['qty'] ?> × <?= e($i['name']) ?></span><span>à retirer sur place avec ton billet</span></div><?php endforeach; ?>
      <?php if ((int)$o['donation'] > 0): ?><div class="summary-line"><span>💛 Ton don</span><span><?= e(money((int)$o['donation'])) ?> — merci !</span></div><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($refundable): ?>
    <details class="card mt no-print"><summary class="linklike" style="cursor:pointer">💸 Demander un remboursement</summary>
      <form method="post" action="<?= url('/commande/' . $o['ref'] . '/remboursement', ['t' => $o['token']]) ?>" class="mt"><?= csrf_field() ?>
        <p class="muted" style="font-size:14px">L'organisateur accepte les demandes<?= $ev['refund_deadline'] ? ' jusqu\'au ' . e(fr_date($ev['refund_deadline'])) : '' ?>. Tes billets resteront valables tant que la demande n'est pas traitée.</p>
        <div class="field"><label>Motif (facultatif)</label><input name="reason"></div><button class="btn btn-ghost" data-confirm="Envoyer la demande de remboursement ?">Envoyer la demande</button></form></details>
  <?php elseif ($o['refund_requested_at'] && $o['status'] === 'paid'): ?><p class="muted center">📨 Demande de remboursement envoyée le <?= e(short_date($o['refund_requested_at'])) ?>, en cours de traitement.</p><?php endif; ?>
  <p class="muted center mt" style="font-size:14px">Un QR code = une entrée. Le partager, c'est le perdre. 🙅</p>
</div>
<?php endif; ?>
