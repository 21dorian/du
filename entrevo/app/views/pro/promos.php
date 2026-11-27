<?php $rateName = array_column($rates, 'name', 'id');
$effect = fn($p) => $p['kind'] === 'percent' ? '−' . (int)$p['value'] . ' %' : ($p['kind'] === 'amount' ? '−' . money((int)$p['value']) . ' / billet' : ($p['kind'] === 'grid' ? 'Grille : ' . implode(', ', array_map(fn($k, $v) => money((int)$v), array_keys(json_decode((string)$p['grid_json'], true) ?: []), json_decode((string)$p['grid_json'], true) ?: [])) : 'Débloque un tarif'));
$totalRev = array_sum(array_map(fn($s) => (int)$s['revenue'], $stats)); $totalUses = array_sum(array_map(fn($s) => (int)$s['n'], $stats)); $totalDisc = array_sum(array_map(fn($s) => (int)$s['disc'], $stats)); ?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Commandes avec code</span><b><?= $totalUses ?></b></div>
  <div class="kpi-mini"><span>Ventes générées</span><b><?= e(money($totalRev)) ?></b></div>
  <div class="kpi-mini"><span>Remises accordées</span><b><?= e(money($totalDisc)) ?></b></div>
  <a class="kpi-mini" href="<?= url('/pro/evenements/' . $ev['id'] . '/promos', ['csv' => 1]) ?>"><span>Export</span><b style="font-size:15px">Tous les codes (CSV)</b></a>
