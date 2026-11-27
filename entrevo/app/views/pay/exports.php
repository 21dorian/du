<?php /** @var array $k, $ev; string $base */
$x = fn($k) => url($base . '/exports', ['x' => $k]); ?>
<div class="pactions">
  <div class="card stack"><h2>Journal de caisse</h2><p class="pnote u-m0">Une ligne par vente : date, ticket, point de vente, serveur, moyen de paiement, TTC, HT, TVA, offert, remise.</p><a class="btn btn-sm" href="<?= $x('journal') ?>">Télécharger (CSV)</a></div>
  <div class="card stack"><h2>Écritures comptables</h2><p class="pnote u-m0">Écritures de ventes par jour au format journal (VT) : encaissements (531 espèces, 5112 carte, 4191 cashless), ventes HT par taux (707), TVA collectée (44571), consignes (4198). Compatible import Sage, Cegid, EBP.</p><a class="btn btn-sm" href="<?= $x('ecritures') ?>">Télécharger (CSV)</a></div>
  <div class="card stack"><h2>Mouvements cashless</h2><p class="pnote u-m0">Toutes les opérations des porte-monnaie : recharges, achats, retours de consigne, transferts, corrections, remboursements.</p><a class="btn btn-sm" href="<?= $x('wallet') ?>">Télécharger (CSV)</a></div>
  <div class="card stack"><h2>Soldes restants</h2><p class="pnote u-m0">Soldes non dépensés à la date d'aujourd'hui (<?= e(money((int)$k['left'])) ?>) : la dette envers les festivaliers, à rembourser ou à conserver selon tes CGV.</p><a class="btn btn-sm" href="<?= $x('balances') ?>">Télécharger (CSV)</a></div>
  <div class="card stack"><h2>Rapport de ventes</h2><p class="pnote u-m0">CA, TVA par taux, par jour, par point de vente, par catégorie et par serveur, en PDF ou CSV.</p><a class="btn btn-sm btn-light" href="<?= url($base . '/rapport') ?>">Ouvrir le rapport</a></div>
  <div class="card stack"><h2>Clôtures (Z)</h2><p class="pnote u-m0">Tickets Z de chaque caisse avec comptage, écart et validation.</p><a class="btn btn-sm btn-light" href="<?= url($base . '/caisses') ?>">Caisses & clôtures</a></div>
</div>
