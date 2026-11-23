<?php $lab = ['waiting' => ['En attente', 'grey'], 'offered' => ['Place proposée', 'orange'], 'converted' => ['A acheté', 'green'], 'expired' => ['Offre expirée', 'red'], 'cancelled' => ['Retiré', 'red']]; ?>
<div class="split">
  <div class="card"><div class="card-head"><h2>⏳ Liste d'attente</h2><form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" name="action" value="process">Proposer les places libres maintenant</button></form></div>
    <p class="muted" style="font-size:14px">Dès qu'une place se libère (annulation, remboursement, jauge augmentée), la première personne reçoit un e-mail et sa place est gardée <?= Waitlist::HOLD_HOURS ?> h. Puis on passe à la suivante.</p>
    <div class="table-wrap"><table class="table"><thead><tr><th>#</th><th>Personne</th><th class="num">Places</th><th>Statut</th><th></th></tr></thead><tbody>
    <?php foreach ($list as $i => $w): [$l, $c] = $lab[$w['status']]; ?><tr><td><?= $i + 1 ?></td><td><?= e(trim($w['first_name'] . ' ' . $w['last_name'])) ?: '—' ?><br><small><?= e($w['email']) ?><?= $w['s_start'] ? ' · ' . e(short_date($w['s_start'])) : '' ?></small></td>
      <td class="num"><?= (int)$w['qty'] ?></td><td><span class="badge <?= $c ?>"><?= $l ?></span><?= $w['status'] === 'offered' ? '<br><small>jusqu\'au ' . e(short_date($w['expires_at'])) . '</small>' : '' ?></td>
      <td class="right"><?php if (in_array($w['status'], ['waiting', 'offered'], true)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="waitlist_id" value="<?= (int)$w['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="cancel">Retirer</button></form><?php endif; ?></td></tr><?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="5" class="empty">Personne en attente. <?= $ev['waitlist_enabled'] ? '' : '(Liste d\'attente désactivée dans les réglages.)' ?></td></tr><?php endif; ?>
    </tbody></table></div></div>
  <div class="card"><h2>🔁 Bourse aux billets</h2>
    <?php if (!$ev['resale_enabled']): ?><p class="notice">Désactivée. Active-la dans « Infos & réglages » : les acheteurs qui ne peuvent plus venir revendent leur billet au prix payé, tu ne perds rien et tu évites le marché noir.</p><?php endif; ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Billet</th><th class="num">Prix</th><th>Statut</th><th>Remboursement vendeur</th></tr></thead><tbody>
    <?php foreach ($resales as $x): ?><tr><td><?= e($x['rate_name']) ?><br><small>vendeur <?= e($x['seller_ref']) ?><?= $x['buyer_ref'] ? ' → ' . e($x['buyer_ref']) : '' ?></small></td><td class="num"><?= e(money((int)$x['price'])) ?><br><small>frais <?= e(money((int)$x['fee'])) ?></small></td>
      <td><?= ['listed' => '<span class="badge orange">En vente</span>', 'sold' => '<span class="badge green">Revendu</span>', 'cancelled' => '<span class="badge grey">Annulé</span>'][$x['status']] ?></td>
      <td><?= $x['refund_status'] === 'ok' ? '✅' : ($x['refund_status'] === 'failed' ? '<span style="color:var(--red)">⚠️ ' . e($x['refund_info']) . '</span>' : '—') ?></td></tr><?php endforeach; ?>
    <?php if (!$resales): ?><tr><td colspan="4" class="empty">Aucune revente.</td></tr><?php endif; ?>
    </tbody></table></div></div>
</div>
