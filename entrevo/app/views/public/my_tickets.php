<?php $card = function (array $o, bool $past) { $t = strtotime((string)$o['start_at']); ob_start(); ?>
  <a class="my-card <?= $past ? 'past' : '' ?>" href="<?= url('/commande/' . $o['ref'], ['t' => $o['token']]) ?>">
    <span class="my-img"><?php if ($o['image']): ?><img src="<?= url($o['image']) ?>" alt="" loading="lazy"><?php endif; ?></span>
    <span class="my-body">
      <b><?= e($o['ev_name']) ?></b>
      <span class="muted"><?= e(fr_date((string)$o['start_at'])) ?><?= $o['venue'] ? ' · ' . e($o['venue']) : '' ?></span>
      <span class="my-tags">
        <?php if ($o['status'] === 'refunded'): ?><span class="badge grey">Remboursée</span>
        <?php else: ?><span class="badge <?= $past ? 'grey' : 'green' ?>"><?= (int)$o['n_tickets'] ?> billet<?= $o['n_tickets'] > 1 ? 's' : '' ?></span><?php endif; ?>
        <?php if (!$past && $t - time() < 7 * 86400 && $t > time()): ?><span class="badge orange"><?= e(ago_until($t)) ?></span><?php endif; ?>
        <?php if ($past && $o['status'] === 'paid' && !$o['review_id']): ?><span class="badge violet">Donne ton avis</span><?php endif; ?>
      </span>
    </span>
    <span class="my-arrow">→</span>
  </a>
<?php return ob_get_clean(); }; ?>
<div class="my-head">
  <div><span class="eyebrow">Mon espace</span><h1>Mes billets</h1><p class="muted">Connecté avec <b><?= e($me) ?></b> · <a href="<?= url('/mes-billets', ['sortir' => 1]) ?>">me déconnecter</a></p></div>
  <div class="my-stats"><div class="kpi-mini"><span>Événements</span><b><?= (int)$stats['events'] ?></b></div><div class="kpi-mini"><span>Billets</span><b><?= (int)$stats['tickets'] ?></b></div></div>
</div>

<h2>À venir</h2>
<?php if (!$upcoming && !$held): ?><div class="card empty">Rien de prévu pour l'instant… <a href="<?= url('/') ?>">Trouve ta prochaine sortie</a></div><?php endif; ?>
<div class="my-list"><?php foreach ($upcoming as $o) echo $card($o, false); ?>
<?php foreach ($held as $t): ?>
  <a class="my-card" href="<?= url('/billet/' . $t['holder_token']) ?>">
    <span class="my-img"><?php if ($t['image']): ?><img src="<?= url($t['image']) ?>" alt="" loading="lazy"><?php endif; ?></span>
    <span class="my-body"><b><?= e($t['ev_name']) ?></b><span class="muted"><?= e(fr_date((string)$t['start_at'])) ?></span><span class="my-tags"><span class="badge violet">Billet offert · <?= e($t['rate_name']) ?></span></span></span>
    <span class="my-arrow">→</span>
  </a>
<?php endforeach; ?></div>

<?php if ($past): ?>
<h2 class="mt">Déjà vécus</h2>
<div class="my-list"><?php foreach ($past as $o) echo $card($o, true); ?></div>
<?php endif; ?>
