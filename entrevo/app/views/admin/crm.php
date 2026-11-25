<?php /** @var array $cols, $notes, $today, $k, $agents, $orgs; ?array $sel */
$kindsN = ['call' => 'Appel', 'mail' => 'E-mail', 'meeting' => 'Rendez-vous', 'note' => 'Note', 'stage' => 'Étape']; ?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Prospects en cours</span><b><?= $k['open'] ?></b></div>
  <div class="kpi-mini"><span>Volume en démo / proposition</span><b><?= e(money($k['pipe'])) ?></b><small class="muted">ventes annuelles estimées</small></div>
  <div class="kpi-mini"><span>Gagnés / perdus</span><b><?= $k['won'] ?> / <?= $k['lost'] ?></b><small class="muted"><?= $k['won'] + $k['lost'] ? round($k['won'] / ($k['won'] + $k['lost']) * 100) : 0 ?> % de réussite</small></div>
  <div class="kpi-mini<?= $today ? ' kpi-alert' : '' ?>"><span>Relances du jour</span><b><?= count($today) ?></b></div>
</div>
<?php if ($today): ?><div class="notice warn mb"><b>À relancer aujourd'hui :</b> <?= implode(' · ', array_map(fn($p) => '<a href="' . url('/admin/prospection', ['p' => $p['id']]) . '">' . e($p['name']) . '</a> (' . e((string)$p['next_action']) . ')', $today)) ?></div><?php endif; ?>
<div class="row between mb"><small class="muted">Glisse une carte d'une colonne à l'autre pour changer d'étape.</small><div class="row"><a class="btn btn-sm" href="<?= url('/admin/prospection', ['p' => 'new']) ?>#fiche">+ Prospect</a>
  <details class="row-menu"><summary class="btn btn-sm btn-light">Importer</summary><div class="row-menu-pop" style="min-width:360px"><form method="post" class="perm-form"><?= csrf_field() ?><input type="hidden" name="action" value="import"><b>Coller une liste</b><small class="muted">Une ligne par prospect : nom ; contact ; e-mail ; téléphone ; ville (copier-coller depuis un tableur marche aussi)</small><textarea name="csv" rows="6"></textarea><button class="btn btn-sm">Importer</button></form></div></details></div></div>
<div class="kanban" data-move="<?= url('/admin/prospection') ?>">
  <?php foreach (CRM_STAGES as $st => $label): ?>
    <div class="kb-col" data-stage="<?= $st ?>"><div class="kb-head"><b><?= $label ?></b><span><?= count($cols[$st]) ?></span></div>
      <?php foreach ($cols[$st] as $p): $late = $p['next_action_on'] && $p['next_action_on'] < date('Y-m-d') && !in_array($st, ['won', 'lost'], true); ?>
        <a class="kb-card<?= $sel && (int)$sel['id'] === (int)$p['id'] ? ' on' : '' ?>" draggable="true" data-id="<?= (int)$p['id'] ?>" href="<?= url('/admin/prospection', ['p' => $p['id']]) ?>#fiche">
          <b><?= e($p['name']) ?></b><small><?= e(trim(($p['city'] ?? '') . ($p['kind'] ? ' · ' . (CRM_KINDS[$p['kind']] ?? '') : ''), ' ·')) ?></small>
          <?php if ((int)$p['est_volume']): ?><small><?= e(money((int)$p['est_volume'])) ?>/an</small><?php endif; ?>
          <?php if ($p['next_action_on'] && !in_array($st, ['won', 'lost'], true)): ?><small class="<?= $late ? 'text-red' : 'muted' ?>"><?= e(date('d/m', strtotime($p['next_action_on']))) ?> · <?= e((string)$p['next_action']) ?></small><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php if ($sel !== null): $p = $sel ?: ['id' => 0, 'name' => '', 'kind' => '', 'contact_name' => '', 'email' => '', 'phone' => '', 'city' => '', 'website' => '', 'source' => '', 'est_volume' => 0, 'next_action' => '', 'next_action_on' => '', 'owner_id' => 0, 'stage' => 'lead', 'organizer_id' => null]; ?>
