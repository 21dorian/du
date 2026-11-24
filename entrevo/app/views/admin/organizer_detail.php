<?php if ($org['status'] === 'pending'): ?>
<div class="flash warning approve-box"><b>En attente de validation</b> — inscrit le <?= e(fr_date($org['created_at'])) ?>. Vérifie ses infos (SIRET, site, description) avant de l'autoriser à publier.
  <form method="post" class="inline-form" style="margin-left:auto"><?= csrf_field() ?><button class="btn btn-sm btn-success" name="action" value="approve">Valider</button> <button class="btn btn-sm btn-light" name="action" value="status" data-confirm="Refuser et suspendre ce compte ?">Refuser</button></form>
</div>
<?php endif; ?>
<?php $pctTxt = fn($v) => rtrim(rtrim(number_format((float)$v, 2, ',', ''), '0'), ','); ?>
<div class="org-hero card">
  <div class="org-hero-id"><?php if ($org['logo']): ?><img src="<?= url($org['logo']) ?>" alt=""><?php else: ?><span><?= e(mb_strtoupper(mb_substr($org['name'], 0, 1))) ?></span><?php endif; ?>
    <div><div class="row" style="gap:8px"><b class="org-hero-name"><?= e($org['name']) ?></b><?= badge($org['status']) ?><span class="badge grey">Formule <?= e($terms['plan']['name'] ?? 'plateforme') ?></span><a class="badge <?= ($org['kyc_status'] ?? '') === 'verified' ? 'green' : 'orange' ?>" href="<?= url('/admin/kyc/' . $org['id']) ?>">KYC : <?= e(Kyc::STATUS[$org['kyc_status'] ?? 'none'][0]) ?></a></div>
      <small class="muted"><?= e((string)$org['email']) ?><?= $org['phone'] ? ' · ' . e($org['phone']) : '' ?> · inscrit le <?= e(date('d/m/Y', strtotime($org['created_at']))) ?> · <a href="<?= url('/o/' . $org['slug']) ?>" target="_blank">page publique</a></small></div></div>
  <div class="row"><a class="btn btn-sm btn-light" href="<?= url('/admin/commandes', ['org' => $org['id']]) ?>">Ses commandes</a><a class="btn btn-sm btn-light" href="<?= url('/admin/contrats', ['org' => $org['id']]) ?>#editeur">Nouveau contrat</a></div>
</div>
<div class="page-head-row">
  <div class="kpi-mini"><span>Ventes 12 mois</span><b><?= e(money((int)$k['gmv12'])) ?></b><small class="muted"><?= e(money((int)$k['gmv'])) ?> depuis le début</small></div>
  <div class="kpi-mini"><span>Commission 12 mois</span><b><?= e(money((int)$k['com12'])) ?></b><small class="muted"><?= e($pctTxt($terms['pct'])) ?> % + <?= e(money($terms['fixed'])) ?> (<?= e($terms['source']) ?>)</small></div>
  <div class="kpi-mini"><span>Billets / acheteurs</span><b><?= number_format((int)$k['tickets'], 0, ',', ' ') ?></b><small class="muted"><?= (int)$k['buyers'] ?> acheteurs · <?= (int)$k['orders'] ?> commandes</small></div>
  <div class="kpi-mini"><span>Reste à reverser</span><b><?= e(money($balance['balance'])) ?></b><small class="muted"><?= (int)$balance['charges'] ? e(money((int)$balance['charges'])) . ' de retenues' : 'aucune retenue' ?></small></div>
  <div class="kpi-mini<?= $k['disputes'] ? ' kpi-alert' : '' ?>"><span>Litiges / support</span><b><?= (int)$k['disputes'] ?> / <?= (int)$k['support'] ?></b><small class="muted">en cours</small></div>
