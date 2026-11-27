<?php /** @var array $s, $ev; string $base */ ?>
<form method="post" class="pgrid"><?= csrf_field() ?>
  <div class="card stack">
    <h2>Cashless</h2>
    <label class="check"><input type="checkbox" name="cashless_enabled" value="1" <?= (int)$ev['cashless_enabled'] ? 'checked' : '' ?>> <span><b>Cashless actif</b><br><small class="muted">Billets, bracelets et cartes virtuelles servent à payer aux bars.</small></span></label>
    <div class="grid2"><div class="field"><label>Recharge minimum</label><div class="input-suffix"><input name="cashless_min_topup" inputmode="decimal" value="<?= e(money_input((int)$ev['cashless_min_topup'])) ?>"><span>€</span></div></div>
      <div class="field"><label>Solde maximum</label><div class="input-suffix"><input name="max_balance" inputmode="decimal" value="<?= e(money_input((int)$s['max_balance'])) ?>"><span>€</span></div></div></div>
    <div class="field"><label>Montants proposés à la recharge (€)</label><input name="topups" value="<?= e(implode(', ', array_map(fn($c) => money_input((int)$c), Pay::topups($ev)))) ?>"><small class="muted">Séparés par des virgules, ex. 10, 20, 30, 50.</small></div>
    <h2 class="mt">Remboursement des soldes</h2>
    <div class="grid2"><div class="field"><label>Demandes acceptées jusqu'au</label><input type="date" name="cashless_refund_until" value="<?= e((string)$ev['cashless_refund_until']) ?>"><small class="muted">Vide : pas de remboursement en ligne.</small></div>
      <div class="field"><label>Frais par remboursement</label><div class="input-suffix"><input name="refund_fee" inputmode="decimal" value="<?= e(money_input((int)$s['refund_fee'])) ?>"><span>€</span></div></div></div>
  </div>
  <div class="card stack">
    <h2>Portail festivalier</h2>
    <p class="pnote u-m0">Adresse : <a href="<?= Pay::url('/e/' . $ev['slug']) ?>" target="_blank"><?= e(Pay::absUrl('/e/' . $ev['slug'])) ?></a></p>
    <label class="check"><input type="checkbox" name="virtual" value="1" <?= $s['virtual'] ? 'checked' : '' ?>> <span><b>Cartes virtuelles</b><br><small class="muted">Sans billet ni bracelet : le festivalier crée sa carte avec son e-mail et paie avec le QR code sur son téléphone.</small></span></label>
    <label class="check"><input type="checkbox" name="transfer" value="1" <?= $s['transfer'] ? 'checked' : '' ?>> <span><b>Transfert entre amis</b><br><small class="muted">Envoyer une partie de son solde sur la carte ou le bracelet d'un ami.</small></span></label>
    <label class="check"><input type="checkbox" name="self_block" value="1" <?= $s['self_block'] ? 'checked' : '' ?>> <span><b>Blocage en autonomie</b><br><small class="muted">Bracelet perdu ou volé : le festivalier le bloque lui-même, son solde est protégé et se reporte sur un nouveau bracelet au point info.</small></span></label>
    <div class="field"><label>Message affiché sur le portail</label><textarea name="message" rows="3" placeholder="Ex. Points de recharge : entrée principale et bar central."><?= e((string)$s['message']) ?></textarea></div>
    <div class="field"><label>Couleur du portail</label><input type="color" name="brand_color" value="<?= e((string)$s['brand_color']) ?>"></div>
    <button class="btn">Enregistrer</button>
  </div>
</form>
