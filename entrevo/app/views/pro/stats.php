<?php
$bars = function (array $rows, string $labelKey, string $valKey, callable $fmtLabel, callable $fmtVal) {
    if (!$rows) return '<p class="muted">Pas encore de données.</p>';
    $max = max(1, max(array_map(fn($r) => (int)$r[$valKey], $rows)));
    $h = '';
    foreach ($rows as $r) {
        $h .= '<div class="hbar"><span class="hbar-l">' . e($fmtLabel($r[$labelKey])) . '</span><span class="hbar-b"><i style="width:' . round((int)$r[$valKey] / $max * 100) . '%"></i></span><span class="hbar-v">' . e($fmtVal($r)) . '</span></div>';
    }
    return $h;
};
$channels = ['web' => 'Site', 'widget' => 'Widget', 'guichet' => 'Guichet', 'invitation' => 'Invitations', 'partenaire' => 'Partenaires', 'revente' => 'Revente', 'online' => 'Site', 'partner' => 'Partenaires', 'api' => 'API'];
$payLabels = ['especes' => 'Espèces (guichet)', 'cb' => 'Carte (guichet)', 'cheque' => 'Chèque', 'virement' => 'Virement', 'autre' => 'Autre', 'invitation' => 'Invitations', 'online' => 'En ligne'];
$days = [1 => 'Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
$fill = $cap ? round($st['sold'] / $cap * 100) : null;
$hours = array_fill(0, 24, 0); foreach ($byHour as $h) $hours[(int)$h['h']] = (int)$h['n']; $hmax = max(1, max($hours));
?>
<form class="toolbar" method="get">
  <?php if ($sessions): ?><select name="seance" onchange="this.form.submit()"><option value="">Toutes les dates</option><?php foreach ($sessions as $x): ?><option value="<?= (int)$x['id'] ?>" <?= $sid === (int)$x['id'] ? 'selected' : '' ?>><?= e(short_date($x['starts_at'])) ?></option><?php endforeach; ?></select><?php endif; ?>
  <span class="grow"></span>
  <a class="btn btn-sm btn-ghost" href="?<?= e(http_build_query(['seance' => $sid, 'csv' => 1])) ?>">⬇️ Ventes par jour (CSV)</a>
  <a class="btn btn-sm btn-ghost" href="<?= url('/pro/evenements/' . $ev['id'] . '/participants', ['csv' => 1]) ?>">⬇️ Participants (CSV)</a>
</form>
<div class="kpis">
  <div class="kpi"><small class="muted">🎟️ Billets</small><b><?= $st['sold'] ?></b><div class="sub"><?= $fill !== null ? $fill . ' % de remplissage' : 'jauge illimitée' ?></div><?php if ($fill !== null): ?><div class="bar"><i style="width:<?= min(100, $fill) ?>%"></i></div><?php endif; ?></div>
  <div class="kpi"><small class="muted">💶 Recettes</small><b><?= e(money($st['revenue'])) ?></b><div class="sub"><?= $st['orders'] ?> commandes · panier moyen <?= e(money($avg)) ?></div></div>
  <div class="kpi"><small class="muted">✅ Taux de présence</small><b><?= $st['sold'] ? round($st['checked'] / $st['sold'] * 100) : 0 ?> %</b><div class="sub"><?= $st['checked'] ?> entrés</div></div>
  <div class="kpi"><small class="muted">💛 Dons · 💸 Remboursements</small><b><?= e(money($donations)) ?></b><div class="sub"><?= (int)$refunds['n'] ?> remb. (<?= e(money((int)$refunds['t'])) ?>)</div></div>
</div>
<div class="card">
  <h2>📈 Ventes jour par jour</h2>
  <?php if ($byDay): $mx = max(1, max(array_map(fn($d) => (int)$d['t'], $byDay))); ?>
    <div class="chart"><?php foreach ($byDay as $d): ?><div style="height:<?= max(1, round((int)$d['t'] / $mx * 100)) ?>%" title="<?= e(date('d/m', strtotime($d['d']))) ?> : <?= (int)$d['n'] ?> commandes, <?= e(money((int)$d['t'])) ?>"></div><?php endforeach; ?></div>
    <div class="chart-labels"><span style="text-align:left"><?= e(date('d/m/Y', strtotime($byDay[0]['d']))) ?></span><span style="text-align:right"><?= e(date('d/m/Y', strtotime(end($byDay)['d']))) ?></span></div>
  <?php else: ?><p class="muted">Pas encore de vente.</p><?php endif; ?>
</div>
<div class="split">
  <div class="card"><h2>🎫 Par tarif</h2><?= $bars($st['by_rate'], 'name', 'sold', fn($l) => $l, fn($r) => $r['sold'] . ' · ' . money((int)$r['revenue'])) ?></div>
  <div class="card"><h2>🛣️ Par canal</h2><?= $bars($byChannel, 'c', 'n', fn($l) => $channels[$l] ?? $l, fn($r) => $r['n'] . ' · ' . money((int)$r['t'])) ?></div>
  <div class="card"><h2>🔎 Provenance (UTM)</h2><?= $bars($bySource, 's', 'n', fn($l) => $l, fn($r) => $r['n'] . ($r['c'] ? ' · ' . $r['c'] : '')) ?>
    <p class="muted mb0" style="font-size:13px">Ajoute <code>?utm_source=instagram</code> à ton lien pour savoir d'où viennent tes acheteurs.</p></div>
  <div class="card"><h2>🏷️ Codes promo</h2><?= $bars($byPromo, 'promo_code', 'n', fn($l) => $l, fn($r) => $r['n'] . ' · −' . money((int)$r['d'])) ?></div>
  <div class="card"><h2>💳 Moyens de paiement</h2><?= $bars($byProvider, 'p', 't', fn($l) => $payLabels[$l] ?? (provider_label($l) ?: $l), fn($r) => money((int)$r['t'])) ?></div>
  <div class="card"><h2>✨ Options vendues</h2><?= $bars($products, 'name', 'q', fn($l) => $l, fn($r) => $r['q'] . ' · ' . money((int)$r['t'])) ?></div>
  <div class="card"><h2>🕐 Heure d'achat</h2><div class="chart" style="height:110px"><?php foreach ($hours as $h => $n): ?><div style="height:<?= max(1, round($n / $hmax * 100)) ?>%" title="<?= $h ?>h : <?= $n ?>"></div><?php endforeach; ?></div><div class="chart-labels"><span style="text-align:left">0h</span><span>12h</span><span style="text-align:right">23h</span></div></div>
  <div class="card"><h2>📆 Jour d'achat</h2><?= $bars($byWeekday, 'd', 'n', fn($l) => $days[(int)$l] ?? $l, fn($r) => (string)$r['n']) ?></div>
</div>
<?php if ($bySession): ?>
<div class="card mt"><h2>📅 Par date</h2><div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Remplissage</th><th class="num">Entrés</th></tr></thead><tbody>
<?php foreach ($bySession as $x): $c = (int)$x['capacity'] ?: (int)$ev['capacity']; ?><tr><td><?= e(fr_date($x['starts_at'])) ?></td><td style="min-width:200px"><?= (int)$x['sold'] ?><?= $c ? ' / ' . $c : '' ?><?php if ($c): ?><div class="bar"><i style="width:<?= min(100, round($x['sold'] / $c * 100)) ?>%"></i></div><?php endif; ?></td><td class="num"><?= (int)$x['checked'] ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
