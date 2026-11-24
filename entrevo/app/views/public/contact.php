<?php /** @var array $v, $errors; ?array $done */ ?>
<section class="store-wrap" style="max-width:760px">
  <span class="eyebrow"><?= e(setting('platform_name', 'Entrevo')) ?></span>
  <h1>Une question ? On t'aide.</h1>
  <?php if ($done): ?>
    <div class="card"><h2>Demande envoyée</h2><p>Référence <b class="mono"><?= e($done['ref']) ?></b>. Tu vas recevoir un e-mail de confirmation, puis notre réponse, en général sous 24 h ouvrées.</p>
      <p><a class="btn" href="<?= url('/support/' . $done['token']) ?>">Suivre ma demande</a></p></div>
  <?php else: ?>
  <p class="lead">Billets pas reçus ? Commence par <a href="<?= url('/mes-billets') ?>">retrouver vos billets</a> : c'est immédiat. Sinon, écris-nous.</p>
  <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="card"><?= csrf_field() ?>
    <div class="field"><label>C'est à propos de…</label><div class="cat-pick">
      <?php foreach (Support::CATEGORIES as $k => $l): if ($k === 'formule') continue; ?><label class="seg-r"><input type="radio" name="category" value="<?= e($k) ?>" <?= $v['category'] === $k ? 'checked' : '' ?>><span><?= e($l) ?></span></label><?php endforeach; ?>
    </div></div>
    <div class="grid2">
      <div class="field"><label>Ton nom</label><input name="name" value="<?= e($v['name']) ?>" autocomplete="name"></div>
      <div class="field"><label>Ton e-mail</label><input type="email" name="email" required value="<?= e($v['email']) ?>" autocomplete="email"></div>
    </div>
    <div class="grid2">
      <div class="field"><label>Sujet</label><input name="subject" maxlength="190" value="<?= e($v['subject']) ?>" placeholder="En quelques mots"></div>
      <div class="field"><label>Référence de commande <small class="muted">(si tu en as une)</small></label><input name="order_ref" value="<?= e($v['order_ref']) ?>" class="mono" placeholder="AB12CD34"></div>
    </div>
    <div class="field"><label>Ton message</label><textarea name="message" rows="7" required><?= e($v['message']) ?></textarea></div>
    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
    <button class="btn btn-lg">Envoyer</button>
  </form>
  <?php endif; ?>
</section>
