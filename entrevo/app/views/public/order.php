<?php $paid = $o['status'] === 'paid'; $fresh = $o['paid_at'] && time() - strtotime($o['paid_at']) < 1800; ?>
<?php if ($paid): ?>
  <div class="steps no-print"><span class="done">Billets</span><span class="done">Informations</span><span class="done">Paiement</span><span class="on">Confirmation</span></div>
  <div class="state-head">
    <div class="state-mark"<?= $fresh ? ' data-confetti="' . e($o['ref']) . '"' : '' ?>><?= ui_icon('check', 30) ?></div>
    <h1>C'est dans la poche, <?= e($o['first_name']) ?> !</h1>
    <p class="muted">Commande <b><?= e($o['ref']) ?></b> · <?= $o['email_sent_at'] ? 'tes billets sont aussi partis vers <b>' . e($o['email']) . '</b>' : 'tes billets sont juste en dessous' ?></p>
    <div class="row no-print" style="justify-content:center">
      <a class="btn" href="<?= url('/commande/' . $o['ref'] . '/pdf', ['t' => $o['token'], 'dl' => 1]) ?>">Télécharger (PDF)</a>
      <button class="btn btn-ghost" onclick="window.print()">Imprimer</button>
      <?php if ((int)$o['total'] > 0): ?><a class="btn btn-ghost" href="<?= url('/commande/' . $o['ref'] . '/recu', ['t' => $o['token']]) ?>" target="_blank">Reçu</a><?php endif; ?>
    </div>
    <?php if (strtotime((string)($ev['ends_at'] ?: $ev['starts_at'])) < time()): $hasRev = val('SELECT id FROM reviews WHERE order_id = ?', [$o['id']]); ?>
      <p class="no-print" style="margin-top:12px"><a class="btn btn-light" href="<?= url('/avis/' . $o['ref'], ['t' => $o['token']]) ?>"><?= $hasRev ? 'Modifier mon avis' : 'Donner mon avis sur l\'événement' ?></a></p>
    <?php endif; ?>
    <div class="row no-print" style="justify-content:center;margin-top:10px">
      <details class="cal-menu"><summary class="btn btn-sm btn-light">Ajouter à mon agenda</summary><div class="cal-pop"><a href="<?= e(Buyers::googleUrl($ev, $session)) ?>" target="_blank" rel="noopener">Google Agenda</a><a href="<?= e(Buyers::icsUrl($ev, $session)) ?>">Apple / Outlook (.ics)</a></div></details>
    </div>
    <div class="soon-row center-row no-print"><?= pub_soon('wallet', 'Ajouter au Wallet') ?><?= pub_soon('covoiturage', 'Covoiturage') ?></div>
  </div>
<?php elseif ($o['status'] === 'pending'): ?>
  <div class="card center auth-box">
    <div class="state-mark wait"><?= ui_icon('clock', 30) ?></div>
    <h1>On vérifie ton paiement…</h1>
    <p class="muted">La banque prend son temps. Ne bouge pas, cette page se met à jour toute seule.</p>
    <a class="btn btn-ghost" href="<?= url('/paiement/' . $o['ref'], ['t' => $o['token']]) ?>">Retour au paiement</a>
    <script>setTimeout(function(){location.reload()},6000)</script>
  </div>
<?php else: ?>
  <div class="card center auth-box"><h1>Commande <?= e($o['ref']) ?></h1><p><?= badge($o['status']) ?></p><p class="muted">Cette commande n'est plus active. Elle a fait son temps.</p></div>
<?php endif; ?>

