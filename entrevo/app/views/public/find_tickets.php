<div class="auth-box card">
  <h1>Billets perdus ? 🕵️</h1>
  <?php if ($sent): ?>
    <div class="flash success">Si on a des commandes à cette adresse, un e-mail arrive avec tes billets. 📬</div>
    <p class="muted">Rien reçu ? Jette un œil dans les spams, ils adorent s'y cacher.</p>
  <?php else: ?>
    <p class="muted">Ça arrive aux meilleurs. Donne l'e-mail utilisé à l'achat, on te renvoie tout.</p>
    <form method="post"><?= csrf_field() ?>
      <div class="field"><label for="email">Adresse e-mail</label><input type="email" id="email" name="email" required autocomplete="email"></div>
      <button class="btn btn-block">Renvoyez-moi tout 🙏</button>
    </form>
  <?php endif; ?>
</div>
