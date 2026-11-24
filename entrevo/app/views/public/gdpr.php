<?php /** @var array $v, $errors; bool $sent */ ?>
<section class="store-wrap" style="max-width:720px">
  <span class="eyebrow"><?= e(setting('platform_name', 'Entrevo')) ?></span>
  <h1>Tes données personnelles</h1>
  <?php if ($sent): ?>
    <div class="card"><h2>Vérifie ta boîte mail</h2><p>Nous venons d'envoyer un lien à <b><?= e($v['email']) ?></b> pour confirmer que la demande vient bien de toi. Une fois confirmée, elle est traitée sous un mois au plus.</p></div>
  <?php else: ?>
  <p class="lead">Tu peux obtenir une copie de tout ce que nous savons de toi, le faire corriger, effacer, ou ne plus recevoir de messages. Les montants de tes commandes restent conservés 10 ans pour la comptabilité, sans ton nom.</p>
  <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="card"><?= csrf_field() ?>
    <div class="field"><label>Ta demande</label><div class="cat-pick"><?php foreach (Gdpr::KINDS as $k => $l): ?><label class="seg-r"><input type="radio" name="kind" value="<?= $k ?>" <?= $v['kind'] === $k ? 'checked' : '' ?>><span><?= e($l) ?></span></label><?php endforeach; ?></div></div>
    <div class="field"><label>L'adresse e-mail utilisée pour tes achats</label><input type="email" name="email" required value="<?= e($v['email']) ?>"></div>
    <div class="field"><label>Précisions <small class="muted">(facultatif)</small></label><textarea name="message" rows="4"><?= e($v['message']) ?></textarea></div>
    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
    <button class="btn btn-lg">Envoyer la demande</button>
  </form>
  <?php endif; ?>
</section>
