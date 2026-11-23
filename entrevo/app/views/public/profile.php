<form class="card" method="post" style="max-width:640px"><?= csrf_field() ?>
  <h2>Mes informations</h2>
  <div class="grid2">
    <div class="field"><label>Nom</label><input name="name" value="<?= e($u['name']) ?>" required></div>
    <div class="field"><label>E-mail de connexion</label><input type="email" name="email" value="<?= e($u['email']) ?>" required></div>
  </div>
  <h3 class="mt">Changer de mot de passe</h3>
  <div class="grid2">
    <div class="field"><label>Mot de passe actuel</label><input type="password" name="current_password" autocomplete="current-password"></div>
    <div class="field"><label>Nouveau mot de passe</label><input type="password" name="new_password" minlength="8" autocomplete="new-password"><small>Laisser vide pour ne pas changer.</small></div>
  </div>
  <button class="btn">Enregistrer</button>
</form>
