<?php if ($result): ?>
<div class="card mb" id="update-result" style="border-color:var(--green)">
  <h2>Version <?= e($result['version']) ?> installée</h2>
  <p class="mb0">Avant : <?= e($result['previous']) ?> · <?= (int)$result['files'] ?> fichier(s) mis à jour<?= $result['deleted'] ? ', ' . (int)$result['deleted'] . ' supprimé(s)' : '' ?>. Sauvegarde : <span class="mono"><?= e($result['backup']) ?></span></p>
  <?php if ($result['notes']): ?><div class="notice mt prose"><?= e($result['notes']) ?></div><?php endif; ?>
  <p class="mt mb0" id="health" data-url="<?= url('/sante') ?>" data-rescue="<?= url('/maj-secours.php', ['jeton' => $result['rescue']]) ?>">Vérification que le site répond…</p>
</div>
<script>
(function(){var h=document.getElementById('health');fetch(h.dataset.url,{cache:'no-store'}).then(function(r){return r.json()}).then(function(j){
  h.innerHTML=j.ok?'Le site répond normalement (version '+j.version+'). Tout roule.':'Problème détecté. <a href="'+h.dataset.rescue+'">Revenir à la version précédente</a>';
}).catch(function(){h.innerHTML='Le site ne répond plus correctement. <a href="'+h.dataset.rescue+'"><b>Revenir immédiatement à la version précédente</b></a> (lien valable 1 h).';});})();
</script>
<?php endif; ?>
<div class="split-3">
  <div>
    <form class="card" method="post" enctype="multipart/form-data" id="upload-form"><?= csrf_field() ?><input type="hidden" name="action" value="install">
      <div class="card-head"><h2>Installer une mise à jour</h2><span class="badge accent">Version actuelle <?= e(app_version()) ?></span></div>
      <label class="dropzone">
        <input type="file" name="package" accept=".zip,application/zip" hidden>
        <span style="font-size:34px"></span>
        <b>Dépose ici le fichier maj-x.y.z.zip</b>
        <span class="muted dz-name">ou clique pour le choisir</span>
      </label>
      <div class="row mt between">
        <label class="check"><input type="checkbox" name="force" value="1"> Forcer (réinstaller ou revenir à une version plus ancienne)</label>
        <button class="btn btn-lg" data-confirm="Installer cette mise à jour ? Une sauvegarde est faite automatiquement avant.">Installer</button>
      </div>
      <p class="muted mt mb0 u-fs14">Avant chaque installation, les fichiers remplacés sont sauvegardés. Tes données (base, billets, images, config.php) ne sont jamais touchées. Taille max. acceptée par l'hébergement : <?= e($limits['upload']) ?>.<?= !$limits['zip'] ? ' <b class="u-red">L\'extension PHP zip est désactivée : active-la dans cPanel.</b>' : '' ?></p>
    </form>
    <div class="card">
      <h2>Historique</h2>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Action</th><th>Version</th><th>Par</th><th>Notes</th></tr></thead>
        <tbody>
        <?php foreach ($log as $l): ?><tr><td class="nowrap"><?= e(short_date($l['created_at'])) ?></td><td><?= $l['action'] === 'install' ? '<span class="badge green">Installée</span>' : '<span class="badge orange">Retour arrière</span>' ?></td>
          <td class="nowrap"><?= e($l['previous']) ?> → <b><?= e($l['version']) ?></b></td><td><?= e($l['user_name']) ?></td><td style="max-width:360px"><small class="prose"><?= e(mb_strimwidth((string)$l['notes'], 0, 240, '…')) ?></small></td></tr><?php endforeach; ?>
        <?php if (!$log): ?><tr><td colspan="5" class="empty">Aucune mise à jour pour l'instant. Tout neuf</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
  <div>
    <div class="card">
      <h2>Revenir en arrière</h2>
      <p class="muted u-fs14">Chaque sauvegarde contient les fichiers d'avant une mise à jour. La base de données n'est pas modifiée par un retour arrière.</p>
      <?php foreach (array_slice($backups, 0, 8) as $b): ?>
        <form method="post" class="list-item"><?= csrf_field() ?><input type="hidden" name="action" value="rollback"><input type="hidden" name="backup" value="<?= e($b['name']) ?>">
          <span class="grow"><b>Revenir à la <?= e($b['version_avant'] ?? '?') ?></b><br><small>avant la <?= e($b['version_apres'] ?? '?') ?> · <?= e(short_date($b['date'] ?? '')) ?></small></span>
          <button class="btn btn-sm btn-light" data-confirm="Revenir à la version <?= e($b['version_avant'] ?? '?') ?> ?">Restaurer</button></form>
      <?php endforeach; ?>
      <?php if (!$backups): ?><p class="muted mb0">Aucune sauvegarde pour l'instant.</p><?php endif; ?>
    </div>
    <div class="card">
      <h2>Sauvegarde des données</h2>
      <p class="muted u-fs14">Télécharge toute la base (événements, commandes, billets, comptes) en un fichier .sql. À faire avant chaque gros événement, on n'est jamais trop prudent.</p>
      <a class="btn btn-block btn-ghost" href="<?= url('/admin/sauvegarde') ?>">Télécharger la sauvegarde</a>
    </div>
  </div>
</div>
