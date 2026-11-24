<?php
/** @var array $s, $ev, $edit */
$base = '/pro/evenements/' . $ev['id'] . '/budget';
$tk = $s['ticketing'];
$res = $s['result'];
$byCat = [];
foreach ($s['lines'] as $l) if ($l['kind'] === 'expense') $byCat[$l['category']] = ($byCat[$l['category']] ?? 0) + ($l['actual'] !== null ? (int)$l['actual'] : (int)$l['planned']);
arsort($byCat);
$be = $s['break_even'];
$edit = $edit ?: ['id' => 0, 'kind' => (string)input('type') === 'recette' ? 'income' : 'expense', 'category' => '', 'label' => '', 'planned' => 0, 'actual' => null, 'paid' => 0, 'supplier' => '', 'note' => ''];
?>
<div class="stat-grid">
  <div class="stat"><div class="stat-top"><small>💸 Dépenses</small></div><b><?= e(money($s['exp_a'])) ?></b><div class="stat-sub">prévu <?= e(money($s['exp_p'])) ?></div></div>
  <div class="stat"><div class="stat-top"><small>🎟️ Billetterie (ta part)</small></div><b><?= e(money($tk['net'])) ?></b><div class="stat-sub"><?= $tk['tickets'] ?> billets · <?= e(money($s['avg'])) ?> en moyenne</div></div>
  <div class="stat"><div class="stat-top"><small>🤝 Autres recettes</small></div><b><?= e(money($s['inc_a'])) ?></b><div class="stat-sub">prévu <?= e(money($s['inc_p'])) ?></div></div>
  <div class="stat <?= $res >= 0 ? 'good' : 'bad' ?>"><div class="stat-top"><small><?= $res >= 0 ? '🎉 Résultat' : '⚠️ Résultat' ?></small></div><b class="<?= $res >= 0 ? 'pos' : 'neg' ?>"><?= $res >= 0 ? '+' : '' ?><?= e(money($res)) ?></b><div class="stat-sub">à ce jour, billetterie comprise</div></div>
</div>

<div class="split-3">
  <div class="card">
    <div class="card-head"><h2>🎯 Seuil de rentabilité</h2><small class="muted">Combien de billets pour couvrir les dépenses ?</small></div>
    <?php if ($be === null): ?><p class="muted">Ajoute un tarif payant ou des ventes pour calculer le seuil.</p>
    <?php elseif (!$s['exp_a']): ?><p class="muted">Ajoute tes dépenses pour calculer le seuil.</p>
    <?php else: $max = max($be, $tk['tickets'], $cap ?: 0, 1); ?>
      <div class="be-big"><b><?= number_format($be, 0, ',', ' ') ?></b> billets à <?= e(money($s['avg'])) ?> <span class="muted">(dépenses − autres recettes : <?= e(money(max(0, $s['exp_a'] - $s['inc_a']))) ?>)</span></div>
      <div class="be-track" role="img" aria-label="<?= $tk['tickets'] ?> billets vendus sur un seuil de <?= $be ?>">
        <i class="be-sold" style="width:<?= min(100, round($tk['tickets'] / $max * 100)) ?>%"></i>
        <span class="be-mark" style="left:<?= min(100, round($be / $max * 100)) ?>%"><em>Seuil</em></span>
        <?php if ($cap): ?><span class="be-mark cap" style="left:<?= min(100, round($cap / $max * 100)) ?>%"><em>Jauge <?= $cap ?></em></span><?php endif; ?>
      </div>
      <p class="mb0"><?= $tk['tickets'] >= $be ? '🎉 Seuil dépassé : chaque billet vendu maintenant, c\'est du bénéfice.' : '🚀 Encore <b>' . ($be - $tk['tickets']) . ' billets</b> à vendre pour être à l\'équilibre.' ?>
        <?= $cap && $be > $cap ? '<br><span class="neg">⚠️ Le seuil dépasse la jauge : il faut réduire les dépenses, trouver des recettes ou augmenter les prix.</span>' : '' ?></p>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h2>📊 Dépenses par poste</h2></div>
    <?php $mx = max(1, max($byCat ?: [1])); foreach ($byCat as $c => $v): ?>
      <div class="hbar"><span class="hbar-l"><?= e($c) ?></span><span class="hbar-b"><i style="width:<?= max(2, round($v / $mx * 100)) ?>%;background:<?= Viz::SERIES ?>"></i></span><span class="hbar-v"><?= e(money($v)) ?></span></div>
    <?php endforeach; ?>
    <?php if (!$byCat): ?><p class="muted">Aucune dépense saisie.</p><?php endif; ?>
  </div>
</div>

