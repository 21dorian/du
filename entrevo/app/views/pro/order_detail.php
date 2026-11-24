<?php
/** @var array $o, $ev, $items, $tickets, $answers, $history */
$paid = $o['status'] === 'paid'; $fieldsAns = $answers['order'] ?? []; $tags = order_tags($o['tags']);
$valid = array_filter($tickets, fn($t) => $t['status'] === 'valid');
?>
<div class="order-top">
  <div>
    <div class="order-ref"><?= badge($o['status']) ?>
      <?php if ($paid && (int)$o['refunded_amount']): ?><span class="badge grey">Remboursée en partie : <?= e(money((int)$o['refunded_amount'])) ?></span><?php endif; ?></div>
    <div class="muted"><?= e(fr_date($o['created_at'])) ?> · <?= e(method_label($o['method'])) ?><?= $creator ? ' par ' . e($creator) : '' ?> · <?= count($tickets) ?> billet<?= count($tickets) > 1 ? 's' : '' ?></div>
  </div>
  <div class="row" style="gap:8px">
    <?php if ($prev): ?><a class="btn btn-sm btn-light" href="<?= url('/pro/commandes/' . $prev) ?>" title="Commande précédente" aria-label="Commande précédente">←</a><?php endif; ?>
    <?php if ($next): ?><a class="btn btn-sm btn-light" href="<?= url('/pro/commandes/' . $next) ?>" title="Commande suivante" aria-label="Commande suivante">→</a><?php endif; ?>
  </div>
</div>

<?php if ($o['refund_requested_at'] && $paid): ?><div class="flash warning">💸 L'acheteur a demandé un remboursement le <?= e(short_date($o['refund_requested_at'])) ?><?= $o['refund_reason'] ? ' : « ' . e($o['refund_reason']) . ' »' : '' ?>. Utilise « Rembourser et annuler » pour accepter, ou un remboursement partiel.</div><?php endif; ?>

