<form class="toolbar" method="get">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Référence, nom, e-mail…">
  <select name="ev"><option value="">Tous les événements</option><?php foreach ($events as $e2): ?><option value="<?= (int)$e2['id'] ?>" <?= $evId === (int)$e2['id'] ? 'selected' : '' ?>><?= e($e2['name']) ?></option><?php endforeach; ?></select>
  <select name="st"><option value="">Payées, annulées, remboursées</option><?php foreach (['paid' => 'Payées', 'pending' => 'En attente de paiement', 'refunded' => 'Remboursées', 'cancelled' => 'Annulées', 'expired' => 'Expirées'] as $k => $l): ?><option value="<?= $k ?>" <?= $st === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
  <button class="btn btn-sm">Filtrer</button>
  <span class="grow"></span>
  <a class="btn btn-sm btn-ghost" href="<?= url('/pro/commandes', ['q' => $q, 'ev' => $evId ?: null, 'st' => $st ?: null, 'csv' => 1]) ?>">⬇️ Export CSV</a>
</form>
<div class="card">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Commande</th><th>Date</th><th>Acheteur</th><th>Événement</th><th class="num">Billets</th><th class="num">Total</th><th>Vente</th><th>Statut</th></tr></thead>
    <tbody>
    <?php foreach ($list as $o): ?>
      <tr>
        <td><a href="<?= url('/pro/commandes/' . $o['id']) ?>"><b><?= e($o['ref']) ?></b></a></td>
        <td class="nowrap"><?= e(short_date($o['created_at'])) ?></td>
        <td><?= e($o['first_name'] . ' ' . $o['last_name']) ?><br><small><?= e($o['email']) ?></small></td>
        <td><?= e($o['ev_name']) ?></td>
        <td class="num"><?= (int)$o['qty'] ?></td>
        <td class="num"><?= e(money((int)$o['total'])) ?></td>
        <td><?= e(method_label($o['method'])) ?><?= $o['provider'] === 'demo' ? ' <small>(démo)</small>' : '' ?></td>
        <td><?= badge($o['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="8" class="empty">Pas encore de commande. Ça va venir 💪</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