<div class="split-3 mt">
  <div>
    <?php foreach (['expense' => '💸 Dépenses', 'income' => '🤝 Recettes (hors billetterie)'] as $kind => $title): $ls = array_filter($s['lines'], fn($l) => $l['kind'] === $kind); ?>
    <div class="card table-card">
      <div class="table-head-row"><h2 class="mb0"><?= $title ?></h2><span class="muted"><?= count($ls) ?> ligne(s)</span></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Poste</th><th>Intitulé</th><th class="num">Prévu</th><th class="num">Réel</th><th class="num">Écart</th><th><?= $kind === 'expense' ? 'Payé' : 'Reçu' ?></th><th></th></tr></thead><tbody>
        <?php foreach ($ls as $l): $gap = $l['actual'] !== null ? (int)$l['actual'] - (int)$l['planned'] : null; ?>
          <tr><td><small><?= e($l['category']) ?></small></td><td><b><?= e($l['label']) ?></b><?= $l['supplier'] ? '<br><small class="muted">' . e($l['supplier']) . '</small>' : '' ?></td>
            <td class="num"><?= e(money((int)$l['planned'])) ?></td><td class="num"><?= $l['actual'] !== null ? e(money((int)$l['actual'])) : '<span class="muted">—</span>' ?></td>
            <td class="num"><?php if ($gap): $bad = $kind === 'expense' ? $gap > 0 : $gap < 0; ?><span class="<?= $bad ? 'neg' : 'pos' ?>"><?= $gap > 0 ? '+' : '' ?><?= e(money($gap)) ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
            <td><?= $l['paid'] ? '✅' : '<span class="muted">—</span>' ?></td>
            <td class="right nowrap"><a class="btn btn-sm btn-light" href="<?= url($base, ['modifier' => $l['id']]) ?>#ligne">✏️</a>
              <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="line_id" value="<?= (int)$l['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="delete" data-confirm="Supprimer cette ligne ?">🗑️</button></form></td></tr>
        <?php endforeach; ?>
        <?php if ($kind === 'income'): ?><tr class="row-auto"><td><small>Billetterie</small></td><td><b>Ventes de billets</b><br><small class="muted">automatique, part qui te revient</small></td><td class="num">—</td><td class="num"><?= e(money($tk['net'])) ?></td><td></td><td></td><td></td></tr><?php endif; ?>
        <?php if (!$ls && $kind === 'expense'): ?><tr><td colspan="7" class="empty">Aucune dépense. <form method="post" class="inline-form"><?= csrf_field() ?><button class="link-btn" name="action" value="template">Ajouter les postes de base</button></form> ou saisis-les à droite.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
    <?php endforeach; ?>
    <p><a class="btn btn-sm btn-ghost" href="<?= url($base, ['csv' => 1]) ?>">⬇️ Exporter le budget (CSV)</a></p>
  </div>
  <div class="card" id="ligne">
    <h2><?= $edit['id'] ? '✏️ Modifier la ligne' : '➕ Nouvelle ligne' ?></h2>
    <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="line_id" value="<?= (int)$edit['id'] ?>">
      <div class="seg-mini" role="radiogroup" style="align-self:flex-start">
        <label class="seg-radio"><input type="radio" name="kind" value="expense" <?= $edit['kind'] !== 'income' ? 'checked' : '' ?> data-budget-kind> Dépense</label>
        <label class="seg-radio"><input type="radio" name="kind" value="income" <?= $edit['kind'] === 'income' ? 'checked' : '' ?> data-budget-kind> Recette</label>
      </div>
      <div class="field mb0"><label>Poste</label><input name="category" value="<?= e($edit['category']) ?>" list="cats-exp" required data-budget-cat>
        <datalist id="cats-exp"><?php foreach (BUDGET_EXPENSES as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
        <datalist id="cats-inc"><?php foreach (BUDGET_INCOMES as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
      <div class="field mb0"><label>Intitulé</label><input name="label" value="<?= e($edit['label']) ?>" required placeholder="Ex. : Cachet de Nova Kid"></div>
      <div class="grid2"><div class="field mb0"><label>Prévu</label><div class="input-suffix"><input name="planned" value="<?= e(money_input((int)$edit['planned'])) ?>" inputmode="decimal"><span>€</span></div></div>
        <div class="field mb0"><label>Réel <small class="muted">(si connu)</small></label><div class="input-suffix"><input name="actual" value="<?= $edit['actual'] !== null ? e(money_input((int)$edit['actual'])) : '' ?>" inputmode="decimal"><span>€</span></div></div></div>
      <div class="field mb0"><label>Fournisseur / financeur</label><input name="supplier" value="<?= e((string)$edit['supplier']) ?>"></div>
      <label class="check"><input type="checkbox" name="paid" value="1" <?= $edit['paid'] ? 'checked' : '' ?>> Déjà payé / reçu</label>
      <div class="field mb0"><label>Note</label><input name="note" value="<?= e((string)$edit['note']) ?>"></div>
      <div class="row" style="gap:8px"><button class="btn btn-sm"><?= $edit['id'] ? 'Enregistrer' : 'Ajouter' ?></button><?php if ($edit['id']): ?><a href="<?= url($base) ?>">Annuler</a><?php endif; ?></div>
    </form>
  </div>
</div>
<script>
document.querySelectorAll('[data-budget-kind]').forEach(function (r) { var cat = document.querySelector('[data-budget-cat]'); var set = function () { if (r.checked) cat.setAttribute('list', r.value === 'income' ? 'cats-inc' : 'cats-exp'); }; r.addEventListener('change', set); set(); });
</script>
