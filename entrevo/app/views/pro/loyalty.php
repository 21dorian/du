<?php /** @var array $s, $list, $byTier, $kpi, $events; string $seg, $tierName, $q; int $total */
$qs = array_filter(['seg' => $seg, 'tier' => $tierName, 'q' => $q]); ?>
<div class="kpi-grid loy-kpis">
  <div class="kpi-mini"><span>Festivaliers</span><b><?= (int)$kpi['n'] ?></b><small class="muted"><?= (int)$kpi['came'] ?> venus au moins une fois</small></div>
  <div class="kpi-mini"><span>Fidèles</span><b><?= (int)$kpi['fideles'] ?></b><small class="muted"><?= $kpi['n'] ? (int)round($kpi['fideles'] / $kpi['n'] * 100) : 0 ?> % reviennent</small></div>
  <div class="kpi-mini"><span>Billetterie</span><b><?= e(money((int)$kpi['spent'])) ?></b></div>
  <div class="kpi-mini"><span>Bars (cashless attribué)</span><b><?= e(money((int)$kpi['bar'])) ?></b></div>
  <div class="kpi-mini"><span>Points moyens</span><b><?= (int)$kpi['pts'] ?></b></div>
</div>
<div class="tier-strip mt"><?php foreach ($s['tiers'] as $t): ?><a class="<?= $seg === 'tier' && $tierName === $t['name'] ? 'on' : '' ?>" href="<?= url('/pro/fidelite', ['seg' => 'tier', 'tier' => $t['name']]) ?>"><b><?= e($t['name']) ?></b><span><?= (int)($byTier[$t['name']] ?? 0) ?> membre(s)</span><small>dès <?= (int)$t['min'] ?> pts<?= (int)$t['pct'] ? ' · offre −' . (int)$t['pct'] . ' %' : '' ?></small></a><?php endforeach; ?></div>
<nav class="pill-tabs mt"><?php foreach (Loyalty::SEGMENTS as $k => $l): if ($k === 'tier') continue; ?><a class="<?= $seg === $k ? 'on' : '' ?>" href="<?= url('/pro/fidelite', array_filter(['seg' => $k, 'q' => $q])) ?>"><?= e($l) ?></a><?php endforeach; ?></nav>
<div class="split mt" style="grid-template-columns:2fr 1fr">
  <div class="card table-card"><div class="card-head"><form class="row grow"><?php foreach (['seg' => $seg, 'tier' => $tierName] as $hk => $hv): if ($hv !== ''): ?><input type="hidden" name="<?= $hk ?>" value="<?= e($hv) ?>"><?php endif; endforeach; ?><input name="q" value="<?= e($q) ?>" placeholder="Nom ou e-mail" class="grow"><button class="btn btn-sm btn-light">Rechercher</button></form>
      <a class="btn btn-sm btn-ghost" href="<?= url('/pro/fidelite', $qs + ['csv' => 1]) ?>">CSV (<?= (int)$total ?>)</a></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Festivalier</th><th>Niveau</th><th class="num">Points</th><th class="num">Événements</th><th class="num">Venu(e)</th><th class="num">Billetterie</th><th class="num">Bars</th></tr></thead><tbody>
      <?php foreach ($list as $m): ?><tr data-href="<?= url('/pro/festivaliers', ['e' => $m['email']]) ?>">
        <td><a href="<?= url('/pro/festivaliers', ['e' => $m['email']]) ?>"><b><?= e(trim($m['first_name'] . ' ' . $m['last_name']) ?: $m['email']) ?></b></a><br><small class="muted"><?= e($m['email']) ?></small></td>
        <td><?= $m['tier'] ? '<span class="tier-tag t' . (array_search($m['tier']['name'], array_column($s['tiers'], 'name')) + 1) . '">' . e($m['tier']['name']) . '</span>' : '<span class="muted">—</span>' ?></td>
        <td class="num"><b><?= (int)$m['points'] ?></b></td><td class="num"><?= (int)$m['events'] ?></td><td class="num"><?= (int)$m['visits'] ?></td>
        <td class="num"><?= e(money((int)$m['spent'])) ?></td><td class="num"><?= $m['bar'] ? e(money((int)$m['bar'])) : '<span class="muted">—</span>' ?></td></tr><?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="7" class="empty">Personne dans ce segment.</td></tr><?php endif; ?>
    </tbody></table></div><?php if ($total > count($list)): ?><p class="muted" style="padding:0 20px"><small>100 premiers sur <?= (int)$total ?> : l'export CSV contient tout le segment.</small></p><?php endif; ?></div>
  <div class="stack">
    <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="offer"><input type="hidden" name="seg" value="<?= e($seg) ?>"><input type="hidden" name="tier" value="<?= e($tierName) ?>">
      <h2>Offre ciblée</h2><p class="muted mb0"><small>Crée un code promo réservé au segment affiché (<?= (int)$total ?> personne(s)) et télécharge la liste avec le code et le lien, prête pour une campagne e-mail.</small></p>
      <select name="event_id" required><option value="">Événement…</option><?php foreach ($events as $e2): ?><option value="<?= (int)$e2['id'] ?>"><?= e($e2['name']) ?> · <?= e(fr_date($e2['starts_at'], false)) ?></option><?php endforeach; ?></select>
      <div class="grid2"><div class="input-suffix"><input name="pct" inputmode="numeric" value="<?= $seg === 'tier' && $tierName ? (int)(array_values(array_filter($s['tiers'], fn($t) => $t['name'] === $tierName))[0]['pct'] ?? 10) : 10 ?>"><span>%</span></div><input name="code" placeholder="Code (auto)"></div>
      <div class="grid2"><div class="field"><label>Valable jusqu'au</label><input type="date" name="until"></div><div class="field"><label>Usages par personne</label><input name="uses" inputmode="numeric" value="1"></div></div>
      <button class="btn btn-sm" <?= $total ? '' : 'disabled' ?>>Créer le code et la liste</button>
      <a class="btn btn-sm btn-light" href="<?= url('/pro/campagnes') ?>">Ouvrir les campagnes e-mail</a></form>
    <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="settings"><h2>Programme de points</h2>
      <label class="check"><input type="checkbox" name="enabled" value="1" <?= $s['enabled'] ? 'checked' : '' ?>> <span>Programme actif</span></label>
      <div class="grid2"><div class="field"><label>Points par € de billets</label><input name="pts_ticket" value="<?= e(str_replace('.', ',', (string)$s['pts_ticket'])) ?>"></div><div class="field"><label>Points par € aux bars</label><input name="pts_bar" value="<?= e(str_replace('.', ',', (string)$s['pts_bar'])) ?>"></div></div>
      <div class="field"><label>Points par événement où il est venu</label><input name="pts_visit" inputmode="numeric" value="<?= (int)$s['pts_visit'] ?>"></div>
      <div class="table-wrap"><table class="table table-sm"><thead><tr><th>Niveau</th><th>Dès (points)</th><th>Offre</th></tr></thead><tbody>
        <?php for ($i = 0; $i < 4; $i++): $t = $s['tiers'][$i] ?? null; ?><tr><td><input name="tier_name[]" value="<?= e($t['name'] ?? '') ?>" placeholder="<?= $i === 3 ? 'ex. Légende' : '' ?>"></td><td><input name="tier_min[]" inputmode="numeric" value="<?= $t ? (int)$t['min'] : '' ?>"></td><td><div class="input-suffix"><input name="tier_pct[]" inputmode="numeric" value="<?= $t ? (int)$t['pct'] : '' ?>"><span>%</span></div></td></tr><?php endfor; ?>
      </tbody></table></div>
      <small class="muted">Les points se calculent à partir des achats de billets (hors remboursements), des dépenses cashless aux bars et de la présence (billet scanné). L'offre du niveau sert de réduction proposée par défaut pour une offre ciblée.</small>
      <button class="btn btn-sm">Enregistrer</button></form>
  </div>
</div>
