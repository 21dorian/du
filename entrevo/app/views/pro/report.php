<?php /** @var array $r, $d, $sec, $events, $sel */
$k = $d['k'];
$bars = function (array $data, string $unit = ''): string {
    if (!$data) return '<p class="muted mb0"><small>Pas de donnée.</small></p>';
    $max = max(1, max($data)); $tot = max(1, array_sum($data)); $h = '';
    foreach ($data as $l => $v) $h .= '<div class="rp-bar"><span class="rp-bar-l">' . e((string)$l) . '</span><span class="rp-bar-t"><i style="width:' . round($v / $max * 100, 1) . '%"></i></span><span class="rp-bar-v">' . e(number_format((int)$v, 0, ',', ' ') . $unit) . ' <small>' . round($v / $tot * 100) . ' %</small></span></div>';
    return $h;
};
$nb = fn($v) => number_format((int)$v, 0, ',', ' '); ?>
<div class="row between mb rp-top">
  <a class="back-link" href="<?= url('/pro/bilans') ?>">‹ Bilans</a>
  <div class="row"><span class="muted" data-saved></span>
    <a class="btn btn-sm btn-light" href="<?= url('/pro/bilans/' . $r['id'], ['format' => 'csv']) ?>">Chiffres (CSV)</a>
    <a class="btn btn-sm" target="_blank" href="<?= url('/pro/bilans/' . $r['id'], ['format' => 'pdf']) ?>">Télécharger le PDF</a></div>
</div>
<div class="kpi-grid rp-kpis">
  <div class="kpi-mini"><span>Billets émis</span><b><?= $nb($k['issued'] ?? 0) ?></b><small class="muted">dont <?= $nb($k['invited'] ?? 0) ?> invitations</small></div>
  <div class="kpi-mini"><span>Entrées contrôlées</span><b><?= $nb($k['present'] ?? 0) ?></b><small class="muted"><?= isset($k['show']) && $k['show'] !== null ? $k['show'] . ' % de présence' : '' ?></small></div>
  <div class="kpi-mini"><span>Remplissage</span><b><?= isset($k['fill']) && $k['fill'] !== null ? $k['fill'] . ' %' : '—' ?></b><small class="muted">jauge <?= $nb($k['capacity'] ?? 0) ?></small></div>
  <div class="kpi-mini"><span>Tarifs réduits</span><b><?= (int)($k['reduced_pct'] ?? 0) ?> %</b><small class="muted"><?= $nb($k['reduced'] ?? 0) ?> billets</small></div>
  <div class="kpi-mini"><span>Acheteurs</span><b><?= $nb($k['buyers'] ?? 0) ?></b><small class="muted"><?= $nb($k['new'] ?? 0) ?> nouveaux · <?= $nb($k['loyal'] ?? 0) ?> fidèles</small></div>
  <div class="kpi-mini"><span>Recettes nettes</span><b><?= e(money((int)($k['net'] ?? 0))) ?></b><small class="muted">prix moyen <?= e(money((int)($k['avg_price'] ?? 0))) ?></small></div>
  <div class="kpi-mini"><span>Bénévolat</span><b><?= e(str_replace('.', ',', (string)$d['staff']['hours'])) ?> h</b><small class="muted"><?= (int)$d['staff']['people'] ?> bénévole(s)</small></div>
  <div class="kpi-mini"><span>Artistes</span><b><?= (int)$d['artists']['n'] ?></b><small class="muted"><?= $d['artists']['fees'] ? e(money($d['artists']['fees'])) . ' de cachets' : '' ?></small></div>