</div>
<div class="split-3">
  <div class="stack">
  <div class="card">
    <h2>Tes codes</h2>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Code</th><th>Effet</th><th>Conditions</th><th class="num">Utilisations</th><th class="num">Ventes</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($promos as $p): $ids = array_filter(explode(',', (string)$p['rate_ids'])); $st = $stats[$p['code']] ?? null;
        $now = time(); $state = !$p['active'] ? 'off' : ($p['valid_from'] && strtotime($p['valid_from']) > $now ? 'soon' : ($p['valid_until'] && strtotime($p['valid_until']) < $now ? 'over' : ($p['max_uses'] && $p['used'] >= $p['max_uses'] ? 'full' : 'on'))); ?>
        <tr style="<?= $state === 'on' ? '' : 'opacity:.6' ?>">
          <td><b class="mono"><?= e($p['code']) ?></b><?= $p['label'] ? '<br><small class="muted">' . e($p['label']) . '</small>' : '' ?>
            <br><?= ['on' => '<span class="badge green">Actif</span>', 'off' => '<span class="badge grey">Désactivé</span>', 'soon' => '<span class="badge blue">Pas encore</span>', 'over' => '<span class="badge grey">Expiré</span>', 'full' => '<span class="badge orange">Épuisé</span>'][$state] ?></td>
          <td><?= $p['kind'] === 'unlock' ? '<span class="badge violet">Débloque un tarif</span>' : e($effect($p)) ?>
            <?php if ($ids): ?><br><small><?= e(implode(', ', array_map(fn($i) => $rateName[$i] ?? '?', $ids))) ?></small><?php endif; ?></td>
          <td><small><?= implode('<br>', array_filter([
              $p['valid_from'] ? 'dès le ' . e(short_date($p['valid_from'])) : '',
              $p['valid_until'] ? 'jusqu\'au ' . e(short_date($p['valid_until'])) : '',
              $p['min_qty'] ? 'dès ' . (int)$p['min_qty'] . ' billets' : '',
              $p['once_per_email'] ? '1 fois par personne' : '',
            ])) ?: '<span class="muted">—</span>' ?></small></td>
          <td class="num"><?= (int)$p['used'] ?><?= $p['max_uses'] ? ' / ' . (int)$p['max_uses'] : '' ?></td>
          <td class="num"><?= $st ? '<b>' . e(money((int)$st['revenue'])) . '</b>' : '<span class="muted">—</span>' ?></td>
          <td class="right nowrap"><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="promo_id" value="<?= (int)$p['id'] ?>">
            <button class="btn btn-sm btn-ghost" name="action" value="toggle"><?= $p['active'] ? 'Désactiver' : 'Activer' ?></button>
            <?php if (!(int)$p['used']): ?><button class="btn btn-sm btn-ghost" name="action" value="delete" data-confirm="Supprimer ce code ?">✕</button><?php endif; ?></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$promos): ?><tr><td colspan="6" class="empty">Aucun code. Les influenceurs vont être déçus</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
  <?php if ($batches): ?>
  <div class="card">
    <h2>Lots de codes uniques</h2>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Lot</th><th>Effet</th><th class="num">Codes</th><th class="num">Utilisés</th><th class="num">Ventes</th><th></th></tr></thead>
      <tbody><?php foreach ($batches as $b): ?>
        <tr><td><b class="mono"><?= e($b['batch']) ?></b><?= $b['label'] ? '<br><small class="muted">' . e($b['label']) . '</small>' : '' ?></td><td><?= e($effect($b)) ?></td><td class="num"><?= (int)$b['n'] ?><?= (int)$b['active'] < (int)$b['n'] ? '<br><small class="muted">' . (int)$b['active'] . ' actifs</small>' : '' ?></td>
          <td class="num"><?= (int)$b['used'] ?> <small class="muted">(<?= $b['n'] ? round($b['used'] / $b['n'] * 100) : 0 ?> %)</small></td><td class="num"><?= $b['revenue'] ? '<b>' . e(money((int)$b['revenue'])) . '</b>' : '—' ?></td>
          <td class="right nowrap"><a class="btn btn-sm btn-light" href="<?= url('/pro/evenements/' . $ev['id'] . '/promos', ['csv' => 1, 'batch' => $b['batch']]) ?>">CSV</a>
            <?php if ((int)$b['active']): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="batch" value="<?= e($b['batch']) ?>"><button class="btn btn-sm btn-ghost" name="action" value="batch_off" data-confirm="Désactiver tous les codes de ce lot ?">Désactiver</button></form><?php endif; ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </div>
  <?php endif; ?>
  </div>
  <form class="card" method="post"><?= csrf_field() ?>
    <h2>+ Nouveau code</h2>
    <div class="field"><label>Type</label><select name="kind">
      <option value="percent">Réduction en %</option><option value="amount">Réduction en € par billet</option><option value="grid">Grille tarifaire (prix spécial par tarif)</option><option value="unlock">Code d'accès à un tarif secret</option></select></div>
    <div class="field" data-show-if="kind=grid"><label>Prix spéciaux avec ce code</label><?php foreach ($rates as $r): ?><div class="row" style="gap:8px;margin-bottom:6px"><span class="grow"><?= e($r['name']) ?></span><span class="input-suffix" style="max-width:130px"><input name="grid[<?= (int)$r['id'] ?>]" inputmode="decimal" placeholder="—"><span>€</span></span></div><?php endforeach; ?><small>Ex. Plein 25 € → 18 €, Réduit 18 € → 12 € pour les membres. Vide = prix normal.</small></div>
    <div class="field"><label>Combien de codes ?</label><select name="count"><option value="1">Un code partagé</option><?php foreach ([10, 25, 50, 100, 250, 500] as $c): ?><option value="<?= $c ?>"><?= $c ?> codes uniques (1 utilisation chacun)</option><?php endforeach; ?></select></div>
    <div class="field" data-show-if="count=1"><label>Code</label><input name="code" placeholder="Vide = on en invente un" style="text-transform:uppercase"></div>
    <div class="field" data-show-if="count=10|25|50|100|250|500"><label>Préfixe des codes</label><input name="prefix" placeholder="INFLU" maxlength="8" style="text-transform:uppercase"><small>Ex. INFLU-K7P2QX, INFLU-93HD2A…</small></div>
    <div class="field"><label>Libellé interne</label><input name="label" placeholder="Ex. : Instagram @lucie, Abonnés newsletter…" maxlength="190"></div>
    <div class="field" data-show-if="kind=percent|amount"><label>Valeur (% ou €)</label><input name="value" value="10" inputmode="decimal"></div>
    <div class="field"><label>Tarifs concernés</label>
      <?php foreach ($rates as $r): ?><label class="check" style="margin-bottom:6px"><input type="checkbox" name="rate_ids[]" value="<?= (int)$r['id'] ?>"> <?= e($r['name']) ?><?= $r['hidden'] ? ' <span class="badge violet">Secret</span>' : '' ?></label><?php endforeach; ?>
      <small>Aucun coché = tous les tarifs (pour une réduction).</small></div>
    <div class="grid2">
      <div class="field"><label>Valable à partir du</label><input type="datetime-local" name="valid_from"></div>
      <div class="field"><label>Jusqu'au</label><input type="datetime-local" name="valid_until"></div>
      <div class="field"><label>Utilisations max.</label><input type="number" name="max_uses" min="0" value="0"><small>0 = illimité</small></div>
      <div class="field"><label>Minimum de billets</label><input type="number" name="min_qty" min="0" max="100" value="0"><small>0 = dès 1 billet</small></div>
    </div>
    <label class="check mb"><input type="checkbox" name="once_per_email" value="1"> <span>Une seule fois par personne (même adresse e-mail)</span></label>
    <button class="btn btn-block">Créer</button>
  </form>
</div>