<div class="split mt" id="fiche">
  <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="prospect_id" value="<?= (int)$p['id'] ?>">
    <div class="card-head"><h2><?= $p['id'] ? e($p['name']) : 'Nouveau prospect' ?></h2><?php if ($p['id']): ?><span class="badge grey"><?= CRM_STAGES[$p['stage']] ?></span><?php endif; ?></div>
    <div class="grid2">
      <div class="field"><label>Structure</label><input name="name" required value="<?= e($p['name']) ?>"></div>
      <div class="field"><label>Type</label><select name="kind"><option value="">—</option><?php foreach (CRM_KINDS as $kk => $l): ?><option value="<?= $kk ?>" <?= $p['kind'] === $kk ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Contact</label><input name="contact_name" value="<?= e((string)$p['contact_name']) ?>"></div>
      <div class="field"><label>Ville</label><input name="city" value="<?= e((string)$p['city']) ?>"></div>
      <div class="field"><label>E-mail</label><input type="email" name="email" value="<?= e((string)$p['email']) ?>"></div>
      <div class="field"><label>Téléphone</label><input name="phone" value="<?= e((string)$p['phone']) ?>"></div>
      <div class="field"><label>Site</label><input name="website_url" value="<?= e((string)$p['website']) ?>"></div>
      <div class="field"><label>Ventes annuelles estimées</label><div class="input-suffix"><input name="est_volume" inputmode="decimal" value="<?= (int)$p['est_volume'] ? e(money_input((int)$p['est_volume'])) : '' ?>"><span>€</span></div></div>
      <div class="field"><label>Source</label><input name="source" value="<?= e((string)$p['source']) ?>" placeholder="salon, bouche-à-oreille…"></div>
      <div class="field"><label>Suivi par</label><select name="owner_id"><?php foreach ($agents as $a): ?><option value="<?= (int)$a['id'] ?>" <?= (int)$p['owner_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="row"><button class="btn">Enregistrer</button><?php if ($p['id']): ?><button class="btn btn-ghost" style="color:var(--red)" name="action" value="delete" formnovalidate data-confirm="Supprimer ce prospect ?">Supprimer</button><?php endif; ?></div>
  </form>
  <?php if ($p['id']): ?>
  <div>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="note"><input type="hidden" name="prospect_id" value="<?= (int)$p['id'] ?>">
      <h2>Suivi</h2>
      <div class="seg seg-full mb"><?php foreach (['call' => 'Appel', 'mail' => 'E-mail', 'meeting' => 'Rendez-vous', 'note' => 'Note'] as $kk => $l): ?><label class="seg-r"><input type="radio" name="kind" value="<?= $kk ?>" <?= $kk === 'call' ? 'checked' : '' ?>><span><?= $l ?></span></label><?php endforeach; ?></div>
      <div class="field"><textarea name="body" rows="3" placeholder="Ce qui s'est dit, objections, prochaines étapes…"></textarea></div>
      <div class="grid2"><div class="field"><label>Prochaine action</label><input name="next_action" value="<?= e((string)$p['next_action']) ?>" placeholder="Rappeler, envoyer la proposition…"></div><div class="field"><label>Le</label><input type="date" name="next_action_on" value="<?= e((string)$p['next_action_on']) ?>"></div></div>
      <button class="btn btn-sm">Enregistrer</button>
    </form>
    <div class="card"><h2>Historique</h2>
      <?php foreach ($notes as $n): ?><div class="list-item"><span class="grow"><span class="badge grey"><?= $kindsN[$n['kind']] ?? $n['kind'] ?></span> <?= nl2br(e($n['body'])) ?><br><small class="muted"><?= e(fr_date($n['created_at'])) ?><?= $n['name'] ? ' · ' . e($n['name']) : '' ?></small></span></div><?php endforeach; ?>
      <?php if (!$notes): ?><p class="muted mb0">Pas encore d'échange.</p><?php endif; ?>
    </div>
    <div class="card"><h2>Conclure</h2>
      <?php if ($p['email']): ?><form method="post" class="mb"><?= csrf_field() ?><input type="hidden" name="action" value="invite"><input type="hidden" name="prospect_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-light">Envoyer le lien d'inscription</button></form><?php endif; ?>
      <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="link"><input type="hidden" name="prospect_id" value="<?= (int)$p['id'] ?>">
        <select name="organizer_id" style="width:auto"><option value="">Relier à un organisateur inscrit…</option><?php foreach ($orgs as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)$p['organizer_id'] === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select><button class="btn btn-sm">Gagné</button></form>
      <?php if ($p['organizer_id']): ?><p class="mt mb0"><a href="<?= url('/admin/organisateurs/' . $p['organizer_id']) ?>">Voir sa fiche organisateur →</a></p><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<script>
(() => { const kb = document.querySelector('.kanban'); if (!kb) return; let drag = null;
  kb.addEventListener('dragstart', e => { drag = e.target.closest('.kb-card'); drag?.classList.add('dragging'); });
  kb.addEventListener('dragend', () => drag?.classList.remove('dragging'));
  kb.querySelectorAll('.kb-col').forEach(col => {
    col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('over'); });
    col.addEventListener('dragleave', () => col.classList.remove('over'));
    col.addEventListener('drop', e => { e.preventDefault(); col.classList.remove('over'); if (!drag) return; col.appendChild(drag);
      const d = new FormData(); d.append('action', 'move'); d.append('ajax', '1'); d.append('prospect_id', drag.dataset.id); d.append('stage', col.dataset.stage); d.append('_csrf', document.querySelector('[name=_csrf]').value);
      fetch(kb.dataset.move, { method: 'POST', body: d, credentials: 'same-origin' });
      kb.querySelectorAll('.kb-col').forEach(c => c.querySelector('.kb-head span').textContent = c.querySelectorAll('.kb-card').length); });
  }); })();
</script>
