<div class="auth-box">
  <form class="card" method="post"><?= csrf_field() ?>
    <input type="hidden" name="suite" value="<?= e(input('suite')) ?>">
    <h1>Re-bonjour 👋</h1>
    <p class="muted">Organisateurs, équipes de contrôle, admins : c'est par ici.</p>
    <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
    <div class="field"><label for="email">E-mail</label><input type="email" id="email" name="email" value="<?= e(input('email')) ?>" required autocomplete="username" autofocus></div>
    <div class="field"><label for="password">Mot de passe</label><input type="password" id="password" name="password" required autocomplete="current-password"></div>
    <button class="btn btn-block btn-lg">J'entre</button>
    <p class="center mt mb0"><a href="<?= url('/mot-de-passe-oublie') ?>">Mot de passe oublié ? Ça arrive 🙃</a></p>
  </form>
  <?php if (setting('signup_open') === '1'): ?><p class="center muted mt">Pas encore de compte ? <a href="<?= url('/inscription') ?>">Lance ta billetterie, c'est gratuit</a></p><?php endif; ?>
</div>
