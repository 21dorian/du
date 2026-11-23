<?php
$form = function (?array $r) use ($org) {
    $p = $r ?? ['id' => 0, 'name' => '', 'description' => '', 'price' => 0, 'quota' => 0, 'max_per_order' => 0, 'sale_start' => null, 'sale_end' => null, 'hidden' => 0, 'active' => 1, 'sort' => 0];
    ob_start(); ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="rate_id" value="<?= (int)$p['id'] ?>">
      <div class="grid3">
        <div class="field" style="grid-column:span 2"><label>Nom du tarif *</label><input name="name" value="<?= e($p['name']) ?>" required placeholder="Plein tarif, Réduit, VIP…"></div>
        <div class="field"><label>Prix</label><div class="input-suffix"><input name="price" value="<?= e(money_input((int)$p['price'])) ?>" inputmode="decimal"><span>€</span></div></div>
      </div>
      <div class="field"><label>Description</label><input name="description" value="<?= e($p['description']) ?>" placeholder="Ex. : sur présentation d'un justificatif"></div>
      <div class="grid4">
        <div class="field"><label>Quota</label><input type="number" name="quota" min="0" value="<?= (int)$p['quota'] ?>"><small>0 = illimité</small></div>
        <div class="field"><label>Max. par commande</label><input type="number" name="max_per_order" min="0" value="<?= (int)$p['max_per_order'] ?>"><small>0 = selon l'événement</small></div>
        <div class="field"><label>Début des ventes</label><input type="datetime-local" name="sale_start" value="<?= e(dt_input($p['sale_start'])) ?>"></div>
        <div class="field"><label>Fin des ventes</label><input type="datetime-local" name="sale_end" value="<?= e(dt_input($p['sale_end'])) ?>"></div>
      </div>
      <div class="row" style="gap:22px;margin-bottom:14px">
        <label class="check"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> Actif (en vente)</label>
        <label class="check"><input type="checkbox" name="hidden" value="1" <?= $p['hidden'] ? 'checked' : '' ?>> Tarif réservé (visible uniquement avec un code d'accès)</label>
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
<div class="notice mb">Frais de service de la plateforme : <b><?= e(rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',')) ?> % + <?= e(money($fixed)) ?></b> par billet payant,
  <?= $org['fee_mode'] === 'buyer' ? '<b>ajoutés au prix payé par l\'acheteur</b>' : '<b>déduits de votre reversement</b>' ?> (<a href="<?= url('/pro/organisation') ?>">modifier</a>). Les billets gratuits sont sans frais.</div>
<?php foreach ($rates as $r): $fee = Tickets::computeFees($org, (int)$r['price'], (int)$r['price'] > 0 ? 1 : 0); ?>
  <details class="box">
    <summary>
      <span><?= e($r['name']) ?> <?= !$r['active'] ? badge('draft') : '' ?> <?= $r['hidden'] ? '<span class="badge violet">Réservé</span>' : '' ?></span>
      <span class="row" style="gap:16px;font-weight:500">
        <span class="muted"><?= (int)$r['sold'] ?><?= $r['quota'] ? ' / ' . (int)$r['quota'] : '' ?> vendus</span>
        <span><?= (int)$r['price'] ? e(money((int)$r['price'])) : 'Gratuit' ?><?php if ($fee): ?> <small>(<?= $org['fee_mode'] === 'buyer' ? 'acheteur paie ' . e(money((int)$r['price'] + $fee)) : 'vous recevez ' . e(money((int)$r['price'] - $fee)) ?>)</small><?php endif; ?></span>
      </span>
    </summary>
    <?= $form($r) ?>
  </details>
<?php endforeach; ?>
<details class="box" <?= !$rates ? 'open' : '' ?>><summary style="color:var(--primary)">+ Ajouter un tarif</summary><?= $form(null) ?></details>
