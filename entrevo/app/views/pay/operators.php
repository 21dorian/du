<?php /** @var array $ops; ?array $edit; bool $new */ $o = $edit ?: []; ?>
<?php if ($edit || $new): ?>
<form method="post" class="card stack" style="max-width:760px"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($o['id'] ?? 0) ?>">
  <h2><?= $edit ? 'Modifier ' . e($o['name']) : 'Nouvel exploitant' ?></h2>
  <div class="grid2"><div class="field"><label>Nom commercial</label><input name="name" value="<?= e($o['name'] ?? '') ?>" required placeholder="Food truck Le Gaufrier"></div><div class="field"><label>Raison sociale</label><input name="legal_name" value="<?= e($o['legal_name'] ?? '') ?>"></div>
    <div class="field"><label>SIRET</label><input name="siret" value="<?= e($o['siret'] ?? '') ?>"></div><div class="field"><label>IBAN (reversements)</label><input name="iban" value="<?= e($o['iban'] ?? '') ?>"></div>
    <div class="field"><label>E-mail</label><input name="email" type="email" value="<?= e($o['email'] ?? '') ?>"></div><div class="field"><label>Téléphone</label><input name="phone" value="<?= e($o['phone'] ?? '') ?>"></div></div>
  <h2 class="mt">Conditions</h2>
  <div class="grid2"><div class="field"><label>Commission sur le CA TTC</label><div class="input-suffix"><input name="commission_pct" inputmode="decimal" value="<?= e(str_replace('.', ',', (string)(float)($o['commission_pct'] ?? 15))) ?>"><span>%</span></div></div>
    <div class="field"><label>Forfait (emplacement, location de terminal…)</label><div class="input-suffix"><input name="fixed_fee" inputmode="decimal" value="<?= e(money_input((int)($o['fixed_fee'] ?? 0))) ?>"><span>€</span></div></div></div>
  <label class="check"><input type="checkbox" name="keeps_cash" value="1" <?= ($o['keeps_cash'] ?? 1) ? 'checked' : '' ?>> <span>L'exploitant garde les espèces qu'il encaisse (sinon il les remet à l'organisateur)</span></label>
  <label class="check"><input type="checkbox" name="keeps_card" value="1" <?= !empty($o['keeps_card']) ? 'checked' : '' ?>> <span>L'exploitant encaisse la carte sur son propre terminal</span></label>
  <p class="pnote">Le cashless est toujours encaissé par l'organisateur. Le relevé calcule : encaissé par l'organisateur − commission = montant à reverser (négatif : l'exploitant doit la commission).</p>
  <div class="field"><label>Notes</label><textarea name="notes" rows="2"><?= e($o['notes'] ?? '') ?></textarea></div>
  <div class="row"><button class="btn">Enregistrer</button><a class="btn btn-light" href="<?= Pay::url('/pro/exploitants') ?>">Annuler</a></div></form>
<?php else: ?>
<div class="row mb"><a class="btn" href="<?= Pay::url('/pro/exploitants', ['edit' => 'new']) ?>">+ Nouvel exploitant</a><span class="pnote">Food trucks, associations, brasseurs : chacun voit ses ventes dans son espace et reçoit un relevé.</span></div>
<div class="card table-card"><div class="table-wrap"><table class="table"><thead><tr><th>Exploitant</th><th>Contact</th><th class="num">Commission</th><th>Encaissement</th><th class="num">Stands</th><th class="num">Comptes</th><th></th></tr></thead><tbody>
  <?php foreach ($ops as $x): ?><tr class="<?= (int)$x['active'] ? '' : 'muted' ?>"><td><b><?= e($x['name']) ?></b><br><small class="muted"><?= e((string)$x['legal_name']) ?></small></td><td><?= e((string)$x['email']) ?><br><small class="muted"><?= e((string)$x['phone']) ?></small></td>
    <td class="num"><?= e(rtrim(rtrim(number_format((float)$x['commission_pct'], 2, ',', ''), '0'), ',')) ?> %<?= (int)$x['fixed_fee'] ? '<br><small class="muted">+ ' . e(money((int)$x['fixed_fee'])) . '</small>' : '' ?></td>
    <td><small><?= (int)$x['keeps_cash'] ? 'garde les espèces' : 'remet les espèces' ?> · <?= (int)$x['keeps_card'] ? 'son terminal' : 'terminal orga' ?></small></td><td class="num"><?= (int)$x['bars'] ?></td><td class="num"><?= (int)$x['users'] ?></td>
    <td class="num"><a class="btn btn-sm btn-light" href="<?= Pay::url('/pro/exploitants', ['edit' => $x['id']]) ?>">Modifier</a> <a class="btn btn-sm btn-light" href="<?= Pay::url('/exploitant', ['op' => $x['id']]) ?>">Son espace</a>
      <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="toggle"><?= (int)$x['active'] ? 'Archiver' : 'Réactiver' ?></button></form></td></tr><?php endforeach; ?>
  <?php if (!$ops): ?><tr><td colspan="7" class="pempty">Aucun exploitant.</td></tr><?php endif; ?></tbody></table></div></div>
<p class="pnote mt">Pour donner un accès à un exploitant, ajoute un compte « Exploitant » dans <a href="<?= Pay::url('/pro/equipe') ?>">Équipe cashless</a>. Rattache ses stands dans chaque événement (Argent › Exploitants & reversements).</p>
<?php endif; ?>
