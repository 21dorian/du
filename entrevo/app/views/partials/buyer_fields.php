<div class="grid2">
  <div class="field"><label>Prénom</label><input name="first_name" required autocomplete="given-name" value="<?= e((string)($_POST['first_name'] ?? $_SESSION['buyer']['first_name'] ?? '')) ?>"></div>
  <div class="field"><label>Nom</label><input name="last_name" required autocomplete="family-name" value="<?= e((string)($_POST['last_name'] ?? $_SESSION['buyer']['last_name'] ?? '')) ?>"></div>
  <div class="field"><label>E-mail</label><input type="email" name="email" required autocomplete="email" value="<?= e((string)($_POST['email'] ?? $_SESSION['buyer']['email'] ?? '')) ?>"></div>
  <div class="field"><label>Téléphone <small class="muted">(facultatif)</small></label><input name="phone" autocomplete="tel" value="<?= e((string)($_POST['phone'] ?? $_SESSION['buyer']['phone'] ?? '')) ?>"></div>
</div>
<input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
