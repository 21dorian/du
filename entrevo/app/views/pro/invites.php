<div class="split">
  <form class="card" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="invite">
    <h2>Envoyer des invitations</h2>
    <p class="muted" style="font-size:14px">Une ligne par invité : <code>prénom ; nom ; e-mail ; nombre</code> (le nombre est facultatif). Ou importe un fichier CSV avec ces colonnes.</p>
    <div class="grid2">
      <div class="field"><label>Tarif</label><select name="rate_id"><?php foreach ($rates as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
      <?php if ($sessions): ?><div class="field"><label>Date</label><select name="session_id"><?php foreach ($sessions as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e(short_date($x['starts_at'])) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    </div>
    <div class="field"><label>Invités</label><textarea name="guests" rows="7" placeholder="Léa ; Martin ; lea@exemple.fr ; 2&#10;Hugo ; Durand ; hugo@exemple.fr"></textarea></div>
    <div class="field"><label>ou fichier CSV</label><input type="file" name="csv" accept=".csv,.txt"></div>
    <div class="field"><label>Note interne</label><input name="note" placeholder="Presse, partenaires, artistes…"></div>
    <label class="check mb"><input type="checkbox" name="send" value="1" checked> Envoyer les billets par e-mail</label>
    <label class="check mb"><input type="checkbox" name="force" value="1"> Dépasser la jauge si besoin</label>
    <button class="btn btn-block">Créer les invitations</button>
  </form>
  <div>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="partner">
      <h2>Nouveau partenaire</h2>
      <p class="muted" style="font-size:14px">Un CE, un sponsor, une asso, un revendeur… reçoit un lien perso pour réserver ses billets lui-même, dans la limite de son lot.</p>
      <div class="grid2"><div class="field"><label>Nom *</label><input name="name" required></div><div class="field"><label>E-mail</label><input type="email" name="email"></div>
        <div class="field"><label>Tarif</label><select name="rate_id"><?php foreach ($rates as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?> (<?= e(money((int)$r['price'])) ?>)</option><?php endforeach; ?></select></div>
        <div class="field"><label>Lot (places)</label><input type="number" name="quota" min="0" value="20"><small>0 = illimité</small></div></div>
      <label class="check mb"><input type="checkbox" name="free" value="1" checked> Billets offerts (sinon : payants au prix du tarif)</label>
      <button class="btn btn-block">Créer le lien partenaire</button>
    </form>
    <div class="card"><h2>Partenaires</h2>
      <?php foreach ($partners as $pa): $link = abs_url('/p/' . $pa['token']); ?>
        <div class="list-item" style="<?= $pa['active'] ? '' : 'opacity:.5' ?>"><span class="grow"><b><?= e($pa['name']) ?></b> · <?= e($pa['rate_name']) ?> <?= $pa['free'] ? '' : '' ?><br><small><?= (int)$pa['used'] ?><?= $pa['quota'] ? ' / ' . (int)$pa['quota'] : '' ?> places utilisées</small></span>
          <button type="button" class="btn btn-sm btn-light" data-copy="<?= e($link) ?>">Lien</button>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="partner_id" value="<?= (int)$pa['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="partner_toggle"><?= $pa['active'] ? 'Couper' : 'Réactiver' ?></button></form></div>
      <?php endforeach; ?>
      <?php if (!$partners): ?><p class="muted mb0">Aucun partenaire.</p><?php endif; ?>
    </div>
  </div>
</div>
<div class="card mt"><h2>Dernières invitations</h2><div class="table-wrap"><table class="table">
  <thead><tr><th>Invité</th><th>E-mail</th><th class="num">Billets</th><th>Date</th><th>Note</th></tr></thead><tbody>
  <?php foreach ($invites as $o): ?><tr><td><a href="<?= url('/pro/commandes/' . $o['id']) ?>"><?= e($o['first_name'] . ' ' . $o['last_name']) ?></a></td><td><?= e($o['email']) ?></td><td class="num"><?= (int)$o['n'] ?></td><td><?= e(short_date($o['created_at'])) ?></td><td><small><?= e($o['note']) ?></small></td></tr><?php endforeach; ?>
  <?php if (!$invites): ?><tr><td colspan="5" class="empty">Aucune invitation envoyée.</td></tr><?php endif; ?>
  </tbody></table></div></div>
