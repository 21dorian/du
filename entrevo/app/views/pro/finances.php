<div class="kpis">
  <div class="kpi"><small class="muted">Encaissé en ligne pour toi</small><b><?= e(money($balance['collected'])) ?></b><div class="sub"><?= $balance['orders'] ?> commandes</div></div>
  <div class="kpi"><small class="muted">Commission plateforme</small><b><?= e(money($balance['commission'])) ?></b><div class="sub"><?= e(rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',')) ?> % + <?= e(money($fixed)) ?> / billet</div></div>
  <div class="kpi"><small class="muted">Déjà reversé</small><b><?= e(money($balance['paid_out'])) ?></b></div>
  <div class="kpi"><small class="muted">💰 Reste à te reverser</small><b style="color:var(--green)"><?= e(money($balance['balance'])) ?></b></div>
</div>
<?php if ($demo): ?><div class="flash info">🧪 <?= $demo ?> commande(s) payée(s) en mode démo ne comptent pas ici : aucun argent réel.</div><?php endif; ?>
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
    <p class="muted" style="font-size:14px">Les reversements sont faits par virement par la plateforme.</p>
    <form method="get" action="<?= url('/pro/finances/releve') ?>" target="_blank" class="row" style="flex-wrap:nowrap;gap:0"><input type="month" name="mois" value="<?= date('Y-m', strtotime('first day of last month')) ?>"><button class="btn btn-sm">📄 Relevé PDF</button></form>
  </div>
</div>
