<div class="auth-box card">
  <h1>Nouveau mot de passe</h1>
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <div class="field"><label>Nouveau mot de passe</label><input type="password" name="password" required minlength="8" autocomplete="new-password"></div>
    <div class="field"><label>Confirmation</label><input type="password" name="password2" required minlength="8" autocomplete="new-password"></div>
    <button class="btn btn-block">C'est noté</button>
  </form>
</div>
