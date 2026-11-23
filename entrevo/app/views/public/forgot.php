<div class="auth-box card">
  <h1>Trou de mémoire ? 🧠</h1>
  <?php if ($sent): ?><div class="flash success">Si un compte existe avec cette adresse, un lien vient de partir. 📬</div>
  <?php else: ?>
  <form method="post"><?= csrf_field() ?>
    <div class="field"><label for="email">Ton e-mail</label><input type="email" id="email" name="email" required autofocus></div>
    <button class="btn btn-block">Envoie-moi un lien</button>
  </form>
  <?php endif; ?>
  <p class="center mt mb0"><a href="<?= url('/connexion') ?>">← Retour à la connexion</a></p>
</div>
