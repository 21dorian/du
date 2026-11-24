<?php if ($org['status'] === 'pending'): ?>
<div class="flash warning approve-box">⏳ <b>En attente de validation</b> — inscrit le <?= e(fr_date($org['created_at'])) ?>. Vérifie ses infos (SIRET, site, description) avant de l'autoriser à publier.
  <form method="post" class="inline-form" style="margin-left:auto"><?= csrf_field() ?><button class="btn btn-sm btn-success" name="action" value="approve">✅ Valider</button> <button class="btn btn-sm btn-light" name="action" value="status" data-confirm="Refuser et suspendre ce compte ?">Refuser</button></form>
</div>
<?php endif; ?>
<div class="kpis">
  <div class="kpi"><small class="muted">Encaissé en ligne</small><b><?= e(money($balance['collected'])) ?></b></div>
  <div class="kpi"><small class="muted">Ta commission</small><b style="color:var(--green)"><?= e(money($balance['commission'])) ?></b></div>
  <div class="kpi"><small class="muted">Déjà reversé</small><b><?= e(money($balance['paid_out'])) ?></b></div>
  <div class="kpi"><small class="muted">💸 Reste à reverser</small><b><?= e(money($balance['balance'])) ?></b></div>
</div>
<div class="split">
  <div>
    <div class="card">
      <div class="card-head"><h2>Infos</h2><?= badge($org['status']) ?></div>
      <div class="summary-line"><span class="muted">Contact</span><span><?= e($org['email']) ?><?= $org['phone'] ? ' · ' . e($org['phone']) : '' ?></span></div>
      <div class="summary-line"><span class="muted">Raison sociale</span><span><?= e($org['legal_name'] ?: '—') ?></span></div>
      <div class="summary-line"><span class="muted">SIRET</span><span><?= e($org['siret'] ?: '—') ?></span></div>
      <div class="summary-line"><span class="muted">IBAN</span><span class="mono"><?= e($org['iban'] ?: '— pas encore renseigné') ?></span></div>
      <div class="summary-line"><span class="muted">BIC</span><span class="mono"><?= e($org['bic'] ?: '—') ?></span></div>
      <div class="summary-line"><span class="muted">Page publique</span><a href="<?= url('/o/' . $org['slug']) ?>" target="_blank">/o/<?= e($org['slug']) ?></a></div>
      <form method="post" class="mt"><?= csrf_field() ?><button class="btn btn-sm <?= $org['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>" name="action" value="status" data-confirm="<?= $org['status'] === 'active' ? 'Suspendre cet organisateur ? Ses événements disparaissent du site.' : 'Réactiver cet organisateur ?' ?>"><?= $org['status'] === 'active' ? '⛔ Suspendre' : '✅ Réactiver' ?></button></form>
    </div>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="fees">
      <h2>💸 Commission</h2>
      <p class="muted">Vide = taux par défaut de la plateforme (<?= e(setting('fee_percent')) ?> % + <?= e(money((int)setting('fee_fixed'))) ?>). Actuel : <b><?= e(rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',')) ?> % + <?= e(money($fixed)) ?></b>.</p>
      <div class="grid2">
        <div class="field"><label>Pourcentage</label><div class="input-suffix"><input name="fee_percent" value="<?= e($org['fee_percent'] ?? '') ?>" inputmode="decimal" placeholder="<?= e(setting('fee_percent')) ?>"><span>%</span></div></div>
        <div class="field"><label>Fixe par billet</label><div class="input-suffix"><input name="fee_fixed" value="<?= $org['fee_fixed'] !== null ? e(money_input((int)$org['fee_fixed'])) : '' ?>" inputmode="decimal" placeholder="<?= e(money_input((int)setting('fee_fixed'))) ?>"><span>€</span></div></div>
      </div>
      <div class="field"><label>Payée par</label><select name="fee_mode"><option value="buyer" <?= $org['fee_mode'] === 'buyer' ? 'selected' : '' ?>>L'acheteur (ajoutée au prix)</option><option value="organizer" <?= $org['fee_mode'] === 'organizer' ? 'selected' : '' ?>>L'organisateur (déduite du reversement)</option></select></div>
      <button class="btn btn-sm">Enregistrer</button>
    </form>
  </div>
  <div>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="payout">
      <h2>🏦 Enregistrer un reversement</h2>
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
</div>
<div class="split mt">
  <div class="card"><h2>Événements</h2>
    <?php foreach ($events as $e2): ?><div class="list-item"><span class="grow"><b><?= e($e2['name']) ?></b><br><small><?= e(fr_date($e2['starts_at'])) ?> · <?= (int)$e2['sold'] ?> billets</small></span><?= badge($e2['status']) ?></div><?php endforeach; ?>
    <?php if (!$events): ?><p class="muted">Aucun événement.</p><?php endif; ?>
  </div>
  <div class="card"><h2>Comptes</h2>
    <?php foreach ($users as $m): ?><div class="list-item"><span class="grow"><b><?= e($m['name']) ?></b><br><small><?= e($m['email']) ?></small></span><span class="badge grey"><?= e(role_label($m['role'])) ?></span></div><?php endforeach; ?>
  </div>
</div>
<div class="soon-row mt"><a class="btn btn-sm btn-light" href="<?= url('/admin/kyc/' . $org['id']) ?>">🪪 KYC : <?= e(Kyc::STATUS[$org['kyc_status'] ?? 'none'][0]) ?></a><?= soon_btn('contrats', '🤝 Contrat & tarifs négociés', 'admin') ?><?= soon_btn('rgpd', '🧾 Export RGPD', 'admin') ?><?= soon_btn('fraude', '🛡️ Score de risque', 'admin') ?></div>
<div class="card mt">
  <h2>📝 Note interne <small class="muted">(visible seulement par le super-admin)</small></h2>
  <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="note"><textarea name="admin_note" rows="3" placeholder="Ex. : appelé le 12/11, pièces OK, gros festival en juillet…"><?= e((string)($org['admin_note'] ?? '')) ?></textarea><button class="btn btn-sm btn-light">Enregistrer</button></form>
</div>
