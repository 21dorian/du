<?php /** @var array $rows, $kinds, $ev; string $f, $base */
$L = ['pay.credit' => 'Crédit / correction', 'pay.block' => 'Blocage', 'pay.unblock' => 'Déblocage', 'pay.bracelet' => 'Bracelet', 'pay.transfer' => 'Transfert', 'pay.virtual' => 'Carte virtuelle', 'pay.bulk' => 'Crédit en masse',
    'pay.payout' => 'Reversement', 'pay.team' => 'Équipe', 'pay.restock' => 'Réassort', 'pos.device' => 'Terminal', 'pos.revoke' => 'Terminal déconnecté']; ?>
<div class="card"><form class="psearch"><select name="f" onchange="this.form.submit()"><option value="">Toutes les actions</option><?php foreach ($kinds as $kk): ?><option value="<?= e($kk) ?>" <?= $f === $kk ? 'selected' : '' ?>><?= e($L[$kk] ?? $kk) ?></option><?php endforeach; ?></select>
  <span class="pnote">Les opérations sensibles (soldes modifiés, blocages, reversements, accès) sont enregistrées avec leur auteur et ne peuvent pas être effacées.</span></form></div>
<div class="card table-card mt"><div class="table-wrap"><table class="table table-sm"><thead><tr><th>Date</th><th>Action</th><th>Détail</th><th>Par</th><th>IP</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td><?= e(date('d/m H:i', strtotime($r['created_at']))) ?></td><td><span class="ptag vio"><?= e($L[$r['action']] ?? $r['action']) ?></span></td><td><?= e((string)$r['details']) ?></td><td><?= e((string)($r['who'] ?: '—')) ?></td><td class="muted"><small><?= e((string)$r['ip']) ?></small></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="pempty">Aucune action enregistrée.</td></tr><?php endif; ?>
</tbody></table></div></div>
