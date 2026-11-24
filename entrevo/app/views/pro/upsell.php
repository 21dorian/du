<?php /** @var string $key; ?array $plan; array $plans */ ?>
<div class="card upsell">
  <span class="upsell-ico"><?= ui_icon('lock', 26) ?></span>
  <h2>Le module « <?= e(Billing::MODULES[$key] ?? $key) ?> » n'est pas inclus dans ta formule<?= $plan ? ' « ' . e($plan['name']) . ' »' : '' ?></h2>
  <p class="muted">Tout le reste continue de fonctionner normalement. Pour l'activer, passe à une formule qui le comprend : écris-nous, le changement est immédiat.</p>
  <?php if ($plans): ?>
  <div class="plan-cards">
    <?php foreach ($plans as $p): ?>
      <div class="plan-card"><b><?= e($p['name']) ?></b><div class="plan-price"><?= (int)$p['monthly_price'] ? e(money((int)$p['monthly_price'])) . '<small> / mois</small>' : 'Sans abonnement' ?></div>
        <?php if ($p['description']): ?><p class="muted"><?= e($p['description']) ?></p><?php endif; ?></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <a class="btn" href="<?= url('/pro/aide', ['sujet' => 'Changer de formule : ' . (Billing::MODULES[$key] ?? $key)]) ?>">Demander ce module</a>
</div>
