<?php $paid = $o['status'] === 'paid'; $fieldsAns = $answers['order'] ?? []; ?>
<div class="split-3">
  <div>
    <div class="card">
      <div class="card-head"><h2>🎟️ Billets</h2><?= badge($o['status']) ?></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Participant</th><th>Tarif</th><th>Code</th><th>Entrée</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $t): $ta = json_decode((string)$t['answers'], true) ?: []; ?>
          <tr>
            <td><form method="post" class="row" style="gap:6px;flex-wrap:nowrap"><?= csrf_field() ?><input type="hidden" name="action" value="holder"><input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
              <input name="first_name" value="<?= e($t['first_name']) ?>" style="width:92px;padding:6px 7px" aria-label="Prénom"><input name="last_name" value="<?= e($t['last_name']) ?>" style="width:104px;padding:6px 7px" aria-label="Nom">
              <button class="btn btn-sm btn-light" title="Enregistrer le nom">OK</button></form>
              <?php foreach ($ta as $a): ?><small style="display:block"><?= e($a['label']) ?> : <b><?= e($a['value']) ?></b></small><?php endforeach; ?>
            </td>
            <td><?= e($t['rate_name']) ?><br><small><?= (int)$t['price'] ? e(money((int)$t['price'])) : 'Gratuit' ?></small></td>
            <td class="mono" style="font-size:13px"><?= e($t['code']) ?></td>
            <td><?= $t['status'] !== 'valid' ? badge('cancelled') : ($t['entries'] > 0 ? '<span class="badge green">' . e(short_date($t['checked_in_at'])) . '</span>' : '<span class="badge grey">Pas arrivé</span>') ?></td>
            <td class="right"><?php if ($t['status'] === 'valid'): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="cancel_ticket" data-confirm="Annuler ce billet ? Il sera refusé à l'entrée (pas de remboursement automatique).">Annuler</button></form><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$tickets): ?><tr><td colspan="5" class="empty">Les billets sont créés dès que le paiement est validé.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
    <?php if ($fieldsAns): ?>
    <div class="card"><h2>Réponses au formulaire</h2><?php foreach ($fieldsAns as $a): ?><div class="list-item"><span class="grow muted"><?= e($a['label']) ?></span><b><?= e($a['value']) ?></b></div><?php endforeach; ?></div>
    <?php endif; ?>
  </div>
  <div>
    <div class="card">
      <h2>Acheteur</h2>
      <p class="mb0"><b><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b><br><?= e($o['email']) ?><?= $o['phone'] ? '<br>' . e($o['phone']) : '' ?></p>
      <hr>
      <div class="summary-line"><span class="muted">Passée le</span><span><?= e(short_date($o['created_at'])) ?></span></div>
      <div class="summary-line"><span class="muted">Vente</span><span><?= e(method_label($o['method'])) ?><?= $creator ? ' par ' . e($creator) : '' ?></span></div>
      <div class="summary-line"><span class="muted">Paiement</span><span><?= e(provider_label($o['provider'] ?: $o['pay_method'])) ?: '—' ?></span></div>
      <?php if ($o['promo_code']): ?><div class="summary-line"><span class="muted">Code promo</span><span class="mono"><?= e($o['promo_code']) ?></span></div><?php endif; ?>
      <?php if ($o['note']): ?><div class="summary-line"><span class="muted">Note</span><span><?= e($o['note']) ?></span></div><?php endif; ?>
      <hr>
      <?php foreach ($items as $i): ?><div class="summary-line"><span><?= (int)$i['qty'] ?> × <?= e($i['name']) ?></span><span><?= e(money($i['qty'] * $i['unit_price'])) ?></span></div><?php endforeach; ?>
      <?php if ($o['discount']): ?><div class="summary-line" style="color:var(--green)"><span>Réduction</span><span>−<?= e(money((int)$o['discount'])) ?></span></div><?php endif; ?>
      <?php if ($o['fees'] && $o['fee_mode'] === 'buyer'): ?><div class="summary-line"><span>Frais payés par l'acheteur</span><span><?= e(money((int)$o['fees'])) ?></span></div><?php endif; ?>
      <div class="summary-line total"><span>Total</span><span><?= e(money((int)$o['total'])) ?></span></div>
      <?php if ($o['method'] === 'online'): ?>
        <div class="summary-line"><span class="muted">Commission plateforme</span><span>−<?= e(money((int)$o['commission'])) ?></span></div>
        <div class="summary-line"><b>Pour toi</b><b><?= e(money((int)$o['organizer_amount'])) ?></b></div>
      <?php endif; ?>
    </div>
    <?php if ($paid): ?>
    <div class="card">
      <h2>Actions</h2>
      <form method="post" class="stack"><?= csrf_field() ?>
        <div class="field mb0"><label>Renvoyer les billets à</label><input type="email" name="email" value="<?= e($o['email']) ?>"></div>
        <button class="btn btn-block" name="action" value="resend">📬 Renvoyer les billets</button>
        <a class="btn btn-block btn-light" href="<?= url('/commande/' . $o['ref'] . '/pdf', ['t' => $o['token']]) ?>" target="_blank">Voir le PDF</a>
        <?php if ($o['method'] === 'online' && $o['total'] > 0): ?>
          <button class="btn btn-block btn-danger" name="action" value="refund" data-confirm="Rembourser l'acheteur et annuler tous les billets ?">💸 Rembourser et annuler</button>
        <?php endif; ?>
        <button class="btn btn-block btn-light" name="action" value="cancel" data-confirm="Annuler la commande SANS remboursement ? Les billets seront refusés à l'entrée.">Annuler sans rembourser</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
