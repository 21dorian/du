<?php
$form = function (?array $r) use ($org, $sessions, $seatCats, $rates) {
    $p = $r ?? ['id' => 0, 'name' => '', 'description' => '', 'price' => 0, 'quota' => 0, 'max_per_order' => 0, 'sale_start' => null, 'sale_end' => null, 'hidden' => 0, 'active' => 1, 'sort' => 0,
        'price_mode' => 'fixed', 'pack_size' => 1, 'max_entries' => 1, 'per_day' => 0, 'session_ids' => '', 'seat_category' => '', 'scan_note' => '', 'scan_color' => '', 'vat_rate' => null, 'after_rate_id' => null, 'min_age' => null];
    $selS = array_map('intval', array_filter(explode(',', (string)$p['session_ids'])));
    ob_start(); ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="rate_id" value="<?= (int)$p['id'] ?>">
      <div class="grid3">
        <div class="field" style="grid-column:span 2"><label>Nom du tarif *</label><input name="name" value="<?= e($p['name']) ?>" required placeholder="Plein tarif, Réduit, VIP…"></div>
        <div class="field"><label>Prix <?= '' ?></label><div class="input-suffix"><input name="price" value="<?= e(money_input((int)$p['price'])) ?>" inputmode="decimal"><span>€</span></div><small>Prix libre : c'est le minimum.</small></div>
      </div>
      <div class="grid4">
        <div class="field"><label>Type de prix</label><select name="price_mode"><option value="fixed" <?= $p['price_mode'] !== 'free' ? 'selected' : '' ?>>Prix fixe</option><option value="free" <?= $p['price_mode'] === 'free' ? 'selected' : '' ?>>Prix libre</option></select></div>
        <div class="field"><label>Billets par achat (pack)</label><input type="number" name="pack_size" min="1" max="50" value="<?= (int)$p['pack_size'] ?>"><small>4 = « pack famille » à 4 billets.</small></div>
        <div class="field"><label>Entrées autorisées</label><input type="number" name="max_entries" min="1" max="100" value="<?= (int)$p['max_entries'] ?>"><small>3 = pass 3 entrées.</small></div>
        <div class="field"><label>&nbsp;</label><label class="check"><input type="checkbox" name="per_day" value="1" <?= $p['per_day'] ? 'checked' : '' ?>> 1 entrée par jour (pass multi-jours)</label></div>
      </div>
      <?php if ($sessions): ?><div class="field"><label>Valable pour les dates</label><div class="row" style="gap:6px 16px">
        <?php foreach ($sessions as $x): ?><label class="check" style="font-weight:400"><input type="checkbox" name="session_ids[]" value="<?= (int)$x['id'] ?>" <?= in_array((int)$x['id'], $selS, true) ? 'checked' : '' ?>> <?= e(short_date($x['starts_at'])) ?></label><?php endforeach; ?></div><small>Aucune cochée = toutes les dates.</small></div><?php endif; ?>
      <?php if ($seatCats): ?><div class="field"><label>Catégorie de place (plan de salle)</label><select name="seat_category"><option value="">Toutes les places</option><?php foreach ($seatCats as $c): ?><option value="<?= e($c['id']) ?>" <?= $p['seat_category'] === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
      <div class="field"><label>Description</label><input name="description" value="<?= e($p['description']) ?>" placeholder="Ex. : sur présentation d'un justificatif"></div>
      <div class="field" style="max-width:320px"><label>Âge minimum pour ce tarif</label><select name="min_age"><?php foreach (['' => 'Comme l\'événement', '12' => '12 ans et +', '16' => '16 ans et +', '18' => '18 ans et +', '21' => '21 ans et +'] as $k => $l): ?><option value="<?= $k ?>" <?= (string)($p['min_age'] ?? '') === (string)$k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="field" style="max-width:320px"><label>TVA de ce tarif</label><select name="vat_rate"><?php foreach (Vat::RATES as $k => $l): ?><option value="<?= e((string)$k) ?>" <?= ($p['vat_rate'] === null ? $k === '' : ($k !== '' && (float)$p['vat_rate'] === (float)$k)) ? 'selected' : '' ?>><?= e($l) ?><?= $k === '' ? ' (' . e(Vat::label($org['vat_rate'] !== null ? (float)$org['vat_rate'] : null)) . ')' : '' ?></option><?php endforeach; ?></select></div>
      <div class="grid3 scan-rule">
        <div class="field" style="grid-column:span 2"><label>Consigne affichée au contrôle</label><input name="scan_note" value="<?= e((string)($p['scan_note'] ?? '')) ?>" maxlength="160" placeholder="Ex. : Vérifier la carte étudiant · Remettre le bracelet VIP"><small>S'affiche en grand sur l'appli de scan quand ce billet passe.</small></div>
        <div class="field"><label>Couleur de bracelet</label><div class="color-pick"><?php foreach (['' => 'Aucune', '#e5372b' => 'Rouge', '#f08c00' => 'Orange', '#f5c400' => 'Jaune', '#1f9d55' => 'Vert', '#0032fa' => 'Bleu', '#7c3aed' => 'Violet', '#0f0f14' => 'Noir'] as $hex => $nm): ?>
          <label title="<?= $nm ?>"><input type="radio" name="scan_color" value="<?= $hex ?>" <?= (string)($p['scan_color'] ?? '') === $hex ? 'checked' : '' ?>><span style="<?= $hex ? 'background:' . $hex : '' ?>" class="<?= $hex ? '' : 'none' ?>" aria-label="<?= $nm ?>"></span></label><?php endforeach; ?></div><small>Le scanner affiche un bandeau de cette couleur.</small></div>
      </div>
      <div class="grid4">
        <div class="field"><label>Quota</label><input type="number" name="quota" min="0" value="<?= (int)$p['quota'] ?>"><small>0 = illimité</small></div>
        <div class="field"><label>Max. par commande</label><input type="number" name="max_per_order" min="0" value="<?= (int)$p['max_per_order'] ?>"><small>0 = selon l'événement</small></div>
        <div class="field"><label>Début des ventes</label><input type="datetime-local" name="sale_start" value="<?= e(dt_input($p['sale_start'])) ?>"></div>
        <div class="field"><label>Fin des ventes</label><input type="datetime-local" name="sale_end" value="<?= e(dt_input($p['sale_end'])) ?>"></div>
      </div>
      <?php $others = array_filter($rates, fn($x) => (int)$x['id'] !== (int)$p['id']); if ($others): ?>
      <div class="field tier-field"><label>Paliers (early bird)</label>
        <select name="after_rate_id"><option value="">Ouvert selon ses dates de vente</option><?php foreach ($others as $x): ?><option value="<?= (int)$x['id'] ?>" <?= (int)($p['after_rate_id'] ?? 0) === (int)$x['id'] ? 'selected' : '' ?>>S'ouvre quand « <?= e($x['name']) ?> » (<?= e(money((int)$x['price'])) ?>) est épuisé ou terminé</option><?php endforeach; ?></select>
        <small>Ex. : « Early bird » 100 places à 15 €, puis « Normal » à 20 € qui s'ouvre tout seul. Le palier suivant s'affiche grisé en attendant, et un compte à rebours apparaît la dernière semaine si le tarif a une date de fin.</small></div>
      <?php endif; ?>
      <div class="row" style="gap:22px;margin-bottom:14px">
        <label class="check"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> Actif (en vente)</label>
        <label class="check"><input type="checkbox" name="hidden" value="1" <?= $p['hidden'] ? 'checked' : '' ?>> Tarif secret (visible seulement avec un code d'accès)</label>
        <label style="display:flex;gap:8px;align-items:center;font-weight:500">Ordre <input type="number" name="sort" value="<?= (int)$p['sort'] ?>" style="width:80px"></label>
      </div>
      <div class="form-actions">
        <button class="btn"><?= $p['id'] ? 'Enregistrer' : 'Ajouter le tarif' ?></button>
        <?php if ($p['id']): ?><button class="btn btn-ghost" style="color:var(--red)" name="action" value="delete" formnovalidate data-confirm="Supprimer ce tarif ?">Supprimer</button><?php endif; ?>
      </div>
    </form>
<?php return ob_get_clean(); };
[$pct, $fixed] = Tickets::feeRates($org);
?>
<div class="notice mb">Frais de service : <b><?= e(rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',')) ?> % + <?= e(money($fixed)) ?></b> par billet payant,
  <?= $org['fee_mode'] === 'buyer' ? '<b>payés par l\'acheteur</b>' : '<b>déduits de ton reversement</b>' ?> (<a href="<?= url('/pro/organisation') ?>">changer</a>). Billets gratuits : 0 €, évidemment.</div>