</div>
<nav class="ev-nav"><div class="ev-nav-groups"><?php foreach (['apercu' => 'Aperçu', 'argent' => 'Argent', 'conditions' => 'Conditions', 'equipe' => 'Équipe & accès', 'activite' => 'Activité'] as $tk => $tl): ?><a class="<?= $tab === $tk ? 'on' : '' ?>" href="<?= url('/admin/organisateurs/' . $org['id'], ['t' => $tk]) ?>"><?= $tl ?></a><?php endforeach; ?></div></nav>

<?php if ($tab === 'apercu'): ?>
<div class="card"><div class="card-head"><h2>Ventes et commission</h2><small class="muted">12 derniers mois</small></div>
  <?= Viz::chart('columns', ['labels' => array_map(fn($m) => mb_substr(month_fr($m), 0, 4) . '. ' . substr($m, 2, 2), array_keys($months)), 'xlabel' => 'Mois', 'series' => [['name' => 'Ventes', 'values' => array_column($months, 0)], ['name' => 'Commission', 'values' => array_column($months, 1)]]], 'Ventes mensuelles', 'money', 220) ?></div>
<div class="split mt">
  <div class="card table-card"><div class="card-head"><h2>Événements <small class="muted">(<?= count($events) ?>)</small></h2></div><div class="table-wrap"><table class="table"><thead><tr><th>Événement</th><th>Statut</th><th class="num">Billets</th><th class="num">Ventes</th></tr></thead><tbody>
    <?php foreach ($events as $e2): ?><tr><td><b><?= e($e2['name']) ?></b><br><small class="muted"><?= e(short_date($e2['starts_at'])) ?></small></td><td><?= badge($e2['status']) ?></td><td class="num"><?= (int)$e2['sold'] ?><?= (int)$e2['capacity'] ? '<small class="muted"> / ' . (int)$e2['capacity'] . '</small>' : '' ?></td><td class="num"><?= e(money((int)$e2['revenue'])) ?></td></tr><?php endforeach; ?>
    <?php if (!$events): ?><tr><td colspan="4" class="empty">Aucun événement.</td></tr><?php endif; ?></tbody></table></div></div>
  <div>
    <div class="card">
      <div class="card-head"><h2>Infos</h2><?= badge($org['status']) ?></div>
      <div class="summary-line"><span class="muted">Contact</span><span><?= e($org['email']) ?><?= $org['phone'] ? ' · ' . e($org['phone']) : '' ?></span></div>
      <div class="summary-line"><span class="muted">Raison sociale</span><span><?= e($org['legal_name'] ?: '—') ?></span></div>
      <div class="summary-line"><span class="muted">SIRET</span><span><?= e($org['siret'] ?: '—') ?></span></div>
      <div class="summary-line"><span class="muted">IBAN</span><span class="mono"><?= e($org['iban'] ?: '— pas encore renseigné') ?></span></div>
      <div class="summary-line"><span class="muted">BIC</span><span class="mono"><?= e($org['bic'] ?: '—') ?></span></div>
      <div class="summary-line"><span class="muted">Page publique</span><a href="<?= url('/o/' . $org['slug']) ?>" target="_blank">/o/<?= e($org['slug']) ?></a></div>
      <form method="post" class="mt"><?= csrf_field() ?><button class="btn btn-sm <?= $org['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>" name="action" value="status" data-confirm="<?= $org['status'] === 'active' ? 'Suspendre cet organisateur ? Ses événements disparaissent du site.' : 'Réactiver cet organisateur ?' ?>"><?= $org['status'] === 'active' ? 'Suspendre' : 'Réactiver' ?></button></form>
    </div>
    
    <div class="card">
  <h2>Note interne <small class="muted">(visible seulement par le super-admin)</small></h2>
  <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="note"><textarea name="admin_note" rows="3" placeholder="Ex. : appelé le 12/11, pièces OK, gros festival en juillet…"><?= e((string)($org['admin_note'] ?? '')) ?></textarea><button class="btn btn-sm btn-light">Enregistrer</button></form>
</div>

  </div>
