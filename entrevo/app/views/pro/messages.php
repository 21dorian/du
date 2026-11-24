<div class="split-3">
  <form class="card" method="post"><?= csrf_field() ?>
    <h2>Écrire aux participants</h2>
    <div class="grid3">
      <div class="field"><label>Qui ?</label><select name="who"><option value="all">Tous les détenteurs de billets</option><option value="out">Ceux pas encore arrivés</option><option value="in">Ceux déjà entrés</option></select></div>
      <div class="field"><label>Tarif</label><select name="rate"><option value="">Tous</option><?php foreach ($rates as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
      <?php if ($sessions): ?><div class="field"><label>Date</label><select name="session"><option value="">Toutes</option><?php foreach ($sessions as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e(short_date($x['starts_at'])) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    </div>
    <div class="field"><label>Objet</label><input name="subject" required placeholder="Infos pratiques pour samedi"></div>
    <div class="field"><label>Message</label><textarea name="body" rows="10" required placeholder="Salut ! Les portes ouvrent à 19 h…&#10;&#10;Astuce : {prenom} est remplacé par le prénom."></textarea></div>
    <p class="muted" style="font-size:14px">Environ <b><?= (int)$preview ?></b> destinataire(s) avec tous les participants (un seul e-mail par adresse).</p>
    <div class="form-actions"><button class="btn" data-confirm="Envoyer ce message maintenant ?">Envoyer</button><button class="btn btn-ghost" name="action" value="test">M'envoyer un test</button></div>
  </form>
  <div class="card"><h2>Historique</h2>
    <?php foreach ($history as $m): ?><div class="list-item"><span class="grow"><b><?= e($m['subject']) ?></b><br><small><?= e(short_date($m['created_at'])) ?> · <?= e($m['user_name']) ?> · <?= (int)$m['sent'] ?> envoyé(s)<?= $m['failed'] ? ', ' . (int)$m['failed'] . ' échec(s)' : '' ?></small></span></div><?php endforeach; ?>
    <?php if (!$history): ?><p class="muted mb0">Aucun message envoyé.</p><?php endif; ?>
  </div>
</div>
