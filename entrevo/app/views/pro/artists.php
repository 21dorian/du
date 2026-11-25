<?php /** @var array $ev, $artists, $perfs, $k, $org; ?array $edit */
$stages = []; foreach ($perfs as $p) $stages[$p['stage']][] = $p;
$min = $perfs ? min(array_map(fn($p) => strtotime($p['arrival_at'] ?: ($p['soundcheck_at'] ?: $p['starts_at'])), $perfs)) : 0;
$max = $perfs ? max(array_map(fn($p) => strtotime($p['ends_at']), $perfs)) : 0;
$min = $min ? strtotime(date('Y-m-d H:00:00', $min)) : 0; $max = $max ? strtotime(date('Y-m-d H:00:00', $max)) + 3600 : 0;
$span = max(3600, $max - $min); $pos = fn($t) => round((strtotime($t) - $min) / $span * 100, 2);
$base = '/pro/evenements/' . $ev['id'] . '/artistes'; ?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Confirmés</span><b><?= (int)$k['confirmed'] ?></b><small class="muted"><?= (int)$k['options'] ?> en option</small></div>
  <div class="kpi-mini"><span>Cachets</span><b><?= e(money((int)$k['fees'])) ?></b><small class="muted"><?= e(money((int)$k['paid'])) ?> payés</small></div>
  <div class="kpi-mini"><span>Personnes à accueillir</span><b><?= (int)$k['people'] ?></b><small class="muted">artistes et équipes</small></div>
  <div class="kpi-mini"><span>Passages</span><b><?= count($perfs) ?></b><small class="muted"><?= count($stages) ?> scène(s)</small></div>
</div>
<div class="card">
  <div class="card-head"><h2>Planning</h2><div class="row"><a class="btn btn-sm btn-light" href="<?= url($base, ['pdf' => 1]) ?>" target="_blank">Feuille de route PDF</a>
    <form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" name="action" value="publish" data-confirm="Remplacer le programme de la page publique par les passages confirmés ?">Publier le programme</button></form></div></div>
  <?php if ($perfs): ?>
  <div class="lineup">
    <div class="lu-hours"><?php for ($t = $min; $t <= $max; $t += 3600): ?><span style="left:<?= round(($t - $min) / $span * 100, 2) ?>%"><?= date('H\h', $t) ?></span><?php endfor; ?></div>
    <?php foreach ($stages as $st => $ps): ?><div class="lu-row"><b class="lu-stage"><?= e($st) ?></b><div class="lu-track">
      <?php foreach ($ps as $p): ?>
        <?php if ($p['soundcheck_at']): ?><i class="lu-sc" style="left:<?= $pos($p['soundcheck_at']) ?>%;width:<?= max(0.6, round(1800 / $span * 100, 2)) ?>%" title="Balance <?= e($p['name']) ?> <?= date('H\hi', strtotime($p['soundcheck_at'])) ?>"></i><?php endif; ?>
        <a class="lu-set <?= $p['status'] ?>" href="<?= url($base, ['edit' => $p['artist_id']]) ?>#fiche" style="left:<?= $pos($p['starts_at']) ?>%;width:<?= max(2, $pos($p['ends_at']) - $pos($p['starts_at'])) ?>%"><b><?= e($p['name']) ?></b><small><?= date('H\hi', strtotime($p['starts_at'])) ?>–<?= date('H\hi', strtotime($p['ends_at'])) ?></small></a>
      <?php endforeach; ?></div></div><?php endforeach; ?>
    <p class="muted mb0" style="font-size:12.5px"><i class="lu-legend lu-sc"></i> balance · hachuré : en option · <?= e(date('d/m', $min)) ?></p>
  </div>
  <?php else: ?><p class="muted mb0">Ajoute un artiste, puis ses horaires de passage : le planning par scène apparaît ici.</p><?php endif; ?>
