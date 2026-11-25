<?php /** @var array $org, $reg, $k, $missing, $commission, $settings, $months; string $month, $from, $to, $kind */ ?>
<p class="muted lead-sm">Toutes tes pièces comptables au même endroit. Une facture émise n'est jamais modifiée : quand tu rembourses une commande facturée, un <b>avoir</b> numéroté est créé tout seul.</p>
<?php if ($missing): ?><div class="notice warn mb">Il manque des mentions obligatoires sur tes factures : <?= e(implode(', ', $missing)) ?>. <a href="<?= url('/pro/organisation') ?>">Complète-les dans Organisation</a>.</div><?php endif; ?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Factures du mois</span><b><?= (int)$k['inv'] ?></b><small class="muted"><?= e(money((int)$k['inv_t'])) ?> TTC</small></div>
  <div class="kpi-mini"><span>Avoirs du mois</span><b><?= (int)$k['av'] ?></b><small class="muted"><?= e(money((int)$k['av_t'])) ?></small></div>
  <div class="kpi-mini"><span>Net facturé</span><b><?= e(money((int)$k['inv_t'] + (int)$k['av_t'])) ?></b></div>
  <div class="kpi-mini<?= $k['todo'] && !(int)$org['auto_invoice'] ? ' kpi-alert' : '' ?>"><span>Ventes sans facture</span><b><?= (int)$k['todo'] ?></b><small class="muted"><?= e(money((int)$k['todo_t'])) ?></small></div>
</div>

<div class="card table-card">
  <div class="card-head"><h2>Registre</h2>
    <form class="row cp-filters">
      <select name="mois" onchange="this.form.submit()"><?php foreach ($months as $m): ?><option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= e(month_fr($m)) ?></option><?php endforeach; ?></select>
      <select name="type" onchange="this.form.submit()"><option value="">Factures et avoirs</option><option value="facture" <?= $kind === 'facture' ? 'selected' : '' ?>>Factures</option><option value="avoir" <?= $kind === 'avoir' ? 'selected' : '' ?>>Avoirs</option></select>
      <a class="btn btn-sm btn-light" href="<?= url('/pro/factures', ['mois' => $month, 'type' => $kind, 'export' => 'csv']) ?>">CSV</a>
      <a class="btn btn-sm btn-light" href="<?= url('/pro/factures', ['mois' => $month, 'export' => 'zip']) ?>">Tous les PDF (ZIP)</a>
    </form>
  </div>
  <?php if ($k['todo']): ?><form method="post" class="cp-todo"><?= csrf_field() ?><input type="hidden" name="action" value="invoice_all"><span><?= (int)$k['todo'] ?> vente(s) payée(s) en <?= e(month_fr($month)) ?> n'ont pas encore de facture.</span><button class="btn btn-sm">Les facturer maintenant</button></form><?php endif; ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Pièce</th><th>Date</th><th>Client</th><th>Commande</th><th class="num">Montant TTC</th><th></th></tr></thead><tbody>
    <?php foreach ($reg as $r): $av = $r['kind'] === 'avoir'; ?>
      <tr><td><span class="badge <?= $av ? 'orange' : 'grey' ?>"><?= $av ? 'Avoir' : 'Facture' ?></span> <b class="mono"><?= e($r['number']) ?></b><?= $av ? '<br><small class="muted">sur ' . e((string)$r['inv']) . '</small>' : '' ?></td>
        <td><?= e(date('d/m/Y', strtotime((string)$r['at']))) ?></td>
        <td><?= e($r['invoice_company'] ?: trim($r['first_name'] . ' ' . $r['last_name'])) ?><br><small class="muted"><?= e((string)$r['email']) ?></small></td>
        <td><a class="mono" href="<?= url('/pro/commandes/' . $r['id']) ?>"><?= e($r['ref']) ?></a><br><small class="muted"><?= e((string)($r['ev'] ?: 'Boutique')) ?></small></td>
        <td class="num<?= $av ? ' text-red' : '' ?>"><b><?= e(money((int)$r['amount'])) ?></b></td>
        <td class="num"><a class="btn btn-sm btn-light" target="_blank" href="<?= url($av ? '/pro/factures/avoir/' . $r['doc_id'] : '/pro/factures/facture/' . $r['doc_id']) ?>">PDF</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$reg): ?><tr><td colspan="6" class="empty">Aucune pièce en <?= e(month_fr($month)) ?>.<?= (int)$org['auto_invoice'] ? '' : ' Les factures sont émises quand un acheteur en demande une, ou quand tu factures le mois.' ?></td></tr><?php endif; ?>
  </tbody></table></div>
