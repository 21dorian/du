<div class="kpis">
  <div class="kpi"><small class="muted">Encaissé en ligne pour toi</small><b><?= e(money($balance['collected'])) ?></b><div class="sub"><?= $balance['orders'] ?> commandes</div></div>
  <div class="kpi"><small class="muted">Commission plateforme</small><b><?= e(money($balance['commission'])) ?></b><div class="sub"><?= e(rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',')) ?> % + <?= e(money($fixed)) ?> / billet</div></div>
  <div class="kpi"><small class="muted">Déjà reversé</small><b><?= e(money($balance['paid_out'])) ?></b></div>
  <div class="kpi"><small class="muted">Reste à te reverser</small><b class="u-green"><?= e(money($balance['balance'])) ?></b></div>
</div>
<?php if ($demo): ?><div class="flash info"><?= $demo ?> commande(s) payée(s) en mode démo ne comptent pas ici : aucun argent réel.</div><?php endif; ?>
<?php if (!$org['iban']): ?><div class="flash warning">Pas encore d'IBAN : <a href="<?= url('/pro/organisation') ?>">ajoute-le</a> pour recevoir tes reversements.</div><?php endif; ?>
<div class="split-3">
  <div class="card">
    <h2>Par événement</h2>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Événement</th><th class="num">Encaissé en ligne</th><th class="num">Commission</th><th class="num">Pour toi</th><th class="num">Guichet (ta caisse)</th></tr></thead>
      <tbody>
      <?php foreach ($byEvent as $r): ?>
        <tr><td><b><?= e($r['name']) ?></b><br><small><?= e(fr_date($r['starts_at'], false)) ?></small></td><td class="num"><?= e(money((int)$r['collected'])) ?></td><td class="num"><?= e(money((int)$r['commission'])) ?></td><td class="num"><b><?= e(money((int)$r['due'])) ?></b></td><td class="num"><?= e(money((int)$r['box'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card">
    <h2>Reversements reçus</h2>
    <?php foreach ($payouts as $p): ?><div class="list-item"><span class="grow"><b><?= e(money((int)$p['amount'])) ?></b><br><small><?= e(short_date($p['created_at'])) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></small></span></div><?php endforeach; ?>
    <?php if (!$payouts): ?><p class="muted">Aucun reversement pour l'instant.</p><?php endif; ?>
    <p class="muted u-fs14">Les reversements sont faits par virement par la plateforme.</p>
    <div class="terms-box">
      <div class="row between"><b>Tes conditions</b><?= $terms['plan'] ? '<span class="badge grey">Formule ' . e($terms['plan']['name']) . '</span>' : '' ?></div>
      <p class="mb0"><?= e(rtrim(rtrim(number_format($terms['pct'], 2, ',', ''), '0'), ',')) ?> % + <?= e(money($terms['fixed'])) ?> par billet<?= $terms['cap'] !== null ? ', au plus ' . e(money($terms['cap'])) . ' par billet' : '' ?>
        <?php if ($terms['contract']): ?><br><small class="muted">Contrat « <?= e($terms['contract']['label']) ?> »<?= $terms['contract']['ends_on'] ? ' jusqu\'au ' . e(date('d/m/Y', strtotime($terms['contract']['ends_on']))) : '' ?><?= $terms['contract']['threshold'] ? ' · ' . e(rtrim(rtrim(number_format((float)$terms['contract']['fee_percent_after'], 2, ',', ''), '0'), ',')) . ' % au-delà de ' . e(money((int)$terms['contract']['threshold'])) . ' de ventes' : '' ?></small><?php endif; ?>
        <?php if ($terms['plan'] && (int)$terms['plan']['monthly_price']): ?><br><small class="muted">Abonnement : <?= e(money((int)$terms['plan']['monthly_price'])) ?> par mois, retenu sur tes reversements</small><?php endif; ?></p>
    </div>
    <form method="get" action="<?= url('/pro/finances/releve') ?>" target="_blank" class="row" style="flex-wrap:nowrap;gap:0"><input type="month" name="mois" value="<?= date('Y-m', strtotime('first day of last month')) ?>"><button class="btn btn-sm">Relevé PDF</button></form>
  </div>
</div>
<?php if ($charges): ?>
<div class="card table-card mt">
  <div class="card-head"><h2>Retenues sur reversements</h2><small class="muted">Total : <?= e(money((int)$balance['charges'])) ?></small></div>
  <div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Nature</th><th>Détail</th><th class="num">Montant</th></tr></thead><tbody>
  <?php foreach ($charges as $c): ?><tr><td class="nowrap"><small><?= e(short_date($c['created_at'])) ?></small></td><td><span class="badge grey"><?= e(Billing::CHARGE_KINDS[$c['kind']] ?? $c['kind']) ?></span></td><td><?= e($c['label']) ?></td><td class="num"><?= (int)$c['amount'] < 0 ? '+' . e(money(-(int)$c['amount'])) : '−' . e(money((int)$c['amount'])) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>
<div class="split mt">
  <div class="card">
    <div class="card-head"><h2>Factures de commission</h2><small class="muted">Émises par <?= e(setting('platform_name')) ?> chaque mois</small></div>
    <?php foreach ($invoices as $inv): ?>
      <a class="todo" href="<?= url('/pro/finances/factures/' . $inv['id']) ?>" target="_blank"><span class="todo-ic"></span><span><b class="mono"><?= e($inv['number']) ?></b><small><?= e(Invoices::monthLabel($inv['period'])) ?> · <?= (int)$inv['orders_count'] ?> commande(s)</small></span><b><?= e(money((int)$inv['amount_ttc'])) ?></b></a>
    <?php endforeach; ?>
    <?php if (!$invoices): ?><p class="muted">Pas encore de facture. Elles arrivent au début de chaque mois pour le mois précédent.</p><?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h2>Export comptable</h2></div>
    <form method="get" action="<?= url('/pro/finances/export') ?>" class="stack">
      <div class="grid2"><div class="field mb0"><label>Du</label><input type="date" name="du" value="<?= date('Y-01-01') ?>"></div><div class="field mb0"><label>Au</label><input type="date" name="au" value="<?= date('Y-m-d') ?>"></div></div>
      <div class="row u-gap8"><button class="btn btn-sm" name="format" value="csv">Ventes détaillées (CSV)</button><button class="btn btn-sm btn-light" name="format" value="fec">Écritures (format FEC)</button></div>
      <small class="muted">Le FEC utilise des comptes standards (706 ventes, 44571 TVA, 467 plateforme, 6226 commissions, 512 banque) : ton comptable pourra les ajuster.</small>
    </form>
  </div>
</div>
<div class="card mt">
  <div class="card-head"><h2>TVA collectée</h2><small class="muted">Taux par défaut : <?= e(Vat::label($org['vat_rate'] !== null ? (float)$org['vat_rate'] : null)) ?> · <a href="<?= url('/pro/organisation') ?>">modifier</a></small></div>
  <?php if (!$vatMonths): ?><p class="muted">Pas encore de vente payante.</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Mois</th><th>Taux</th><th class="num">Ventes HT</th><th class="num">TVA</th><th class="num">TTC</th></tr></thead><tbody>
    <?php foreach ($vatMonths as $m => $rates): foreach ($rates as $k => $v): ?>
      <tr><td><?= e(Invoices::monthLabel($m)) ?></td><td><?= e($k === 'none' ? 'Non assujetti' : Vat::label((float)$k)) ?></td><td class="num"><?= e(money($v['ht'])) ?></td><td class="num"><b><?= e(money($v['vat'])) ?></b></td><td class="num"><?= e(money($v['ttc'])) ?></td></tr>
    <?php endforeach; endforeach; ?>
  </tbody></table></div>
  <p class="muted mb0 u-fs13">Calcul sur le prix des billets et options (réductions réparties), hors frais de service et dons. Tous canaux confondus (en ligne, guichet, invitations payantes).</p>
  <?php endif; ?>
</div>
<?= soon_card('Bientôt dans tes finances', [
  'stripe-connect' => ['Paiement direct sur ton compte', 'L\'argent arrive chez toi sans attendre les reversements'],
  'subventions' => ['Bilan pour les financeurs', 'Les chiffres prêts à copier'],
]) ?>
