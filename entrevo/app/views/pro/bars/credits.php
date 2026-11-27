<?php /** @var array $list, $cats; int $volunteers, $value; string $btab, $bbase */ echo view('pro/bars/_nav', compact('btab', 'bbase')); ?>
<p class="muted lead-sm">Boissons et repas offerts à l'équipe, aux artistes et aux invités : chacun reçoit un badge (QR code, ou bracelet NFC associé) avec un nombre de consommations ou un montant par jour. Au bar, le serveur choisit « Crédit » et scanne le badge.</p>
<div class="page-head-row">
  <div class="kpi-mini"><span>Badges actifs</span><b><?= count(array_filter($list, fn($a) => (int)$a['active'])) ?></b></div>
  <div class="kpi-mini"><span>Valeur offerte</span><b><?= e(money($value)) ?></b></div>
  <div class="kpi-mini"><span>Utilisés aujourd'hui</span><b><?= count(array_filter($list, fn($a) => $a['used']['today'] > 0)) ?></b></div>
</div>
<div class="split">
  <div class="card table-card"><div class="card-head"><h2>Badges</h2><a class="btn btn-sm btn-light" href="<?= url($bbase . '/credits', ['badges' => 1]) ?>" target="_blank">Imprimer les badges (PDF)</a></div>
    <div class="table-wrap u-ovis"><table class="table"><thead><tr><th>Personne</th><th>Droit</th><th class="num">Aujourd'hui</th><th class="num">Total</th><th></th></tr></thead><tbody>
      <?php foreach ($list as $a): $eu = $a['mode'] === 'euros'; ?><tr class="<?= (int)$a['active'] ? '' : 'muted' ?>"><td><b><?= e($a['holder']) ?></b><br><small class="muted"><?= e(Till::GROUPS[$a['grp']] ?? '') ?> · <span class="mono"><?= e($a['code']) ?></span><?= $a['nfc_uid'] ? ' · bracelet' : '' ?></small></td>
        <td><?= $eu ? e(money((int)$a['per_day'])) : (int)$a['per_day'] . ' conso' ?> / jour<?= $a['total_limit'] !== null ? '<br><small class="muted">max ' . ($eu ? e(money((int)$a['total_limit'])) : (int)$a['total_limit']) . '</small>' : '' ?></td>
        <td class="num"><?= $eu ? e(money($a['used']['today'])) : $a['used']['today'] ?></td><td class="num"><?= $eu ? e(money($a['used']['all'])) : $a['used']['all'] ?></td>
        <td class="num"><details class="row-menu"><summary class="btn btn-sm btn-light">Gérer</summary><div class="row-menu-pop"><form method="post" class="perm-form"><?= csrf_field() ?><input type="hidden" name="allowance_id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="action" value="edit">
          <b>Modifier</b><input name="holder" value="<?= e($a['holder']) ?>"><div class="input-suffix"><input name="per_day" inputmode="decimal" value="<?= $eu ? e(money_input((int)$a['per_day'])) : (int)$a['per_day'] ?>"><span><?= $eu ? '€' : 'conso' ?>/j</span></div>
          <input name="nfc_uid" value="<?= e((string)$a['nfc_uid']) ?>" placeholder="Identifiant du bracelet NFC (facultatif)"><button class="btn btn-sm">Enregistrer</button></form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="allowance_id" value="<?= (int)$a['id'] ?>"><button name="action" value="toggle"><?= (int)$a['active'] ? 'Désactiver le badge' : 'Réactiver le badge' ?></button></form></div></details></td></tr><?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="5" class="empty">Aucun badge pour l'instant.</td></tr><?php endif; ?>
    </tbody></table></div></div>
  <div>
    <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="add"><h2>Créer des badges</h2>
      <div class="field"><label>Personnes</label><textarea name="holders" rows="5" placeholder="Un nom par ligne" required></textarea></div>
      <div class="grid2"><div class="field"><label>Groupe</label><select name="grp"><?php foreach (Till::GROUPS as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Type</label><select name="mode"><option value="items">Consommations</option><option value="euros">Montant en €</option></select></div>
        <div class="field"><label>Par jour</label><input name="per_day" inputmode="decimal" value="3" required></div><div class="field"><label>Maximum total <small class="muted">facultatif</small></label><input name="total_limit" inputmode="decimal"></div></div>
      <div class="field"><label>Limité aux catégories <small class="muted">facultatif</small></label><div class="chips-row"><?php foreach ($cats as $c): ?><label class="chip-check"><input type="checkbox" name="category_ids[]" value="<?= (int)$c['id'] ?>"><span><?= e($c['name']) ?></span></label><?php endforeach; ?></div></div>
      <button class="btn">Créer</button></form>
    <?php if ($volunteers): ?><form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="from_staff"><input type="hidden" name="mode" value="items"><input type="hidden" name="per_day" value="2"><h2>Bénévoles</h2><p class="muted"><?= (int)$volunteers ?> bénévole(s) confirmé(s) sur cet événement. Crée-leur un badge de 2 consommations par jour en un clic (modifiable ensuite).</p><button class="btn btn-sm">Créer les badges des bénévoles</button></form><?php endif; ?>
  </div>
</div>
