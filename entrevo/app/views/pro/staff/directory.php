<?php /** @var array $list, $teams, $f, $kpi, $events */ ?>
<p class="muted">Ton équipe de bénévoles et de staff : une fiche par personne, réutilisable d'un événement à l'autre. Chacun reçoit un lien perso pour voir son planning, s'inscrire sur des créneaux et pointer avec son badge QR 🙌</p>
<div class="page-head-row">
  <div class="kpi-mini"><span>Actifs</span><b><?= (int)$kpi['active'] ?></b></div>
  <a class="kpi-mini<?= (int)$kpi['candidates'] ? ' warn' : '' ?>" href="<?= url('/pro/benevoles', ['st' => 'candidate']) ?>"><span>Candidatures</span><b><?= (int)$kpi['candidates'] ?></b></a>
  <div class="kpi-mini"><span>Créneaux à venir</span><b><?= (int)$kpi['shifts'] ?></b></div>
  <div class="kpi-mini<?= (int)$kpi['holes'] ? ' warn' : '' ?>"><span>Places à pourvoir</span><b><?= (int)$kpi['holes'] ?></b></div>
</div>

<?php if ($events): ?>
<div class="staff-events">
  <?php foreach ($events as $e2): ?>
    <a class="staff-ev" href="<?= url('/pro/evenements/' . $e2['id'] . '/benevoles') ?>"><b><?= e($e2['name']) ?></b><span><?= e(fr_date($e2['starts_at'], false)) ?> · <?= (int)$e2['shifts'] ? (int)$e2['shifts'] . ' créneau' . ((int)$e2['shifts'] > 1 ? 'x' : '') : 'pas encore de planning' ?></span><i aria-hidden="true">→</i></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="split-3">
  <div>
    <form class="filters card" method="get">
      <div class="filters-main">
        <label class="search-field"><span aria-hidden="true">🔎</span><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Nom, e-mail, téléphone, compétence…" aria-label="Rechercher"></label>
        <select name="team" aria-label="Équipe"><option value="">Toutes les équipes</option><?php foreach ($teams as $t => $n): ?><option value="<?= e($t) ?>" <?= $f['team'] === $t ? 'selected' : '' ?>><?= e($t) ?> (<?= (int)$n ?>)</option><?php endforeach; ?></select>
        <select name="st" aria-label="Statut"><option value="">Actifs et candidats</option><?php foreach (Staff::MEMBER_STATUS as $k => [$l]): ?><option value="<?= $k ?>" <?= $f['st'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm">Filtrer</button>
      </div>
    </form>
    <form method="post" class="card table-card" data-bulk>
      <?= csrf_field() ?><input type="hidden" name="all" value="0" data-bulk-all>
      <div class="bulk-bar" data-bulk-bar hidden>
        <b data-bulk-count>0</b><span class="grow"></span>
        <button class="btn btn-sm btn-light" name="action" value="send" data-confirm="Envoyer à chacun son lien d'espace bénévole et son planning ?">📬 Envoyer le lien</button>
        <button class="btn btn-sm btn-light" name="action" value="activate">✅ Activer</button>
        <button class="btn btn-sm btn-light" name="action" value="archive">🗄️ Archiver</button>
      </div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th class="cb"><input type="checkbox" data-bulk-toggle aria-label="Tout sélectionner"></th><th>Personne</th><th>Équipe</th><th>Contact</th><th class="num">Créneaux à venir</th><th class="num">Heures</th><th>Statut</th></tr></thead>
        <tbody>
        <?php foreach ($list as $m): ?>
          <tr data-href="<?= url('/pro/benevoles/' . $m['id']) ?>">
            <td class="cb"><input type="checkbox" name="ids[]" value="<?= (int)$m['id'] ?>" aria-label="Sélectionner <?= e($m['first_name']) ?>"></td>
            <td><span class="avatar-row"><span class="avatar-sm" style="--h:<?= crc32($m['last_name'] . $m['first_name']) % 360 ?>"><?= e(mb_strtoupper(mb_substr($m['first_name'], 0, 1) . mb_substr($m['last_name'], 0, 1))) ?></span>
              <span><a href="<?= url('/pro/benevoles/' . $m['id']) ?>"><b><?= e($m['first_name'] . ' ' . $m['last_name']) ?></b></a><?php if ($m['skills']): ?><br><small class="muted"><?= e($m['skills']) ?></small><?php endif; ?></span></span></td>
            <td><?= $m['team'] ? '<span class="tag">' . e($m['team']) . '</span>' : '<span class="muted">—</span>' ?></td>
            <td><small><?= e((string)$m['email']) ?><?= $m['phone'] ? '<br>' . e($m['phone']) : '' ?></small></td>
            <td class="num"><?= (int)$m['upcoming'] ?: '<span class="muted">0</span>' ?></td>
            <td class="num"><?= (int)$m['minutes'] ? e(Staff::hm((int)$m['minutes'])) : '<span class="muted">—</span>' ?></td>
            <td><?= Staff::badge($m['status'], true) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="7" class="empty">Personne pour l'instant. Ajoute ta première recrue à droite, ou ouvre le recrutement sur un événement 📣</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </form>
  </div>
  <div>
    <div class="card">
      <h2>➕ Ajouter quelqu'un</h2>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="add">
        <div class="grid2"><div class="field mb0"><label>Prénom</label><input name="first_name" required></div><div class="field mb0"><label>Nom</label><input name="last_name" required></div></div>
        <div class="field mb0"><label>E-mail</label><input type="email" name="email"></div>
        <div class="grid2"><div class="field mb0"><label>Téléphone</label><input name="phone"></div><div class="field mb0"><label>Équipe</label><input name="team" list="teams-list" placeholder="Bar, Accueil…"></div></div>
        <datalist id="teams-list"><?php foreach ($teams as $t => $n): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
        <div class="field mb0"><label>Compétences <small class="muted">(optionnel)</small></label><input name="skills" placeholder="Permis B, secourisme, anglais…"></div>
        <label class="check"><input type="checkbox" name="send" value="1"> Lui envoyer son lien d'accès par e-mail</label>
        <button class="btn">Ajouter</button>
      </form>
    </div>
    <details class="card card-details">
      <summary><h2>📥 Import rapide</h2></summary>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="import">
        <p class="muted mb0">Colle une ligne par personne (copiée depuis Excel ou un tableur) : <code>Prénom ; Nom ; E-mail ; Téléphone ; Équipe</code>. Les doublons (même e-mail) sont fusionnés.</p>
        <textarea name="lines" rows="6" placeholder="Léa;Martin;lea@exemple.fr;0601020304;Bar&#10;Tom;Petit;tom@exemple.fr;;Accueil"></textarea>
        <button class="btn btn-light">Importer</button>
      </form>
    </details>
  </div>
</div>
