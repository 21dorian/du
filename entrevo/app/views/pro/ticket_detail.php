<?php /** @var array $t, $o, $ev, $scans, $transfers, $history, $sessions, $answers; ?array $session, $wallet; string $base */
$valid = $t['status'] === 'valid'; $name = trim($t['first_name'] . ' ' . $t['last_name']); ?>
<div class="page-head"><div><p class="muted u-mt0"><a href="<?= url('/pro/evenements/' . $ev['id'] . '/participants') ?>">Participants</a> · billet <span class="mono"><?= e($t['code']) ?></span></p>
  <div class="row u-gap8"><?= $valid ? '<span class="badge ok">Valable</span>' : '<span class="badge red">Annulé</span>' ?><?= (int)$t['inside'] ? '<span class="badge green">Sur place</span>' : ((int)$t['entries'] ? '<span class="badge">Sorti</span>' : '<span class="badge grey">Pas encore entré</span>') ?><?= $t['type'] !== 'ticket' ? '<span class="badge">' . e(['invitation' => 'Invitation', 'accreditation' => 'Accréditation', 'external' => 'Importé'][$t['type']] ?? $t['type']) . '</span>' : '' ?></div></div>
  <div class="row u-gap8"><a class="btn btn-light" href="<?= url('/pro/commandes/' . $o['id']) ?>">Commande <?= e($o['ref']) ?></a><a class="btn btn-light" target="_blank" href="<?= url('/commande/' . $o['ref'] . '/pdf', ['t' => $o['token']]) ?>">PDF</a></div></div>

