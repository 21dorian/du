<?php if (!$hasEvents): ?>
<div class="card center" style="padding:44px 22px">
  
  <h2>🎉 Bienvenue, <?= e($org['name']) ?> !</h2>
  <p class="muted">Première étape : ton premier événement. Il reste en brouillon (invisible) tant que tu ne le publies pas. Zéro stress.</p>
  <a class="btn btn-lg" href="<?= url('/pro/evenements/nouveau') ?>">Je crée mon premier événement 🚀</a>
</div>
<?php else: ?>
<div class="kpis">
  <div class="kpi"><small class="muted">💶 Chiffre d'affaires</small><b><?= e(money((int)$k['total'])) ?></b><div class="sub"><?= (int)$k['n'] ?> commandes payées</div></div>
  <div class="kpi"><small class="muted">🎟️ Billets vendus</small><b><?= number_format($tickets, 0, ',', ' ') ?></b><div class="sub">tous événements</div></div>
  <div class="kpi"><small class="muted">☀️ Aujourd'hui</small><b><?= e(money((int)$today['t'])) ?></b><div class="sub"><?= (int)$today['n'] ?> commande<?= $today['n'] > 1 ? 's' : '' ?></div></div>
  <div class="kpi"><small class="muted">💰 À te reverser</small><b><?= e(money($balance['balance'])) ?></b><div class="sub"><a href="<?= url('/pro/finances') ?>">Voir les finances →</a></div></div>
</div>
<div class="split-3">
  <div class="card">
    <div class="card-head"><h2>Ventes des 30 derniers jours</h2></div>
    <?= view('pro/_chart', ['chart' => $chart]) ?>
  </div>
  <div class="card">
    <div class="card-head"><h2>🔔 Dernières commandes</h2><a href="<?= url('/pro/commandes') ?>">Tout voir</a></div>
    <?php foreach ($recent as $o): ?>
      <a class="list-item" href="<?= url('/pro/commandes/' . $o['id']) ?>" style="color:inherit;text-decoration:none">
        <span class="avatar" style="width:32px;height:32px;font-size:13px"><?= e(mb_strtoupper(mb_substr((string)$o['first_name'], 0, 1) . mb_substr((string)$o['last_name'], 0, 1))) ?></span>
        <span class="grow" style="min-width:0;line-height:1.3"><b style="font-size:14px"><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b><br><small><?= e($o['ev_name']) ?></small></span>
        <span class="right" style="line-height:1.3"><b style="font-size:14px"><?= e(money((int)$o['total'])) ?></b><br><small><?= e(short_date($o['created_at'])) ?></small></span>
      </a>
    <?php endforeach; ?>
    <?php if (!$recent): ?><p class="muted">Rien pour l'instant. Partage ton lien, ça va venir 💪</p><?php endif; ?>
  </div>
</div>
<div class="card">
  <div class="card-head"><h2>📅 Prochains événements</h2><a href="<?= url('/pro/evenements') ?>">Tous les événements</a></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Événement</th><th>Date</th><th>Statut</th><th>Remplissage</th><th class="num">Recettes</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($upcoming as $ev): $cap = (int)$ev['capacity']; $pct = $cap ? min(100, round($ev['stats']['sold'] / $cap * 100)) : null; ?>
      <tr>
        <td><a href="<?= url('/pro/evenements/' . $ev['id']) ?>"><b><?= e($ev['name']) ?></b></a></td>
        <td class="nowrap"><?= e(fr_date($ev['starts_at'], false)) ?></td>
        <td><?= badge($ev['status']) ?></td>
        <td style="min-width:160px"><?= (int)$ev['stats']['sold'] ?><?= $cap ? ' / ' . $cap : '' ?> billets<?php if ($pct !== null): ?><div class="bar"><i style="width:<?= $pct ?>%"></i></div><?php endif; ?></td>
        <td class="num"><?= e(money($ev['stats']['revenue'])) ?></td>
        <td class="right"><a class="btn btn-sm btn-ghost" href="<?= url('/pro/evenements/' . $ev['id']) ?>">Gérer</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$upcoming): ?><tr><td colspan="6" class="empty">Aucun événement à venir. On en crée un ?</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
