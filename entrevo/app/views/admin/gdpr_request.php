<?php /** @var array $r, $inv */ $total = array_sum(array_map('count', $inv));
$lbl = ['id' => '#', 'ref' => 'Réf.', 'code' => 'Code', 'created_at' => 'Date', 'status' => 'Statut', 'first_name' => 'Prénom', 'last_name' => 'Nom', 'name' => 'Nom', 'email' => 'E-mail', 'phone' => 'Téléphone',
  'birth_date' => 'Naissance', 'ship_address' => 'Adresse', 'answers' => 'Réponses', 'total' => 'Montant', 'marketing_optin' => 'Actus', 'event' => 'Événement', 'company' => 'Société', 'holder_email' => 'Détenteur',
  'checked_in_at' => 'Entré le', 'from_name' => 'De', 'from_email' => 'E-mail (de)', 'to_name' => 'À', 'to_email' => 'E-mail (à)', 'event_id' => 'Événement', 'rating' => 'Note', 'comment' => 'Commentaire', 'author' => 'Auteur',
  'campaign_id' => 'Campagne', 'sent_at' => 'Envoyé', 'opened_at' => 'Ouvert', 'clicked_at' => 'Cliqué', 'organizer_id' => 'Organisateur', 'team' => 'Équipe', 'notes' => 'Notes', 'buyer_email' => 'Acheteur',
  'recipient_email' => 'Bénéficiaire', 'recipient_name' => 'Pour', 'message' => 'Message', 'balance' => 'Solde', 'subject' => 'Sujet', 'feature' => 'Fonction', 'role' => 'Rôle', 'last_login' => 'Connexion'];
$money = ['total', 'balance']; ?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Demande</span><b style="font-size:17px"><?= e(Gdpr::KINDS[$r['kind']] ?? $r['kind']) ?></b></div>
  <div class="kpi-mini"><span>Statut</span><b><?= Gdpr::badge($r['status']) ?></b></div>
  <div class="kpi-mini"><span>À traiter avant</span><b><?= $r['due_on'] ? e(date('d/m/Y', strtotime($r['due_on']))) : '—' ?></b></div>
  <div class="kpi-mini"><span>Données trouvées</span><b><?= $total ?></b><small class="muted"><?= count($inv) ?> rubrique(s)</small></div>
</div>
<?php if ($r['message']): ?><div class="notice info mb"><b>Message :</b> <?= nl2br(e($r['message'])) ?></div><?php endif; ?>
<div class="split-3">
  <div class="card">
    <div class="card-head"><h2>Inventaire</h2><form method="post" class="inline-form"><?= csrf_field() ?><button class="btn btn-sm btn-light" name="action" value="export">Télécharger (JSON)</button></form></div>
    <?php foreach ($inv as $label => $rows): ?>
      <details class="inv"><summary><b><?= e($label) ?></b> <span class="badge grey"><?= count($rows) ?></span></summary>
        <div class="table-wrap"><table class="table table-sm"><thead><tr><?php foreach (array_keys($rows[0]) as $c): ?><th><?= e($lbl[$c] ?? $c) ?></th><?php endforeach; ?></tr></thead><tbody>
        <?php foreach (array_slice($rows, 0, 50) as $row): ?><tr><?php foreach ($row as $col => $c): ?><td><small><?= in_array($col, $money, true) ? e(money((int)$c)) : e(mb_strimwidth((string)$c, 0, 60, '…')) ?></small></td><?php endforeach; ?></tr><?php endforeach; ?>
        </tbody></table></div></details>
    <?php endforeach; ?>
    <?php if (!$inv): ?><p class="muted">Aucune donnée trouvée pour cette adresse (ou déjà anonymisée).</p><?php endif; ?>
  </div>
  <div class="stack">
    <div class="card"><h2>Actions</h2>
      <form method="post" class="mb"><?= csrf_field() ?><button class="btn btn-light btn-block" name="action" value="send_export" <?= $inv ? '' : 'disabled' ?>>Envoyer la copie à la personne</button></form>
      <form method="post" class="mb"><?= csrf_field() ?><button class="btn btn-light btn-block" name="action" value="oppose">Appliquer l'opposition (e-mails + SMS)</button></form>
      <form method="post" class="danger-zone"><?= csrf_field() ?><input type="hidden" name="action" value="anonymize">
        <p class="mb0"><b>Effacer (anonymiser)</b></p><p class="muted u-fs13">Irréversible. Noms, e-mails, téléphones, réponses, adresses : effacés partout. Les comptes organisateurs ne sont jamais touchés.</p>
        <div class="field"><input name="confirm_email" placeholder="Recopie : <?= e($r['email']) ?>"></div>
        <button class="btn btn-block danger" <?= $inv ? '' : 'disabled' ?>>Anonymiser</button></form>
    </div>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="close">
      <h2>Clôturer</h2>
      <div class="field"><label>Réponse à la personne <small class="muted">(facultatif)</small></label><textarea name="reply" rows="4">Bonjour,

Ta demande concernant tes données personnelles a été traitée.

Bonne journée,
L'équipe <?= e(setting('platform_name', 'Entrevo')) ?></textarea></div>
      <div class="row"><button class="btn" name="status" value="done">Traitée</button><button class="btn btn-ghost" name="status" value="rejected">Refuser</button></div>
    </form>
    <?php if ($r['notes']): ?><div class="card"><h2>Journal</h2><p style="white-space:pre-line;font-size:13px" class="mb0"><?= e($r['notes']) ?></p></div><?php endif; ?>
  </div>
</div>
