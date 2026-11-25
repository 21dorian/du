<?php /** @var array $list; int|false $free; int $total */ $last = (int)setting('backup_last', '0'); $err = (string)setting('backup_error'); ?>
<p class="muted lead-sm">Une copie complète de la base chaque nuit, compressée, rangée hors d'atteinte du web. Les fichiers envoyés (affiches, logos) sont dans le dossier <span class="mono">uploads/</span> : pense à les copier aussi de temps en temps depuis ton hébergeur.</p>
<?php if ($err): ?><div class="notice warn mb"><b>Dernière sauvegarde automatique en échec :</b> <?= e($err) ?></div><?php endif; ?>
<div class="page-head-row">
  <div class="kpi-mini<?= !$last || $last < time() - 36 * 3600 ? ' kpi-alert' : '' ?>"><span>Dernière sauvegarde</span><b><?= $last ? e(ago(date('Y-m-d H:i:s', $last))) : 'jamais' ?></b></div>
  <div class="kpi-mini"><span>Sauvegardes gardées</span><b><?= count($list) ?></b><small class="muted"><?= e(Backups::human($total)) ?></small></div>
  <div class="kpi-mini"><span>Espace libre</span><b><?= $free !== false ? e(Backups::human((int)$free)) : '—' ?></b></div>
  <div class="kpi-mini"><span>Automatique</span><b><?= setting('backup_auto', '1') === '1' ? 'chaque nuit dès ' . (int)setting('backup_hour', '4') . ' h' : 'désactivée' ?></b></div>
</div>
<div class="split-3">
  <div class="card table-card">
    <div class="card-head"><h2>Sauvegardes</h2><form method="post"><?= csrf_field() ?><button class="btn btn-sm" name="action" value="now">Sauvegarder maintenant</button></form></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Type</th><th class="num">Taille</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $b): $type = str_contains($b['name'], 'auto') ? 'Automatique' : (str_contains($b['name'], 'avantrestauration') ? 'Avant restauration' : 'Manuelle'); ?>
      <tr><td><b><?= e(fr_date(date('Y-m-d H:i:s', $b['at']))) ?></b><br><small class="muted mono"><?= e($b['name']) ?></small></td><td><span class="badge grey"><?= $type ?></span></td><td class="num"><?= e(Backups::human($b['size'])) ?></td>
        <td class="right nowrap"><a class="btn btn-sm btn-light" href="<?= url('/admin/sauvegardes', ['dl' => $b['name']]) ?>">Télécharger</a>
          <details class="row-menu"><summary class="btn btn-sm btn-ghost">…</summary><div class="row-menu-pop">
            <form method="post" class="perm-form"><?= csrf_field() ?><input type="hidden" name="name" value="<?= e($b['name']) ?>">
              <b>Restaurer cette sauvegarde</b><small class="muted">Toute la base revient à cet état. L'état actuel est sauvegardé juste avant.</small>
              <input name="confirm" placeholder="Écris RESTAURER" autocomplete="off"><button class="btn btn-sm danger" name="action" value="restore">Restaurer</button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="name" value="<?= e($b['name']) ?>"><button name="action" value="delete" class="danger-text" data-confirm="Supprimer cette sauvegarde ?">Supprimer</button></form>
          </div></details></td></tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="4" class="empty">Aucune sauvegarde pour l'instant : clique sur « Sauvegarder maintenant ».</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
  <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
    <h2>Réglages</h2>
    <label class="check mb"><input type="checkbox" name="backup_auto" value="1" <?= setting('backup_auto', '1') === '1' ? 'checked' : '' ?>> <span>Sauvegarde automatique chaque nuit</span></label>
    <div class="grid2">
      <div class="field"><label>À partir de</label><div class="input-suffix"><input type="number" name="backup_hour" min="0" max="23" value="<?= (int)setting('backup_hour', '4') ?>"><span>h</span></div></div>
      <div class="field"><label>Garder</label><div class="input-suffix"><input type="number" name="backup_keep" min="3" max="90" value="<?= (int)setting('backup_keep', '14') ?>"><span>dernières</span></div></div>
    </div>
    <small class="muted">En plus, la dernière sauvegarde de chaque mois est gardée 12 mois.</small>
    <div class="field mt"><label>Recevoir une copie par e-mail</label><input type="email" name="backup_email" value="<?= e((string)setting('backup_email')) ?>" placeholder="facultatif"><small>Une copie hors du serveur, si le fichier fait moins de 15 Mo. Elle contient des données personnelles : utilise une boîte sûre.</small></div>
    <button class="btn btn-block">Enregistrer</button>
  </form>
</div>
