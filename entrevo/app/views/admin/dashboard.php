<?php if (!$providers || array_keys($providers) === ['demo']): ?>
<div class="flash warning">🧪 Aucun vrai moyen de paiement n'est branché : les acheteurs paient en mode démo. <a href="<?= url('/admin/reglages', ['tab' => 'payments']) ?>">Brancher Stripe, PayPal, SumUp ou Mollie</a>.</div>
<?php endif; ?>
<div class="kpis">
  <div class="kpi"><small class="muted">💳 Encaissé en ligne</small><b><?= e(money((int)$k['collected'])) ?></b><div class="sub"><?= (int)$k['n'] ?> commandes réelles</div></div>
  <div class="kpi"><small class="muted">🤑 Tes commissions</small><b style="color:var(--green)"><?= e(money((int)$k['commission'])) ?></b><div class="sub">ce mois-ci : <?= e(money((int)$month['c'])) ?></div></div>
  <div class="kpi"><small class="muted">🏦 À reverser aux orgas</small><b><?= e(money((int)$k['due'] - $paidOut)) ?></b><div class="sub">déjà reversé : <?= e(money($paidOut)) ?></div></div>
  <div class="kpi"><small class="muted">🎟️ Billets émis</small><b><?= number_format($tickets, 0, ',', ' ') ?></b><div class="sub"><?= $orgs ?> organisateurs</div></div>
</div>
<?php if ($demo): ?><p class="muted">🧪 <?= $demo ?> commande(s) de démonstration ne sont pas comptées ci-dessus.</p><?php endif; ?>
<div class="split">
  <div class="card">
    <div class="card-head"><h2>🏆 Top organisateurs</h2><a href="<?= url('/admin/organisateurs') ?>">Tous</a></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Organisateur</th><th class="num">Commandes</th><th class="num">Ventes</th><th class="num">Commission</th></tr></thead>
      <tbody><?php foreach ($topOrgs as $o): ?><tr><td><a href="<?= url('/admin/organisateurs/' . $o['id']) ?>"><?= e($o['name']) ?></a></td><td class="num"><?= (int)$o['n'] ?></td><td class="num"><?= e(money((int)$o['total'])) ?></td><td class="num"><?= e(money((int)$o['com'])) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
  </div>
  <div class="card">
    <div class="card-head"><h2>👋 Derniers inscrits</h2></div>
    <?php foreach ($newOrgs as $o): ?><a class="list-item" href="<?= url('/admin/organisateurs/' . $o['id']) ?>"><span class="grow"><b><?= e($o['name']) ?></b><br><small><?= e($o['email']) ?></small></span><span class="right"><?= badge($o['status']) ?><br><small><?= e(short_date($o['created_at'])) ?></small></span></a><?php endforeach; ?>
    <?php if (!$newOrgs): ?><p class="muted">Personne encore. Ça va venir 💪</p><?php endif; ?>
  </div>
</div>
