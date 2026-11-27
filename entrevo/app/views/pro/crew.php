<?php /** @var array $ev, $members, $docs, $sigs, $shifts, $teams, $tot */ $base = '/pro/evenements/' . $ev['id']; ?>
<div class="page-head"><div><p class="muted" style="margin-top:0">Repas, hébergement et matériel de chaque bénévole, documents à signer en ligne, messages par poste et plannings individuels.</p></div>
  <a class="btn btn-light" href="<?= url($base . '/benevoles') ?>">Planning des créneaux</a></div>
<div class="kpis"><div class="kpi"><small>Personnes confirmées</small><b><?= count($members) ?></b></div><div class="kpi"><small>Repas prévus</small><b><?= (int)$tot['meals'] ?></b></div><div class="kpi"><small>Repas servis</small><b><?= (int)$tot['used'] ?></b></div><div class="kpi"><small>Hébergés</small><b><?= (int)$tot['lodging'] ?></b></div></div>

<form method="post" class="card table-card mt"><?= csrf_field() ?>
  <div class="card-head"><h2>Dotations</h2><span class="grow"></span><button class="btn btn-sm btn-light" name="action" value="auto_meals" formnovalidate>Calculer les repas depuis le planning</button> <button class="btn btn-sm" name="action" value="provisions">Enregistrer</button></div>
  <?php if (!$members): ?><p class="muted" style="padding:0 20px 20px">Personne n'est encore confirmé sur un créneau de cet événement.</p><?php else: ?>
  <div class="table-wrap"><table class="table"><thead><tr><th>Personne</th><th>Postes</th><th class="num">Repas</th><th class="num">Servis</th><th>Hébergement</th><th>Matériel remis</th><th>Remis</th><th></th></tr></thead><tbody>
  <?php foreach ($members as $m): $k = 'p[' . (int)$m['id'] . ']'; ?><tr>
    <td><b><?= e($m['first_name'] . ' ' . $m['last_name']) ?></b><br><small class="muted"><?= e((string)$m['team']) ?> · <?= e(Staff::hm((int)$m['minutes'])) ?> · <a target="_blank" href="<?= url($base . '/equipes/planning/' . $m['id'] . '.pdf') ?>">planning PDF</a></small></td>
    <td><small><?= e(mb_strimwidth((string)$m['posts'], 0, 60, '…')) ?></small></td>
    <td class="num"><input type="number" name="<?= $k ?>[meals]" min="0" max="99" value="<?= (int)$m['meals'] ?>" style="width:64px"></td>
    <td class="num"><input type="number" name="<?= $k ?>[used]" min="0" max="99" value="<?= (int)$m['meals_used'] ?>" style="width:64px"></td>
    <td><input name="<?= $k ?>[lodging]" value="<?= e((string)$m['lodging']) ?>" placeholder="Camping, hôtel, nuits…" style="min-width:120px"></td>
    <td><input name="<?= $k ?>[items]" value="<?= e((string)$m['items']) ?>" placeholder="T-shirt <?= e((string)$m['tshirt']) ?>, jetons, talkie…" style="min-width:130px"></td>
    <td><label class="check"><input type="checkbox" name="<?= $k ?>[given]" value="1" <?= $m['given_at'] ? 'checked' : '' ?>></label></td>
    <td class="nowrap"><button class="btn btn-sm btn-light" name="action" value="meal" formnovalidate onclick="this.form.member_id.value=<?= (int)$m['id'] ?>">+1 repas</button></td>
  </tr><?php endforeach; ?></tbody></table></div><input type="hidden" name="member_id" value=""><?php endif; ?>
</form>

<div class="grid2 mt">
  <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="message">
    <h2>Message par poste</h2>
    <div class="field mb0"><label>Destinataires</label><select name="target"><option value="all">Toute l'équipe confirmée</option>
      <?php if ($teams): ?><optgroup label="Par équipe"><?php foreach ($teams as $t): ?><option value="team:<?= e($t) ?>"><?= e($t) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
      <?php if ($shifts): ?><optgroup label="Par créneau"><?php foreach ($shifts as $s): ?><option value="shift:<?= (int)$s['id'] ?>"><?= e($s['title'] . ' · ' . short_date($s['starts_at'])) ?></option><?php endforeach; ?></optgroup><?php endif; ?></select></div>
    <div class="field mb0"><label>Objet</label><input name="subject" maxlength="160" required placeholder="Briefing sécurité à 15 h"></div>
    <div class="field mb0"><label>Message</label><textarea name="body" rows="4" required></textarea><small>Envoyé par e-mail avec le lien vers l'espace bénévole de chacun.</small></div>
    <div><button class="btn">Envoyer</button></div>
  </form>
  <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="doc">
    <h2>Document à signer</h2>
    <div class="field mb0"><label>Titre</label><input name="title" maxlength="190" required placeholder="Charte bénévole, convention, droit à l'image…"></div>
    <div class="field mb0"><label>Texte</label><textarea name="body" rows="4" required></textarea></div>
    <div class="grid2"><div class="field mb0"><label>Pour</label><select name="scope"><option value="event">Les équipes de cet événement</option><option value="org">Tous mes bénévoles</option></select></div>
      <div class="field mb0"><label>Équipe <small class="muted">(facultatif)</small></label><input name="team" list="teams-list"><datalist id="teams-list"><?php foreach ($teams as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist></div></div>
    <small class="muted">Signature électronique simple : la personne relit, coche « lu et approuvé » et tape son nom. On garde la date, l'adresse IP et l'empreinte du texte signé.</small>
    <div><button class="btn">Publier le document</button></div>
  </form>
</div>

<?php if ($docs): ?><div class="card table-card mt"><div class="card-head"><h2>Documents</h2></div><table class="table"><thead><tr><th>Document</th><th>Pour</th><th class="num">Signatures</th><th>Manquantes</th><th></th></tr></thead><tbody>
<?php foreach ($docs as $d): $missing = array_filter($members, fn($m) => empty($sigs[$d['id']][$m['id']]) && (!$d['team'] || $d['team'] === $m['team'])); ?><tr>
  <td><b><?= e($d['title']) ?></b><br><small class="muted"><?= e(short_date($d['created_at'])) ?></small></td><td><?= $d['event_id'] ? 'Cet événement' : 'Tous' ?><?= $d['team'] ? ' · ' . e($d['team']) : '' ?></td>
  <td class="num"><?= (int)$d['signed'] ?></td><td><small><?= $missing ? e(mb_strimwidth(implode(', ', array_map(fn($m) => $m['first_name'] . ' ' . mb_substr($m['last_name'], 0, 1) . '.', $missing)), 0, 90, '…')) : '<span class="badge ok">Complet</span>' ?></small></td>
  <td class="nowrap"><a class="btn btn-sm btn-light" href="<?= url($base . '/equipes/documents/' . $d['id']) ?>">Voir</a><?php if (!(int)$d['signed']): ?> <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="doc_delete" data-confirm="Supprimer ce document ?">Supprimer</button></form><?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