</div>
<?php elseif ($tab === 'argent'): ?>
<div class="money-flow card">
  <div><small class="muted">Part organisateur des ventes en ligne</small><b><?= e(money($balance['due'])) ?></b></div><span>−</span>
  <div><small class="muted">Déjà reversé</small><b><?= e(money($balance['paid_out'])) ?></b></div><span>−</span>
  <div><small class="muted">Retenues</small><b><?= e(money((int)$balance['charges'])) ?></b></div><span>=</span>
  <div class="mf-total"><small class="muted">Reste à reverser</small><b><?= e(money($balance['balance'])) ?></b></div>
</div>
<div class="split mt">
  <div><form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="payout">
      <h2>Enregistrer un reversement</h2>
      <p class="muted">Fais le virement depuis ta banque vers l'IBAN de l'organisateur, puis note-le ici.</p>
      <div class="grid2">
        <div class="field"><label>Montant</label><div class="input-suffix"><input name="amount" value="<?= e(money_input(max(0, $balance['balance']))) ?>" inputmode="decimal"><span>€</span></div></div>
        <div class="field"><label>Référence du virement</label><input name="reference" placeholder="VIR-2026-…"></div>
      </div>
      <div class="field"><label>Note</label><input name="note"></div>
      <button class="btn btn-sm">Enregistrer le reversement</button>
    </form>
    <div class="card">
      <h2>Historique des reversements</h2>
      <?php foreach ($payouts as $p): ?><div class="list-item"><span class="grow"><b><?= e(money((int)$p['amount'])) ?></b> <small><?= e($p['reference']) ?></small><br><small><?= e(short_date($p['created_at'])) ?> · <?= e($p['by_name']) ?><?= $p['note'] ? ' · ' . e($p['note']) : '' ?></small></span>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="payout_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="delete_payout" data-confirm="Supprimer ce reversement de l'historique ?">✕</button></form></div><?php endforeach; ?>
      <?php if (!$payouts): ?><p class="muted">Aucun reversement.</p><?php endif; ?>
    </div>
  </div>
  <div>
    <div class="card table-card"><div class="card-head"><h2>Retenues & avoirs</h2></div><div class="table-wrap"><table class="table"><tbody>
      <?php foreach ($charges as $c): ?><tr><td><small class="muted"><?= e(short_date($c['created_at'])) ?></small><br><span class="badge grey"><?= e(Billing::CHARGE_KINDS[$c['kind']] ?? $c['kind']) ?></span></td><td><?= e($c['label']) ?><?= $c['by_name'] ? '<br><small class="muted">' . e($c['by_name']) . '</small>' : '' ?></td>
        <td class="num nowrap"><?= (int)$c['amount'] < 0 ? '+' . e(money(-(int)$c['amount'])) : '−' . e(money((int)$c['amount'])) ?></td>
        <td><?php if (in_array($c['kind'], ['ajustement', 'avoir'], true)): ?><form method="post" action="<?= url('/admin/organisateurs/' . $org['id'] . '/retenue') ?>"><?= csrf_field() ?><input type="hidden" name="charge_id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="delete" data-confirm="Supprimer cette ligne ?">×</button></form><?php endif; ?></td></tr><?php endforeach; ?>
      <?php if (!$charges): ?><tr><td class="empty">Aucune retenue.</td></tr><?php endif; ?>
    </tbody></table></div>
    <form method="post" action="<?= url('/admin/organisateurs/' . $org['id'] . '/retenue') ?>" class="charge-form"><?= csrf_field() ?>
      <select name="kind"><option value="ajustement">Retenue</option><option value="avoir">Avoir (en sa faveur)</option></select>
      <input name="label" placeholder="Libellé (ex. : frais d'impression des billets)" required><div class="input-suffix"><input name="amount" inputmode="decimal" required><span>€</span></div><button class="btn btn-sm">Ajouter</button></form></div>
    <?php if ($invoices): ?><div class="card"><h2>Factures de commission</h2><?php foreach ($invoices as $i): ?><div class="list-item"><a class="grow" href="<?= url('/admin/factures/' . $i['id'] . '/pdf') ?>" target="_blank"><?= e($i['number']) ?> · <?= e($i['period']) ?></a><span><?= e(money((int)$i['amount_ttc'])) ?></span></div><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>
