<?php /** @var array $q, $ev, $rates, $t, $org; ?array $order */ $lines = array_column(Quotes::lines($q), 'qty', 'rate_id'); $locked = in_array($q['status'], ['paid', 'declined'], true); ?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Statut</span><b><?= Quotes::badge($q['status']) ?></b></div>
  <div class="kpi-mini"><span>Places</span><b><?= $t['tickets'] ?></b><?= (int)$q['qty_wanted'] ? '<small class="muted">' . (int)$q['qty_wanted'] . ' demandées</small>' : '' ?></div>
  <div class="kpi-mini"><span>Total client</span><b><?= e(money($t['total'])) ?></b><?= $t['discount'] ? '<small class="muted">remise ' . e(money($t['discount'])) . '</small>' : '' ?></div>
  <div class="kpi-mini"><span>Événement</span><b style="font-size:16px"><?= e($ev['name']) ?></b><small class="muted"><?= e(short_date($ev['starts_at'])) ?></small></div>
</div>
<?php if ($q['message']): ?><div class="notice info mb"><b>Message du client :</b> <?= nl2br(e($q['message'])) ?></div><?php endif; ?>
<?php if ($q['status'] === 'accepted'): ?><div class="notice warn mb"><b>Accepté, virement attendu</b> de <?= e(money($t['total'] - $t['fees'])) ?> (référence <?= e($q['ref']) ?>). <form method="post" class="inline-form"><?= csrf_field() ?><button class="btn btn-sm" name="action" value="transfer_paid" data-confirm="Confirmer la réception du virement et envoyer les billets ?">Virement reçu : envoyer les billets</button></form></div><?php endif; ?>
<?php if ($order): ?><div class="notice info mb">Commande <a href="<?= url('/pro/commandes/' . $order['id']) ?>"><?= e($order['ref']) ?></a> · <?= badge($order['status']) ?></div><?php endif; ?>
<form method="post" class="split-3"><?= csrf_field() ?>
  <div class="card table-card">
    <div class="card-head"><h2>Places</h2><a class="btn btn-sm btn-ghost" href="<?= url('/pro/devis/' . $q['id'], ['pdf' => 1]) ?>" target="_blank">Aperçu PDF</a></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Tarif</th><th class="num">Prix</th><th class="num">Restant</th><th style="width:120px">Quantité</th></tr></thead><tbody>
      <?php foreach ($rates as $r): $left = $r['quota'] ? max(0, (int)$r['quota'] - (int)$r['sold']) : null; ?><tr><td><b><?= e($r['name']) ?></b><?= !$r['active'] ? ' <span class="badge grey">en pause</span>' : '' ?></td><td class="num"><?= e(money((int)$r['price'])) ?></td><td class="num"><?= $left === null ? '∞' : $left ?></td>
        <td><input type="number" name="qty[<?= (int)$r['id'] ?>]" min="0" value="<?= (int)($lines[$r['id']] ?? 0) ?>" <?= $locked ? 'disabled' : '' ?>></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <div class="quote-sum"><span>Sous-total <b><?= e(money($t['gross'])) ?></b></span><?php if ($t['discount']): ?><span>Remise <b>−<?= e(money($t['discount'])) ?></b></span><?php endif; ?><?php if ($t['fees']): ?><span>Frais <b><?= e(money($t['fees'])) ?></b></span><?php endif; ?><span class="qs-total">Total <b><?= e(money($t['total'])) ?></b></span></div>
  </div>
  <div class="card">
    <h2>Conditions</h2>
    <div class="grid2"><div class="field"><label>Remise de groupe</label><div class="input-suffix"><input name="discount_pct" inputmode="decimal" value="<?= e(rtrim(rtrim(number_format((float)$q['discount_pct'], 2, ',', ''), '0'), ',')) ?>" <?= $locked ? 'disabled' : '' ?>><span>%</span></div></div>
      <div class="field"><label>Valable jusqu'au</label><input type="date" name="valid_until" value="<?= e((string)$q['valid_until']) ?>" <?= $locked ? 'disabled' : '' ?>></div></div>
    <?php if ($sessions): ?><div class="field"><label>Date</label><select name="session_id" required <?= $locked ? 'disabled' : '' ?>><option value="">Choisir…</option><?php foreach ($sessions as $x): ?><option value="<?= (int)$x['id'] ?>" <?= (int)$q['session_id'] === (int)$x['id'] ? 'selected' : '' ?>><?= e(fr_date($x['starts_at'])) ?><?= $x['label'] ?? '' ? ' · ' . e($x['label']) : '' ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div class="field"><label>Paiement</label><select name="pay_mode" <?= $locked ? 'disabled' : '' ?>><?php foreach (['both' => 'Carte ou virement', 'card' => 'Carte uniquement', 'transfer' => 'Virement uniquement'] as $k => $l): ?><option value="<?= $k ?>" <?= $q['pay_mode'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><small>Par virement, l'argent arrive directement chez toi (pas de frais).</small></div>
    <h3 class="mt">Client</h3>
    <div class="field"><label>Structure</label><input name="company" value="<?= e((string)$q['company']) ?>"></div>
    <div class="field"><label>Contact</label><input name="contact_name" value="<?= e($q['contact_name']) ?>"><small><?= e($q['email']) ?><?= $q['phone'] ? ' · ' . e($q['phone']) : '' ?></small></div>
    <div class="field"><label>Adresse de facturation</label><textarea name="address" rows="2"><?= e((string)$q['address']) ?></textarea></div>
    <div class="field"><label>Mot sur le devis</label><textarea name="notes" rows="2" placeholder="Placement regroupé, accueil spécial…"><?= e((string)$q['notes']) ?></textarea></div>
    <?php if (!$locked): ?><div class="row"><button class="btn btn-light">Enregistrer</button><button class="btn" name="action" value="send"><?= $q['status'] === 'request' ? 'Envoyer le devis' : 'Renvoyer le devis' ?></button></div>
      <?php if ($q['status'] !== 'request'): ?><button class="linklike mt" name="action" value="decline">Classer comme refusé</button><?php endif; endif; ?>
  </div>
</form>
