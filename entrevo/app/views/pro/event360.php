<?php /** @var array $D, $ev */
$tk = $D['tk']; $fc = $D['fc']; $cap = $D['cap']; $total = $D['ticketRev'] + $D['barRev'];
$fill = $cap ? min(100, (int)round((int)$tk['n'] / $cap * 100)) : null;
$maxIn = max(array_merge([1], array_column($D['hours'], 'in'))); $maxBar = max(array_merge([1], array_column($D['hours'], 'bar')));
$base = '/pro/evenements/' . $ev['id']; ?>
<div class="row between mb"><p class="muted mb0">Billetterie, entrées, bars et cashless réunis. Les chiffres des bars viennent d'Entrevo Pay.</p>
  <div class="row"><a class="btn btn-sm btn-light" href="<?= url($base . '/360', ['export' => 'csv']) ?>">Export CSV</a><a class="btn btn-sm" href="<?= e(Pay::url('/pro/e/' . $ev['id'] . '/bilan')) ?>">Bilan des bars ↗</a></div></div>
<div class="v360-hero card">
  <div><span>Recette totale</span><b><?= e(money($total)) ?></b><small>billetterie <?= e(money($D['ticketRev'])) ?> · bars <?= e(money($D['barRev'])) ?></small></div>
  <div><span>Billets</span><b><?= (int)$tk['n'] ?><?= $cap ? '<em> / ' . $cap . '</em>' : '' ?></b><?php if ($fill !== null): ?><div class="bar-thin"><i style="width:<?= $fill ?>%"></i></div><?php endif; ?><small><?= (int)$tk['inv'] ?> invitation(s)</small></div>
  <div><span>Présents</span><b><?= (int)$tk['inside_ever'] ?></b><small><?= e((string)$D['show_rate']) ?> % des billets · <?= (int)$tk['inside_now'] ?> sur site</small></div>
  <div><span>Dépense bar / présent</span><b><?= e(money($D['per_head'])) ?></b><small>dont cashless <?= e(money($D['spend_per_head'])) ?></small></div>
</div>
<div class="split mt">
  <div class="card"><h2>Billetterie</h2>
    <div class="list-item"><span class="grow">Encaissé (TTC)</span><b><?= e(money((int)$D['ord']['total'])) ?></b></div>
    <div class="list-item"><span class="grow">Remboursé</span><b>− <?= e(money((int)$D['ord']['refunded'])) ?></b></div>
    <div class="list-item"><span class="grow">Frais de service</span><b><?= e(money((int)$D['ord']['fees'])) ?></b></div>
    <div class="list-item"><span class="grow">Revenant à l'organisateur</span><b><?= e(money((int)$D['ord']['net'])) ?></b></div>
    <?php foreach (['product' => 'Options', 'shop' => 'Boutique', 'gift' => 'Cartes cadeaux', 'topup' => 'Recharges cashless en ligne'] as $kk => $kl): if (empty($D['items'][$kk])) continue; ?><div class="list-item"><span class="grow"><?= $kl ?></span><b><?= e(money($D['items'][$kk])) ?></b></div><?php endforeach; ?>
    <?php if ((int)$D['ord']['donation']): ?><div class="list-item"><span class="grow">Dons</span><b><?= e(money((int)$D['ord']['donation'])) ?></b></div><?php endif; ?>
    <h3 class="mt">Par tarif</h3>
    <?php foreach ($D['byRate'] as $r): $q = (int)$r['quota']; ?><div class="list-item"><span class="grow"><?= e($r['name']) ?><?= $r['dyn_json'] ? ' <span class="badge">dynamique</span>' : '' ?><br><small class="muted"><?= e(money((int)$r['price'])) ?></small></span><b><?= (int)$r['sold'] ?><?= $q ? '<small class="muted"> / ' . $q . '</small>' : '' ?></b></div><?php endforeach; ?></div>
  <div class="card"><h2>Bars & cashless</h2>
    <div class="list-item"><span class="grow">Chiffre d'affaires des bars</span><b><?= e(money($D['barRev'])) ?></b></div>
    <div class="list-item"><span class="grow">Tickets de caisse</span><b><?= (int)$D['bar']['n'] ?></b></div>
    <div class="list-item"><span class="grow">Pourboires</span><b><?= e(money((int)$D['bar']['tips'])) ?></b></div>
    <div class="list-item"><span class="grow">Cashless rechargé</span><b><?= e(money((int)$D['k']['loaded'])) ?></b></div>
    <div class="list-item"><span class="grow">Soldes restants</span><b><?= e(money((int)$D['k']['left'])) ?></b></div>
    <h3 class="mt">Points de vente</h3>
    <?php foreach ($D['topBars'] as $b): ?><div class="list-item"><span class="grow"><?= e($b['name']) ?> <small class="muted">· <?= (int)$b['n'] ?> ventes</small></span><b><?= e(money((int)$b['ttc'])) ?></b></div><?php endforeach; ?>
    <?php if (!$D['topBars']): ?><p class="muted">Pas de bar pour cet événement.</p><?php endif; ?></div>