<?php elseif ($tab === 'conditions'): ?>
<div class="split">
  <div>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="plan">
      <h2>Formule</h2>
      <div class="field"><select name="plan_id"><option value="">Formule par défaut<?= ($d = Billing::defaultPlan()) ? ' (' . e($d['name']) . ')' : '' ?></option><?php foreach ($plans as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$org['plan_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> · <?= (int)$p['monthly_price'] ? e(money((int)$p['monthly_price'])) . '/mois' : 'sans abonnement' ?></option><?php endforeach; ?></select></div>
      <label class="check mb"><input type="checkbox" name="notify" value="1" checked> <span>Prévenir l'organisateur par e-mail</span></label>
      <button class="btn btn-sm">Changer de formule</button>
      <?php if ($terms['plan'] && $terms['plan']['modules']): ?><p class="muted mt mb0" style="font-size:13px">Modules inclus : <?= e(implode(', ', array_map(fn($m) => Billing::MODULES[$m] ?? $m, explode(',', (string)$terms['plan']['modules'])))) ?></p><?php endif; ?>
    </form>
    <div class="card"><h2>Commission appliquée</h2>
      <p class="mb0"><b><?= e($pctTxt($terms['pct'])) ?> % + <?= e(money($terms['fixed'])) ?></b> par billet<?= $terms['cap'] !== null ? ', plafonnée à ' . e(money($terms['cap'])) : '' ?></p>
      <p class="muted" style="font-size:13px">Source : <?= e($terms['source']) ?>. Ordre : contrat en cours, puis réglage particulier ci-dessous, puis formule, puis réglage global.</p>
    </div>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="fees">
      <h2>Réglage particulier</h2>
      <p class="muted">Vide = taux par défaut de la plateforme (<?= e(setting('fee_percent')) ?> % + <?= e(money((int)setting('fee_fixed'))) ?>). Actuel : <b><?= e(rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',')) ?> % + <?= e(money($fixed)) ?></b>.</p>
      <div class="grid2">
        <div class="field"><label>Pourcentage</label><div class="input-suffix"><input name="fee_percent" value="<?= e($org['fee_percent'] ?? '') ?>" inputmode="decimal" placeholder="<?= e(setting('fee_percent')) ?>"><span>%</span></div></div>
        <div class="field"><label>Fixe par billet</label><div class="input-suffix"><input name="fee_fixed" value="<?= $org['fee_fixed'] !== null ? e(money_input((int)$org['fee_fixed'])) : '' ?>" inputmode="decimal" placeholder="<?= e(money_input((int)setting('fee_fixed'))) ?>"><span>€</span></div></div>
      </div>
      <div class="field"><label>Payée par</label><select name="fee_mode"><option value="buyer" <?= $org['fee_mode'] === 'buyer' ? 'selected' : '' ?>>L'acheteur (ajoutée au prix)</option><option value="organizer" <?= $org['fee_mode'] === 'organizer' ? 'selected' : '' ?>>L'organisateur (déduite du reversement)</option></select></div>
      <button class="btn btn-sm">Enregistrer</button>
    </form>
  </div>
  <div class="card table-card"><div class="card-head"><h2>Contrats</h2><a class="btn btn-sm" href="<?= url('/admin/contrats', ['org' => $org['id']]) ?>#editeur">+ Contrat</a></div><div class="table-wrap"><table class="table"><tbody>
    <?php foreach ($contracts as $c): $live = $c['status'] === 'active' && $c['starts_on'] <= date('Y-m-d') && (!$c['ends_on'] || $c['ends_on'] >= date('Y-m-d')); ?>
      <tr><td><a href="<?= url('/admin/contrats', ['edit' => $c['id']]) ?>"><b><?= e($c['label']) ?></b></a><br><small class="muted"><?= e(date('d/m/Y', strtotime($c['starts_on']))) ?> → <?= $c['ends_on'] ? e(date('d/m/Y', strtotime($c['ends_on']))) : 'sans fin' ?></small></td>
        <td><?= $c['fee_percent'] !== null ? e($pctTxt($c['fee_percent'])) . ' %' : '' ?><?= $c['fee_fixed'] !== null ? ' + ' . e(money((int)$c['fee_fixed'])) : '' ?></td><td><?= $live ? '<span class="badge green">En cours</span>' : '<span class="badge grey">Inactif</span>' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$contracts): ?><tr><td class="empty">Pas de contrat : la formule s'applique.</td></tr><?php endif; ?>
  </tbody></table></div></div>
