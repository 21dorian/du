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
<div class="auth-box card soon-account">
  <span class="soon-tag big">Bientôt disponible</span>
  <h2>👤 Ton compte acheteur</h2>
  <p class="muted">Tous tes billets au même endroit, tes favoris, les organisateurs que tu suis, connexion sans mot de passe.</p>
  <div class="soon-row"><?= pub_soon('compte-acheteur', 'Me prévenir', 'btn btn-sm') ?><?= pub_soon('favoris', '❤️ Favoris') ?><?= pub_soon('suivre-orga', '🔔 Suivre') ?></div>
</div>
