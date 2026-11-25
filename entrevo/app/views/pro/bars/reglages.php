<?php /** @var array $settings, $bars; string $btab, $bbase */ echo view('pro/bars/_nav', compact('btab', 'bbase')); $s = $settings; ?>
<div class="split">
  <form method="post" class="card"><?= csrf_field() ?>
    <h2>Caisse</h2>
    <div class="field"><label>Moyens de paiement acceptés</label><div class="chips-row"><?php foreach (['cashless' => 'Cashless', 'card' => 'Carte bancaire', 'cash' => 'Espèces', 'allowance' => 'Crédits offerts'] as $k => $l): ?><label class="chip-check"><input type="checkbox" name="methods[]" value="<?= $k ?>" <?= in_array($k, $s['methods'], true) ? 'checked' : '' ?>><span><?= $l ?></span></label><?php endforeach; ?></div><small class="muted">Le cashless doit aussi être activé dans l'onglet Cashless.</small></div>
    <label class="check"><input type="checkbox" name="require_staff" value="1" <?= $s['require_staff'] ? 'checked' : '' ?>> <span><b>Connexion des serveurs par code</b><br><small class="muted">Chaque vente est au nom d'un serveur, les actions sensibles suivent ses droits.</small></span></label>
    <label class="check"><input type="checkbox" name="sessions" value="1" <?= $s['sessions'] ? 'checked' : '' ?>> <span><b>Sessions de caisse</b><br><small class="muted">Ouverture avec fond de caisse et clôture avec comptage des espèces.</small></span></label>
    <div class="grid2 mt"><div class="field"><label>Alerte « caisse pleine » au-delà de</label><div class="input-suffix"><input name="drop_alert" inputmode="decimal" value="<?= e(money_input((int)$s['drop_alert'])) ?>"><span>€</span></div><small class="muted">Espèces dans une caisse : pense à faire une remise en banque.</small></div>
      <div class="field"><label>TVA par défaut</label><div class="input-suffix"><input name="default_vat" inputmode="decimal" value="<?= e(str_replace('.', ',', (string)(float)$s['default_vat'])) ?>"><span>%</span></div><small class="muted">20 % boissons alcoolisées, 10 % softs et food consommés sur place.</small></div></div>
    <div class="field"><label>Motifs proposés pour un article offert</label><textarea name="offer_reasons" rows="4"><?= e((string)$s['offer_reasons']) ?></textarea><small class="muted">Un par ligne.</small></div>
    <h2 class="mt">Affichage</h2>
    <label class="check"><input type="checkbox" name="photos" value="1" <?= $s['photos'] ? 'checked' : '' ?>> <span>Photos des produits sur les touches</span></label>
    <label class="check"><input type="checkbox" name="big_buttons" value="1" <?= $s['big_buttons'] ? 'checked' : '' ?>> <span>Grandes touches (moins de produits par écran, plus faciles à viser)</span></label>
    <h2 class="mt">Ticket de caisse</h2>
    <div class="field"><div class="seg seg-full"><?php foreach (['off' => 'Pas de ticket', 'qr' => 'QR code à scanner', 'ask' => 'QR code + e-mail'] as $k => $l): ?><label class="seg-r"><input type="radio" name="receipt" value="<?= $k ?>" <?= $s['receipt'] === $k ? 'checked' : '' ?>><span><?= $l ?></span></label><?php endforeach; ?></div><small class="muted">Après chaque vente, la caisse affiche un QR code : le client le scanne pour voir, télécharger ou recevoir son ticket par e-mail. Avec « QR + e-mail », le serveur peut aussi saisir l'adresse du client.</small></div>
    <div class="field"><label>Mention en bas du ticket</label><textarea name="receipt_footer" rows="2" placeholder="Merci et bon festival ! Licence n° …"><?= e((string)$s['receipt_footer']) ?></textarea></div>
    <button class="btn">Enregistrer</button>
  </form>
  <div class="card"><h2>Points de vente</h2>
    <?php foreach ($bars as $b): ?><form method="post" class="row bar-edit"><?= csrf_field() ?><input type="hidden" name="bar_id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="action" value="bar"><input name="name" value="<?= e($b['name']) ?>" class="grow"><button class="btn btn-sm btn-light">Renommer</button><button class="btn btn-sm btn-ghost" name="action" value="bar_toggle"><?= (int)$b['active'] ? 'Fermer' : 'Ouvrir' ?></button></form><?php endforeach; ?>
    <form method="post" class="row mt"><?= csrf_field() ?><input type="hidden" name="action" value="bar"><input name="name" placeholder="Bar principal, food truck, stand merch…" required class="grow"><button class="btn btn-sm">Ajouter</button></form>
    <p class="muted mt mb0"><small>Un point de vente peut être un bar, un stand de restauration ou de merchandising. Chaque appareil est rattaché à un point de vente.</small></p></div>
</div>