</div>
<?php elseif ($tab === 'equipe'): ?>
<div class="split">
  <div class="card"><h2>Comptes</h2>
    <?php foreach ($users as $m): ?><div class="list-item"><span class="grow"><b><?= e($m['name']) ?></b><br><small><?= e($m['email']) ?></small></span><span class="badge grey"><?= e(role_label($m['role'])) ?></span></div><?php endforeach; ?>
  </div>
  <div class="card"><h2>Dernières connexions</h2>
    <?php foreach ($logins as $l): ?><div class="list-item"><span class="grow"><?= e($l['name']) ?> <small class="muted"><?= e((string)$l['ip']) ?></small><br><small class="muted"><?= e(fr_date($l['created_at'])) ?></small></span><span class="badge <?= $l['result'] === 'ok' ? 'green' : 'red' ?>"><?= e($l['result'] === 'ok' ? 'Réussie' : 'Échec') ?></span></div><?php endforeach; ?>
    <?php if (!$logins): ?><p class="muted mb0">Pas encore de connexion enregistrée (journal disponible depuis la 2.2).</p><?php endif; ?>
  </div>
</div>
<?php else: ?>
<div class="split">
  <div class="card"><h2>Journal</h2>
    <?php foreach ($activity as $l): ?><div class="list-item"><span class="grow"><b><?= e(Audit::label($l['action'])) ?></b> <?= e(mb_strimwidth((string)$l['details'], 0, 120, '…')) ?><br><small class="muted"><?= e(fr_date($l['created_at'])) ?><?= $l['user_name'] ? ' · ' . e($l['user_name']) : '' ?></small></span></div><?php endforeach; ?>
    <?php if (!$activity): ?><p class="muted mb0">Rien pour l'instant.</p><?php endif; ?>
  </div>
  <div>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="message">
      <h2>Écrire à l'organisateur</h2>
      <div class="field"><input name="subject" placeholder="Sujet" required></div>
      <div class="field"><textarea name="body" rows="5" placeholder="Message (envoyé à <?= e((string)$org['email']) ?>)" required></textarea></div>
      <button class="btn btn-sm">Envoyer</button></form>
    <div class="card"><h2>Support</h2><?php foreach ($support as $s): ?><div class="list-item"><a class="grow" href="<?= url('/admin/support/' . $s['id']) ?>"><?= e($s['subject']) ?></a><?= Support::badge($s['status']) ?></div><?php endforeach; ?><?php if (!$support): ?><p class="muted mb0">Aucune demande.</p><?php endif; ?></div>
    <div class="card"><h2>Litiges</h2><?php foreach ($disputes as $d): ?><div class="list-item"><a class="grow mono" href="<?= url('/admin/litiges/' . $d['id']) ?>"><?= e((string)$d['ref']) ?></a><span><?= e(money((int)$d['amount'])) ?></span> <?= Disputes::badge($d['status']) ?></div><?php endforeach; ?><?php if (!$disputes): ?><p class="muted mb0">Aucun litige.</p><?php endif; ?></div>
  </div>
</div>
<?php endif; ?>
