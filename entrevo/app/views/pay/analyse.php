<?php /** @var array $A, $others, $ev; ?array $other, $CMP; ?string $day; string $base */
$max = 1; foreach ($A['heat'] as $row) foreach ($row as $c) $max = max($max, $c['t']);
$totT = array_sum(array_map(fn($d) => (int)$d['t'], $A['days'])); $totN = array_sum(array_map(fn($d) => (int)$d['n'], $A['days']));
$PR = Pay::PROFILES + ['sans' => 'Sans profil']; ?>
<div class="card"><form class="psearch"><select name="jour" onchange="this.form.submit()"><option value="">Tout l'événement</option><?php foreach ($A['days'] as $d): ?><option value="<?= e($d['d']) ?>" <?= $day === $d['d'] ? 'selected' : '' ?>><?= e(fr_date($d['d'], false)) ?></option><?php endforeach; ?></select>
  <select name="vs" onchange="this.form.submit()"><option value="">Comparer avec…</option><?php foreach ($others as $o): ?><option value="<?= (int)$o['id'] ?>" <?= $other && (int)$other['id'] === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?> (<?= e(fr_date($o['starts_at'], false)) ?>)</option><?php endforeach; ?></select></form></div>
<div class="card mt"><h2>Carte de chaleur · chiffre d'affaires par heure et par point de vente</h2>
  <?php if ($A['heat']): $cols = count($A['hours']); ?><div class="heatg-wrap"><div class="heatg" style="grid-template-columns:140px repeat(<?= $cols ?>, minmax(40px, 1fr)) 100px">
    <span></span><?php foreach ($A['hours'] as $h): ?><span class="hh"><?= $h ?>h</span><?php endforeach; ?><span class="hh">Total</span>
    <?php foreach ($A['heat'] as $bar => $row): ?><span class="hb"><?= e($bar) ?></span><?php foreach ($A['hours'] as $h): $c = $row[$h] ?? null; $a = $c ? $c['t'] / $max : 0; ?><i class="<?= !$c ? 'empty' : ($a < .45 ? 'lt' : '') ?>" style="--a:<?= round($a, 3) ?>" title="<?= e($bar . ' · ' . $h . 'h : ' . ($c ? money($c['t']) . ' · ' . $c['n'] . ' ventes' : 'rien')) ?>"><?= $c ? e(number_format($c['t'] / 100, 0, ',', ' ')) : '' ?></i><?php endforeach; ?><b class="ht"><?= e(money(array_sum(array_column($row, 't')))) ?></b><?php endforeach; ?>
  </div></div><p class="pnote">Montants en euros. Plus la case est foncée, plus l'heure a été chargée : renforce les équipes et le stock sur ces créneaux.</p>
  <?php else: ?><p class="pempty">Pas encore de vente.</p><?php endif; ?></div>
<div class="pgrid-2 mt">
  <div class="card table-card"><div class="card-head"><h2>Par journée</h2></div><div class="table-wrap"><table class="table table-sm"><thead><tr><th>Jour</th><th class="num">Ventes</th><th class="num">CA</th><th class="num">Panier</th><th>Pic</th></tr></thead><tbody>
    <?php foreach ($A['days'] as $d): ?><tr><td><?= e(fr_date($d['d'], false)) ?></td><td class="num"><?= (int)$d['n'] ?></td><td class="num"><b><?= e(money((int)$d['t'])) ?></b></td><td class="num"><?= e(money((int)round((int)$d['t'] / max(1, (int)$d['n'])))) ?></td><td><?= $d['peak'] !== null ? (int)$d['peak'] . 'h · ' . e(money($d['peak_t'])) : '—' ?></td></tr><?php endforeach; ?>
    <?php if (!$A['days']): ?><tr><td class="pempty">—</td></tr><?php endif; ?></tbody></table></div></div>
  <div class="card"><h2>Panier moyen</h2>
    <h3 class="pnote" style="margin:0 0 6px">Par profil (cashless)</h3>
    <?php foreach ($A['prof'] as $r): ?><div class="list-item"><span class="grow"><?= e($PR[$r['profile']] ?? $r['profile']) ?> <small class="muted">· <?= (int)$r['n'] ?> ventes</small></span><b><?= e(money((int)round((int)$r['t'] / max(1, (int)$r['n'])))) ?></b></div><?php endforeach; ?><?php if (!$A['prof']): ?><p class="pnote">Pas de vente cashless.</p><?php endif; ?>
    <h3 class="pnote" style="margin:14px 0 6px">Par moyen de paiement</h3>
    <?php foreach ($A['meth'] as $r): ?><div class="list-item"><span class="grow"><?= e(Till::METHODS[$r['method']] ?? $r['method']) ?> <small class="muted">· <?= (int)$r['n'] ?> ventes</small></span><b><?= e(money((int)round((int)$r['t'] / max(1, (int)$r['n'])))) ?></b></div><?php endforeach; ?>
    <h3 class="pnote" style="margin:14px 0 6px">Articles par ticket</h3>
    <?php $sz = array_sum($A['sizes']) ?: 1; foreach ($A['sizes'] as $k => $n): ?><div style="margin-bottom:6px"><div class="row" style="justify-content:space-between"><small><?= $k >= 5 ? '5 et +' : $k ?> article<?= $k > 1 ? 's' : '' ?></small><small><?= (int)round($n / $sz * 100) ?> %</small></div><div class="pmeter"><i style="width:<?= (int)round($n / $sz * 100) ?>%"></i></div></div><?php endforeach; ?></div>
