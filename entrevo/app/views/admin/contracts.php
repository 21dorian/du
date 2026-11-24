<?php /** @var array $list, $counts, $orgs; ?array $edit; string $f; int $preOrg */
$pctTxt = fn($v) => $v === null ? '—' : rtrim(rtrim(number_format((float)$v, 2, ',', ''), '0'), ',') . ' %';
$c = $edit ?: ['id' => 0, 'organizer_id' => $preOrg, 'label' => '', 'fee_percent' => null, 'fee_fixed' => null, 'fee_cap' => null, 'threshold' => null, 'fee_percent_after' => null, 'starts_on' => date('Y-m-d'), 'ends_on' => null, 'notes' => '', 'document' => null];
$mv = fn($v) => $v === null ? '' : money_input((int)$v);
?>
<p class="muted lead-sm">Conditions négociées avec un organisateur : elles remplacent sa formule pendant la durée du contrat. Avec un palier, la commission baisse automatiquement une fois un volume de ventes atteint.</p>
<div class="page-head-row">
  <div class="kpi-mini"><span>Contrats en cours</span><b><?= (int)$counts['actifs'] ?></b></div>
  <div class="kpi-mini"><span>Finissent dans 45 jours</span><b><?= (int)$counts['fin'] ?></b></div>
</div>
<div>
  <div>
    <nav class="pills mb"><?php foreach (['actifs' => 'En cours', 'fin' => 'Bientôt finis', 'termines' => 'Terminés', 'tous' => 'Tous'] as $k => $l): ?><a class="<?= $f === $k ? 'on' : '' ?>" href="<?= url('/admin/contrats', ['f' => $k]) ?>"><?= $l ?></a><?php endforeach; ?></nav>
    <div class="card table-card"><div class="table-wrap"><table class="table">
      <thead><tr><th>Organisateur</th><th>Conditions</th><th>Période</th><th class="num">Volume</th><th class="num">Commission</th><th></th></tr></thead><tbody>
      <?php foreach ($list as $x): $live = $x['status'] === 'active' && (!$x['ends_on'] || $x['ends_on'] >= date('Y-m-d')) && $x['starts_on'] <= date('Y-m-d');
        $prog = $x['threshold'] ? min(100, round($x['volume'] / max(1, (int)$x['threshold']) * 100)) : null; ?>
        <tr><td><a href="<?= url('/admin/organisateurs/' . $x['organizer_id']) ?>"><b><?= e($x['org_name']) ?></b></a><br><small class="muted"><?= e($x['label']) ?></small></td>
          <td><?= e($pctTxt($x['fee_percent'])) ?><?= $x['fee_fixed'] !== null ? ' + ' . e(money((int)$x['fee_fixed'])) : '' ?><?= $x['fee_cap'] !== null ? '<br><small class="muted">max ' . e(money((int)$x['fee_cap'])) . ' / billet</small>' : '' ?>
            <?php if ($x['threshold']): ?><br><small class="muted">puis <?= e($pctTxt($x['fee_percent_after'])) ?> au-delà de <?= e(money((int)$x['threshold'])) ?></small><div class="bar-thin"><i style="width:<?= $prog ?>%"></i></div><?php endif; ?></td>
          <td class="nowrap"><small><?= e(date('d/m/Y', strtotime($x['starts_on']))) ?> → <?= $x['ends_on'] ? e(date('d/m/Y', strtotime($x['ends_on']))) : 'sans fin' ?></small><br><?= $live ? '<span class="badge green">En cours</span>' : ($x['starts_on'] > date('Y-m-d') ? '<span class="badge orange">À venir</span>' : '<span class="badge grey">Terminé</span>') ?></td>
          <td class="num"><?= e(money((int)$x['volume'])) ?></td><td class="num"><?= e(money((int)$x['commission'])) ?></td>
          <td class="right nowrap"><?php if ($x['document']): ?><a class="btn btn-sm btn-ghost" href="<?= url('/admin/contrats', ['doc' => $x['id']]) ?>" target="_blank">PDF</a><?php endif; ?>
            <a class="btn btn-sm btn-light" href="<?= url('/admin/contrats', ['edit' => $x['id'], 'f' => $f]) ?>">Modifier</a>
            <?php if ($live): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="contract_id" value="<?= (int)$x['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="end" data-confirm="Terminer ce contrat aujourd'hui ?">Terminer</button></form><?php endif; ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="6" class="empty">Aucun contrat ici.</td></tr><?php endif; ?>
      </tbody></table></div></div>
  </div>
  <details class="card contract-form" id="editeur" <?= $c['id'] || !$list || $preOrg ? 'open' : '' ?>><summary><h2><?= $c['id'] ? 'Modifier le contrat' : '+ Nouveau contrat' ?></h2></summary>
  <form method="post" enctype="multipart/form-data" class="contract-grid"><?= csrf_field() ?><input type="hidden" name="contract_id" value="<?= (int)$c['id'] ?>"><div>
    <div class="field"><label>Organisateur</label><select name="organizer_id" required><option value="">Choisir…</option><?php foreach ($orgs as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)$c['organizer_id'] === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Intitulé</label><input name="label" value="<?= e($c['label']) ?>" placeholder="Contrat saison 2027, offre de lancement…"></div>
    <div class="grid2">
      <div class="field"><label>Commission</label><div class="input-suffix"><input name="fee_percent" inputmode="decimal" value="<?= $c['fee_percent'] === null ? '' : e(str_replace('.', ',', (string)(float)$c['fee_percent'])) ?>"><span>%</span></div></div>
      <div class="field"><label>Fixe par billet</label><div class="input-suffix"><input name="fee_fixed" inputmode="decimal" value="<?= e($mv($c['fee_fixed'])) ?>"><span>€</span></div></div>
      <div class="field"><label>Plafond par billet</label><div class="input-suffix"><input name="fee_cap" inputmode="decimal" value="<?= e($mv($c['fee_cap'])) ?>"><span>€</span></div></div>
    </div>
    <small class="muted">Champ vide = celui de la formule de l'organisateur.</small></div><div>
    <h3 class="mt">Palier <small class="muted">(facultatif)</small></h3>
    <div class="grid2">
      <div class="field"><label>Au-delà de (ventes)</label><div class="input-suffix"><input name="threshold" inputmode="decimal" value="<?= e($mv($c['threshold'])) ?>" placeholder="50000"><span>€</span></div></div>
      <div class="field"><label>Commission</label><div class="input-suffix"><input name="fee_percent_after" inputmode="decimal" value="<?= $c['fee_percent_after'] === null ? '' : e(str_replace('.', ',', (string)(float)$c['fee_percent_after'])) ?>"><span>%</span></div></div>
    </div>
    </div><div>
    <div class="grid2">
      <div class="field"><label>Début</label><input type="date" name="starts_on" value="<?= e($c['starts_on']) ?>" required></div>
      <div class="field"><label>Fin</label><input type="date" name="ends_on" value="<?= e((string)$c['ends_on']) ?>"><small>Vide = sans fin.</small></div>
    </div>
    <div class="field"><label>Contrat signé (PDF)</label><input type="file" name="document" accept="application/pdf"><?= $c['document'] ? '<small>Déjà joint : un nouveau fichier le remplace.</small>' : '' ?></div>
    <div class="field"><label>Notes internes</label><textarea name="notes" rows="3"><?= e((string)$c['notes']) ?></textarea></div>
    <button class="btn btn-block"><?= $c['id'] ? 'Enregistrer' : 'Créer le contrat' ?></button>
    <?php if ($c['id']): ?><a class="btn btn-ghost btn-block mt" href="<?= url('/admin/contrats') ?>">Annuler</a><?php endif; ?>
  </div></form></details>
</div>
