<?php $apName = array_column($aps, 'name', 'id'); ?>
<div class="split">
  <div>
    <div class="card"><div class="card-head"><h2>🪪 Populations</h2><a class="btn btn-sm btn-ghost" href="<?= url('/pro/evenements/' . $ev['id'] . '/badges') ?>" target="_blank">Imprimer tous les badges</a></div>
      <?php foreach ($pops as $p): $z = array_filter(explode(',', (string)$p['access_point_ids'])); ?>
        <details class="box"><summary><span><span style="display:inline-block;width:12px;height:12px;background:<?= e($p['color']) ?>"></span> <?= e($p['name']) ?></span><span class="muted" style="font-weight:500"><?= (int)$p['n'] ?> pers. · <?= $z ? e(implode(', ', array_map(fn($i) => $apName[$i] ?? '?', $z))) : 'toutes zones' ?></span></summary>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="population"><input type="hidden" name="population_id" value="<?= (int)$p['id'] ?>">
            <div class="grid2"><div class="field"><label>Nom</label><input name="name" value="<?= e($p['name']) ?>"></div><div class="field"><label>Couleur</label><input type="color" name="color" value="<?= e($p['color']) ?>"></div></div>
            <div class="field"><label>Zones autorisées</label><?php foreach ($aps as $a): ?><label class="check" style="font-weight:400"><input type="checkbox" name="zones[]" value="<?= (int)$a['id'] ?>" <?= in_array((string)$a['id'], $z, true) ? 'checked' : '' ?>> <?= e($a['name']) ?></label><?php endforeach; ?></div>
            <div class="form-actions"><button class="btn btn-sm">Enregistrer</button><a class="btn btn-sm btn-ghost" href="<?= url('/pro/evenements/' . $ev['id'] . '/badges', ['pop' => $p['id']]) ?>" target="_blank">Badges de cette population</a></div></form></details>
      <?php endforeach; ?>
      <details class="box add" <?= !$pops ? 'open' : '' ?>><summary>+ Nouvelle population</summary>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="population">
          <div class="grid2"><div class="field"><label>Nom</label><input name="name" placeholder="Bénévoles, Presse, Artistes, Sécurité…" required></div><div class="field"><label>Couleur du badge</label><input type="color" name="color" value="#ffb703"></div></div>
          <div class="field"><label>Zones autorisées</label><?php foreach ($aps as $a): ?><label class="check" style="font-weight:400"><input type="checkbox" name="zones[]" value="<?= (int)$a['id'] ?>"> <?= e($a['name']) ?></label><?php endforeach; ?><small>Les points d'accès se créent dans l'onglet « Contrôle d'accès ».</small></div>
          <button class="btn btn-sm">Créer</button></form></details>
    </div>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
      <h2>+ Accréditer des personnes</h2>
      <div class="field"><label>Population</label><select name="population_id"><?php foreach ($pops as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Une ligne par personne : <code>prénom ; nom ; structure ; e-mail</code></label><textarea name="people" rows="6" placeholder="Sam ; Petit ; Radio Nova&#10;Inès ; Garcia ; Bénévole"></textarea></div>
      <button class="btn btn-block">Créer les accréditations</button>
    </form>
    <form class="card" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="import">
      <h2>📥 Importer des billets externes</h2>
      <p class="muted" style="font-size:14px">Billets vendus ailleurs (Fnac, Ticketmaster, CE…) : importe leurs codes et ils passent au scanner Entrevo. <?= $external ? '<b>' . $external . ' déjà importés.</b>' : '' ?><br>Une ligne par billet : <code>code ; prénom ; nom ; tarif</code></p>
      <div class="field"><textarea name="codes" rows="4" placeholder="FNAC123456789 ; Léa ; Martin ; Fosse"></textarea></div>
      <div class="field"><input type="file" name="csv" accept=".csv,.txt"></div>
      <button class="btn btn-block btn-ghost">Importer</button>
    </form>
  </div>
  <div class="card"><h2><?= count($people) ?> accrédité<?= count($people) > 1 ? 's' : '' ?></h2>
    <div class="table-wrap"><table class="table"><thead><tr><th>Personne</th><th>Population</th><th>Entrée</th><th></th></tr></thead><tbody>
    <?php foreach ($people as $t): ?><tr style="<?= $t['status'] === 'valid' ? '' : 'opacity:.45' ?>"><td><b><?= e($t['last_name']) ?></b> <?= e($t['first_name']) ?><br><small><?= e($t['company']) ?></small></td>
      <td><span class="badge" style="color:<?= e($t['color']) ?>"><?= e($t['pop_name']) ?></span></td><td><?= $t['entries'] > 0 ? '✅' : '—' ?></td>
      <td class="right nowrap"><a class="btn btn-sm btn-light" href="<?= url('/pro/evenements/' . $ev['id'] . '/badges', ['t' => $t['id']]) ?>" target="_blank">Badge</a>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="revoke"><?= $t['status'] === 'valid' ? 'Révoquer' : 'Rétablir' ?></button></form></td></tr><?php endforeach; ?>
    <?php if (!$people): ?><tr><td colspan="4" class="empty">Personne pour l'instant.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</div>
