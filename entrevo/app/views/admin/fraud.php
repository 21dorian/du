<?php /** @var array $queue, $recent, $blocks, $k, $topIps */ ?>
<p class="muted lead-sm">Chaque commande en ligne reçoit un score de risque, à partir de signaux simples et explicables (e-mail jetable, rafale depuis une même adresse IP, litiges passés, gros montant d'un nouvel acheteur…). Au-dessus du seuil, elle arrive ici : à toi de la valider ou de l'annuler avant l'événement.</p>
<div class="page-head-row">
  <div class="kpi-mini<?= (int)$k['review'] ? ' kpi-alert' : '' ?>"><span>À vérifier</span><b><?= (int)$k['review'] ?></b><small class="muted"><?= e(money((int)$k['at_stake'])) ?> payés</small></div>
  <div class="kpi-mini"><span>Signalées (30 j)</span><b><?= (int)$k['flagged30'] ?></b><small class="muted">score de 25 ou plus</small></div>
  <div class="kpi-mini"><span>Acheteurs bloqués</span><b><?= (int)$k['blocked'] ?></b></div>
  <div class="kpi-mini"><span>Liste de blocage</span><b><?= count($blocks) ?></b></div>
</div>
<div class="card table-card">
  <div class="card-head"><h2>À vérifier</h2></div>
  <div class="table-wrap u-ovis"><table class="table users-table">
    <thead><tr><th>Score</th><th>Commande</th><th>Pourquoi</th><th class="num">Montant</th><th></th></tr></thead><tbody>
    <?php foreach ($queue as $o): ?>
      <tr><td><?= Risk::badge((int)$o['risk_score']) ?></td>
        <td><a class="mono" href="<?= url('/admin/commandes/' . $o['id']) ?>"><b><?= e($o['ref']) ?></b></a> <?= badge($o['status']) ?><br><small class="muted"><?= e($o['email']) ?> · <?= e((string)($o['ev_name'] ?: 'Boutique')) ?> · <?= e($o['org_name']) ?></small></td>
        <td><small><?= e((string)$o['risk_flags']) ?></small><?= $o['ip'] ? '<br><small class="muted">IP ' . e($o['ip']) . '</small>' : '' ?></td>
        <td class="num"><?= e(money((int)$o['total'])) ?></td>
        <td><details class="row-menu"><summary class="btn btn-sm btn-light">Décider</summary><div class="row-menu-pop">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><button name="action" value="ok">C'est bon, valider</button></form>
          <form method="post" class="perm-form"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><b>Bloquer l'acheteur</b><small class="muted">Plus aucune commande avec cet e-mail. Tu pourras ensuite rembourser.</small>
            <?php if ($o['ip']): ?><label class="check sub"><input type="checkbox" name="with_ip" value="1"> <span>et l'adresse IP (30 jours)</span></label><?php endif; ?>
            <button class="btn btn-sm danger" name="action" value="block_order">Bloquer</button></form>
        </div></details></td></tr>
    <?php endforeach; ?>
    <?php if (!$queue): ?><tr><td colspan="5" class="empty">Rien à vérifier.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<div class="split mt">
  <div>
    <div class="card table-card"><div class="card-head"><h2>Liste de blocage</h2></div><div class="table-wrap"><table class="table table-sm"><tbody>
      <?php foreach ($blocks as $b): ?><tr><td><span class="badge grey"><?= e(Risk::KINDS[$b['kind']] ?? $b['kind']) ?></span></td><td class="mono"><?= e($b['value']) ?><br><small class="muted"><?= e((string)$b['reason']) ?><?= $b['expires_at'] ? ' · jusqu\'au ' . e(date('d/m/Y', strtotime($b['expires_at']))) : '' ?></small></td>
        <td class="right"><form method="post"><?= csrf_field() ?><input type="hidden" name="block_id" value="<?= (int)$b['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="unblock">Retirer</button></form></td></tr><?php endforeach; ?>
      <?php if (!$blocks): ?><tr><td class="empty">Personne n'est bloqué.</td></tr><?php endif; ?>
    </tbody></table></div>
    <form method="post" class="charge-form block-form"><?= csrf_field() ?><input type="hidden" name="action" value="block">
      <select name="kind"><?php foreach (Risk::KINDS as $kk => $l): ?><option value="<?= $kk ?>"><?= $l ?></option><?php endforeach; ?></select>
      <input name="value" placeholder="adresse, domaine ou IP" required><input name="reason" placeholder="Motif"><button class="btn btn-sm">Bloquer</button></form></div>
    <?php if ($topIps): ?><div class="card"><h2>Adresses IP partagées (7 jours)</h2><p class="muted u-fs13">Plusieurs acheteurs depuis la même adresse : normal pour un bureau ou un festival en wifi, suspect sinon.</p>
      <?php foreach ($topIps as $t): ?><div class="list-item"><span class="grow mono"><?= e($t['ip']) ?></span><small class="muted"><?= (int)$t['n'] ?> commandes · <?= (int)$t['emails'] ?> e-mails · <?= (int)$t['paid'] ?> payées</small></div><?php endforeach; ?></div><?php endif; ?>
  </div>
  <div>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <h2>Règles</h2>
      <div class="grid2">
        <div class="field"><label>Seuil « à vérifier »</label><div class="input-suffix"><input type="number" name="risk_threshold" min="10" max="100" value="<?= (int)setting('risk_threshold', '50') ?>"><span>/ 100</span></div></div>
        <div class="field"><label>Commandes par IP et par heure</label><input type="number" name="risk_ip_hour" min="2" max="100" value="<?= (int)setting('risk_ip_hour', '6') ?>"></div>
        <div class="field"><label>Montant élevé à partir de</label><div class="input-suffix"><input name="risk_amount" inputmode="decimal" value="<?= e(money_input((int)setting('risk_amount', '50000'))) ?>"><span>€</span></div></div>
      </div>
      <label class="check"><input type="checkbox" name="risk_block_disposable" value="1" <?= setting('risk_block_disposable') === '1' ? 'checked' : '' ?>> <span>Refuser les adresses e-mail jetables (yopmail, mailinator…)</span></label>
      <button class="btn btn-block mt">Enregistrer</button>
    </form>
    <?php if ($recent): ?><div class="card"><h2>Signalées récemment</h2><?php foreach ($recent as $r): ?><div class="list-item"><span class="grow"><a class="mono" href="<?= url('/admin/commandes/' . $r['id']) ?>"><?= e($r['ref']) ?></a> <small class="muted"><?= e((string)$r['risk_flags']) ?></small></span><?= Risk::badge((int)$r['risk_score']) ?></div><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
