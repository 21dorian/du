<form class="toolbar" method="get"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Référence, e-mail, nom, organisateur…"><button class="btn btn-sm">Chercher</button></form>
<div class="card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Commande</th><th>Date</th><th>Acheteur</th><th>Événement</th><th>Organisateur</th><th class="num">Total</th><th class="num">Commission</th><th>Paiement</th><th>Statut</th></tr></thead>
  <tbody>
  <?php foreach ($list as $o): ?>
    <tr><td class="mono"><b><?= e($o['ref']) ?></b></td><td class="nowrap"><?= e(short_date($o['created_at'])) ?></td><td><?= e($o['first_name'] . ' ' . $o['last_name']) ?><br><small><?= e($o['email']) ?></small></td>
    <td><?= e($o['ev_name']) ?></td><td><a href="<?= url('/admin/organisateurs/' . $o['organizer_id']) ?>"><?= e($o['org_name']) ?></a></td>
    <td class="num"><?= e(money((int)$o['total'])) ?></td><td class="num"><?= e(money((int)$o['commission'])) ?></td><td><?= e(provider_label($o['provider'] ?: $o['pay_method'])) ?: e(method_label($o['method'])) ?></td><td><?= badge($o['status']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$list): ?><tr><td colspan="9" class="empty">Aucune commande.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
