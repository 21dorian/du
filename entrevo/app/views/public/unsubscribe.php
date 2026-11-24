<div class="card center auth-box">
  <div class="state-mark"><?= ui_icon($done ? 'check' : 'mail', 30) ?></div>
  <h1><?= $done ? 'C\'est fait, plus d\'e-mails' : 'Se désinscrire ?' ?></h1>
  <p class="muted">Tu ne recevras plus les actus de <b><?= e($org['name']) ?></b> à l'adresse <b><?= e($email) ?></b>.<?= $done ? ' Tes billets et confirmations d\'achat, eux, continueront d\'arriver.' : '' ?></p>
  <?php if (!$done): ?><form method="post"><button class="btn btn-lg">Oui, me désinscrire</button></form>
  <?php else: ?><a class="btn btn-ghost" href="<?= url('/') ?>">Retour au site</a><?php endif; ?>
</div>
