<?php /** @var array $list, $k, $orgs; ?array $sel, $detail; string $month */
$pctTxt = fn($v) => rtrim(rtrim(number_format((float)$v, 2, ',', ''), '0'), ',') . ' %'; ?>
<p class="muted lead-sm">Offices de tourisme, CSE, commerces : ils vendent au comptoir les billets des organisateurs qui les y autorisent, depuis un portail web. Ils encaissent l'acheteur eux-mêmes et reversent chaque mois à l'organisateur le montant vendu, moins leur commission.</p>
<div class="page-head-row">
  <div class="kpi-mini"><span>Revendeurs actifs</span><b><?= (int)$k['n'] ?></b></div>
  <div class="kpi-mini"><span>Ventes du mois</span><b><?= e(money((int)$k['total'])) ?></b></div>
  <div class="kpi-mini"><span>Commissions du mois</span><b><?= e(money((int)$k['comm'])) ?></b></div>
  <div class="kpi-mini"><span>Ventes depuis janvier</span><b><?= e(money((int)$k['year'])) ?></b></div>
</div>
<div class="card table-card">
  <div class="card-head"><h2>Revendeurs</h2><a class="btn btn-sm" href="<?= url('/admin/revendeurs', ['r' => 'new']) ?>#fiche">+ Revendeur</a></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Revendeur</th><th>Organisateurs</th><th class="num">Commission</th><th class="num">Ventes du mois</th><th>Dernière vente</th></tr></thead><tbody>
    <?php foreach ($list as $r): ?>
      <tr class="<?= (int)$r['active'] ? '' : 'muted' ?>"><td><a href="<?= url('/admin/revendeurs', ['r' => $r['id']]) ?>#fiche"><b><?= e($r['name']) ?></b></a><?= (int)$r['active'] ? '' : ' <span class="badge grey">suspendu</span>' ?><br><small class="muted"><?= e(trim(($r['city'] ?? '') . ($r['kind'] ? ' · ' . (Resellers::KINDS[$r['kind']] ?? '') : ''), ' ·')) ?></small></td>
        <td><?= (int)$r['orgs'] ?></td><td class="num"><?= e($pctTxt($r['commission_pct'])) ?></td>
        <td class="num"><?= e(money((int)$r['month_total'])) ?><br><small class="muted"><?= e(money((int)$r['month_comm'])) ?> de commission</small></td>
        <td><?= $r['last_sale'] ? e(ago($r['last_sale'])) : '<span class="muted">jamais</span>' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="5" class="empty">Aucun revendeur pour l'instant.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>

