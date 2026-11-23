<?php
$jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$price = fn($ev) => $ev['min_price'] === null ? '' : ($ev['min_price'] === 0 ? 'Gratuit' : 'Dès ' . money($ev['min_price']));
$featured = $q === '' && $events ? $events[0] : null;
foreach ($events as $ev) if ($q === '' && $ev['image'] && (int)$ev['capacity'] > (int)($featured['capacity'] ?? 0)) $featured = $ev;
?>
<?php if ($featured): $ft = strtotime($featured['starts_at']); ?>
<section class="featured">
  <a class="featured-img" href="<?= url('/e/' . $featured['slug']) ?>"><?php if ($featured['image']): ?><img src="<?= url($featured['image']) ?>" alt="<?= e($featured['name']) ?>"><?php endif; ?></a>
  <div class="featured-body">
    <span class="eyebrow">🔥 À la une</span>
    <h1><?= e($featured['name']) ?></h1>
    <div class="featured-meta"><?= e(fr_date($featured['starts_at'])) ?></div>
    <div class="featured-meta" style="color:var(--muted)"><?= e(trim($featured['venue'] . ($featured['city'] ? ' · ' . $featured['city'] : ''), ' ·')) ?></div>
    <a class="btn btn-lg" href="<?= url('/e/' . $featured['slug']) ?>">Je prends ma place<?= $price($featured) ? ' — ' . e(mb_strtolower($price($featured))) : '' ?></a>
  </div>
</section>
<?php endif; ?>

<form class="searchbar" method="get" action="<?= url('/') ?>">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Un concert, une ville, un plan pour samedi…" aria-label="Rechercher">
  <button class="btn">Trouver 🔍</button>
</form>

<?php if ($cats): ?><div class="chips"><a class="chip <?= $cat === '' ? 'on' : '' ?>" href="<?= url('/') ?>">Tout</a><?php foreach ($cats as $c): ?><a class="chip <?= $cat === $c ? 'on' : '' ?>" href="<?= url('/', ['cat' => $c]) ?>"><?= e($c) ?></a><?php endforeach; ?></div><?php endif; ?>
<?php if ($q !== ''): ?><p class="muted"><?= count($events) ?> résultat<?= count($events) > 1 ? 's' : '' ?> pour « <?= e($q) ?> ». <a href="<?= url('/') ?>">Tout voir</a></p><?php endif; ?>
<?php if (!$events): ?>
  <div class="empty"><?= $q !== '' ? 'Rien trouvé 🤷 Essaie avec un autre mot, une autre ville.' : 'Calme plat pour l\'instant 👀 Reviens vite, ça bouge souvent.' ?></div>
<?php endif; ?>

<?php $day = null; foreach ($events as $ev): $t = strtotime($ev['starts_at']); $d = date('Y-m-d', $t); ?>
  <?php if ($d !== $day): $day = $d; ?>
    <div class="agenda-day"><h2><?= $jours[(int)date('w', $t)] ?> <?= date('j', $t) ?> <?= $mois[(int)date('n', $t)] ?></h2><span><?= $d === date('Y-m-d') ? '🔥 Ce soir' : ($d === date('Y-m-d', strtotime('+1 day')) ? 'Demain' : date('Y', $t)) ?></span></div>
  <?php endif; ?>
  <a class="agenda-row" href="<?= url('/e/' . $ev['slug']) ?>">
    <span class="agenda-thumb"><?php if ($ev['image']): ?><img src="<?= url($ev['image']) ?>" alt="" loading="lazy"><?php endif; ?></span>
    <span class="agenda-time"><?= $ev['session_count'] > 1 ? '📅' : date('H:i', $t) ?></span>
    <span><span class="agenda-title"><?= e($ev['name']) ?></span><span class="agenda-place"><?= $ev['session_count'] > 1 ? '<b>' . (int)$ev['session_count'] . ' dates</b> · ' : '' ?><?= e(trim($ev['venue'] . ($ev['city'] ? ' · ' . $ev['city'] : ''), ' ·')) ?> — <?= e($ev['org_name']) ?></span></span>
    <span class="agenda-price"><?= e($price($ev)) ?></span>
  </a>
<?php endforeach; ?>

<?php if (setting('signup_open') === '1'): ?>
<div class="org-cta">
  <div><h2>Tu organises un truc ? 🎤</h2><span class="muted">Mets tes billets en vente en 5 minutes, scanne les entrées avec ton téléphone. Pas d'abonnement, pas de prise de tête.</span></div>
  <a class="btn btn-ghost btn-lg" href="<?= url('/inscription') ?>">Je lance ma billetterie</a>
</div>
<?php endif; ?>
