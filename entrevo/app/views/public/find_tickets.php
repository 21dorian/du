<div class="auth-box card">
  <h1>Mes billets</h1>
  <?php if ($sent): ?>
    <div class="flash success">Si on a des billets à cette adresse, un lien vers ton espace vient de partir (valable 30 minutes).</div>
    <p class="muted">Rien reçu ? Jette un œil dans les spams, ils adorent s'y cacher.</p>
  <?php else: ?>
    <p class="muted">Pas de mot de passe à retenir : donne l'e-mail utilisé à l'achat, on t'envoie un lien qui ouvre ton espace avec <b>tous tes billets</b>, à venir et passés.</p>
    <form method="post"><?= csrf_field() ?>
      <div class="field"><label for="email">Adresse e-mail</label><input type="email" id="email" name="email" required autocomplete="email"></div>
      <button class="btn btn-block">Recevoir mon lien de connexion</button>
    </form>
  <?php endif; ?>
</div>
<div class="auth-box card my-perks">
  <h2>Dans ton espace</h2>
  <ul class="my-perks-list">
    <li>Tous tes billets, même ceux qu'on t'a offerts</li>
    <li>Changer de date, donner un billet, inviter des potes</li>
    <li>Donner ton avis après l'événement</li>
  </ul>
  <div class="soon-row"><a class="soon-chip" href="<?= url('/favoris') ?>">Mes favoris</a></div>
</div>
