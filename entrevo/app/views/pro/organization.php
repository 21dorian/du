<form method="post"><?= csrf_field() ?>
<div class="split-3">
  <div>
    <div class="card">
      <h2>Infos publiques</h2>
      <div class="grid2">
        <div class="field"><label>Nom affiché *</label><input name="name" value="<?= e($org['name']) ?>" required></div>
        <div class="field"><label>E-mail de contact *</label><input type="email" name="email" value="<?= e($org['email']) ?>" required><small>Les acheteurs t'écrivent ici.</small></div>
        <div class="field"><label>Téléphone</label><input name="phone" value="<?= e($org['phone']) ?>"></div>
        <div class="field"><label>Site web</label><input name="website" value="<?= e($org['website']) ?>" placeholder="https://"></div>
      </div>
      <div class="field"><label>Présentation</label><textarea name="description" rows="4"><?= e($org['description']) ?></textarea></div>
      <p class="mb0"><a href="<?= url('/o/' . $org['slug']) ?>" target="_blank">Voir ta page publique ↗</a></p>
    </div>
    <div class="card">
      <h2>Infos légales & reversements 🏦</h2>
      <p class="muted">On s'en sert pour te reverser l'argent de tes ventes. Promis, on ne le dépense pas en confettis.</p>
      <div class="grid2">
        <div class="field"><label>Raison sociale</label><input name="legal_name" value="<?= e($org['legal_name']) ?>"></div>
        <div class="field"><label>SIRET / RNA</label><input name="siret" value="<?= e($org['siret']) ?>"></div>
        <div class="field"><label>N° TVA</label><input name="vat_number" value="<?= e($org['vat_number']) ?>"></div>
        <div class="field"><label>Adresse</label><input name="address" value="<?= e($org['address']) ?>"></div>
        <div class="field"><label>IBAN</label><input name="iban" value="<?= e($org['iban']) ?>" class="mono"></div>
        <div class="field"><label>BIC</label><input name="bic" value="<?= e($org['bic']) ?>" class="mono"></div>
      </div>
    </div>
  </div>
  <div class="card">
    <h2>Qui paie les frais ? 💸</h2>
    <p class="muted">Commission : <b><?= e(rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',')) ?> % + <?= e(money($fixed)) ?></b> par billet payant. Billets gratuits : 0 €.</p>
    <label class="check" style="margin-bottom:12px"><input type="radio" name="fee_mode" value="buyer" <?= $org['fee_mode'] === 'buyer' ? 'checked' : '' ?>> <span><b>L'acheteur</b><br><small>Billet à 20 € → l'acheteur paie <?= e(money(2000 + Tickets::computeFees($org, 2000, 1))) ?>, tu reçois 20,00 €.</small></span></label>
    <label class="check"><input type="radio" name="fee_mode" value="organizer" <?= $org['fee_mode'] === 'organizer' ? 'checked' : '' ?>> <span><b>Moi, l'organisateur</b><br><small>Billet à 20 € → l'acheteur paie 20,00 €, tu reçois <?= e(money(2000 - Tickets::computeFees($org, 2000, 1))) ?>.</small></span></label>
  </div>
</div>
<div class="form-actions mt"><button class="btn btn-lg">Enregistrer</button></div>
</form>
