<?php /** @var array $m, $shifts, $teams */ $link = Staff::portalUrl($m); ?>
<div class="order-top">
  <div class="avatar-row"><span class="avatar-lg" style="--h:<?= crc32($m['last_name'] . $m['first_name']) % 360 ?>"><?= e(mb_strtoupper(mb_substr($m['first_name'], 0, 1) . mb_substr($m['last_name'], 0, 1))) ?></span>
    <div><div class="order-ref"><?= Staff::badge($m['status'], true) ?><?= $m['team'] ? ' <span class="tag">' . e($m['team']) . '</span>' : '' ?></div>
    <div class="muted">Dans l'équipe depuis le <?= e(fr_date($m['created_at'], false)) ?><?= $m['last_seen_at'] ? ' · espace ouvert ' . e(ago($m['last_seen_at'])) : ' · n\'a pas encore ouvert son espace' ?></div></div></div>
</div>
<div class="split-3">
  <div>
    <div class="page-head-row">
      <div class="kpi-mini"><span>Créneaux</span><b><?= count(array_filter($shifts, fn($s) => $s['status'] === 'confirmed')) ?></b></div>
      <div class="kpi-mini"><span>Heures prévues</span><b><?= e(Staff::hm($planned)) ?></b></div>
      <div class="kpi-mini"><span>Heures pointées</span><b><?= e(Staff::hm($done)) ?></b></div>
    </div>
    <div class="card table-card">
      <div class="table-head-row"><h2 class="mb0">Créneaux</h2></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Quand</th><th>Poste</th><th>Événement</th><th>Statut</th><th>Pointage</th></tr></thead>
        <tbody>
        <?php foreach ($shifts as $s): ?>
          <tr><td class="nowrap"><?= e(short_date($s['starts_at'])) ?> → <?= e(date('H:i', strtotime($s['ends_at']))) ?></td>
            <td><b><?= e($s['title']) ?></b><?= $s['location'] ? '<br><small class="muted">' . e($s['location']) . '</small>' : '' ?></td>
            <td><a href="<?= url('/pro/evenements/' . $s['event_id'] . '/benevoles') ?>"><?= e($s['ev_name']) ?></a></td>
            <td><?= Staff::badge($s['status']) ?></td>
            <td><small><?= $s['checked_in_at'] ? '' . e(date('H:i', strtotime($s['checked_in_at']))) . ($s['checked_out_at'] ? ' → ' . e(date('H:i', strtotime($s['checked_out_at']))) : '') : '<span class="muted">—</span>' ?></small></td></tr>
        <?php endforeach; ?>
        <?php if (!$shifts): ?><tr><td colspan="5" class="empty">Aucun créneau pour l'instant. Place-le depuis l'onglet « Bénévoles » d'un événement.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
  <div>
    <div class="card">
      <h2>Espace bénévole</h2>
      <p class="muted">Lien personnel : planning, confirmation des créneaux, inscription sur les créneaux ouverts et badge QR.</p>
      <div class="copy-field"><input value="<?= e($link) ?>" readonly aria-label="Lien de l'espace bénévole"><button type="button" class="btn btn-sm btn-light" data-copy="<?= e($link) ?>">Copier</button></div>
      <form method="post" class="row" style="margin-top:10px;gap:8px"><?= csrf_field() ?>
        <button class="btn btn-sm" name="action" value="send">Envoyer par e-mail</button>
        <a class="btn btn-sm btn-light" href="<?= url('/benevole/' . $m['token'] . '/badge') ?>" target="_blank">Badge</a>
        <button class="btn btn-sm btn-light" name="action" value="new_link" data-confirm="Créer un nouveau lien ? L'ancien lien et l'ancien badge ne marcheront plus.">Nouveau lien</button>
      </form>
    </div>
    <div class="card">
      <h2>Fiche</h2>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="save">
        <div class="grid2"><div class="field mb0"><label>Prénom</label><input name="first_name" value="<?= e($m['first_name']) ?>" required></div><div class="field mb0"><label>Nom</label><input name="last_name" value="<?= e($m['last_name']) ?>" required></div></div>
        <div class="field mb0"><label>E-mail</label><input type="email" name="email" value="<?= e((string)$m['email']) ?>"></div>
        <div class="grid2"><div class="field mb0"><label>Téléphone</label><input name="phone" value="<?= e((string)$m['phone']) ?>"></div>
          <div class="field mb0"><label>Équipe</label><input name="team" value="<?= e((string)$m['team']) ?>" list="tl"><datalist id="tl"><?php foreach ($teams as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist></div></div>
        <div class="grid2"><div class="field mb0"><label>Compétences</label><input name="skills" value="<?= e((string)$m['skills']) ?>"></div>
          <div class="field mb0"><label>Taille t-shirt</label><select name="tshirt"><option value="">—</option><?php foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL'] as $t): ?><option <?= $m['tshirt'] === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div></div>
        <div class="field mb0"><label>Statut</label><select name="status"><?php foreach (Staff::MEMBER_STATUS as $k => [$l]): ?><option value="<?= $k ?>" <?= $m['status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="field mb0"><label>Notes internes</label><textarea name="notes" rows="3" placeholder="Dispo surtout le week-end, allergie…"><?= e((string)$m['notes']) ?></textarea></div>
        <button class="btn btn-sm">Enregistrer</button>
      </form>
      <form method="post" class="u-mt12"><?= csrf_field() ?><button class="link-btn neg" name="action" value="delete" data-confirm="Supprimer définitivement cette fiche et ses créneaux ?">Supprimer la fiche</button></form>
    </div>
  </div>
</div>