<div class="split-3">
  <div>
    <div class="card"><h2>Participant</h2>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="holder">
        <div class="grid3"><div class="field mb0"><label>Prénom</label><input name="first_name" value="<?= e($t['first_name']) ?>"></div><div class="field mb0"><label>Nom</label><input name="last_name" value="<?= e($t['last_name']) ?>"></div>
          <div class="field mb0"><label>E-mail du porteur</label><input type="email" name="holder_email" value="<?= e((string)$t['holder_email']) ?>" placeholder="<?= e((string)$o['email']) ?>"></div></div>
        <div><button class="btn btn-sm">Enregistrer</button></div></form>
      <dl class="kv u-mt12">
        <dt>Tarif</dt><dd><?= e($t['rate_name']) ?> · <?= e(money((int)$t['price'])) ?></dd>
        <dt>Date</dt><dd><?= e(fr_date($session['starts_at'] ?? $ev['starts_at'])) ?></dd>
        <?php if ($t['seat']): ?><dt>Place</dt><dd><?= e((string)$t['seat']) ?></dd><?php endif; ?>
        <dt>Entrées</dt><dd><?= (int)$t['entries'] ?><?= (int)$t['max_entries'] > 1 ? ' / ' . (int)$t['max_entries'] : '' ?><?= $t['checked_in_at'] ? ' · première le ' . e(short_date($t['checked_in_at'])) : '' ?></dd>
        <dt>Acheteur</dt><dd><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?> · <?= e((string)$o['email']) ?></dd>
        <?php if ($t['nfc_uid']): ?><dt>Bracelet NFC</dt><dd class="mono"><?= e($t['nfc_uid']) ?></dd><?php endif; ?>
        <?php if ($wallet): ?><dt>Cashless</dt><dd><?= e(money((int)$wallet['balance'])) ?> · <span class="mono"><?= e($wallet['code']) ?></span></dd><?php endif; ?>
        <?php foreach ($answers as $a): if (!is_array($a) || !isset($a['label'])) continue; ?><dt><?= e($a['label']) ?></dt><dd><?= e((string)($a['value'] ?? '')) ?></dd><?php endforeach; ?>
      </dl>
    </div>

    <div class="card table-card"><div class="card-head"><h2>Passages au contrôle</h2></div>
      <?php if (!$scans): ?><p class="muted" style="padding:0 20px 20px">Aucun passage pour l'instant.</p><?php else: ?>
      <div class="table-wrap"><table class="table table-sm"><thead><tr><th>Quand</th><th>Résultat</th><th>Porte</th><th>Par</th></tr></thead><tbody>
      <?php foreach ($scans as $s): ?><tr class="<?= $s['undone'] ? 'muted' : '' ?>"><td><?= e(short_date($s['scanned_at'])) ?></td><td><?= e(Tickets::LABELS[$s['result']] ?? $s['result']) ?><?= $s['direction'] === 'out' ? ' (sortie)' : '' ?><?= $s['undone'] ? ' · annulé' : '' ?><?= $s['offline'] ? ' · hors ligne' : '' ?></td><td><?= e((string)($s['ap'] ?? '—')) ?></td><td><?= e((string)($s['who'] ?: $s['device'])) ?></td></tr><?php endforeach; ?>
      </tbody></table></div><?php endif; ?></div>

    <?php if ($transfers || $history): ?><div class="card"><h2>Historique du billet</h2><ul class="timeline">
      <?php foreach ($transfers as $x): ?><li><span class="tl-dot"></span><div><b><?= $x['from_name'] === 'Réédition' ? 'Réédité' : 'Transféré' ?></b> · ancien code <span class="mono"><?= e($x['old_code']) ?></span><?= $x['from_name'] !== 'Réédition' ? ' · de ' . e($x['from_name']) . ' à ' . e($x['to_name']) : '' ?><br><small class="muted"><?= e(short_date($x['created_at'])) ?></small></div></li><?php endforeach; ?>
      <?php foreach ($history as $h): ?><li><span class="tl-dot"></span><div><b><?= e(Audit::label($h['action'])) ?></b> · <?= e((string)$h['details']) ?><br><small class="muted"><?= e(short_date($h['created_at'])) ?><?= $h['user_name'] ? ' · ' . e($h['user_name']) : '' ?></small></div></li><?php endforeach; ?>
    </ul></div><?php endif; ?>
  </div>

  <div>
    <div class="card"><h2>Contrôle</h2>
      <form method="post" class="row u-gap8"><?= csrf_field() ?><?php if ($valid): ?><button class="btn btn-sm" name="action" value="checkin">Faire entrer</button><?php endif; ?><?php if ((int)$t['inside']): ?><button class="btn btn-sm btn-light" name="action" value="checkout">Faire sortir</button><?php endif; ?></form>
      <small class="muted">Enregistré comme un passage au contrôle (« back-office »).</small></div>

    <div class="card"><h2>Envoyer le billet</h2>
      <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="resend">
        <div class="field mb0"><label>À</label><input type="email" name="email" value="<?= e((string)($t['holder_email'] ?: $o['email'])) ?>"></div><div><button class="btn btn-sm btn-light">Envoyer ce billet</button></div></form></div>

    <?php if ($valid): ?>
    <details class="card card-details"><summary><h2>Rééditer</h2><span class="muted">billet perdu, volé, partagé</span></summary>
      <form method="post" class="stack u-mt12"><?= csrf_field() ?><input type="hidden" name="action" value="reissue">
        <p class="muted u-m0">Un nouveau code est créé : l'ancien billet (imprimé ou en capture d'écran) sera refusé à l'entrée.</p>
        <div class="field mb0"><label>Motif</label><input name="reason" maxlength="120" placeholder="Billet perdu"></div>
        <label class="check"><input type="checkbox" name="send" value="1" checked> <span>Envoyer le nouveau billet</span></label>
        <div><button class="btn btn-sm" data-confirm="Rééditer ce billet ? L'ancien code ne passera plus.">Rééditer</button></div></form></details>

    <details class="card card-details"><summary><h2>Transférer</h2><span class="muted">à une autre personne</span></summary>
      <form method="post" class="stack u-mt12"><?= csrf_field() ?><input type="hidden" name="action" value="transfer">
        <div class="grid2"><div class="field mb0"><label>Prénom</label><input name="first_name" required></div><div class="field mb0"><label>Nom</label><input name="last_name"></div></div>
        <div class="field mb0"><label>E-mail</label><input type="email" name="email" required></div>
        <small class="muted">Nouveau nom et nouveau code ; le billet est envoyé à cette adresse.</small>
        <div><button class="btn btn-sm">Transférer</button></div></form></details>

    <?php if (count($sessions) > 1): ?>
    <details class="card card-details"><summary><h2>Changer de date</h2></summary>
      <form method="post" class="stack u-mt12"><?= csrf_field() ?><input type="hidden" name="action" value="session">
        <div class="field mb0"><label>Nouvelle date</label><select name="session_id"><?php foreach ($sessions as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$t['session_id'] === (int)$s['id'] ? 'selected' : '' ?>><?= e(fr_date($s['starts_at'])) ?></option><?php endforeach; ?></select></div>
        <label class="check"><input type="checkbox" name="resend" value="1" checked> <span>Renvoyer les billets</span></label>
        <div><button class="btn btn-sm">Déplacer</button></div></form></details>
    <?php endif; ?>

    <details class="card card-details"><summary><h2>Annuler ce billet</h2></summary>
      <form method="post" class="stack u-mt12"><?= csrf_field() ?><input type="hidden" name="action" value="cancel">
        <div class="field mb0"><label>Motif</label><input name="reason" maxlength="120"></div>
        <small class="muted">Sans remboursement. Pour rembourser, utilise « Annuler la commande » sur la <a href="<?= url('/pro/commandes/' . $o['id'] . '#annuler') ?>">commande</a>.</small>
        <div><button class="btn btn-sm btn-danger" data-confirm="Annuler ce billet ? Il sera refusé à l'entrée.">Annuler le billet</button></div></form></details>
    <?php else: ?>
    <div class="card"><h2>Réactiver</h2><form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="reactivate">
      <label class="check"><input type="checkbox" name="force" value="1"> <span>Dépasser la jauge si le tarif est complet</span></label><div><button class="btn btn-sm">Réactiver le billet</button></div></form></div>
    <?php endif; ?>
  </div>
</div>
