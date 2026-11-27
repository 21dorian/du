<?php /** @var array $org, $items, $reports, $k, $events; string $f */ $pubUrl = abs_url('/o/' . $org['slug'] . '/objets-trouves'); ?>
<p class="muted lead-sm">Chaque objet ramassé reçoit une référence à écrire sur son étiquette. Le public déclare ses pertes en ligne : les déclarations qui ressemblent à un objet en stock te sont proposées, tu préviens la personne en un clic.</p>
<div class="page-head-row">
  <div class="kpi-mini"><span>En stock</span><b><?= (int)$k['stock'] ?></b></div>
  <div class="kpi-mini<?= $k['reports'] ? ' kpi-alert' : '' ?>"><span>Pertes déclarées</span><b><?= (int)$k['reports'] ?></b><small class="muted">en attente</small></div>
  <div class="kpi-mini"><span>Propriétaire trouvé</span><b><?= (int)$k['claimed'] ?></b></div>
  <div class="kpi-mini"><span>Rendus</span><b><?= (int)$k['returned'] ?></b><small class="muted">sur <?= (int)$k['n'] ?> objets</small></div>
</div>
<div class="split-3">
  <div>
    <nav class="pills mb"><?php foreach (LOST_STATUS as $kk => [$l]): ?><a class="<?= $f === $kk ? 'on' : '' ?>" href="<?= url('/pro/objets-trouves', ['f' => $kk]) ?>"><?= $l ?></a><?php endforeach; ?><a class="<?= $f === 'tous' ? 'on' : '' ?>" href="<?= url('/pro/objets-trouves', ['f' => 'tous']) ?>">Tous</a></nav>
    <div class="card table-card"><div class="table-wrap u-ovis"><table class="table users-table"><thead><tr><th>Objet</th><th>Trouvé</th><th>Statut</th><th></th></tr></thead><tbody>
    <?php foreach ($items as $i): ?>
      <tr><td><div class="row" style="gap:10px;flex-wrap:nowrap"><?php if ($i['photo']): ?><img src="<?= url($i['photo']) ?>" alt="" class="lost-thumb"><?php endif; ?><span><b><?= e($i['title']) ?></b> <span class="mono muted u-fs12"><?= e($i['ref']) ?></span><br><small class="muted"><?= e(LOST_CATS[$i['category']] ?? '') ?><?= $i['description'] ? ' · ' . e(mb_strimwidth($i['description'], 0, 70, '…')) : '' ?></small>
        <?php foreach ($i['matches'] as $m): ?><br><span class="lost-match">Ressemble à la perte de <b><?= e($m['name']) ?></b> : « <?= e(mb_strimwidth($m['description'], 0, 60, '…')) ?> »</span><?php endforeach; ?></span></div></td>
        <td class="nowrap"><small><?= e(date('d/m H\hi', strtotime($i['found_at']))) ?><br><span class="muted"><?= e((string)($i['found_where'] ?: $i['ev_name'])) ?></span></small></td>
        <td><span class="badge <?= LOST_STATUS[$i['status']][1] ?>"><?= LOST_STATUS[$i['status']][0] ?></span><?= $i['claimant'] ? '<br><small class="muted">' . e($i['claimant']) . '</small>' : '' ?></td>
        <td><details class="row-menu"><summary class="btn btn-sm btn-light">Gérer</summary><div class="row-menu-pop">
          <?php foreach ($i['matches'] as $m): ?><form method="post" class="perm-form"><?= csrf_field() ?><input type="hidden" name="action" value="match"><input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>"><input type="hidden" name="report_id" value="<?= (int)$m['id'] ?>">
            <b>Prévenir <?= e($m['name']) ?></b><textarea name="how" rows="2" placeholder="Comment récupérer l'objet (lieu, horaires)…"></textarea><button class="btn btn-sm">Envoyer l'e-mail</button></form><?php endforeach; ?>
          <form method="post" class="perm-form"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>">
            <b>Rendu à</b><input name="claimant" value="<?= e((string)$i['claimant']) ?>" placeholder="Nom de la personne"><button class="btn btn-sm" name="status" value="returned">Marquer rendu</button></form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>"><button name="status" value="found">Remettre en stock</button><button name="status" value="discarded">Donné / détruit</button></form>
        </div></details></td></tr>
    <?php endforeach; ?>
    <?php if (!$items): ?><tr><td colspan="4" class="empty">Aucun objet ici.</td></tr><?php endif; ?>
    </tbody></table></div></div>
    <?php if ($reports): ?><div class="card"><h2>Pertes déclarées</h2>
      <?php foreach ($reports as $r): ?><div class="list-item"><span class="grow"><b><?= e($r['name']) ?></b> <small class="muted"><?= e(LOST_CATS[$r['category']] ?? '') ?> · <?= e((string)$r['ev_name']) ?> · <?= e(short_date($r['created_at'])) ?></small><br><small><?= e($r['description']) ?></small><br><small class="muted"><?= e($r['email']) ?><?= $r['phone'] ? ' · ' . e($r['phone']) : '' ?></small></span>
        <?= $r['status'] === 'matched' ? '<span class="badge orange">Rapproché</span>' : '<form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><input type="hidden" name="action" value="close_report"><input type="hidden" name="report_id" value="' . (int)$r['id'] . '"><button class="btn btn-sm btn-ghost">Classer</button></form>' ?></div><?php endforeach; ?></div><?php endif; ?>
  </div>
  <div class="stack">
    <form class="card" method="post" enctype="multipart/form-data"><?= csrf_field() ?>
      <h2>Objet trouvé</h2>
      <div class="field"><label>Quoi</label><input name="title" required placeholder="iPhone coque rouge, écharpe grise…"></div>
      <div class="grid2"><div class="field"><label>Catégorie</label><select name="category"><?php foreach (LOST_CATS as $kk => $l): ?><option value="<?= $kk ?>"><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Événement</label><select name="event_id"><option value="">—</option><?php foreach ($events as $e2): ?><option value="<?= (int)$e2['id'] ?>"><?= e($e2['name']) ?></option><?php endforeach; ?></select></div></div>
      <div class="field"><label>Détails <small class="muted">(gardés pour vérifier le propriétaire)</small></label><textarea name="description" rows="2" placeholder="Contenu, marque, signe distinctif…"></textarea></div>
      <div class="grid2"><div class="field"><label>Où</label><input name="found_where" placeholder="Bar, devant la scène…"></div><div class="field"><label>Quand</label><input type="datetime-local" name="found_at" value="<?= date('Y-m-d\TH:i') ?>"></div></div>
      <div class="field"><label>Photo</label><input type="file" name="photo" accept="image/*" capture="environment"></div>
      <button class="btn btn-block">Enregistrer</button></form>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <h2>Page publique</h2>
      <label class="check"><input type="checkbox" name="lost_enabled" value="1" <?= (int)$org['lost_enabled'] ? 'checked' : '' ?> onchange="this.form.requestSubmit()"> <span>Le public peut déclarer ses pertes</span></label>
      <p class="muted mt mb0 u-fs13"><a href="<?= e($pubUrl) ?>" target="_blank"><?= e(preg_replace('~^https?://~', '', $pubUrl)) ?></a> · lien aussi sur ta page et dans l'e-mail du lendemain.</p></form>
  </div>
</div>
