<?php /** @var array $L, $ev; string $base */
$m = $L['methods']; $mt = fn($k) => (int)($m[$k]['total'] ?? 0); $all = max(1, $mt('cashless') + $mt('card') + $mt('cash'));
$max = max(array_merge(array_values($L['slots']), [1])); $topBar = max(array_merge(array_map(fn($b) => $b['ttc'], $L['bars']), [1])); ?>
<div data-live-url="<?= Pay::url('/pro/e/' . $ev['id'] . '/live.json') ?>"></div>
<?php if (!(int)$ev['cashless_enabled']): ?><div class="notice info mb">Le cashless est désactivé pour cet événement : les bars acceptent la carte et les espèces. Active-le dans <a href="<?= Pay::url('/pro/e/' . $ev['id'] . '/portail') ?>">Réglages › Portail & cashless</a>.</div><?php endif; ?>
<div class="pk">
  <div class="hero"><span>Chiffre d'affaires des bars</span><b data-live="ttc"><?= e(money($L['ttc'])) ?></b><small><span data-live="sales"><?= (int)$L['sales'] ?> ventes</span> · panier moyen <span data-live="basket"><?= e(money($L['basket'])) ?></span></small></div>
  <div><span>Dernière heure</span><b data-live="hour"><?= e(money($L['hour'])) ?></b><small data-live="hour_n"><?= (int)$L['hour_n'] ?> ventes · <?= e((string)$L['per_min']) ?>/min</small></div>
  <div><span>Aujourd'hui</span><b data-live="today"><?= e(money($L['today'])) ?></b><small><?= (int)$L['today_n'] ?> ventes</small></div>
  <div><span>Soldes en circulation</span><b data-live="float"><?= e(money($L['float'])) ?></b><small>argent des festivaliers non dépensé</small></div>
  <div><span>Rechargé</span><b data-live="loaded"><?= e(money($L['loaded'])) ?></b><small>en ligne <?= e(money($L['online'])) ?> · sur place <?= e(money($L['onsite'])) ?></small></div>
  <div><span>Porte-monnaie</span><b data-live="wallets"><?= (int)$L['wallets'] ?></b><small><?= (int)$L['nfc'] ?> bracelets · <?= (int)$L['virtual'] ?> cartes virtuelles</small></div>
  <div><span>Terminaux en ligne</span><b data-live="devices"><?= (int)$L['devices_online'] ?> / <?= (int)$L['devices'] ?></b><small><a href="<?= $base ?>/materiel">Parc de terminaux</a></small></div>
</div>
<div class="pgrid">
  <div class="card"><h2>Ventes par quart d'heure <small class="muted">· 6 dernières heures</small></h2>
    <div class="pbars" data-live-bars><?php foreach ($L['slots'] as $k => $v): ?><div title="<?= e($k . ' : ' . money($v)) ?>"><i style="height:<?= (int)round($v / $max * 100) ?>%"></i><small><?= e($k) ?></small></div><?php endforeach; ?></div></div>
  <div class="card"><h2>Moyens de paiement</h2>
    <?php foreach (['cashless' => ['Cashless', '#5e5ce6'], 'card' => ['Carte', '#0a84ff'], 'cash' => ['Espèces', '#30d158']] as $k => [$l, $c]): ?>
      <div style="margin-bottom:12px"><div class="row" style="justify-content:space-between"><span><?= $l ?></span><b><?= e(money($mt($k))) ?></b></div><div class="pmeter"><i style="width:<?= (int)round($mt($k) / $all * 100) ?>%;background:<?= $c ?>"></i></div></div>
    <?php endforeach; ?>
    <p class="pnote">Offert : <?= e(money($L['offered'])) ?> · Crédits staff : <?= e(money((int)($m['allowance']['offered'] ?? 0))) ?></p></div>
</div>
<div class="pgrid mt">
  <div class="card"><h2>Points de vente</h2><div class="plive" data-live-pos>
    <?php foreach ($L['bars'] as $b): ?><div class="row-it"><div><b><?= e($b['name']) ?></b><small><?= $b['operator'] ? e($b['operator']) . ' · ' : '' ?><?= (int)$b['devices'] ?> appareil(s) · <?= $b['open'] ? 'caisse ouverte' : 'caisse fermée' ?></small><div class="pmeter"><i style="width:<?= (int)round($b['ttc'] / $topBar * 100) ?>%"></i></div></div>
      <div class="num"><small>15 min</small><?= e(money($b['ttc15'])) ?></div><div class="num"><small><?= (int)$b['n'] ?> ventes</small><b><?= e(money($b['ttc'])) ?></b></div><div class="num"><small>espèces</small><?= e(money($b['cash'])) ?></div></div><?php endforeach; ?>
    <?php if (!$L['bars']): ?><p class="pempty">Aucun point de vente. Crée-les dans <a href="<?= $base ?>/appareils">Points de vente & appareils</a>.</p><?php endif; ?></div></div>
  <div class="stack">
    <div class="card"><h2>Alertes</h2><div class="palerts" data-live-alerts><?php foreach ($L['alerts'] as [$lvl, $txt]): ?><div class="<?= e($lvl) ?>"><?= e($txt) ?></div><?php endforeach; ?><?php if (!$L['alerts']): ?><p class="pnote">Aucune alerte. Tout roule.</p><?php endif; ?></div></div>
    <div class="card"><h2>Top produits <small class="muted">· 24 h</small></h2><?php foreach ($L['products'] as $n => $p): ?><div class="list-item"><span class="grow"><?= e($n) ?></span><small class="muted">× <?= (int)$p['q'] ?></small><b style="min-width:84px;text-align:right"><?= e(money((int)$p['t'])) ?></b></div><?php endforeach; ?><?php if (!$L['products']): ?><p class="pnote">Pas encore de vente.</p><?php endif; ?></div>
  </div>
</div>
