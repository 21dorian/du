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
  <div class="row u-gap8">
    <?php if ($prev): ?><a class="btn btn-sm btn-light" href="<?= url('/pro/commandes/' . $prev) ?>" title="Commande précédente" aria-label="Commande précédente">←</a><?php endif; ?>
    <?php if ($next): ?><a class="btn btn-sm btn-light" href="<?= url('/pro/commandes/' . $next) ?>" title="Commande suivante" aria-label="Commande suivante">→</a><?php endif; ?>
  </div>
</div>

<?php if (!empty($o['cancel_reason'])): ?><div class="flash info">Motif d'annulation : <?= e($o['cancel_reason']) ?><?= !empty($o['credit_gift_id']) ? ' · avoir émis (carte ' . e((string)val('SELECT code FROM gift_cards WHERE id = ?', [$o['credit_gift_id']])) . ')' : '' ?></div><?php endif; ?>
<?php if (count($linked ?? []) > 0): ?><div class="flash info">Commandes liées : <?php foreach ($linked as $l): ?><a href="<?= url('/pro/commandes/' . $l['id']) ?>"><?= e($l['ref']) ?></a> (<?= e(money((int)$l['total'])) ?>, <?= e(strip_tags(badge($l['status']))) ?>) <?php endforeach; ?></div><?php endif; ?>
<?php if ($o['refund_requested_at'] && $paid): ?><div class="flash warning">L'acheteur a demandé un remboursement le <?= e(short_date($o['refund_requested_at'])) ?><?= $o['refund_reason'] ? ' : « ' . e($o['refund_reason']) . ' »' : '' ?>. Utilise « Rembourser et annuler » pour accepter, ou un remboursement partiel.</div><?php endif; ?>