<div class="split-3">
  <div>
    <div class="card">
      <div class="card-head"><h2>🎟️ Billets</h2><span class="muted"><?= count($valid) ?> valide<?= count($valid) > 1 ? 's' : '' ?> sur <?= count($tickets) ?></span></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Participant</th><th>Tarif</th><th>Code</th><th>Entrée</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $t): $ta = json_decode((string)$t['answers'], true) ?: []; ?>
          <tr>
            <td><form method="post" class="row" style="gap:6px;flex-wrap:nowrap"><?= csrf_field() ?><input type="hidden" name="action" value="holder"><input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
              <input name="first_name" value="<?= e($t['first_name']) ?>" style="width:78px;padding:6px 7px" aria-label="Prénom"><input name="last_name" value="<?= e($t['last_name']) ?>" style="width:86px;padding:6px 7px" aria-label="Nom">
              <button class="btn btn-sm btn-light" title="Enregistrer le nom">OK</button></form>
              <?php foreach ($ta as $a): ?><small style="display:block"><?= e($a['label']) ?> : <b><?= e($a['value']) ?></b></small><?php endforeach; ?>
              <?php if ($t['holder_email']): ?><small style="display:block" class="muted">🎁 Donné à <?= e($t['holder_email']) ?> le <?= e(short_date((string)$t['transferred_at'])) ?><?= (int)$t['transfers'] > 1 ? ' (' . (int)$t['transfers'] . ' transferts)' : '' ?></small><?php endif; ?>
            </td>
            <td style="min-width:110px"><?= e($t['rate_name']) ?><br><small><?= (int)$t['price'] ? e(money((int)$t['price'])) : 'Gratuit' ?><?= $t['seat'] ? ' · 💺 ' . e($t['seat']) : '' ?></small></td>
            <td class="mono" style="font-size:13px"><?= e($t['code']) ?></td>
            <td><?= $t['status'] !== 'valid' ? badge('cancelled') : ($t['entries'] > 0 ? '<span class="badge green">' . e(short_date($t['checked_in_at'])) . '</span>' : '<span class="badge grey">Pas arrivé</span>') ?></td>
            <td class="right"><?php if ($t['status'] === 'valid'): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="cancel_ticket" data-confirm="Annuler ce billet ? Il sera refusé à l'entrée (pas de remboursement automatique).">Annuler</button></form><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$tickets): ?><tr><td colspan="5" class="empty">Les billets sont créés dès que le paiement est validé.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>

    <?php if ($paid && $refundable > 0): ?>
    <details class="card card-details" id="partiel">
      <summary><h2>💸 Remboursement partiel</h2><span class="muted">Jusqu'à <?= e(money($refundable)) ?></span></summary>
      <form method="post" class="stack" data-partial-refund>
        <?= csrf_field() ?><input type="hidden" name="action" value="partial_refund">
        <p class="muted mb0">Rembourse une partie de la commande : un billet, une option, un geste commercial… Tu peux aussi annuler les billets concernés pour qu'ils soient refusés à l'entrée.
          <?= $o['method'] === 'online' ? 'Le montant est rendu sur le moyen de paiement d\'origine.' : 'Paiement hors ligne : rends l\'argent en direct, on garde la trace.' ?></p>
        <?php if ($valid): ?>
        <fieldset class="check-list"><legend>Billets à annuler (optionnel)</legend>
          <?php foreach ($valid as $t): ?><label class="check"><input type="checkbox" name="tickets[]" value="<?= (int)$t['id'] ?>" data-price="<?= (int)$t['price'] ?>"> <span><?= e(trim($t['first_name'] . ' ' . $t['last_name']) ?: 'Sans nom') ?> · <?= e($t['rate_name']) ?> <small class="muted"><?= (int)$t['price'] ? e(money((int)$t['price'])) : 'Gratuit' ?></small></span></label><?php endforeach; ?>
        </fieldset>
        <?php endif; ?>
        <div class="row" style="gap:10px;align-items:flex-end">
          <div class="field mb0"><label for="pr-amount">Montant à rembourser</label><div class="input-suffix" style="width:160px"><input id="pr-amount" name="amount" inputmode="decimal" placeholder="0,00" data-max="<?= $refundable ?>"><span>€</span></div></div>
          <button class="btn btn-danger" data-confirm="Valider ce remboursement partiel ?">Rembourser</button>
        </div>
        <small class="muted">Astuce : cocher des billets pré-remplit le montant avec leur prix.</small>
      </form>
    </details>
    <?php endif; ?>

    <?php if ($fieldsAns): ?>
    <div class="card"><h2>Réponses au formulaire</h2><?php foreach ($fieldsAns as $a): ?><div class="list-item"><span class="grow muted"><?= e($a['label']) ?></span><b><?= e($a['value']) ?></b></div><?php endforeach; ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h2>🕓 Historique</h2></div>
      <ol class="timeline">
        <?php foreach ($history as $h): ?>
          <li><span class="tl-dot"></span><div><b><?= e(Audit::label($h['action'])) ?></b><?= $h['details'] ? ' · ' . e($h['details']) : '' ?><br><small class="muted"><?= e(short_date($h['created_at'])) ?><?= $h['user_name'] ? ' · ' . e($h['user_name']) : '' ?></small></div></li>
        <?php endforeach; ?>
        <?php if ($o['cancelled_at']): ?><li><span class="tl-dot red"></span><div><b><?= $o['status'] === 'refunded' ? '💸 Remboursée' : '🚫 Annulée' ?></b><br><small class="muted"><?= e(short_date($o['cancelled_at'])) ?></small></div></li><?php endif; ?>
        <?php if ($o['refund_requested_at']): ?><li><span class="tl-dot orange"></span><div><b>💬 Remboursement demandé par l'acheteur</b><br><small class="muted"><?= e(short_date($o['refund_requested_at'])) ?></small></div></li><?php endif; ?>
        <?php if ($o['paid_at']): ?><li><span class="tl-dot green"></span><div><b>✅ Paiement validé</b> · <?= e(money((int)$o['total'])) ?><br><small class="muted"><?= e(short_date($o['paid_at'])) ?></small></div></li><?php endif; ?>
        <li><span class="tl-dot"></span><div><b>🛒 Commande créée</b><?= $o['utm_source'] ?? '' ? ' · via ' . e($o['utm_source']) : '' ?><?php if (!empty($o['referrer_order_id'])): $rf = one('SELECT ref, first_name, last_name FROM orders WHERE id = ?', [$o['referrer_order_id']]); if ($rf): ?> · 👯 invité par <a href="<?= url('/pro/commandes', ['q' => $rf['ref']]) ?>"><?= e(trim($rf['first_name'] . ' ' . $rf['last_name'])) ?></a><?php endif; endif; ?><?= (int)($o['group_discount'] ?? 0) ? ' · 👥 tarif groupe −' . e(money((int)$o['group_discount'])) : '' ?><?= (int)($o['exchanges'] ?? 0) ? ' · 📅 date changée ' . (int)$o['exchanges'] . ' fois' : '' ?><?= !empty($o['birth_date']) ? ' · 🎂 ' . e(date('d/m/Y', strtotime((string)$o['birth_date']))) : '' ?><br><small class="muted"><?= e(short_date($o['created_at'])) ?></small></div></li>
      </ol>
    </div>
  </div>

  <div>
    <div class="card">
      <div class="card-head"><h2>Acheteur</h2><button type="button" class="link-btn" data-toggle="#buyer-edit">Modifier</button></div>
      <p class="mb0"><b><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b><br><?= $o['email'] ? '<a href="mailto:' . e($o['email']) . '">' . e($o['email']) . '</a>' : '<span class="muted">Pas d\'e-mail</span>' ?><?= $o['phone'] ? '<br>' . e($o['phone']) : '' ?></p>
      <form method="post" class="stack" id="buyer-edit" hidden style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="action" value="buyer">
        <div class="grid2"><div class="field mb0"><label>Prénom</label><input name="first_name" value="<?= e($o['first_name']) ?>" required></div><div class="field mb0"><label>Nom</label><input name="last_name" value="<?= e($o['last_name']) ?>" required></div></div>
        <div class="field mb0"><label>E-mail</label><input type="email" name="email" value="<?= e($o['email']) ?>"></div>
        <div class="field mb0"><label>Téléphone</label><input name="phone" value="<?= e($o['phone']) ?>"></div>
        <label class="check"><input type="checkbox" name="sync_tickets" value="1" checked> Mettre aussi à jour les billets au même nom</label>
        <button class="btn btn-sm">Enregistrer</button>
      </form>
      <hr>
      <div class="summary-line"><span class="muted">Paiement</span><span><?= e(provider_label($o['provider'] ?: $o['pay_method'])) ?: '—' ?></span></div>
      <?php if ($o['promo_code']): ?><div class="summary-line"><span class="muted">Code promo</span><span class="mono"><?= e($o['promo_code']) ?></span></div><?php endif; ?>
      <?php if ($o['note']): ?><div class="summary-line"><span class="muted">Note</span><span><?= e($o['note']) ?></span></div><?php endif; ?>
      <hr>
      <?php foreach ($items as $i): ?><div class="summary-line"><span><?= (int)$i['qty'] ?> × <?= e($i['name']) ?></span><span><?= e(money($i['qty'] * $i['unit_price'])) ?></span></div><?php endforeach; ?>
      <?php if ($o['discount']): ?><div class="summary-line" style="color:var(--green)"><span>Réduction</span><span>−<?= e(money((int)$o['discount'])) ?></span></div><?php endif; ?>
      <?php if ($o['fees'] && $o['fee_mode'] === 'buyer'): ?><div class="summary-line"><span>Frais payés par l'acheteur</span><span><?= e(money((int)$o['fees'])) ?></span></div><?php endif; ?>
      <div class="summary-line total"><span>Total</span><span><?= e(money((int)$o['total'])) ?></span></div>
      <?php if ((int)$o['refunded_amount']): ?><div class="summary-line neg"><span>Remboursé</span><span>−<?= e(money((int)$o['refunded_amount'])) ?></span></div><?php endif; ?>
      <?php if ($o['method'] === 'online'): ?>
        <div class="summary-line"><span class="muted">Commission plateforme</span><span>−<?= e(money((int)$o['commission'])) ?></span></div>
        <div class="summary-line"><b>Pour toi</b><b><?= e(money((int)$o['organizer_amount'])) ?></b></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>🏷️ Étiquettes & note interne</h2>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="meta">
        <div class="field mb0"><label for="tags">Étiquettes <small class="muted">(séparées par des virgules)</small></label><input id="tags" name="tags" value="<?= e(implode(', ', $tags)) ?>" placeholder="VIP, presse, à rappeler…" maxlength="255"></div>
        <div class="field mb0"><label for="inote">Note interne <small class="muted">(jamais vue par l'acheteur)</small></label><textarea id="inote" name="internal_note" rows="3" placeholder="Ex. : a appelé pour changer de date, OK pour un geste…"><?= e((string)$o['internal_note']) ?></textarea></div>
        <button class="btn btn-sm btn-light">Enregistrer</button>
      </form>
    </div>

    <?php if ($paid): ?>
    <div class="card">
      <h2>Actions</h2>
      <form method="post" class="stack"><?= csrf_field() ?>
        <div class="field mb0"><label>Renvoyer les billets à</label><input type="email" name="email" value="<?= e($o['email']) ?>"></div>
        <button class="btn btn-block" name="action" value="resend">📬 Renvoyer les billets</button>
        <a class="btn btn-block btn-light" href="<?= url('/commande/' . $o['ref'] . '/pdf', ['t' => $o['token']]) ?>" target="_blank">Voir le PDF</a>
        <a class="btn btn-block btn-light" href="<?= url('/commande/' . $o['ref'] . '/recu', ['t' => $o['token']]) ?>" target="_blank">🧾 Reçu</a>
        <a class="btn btn-block btn-light" href="<?= url('/pro/commandes/' . $o['id'] . '/ticket-caisse') ?>" target="_blank">🖨️ Ticket de caisse (80 mm)</a>
        <?php if ($o['total'] > 0): ?>
          <button class="btn btn-block btn-danger" name="action" value="refund" data-confirm="Rembourser l'acheteur et annuler tous les billets ?">💸 Rembourser et annuler</button>
        <?php endif; ?>
        <button class="btn btn-block btn-light" name="action" value="cancel" data-confirm="Annuler la commande SANS remboursement ? Les billets seront refusés à l'entrée.">Annuler sans rembourser</button>
      </form>
      <div class="soon-row"><?= soon_btn('factures', '🧾 Facture pro') ?><?= soon_btn('sms', '💬 Envoyer par SMS') ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>
