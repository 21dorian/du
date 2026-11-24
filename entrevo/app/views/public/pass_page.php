<div class="store-head">
  <span class="eyebrow"><?= e($org['name']) ?> · abonné n° <?= e($p['code']) ?></span>
  <h1><?= e($type['name']) ?></h1>
  <p class="lead">Bonjour <?= e((string)$p['first_name']) ?>. <?= $p['uses_left'] !== null ? 'Il te reste <b>' . (int)$p['uses_left'] . ' entrée' . ((int)$p['uses_left'] > 1 ? 's' : '') . '</b>.' : 'Ton pass est valable sur tous les événements ci-dessous.' ?></p>
</div>
<div class="my-list">
<?php foreach ($events as $ev): $b = $booked[$ev['id']] ?? null; ?>
  <div class="my-card">
    <span class="my-img"><?php if ($ev['image']): ?><img src="<?= url($ev['image']) ?>" alt=""><?php endif; ?></span>
    <span class="my-body"><b><?= e($ev['name']) ?></b><span class="muted"><?= e(fr_date($ev['starts_at'])) ?><?= $ev['venue'] ? ' · ' . e($ev['venue']) : '' ?></span></span>
    <?php if ($b): ?><a class="btn btn-sm btn-light" href="<?= url('/commande/' . $b['ref'], ['t' => $b['token']]) ?>">Mon billet</a>
    <?php elseif ($p['status'] === 'active' && ($p['uses_left'] === null || (int)$p['uses_left'] > 0)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>"><button class="btn btn-sm">Réserver ma place</button></form><?php endif; ?>
  </div>
<?php endforeach; ?>
<?php if (!$events): ?><div class="card empty">Pas d'événement à venir pour l'instant. Tu recevras un e-mail dès qu'un événement est publié.</div><?php endif; ?>
</div>