<?php foreach ($rates as $r): $fee = Tickets::computeFees($org, (int)$r['price'], (int)$r['price'] > 0 ? 1 : 0); ?>
  <details class="box">
    <summary>
      <span><?= e($r['name']) ?> <?= !$r['active'] ? badge('draft') : '' ?> <?= $r['hidden'] ? '<span class="badge violet">Secret</span>' : '' ?><?php if ($r['after_rate_id']): $pv = array_values(array_filter($rates, fn($x) => (int)$x['id'] === (int)$r['after_rate_id'])); ?> <span class="badge blue">après « <?= e($pv[0]['name'] ?? '?') ?> »</span><?php endif; ?></span>
      <span class="row" style="gap:16px;font-weight:500">
        <span class="muted"><?= (int)$r['sold'] ?><?= $r['quota'] ? ' / ' . (int)$r['quota'] : '' ?> vendus</span>
        <span><?= (int)$r['price'] ? e(money((int)$r['price'])) : 'Gratuit' ?><?php if ($fee): ?> <small>(<?= $org['fee_mode'] === 'buyer' ? 'acheteur paie ' . e(money((int)$r['price'] + $fee)) : 'tu reçois ' . e(money((int)$r['price'] - $fee)) ?>)</small><?php endif; ?></span>
      </span>
    </summary>
    <?= $form($r) ?>
  </details>
<?php endforeach; ?>
<details class="box" <?= !$rates ? 'open' : '' ?>><summary style="color:var(--primary)">+ Ajouter un tarif</summary><?= $form(null) ?></details>
<?= soon_card('Plus de possibilités pour tes tarifs', [
  'prix-dynamique' => ['Tarification dynamique', 'Le prix suit la demande, entre un plancher et un plafond'],
  'paiement-plusieurs-fois' => ['Paiement en 3 fois', 'Pour les pass et les formules VIP'],
  'abonnements' => ['Inclus dans un abonnement', 'Le tarif est accessible aux abonnés de la saison'],
]) ?>
