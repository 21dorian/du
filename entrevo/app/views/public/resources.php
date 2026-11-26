<?php /** @var array $items */ ?>
<div class="container" style="max-width:960px;padding:28px 16px">
  <h1>Guides &amp; ressources</h1>
  <p class="muted">Des fiches pratiques pour réussir tes événements, de la mise en vente au bilan.</p>
  <div class="res-grid mt"><?php foreach ($items as $i => [$t, $p, $pts]): ?><details class="card res-card"<?= $i === 0 ? ' open' : '' ?>><summary><span class="res-n"><?= sprintf('%02d', $i + 1) ?></span><b><?= e($t) ?></b><span class="muted"><?= e($p) ?></span></summary><ul><?php foreach ($pts as $pt): ?><li><?= e($pt) ?></li><?php endforeach; ?></ul></details><?php endforeach; ?></div>
  <div class="org-cta mt"><div><h2>Besoin d'un coup de main ?</h2><span class="muted">Une démo de 20 minutes en visio, ou on migre tes données depuis ton ancienne billetterie.</span></div><div class="row"><a class="btn" href="<?= url('/demo') ?>">Démo en visio</a><a class="btn btn-ghost" href="<?= url('/migration') ?>">Migration</a></div></div>
</div>
