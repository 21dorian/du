<?php /** @var array $o, $org, $items, $tickets, $log, $disputes, $tickets_support, $other; ?array $ev */
$left = (int)$o['total'] - (int)$o['refunded_amount'];
$gesture = min((int)$o['commission'], $left); ?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Statut</span><b><?= badge($o['status']) ?></b></div>
  <div class="kpi-mini"><span>Total payé</span><b><?= e(money((int)$o['total'])) ?></b><?= (int)$o['refunded_amount'] ? '<small class="muted">dont ' . e(money((int)$o['refunded_amount'])) . ' remboursés</small>' : '' ?></div>
  <div class="kpi-mini"><span>Commission</span><b><?= e(money((int)$o['commission'])) ?></b></div>
  <div class="kpi-mini"><span>Part organisateur</span><b><?= e(money((int)$o['organizer_amount'])) ?></b></div>
</div>
<?php if ($o['refund_requested_at'] && $o['status'] === 'paid'): ?><div class="notice info mb"><b>Remboursement demandé</b> le <?= e(fr_date($o['refund_requested_at'])) ?><?= $o['refund_reason'] ? ' : « ' . e($o['refund_reason']) . ' »' : '' ?></div><?php endif; ?>
<?php foreach ($disputes as $d): ?><div class="notice warn mb">Litige <?= Disputes::badge($d['status']) ?> de <?= e(money((int)$d['amount'])) ?> · <a href="<?= url('/admin/litiges/' . $d['id']) ?>">ouvrir</a></div><?php endforeach; ?>
<div class="split-3">
  <div>
    <div class="card"><h2>Détail</h2>
      <div class="table-wrap"><table class="table"><tbody>
        <?php foreach ($items as $i): ?><tr><td><?= (int)$i['qty'] ?> × <?= e($i['name']) ?></td><td class="num"><?= e(money((int)$i['unit_price'] * (int)$i['qty'])) ?></td></tr><?php endforeach; ?>
        <?php if ((int)$o['discount']): ?><tr><td>Réduction<?= $o['promo_code'] ? ' (' . e($o['promo_code']) . ')' : '' ?></td><td class="num">−<?= e(money((int)$o['discount'])) ?></td></tr><?php endif; ?>
        <?php if ((int)$o['gift_amount']): ?><tr><td>Carte cadeau <?= e((string)$o['gift_code']) ?></td><td class="num">−<?= e(money((int)$o['gift_amount'])) ?></td></tr><?php endif; ?>
        <?php if ((int)$o['fees']): ?><tr><td>Frais de service (<?= $o['fee_mode'] === 'buyer' ? 'payés par l\'acheteur' : 'à la charge de l\'organisateur' ?>)</td><td class="num"><?= e(money((int)$o['fees'])) ?></td></tr><?php endif; ?>
        <tr><td><b>Total</b></td><td class="num"><b><?= e(money((int)$o['total'])) ?></b></td></tr>
      </tbody></table></div>
      <p class="muted mt mb0" style="font-size:13px"><?= e(provider_label((string)$o['provider']) ?: method_label((string)$o['method'])) ?><?= $o['provider_ref'] ? ' · réf. ' . e($o['provider_ref']) : '' ?><?= $o['provider_payment'] ? ' · paiement ' . e($o['provider_payment']) : '' ?>
        · créée le <?= e(fr_date($o['created_at'])) ?><?= $o['paid_at'] ? ' · payée le ' . e(fr_date($o['paid_at'])) : '' ?><?= $o['channel'] ? ' · canal ' . e($o['channel']) : '' ?><?= $o['utm_source'] ? ' · source ' . e($o['utm_source']) : '' ?></p>
    </div>
    <?php if ($tickets): ?><div class="card table-card"><div class="card-head"><h2>Billets</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>Code</th><th>Tarif</th><th>Nom</th><th>État</th></tr></thead><tbody>
      <?php foreach ($tickets as $t): ?><tr><td class="mono"><?= e($t['code']) ?></td><td><?= e($t['rate_name']) ?></td><td><?= e(trim($t['first_name'] . ' ' . $t['last_name'])) ?><?= $t['holder_email'] ? '<br><small class="muted">transmis à ' . e($t['holder_email']) . '</small>' : '' ?></td>
        <td><?= $t['status'] !== 'valid' ? '<span class="badge grey">' . e($t['status'] === 'cancelled' ? 'Annulé' : $t['status']) . '</span>' : ($t['checked_in_at'] ? '<span class="badge green">Entré ' . e(date('d/m H:i', strtotime($t['checked_in_at']))) . '</span>' : '<span class="badge blue">Valide</span>') ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div><?php endif; ?>
    <div class="card"><h2>Historique</h2>
      <?php foreach ($log as $l): ?><div class="list-item"><span class="grow"><b><?= e(Audit::label($l['action'])) ?></b> <?= e((string)$l['details']) ?><br><small class="muted"><?= e(fr_date($l['created_at'])) ?><?= $l['user_name'] ? ' · ' . e($l['user_name']) : '' ?></small></span></div><?php endforeach; ?>
      <?php if (!$log): ?><p class="muted mb0">Aucune action enregistrée sur cette commande.</p><?php endif; ?>
    </div>
  </div>
  <div class="stack">
    <div class="card"><h2>Acheteur</h2>
      <p class="mb0"><b><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?></b><br><small class="muted"><?= e($o['email']) ?><?= $o['phone'] ? ' · ' . e($o['phone']) : '' ?></small></p>
      <p class="mt mb0"><small class="muted">Organisateur</small><br><a href="<?= url('/admin/organisateurs/' . $org['id']) ?>"><?= e($org['name']) ?></a><?= $ev ? '<br><small class="muted">' . e($ev['name']) . ' · ' . e(short_date($ev['starts_at'])) . '</small>' : '' ?></p>
      <p class="mt mb0"><a href="<?= url('/commande/' . $o['ref'], ['t' => $o['token']]) ?>" target="_blank">Page de commande de l'acheteur →</a></p>
    </div>
    <?php if ($o['status'] === 'paid'): ?>
    <div class="card"><h2>Actions</h2>
      <form method="post" class="mb"><?= csrf_field() ?><input type="hidden" name="action" value="resend">
        <div class="field"><label>Renvoyer les billets à</label><input type="email" name="email" value="<?= e($o['email']) ?>"></div><button class="btn btn-light btn-block">Renvoyer</button></form>
      <form method="post" class="mb"><?= csrf_field() ?><input type="hidden" name="action" value="partial_refund">
        <div class="field"><label>Remboursement partiel <small class="muted">(pris sur l'organisateur)</small></label><div class="input-suffix"><input name="amount" inputmode="decimal" placeholder="max <?= e(money_input($left)) ?>"><span>€</span></div></div>
        <button class="btn btn-light btn-block" data-confirm="Rembourser ce montant à l'acheteur ?">Rembourser ce montant</button></form>
      <form method="post" class="mb"><?= csrf_field() ?>
        <div class="row"><button class="btn btn-light grow" name="action" value="refund" data-confirm="Rembourser toute la commande et invalider les billets ?">Tout rembourser</button><button class="btn btn-ghost" name="action" value="cancel" data-confirm="Annuler sans rembourser ?">Annuler</button></div></form>
      <?php if ($gesture > 0): ?><form method="post" class="gesture"><?= csrf_field() ?><input type="hidden" name="action" value="platform_refund">
        <p class="mb0"><b>Geste commercial plateforme</b></p><p class="muted" style="font-size:13px">Remboursé sur ta commission (max <?= e(money($gesture)) ?>), sans toucher à la part de l'organisateur.</p>
        <div class="grid2"><div class="input-suffix"><input name="amount" inputmode="decimal" placeholder="<?= e(money_input($gesture)) ?>"><span>€</span></div><input name="reason" placeholder="Motif"></div>
        <button class="btn btn-light btn-block mt" data-confirm="Rembourser ce geste sur la commission ?">Faire le geste</button></form><?php endif; ?>
      <p class="mt mb0"><a href="<?= url('/admin/litiges', ['ref' => $o['ref']]) ?>#saisie">Ouvrir un litige sur cette commande →</a></p>
    </div>
    <?php endif; ?>
    <?php if ($tickets_support): ?><div class="card"><h2>Support</h2><?php foreach ($tickets_support as $s): ?><div class="list-item"><a class="grow" href="<?= url('/admin/support/' . $s['id']) ?>"><?= e($s['subject']) ?></a><?= Support::badge($s['status']) ?></div><?php endforeach; ?></div><?php endif; ?>
    <?php if ($other): ?><div class="card"><h2>Autres commandes</h2><?php foreach ($other as $x): ?><div class="list-item"><span class="grow"><a class="mono" href="<?= url('/admin/commandes/' . $x['id']) ?>"><?= e($x['ref']) ?></a><br><small class="muted"><?= e((string)($x['ev_name'] ?: 'Boutique')) ?> · <?= e(short_date($x['created_at'])) ?></small></span><span><?= e(money((int)$x['total'])) ?></span></div><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
