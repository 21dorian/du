<?php /** @var array $m, $s, $orders, $wallets, $prods, $barByEv, $pts; ?array $next; int $rank, $n */
$t = $m['tier']; $prog = $next ? min(100, (int)round($m['points'] / max(1, (int)$next['min']) * 100)) : 100; ?>
<div class="lp-head card">
  <span class="avatar-sm lp-av" style="--h:<?= crc32($m['email']) % 360 ?>"><?= e(mb_strtoupper(mb_substr((string)$m['first_name'], 0, 1) . mb_substr((string)$m['last_name'], 0, 1)) ?: '@') ?></span>
  <div class="grow"><h2 style="margin:0"><?= e(trim($m['first_name'] . ' ' . $m['last_name']) ?: $m['email']) ?> <?= $t ? '<span class="tier-tag t' . (array_search($t['name'], array_column($s['tiers'], 'name')) + 1) . '">' . e($t['name']) . '</span>' : '' ?></h2>
    <p class="muted mb0"><?= e($m['email']) ?><?= $m['phone'] ? ' · ' . e($m['phone']) : '' ?> · <?= $m['optin'] ? 'accepte les e-mails' : 'n\'a pas accepté les e-mails' ?> · client depuis <?= $m['first_at'] ? e(fr_date($m['first_at'], false)) : '—' ?></p></div>
  <div class="lp-pts"><b><?= (int)$m['points'] ?></b><span>points · <?= (int)$rank ?><sup>e</sup> sur <?= (int)$n ?></span>
    <div class="bar-thin"><i style="width:<?= $prog ?>%"></i></div><small class="muted"><?= $next ? ((int)$next['min'] - (int)$m['points']) . ' pts avant « ' . e($next['name']) . ' »' : 'Niveau maximum' ?></small></div>
</div>
<div class="kpi-grid loy-kpis mt">
  <div class="kpi-mini"><span>Billetterie</span><b><?= e(money((int)$m['spent'])) ?></b><small class="muted"><?= (int)$m['orders'] ?> commande(s) · <?= (int)$pts['ticket'] ?> pts</small></div>
  <div class="kpi-mini"><span>Bars</span><b><?= e(money((int)$m['bar'])) ?></b><small class="muted"><?= (int)$m['bar_n'] ?> achat(s) · <?= (int)$pts['bar'] ?> pts</small></div>
  <div class="kpi-mini"><span>Présence</span><b><?= (int)$m['visits'] ?> / <?= (int)$m['events'] ?></b><small class="muted">événements · <?= (int)$pts['visit'] ?> pts</small></div>
  <div class="kpi-mini"><span>Valeur totale</span><b><?= e(money((int)$m['spent'] + (int)$m['bar'])) ?></b></div>
</div>
<div class="split mt" style="grid-template-columns:2fr 1fr">
  <div class="card table-card"><div class="card-head"><h2>Parcours</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>Événement</th><th>Commande</th><th class="num">Billets</th><th>Venu(e)</th><th class="num">Billetterie</th><th class="num">Bars</th></tr></thead><tbody>
    <?php foreach ($orders as $o): ?><tr><td><b><?= e($o['ev']) ?></b><br><small class="muted"><?= e(fr_date($o['starts_at'], false)) ?></small></td><td><a href="<?= url('/pro/commandes/' . $o['id']) ?>"><?= e($o['ref']) ?></a><br><small class="muted"><?= e($o['status']) ?></small></td>
      <td class="num"><?= (int)$o['tk'] ?></td><td><?= (int)$o['came'] ? '<span class="badge green">oui</span>' : (strtotime((string)$o['starts_at']) < time() ? '<span class="badge">non</span>' : '<span class="muted">à venir</span>') ?></td>
      <td class="num"><?= e(money((int)$o['total'] - (int)$o['refunded_amount'])) ?></td><td class="num"><?= isset($barByEv[$o['ev']]) ? e(money((int)$barByEv[$o['ev']])) : '—' ?></td></tr><?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="6" class="empty">Pas de commande : dépenses aux bars seulement.</td></tr><?php endif; ?></tbody></table></div></div>
  <div class="stack">
    <div class="card"><h2>Ce qu'il ou elle consomme</h2><?php foreach (array_slice($prods, 0, 8, true) as $p => $q): ?><div class="list-item"><span class="grow"><?= e($p) ?></span><b>× <?= (int)$q ?></b></div><?php endforeach; ?><?php if (!$prods): ?><p class="muted">Aucun achat cashless rattaché.</p><?php endif; ?></div>
    <div class="card"><h2>Porte-monnaie cashless</h2><?php foreach ($wallets as $w): ?><div class="list-item"><span class="grow"><?= e($w['ev']) ?><br><small class="muted mono"><?= e($w['code']) ?></small></span><b><?= e(money((int)$w['balance'])) ?></b></div><?php endforeach; ?><?php if (!$wallets): ?><p class="muted">Aucun.</p><?php endif; ?></div>
  </div>
</div>