</div>
<div class="split-3">
  <div class="card table-card"><div class="card-head"><h2>Artistes</h2><a class="btn btn-sm" href="<?= url($base, ['edit' => 'new']) ?>#fiche">+ Artiste</a></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Artiste</th><th>Passage</th><th class="num">Cachet</th><th>Statut</th></tr></thead><tbody>
    <?php foreach ($artists as $a): ?><tr data-href="<?= url($base, ['edit' => $a['id']]) ?>#fiche"><td><a href="<?= url($base, ['edit' => $a['id']]) ?>#fiche"><b><?= e($a['name']) ?></b></a><br><small class="muted"><?= e(trim(($a['kind'] ?? '') . ((int)$a['people'] > 1 ? ' · ' . (int)$a['people'] . ' pers.' : ''), ' ·')) ?></small></td>
      <td><?= $a['first_set'] ? e(date('d/m H\hi', strtotime($a['first_set']))) : '<span class="muted">à caler</span>' ?></td>
      <td class="num"><?= (int)$a['fee'] ? e(money((int)$a['fee'])) . '<br><small class="muted">' . e(FEE_STATUS[$a['fee_status']] ?? '') . '</small>' : '<span class="muted">—</span>' ?></td>
      <td><span class="badge <?= ARTIST_STATUS[$a['status']][1] ?>"><?= ARTIST_STATUS[$a['status']][0] ?></span></td></tr><?php endforeach; ?>
    <?php if (!$artists): ?><tr><td colspan="4" class="empty">Pas encore d'artiste.</td></tr><?php endif; ?>
    </tbody></table></div></div>
  <div id="fiche">
    <?php if ($edit !== null): $a = $edit ?: ['id' => 0, 'name' => '', 'kind' => '', 'contact_name' => '', 'email' => '', 'phone' => '', 'fee' => 0, 'fee_status' => 'todo', 'people' => 1, 'rider' => '', 'hospitality' => '', 'notes' => '', 'status' => 'option', 'token' => ''];
      $mine = $a['id'] ? array_values(array_filter($perfs, fn($p) => (int)$p['artist_id'] === (int)$a['id'])) : []; ?>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="artist_id" value="<?= (int)$a['id'] ?>">
      <div class="card-head"><h2><?= $a['id'] ? e($a['name']) : 'Nouvel artiste' ?></h2><?php if ($a['id']): ?><a class="btn btn-sm btn-ghost" href="<?= url('/artiste/' . $a['token']) ?>" target="_blank">Son espace</a><?php endif; ?></div>
      <div class="field"><label>Nom</label><input name="name" required value="<?= e($a['name']) ?>"></div>
      <div class="grid2"><div class="field"><label>Type</label><input name="kind" value="<?= e((string)$a['kind']) ?>" placeholder="Concert, DJ set, conférence…"></div>
        <div class="field"><label>Statut</label><select name="status"><?php foreach (ARTIST_STATUS as $kk => [$l]): ?><option value="<?= $kk ?>" <?= $a['status'] === $kk ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Cachet</label><div class="input-suffix"><input name="fee" inputmode="decimal" value="<?= (int)$a['fee'] ? e(money_input((int)$a['fee'])) : '' ?>"><span>€</span></div></div>
        <div class="field"><label>Paiement</label><select name="fee_status"><?php foreach (FEE_STATUS as $kk => $l): ?><option value="<?= $kk ?>" <?= $a['fee_status'] === $kk ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Contact (tourneur, manager)</label><input name="contact_name" value="<?= e((string)$a['contact_name']) ?>"></div>
        <div class="field"><label>Personnes</label><input type="number" name="people" min="1" value="<?= (int)$a['people'] ?>"></div>
        <div class="field"><label>E-mail</label><input type="email" name="email" value="<?= e((string)$a['email']) ?>"></div>
        <div class="field"><label>Téléphone</label><input name="phone" value="<?= e((string)$a['phone']) ?>"></div></div>
      <div class="field"><label>Fiche technique / besoins</label><textarea name="rider" rows="3" placeholder="Backline, retours, micros, lumière…"><?= e((string)$a['rider']) ?></textarea></div>
      <div class="field"><label>Accueil</label><textarea name="hospitality" rows="2" placeholder="Repas, loge, hébergement, transport…"><?= e((string)$a['hospitality']) ?></textarea></div>
      <div class="field"><label>Notes internes</label><textarea name="notes" rows="2"><?= e((string)$a['notes']) ?></textarea></div>
      <div class="row"><button class="btn">Enregistrer</button><?php if ($a['id']): ?><button class="btn btn-light" name="action" value="send" formnovalidate>Envoyer sa feuille de route</button><button class="btn btn-ghost" style="color:var(--red)" name="action" value="delete" formnovalidate data-confirm="Supprimer cet artiste et ses passages ?">Supprimer</button><?php endif; ?></div>
    </form>
    <?php if ($a['id']): ?>
    <div class="card"><h2>Passages</h2>
      <?php foreach ($mine as $p): ?><div class="list-item"><span class="grow"><b><?= e(date('d/m H\hi', strtotime($p['starts_at']))) ?> – <?= e(date('H\hi', strtotime($p['ends_at']))) ?></b> · <?= e($p['stage']) ?><br><small class="muted"><?= $p['arrival_at'] ? 'arrivée ' . date('H\hi', strtotime($p['arrival_at'])) : '' ?><?= $p['soundcheck_at'] ? ' · balance ' . date('H\hi', strtotime($p['soundcheck_at'])) : '' ?></small></span>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="perf_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="delete_perf">×</button></form></div><?php endforeach; ?>
      <form method="post" class="mt"><?= csrf_field() ?><input type="hidden" name="action" value="perf"><input type="hidden" name="artist_id" value="<?= (int)$a['id'] ?>">
        <?php $d0 = date('Y-m-d', strtotime($ev['starts_at'])); ?>
        <div class="field"><label>Scène</label><input name="stage" list="stages" value="<?= e($mine[0]['stage'] ?? (array_key_first($stages) ?? 'Scène principale')) ?>"><datalist id="stages"><?php foreach (array_keys($stages) as $st): ?><option value="<?= e($st) ?>"><?php endforeach; ?></datalist></div>
        <div class="grid2"><div class="field"><label>Début</label><input type="datetime-local" name="starts_at" value="<?= e($d0) ?>T21:00" required></div><div class="field"><label>Fin</label><input type="datetime-local" name="ends_at" value="<?= e($d0) ?>T22:00" required></div>
          <div class="field"><label>Arrivée</label><input type="datetime-local" name="arrival_at"></div><div class="field"><label>Balance</label><input type="datetime-local" name="soundcheck_at"></div></div>
        <div class="field"><input name="notes" placeholder="Note (plateau partagé, changement de plateau 20 min…)"></div>
        <button class="btn btn-light btn-block">Ajouter le passage</button></form></div>
    <?php endif; endif; ?>
  </div>
</div>
