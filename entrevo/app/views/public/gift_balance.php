<div class="auth-box card">
  <span class="eyebrow"><?= e($org['name']) ?></span>
  <h1>Solde de ta carte cadeau</h1>
  <?php if ($card): ?>
    <div class="giftcard sm"><small>Solde disponible</small><b><?= e(money((int)$card['balance'])) ?></b><span>sur <?= e(money((int)$card['initial'])) ?> · valable jusqu'au <?= e(date('d/m/Y', strtotime((string)$card['expires_at']))) ?></span></div>
    <p><a class="btn btn-block" href="<?= url('/o/' . $org['slug']) ?>">Choisir un événement</a></p>
  <?php else: ?>
    <?php if ($err): ?><div class="flash error"><?= e($err) ?></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?><div class="field"><label>Code de la carte</label><input name="code" required placeholder="KDO-XXXX-XXXX" style="text-transform:uppercase"></div><button class="btn btn-block">Voir le solde</button></form>
  <?php endif; ?>
</div>
