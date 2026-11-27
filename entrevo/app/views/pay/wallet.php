<?php /** @var array $w, $tx, $sales, $bracelets, $totals, $ev; ?array $ticket; string $base */
$blocked = $w['status'] === 'blocked'; ?>
<p><a href="<?= url($base . '/porte-monnaie') ?>">← Porte-monnaie</a></p>
<div class="card pwallet-head">
  <div class="pcardv<?= $blocked ? ' blocked' : '' ?>"><div class="row" style="justify-content:space-between"><small><?= e($ev['name']) ?></small><small><?= e(Pay::PROFILES[$w['profile']] ?? '') ?></small></div>
    <div><small>Solde</small><br><b class="bal"><?= e(money((int)$w['balance'])) ?></b></div>
    <div class="row" style="justify-content:space-between;align-items:flex-end"><span class="code"><?= e(chunk_split($w['code'], 4, ' ')) ?></span><small><?= e(Pay::holder($w)) ?></small></div></div>
  <div class="grow stack">
    <div class="row" style="flex-wrap:wrap;gap:6px">
      <?= $blocked ? '<span class="ptag bad">Bloqué' . ($w['blocked_reason'] ? ' · ' . e($w['blocked_reason']) : '') . '</span>' : ($w['status'] === 'active' ? '<span class="ptag ok">Actif</span>' : '<span class="ptag warn">' . e($w['status'] === 'refund_req' ? 'Remboursement demandé' : ($w['status'] === 'refunded' ? 'Remboursé' : $w['status'])) . '</span>') ?>
      <?= $w['nfc_uid'] ? '<span class="ptag vio">Bracelet ' . e($w['nfc_uid']) . '</span>' : '<span class="ptag">Sans bracelet</span>' ?>
      <?= (int)$w['virtual'] ? '<span class="ptag">Carte virtuelle</span>' : '' ?>
      <?= $ticket ? '<span class="ptag">Billet ' . e($ticket['code']) . ' · commande ' . e($ticket['ref']) . '</span>' : '' ?>
    </div>
    <div class="pk u-m0"><div><span>Rechargé</span><b><?= e(money((int)$totals['loaded'])) ?></b></div><div><span>Dépensé</span><b><?= e(money((int)$totals['spent'])) ?></b></div><div><span>Opérations</span><b><?= count($tx) ?></b></div></div>
    <div class="row" style="flex-wrap:wrap">
      <a class="btn btn-sm btn-light" target="_blank" href="<?= Pay::url('/w/' . $w['token']) ?>">Voir le portail du festivalier</a>
      <?php if ($w['email']): ?><form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-light" name="action" value="send">Renvoyer le lien par e-mail</button></form><?php endif; ?>
      <?php if ($blocked): ?><form method="post"><?= csrf_field() ?><button class="btn btn-sm" name="action" value="unblock">Débloquer</button></form>
      <?php else: ?><form method="post" class="row" data-confirm="Bloquer ce porte-monnaie ? Il ne pourra plus payer."><?= csrf_field() ?><input type="hidden" name="action" value="block"><input name="reason" placeholder="Motif (perdu, volé…)" class="u-mw200"><button class="btn btn-sm btn-red">Bloquer</button></form><?php endif; ?>
    </div>
  </div>
</div>
<div class="pactions mt">
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="credit"><h2>Créditer ou corriger</h2>
    <div class="seg seg-full"><label class="seg-r"><input type="radio" name="sign" value="+" checked><span>Ajouter</span></label><label class="seg-r"><input type="radio" name="sign" value="-"><span>Retirer</span></label></div>
    <div class="input-suffix"><input name="amount" inputmode="decimal" placeholder="Montant" required><span>€</span></div>
    <select name="kind"><option value="adjust">Correction (erreur de caisse…)</option><option value="gift">Crédit offert (geste commercial)</option></select>
    <input name="note" placeholder="Motif (obligatoire pour une correction)"><button class="btn btn-sm">Enregistrer</button></form>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="bracelet"><h2>Bracelet</h2>
    <p class="pnote u-m0">Bracelet perdu ou cassé : associe un nouveau bracelet, l'ancien est désactivé et le solde suit.</p>
    <input name="ref" placeholder="N° imprimé ou UID NFC du nouveau bracelet" required><button class="btn btn-sm">Associer</button>
    <?php if ($w['nfc_uid']): ?><button class="btn btn-sm btn-light" name="action" value="unlink" formnovalidate>Dissocier le bracelet actuel</button><?php endif; ?></form>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="transfer"><h2>Transférer le solde</h2>
    <p class="pnote u-m0">Vers une autre carte, un autre billet ou bracelet du même événement.</p>
    <input name="to" placeholder="Code, n° ou UID du destinataire" required><div class="input-suffix"><input name="amount" inputmode="decimal" placeholder="Montant" value="<?= e(money_input((int)$w['balance'])) ?>"><span>€</span></div><button class="btn btn-sm">Transférer</button></form>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="profile"><h2>Fiche</h2>
    <input name="holder_name" value="<?= e((string)$w['holder_name']) ?>" placeholder="Nom du titulaire"><input name="email" type="email" value="<?= e((string)$w['email']) ?>" placeholder="E-mail"><input name="phone" value="<?= e((string)$w['phone']) ?>" placeholder="Téléphone">
    <select name="profile"><?php foreach (Pay::PROFILES as $pk => $pl): ?><option value="<?= $pk ?>" <?= $w['profile'] === $pk ? 'selected' : '' ?>><?= e($pl) ?></option><?php endforeach; ?></select><button class="btn btn-sm">Enregistrer</button></form>
  <?php if ($w['status'] === 'refund_req'): ?><form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="refund_done"><h2>Remboursement demandé</h2><p class="pnote">IBAN <span class="mono"><?= e((string)$w['refund_iban']) ?></span> · <?= e(money((int)$w['balance'])) ?></p><button class="btn btn-sm">Marquer comme viré</button></form><?php endif; ?>
</div>
<div class="card table-card mt"><div class="card-head"><h2>Historique</h2></div><div class="table-wrap"><table class="table ptx"><thead><tr><th>Date</th><th>Opération</th><th>Détail</th><th>Point de vente</th><th class="num">Montant</th></tr></thead><tbody>
  <?php foreach ($tx as $t): $it = json_decode((string)$t['items'], true) ?: []; ?><tr class="<?= (int)$t['undone'] ? 'muted' : '' ?>">
    <td><?= e(date('d/m H:i', strtotime($t['created_at']))) ?></td><td><?= e(Pay::TX[$t['kind']] ?? $t['kind']) ?><?= (int)$t['undone'] ? ' <span class="ptag">annulé</span>' : '' ?></td>
    <td><small class="muted"><?= e(isset($it[0]['name']) ? implode(', ', array_map(fn($l) => ($l['qty'] ?? 1) . '× ' . ($l['name'] ?? ''), array_slice($it, 0, 4))) : implode(' · ', array_map(fn($k, $v) => $k . ' ' . (is_scalar($v) ? $v : ''), array_keys($it), $it))) ?><?= $t['who'] ? ' · par ' . e($t['who']) : '' ?></small></td>
    <td><?= e((string)$t['bar']) ?></td><td class="num <?= (int)$t['amount'] > 0 ? 'pos' : 'neg' ?>"><?= (int)$t['amount'] > 0 ? '+' : '' ?><?= e(money((int)$t['amount'])) ?></td></tr><?php endforeach; ?>
  <?php if (!$tx): ?><tr><td colspan="5" class="pempty">Aucune opération.</td></tr><?php endif; ?></tbody></table></div></div>