</div>
<div class="card mt"><h2>Souvent achetés ensemble</h2>
  <?php foreach ($A['assoc'] as $x): ?><div class="list-item"><span class="grow"><b><?= e($x['a']) ?></b> + <b><?= e($x['b']) ?></b></span><small class="muted"><?= (int)$x['n'] ?> tickets</small><span class="ptag vio" style="margin-left:10px"><?= (int)$x['pct'] ?> %</span></div><?php endforeach; ?>
  <?php if (!$A['assoc']): ?><p class="pnote">Pas assez de tickets à plusieurs produits pour l'instant.</p><?php endif; ?>
  <p class="pnote">Le pourcentage indique, parmi les tickets contenant le produit le moins vendu des deux, la part qui contient aussi l'autre : une bonne piste pour une formule ou une offre.</p></div>
<?php if ($CMP): $a = $CMP['a']; $b = $CMP['b']; $d = fn($x, $y) => $y ? (($x - $y) / $y * 100 >= 0 ? '+' : '') . (int)round(($x - $y) / $y * 100) . ' %' : '—'; ?>
<div class="card table-card mt"><div class="card-head"><h2>Comparaison avec « <?= e($other['name']) ?> »</h2></div><div class="table-wrap"><table class="table"><thead><tr><th></th><th class="num"><?= e($ev['name']) ?></th><th class="num"><?= e($other['name']) ?></th><th class="num">Évolution</th></tr></thead><tbody>
  <tr><td>Chiffre d'affaires des bars</td><td class="num"><b><?= e(money((int)$a['t'])) ?></b></td><td class="num"><?= e(money((int)$b['t'])) ?></td><td class="num"><?= $d((int)$a['t'], (int)$b['t']) ?></td></tr>
  <tr><td>Ventes</td><td class="num"><?= (int)$a['n'] ?></td><td class="num"><?= (int)$b['n'] ?></td><td class="num"><?= $d((int)$a['n'], (int)$b['n']) ?></td></tr>
  <tr><td>Panier moyen</td><td class="num"><?= e(money((int)round((int)$a['t'] / max(1, (int)$a['n'])))) ?></td><td class="num"><?= e(money((int)round((int)$b['t'] / max(1, (int)$b['n'])))) ?></td><td class="num"><?= $d((int)$a['t'] / max(1, (int)$a['n']), (int)$b['t'] / max(1, (int)$b['n'])) ?></td></tr>
  <tr><td>Présents (billets scannés)</td><td class="num"><?= (int)$a['att'] ?></td><td class="num"><?= (int)$b['att'] ?></td><td class="num"><?= $d((int)$a['att'], (int)$b['att']) ?></td></tr>
  <tr><td>Dépense par présent</td><td class="num"><?= e(money((int)round((int)$a['t'] / max(1, (int)$a['att'])))) ?></td><td class="num"><?= e(money((int)round((int)$b['t'] / max(1, (int)$b['att'])))) ?></td><td class="num"><?= $d((int)$a['t'] / max(1, (int)$a['att']), (int)$b['t'] / max(1, (int)$b['att'])) ?></td></tr>
  <?php foreach ($CMP['products'] as $n => $x): ?><tr><td class="muted"><?= e($n) ?></td><td class="num"><?= e(money($x['a'])) ?> <small class="muted">× <?= $x['qa'] ?></small></td><td class="num"><?= e(money($x['b'])) ?> <small class="muted">× <?= $x['qb'] ?></small></td><td class="num"><?= $d($x['a'], $x['b']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
