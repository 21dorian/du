<div class="amb">
  <span class="eyebrow"><?= e($org['name']) ?> · <?= e($ev['name']) ?></span>
  <h1>Bonjour <?= e($lk['name']) ?></h1>
  <p class="muted">Voici ce que ton lien a rapporté. Les chiffres se mettent à jour à chaque vente.</p>
  <div class="amb-stats">
    <div class="card"><small>Personnes venues par ton lien</small><b><?= (int)$lk['clicks'] ?></b></div>
    <div class="card"><small>Billets vendus grâce à toi</small><b><?= (int)$st['tickets'] ?></b></div>
    <div class="card"><small>Commandes</small><b><?= (int)$st['n'] ?></b></div>
    <?php if ($rank): ?><div class="card"><small>Ton classement</small><b><?= $rank[0] ?><span class="muted"> / <?= $rank[1] ?></span></b></div><?php endif; ?>
  </div>
  <?php if ($lk['reward']): ?><div class="card amb-reward"><small>Ta récompense</small><p><?= e($lk['reward']) ?></p></div><?php endif; ?>
  <div class="card amb-share">
    <h2>Ton lien</h2>
    <div class="invite-link"><input readonly value="<?= e($url) ?>" aria-label="Ton lien"><button type="button" class="btn btn-sm" data-copy="<?= e($url) ?>">Copier</button></div>
    <?php if ($lk['promo_code']): ?><p class="muted">Ceux qui passent par ton lien profitent du code <b><?= e($lk['promo_code']) ?></b>.</p><?php endif; ?>
    <div class="amb-qr"><?= Qr::svg($url, 200) ?></div>
  </div>
</div>