<?php if ($sel !== null): $r = $sel ?: ['id' => 0, 'name' => '', 'kind' => '', 'contact_name' => '', 'email' => '', 'phone' => '', 'city' => '', 'commission_pct' => 5, 'active' => 1, 'token' => '']; ?>
<div class="split mt" id="fiche">
  <div>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="reseller_id" value="<?= (int)$r['id'] ?>">
      <div class="card-head"><h2><?= $r['id'] ? e($r['name']) : 'Nouveau revendeur' ?></h2></div>
      <div class="grid2">
        <div class="field"><label>Nom</label><input name="name" required value="<?= e($r['name']) ?>" placeholder="Office de tourisme de Lyon"></div>
        <div class="field"><label>Type</label><select name="kind"><option value="">—</option><?php foreach (Resellers::KINDS as $kk => $l): ?><option value="<?= $kk ?>" <?= $r['kind'] === $kk ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Contact</label><input name="contact_name" value="<?= e((string)$r['contact_name']) ?>"></div>
        <div class="field"><label>Ville</label><input name="city" value="<?= e((string)$r['city']) ?>"></div>
        <div class="field"><label>E-mail</label><input type="email" name="email" required value="<?= e((string)$r['email']) ?>"></div>
        <div class="field"><label>Téléphone</label><input name="phone" value="<?= e((string)$r['phone']) ?>"></div>
        <div class="field"><label>Commission par défaut</label><div class="input-suffix"><input name="commission_pct" inputmode="decimal" value="<?= e(str_replace('.', ',', (string)(float)$r['commission_pct'])) ?>"><span>%</span></div><small class="muted">Chaque organisateur peut en négocier une autre.</small></div>
      </div>
      <div class="row"><button class="btn"><?= $r['id'] ? 'Enregistrer' : 'Créer le revendeur' ?></button></div>
    </form>
    <?php if ($r['id']): ?>
    <div class="card">
      <div class="card-head"><h2>Portail de vente</h2><?= (int)$r['active'] ? '<span class="badge green">ouvert</span>' : '<span class="badge grey">suspendu</span>' ?></div>
      <div class="rs-copy"><input readonly value="<?= e(abs_url('/revendeur/' . $r['token'])) ?>" onclick="this.select()"><a class="btn btn-sm btn-light" target="_blank" href="<?= url('/revendeur/' . $r['token']) ?>">Ouvrir</a></div>
      <div class="row mt">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="reseller_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm" name="action" value="send">Envoyer le lien par e-mail</button></form>
        <form method="post" onsubmit="return confirm('L\'ancien lien ne marchera plus. Continuer ?')"><?= csrf_field() ?><input type="hidden" name="reseller_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="token">Nouveau lien</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="reseller_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="toggle"><?= (int)$r['active'] ? 'Suspendre' : 'Réactiver' ?></button></form>
        <form method="post" onsubmit="return confirm('Supprimer ce revendeur ?')"><?= csrf_field() ?><input type="hidden" name="reseller_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-light danger" name="action" value="delete">Supprimer</button></form>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($r['id'] && $detail): ?>
  <div>
    <div class="card">
      <div class="card-head"><h2>Organisateurs ouverts</h2></div>
      <?php foreach ($detail['access'] as $a): ?>
        <div class="list-item"><span class="grow"><b><?= e($a['org_name']) ?></b><br><small class="muted"><?= $a['commission_pct'] !== null ? 'Commission négociée : ' . e($pctTxt($a['commission_pct'])) : 'Commission par défaut' ?></small></span>
          <?php if ((int)$a['active']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="reseller_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="organizer_id" value="<?= (int)$a['organizer_id'] ?>"><button class="btn btn-sm btn-light" name="action" value="revoke">Fermer</button></form>
          <?php else: ?><span class="badge grey">fermé</span><?php endif; ?></div>
      <?php endforeach; ?>
      <?php if (!$detail['access']): ?><p class="muted">Aucun organisateur pour l'instant. Les organisateurs peuvent aussi l'activer eux-mêmes depuis leur menu « Revendeurs ».</p><?php endif; ?>
      <form method="post" class="row mt"><?= csrf_field() ?><input type="hidden" name="reseller_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="access">
        <select name="organizer_id" class="grow"><?php foreach ($orgs as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select>
        <div class="input-suffix" style="max-width:110px"><input name="pct" inputmode="decimal" placeholder="défaut"><span>%</span></div><button class="btn btn-sm">Ouvrir</button></form>
    </div>
    <div class="card table-card">
      <div class="card-head"><h2>Relevé</h2>
        <form class="row rs-month"><input type="hidden" name="r" value="<?= (int)$r['id'] ?>"><select name="mois" onchange="this.form.submit()"><?php foreach (Resellers::months() as $m): ?><option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= e(month_fr($m)) ?></option><?php endforeach; ?></select>
          <a class="btn btn-sm btn-light" href="<?= url('/admin/revendeurs', ['r' => $r['id'], 'mois' => $month, 'export' => 'csv']) ?>">CSV</a></form></div>
      <div class="table-wrap"><table class="table table-sm"><thead><tr><th>Organisateur</th><th class="num">Billets</th><th class="num">Vendu</th><th class="num">Commission</th><th class="num">À reverser</th></tr></thead><tbody>
        <?php foreach ($detail['statement'] as $s): ?><tr><td><?= e($s['org_name']) ?></td><td class="num"><?= (int)$s['tickets'] ?></td><td class="num"><?= e(money((int)$s['total'])) ?></td><td class="num"><?= e(money((int)$s['commission'])) ?></td><td class="num"><b><?= e(money((int)$s['owed'])) ?></b></td></tr><?php endforeach; ?>
        <?php if (!$detail['statement']): ?><tr><td colspan="5" class="empty">Aucune vente en <?= e(month_fr($month)) ?>.</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
    <?php if ($detail['orders']): ?><div class="card"><h2>Dernières ventes</h2>
      <?php foreach ($detail['orders'] as $o): ?><div class="list-item"><span class="grow"><a class="mono" href="<?= url('/admin/commandes/' . $o['id']) ?>"><?= e($o['ref']) ?></a> <?= badge($o['status']) ?><br><small class="muted"><?= e(date('d/m H:i', strtotime($o['created_at']))) ?> · <?= e((string)$o['event_name']) ?> · <?= (int)$o['n'] ?> billet(s)</small></span><b><?= e(money((int)$o['total'])) ?></b></div><?php endforeach; ?></div><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
