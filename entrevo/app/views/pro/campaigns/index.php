<?php /** @var array $list, $counts, $tot, $templates */ $pct = fn($a, $b) => $b ? number_format($a / $b * 100, 1, ',', ' ') . ' %' : '—'; ?>
<p class="muted">Préviens tes anciens participants de ton prochain événement. On n'écrit qu'aux personnes qui l'ont accepté (ou à tes clients pour des événements du même genre), avec un lien de désinscription dans chaque e-mail.</p>
<div class="page-head-row">
  <div class="kpi-mini"><span>📬 Inscrits aux actus</span><b><?= number_format($counts['optin'], 0, ',', ' ') ?></b></div>
  <div class="kpi-mini"><span>🛒 Clients joignables</span><b><?= number_format($counts['customers'], 0, ',', ' ') ?></b></div>
  <div class="kpi-mini"><span>👀 Taux d'ouverture</span><b><?= $pct($tot['opened'], $tot['sent']) ?></b></div>
  <div class="kpi-mini"><span>👆 Taux de clic</span><b><?= $pct($tot['clicked'], $tot['sent']) ?></b></div>
  <div class="kpi-mini"><span>💶 Ventes générées</span><b><?= e(money($tot['revenue'])) ?></b></div>
</div>

<div class="card">
  <div class="card-head"><h2>✨ Nouvelle campagne</h2><small class="muted">Choisis un point de départ, tout se modifie ensuite</small></div>
  <form method="post" action="<?= url('/pro/campagnes/nouvelle') ?>" class="tpl-grid"><?= csrf_field() ?>
    <?php foreach ($templates as $k => [$label, $subject]): ?>
      <button class="tpl" name="modele" value="<?= e($k) ?>"><span class="tpl-ico"><?= e(mb_substr($label, 0, 2)) ?></span><b><?= e(trim(mb_substr($label, 2))) ?></b><small><?= e($subject ?: 'Commence de zéro') ?></small></button>
    <?php endforeach; ?>
  </form>
</div>

<div class="card table-card">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Campagne</th><th>Statut</th><th class="num">Destinataires</th><th class="num">Ouvertures</th><th class="num">Clics</th><th class="num">Ventes</th><th>Date</th></tr></thead>
    <tbody>
    <?php foreach ($list as $c): $s = $c['stats']; ?>
      <tr data-href="<?= url('/pro/campagnes/' . $c['id']) ?>">
        <td><a href="<?= url('/pro/campagnes/' . $c['id']) ?>"><b><?= e($c['name']) ?></b></a><br><small class="muted"><?= e($c['subject'] ?: '(sans objet)') ?></small></td>
        <td><?= Campaigns::badge($c['status']) ?><?php if ($c['status'] === 'sending' && $s): ?><br><small class="muted"><?= $s['sent'] ?> / <?= $s['total'] ?></small><?php endif; ?></td>
        <td class="num"><?= $s ? number_format($s['total'], 0, ',', ' ') : '<span class="muted">—</span>' ?></td>
        <td class="num"><?= $s ? number_format($s['open_rate'], 1, ',', ' ') . ' %' : '<span class="muted">—</span>' ?></td>
        <td class="num"><?= $s ? number_format($s['click_rate'], 1, ',', ' ') . ' %' : '<span class="muted">—</span>' ?></td>
        <td class="num"><?= $s && $s['revenue'] ? '<b>' . e(money($s['revenue'])) . '</b>' : '<span class="muted">—</span>' ?></td>
        <td class="nowrap"><small><?= e(short_date($c['sent_at'] ?: $c['scheduled_at'] ?: $c['updated_at'] ?: $c['created_at'])) ?></small></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="7" class="empty">Pas encore de campagne. Choisis un modèle juste au-dessus 👆</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<p class="muted" style="font-size:13px">💡 Les acheteurs peuvent cocher « recevoir les prochains événements » au moment de payer : ta liste grossit toute seule. Les e-mails partent par lots de <?= Campaigns::BATCH ?> toutes les minutes environ, pour rester dans les limites de ton hébergement.</p>
