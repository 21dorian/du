<?php /** @var array $all, $votes */ ?>
<div class="modules-hero">
  <div>
    <span class="eyebrow">🧰 Ta boîte à outils</span>
    <h2><?= (int)$nOn ?> modules déjà inclus, <?= (int)$nSoon ?> en préparation</h2>
    <p>Tout est compris dans ta commission, sans abonnement. Les modules « Bientôt » sont notre feuille de route : vote pour ceux qui te manquent, on développe d'abord les plus demandés 🗳️</p>
  </div>
  <div class="modules-hero-stats"><span><b><?= (int)$nOn ?></b> actifs</span><span><b><?= (int)$nSoon ?></b> à venir</span><span><b><?= count($votes) ?></b> de tes votes</span></div>
</div>
<nav class="pill-tabs" aria-label="Catégories">
  <a class="<?= $cat === '' ? 'on' : '' ?>" href="<?= url('/pro/modules') ?>">Tout</a>
  <?php foreach (Features::CATEGORIES as $k => [$em, $l]): ?><a class="<?= $cat === $k ? 'on' : '' ?>" href="<?= url('/pro/modules', ['cat' => $k]) ?>"><?= $em ?> <?= e($l) ?></a><?php endforeach; ?>
</nav>
<?php foreach (Features::CATEGORIES as $ck => [$cem, $cl]): if ($cat !== '' && $cat !== $ck) continue; $items = array_filter($all, fn($f) => $f['cat'] === $ck); ?>
  <section class="mod-section">
    <h2 class="mod-cat"><?= $cem ?> <?= e($cl) ?></h2>
    <div class="mod-grid">
      <?php foreach ($items as $k => $f): $soon = $f['status'] === 'soon'; $href = $soon ? '/pro/bientot/' . $k : $f['url']; ?>
        <a class="mod-card<?= $soon ? ' is-soon' : '' ?>" href="<?= url($href) ?>">
          <span class="mod-ico"><?= $f['emoji'] ?></span>
          <span class="mod-body"><b><?= e($f['name']) ?></b><span><?= e($f['pitch']) ?></span></span>
          <span class="mod-foot"><?php if ($soon): ?><span class="soon-pill">Bientôt<?= !empty($f['eta']) ? ' · ' . e($f['eta']) : '' ?></span><?= isset($votes[$k]) ? '<span class="voted">✓ Tu as voté</span>' : '<span class="muted">Voter →</span>' ?>
            <?php else: ?><span class="on-pill">● Actif</span><span class="muted">Ouvrir →</span><?php endif; ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
