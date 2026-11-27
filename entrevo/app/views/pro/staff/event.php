<?php
/** @var array $ev, $shifts, $asg, $members, $teams, $edit */
$fill = $needed ? round($confirmed / $needed * 100) : 0;
$link = abs_url('/e/' . $ev['slug'] . '/benevoles');
$byDay = [];
foreach ($shifts as $s) $byDay[substr($s['starts_at'], 0, 10)][] = $s;
$jours = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
$e = $edit ?: ['id' => 0, 'title' => '', 'team' => '', 'location' => '', 'starts_at' => substr($ev['starts_at'], 0, 10) . ' 18:00:00', 'ends_at' => substr($ev['starts_at'], 0, 10) . ' 22:00:00', 'needed' => 2, 'description' => '', 'open_signup' => 1];
?>
<div class="page-head-row">
  <div class="kpi-mini<?= $fill < 100 && $needed ? ' warn' : '' ?>"><span>Postes pourvus</span><b><?= $confirmed ?> / <?= $needed ?> <small style="font-size:14px;color:var(--muted)">(<?= $fill ?> %)</small></b></div>
  <div class="kpi-mini"><span>Bénévoles</span><b><?= $people ?></b></div>
  <div class="kpi-mini"><span>Heures prévues</span><b><?= e(Staff::hm($hours)) ?></b></div>
  <div class="kpi-mini<?= $pending ? ' warn' : '' ?>"><span>Candidatures</span><b><?= $pending ?></b></div>
</div>
<div class="row" style="gap:8px;margin-bottom:14px;flex-wrap:wrap">
  <a class="btn btn-sm" href="<?= url($base . '/pointage') ?>">Pointage du jour J</a>
  <a class="btn btn-sm btn-light" href="<?= url($base . '/export') ?>">Planning & heures (CSV)</a>
  <a class="btn btn-sm btn-light" href="<?= url('/pro/benevoles') ?>">Annuaire</a>
  <?php if ($pending): ?><form method="post" class="inline-form"><?= csrf_field() ?><button class="btn btn-sm btn-success" name="action" value="accept_all">Accepter les <?= $pending ?> candidature(s)</button></form><?php endif; ?>
</div>