<?php if ($tickets): $n = count($tickets); $start = $session['starts_at'] ?? $ev['starts_at']; ?>
<div style="max-width:860px;margin:0 auto">
  <?php if (!empty($paid) && (int)($ev['cashless_enabled'] ?? 0) && strtotime((string)($ev['ends_at'] ?: $ev['starts_at'])) + 6 * 3600 > time()): $cv = array_values(array_filter($tickets, fn($t) => $t['status'] === 'valid')); ?>
  <div class="card no-print cashless-box"><div><b>Paiement sans espèces aux bars</b><br><small class="muted">Recharge ton billet à l'avance : au bar, ton QR code suffit.</small></div>
    <div class="row"><?php foreach (array_slice($cv, 0, 6) as $t): $wb = (int)val('SELECT balance FROM wallets WHERE ticket_id = ?', [$t['id']]); ?><a class="btn btn-sm btn-light" href="<?= url('/commande/' . $o['ref'] . '/cashless/' . $t['code'], ['t' => $o['token']]) ?>"><?= e(trim($t['first_name']) ?: $t['code']) ?> · <?= e(money($wb)) ?></a><?php endforeach; ?></div></div>
  <?php endif; ?>
  <?php if ($paid && strtotime((string)($ev['ends_at'] ?: $ev['starts_at'])) > time()): $inv = Buyers::inviteUrl($ev, $o); $nRef = Buyers::referralCount((int)$o['id']);
    $msg = 'Je vais à « ' . $ev['name'] . ' » le ' . fr_date((string)$start, false) . ', tu viens ? ' . $inv; ?>
  <div class="card invite-box no-print">
    <div class="invite-head"><div><b>Plus on est de fous…</b><span class="muted">Envoie ton lien à tes potes pour qu'ils prennent leur place.<?= $nRef ? ' <b class="invite-count">' . $nRef . ' ami' . ($nRef > 1 ? 's ont' : ' a') . ' déjà pris sa place grâce à toi !</b>' : '' ?></span></div></div>
    <div class="invite-actions">
      <a class="btn btn-sm invite-wa" href="https://wa.me/?text=<?= e(rawurlencode($msg)) ?>" target="_blank" rel="noopener">WhatsApp</a>
      <a class="btn btn-sm btn-light" href="sms:?&amp;body=<?= e(rawurlencode($msg)) ?>">SMS</a>
      <a class="btn btn-sm btn-light" href="mailto:?subject=<?= e(rawurlencode('On y va ? ' . $ev['name'])) ?>&amp;body=<?= e(rawurlencode($msg)) ?>">E-mail</a>
      <button type="button" class="btn btn-sm btn-light" data-share data-url="<?= e($inv) ?>" data-text="<?= e($msg) ?>">Partager…</button>
      <div class="invite-link"><input readonly value="<?= e($inv) ?>" aria-label="Ton lien d'invitation"><button type="button" class="btn btn-sm btn-light" data-copy="<?= e($inv) ?>">Copier</button></div>
    </div>
  </div>
  <?php endif; ?>
  <?php $ex = Buyers::exchangeOptions($o, $ev); if ($ex['why'] || $ex['list']): ?>
  <details class="card exchange-box no-print">
    <summary><b>Changer de date</b> <span class="muted"><?= $ex['why'] ? e($ex['why']) : 'Un empêchement ? Déplace tes ' . (int)$ex['count'] . ' billet' . ($ex['count'] > 1 ? 's' : '') . ' sur une autre date, sans frais.' ?></span></summary>
    <?php if ($ex['list']): ?>
    <form method="post" action="<?= url('/commande/' . $o['ref'] . '/date', ['t' => $o['token']]) ?>" class="exchange-form"><?= csrf_field() ?>
      <div class="exchange-list">
        <?php foreach ($ex['list'] as $x): ?>
          <label class="exchange-opt <?= $x['ok'] ? '' : 'off' ?>"><input type="radio" name="session_id" value="<?= (int)$x['id'] ?>" <?= $x['ok'] ? '' : 'disabled' ?> required>
            <span><b><?= e(fr_date((string)$x['starts_at'])) ?></b><?= !empty($x['label']) ? '<small>' . e($x['label']) . '</small>' : '' ?></span><span class="exchange-tag"><?= $x['ok'] ? 'Disponible' : 'Complet' ?></span></label>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-sm" data-confirm="Déplacer toute ta commande sur cette date ? Tes QR codes restent les mêmes.">Changer de date</button>
    </form>
    <?php endif; ?>
  </details>
  <?php endif; ?>
  <?php $valid = array_values(array_filter($tickets, fn($t) => $t['status'] === 'valid' && (int)$t['entries'] === 0 && !$t['holder_email']));
  $canName = $o['status'] === 'paid' && $valid && strtotime((string)($ev['ends_at'] ?: $ev['starts_at'])) > time();
  $allBuyer = $canName && count($valid) > 1 && !array_filter($valid, fn($t) => $t['first_name'] !== $o['first_name'] || $t['last_name'] !== $o['last_name']); ?>
  <?php if ($canName && count($valid) > 1): ?>
  <details class="card names-box no-print" <?= $allBuyer ? 'open' : '' ?>>
    <summary><b>Qui vient avec toi ?</b> <span class="muted"><?= $allBuyer ? 'Tes ' . count($valid) . ' billets sont à ton nom : mets le nom de chacun (facultatif).' : 'Modifier les noms sur les billets' ?></span></summary>
    <form method="post" action="<?= url('/commande/' . $o['ref'] . '/noms', ['t' => $o['token']]) ?>" class="stack" style="margin-top:12px"><?= csrf_field() ?>
      <?php foreach ($valid as $i => $t): ?>
        <div class="names-row"><span class="names-label"><?= e($t['rate_name']) ?><?= $t['seat'] ? ' · ' . e(Seating::label($t['seat'], $map)) : '' ?></span>
          <input name="t[<?= (int)$t['id'] ?>][first_name]" value="<?= e($t['first_name']) ?>" aria-label="Prénom" placeholder="Prénom"><input name="t[<?= (int)$t['id'] ?>][last_name]" value="<?= e($t['last_name']) ?>" aria-label="Nom" placeholder="Nom"></div>
      <?php endforeach; ?>
      <label class="check"><input type="checkbox" name="send" value="1" checked> <span>Me renvoyer les billets à jour par e-mail</span></label>
      <button class="btn btn-sm">Enregistrer les noms</button>
    </form>
  </details>
  <?php endif; ?>
  <div class="tickets-list">
  <?php foreach ($tickets as $i => $t): ?>
    <?php if ($t['holder_email']): ?>
    <div class="ticket given">
      <div class="ticket-main">
        <div class="ticket-org"><?= e($org['name']) ?> présente</div>
        <div class="ticket-event"><?= e($ev['name']) ?></div>
        <p class="given-note"><b><?= e($t['rate_name']) ?></b> donné à <b><?= e(trim($t['first_name'] . ' ' . $t['last_name'])) ?></b> le <?= e(short_date((string)$t['transferred_at'])) ?>. Il ou elle a reçu son propre billet par e-mail.</p>
      </div>
      <div class="ticket-stub"><span class="ticket-num">Billet <?= $i + 1 ?>/<?= $n ?></span><div class="given-stub"><?= ui_icon('gift', 44) ?></div><span class="ticket-num">Transféré</span></div>
    </div>
    <?php continue; endif; ?>
    <div class="ticket <?= $t['status'] !== 'valid' ? 'cancelled' : '' ?>">
      <div class="ticket-main">
        <div class="ticket-org"><?= e($org['name']) ?> présente<?= $t['type'] === 'invitation' ? ' · INVITATION' : '' ?></div>
        <div class="ticket-event"><?= e($ev['name']) ?></div>
        <div class="ticket-grid">
          <div><small>Date</small><b><?= e(date('d.m.Y', strtotime($start))) ?></b></div>
          <div><small>Heure</small><b><?= e(date('H:i', strtotime($start))) ?></b></div>
          <div><small>Tarif</small><b><?= e($t['rate_name']) ?></b></div>
          <div style="grid-column:span 2"><small>Lieu</small><b><?= e(trim($ev['venue'] . ($ev['city'] ? ', ' . $ev['city'] : ''), ', ')) ?: '—' ?></b></div>
          <div><small>Prix</small><b><?= (int)$t['price'] ? e(money((int)$t['price'])) : 'Gratuit' ?></b></div>
          <div style="grid-column:1/-1"><small>Participant</small><b><?= e(trim($t['first_name'] . ' ' . $t['last_name'])) ?></b></div>
        </div>
        <?php if ($t['seat']): ?><span class="ticket-seat"><?= e(Seating::label($t['seat'], $map)) ?></span><?php endif; ?>
        <?php if ((int)$t['max_entries'] > 1 || $t['per_day']): ?><span class="ticket-seat"><?= $t['per_day'] ? '1 entrée par jour' : (int)$t['max_entries'] . ' entrées' ?></span><?php endif; ?>
        <div class="ticket-actions no-print">
          <?= $t['status'] === 'valid' ? '' : '<b style="color:var(--red)">BILLET ANNULÉ</b>' ?>
          <?php if ($t['status'] === 'valid'): ?><a href="<?= url('/commande/' . $o['ref'] . '/pdf', ['t' => $o['token'], 'billet' => $t['code']]) ?>" target="_blank">PDF de ce billet</a><?php endif; ?>
          <?php if ($t['replaced_by']): ?><b>Revendu</b><?php endif; ?>
          <?php if (!Buyers::cannotTransfer($t, $o, $ev)): ?>
            <details class="give-inline"><summary class="linklike">Donner à un ami</summary>
              <form method="post" action="<?= url('/commande/' . $o['ref'] . '/donner', ['t' => $o['token']]) ?>" class="give-form"><?= csrf_field() ?><input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
                <input name="to_first_name" placeholder="Prénom" required aria-label="Prénom"><input name="to_last_name" placeholder="Nom" required aria-label="Nom"><input type="email" name="to_email" placeholder="son@email.fr" required aria-label="Son e-mail">
                <button class="btn btn-sm" data-confirm="Donner ce billet ? Il sera mis à son nom avec un nouveau QR code, et celui-ci ne fonctionnera plus.">Envoyer</button>
              </form>
            </details>
          <?php endif; ?>
          <?php if ($ev['resale_enabled'] && $t['status'] === 'valid' && $t['type'] === 'ticket' && (int)$t['entries'] === 0 && (int)$t['price'] > 0): ?>
            <form method="post" action="<?= url('/commande/' . $o['ref'] . '/revendre', ['t' => $o['token']]) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
            <?php if ($t['resale_status'] === 'listed'): ?><span>En vente sur la bourse ·</span> <button class="linklike" name="action" value="cancel">annuler la revente</button>
            <?php else: ?><button class="linklike" name="action" value="list" data-confirm="Mettre ce billet en vente au prix payé (<?= e(money((int)$t['price'])) ?>) ? Tu seras remboursé moins <?= e(rtrim(rtrim((string)$ev['resale_fee_percent'], '0'), '.')) ?> % de frais quand il sera racheté.">Je ne peux plus venir : revendre</button><?php endif; ?></form>
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
    <div class="card mt"><h3>Avec ta commande</h3>
      <?php foreach ($extras as $i): ?><div class="summary-line"><span><?= (int)$i['qty'] ?> × <?= e($i['name']) ?></span><span>à retirer sur place avec ton billet</span></div><?php endforeach; ?>
      <?php if ((int)$o['donation'] > 0): ?><div class="summary-line"><span>Ton don</span><span><?= e(money((int)$o['donation'])) ?> — merci !</span></div><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if (in_array($o['status'], ['paid', 'refunded'], true) && (int)$o['total'] > 0): ?>
    <details class="card mt no-print invoice-box"><summary class="linklike" style="cursor:pointer"><?= $o['invoice_number'] ? 'Ta facture ' . e($o['invoice_number']) : 'Besoin d\'une facture ?' ?></summary>
      <?php if ($o['invoice_number']): ?><p class="mt mb0 row"><a class="btn btn-light" href="<?= url('/commande/' . $o['ref'] . '/facture', ['t' => $o['token']]) ?>" target="_blank">Télécharger la facture (PDF)</a>
        <?php foreach (class_exists('Accounting') ? all('SELECT id, number FROM credit_notes WHERE order_id = ? ORDER BY id', [$o['id']]) : [] as $cn): ?><a class="btn btn-light" href="<?= url('/commande/' . $o['ref'] . '/avoir/' . $cn['id'], ['t' => $o['token']]) ?>" target="_blank">Avoir <?= e($cn['number']) ?></a><?php endforeach; ?></p>
      <?php else: ?><form method="post" action="<?= url('/commande/' . $o['ref'] . '/facture', ['t' => $o['token']]) ?>" class="mt"><?= csrf_field() ?>
        <p class="muted" style="font-size:14px">Pour ton entreprise, ton comité d'entreprise ou tes notes de frais. Attention : une fois émise, la facture ne peut plus être modifiée.</p>
        <div class="grid2"><div class="field"><label>Société <small class="muted">(facultatif)</small></label><input name="invoice_company" value="<?= e((string)$o['invoice_company']) ?>"></div>
          <div class="field"><label>N° de TVA <small class="muted">(facultatif)</small></label><input name="invoice_vat"></div></div>
        <div class="field"><label>Adresse de facturation</label><textarea name="invoice_address" rows="2"><?= e((string)$o['invoice_address']) ?></textarea></div>
        <button class="btn btn-light">Créer ma facture</button></form><?php endif; ?>
    </details>
  <?php endif; ?>
  <?php if ($refundable): ?>
    <details class="card mt no-print"><summary class="linklike" style="cursor:pointer">Demander un remboursement</summary>
      <form method="post" action="<?= url('/commande/' . $o['ref'] . '/remboursement', ['t' => $o['token']]) ?>" class="mt"><?= csrf_field() ?>
        <p class="muted" style="font-size:14px">L'organisateur accepte les demandes<?= $ev['refund_deadline'] ? ' jusqu\'au ' . e(fr_date($ev['refund_deadline'])) : '' ?>. Tes billets resteront valables tant que la demande n'est pas traitée.</p>
        <div class="field"><label>Motif (facultatif)</label><input name="reason"></div><button class="btn btn-ghost" data-confirm="Envoyer la demande de remboursement ?">Envoyer la demande</button></form></details>
  <?php elseif ($o['refund_requested_at'] && $o['status'] === 'paid'): ?><p class="muted center">Demande de remboursement envoyée le <?= e(short_date($o['refund_requested_at'])) ?>, en cours de traitement.</p><?php endif; ?>
  <p class="muted center mt" style="font-size:14px">Un QR code = une entrée. Le partager, c'est le perdre.</p>
</div>
<?php endif; ?>
