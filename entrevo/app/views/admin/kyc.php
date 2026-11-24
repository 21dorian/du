<nav class="pill-tabs"><?php foreach (['pending' => '🔎 À vérifier', 'rejected' => 'À corriger', 'incomplete' => 'Incomplets', 'verified' => 'Vérifiés', 'none' => 'Rien déposé', 'all' => 'Tous'] as $k => $l): ?><a class="<?= $st === $k ? 'on' : '' ?>" href="<?= url('/admin/kyc', ['st' => $k]) ?>"><?= $l ?><?= $k !== 'all' && !empty($counts[$k]) ? ' <small>' . (int)$counts[$k] . '</small>' : '' ?></a><?php endforeach; ?></nav>
<p class="muted">Option « reversements bloqués tant que le KYC n'est pas vérifié » : <b><?= Kyc::required() ? 'activée' : 'désactivée' ?></b> (Réglages › Commissions).</p>
<div class="card table-card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Organisateur</th><th>Statut</th><th class="num">Documents</th><th>Dernier dépôt</th><th class="num">Solde dû</th></tr></thead><tbody>
  <?php foreach ($list as $o): ?><tr data-href="<?= url('/admin/kyc/' . $o['id']) ?>">
    <td><a href="<?= url('/admin/kyc/' . $o['id']) ?>"><b><?= e($o['name']) ?></b></a><br><small class="muted"><?= e($o['legal_name'] ?: $o['email']) ?><?= $o['siret'] ? ' · SIRET ' . e($o['siret']) : '' ?></small></td>
    <td><?= Kyc::badge($o['kyc_status']) ?></td><td class="num"><?= (int)$o['docs'] ?></td>
    <td><small><?= $o['last_upload'] ? e(ago($o['last_upload'])) : '—' ?></small></td>
    <td class="num"><?= e(money(Tickets::balance((int)$o['id'])['balance'])) ?></td></tr><?php endforeach; ?>
  <?php if (!$list): ?><tr><td colspan="5" class="empty">Personne dans cette liste 👌</td></tr><?php endif; ?>
</tbody></table></div></div>