</div>
<div class="rp-layout mt">
  <form method="post" class="rp-text" data-autosave><?= csrf_field() ?>
    <div class="card"><div class="field mb0"><label>Titre du bilan</label><input name="title" value="<?= e($r['title']) ?>" class="rp-title"></div>
      <details class="mt"><summary class="linklike">Événements inclus (<?= count($sel) ?>)</summary><div class="rp-events mt">
        <?php foreach ($events as $e): ?><label class="check"><input type="checkbox" name="events[]" value="<?= (int)$e['id'] ?>" <?= in_array((int)$e['id'], $sel, true) ? 'checked' : '' ?>> <span><?= e($e['name']) ?> <small class="muted">· <?= e(date('d/m/Y', strtotime($e['starts_at']))) ?></small></span></label><?php endforeach; ?>
        <button class="btn btn-sm mt" name="refresh" value="1">Recalculer</button></div></details>
    </div>
    <?php foreach (Reports::SECTIONS as $key => [$label, $hint]): ?>
      <div class="card"><label class="rp-label" for="s-<?= $key ?>"><?= e($label) ?></label><small class="muted"><?= e($hint) ?></small>
        <textarea id="s-<?= $key ?>" name="s[<?= $key ?>]" rows="5" class="rp-area"><?= e((string)($sec[$key] ?? '')) ?></textarea></div>
    <?php endforeach; ?>
    <div class="row"><button class="btn">Enregistrer</button><button class="btn btn-light danger" name="action" value="delete" formnovalidate data-confirm="Supprimer ce bilan ?">Supprimer</button></div>
  </form>
  <div class="rp-figs">
    <div class="card"><h2>Événements</h2>
      <?php foreach ($d['events'] as $e): ?><div class="list-item"><span class="grow"><b><?= e($e['name']) ?></b><br><small class="muted"><?= e(date('d/m/Y', strtotime($e['starts_at']))) ?><?= $e['venue'] ? ' · ' . e($e['venue']) : '' ?></small></span><span class="num"><b><?= $nb($e['issued']) ?></b><br><small class="muted"><?= $nb($e['present']) ?> présents</small></span></div><?php endforeach; ?>
    </div>
    <div class="card"><h2>Tarifs</h2><?= $bars(array_column($d['rates'], 'n', 'name')) ?><p class="muted mb0 mt"><small>Sont comptés comme « réduits » les tarifs moins chers que ton « plein tarif » et ceux dont le nom l'indique (réduit, étudiant, solidaire, enfant, adhérent…).</small></p></div>
    <div class="card"><h2>Canaux de vente</h2><?= $bars($d['channels']) ?></div>
    <?php if ($d['ages']): ?><div class="card"><h2>Âge des acheteurs</h2><?= $bars($d['ages']) ?></div><?php endif; ?>
    <div class="card"><h2>Provenance</h2><?= $d['places'] ? $bars($d['places']) : '<p class="muted mb0"><small>Ajoute une question « Code postal » au formulaire de tes événements pour connaître la provenance de ton public.</small></p>' ?></div>
    <div class="card"><h2>Recettes</h2>
      <div class="list-item"><span class="grow">Ventes encaissées</span><b><?= e(money((int)($k['gross'] ?? 0))) ?></b></div>
      <?php if (!empty($k['refunded'])): ?><div class="list-item"><span class="grow">Remboursements</span><span>− <?= e(money((int)$k['refunded'])) ?></span></div><?php endif; ?>
      <div class="list-item"><span class="grow">Frais de billetterie</span><span>− <?= e(money((int)($k['fees'] ?? 0))) ?></span></div>
      <div class="list-item"><span class="grow"><b>Recettes nettes</b></span><b><?= e(money((int)($k['net'] ?? 0))) ?></b></div>
    </div>
    <?php if (!empty($d['budget'])): ?><div class="card"><h2>Budget prévu / réalisé</h2>
      <?php foreach (['income' => 'Recettes', 'expense' => 'Dépenses'] as $kind => $label): if (empty($d['budget'][$kind])) continue; ?><h3><?= $label ?></h3>
        <?php foreach ($d['budget'][$kind] as $b): ?><div class="list-item"><span class="grow"><?= e($b['category']) ?></span><small class="muted"><?= e(money($b['planned'])) ?></small><b><?= $b['actual'] === null ? '—' : e(money($b['actual'])) ?></b></div><?php endforeach; ?>
      <?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
<script>
(() => { const f = document.querySelector('[data-autosave]'); if (!f) return; let t; const s = document.querySelector('[data-saved]');
  f.querySelectorAll('textarea, .rp-title').forEach(i => i.addEventListener('input', () => { clearTimeout(t); s.textContent = 'Modifications…'; t = setTimeout(() => {
    const fd = new FormData(f); fd.append('ajax', '1'); fetch(location.pathname, { method: 'POST', body: fd }).then(r => r.json()).then(j => { s.textContent = j.ok ? 'Enregistré à ' + j.at : ''; }).catch(() => { s.textContent = 'Non enregistré'; }); }, 1200); })); })();
</script>
