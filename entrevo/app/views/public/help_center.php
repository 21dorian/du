<?php /** @var array $faq; string $q */ ?>
<div class="container" style="max-width:860px;padding:28px 16px">
  <h1>Centre d'aide</h1>
  <form class="row" style="margin:14px 0 22px"><input name="q" value="<?= e($q) ?>" placeholder="Billet perdu, remboursement, accès…" class="grow input-big"><button class="btn">Chercher</button></form>
  <div class="help-quick"><a class="card" href="<?= url('/mes-billets') ?>"><b>Retrouver mes billets</b><span class="muted">Renvoi immédiat par e-mail</span></a><a class="card" href="<?= url('/contact') ?>"><b>Écrire au support</b><span class="muted">Réponse sous 24 h ouvrées</span></a><a class="card" href="<?= url('/favoris') ?>"><b>Mes favoris</b><span class="muted">Les événements mis de côté</span></a></div>
  <?php foreach ($faq as $sec => $items): ?><h2 class="mt"><?= e($sec) ?></h2>
    <?php foreach ($items as [$qq, $a]): ?><details class="faq-item card"><summary><b><?= e($qq) ?></b></summary><p class="muted"><?= e($a) ?></p></details><?php endforeach; ?>
  <?php endforeach; ?>
  <?php if (!$faq): ?><div class="empty">Rien trouvé pour « <?= e($q) ?> ». <a href="<?= url('/contact') ?>">Écris-nous</a>, on te répond vite.</div><?php endif; ?>
</div>