</div>
<div class="split mt">
  <div class="card"><h2>Prévision de remplissage</h2>
    <?php if ($fc): ?><div class="list-item"><span class="grow">Billets attendus le jour J</span><b><?= (int)$fc['forecast'] ?><?= $cap ? ' / ' . $cap : '' ?></b></div>
      <div class="list-item"><span class="grow">Fourchette</span><b><?= (int)$fc['low'] ?> à <?= (int)$fc['high'] ?></b></div>
      <div class="list-item"><span class="grow">Rythme (14 derniers jours)</span><b><?= e(number_format($fc['rate14'], 1, ',', ' ')) ?> / jour</b></div>
      <?php if ($fc['need'] !== null): ?><div class="list-item"><span class="grow">Pour être complet</span><b><?= e(number_format($fc['need'], 1, ',', ' ')) ?> / jour</b></div><?php endif; ?>
      <p class="muted"><small>Méthode : <?= $fc['method'] === 'historique' ? 'comparaison avec tes événements passés au même moment' : 'rythme récent prolongé' ?> · J-<?= (int)$fc['days'] ?>.</small></p>
    <?php else: ?><p class="muted">L'événement a commencé ou est passé : place au bilan.</p><?php endif; ?>
    <?php if ($D['budget'] && ((int)$D['budget']['inc'] || (int)$D['budget']['exp'])): $res = $total - (int)$D['budget']['exp']; ?>
      <h3 class="mt">Budget</h3>
      <div class="list-item"><span class="grow">Recettes prévues</span><b><?= e(money((int)$D['budget']['inc'])) ?></b></div>
      <div class="list-item"><span class="grow">Dépenses prévues</span><b><?= e(money((int)$D['budget']['exp'])) ?></b></div>
      <div class="list-item"><span class="grow">Résultat avec les recettes réelles</span><b class="<?= $res < 0 ? 'text-red' : 'text-green' ?>"><?= e(money($res)) ?></b></div>
    <?php endif; ?></div>
  <div class="card"><h2>Jour J : entrées et ventes des bars par heure</h2>
    <?php if ($D['hours']): ?><div class="v360-hours"><?php foreach ($D['hours'] as $h => $x): ?><div title="<?= e($h . ' : ' . $x['in'] . ' entrées · ' . money($x['bar'])) ?>"><span class="in" style="height:<?= (int)round($x['in'] / $maxIn * 100) ?>%"></span><span class="bar" style="height:<?= (int)round($x['bar'] / $maxBar * 100) ?>%"></span><small><?= e(substr($h, -3)) ?></small></div><?php endforeach; ?></div>
      <p class="muted"><small><span class="v360-key in"></span> entrées · <span class="v360-key bar"></span> ventes des bars</small></p>
    <?php else: ?><p class="muted">Les entrées et les ventes s'afficheront ici le jour J.</p><?php endif; ?>
    <h3 class="mt">Ventes de billets · 30 jours</h3>
    <?php $mx = max(array_merge([1], array_map(fn($x) => (int)$x['t'], $D['byDay']))); ?>
    <div class="v360-days"><?php foreach ($D['byDay'] as $x): ?><i title="<?= e(fr_date($x['d'], false) . ' : ' . $x['n'] . ' commande(s) · ' . money((int)$x['t'])) ?>" style="height:<?= max(3, (int)round((int)$x['t'] / $mx * 100)) ?>%"></i><?php endforeach; ?></div></div>
</div>
