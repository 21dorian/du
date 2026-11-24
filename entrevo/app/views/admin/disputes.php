<?php /** @var array $list, $k, $byOrg; string $f; float $rate */ ?>
<p class="muted lead-sm">Quand un acheteur conteste un paiement auprès de sa banque. Les litiges Stripe arrivent tout seuls ; les autres se saisissent ici. Perdu ou accepté : le montant et les frais bancaires sont retenus sur le reversement de l'organisateur.</p>
<div class="page-head-row">
  <div class="kpi-mini"><span>À traiter</span><b><?= (int)$k['open'] ?></b><small class="muted"><?= e(money((int)$k['at_stake'])) ?> en jeu</small></div>
  <div class="kpi-mini<?= (int)$k['urgent'] ? ' kpi-alert' : '' ?>"><span>Échéance sous 7 jours</span><b><?= (int)$k['urgent'] ?></b></div>
  <div class="kpi-mini"><span>Gagnés / perdus</span><b><?= (int)$k['won'] ?> / <?= (int)$k['lost'] ?></b><small class="muted"><?= e(money((int)$k['lost_amount'])) ?> retenus</small></div>
  <div class="kpi-mini"><span>Taux de litige (12 mois)</span><b><?= e(number_format($rate, 2, ',', ' ')) ?> %</b><small class="muted"><?= $rate >= 0.75 ? 'au-dessus du seuil des banques' : 'sous le seuil de 0,75 %' ?></small></div>
</div>
<div class="split-3">
  <div>
    <nav class="pills mb"><?php foreach (['ouverts' => 'En cours', 'clos' => 'Clos', 'tous' => 'Tous'] as $kk => $l): ?><a class="<?= $f === $kk ? 'on' : '' ?>" href="<?= url('/admin/litiges', ['f' => $kk]) ?>"><?= $l ?></a><?php endforeach; ?></nav>
    <div class="card table-card"><div class="table-wrap"><table class="table">
      <thead><tr><th>Commande</th><th>Organisateur</th><th>Motif</th><th class="num">Montant</th><th>Échéance</th><th>Statut</th></tr></thead><tbody>
      <?php foreach ($list as $d): $late = $d['due_on'] && $d['status'] === 'open' && strtotime($d['due_on']) < time() + 7 * 86400; ?>
        <tr data-href="<?= url('/admin/litiges/' . $d['id']) ?>"><td><a href="<?= url('/admin/litiges/' . $d['id']) ?>"><b class="mono"><?= e((string)$d['ref']) ?></b></a><br><small class="muted"><?= e((string)$d['buyer']) ?></small></td>
          <td><?= e($d['org_name']) ?><br><small class="muted"><?= e((string)$d['ev_name']) ?></small></td>
          <td><small><?= e(Disputes::REASONS[$d['reason']] ?? $d['reason']) ?></small></td>
          <td class="num"><b><?= e(money((int)$d['amount'])) ?></b><?= (int)$d['bank_fee'] ? '<br><small class="muted">+ ' . e(money((int)$d['bank_fee'])) . ' frais</small>' : '' ?></td>
          <td class="nowrap"><?= $d['due_on'] ? '<span class="' . ($late ? 'text-red' : '') . '">' . e(date('d/m/Y', strtotime($d['due_on']))) . '</span>' : '<span class="muted">—</span>' ?></td>
          <td><?= Disputes::badge($d['status']) ?><?= $d['provider_ref'] ? '<br><small class="muted">' . e($d['provider']) . '</small>' : '' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="6" class="empty">Aucun litige ici. Tant mieux.</td></tr><?php endif; ?>
      </tbody></table></div></div>
  </div>
  <div class="stack">
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create">
      <h2>Saisir un litige</h2>
      <div class="field" id="saisie"><label>Référence de la commande</label><input name="order_ref" required placeholder="AB12CD34" class="mono" value="<?= e((string)input('ref')) ?>"></div>
      <div class="grid2">
        <div class="field"><label>Montant contesté</label><div class="input-suffix"><input name="amount" inputmode="decimal" placeholder="total"><span>€</span></div></div>
        <div class="field"><label>Frais bancaires</label><div class="input-suffix"><input name="bank_fee" inputmode="decimal" value="<?= e(money_input((int)setting('dispute_fee', '1500'))) ?>"><span>€</span></div></div>
      </div>
      <div class="field"><label>Motif</label><select name="reason"><?php foreach (Disputes::REASONS as $kk => $l): ?><option value="<?= $kk ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="grid2">
        <div class="field"><label>Répondre avant le</label><input type="date" name="due_on"></div>
        <div class="field"><label>Réf. prestataire</label><input name="provider_ref" placeholder="facultatif"></div>
      </div>
      <button class="btn btn-block">Ouvrir le litige</button>
    </form>
    <?php if ($byOrg): ?><div class="card"><h2>Organisateurs concernés</h2><p class="muted" style="font-size:13px">12 derniers mois</p>
      <?php foreach ($byOrg as $b): ?><div class="list-item"><a class="grow" href="<?= url('/admin/organisateurs/' . $b['id']) ?>"><?= e($b['name']) ?></a><span><?= (int)$b['n'] ?> · <?= e(money((int)$b['amount'])) ?></span></div><?php endforeach; ?></div><?php endif; ?>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <h2>Frais par défaut</h2><div class="field"><label>Frais bancaires par litige</label><div class="input-suffix"><input name="dispute_fee" inputmode="decimal" value="<?= e(money_input((int)setting('dispute_fee', '1500'))) ?>"><span>€</span></div><small>Stripe facture 15 € à 20 € par contestation.</small></div>
      <button class="btn btn-light btn-block">Enregistrer</button></form>
  </div>
</div>
