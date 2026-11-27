<?php /** @var array $w, $ev, $org, $s, $tx; bool $ended, $canRefund, $hasBracelet */
$blocked = $w['status'] === 'blocked'; $refund = $w['status'] === 'refund_req'; ?>
<div class="pp-wcard<?= $blocked ? ' blocked' : '' ?>">
  <div class="pp-wrow"><small><?= e($ev['name']) ?></small><small><?= $hasBracelet ? 'Bracelet associé' : ((int)$w['virtual'] ? 'Carte virtuelle' : 'Billet') ?></small></div>
  <div class="pp-bal"><?= e(money((int)$w['balance'])) ?></div>
  <div class="pp-wrow"><span class="pp-code"><?= e(chunk_split($w['code'], 4, ' ')) ?></span><small><?= e(Pay::holder($w)) ?></small></div>
</div>
<?php if ($blocked): ?><div class="pp-card"><h2>Carte bloquée</h2><p style="margin:0">Ton solde est protégé. Passe au point info avec une pièce d'identité : il sera reporté sur un nouveau bracelet.</p></div><?php endif; ?>
<?php if ($refund): ?><div class="pp-card"><h2>Remboursement en cours</h2><p style="margin:0">Ta demande est enregistrée : le virement arrive sous quelques jours.</p></div><?php endif; ?>
<?php if (!$blocked && !$refund && $w['status'] === 'active'): ?>
  <div class="pp-card" style="text-align:center"><div class="pp-qr"><img src="<?= Pay::url('/w/' . $w['token'] . '/qr') ?>" alt="QR code de paiement"></div><p class="pp-note" style="margin:0">Présente ce QR code au bar<?= $hasBracelet ? ', ou approche ton bracelet du terminal' : '' ?>.</p></div>
  <?php foreach ($cOrders ?? [] as $co): if ($co['prep_status'] === 'served') continue; ?>
  <div class="pp-card" style="text-align:center"><small><?= e($co['bar']) ?> · <?= e(date('H:i', strtotime($co['created_at']))) ?></small><div style="font-size:44px;font-weight:800;line-height:1.1">N° <?= (int)$co['order_no'] ?></div>
    <p style="margin:4px 0 0"><?= $co['prep_status'] === 'ready' ? '<b>C\'est prêt ! Va le chercher au comptoir.</b>' : 'En préparation…' ?></p><small class="pp-note"><?= e(implode(', ', array_map(fn($l) => $l['qty'] . ' × ' . $l['name'], json_decode((string)$co['items'], true) ?: []))) ?></small></div>
  <?php endforeach; ?>
  <?php if (!empty($cBars) && $cMenu): ?>
  <details class="pp-card" id="commander"<?= empty($cOrders) ? ' open' : '' ?>><summary>Commander au bar sans faire la queue</summary>
    <form method="post" class="pp-stack"><?= csrf_field() ?><input type="hidden" name="action" value="collect">
      <?php if (count($cBars) > 1): ?><select name="bar_id"><?php foreach ($cBars as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select><?php else: ?><input type="hidden" name="bar_id" value="<?= (int)$cBars[0]['id'] ?>"><p class="pp-note" style="margin:0">À récupérer au bar <b><?= e($cBars[0]['name']) ?></b></p><?php endif; ?>
      <?php $cat = null; foreach ($cMenu as $p): if ($p['cat'] !== $cat): $cat = $p['cat']; ?><small style="font-weight:700;margin-top:6px"><?= e((string)($cat ?: 'Carte')) ?></small><?php endif; ?>
        <label class="pp-row" style="display:flex;align-items:center;gap:10px"><span style="flex:1"><?= e($p['name']) ?> <small class="pp-note"><?= e(money((int)$p['price'] + (int)$p['deposit'])) ?></small></span><input type="number" name="q[<?= (int)$p['id'] ?>]" min="0" max="20" value="0" style="width:70px"></label>
      <?php endforeach; ?>
      <button class="pp-btn">Commander et payer avec mon solde</button><p class="pp-note" style="margin:0">Tu reçois un numéro : il s'affiche sur l'écran du bar quand c'est prêt.</p>
    </form></details>
  <?php endif; ?>
  <?php if (!$ended && (int)$ev['cashless_enabled']): ?>
  <form method="post" class="pp-card pp-stack" id="recharger"><?= csrf_field() ?><input type="hidden" name="action" value="topup">
    <h2>Recharger</h2>
    <div class="pp-amounts"><?php foreach (Pay::topups($ev) as $i => $a): ?><label><input type="radio" name="amount" value="<?= (int)$a ?>" <?= $i === 1 ? 'checked' : '' ?>><span><?= e(rtrim(rtrim(number_format($a / 100, 2, ',', ''), '0'), ',')) ?> €</span></label><?php endforeach; ?></div>
    <label class="pp-amounts" style="grid-template-columns:1fr"><input type="radio" name="amount" value="libre" style="position:static;opacity:1;width:auto;display:none" id="free-r"></label>
    <input name="free" inputmode="decimal" placeholder="Autre montant (€)" oninput="document.getElementById('free-r').checked = this.value !== ''">
    <?php if (!$w['email']): ?><input name="email" type="email" placeholder="E-mail pour le reçu" required><?php endif; ?>
    <button class="pp-btn">Payer par carte</button><p class="pp-note" style="margin:0">Carte bancaire, Apple Pay ou Google Pay selon ton appareil. Recharge à partir de <?= e(money((int)$ev['cashless_min_topup'])) ?>.</p></form>
  <?php endif; ?>
  <?php if (!$hasBracelet): ?><details class="pp-card"><summary>Associer mon bracelet</summary><form method="post" class="pp-stack"><?= csrf_field() ?><input type="hidden" name="action" value="bracelet"><p>Saisis le numéro imprimé sur ton bracelet : ton solde le suit, tu paies en l'approchant du terminal.</p><input name="ref" placeholder="Numéro du bracelet" required><button class="pp-btn light">Associer</button></form></details><?php endif; ?>
  <?php if ($s['transfer'] && (int)$w['balance'] > 0): ?><details class="pp-card"><summary>Envoyer à un ami</summary><form method="post" class="pp-stack"><?= csrf_field() ?><input type="hidden" name="action" value="transfer"><p>Transfère une partie de ton solde sur la carte, le billet ou le bracelet d'un ami présent au même événement.</p>
    <input name="to" placeholder="Code de sa carte ou n° de bracelet" required><input name="amount" inputmode="decimal" placeholder="Montant (€)" required><button class="pp-btn light">Envoyer</button></form></details><?php endif; ?>
  <?php if ($s['self_block']): ?><details class="pp-card"><summary>Bracelet ou téléphone perdu</summary><form method="post" class="pp-stack" onsubmit="return confirm('Bloquer ta carte ? Plus personne ne pourra payer avec.')"><?= csrf_field() ?><input type="hidden" name="action" value="block"><p>Bloque ta carte tout de suite : ton solde est protégé et sera reporté sur un nouveau bracelet au point info.</p><button class="pp-btn red">Bloquer ma carte</button></form></details><?php endif; ?>
<?php endif; ?>
<?php if ($canRefund): ?><form method="post" class="pp-card pp-stack"><?= csrf_field() ?><input type="hidden" name="action" value="refund"><h2>Me faire rembourser</h2>
  <p>L'événement est terminé : demande le remboursement de ton solde (<?= e(money((int)$w['balance'])) ?><?= (int)$s['refund_fee'] ? ', frais de ' . e(money((int)$s['refund_fee'])) . ' déduits' : '' ?>) jusqu'au <?= e(fr_date($ev['cashless_refund_until'], false)) ?>.</p>
  <input name="iban" placeholder="IBAN" autocapitalize="characters" required><?php if (!$w['email']): ?><input name="email" type="email" placeholder="E-mail" required><?php endif; ?><button class="pp-btn">Demander le remboursement</button></form><?php endif; ?>
<div class="pp-card"><h2>Mes opérations</h2><div class="pp-tx">
  <?php foreach ($tx as $t): $it = json_decode((string)$t['items'], true) ?: []; ?><div><span><?= e(Pay::TX[$t['kind']] ?? $t['kind']) ?><small><?= e(date('d/m H:i', strtotime($t['created_at']))) ?><?= $t['bar'] ? ' · ' . e($t['bar']) : '' ?><?= isset($it[0]['name']) ? ' · ' . e(implode(', ', array_map(fn($l) => ($l['qty'] ?? 1) . '× ' . ($l['name'] ?? ''), array_slice($it, 0, 3)))) : '' ?></small></span><b class="<?= (int)$t['amount'] > 0 ? 'pos' : '' ?>"><?= (int)$t['amount'] > 0 ? '+' : '' ?><?= e(money((int)$t['amount'])) ?></b></div><?php endforeach; ?>
  <?php if (!$tx): ?><p class="pp-note">Aucune opération pour l'instant.</p><?php endif; ?></div></div>
