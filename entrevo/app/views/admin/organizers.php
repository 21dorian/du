<nav class="pill-tabs" aria-label="Statut"><?php foreach (['' => 'Tous', 'pending' => 'À valider', 'active' => 'Actifs', 'suspended' => 'Suspendus'] as $k => $l): ?><a class="<?= $st === $k ? 'on' : '' ?>" href="<?= url('/admin/organisateurs', array_filter(['st' => $k ?: null, 'q' => $q ?: null])) ?>"><?= $l ?><?= $k === 'pending' && $pending ? ' <small>' . $pending . '</small>' : '' ?></a><?php endforeach; ?></nav>
<form class="toolbar" method="get"><?php if ($st): ?><input type="hidden" name="st" value="<?= e($st) ?>"><?php endif; ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, e-mail ou SIRET…"><button class="btn btn-sm">Chercher</button></form>
<div class="card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Organisateur</th><th>Inscrit le</th><th class="num">Événements</th><th class="num">Ventes</th><th>Commission</th><th class="num">À reverser</th><th>Statut</th></tr></thead>
  <tbody>
  <?php foreach ($list as $o): [$pct, $fixed] = Tickets::feeRates($o); ?>
    <tr>
      <td><a href="<?= url('/admin/organisateurs/' . $o['id']) ?>"><b><?= e($o['name']) ?></b></a><br><small><?= e($o['email']) ?></small></td>
      <td class="nowrap"><?= e(short_date($o['created_at'])) ?></td>
      <td class="num"><?= (int)$o['events'] ?></td>
      <td class="num"><?= e(money((int)$o['sales'])) ?></td>
      <td class="nowrap"><?= e(rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',')) ?> % + <?= e(money($fixed)) ?><?= $o['fee_percent'] !== null || $o['fee_fixed'] !== null ? ' <span class="badge violet">perso</span>' : '' ?><br><small>payée par <?= $o['fee_mode'] === 'buyer' ? 'l\'acheteur' : 'l\'orga' ?></small></td>
      <td class="num"><b><?= e(money((int)$o['balance'])) ?></b></td>
      <td><?= badge($o['status']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$list): ?><tr><td colspan="7" class="empty">Aucun organisateur.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