<div class="split-3">
  <div>
    <div class="card">
      <div class="card-head"><h2>Billets</h2><span class="muted"><?= count($valid) ?> valide<?= count($valid) > 1 ? 's' : '' ?> sur <?= count($tickets) ?></span></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Participant</th><th>Tarif</th><th>Code</th><th>Entrée</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $t): $ta = json_decode((string)$t['answers'], true) ?: []; ?>
          <tr>
            <td><form method="post" class="row" style="gap:6px;flex-wrap:nowrap"><?= csrf_field() ?><input type="hidden" name="action" value="holder"><input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
              <input name="first_name" value="<?= e($t['first_name']) ?>" style="width:78px;padding:6px 7px" aria-label="Prénom"><input name="last_name" value="<?= e($t['last_name']) ?>" style="width:86px;padding:6px 7px" aria-label="Nom">
              <button class="btn btn-sm btn-light" title="Enregistrer le nom">OK</button></form>
              <?php foreach ($ta as $a): ?><small class="u-block"><?= e($a['label']) ?> : <b><?= !empty($a['file']) ? '<a href="' . url('/pro/fichier/' . $a['file']) . '" target="_blank">' . e($a['value']) . '</a>' : e($a['value']) ?></b></small><?php endforeach; ?>
              <?php if ($t['holder_email']): ?><small class="muted u-block">Donné à <?= e($t['holder_email']) ?> le <?= e(short_date((string)$t['transferred_at'])) ?><?= (int)$t['transfers'] > 1 ? ' (' . (int)$t['transfers'] . ' transferts)' : '' ?></small><?php endif; ?>
            </td>
            <td style="min-width:110px"><?= e($t['rate_name']) ?><br><small><?= (int)$t['price'] ? e(money((int)$t['price'])) : 'Gratuit' ?><?= $t['seat'] ? ' · ' . e($t['seat']) : '' ?></small></td>
            <td class="mono u-fs13"><a href="<?= url('/pro/billets/' . $t['id']) ?>" title="Fiche du billet"><?= e($t['code']) ?></a></td>
            <td><?= $t['status'] !== 'valid' ? badge('cancelled') : ($t['entries'] > 0 ? '<span class="badge green">' . e(short_date($t['checked_in_at'])) . '</span>' : '<span class="badge grey">Pas arrivé</span>') ?></td>
            <td class="right"><?php if ($t['status'] === 'valid'): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="cancel_ticket" data-confirm="Annuler ce billet ? Il sera refusé à l'entrée (pas de remboursement automatique).">Annuler</button></form><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$tickets): ?><tr><td colspan="5" class="empty">Les billets sont créés dès que le paiement est validé.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>

    <?php if ($paid && $refundable > 0): ?>
    <?php $hasSessions = count($sessions ?? []) > 1; $entered = array_filter($valid, fn($t) => (int)$t['entries'] > 0); ?>
    <?php if ($paid): ?>
    <details class="card card-details" id="modifier">
      <summary><h2>Modifier la commande</h2><span class="muted">ajouter des billets, changer de tarif<?= $hasSessions ? ' ou de date' : '' ?></span></summary>
      <div class="grid2 u-mt12">
        <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="add_tickets">
          <h3 class="u-m0">Ajouter des billets</h3>
          <div class="grid2"><div class="field mb0"><label>Tarif</label><select name="rate_id" required><?php foreach ($rates as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?> · <?= e(money((int)$r['price'])) ?></option><?php endforeach; ?></select></div>
            <div class="field mb0"><label>Nombre</label><input type="number" name="qty" min="1" max="50" value="1" required></div></div>
          <?php if ($hasSessions): ?><div class="field mb0"><label>Date</label><select name="session_id"><?php foreach ($sessions as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$o['session_id'] === (int)$s['id'] ? 'selected' : '' ?>><?= e(fr_date($s['starts_at'])) ?></option><?php endforeach; ?></select></div><?php endif; ?>
          <div class="field mb0"><label>Règlement</label><select name="pay"><option value="especes">Encaissé en espèces</option><option value="cb">Encaissé par carte</option><option value="cheque">Encaissé par chèque</option><option value="virement">Encaissé par virement</option><option value="lien">Lien de paiement envoyé à l'acheteur</option><option value="offert">Offert</option></select></div>
          <label class="check"><input type="checkbox" name="send" value="1" checked> <span>Envoyer les nouveaux billets par e-mail</span></label>
          <div><button class="btn btn-sm">Ajouter</button></div>
        </form>
        <div class="stack">
          <?php if ($valid): ?>
          <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="change_rate">
            <h3 class="u-m0">Changer le tarif d'un billet</h3>
            <div class="field mb0"><label>Billet</label><select name="ticket_id"><?php foreach ($valid as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e(trim($t['first_name'] . ' ' . $t['last_name']) ?: $t['code']) ?> · <?= e($t['rate_name']) ?> (<?= e(money((int)$t['price'])) ?>)</option><?php endforeach; ?></select></div>
            <div class="field mb0"><label>Nouveau tarif</label><select name="rate_id"><?php foreach ($rates as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?> · <?= e(money((int)$r['price'])) ?></option><?php endforeach; ?></select></div>
            <div class="field mb0"><label>Différence de prix</label><select name="diff"><option value="collect">Plus cher : encaissée sur place</option><option value="refund">Moins cher : remboursée</option><option value="free">Ni encaissée ni remboursée</option></select></div>
            <input type="hidden" name="pay_method" value="sur place">
            <div><button class="btn btn-sm btn-light">Changer le tarif</button></div>
          </form>
          <?php if ($hasSessions): ?>
          <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="change_session">
            <h3 class="u-m0">Changer de date</h3>
            <div class="field mb0"><label>Nouvelle date</label><select name="session_id"><?php foreach ($sessions as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e(fr_date($s['starts_at'])) ?><?= (int)$o['session_id'] === (int)$s['id'] ? ' (actuelle)' : '' ?></option><?php endforeach; ?></select></div>
            <small class="muted">Toute la commande, ou seulement les billets cochés :</small>
            <?php foreach ($valid as $t): ?><label class="check"><input type="checkbox" name="tickets[]" value="<?= (int)$t['id'] ?>"> <span><?= e(trim($t['first_name'] . ' ' . $t['last_name']) ?: $t['code']) ?> · <?= e($t['rate_name']) ?></span></label><?php endforeach; ?>
            <label class="check"><input type="checkbox" name="resend" value="1" checked> <span>Renvoyer les billets mis à jour</span></label>
            <div><button class="btn btn-sm btn-light">Déplacer</button></div>
          </form>
          <?php endif; endif; ?>
        </div>
      </div>
    </details>
    <?php endif; ?>
    <?php if ($paid || in_array($o['status'], ['pending', 'expired'], true)): $left = max(0, (int)$o['total'] - (int)$o['refunded_amount']); ?>
    <details class="card card-details" id="annuler">
      <summary><h2>Annuler la commande</h2><span class="muted"><?= $paid ? ((int)$o['total'] ? 'remboursement, avoir ou annulation simple' : 'invitation ou billets gratuits') : 'commande non payée : libère les places' ?></span></summary>
      <form method="post" class="stack u-mt12" data-cancel-form><?= csrf_field() ?><input type="hidden" name="action" value="cancel_full">
        <?php if ($paid && count($valid) > 1): ?>
          <fieldset class="cancel-set"><legend>Billets à annuler</legend>
            <?php foreach ($valid as $t): ?><label class="check"><input type="checkbox" name="tickets[]" value="<?= (int)$t['id'] ?>" checked data-price="<?= (int)$t['price'] ?>"> <span><?= e(trim($t['first_name'] . ' ' . $t['last_name']) ?: $t['code']) ?> · <?= e($t['rate_name']) ?> · <?= e(money((int)$t['price'])) ?><?= (int)$t['entries'] ? ' · <b class="u-red">déjà entré</b>' : '' ?></span></label><?php endforeach; ?>
          </fieldset>
        <?php endif; ?>
        <?php if ($paid && (int)$o['total'] > 0): ?>
          <fieldset class="cancel-set"><legend>Remboursement</legend>
            <label class="check"><input type="radio" name="mode" value="full" checked> <span><b>Rembourser</b> · <?= e(money($left)) ?><?= $o['method'] === 'online' ? ' sur le moyen de paiement' : ' à rendre (' . e(['especes' => 'espèces', 'cb' => 'carte bancaire', 'cheque' => 'chèque', 'virement' => 'virement'][$o['pay_method'] ?? ''] ?? 'sur place') . ')' ?></span></label>
            <?php if ((int)$o['fees'] > 0): ?><label class="check"><input type="radio" name="mode" value="nofees"> <span><b>Rembourser hors frais de service</b> · <?= e(money(max(0, $left - (int)$o['fees']))) ?> (frais gardés : <?= e(money((int)$o['fees'])) ?>)</span></label><?php endif; ?>
            <label class="check"><input type="radio" name="mode" value="partial"> <span><b>Rembourser un montant</b></span></label>
            <div class="field mb0" data-partial hidden><label>Montant</label><div class="input-suffix u-mw200"><input name="amount" inputmode="decimal" placeholder="0,00"><span>€</span></div><small>Jusqu'à <?= e(money($left)) ?></small></div>
            <label class="check"><input type="radio" name="mode" value="credit"> <span><b>Avoir</b> · carte cadeau de <?= e(money($left)) ?> envoyée à l'acheteur, utilisable sur tes prochains événements</span></label>
            <label class="check"><input type="radio" name="mode" value="none"> <span><b>Sans remboursement</b></span></label>
          </fieldset>
        <?php endif; ?>
        <div class="grid2"><div class="field mb0"><label>Motif</label><select name="reason" required><option value="">Choisis…</option><?php foreach (OrderOps::REASONS as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select></div>
          <div class="field mb0"><label>Précision <small class="muted">(facultatif)</small></label><input name="note" maxlength="200" placeholder="Visible par l'acheteur dans l'e-mail"></div></div>
        <label class="check"><input type="checkbox" name="notify" value="1" <?= is_email((string)$o['email']) ? 'checked' : 'disabled' ?>> <span>Prévenir l'acheteur par e-mail<?= is_email((string)$o['email']) ? '' : ' (pas d\'e-mail sur cette commande)' ?></span></label>
        <?php if ($entered): ?><label class="check"><input type="checkbox" name="force" value="1"> <span>Annuler quand même les billets déjà utilisés à l'entrée</span></label><?php endif; ?>
        <div><button class="btn btn-danger" data-confirm="Confirmer l'annulation ? Les billets annulés seront refusés à l'entrée.">Confirmer l'annulation</button></div>
      </form>
    </details>
    <?php endif; ?>
    <details class="card card-details" id="partiel">
      <summary><h2>Remboursement partiel</h2><span class="muted">Jusqu'à <?= e(money($refundable)) ?></span></summary>
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
    <div class="card"><h2>Réponses au formulaire</h2><?php foreach ($fieldsAns as $a): ?><div class="list-item"><span class="grow muted"><?= e($a['label']) ?></span><b><?= !empty($a['file']) ? '<a href="' . url('/pro/fichier/' . $a['file']) . '" target="_blank">' . e($a['value']) . '</a>' : e($a['value']) ?></b></div><?php endforeach; ?></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h2>Historique</h2></div>
      <ol class="timeline">
        <?php foreach ($history as $h): ?>
          <li><span class="tl-dot"></span><div><b><?= e(Audit::label($h['action'])) ?></b><?= $h['details'] ? ' · ' . e($h['details']) : '' ?><br><small class="muted"><?= e(short_date($h['created_at'])) ?><?= $h['user_name'] ? ' · ' . e($h['user_name']) : '' ?></small></div></li>
        <?php endforeach; ?>
        <?php if ($o['cancelled_at']): ?><li><span class="tl-dot red"></span><div><b><?= $o['status'] === 'refunded' ? 'Remboursée' : 'Annulée' ?></b><br><small class="muted"><?= e(short_date($o['cancelled_at'])) ?></small></div></li><?php endif; ?>
        <?php if ($o['refund_requested_at']): ?><li><span class="tl-dot orange"></span><div><b>Remboursement demandé par l'acheteur</b><br><small class="muted"><?= e(short_date($o['refund_requested_at'])) ?></small></div></li><?php endif; ?>
        <?php if ($o['paid_at']): ?><li><span class="tl-dot green"></span><div><b>Paiement validé</b> · <?= e(money((int)$o['total'])) ?><br><small class="muted"><?= e(short_date($o['paid_at'])) ?></small></div></li><?php endif; ?>
        <li><span class="tl-dot"></span><div><b>Commande créée</b><?= $o['utm_source'] ?? '' ? ' · via ' . e($o['utm_source']) : '' ?><?php if (!empty($o['referrer_order_id'])): $rf = one('SELECT ref, first_name, last_name FROM orders WHERE id = ?', [$o['referrer_order_id']]); if ($rf): ?> · invité par <a href="<?= url('/pro/commandes', ['q' => $rf['ref']]) ?>"><?= e(trim($rf['first_name'] . ' ' . $rf['last_name'])) ?></a><?php endif; endif; ?><?= (int)($o['group_discount'] ?? 0) ? ' · tarif groupe −' . e(money((int)$o['group_discount'])) : '' ?><?= (int)($o['exchanges'] ?? 0) ? ' · date changée ' . (int)$o['exchanges'] . ' fois' : '' ?><?= !empty($o['birth_date']) ? ' · ' . e(date('d/m/Y', strtotime((string)$o['birth_date']))) : '' ?><br><small class="muted"><?= e(short_date($o['created_at'])) ?></small></div></li>
      </ol>
    </div>
  </div>

  <div>
    <div class="card">
      <div class="card-head"><h2>Acheteur</h2><button type="button" class="link-btn" data-toggle="#buyer-edit">Modifier</button></div>
      <p class="mb0"><b><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b><br><?= $o['email'] ? '<a href="mailto:' . e($o['email']) . '">' . e($o['email']) . '</a>' : '<span class="muted">Pas d\'e-mail</span>' ?><?= $o['phone'] ? '<br>' . e($o['phone']) : '' ?></p>
      <form method="post" class="stack u-mt12" id="buyer-edit" hidden><?= csrf_field() ?><input type="hidden" name="action" value="buyer">
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
      <?php if ($o['discount']): ?><div class="summary-line u-green"><span>Réduction</span><span>−<?= e(money((int)$o['discount'])) ?></span></div><?php endif; ?>
      <?php if ($o['fees'] && $o['fee_mode'] === 'buyer'): ?><div class="summary-line"><span>Frais payés par l'acheteur</span><span><?= e(money((int)$o['fees'])) ?></span></div><?php endif; ?>
      <div class="summary-line total"><span>Total</span><span><?= e(money((int)$o['total'])) ?></span></div>
      <?php if ((int)$o['refunded_amount']): ?><div class="summary-line neg"><span>Remboursé</span><span>−<?= e(money((int)$o['refunded_amount'])) ?></span></div><?php endif; ?>
      <?php if ($o['method'] === 'online'): ?>
        <div class="summary-line"><span class="muted">Commission plateforme</span><span>−<?= e(money((int)$o['commission'])) ?></span></div>
        <div class="summary-line"><b>Pour toi</b><b><?= e(money((int)$o['organizer_amount'])) ?></b></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Étiquettes & note interne</h2>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="meta">
        <div class="field mb0"><label for="tags">Étiquettes <small class="muted">(séparées par des virgules)</small></label><input id="tags" name="tags" value="<?= e(implode(', ', $tags)) ?>" placeholder="VIP, presse, à rappeler…" maxlength="255"></div>
        <div class="field mb0"><label for="inote">Note interne <small class="muted">(jamais vue par l'acheteur)</small></label><textarea id="inote" name="internal_note" rows="3" placeholder="Ex. : a appelé pour changer de date, OK pour un geste…"><?= e((string)$o['internal_note']) ?></textarea></div>
        <button class="btn btn-sm btn-light">Enregistrer</button>
      </form>
    </div>

        <?php if ($o['status'] === 'pending' && ($o['channel'] ?? '') === 'reservation'): ?><div class="card resa-admin"><h2>Réservation à encaisser</h2><form method="post"><?= csrf_field() ?><p class="muted"> À régler avant le <?= e(fr_date((string)$o['expires_at'])) ?> · <?= e(money((int)$o['total'])) ?></p>
          <div class="row mt u-gap6"><select name="pay_method" class="u-wauto"><option value="virement">Virement</option><option value="cheque">Chèque</option><option value="especes">Espèces</option><option value="cb">CB</option></select><button class="btn btn-sm" name="action" value="resa_paid">Marquer payée</button></div>
          <div class="row mt u-gap6"><button class="btn btn-sm btn-light" name="action" value="resa_resend">Renvoyer les instructions</button><button class="btn btn-sm btn-ghost" name="action" value="resa_extend">Prolonger de 3 jours</button></div></form></div><?php endif; ?>
    <?php if ($paid): ?>
    <div class="card">
      <h2>Actions</h2>
      <form method="post" class="stack"><?= csrf_field() ?>
        <div class="field mb0"><label>Renvoyer les billets à</label><input type="email" name="email" value="<?= e($o['email']) ?>"></div>
        <button class="btn btn-block" name="action" value="resend">Renvoyer les billets</button>
        <a class="btn btn-block btn-light" href="<?= url('/commande/' . $o['ref'] . '/pdf', ['t' => $o['token']]) ?>" target="_blank">Voir le PDF</a>
        <a class="btn btn-block btn-light" href="<?= url('/commande/' . $o['ref'] . '/recu', ['t' => $o['token']]) ?>" target="_blank">Reçu</a>
        <a class="btn btn-block btn-light" href="<?= url('/pro/commandes/' . $o['id'] . '/ticket-caisse') ?>" target="_blank">Ticket de caisse (80 mm)</a>
        <a class="btn btn-block btn-light" href="<?= url('/pro/commandes/' . $o['id'] . '/billets-boca') ?>" target="_blank">Billets thermiques (Boca 152 × 54)</a>
        <a class="btn btn-block btn-light" href="#modifier" data-open-details="modifier">Modifier la commande</a>
        <a class="btn btn-block btn-danger" href="#annuler" data-open-details="annuler">Annuler ou rembourser…</a>
      </form>
      <div class="soon-row"><?php if (in_array($o['status'], ['paid', 'refunded'], true) && (int)$o['total'] > 0): ?><a class="btn btn-sm btn-light" href="<?= url('/commande/' . $o['ref'] . '/facture', ['t' => $o['token']]) ?>" target="_blank"><?= $o['invoice_number'] ? 'Facture ' . e($o['invoice_number']) : 'Établir la facture' ?></a><?php endif; ?><?php if (!empty($o['quote_id'])): ?><a class="btn btn-sm btn-ghost" href="<?= url('/pro/devis/' . (int)$o['quote_id']) ?>">Devis lié</a><?php endif; ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>
