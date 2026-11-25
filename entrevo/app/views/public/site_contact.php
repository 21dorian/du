<?php /** @var array $org, $s, $old */ ?>
<section class="site-contact">
  <div><h1 class="site-h1">Nous écrire</h1><p class="lead">Une question sur un événement, un partenariat, une demande particulière ? <?= e($org['name']) ?> te répond par e-mail.</p>
    <?php if ($org['phone'] ?? ''): ?><p class="muted"><?= e($org['phone']) ?></p><?php endif; ?>
    <p class="muted"><small>Pour un billet perdu, utilise plutôt <a href="<?= url('/mes-billets') ?>">Retrouver mes billets</a> : c'est immédiat.</small></p></div>
  <form method="post" class="card"><?= csrf_field() ?>
    <div class="grid2"><div class="field"><label>Nom</label><input name="name" required value="<?= e($old['name'] ?? '') ?>"></div><div class="field"><label>E-mail</label><input type="email" name="email" required value="<?= e($old['email'] ?? '') ?>"></div></div>
    <div class="field"><label>Sujet</label><input name="subject" value="<?= e($old['subject'] ?? '') ?>"></div>
    <div class="field"><label>Message</label><textarea name="body" rows="6" required><?= e($old['body'] ?? '') ?></textarea></div>
    <input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <?php if ($s['newsletter']): ?><label class="check"><input type="checkbox" name="newsletter" value="1"> <span>Je veux aussi recevoir les annonces de <?= e($org['name']) ?></span></label><?php endif; ?>
    <button class="btn btn-lg mt">Envoyer</button>
  </form>
</section>