</div>

<div class="split mt">
  <div>
    <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="auto">
      <h2>Facturation</h2>
      <label class="check"><input type="checkbox" name="auto_invoice" value="1" <?= (int)$org['auto_invoice'] ? 'checked' : '' ?> onchange="this.form.submit()"><span><b>Facturer automatiquement chaque vente payée</b><br><small class="muted">Utile si tu dois une facture pour chaque recette (association fiscalisée, société). Sinon, une facture est émise quand l'acheteur la demande depuis sa commande.<?= (int)$org['auto_invoice'] && !empty($settings['auto_from']) ? ' Actif depuis le ' . e(date('d/m/Y', strtotime($settings['auto_from']))) . '.' : '' ?></small></span></label>
      <p class="muted mb0"><small>Numérotation continue, sans trou : factures <span class="mono">FA-<?= date('Y') ?>-0001</span>, avoirs <span class="mono">AV-<?= date('Y') ?>-0001</span>.</small></p>
    </form>
    <div class="card"><h2>Exports pour ton comptable</h2>
      <form method="get" action="<?= url('/pro/finances/export') ?>" class="stack">
        <div class="grid2"><div class="field"><label>Du</label><input type="date" name="du" value="<?= e($from) ?>"></div><div class="field"><label>Au</label><input type="date" name="au" value="<?= e($to) ?>"></div></div>
        <div class="row"><button class="btn btn-sm" name="format" value="csv">Détail des ventes (CSV)</button><button class="btn btn-sm btn-light" name="format" value="fec">Écritures FEC</button></div>
      </form>
      <?php if ($commission): ?><h3 class="mt">Factures de commission reçues</h3>
        <?php foreach ($commission as $c): ?><div class="list-item"><span class="grow"><span class="mono"><?= e($c['number']) ?></span> · <?= e(month_fr($c['period'])) ?></span><b><?= e(money((int)$c['amount_ttc'])) ?></b><a class="btn btn-sm btn-light" target="_blank" href="<?= url('/pro/finances/factures/' . $c['id']) ?>">PDF</a></div><?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="accounts">
    <h2>Plan comptable</h2><p class="muted"><small>Utilisé par l'export FEC. Laisse vide pour garder le compte proposé ; ton comptable peut te donner les siens.</small></p>
    <?php foreach (Accounting::ACCOUNTS as $key => [$def, $label]): ?>
      <div class="cp-acc"><label for="acc-<?= $key ?>"><?= e($label) ?></label><input id="acc-<?= $key ?>" name="acc[<?= $key ?>]" inputmode="numeric" placeholder="<?= $def ?>" value="<?= e((string)($settings['accounts'][$key] ?? '')) ?>"></div>
    <?php endforeach; ?>
    <?php foreach (Accounting::JOURNALS as $key => [$def, $label]): ?>
      <div class="cp-acc"><label for="jr-<?= $key ?>">Journal · <?= e($label) ?></label><input id="jr-<?= $key ?>" name="jr[<?= $key ?>]" placeholder="<?= $def ?>" value="<?= e((string)($settings['journals'][$key] ?? '')) ?>"></div>
    <?php endforeach; ?>
    <button class="btn mt">Enregistrer</button>
  </form>
</div>
