<div class="auth-box">
  <form class="card" method="post"><?= csrf_field() ?>
    <h1>Code de vérification</h1>
    <p class="muted">Ouvre ton application d'authentification et saisis le code à 6 chiffres affiché pour <?= e(setting('platform_name', 'Entrevo')) ?>.</p>
    <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
    <div class="field"><input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus class="otp-input" placeholder="000000"></div>
    <button class="btn btn-block btn-lg">Valider</button>
    <p class="center mt mb0"><a href="<?= url('/connexion') ?>">Annuler</a> · <small class="muted">Téléphone perdu ? Contacte l'équipe de la plateforme.</small></p>
  </form>
</div>
