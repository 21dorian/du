<?php /** @var array $B, $ev; string $base */
$T = $B['R']['tot']; $k = $B['k']; $ok = $B['tx_sum'] === $B['balances'];
$cin = array_sum(array_column($B['cups']['types'], 'value_in')); $cout = array_sum(array_column($B['cups']['types'], 'value_out'));
$li = fn($l, $v, $b = false, $note = '') => '<div class="list-item"><span class="grow">' . ($b ? '<b>' . e($l) . '</b>' : e($l)) . ($note ? '<br><small class="muted">' . e($note) . '</small>' : '') . '</span><b>' . $v . '</b></div>'; ?>
<div class="row mb"><a class="btn" target="_blank" href="<?= url($base . '/bilan', ['pdf' => 1]) ?>">Bilan PDF</a><span class="pnote">Le document à garder après l'événement : ventes, cashless, caisses, consignes, stocks et exploitants.</span></div>
<div class="pk">
  <div class="hero"><span>Chiffre d'affaires TTC</span><b><?= e(money($T['ttc'])) ?></b><small>HT <?= e(money($T['ht'])) ?> · TVA <?= e(money($T['tva'])) ?> · <?= (int)$T['n'] ?> tickets</small></div>
  <div class="good"><span>Marge brute</span><b><?= $T['cost'] ? e(money($B['costed'] - $T['cost'])) : '—' ?></b><small><?= $T['cost'] ? 'sur les produits chiffrés' : 'renseigne les prix d\'achat' ?></small></div>
  <div><span>Soldes restants</span><b><?= e(money($B['balances'])) ?></b><small><?= (int)$B['w']['pos'] ?> porte-monnaie · <?= (int)$B['w']['req'] ?> demande(s)</small></div>
  <div class="<?= $ok ? 'good' : 'warn' ?>"><span>Rapprochement cashless</span><b><?= $ok ? 'Juste' : e(money($B['tx_sum'] - $B['balances'])) ?></b><small>mouvements = soldes</small></div>
</div>
<div class="pgrid-2">
  <div class="card"><h2>Ventes</h2>
    <?= $li('Cashless', e(money($T['cashless']))) . $li('Carte bancaire', e(money($T['card']))) . $li('Espèces', e(money($T['cash']))) ?>
    <?= $li('Offert', e(money($T['offered']))) . $li('Remises', e(money($T['discount']))) . $li('Tickets remboursés', e(money($T['refund']))) . $li('Pourboires', e(money($T['tip'])), false, 'hors chiffre d\'affaires') ?>
    <?php foreach ($B['R']['vat'] as $rate => $v): ?><?= $li('TVA ' . $rate . ' %', e(money($v['tva'])), false, 'base HT ' . money($v['ht'])) ?><?php endforeach; ?></div>
  <div class="card"><h2>Cashless</h2>
    <?= $li('Rechargé en ligne', e(money((int)$k['online']))) . $li('Rechargé sur place', e(money((int)$k['onsite']))) . $li('Crédits offerts', e(money($B['tx']['gift']['a'] ?? 0))) ?>
    <?= $li('Dépensé aux bars', '− ' . e(money((int)$k['spent']))) . $li('Retours de consigne recrédités', e(money($B['tx']['deposit']['a'] ?? 0))) . $li('Achats remboursés', e(money($B['tx']['rembours']['a'] ?? 0))) ?>
    <?= $li('Soldes remboursés', '− ' . e(money((int)$k['refunded']))) . $li('Corrections', e(money($B['tx']['adjust']['a'] ?? 0))) ?>
    <?= $li('Soldes restants', e(money($B['balances'])), true, 'dette envers les festivaliers : à rembourser ou conserver selon tes CGV') ?>
    <?= $li('Porte-monnaie', (string)(int)$B['w']['n'], false, (int)$B['w']['nfc'] . ' bracelets · ' . (int)$B['w']['virt'] . ' cartes virtuelles') ?></div>
  <div class="card"><h2>Caisses</h2>
    <?= $li('Clôtures validées', (int)$B['z']['valid'] . ' / ' . (int)$B['z']['closed'], false, ((int)$B['z']['n'] - (int)$B['z']['closed']) . ' caisse(s) encore ouverte(s)') ?>
    <?= $li('Écart de caisse cumulé', e(money((int)$B['z']['gap'])), true) ?>
    <p class="pnote"><a href="<?= url($base . '/caisses') ?>">Voir et valider les Z</a></p></div>
  <div class="card"><h2>Consignes et stocks</h2>
    <?= $li('Consignes encaissées', e(money($cin))) . $li('Consignes rendues', '− ' . e(money($cout))) . $li('Consignes conservées', e(money($cin - $cout)), true) ?>
    <?= $li('Pertes valorisées', e(money($B['loss'])), false, 'casse, périmé, vol… au prix d\'achat') ?></div>
</div>
<?php if ($B['ops']): ?><div class="card table-card mt"><div class="card-head"><h2>Exploitants</h2><a class="btn btn-sm btn-light" href="<?= url($base . '/exploitants') ?>">Reversements</a></div><div class="table-wrap"><table class="table"><thead><tr><th>Exploitant</th><th class="num">CA TTC</th><th class="num">Commission</th><th class="num">Dû</th><th class="num">Versé</th><th class="num">Reste</th></tr></thead><tbody>
  <?php foreach ($B['ops'] as $o): ?><tr><td><?= e($o['name']) ?></td><td class="num"><?= e(money($o['ttc'])) ?></td><td class="num"><?= e(money($o['commission'])) ?></td><td class="num"><?= e(money($o['due'])) ?></td><td class="num"><?= e(money($o['paid'])) ?></td><td class="num"><b><?= e(money($o['left'])) ?></b></td></tr><?php endforeach; ?>
</tbody></table></div></div><?php endif; ?>
