<?php /** @var array $list, $statement, $orders; string $month */
$pctTxt = fn($v) => rtrim(rtrim(number_format((float)$v, 2, ',', ''), '0'), ',') . ' %'; ?>
<p class="muted lead-sm">Fais vendre tes billets au comptoir de partenaires de la plateforme (offices de tourisme, CSE, commerces). Ils encaissent l'acheteur, qui reçoit ses billets par e-mail comme d'habitude. Chaque mois, ils te reversent le montant vendu moins leur commission : le relevé est ci-dessous.</p>
<div class="card table-card">
  <div class="card-head"><h2>Revendeurs disponibles</h2></div>
  <div class="table-wrap u-ovis"><table class="table">
    <thead><tr><th>Revendeur</th><th class="num">Commission</th><th class="num">Vendu pour toi</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $r): $on = (int)$r['on_'] === 1; ?>
      <tr><td><b><?= e($r['name']) ?></b> <?= $on ? '<span class="badge green">autorisé</span>' : '' ?><br><small class="muted"><?= e(trim(($r['city'] ?? '') . ($r['kind'] ? ' · ' . (Resellers::KINDS[$r['kind']] ?? '') : ''), ' ·')) ?></small></td>
        <td class="num"><?= e($pctTxt($on && $r['my_pct'] !== null ? $r['my_pct'] : $r['commission_pct'])) ?></td>
        <td class="num"><?= e(money((int)$r['sold'])) ?><?= (int)$r['comm'] ? '<br><small class="muted">' . e(money((int)$r['comm'])) . ' de commission</small>' : '' ?></td>
        <td class="num"><?php if ($on): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="reseller_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="off">Retirer</button></form>
          <?php else: ?><details class="row-menu"><summary class="btn btn-sm">Autoriser</summary><div class="row-menu-pop"><form method="post" class="perm-form"><?= csrf_field() ?><input type="hidden" name="reseller_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="on">
            <b>Autoriser <?= e($r['name']) ?></b><small class="muted">Il pourra vendre les tarifs publics de tes événements en ligne. Laisse vide pour garder sa commission habituelle (<?= e($pctTxt($r['commission_pct'])) ?>).</small>
            <div class="input-suffix"><input name="pct" inputmode="decimal" placeholder="<?= e(str_replace('.', ',', (string)(float)$r['commission_pct'])) ?>"><span>%</span></div><button class="btn btn-sm">Autoriser</button></form></div></details><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="4" class="empty">Aucun revendeur sur la plateforme pour l'instant.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<div class="split mt">
  <div class="card table-card">
    <div class="card-head"><h2>Relevé du mois</h2><form class="row rs-month"><select name="mois" onchange="this.form.submit()"><?php foreach (Resellers::months() as $m): ?><option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= e(month_fr($m)) ?></option><?php endforeach; ?></select></form></div>
    <div class="table-wrap"><table class="table table-sm"><thead><tr><th>Revendeur</th><th class="num">Billets</th><th class="num">Vendu</th><th class="num">Commission</th><th class="num">Il te doit</th></tr></thead><tbody>
      <?php foreach ($statement as $s): ?><tr><td><?= e($s['reseller']) ?></td><td class="num"><?= (int)$s['tickets'] ?></td><td class="num"><?= e(money((int)$s['total'])) ?></td><td class="num"><?= e(money((int)$s['commission'])) ?></td><td class="num"><b><?= e(money((int)$s['owed'])) ?></b></td></tr><?php endforeach; ?>
      <?php if (!$statement): ?><tr><td colspan="5" class="empty">Aucune vente en <?= e(month_fr($month)) ?>.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
  <div class="card"><h2>Dernières ventes</h2>
    <?php foreach ($orders as $o): ?><div class="list-item"><span class="grow"><a class="mono" href="<?= url('/pro/commandes/' . $o['id']) ?>"><?= e($o['ref']) ?></a> <?= badge($o['status']) ?><br><small class="muted"><?= e(date('d/m H:i', strtotime($o['created_at']))) ?> · <?= e($o['reseller']) ?> · <?= e((string)$o['event_name']) ?> · <?= (int)$o['n'] ?> billet(s)</small></span><b><?= e(money((int)$o['total'])) ?></b></div><?php endforeach; ?>
    <?php if (!$orders): ?><p class="muted mb0">Pas encore de vente par un revendeur.</p><?php endif; ?>
  </div>
</div>