<div class="split-3">
  <div>
    <?php if (!$shifts): ?><div class="card empty">Pas encore de créneau. Crée les postes à droite (bar, accueil, sécurité…) puis place ton équipe ou ouvre les inscriptions</div><?php endif; ?>
    <?php foreach ($byDay as $day => $list): $ts = strtotime($day); $minH = 24; $maxH = 0;
      foreach ($list as $s) { $minH = min($minH, (int)date('G', strtotime($s['starts_at']))); $maxH = max($maxH, (int)ceil((strtotime($s['ends_at']) - strtotime($day)) / 3600)); }
      $span = max(1, $maxH - $minH); ?>
      <div class="card staff-day">
        <div class="card-head"><h2><?= $jours[(int)date('w', $ts)] ?> <?= date('d/m', $ts) ?></h2><small class="muted"><?= count($list) ?> créneau(x)</small></div>
        <div class="timeline-h" aria-hidden="true"><?php for ($h = $minH; $h <= $maxH; $h += max(1, (int)ceil($span / 8))): ?><span style="left:<?= round(($h - $minH) / $span * 100, 2) ?>%"><?= $h % 24 ?>h</span><?php endfor; ?></div>
        <?php foreach ($list as $s): $sid = (int)$s['id']; $a0 = (strtotime($s['starts_at']) - strtotime($day)) / 3600; $a1 = (strtotime($s['ends_at']) - strtotime($day)) / 3600;
          $p = $s['needed'] ? min(100, round($s['confirmed'] / $s['needed'] * 100)) : 0; $people = $asg[$sid] ?? []; ?>
          <div class="shift">
            <div class="shift-head">
              <div><b><?= e($s['title']) ?></b><?= $s['team'] ? ' <span class="tag">' . e($s['team']) . '</span>' : '' ?>
                <small class="muted"><?= e(date('H:i', strtotime($s['starts_at']))) ?> → <?= e(date('H:i', strtotime($s['ends_at']))) ?><?= $s['location'] ? ' · ' . e($s['location']) : '' ?><?= $s['open_signup'] ? ' · ouvert aux inscriptions' : '' ?></small></div>
              <span class="shift-count <?= $p >= 100 ? 'full' : ($p ? 'part' : 'empty') ?>"><?= (int)$s['confirmed'] ?>/<?= (int)$s['needed'] ?></span>
            </div>
            <div class="shift-bar"><i style="left:<?= round(($a0 - $minH) / $span * 100, 2) ?>%;width:<?= max(2, round(($a1 - $a0) / $span * 100, 2)) ?>%"><em style="width:<?= $p ?>%"></em></i></div>
            <div class="shift-people">
              <?php foreach ($people as $a): ?>
                <span class="person <?= e($a['status']) ?>" title="<?= e(Staff::STATUS[$a['status']][0]) ?>">
                  <?= e($a['first_name'] . ' ' . mb_substr($a['last_name'], 0, 1) . '.') ?><?= $a['checked_in_at'] ? '' : '' ?>
                  <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
                    <?php if ($a['status'] !== 'confirmed'): ?><button name="status" value="confirmed" title="Confirmer" aria-label="Confirmer"><?= ui_icon('check', 14) ?></button><?php endif; ?>
                    <button name="status" value="remove" title="Retirer" aria-label="Retirer"><?= ui_icon('x', 14) ?></button></form></span>
              <?php endforeach; ?>
              <form method="post" class="add-person"><?= csrf_field() ?><input type="hidden" name="action" value="assign"><input type="hidden" name="shift_id" value="<?= $sid ?>">
                <select name="member_id" aria-label="Ajouter quelqu'un" required><option value="">+ Placer quelqu'un…</option>
                  <?php foreach ($members as $m): if (array_filter($people, fn($a) => (int)$a['member_id'] === (int)$m['id'])) continue; ?><option value="<?= (int)$m['id'] ?>"><?= e($m['first_name'] . ' ' . $m['last_name']) ?><?= $m['team'] ? ' · ' . e($m['team']) : '' ?></option><?php endforeach; ?></select>
                <label class="check" title="Sinon le bénévole doit confirmer"><input type="checkbox" name="confirm" value="1"> confirmé</label><button class="btn btn-sm btn-light">OK</button></form>
            </div>
            <div class="shift-actions"><a href="<?= url($base, ['modifier' => $sid]) ?>#creneau">Modifier</a>
              <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="shift_id" value="<?= $sid ?>"><button class="link-btn" name="action" value="duplicate_shift">Dupliquer</button> <button class="link-btn neg" name="action" value="delete_shift" data-confirm="Supprimer ce créneau et ses affectations ?">Supprimer</button></form></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div>
    <div class="card" id="creneau">
      <h2><?= $e['id'] ? 'Modifier le créneau' : 'Nouveau créneau' ?></h2>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="shift"><input type="hidden" name="shift_id" value="<?= (int)$e['id'] ?>">
        <div class="field mb0"><label>Poste</label><input name="title" value="<?= e($e['title']) ?>" required placeholder="Bar, accueil, billetterie, sécurité…" list="postes"><datalist id="postes"><?php foreach (['Accueil & billetterie', 'Bar', 'Catering artistes', 'Sécurité', 'Montage', 'Démontage', 'Vestiaire', 'Parking', 'Merch'] as $x): ?><option value="<?= e($x) ?>"><?php endforeach; ?></datalist></div>
        <div class="grid2"><div class="field mb0"><label>Équipe</label><input name="team" value="<?= e((string)$e['team']) ?>" list="teams"><datalist id="teams"><?php foreach ($teams as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist></div>
          <div class="field mb0"><label>Lieu de rendez-vous</label><input name="location" value="<?= e((string)$e['location']) ?>"></div></div>
        <div class="field mb0"><label>Date</label><input type="date" name="date" value="<?= e(substr($e['starts_at'], 0, 10)) ?>" required></div>
        <div class="grid2">
          <div class="field mb0"><label>Début</label><input type="time" name="start" value="<?= e(substr($e['starts_at'], 11, 5)) ?>" required></div>
          <div class="field mb0"><label>Fin</label><input type="time" name="end" value="<?= e(substr($e['ends_at'], 11, 5)) ?>" required></div></div>
        <div class="grid2"><div class="field mb0"><label>Personnes nécessaires</label><input type="number" name="needed" min="1" max="500" value="<?= (int)$e['needed'] ?>"></div>
          <?php if (!$e['id']): ?><div class="field mb0"><label>Répéter</label><select name="repeat"><?php foreach ([1, 2, 3, 4, 6, 8] as $n): ?><option value="<?= $n ?>"><?= $n === 1 ? 'Une seule fois' : $n . ' créneaux à la suite' ?></option><?php endforeach; ?></select></div><?php endif; ?></div>
        <div class="field mb0"><label>Consignes</label><textarea name="description" rows="2" placeholder="Tenue, matériel, contact sur place…"><?= e((string)$e['description']) ?></textarea></div>
        <label class="check"><input type="checkbox" name="open_signup" value="1" <?= $e['open_signup'] ? 'checked' : '' ?>> Les bénévoles peuvent s'y inscrire eux-mêmes</label>
        <div class="row u-gap8"><button class="btn btn-sm"><?= $e['id'] ? 'Enregistrer' : 'Créer' ?></button><?php if ($e['id']): ?><a href="<?= url($base) ?>">Annuler</a><?php endif; ?></div>
      </form>
    </div>
    <div class="card">
      <h2>Recrutement</h2>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
        <label class="check"><input type="checkbox" name="volunteer_recruit" value="1" <?= $ev['volunteer_recruit'] ? 'checked' : '' ?>> Ouvrir le formulaire de candidature public</label>
        <textarea name="volunteer_intro" rows="3" placeholder="Pourquoi nous rejoindre : accès gratuit, repas, t-shirt…"><?= e((string)$ev['volunteer_intro']) ?></textarea>
        <button class="btn btn-sm btn-light">Enregistrer</button></form>
      <?php if ($ev['volunteer_recruit']): ?><div class="copy-field mt"><input value="<?= e($link) ?>" readonly aria-label="Lien de candidature"><button type="button" class="btn btn-sm btn-light" data-copy="<?= e($link) ?>">Copier</button></div><?php endif; ?>
    </div>
    <div class="card">
      <h2>Envoyer les plannings</h2>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="send_all">
        <textarea name="intro" rows="3" placeholder="Petit mot (facultatif) : « Merci d'être là ! Rendez-vous à l'entrée artistes… »"></textarea>
        <button class="btn btn-sm" data-confirm="Envoyer à chaque bénévole son planning et le lien de son espace ?">Envoyer à tous les bénévoles</button>
        <small class="muted">Chacun reçoit ses créneaux et un lien perso pour confirmer, s'inscrire ailleurs et afficher son badge. Un rappel part automatiquement la veille.</small></form>
    </div>
  </div>
</div>
