<div class="auth-box wide">
  <div class="center mb"><h1>Lance ta billetterie</h1><p class="muted">Zéro abonnement. On se paie seulement quand tu vends : <b><?= e(rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',')) ?> %<?= $fixed ? ' + ' . e(money($fixed)) : '' ?></b> par billet payant. Les billets gratuits ? Gratuits. Logique.</p></div>
  <form class="card" method="post"><?= csrf_field() ?>
    <?php if ($errors): ?><div class="flash error"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
    <div class="field"><label for="org_name">Ton organisation</label><input id="org_name" name="org_name" value="<?= e(input('org_name')) ?>" required placeholder="Asso, salle, festival, boîte…"><small>C'est ce nom qui s'affiche sur tes pages et tes billets.</small></div>
    <div class="grid2">
      <div class="field"><label for="name">Ton nom</label><input id="name" name="name" value="<?= e(input('name')) ?>" required autocomplete="name"></div>
      <div class="field"><label for="phone">Téléphone</label><input type="tel" id="phone" name="phone" value="<?= e(input('phone')) ?>" autocomplete="tel"></div>
      <div class="field"><label for="email">E-mail</label><input type="email" id="email" name="email" value="<?= e(input('email')) ?>" required autocomplete="email"></div>
      <div class="field"><label for="password">Mot de passe</label><input type="password" id="password" name="password" required minlength="8" autocomplete="new-password"><small>8 caractères minimum.</small></div>
    </div>
    <label class="check"><input type="checkbox" name="terms" value="1" required> J'accepte les conditions d'utilisation<?= setting('cgv') ? ' (<a href="' . url('/cgv') . '" target="_blank">lire</a>)' : '' ?>.</label>
    <div style="height:16px"></div>
    <button class="btn btn-lg btn-block">Créer mon espace</button>
  </form>
  <p class="center muted mt">Déjà dans la place ? <a href="<?= url('/connexion') ?>">Connecte-toi</a></p>
</div>
