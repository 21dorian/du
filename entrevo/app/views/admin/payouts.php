<?php /** @var array $due, $batches */ $total = array_sum(array_column($due, 'balance')); $noIban = count(array_filter($due, fn($o) => !$o['iban_ok'])); ?>
<p class="muted">Ce que tu dois à chaque organisateur (ventes en ligne moins ta commission, moins ce qui a déjà été reversé). Coche, ajuste si besoin, puis génère un fichier de virements groupés à importer dans ta banque.</p>
<div class="page-head-row">
  <div class="kpi-mini"><span>💸 Total à reverser</span><b><?= e(money($total)) ?></b></div>
  <div class="kpi-mini"><span>🏢 Organisateurs</span><b><?= count($due) ?></b></div>
  <div class="kpi-mini<?= $noIban ? ' warn' : '' ?>"><span>⚠️ Sans IBAN valide</span><b><?= $noIban ?></b></div>
</div>
<?php if (!$sepaOk): ?><div class="flash warning">🏦 Pour générer le fichier SEPA, renseigne d'abord le <a href="#emetteur">compte émetteur</a> (nom et IBAN de la plateforme).</div><?php endif; ?>
<form method="post" class="card table-card" data-bulk>
  <?= csrf_field() ?><input type="hidden" name="all" value="0" data-bulk-all>
  <div class="bulk-bar" data-bulk-bar hidden>
    <b data-bulk-count>0</b><span class="grow"></span>
    <label class="check" style="color:#fff"><input type="checkbox" name="record" value="1" checked> Enregistrer les reversements</label>
    <button class="btn btn-sm btn-light" name="action" value="sepa">📄 Fichier SEPA (XML)</button>
    <button class="btn btn-sm btn-light" name="action" value="mark" data-confirm="Marquer ces montants comme reversés (virement fait à la main) ?">✅ Marquer comme versés</button>
  </div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th class="cb"><input type="checkbox" data-bulk-toggle aria-label="Tout sélectionner"></th><th>Organisateur</th><th>IBAN</th><th>Dernier reversement</th><th class="num">Dû</th><th class="num">Montant à verser</th></tr></thead>
    <tbody>
    <?php foreach ($due as $o): ?>
      <tr>
        <td class="cb"><input type="checkbox" name="ids[]" value="<?= (int)$o['id'] ?>" aria-label="Sélectionner <?= e($o['name']) ?>"></td>
        <td><a href="<?= url('/admin/organisateurs/' . $o['id']) ?>"><b><?= e($o['name']) ?></b></a><br><small class="muted"><?= e($o['legal_name'] ?: $o['email']) ?></small></td>
        <td><?php if ($o['iban_ok']): ?><span class="mono" style="font-size:13px"><?= e(substr(preg_replace('~\s+~', '', (string)$o['iban']), 0, 4) . ' •••• ' . substr(preg_replace('~\s+~', '', (string)$o['iban']), -4)) ?></span> <span class="badge green">OK</span>
          <?php else: ?><span class="badge red"><?= $o['iban'] ? 'Invalide' : 'Manquant' ?></span><?php endif; ?>
          <?php if (!$o['kyc_ok']): ?><br><a class="badge orange" href="<?= url('/admin/kyc/' . $o['id']) ?>">KYC : <?= e(Kyc::STATUS[$o['kyc_status']][0] ?? '') ?></a><?php endif; ?></td>
        <td class="nowrap"><small><?= $o['last_payout'] ? e(short_date($o['last_payout'])) : '<span class="muted">jamais</span>' ?></small></td>
        <td class="num"><b><?= e(money((int)$o['balance'])) ?></b></td>
        <td class="num"><div class="input-suffix" style="width:120px;margin-left:auto"><input name="amount[<?= (int)$o['id'] ?>]" value="<?= e(money_input((int)$o['balance'])) ?>" inputmode="decimal" aria-label="Montant pour <?= e($o['name']) ?>"><span>€</span></div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$due): ?><tr><td colspan="6" class="empty">🎉 Personne n'attend d'argent. Tout est à jour.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</form>
<div class="split mt">
  <div class="card">
    <h2>🧾 Derniers lots</h2>
    <?php foreach ($batches as $b): ?>
      <div class="list-item"><span class="grow"><b class="mono"><?= e($b['batch']) ?></b><br><small class="muted"><?= e(short_date($b['at'])) ?> · <?= (int)$b['n'] ?> virement(s) · <?= e((string)$b['note']) ?></small></span><b><?= e(money((int)$b['total'])) ?></b>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="batch" value="<?= e($b['batch']) ?>"><button class="link-btn neg" name="action" value="cancel_batch" data-confirm="Annuler ce lot ? Les montants redeviendront dus (à faire si la banque a rejeté le fichier).">Annuler</button></form></div>
    <?php endforeach; ?>
    <?php if (!$batches): ?><p class="muted">Aucun lot pour l'instant.</p><?php endif; ?>
  </div>
  <div class="card" id="emetteur">
    <h2>🏦 Compte émetteur</h2>
    <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="sepa_settings">
      <div class="field mb0"><label>Titulaire du compte</label><input name="sepa_name" value="<?= e((string)setting('sepa_name')) ?>" maxlength="70" placeholder="Ex. : Entrevo SAS"></div>
      <div class="grid2"><div class="field mb0"><label>IBAN</label><input name="sepa_iban" value="<?= e((string)setting('sepa_iban')) ?>" placeholder="FR76 …"></div><div class="field mb0"><label>BIC <small class="muted">(facultatif)</small></label><input name="sepa_bic" value="<?= e((string)setting('sepa_bic')) ?>"></div></div>
      <button class="btn btn-sm btn-light">Enregistrer</button>
      <small class="muted">Le fichier XML (norme SEPA pain.001) s'importe dans l'espace pro de ta banque : « Virements groupés » ou « Remise de fichier ».</small>
    </form>
  </div>
</div>
<?= soon_card('🧪 Reversements à venir', [
  'litiges' => ['Retenue pour litiges', 'Une réserve gardée sur le solde en cas de contestation'],
], 'admin') ?>
