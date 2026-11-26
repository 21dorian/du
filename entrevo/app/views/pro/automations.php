<?php /** @var array $s, $codes, $stats, $pot, $top; ?array $next */
$sc = ['birthday' => 'Anniversaire', 'comeback' => 'Retour', 'merci' => 'Merci', 'parrain' => 'Parrainage'];
$st = fn($k) => $stats[$k] ?? ['n' => 0, 'used' => 0]; ?>
<?php if (!$next): ?><div class="flash">Les codes automatiques portent sur ton <b>prochain événement en vente</b> : publie-en un pour qu'ils partent.</div><?php else: ?><p class="muted">Les codes portent sur ton prochain événement en vente : <b><?= e($next['name']) ?></b> (<?= e(fr_date($next['starts_at'], false)) ?>). Un code unique par personne, une utilisation, valable 30 jours. Seules les personnes qui ont accepté tes actus reçoivent un e-mail.</p><?php endif; ?>
<form method="post" class="stack"><?= csrf_field() ?>
<div class="split">
  <div class="card stack"><div class="row between"><h2>Anniversaire</h2><label class="switch"><input type="checkbox" name="auto_birthday" value="1" <?= $s['auto_birthday'] ? 'checked' : '' ?>><span></span></label></div>
    <p class="muted mb0"><small>Le jour J, un e-mail « Joyeux anniversaire » avec une réduction. Il faut demander la date de naissance (événements avec âge minimum, ou formulaire). <?= (int)$pot['birthday'] ?> personne(s) concernée(s) dans l'année.</small></p>
    <div class="input-suffix" style="max-width:140px"><input name="auto_birthday_pct" inputmode="numeric" value="<?= (int)$s['auto_birthday_pct'] ?>"><span>%</span></div>
    <small class="muted"><?= (int)$st('birthday')['n'] ?> envoyé(s) · <?= (int)$st('birthday')['used'] ?> utilisé(s)</small></div>
  <div class="card stack"><div class="row between"><h2>Tu nous manques</h2><label class="switch"><input type="checkbox" name="auto_comeback" value="1" <?= $s['auto_comeback'] ? 'checked' : '' ?>><span></span></label></div>
    <p class="muted mb0"><small>Pour ceux qui n'ont rien acheté depuis plus d'un an : une réduction pour revenir (une fois par an). <?= (int)$pot['comeback'] ?> personne(s) aujourd'hui.</small></p>
    <div class="input-suffix" style="max-width:140px"><input name="auto_comeback_pct" inputmode="numeric" value="<?= (int)$s['auto_comeback_pct'] ?>"><span>%</span></div>
    <small class="muted"><?= (int)$st('comeback')['n'] ?> envoyé(s) · <?= (int)$st('comeback')['used'] ?> utilisé(s)</small></div>
  <div class="card stack"><div class="row between"><h2>Merci d'être venu</h2><label class="switch"><input type="checkbox" name="auto_thanks" value="1" <?= $s['auto_thanks'] ? 'checked' : '' ?>><span></span></label></div>
    <p class="muted mb0"><small>Le lendemain d'un événement, aux personnes dont le billet a été scanné : une réduction sur le suivant.</small></p>
    <div class="input-suffix" style="max-width:140px"><input name="auto_thanks_pct" inputmode="numeric" value="<?= (int)$s['auto_thanks_pct'] ?>"><span>%</span></div>
    <small class="muted"><?= (int)$st('merci')['n'] ?> envoyé(s) · <?= (int)$st('merci')['used'] ?> utilisé(s)</small></div>
</div>
<div class="split">
  <div class="card stack"><div class="row between"><h2>Parrainage</h2><label class="switch"><input type="checkbox" name="referral" value="1" <?= $s['referral'] ? 'checked' : '' ?>><span></span></label></div>
    <p class="muted mb0"><small>Chaque acheteur a un lien « Inviter des amis » dans son e-mail de confirmation. Quand assez d'amis achètent grâce à lui, il reçoit sa récompense. Déjà <?= (int)$pot['referred'] ?> commande(s) venue(s) de <?= (int)$pot['referrers'] ?> parrain(s).</small></p>
    <div class="grid2"><div class="field"><label>Amis nécessaires</label><input name="referral_n" inputmode="numeric" value="<?= (int)$s['referral_n'] ?>"></div><div class="field"><label>Récompense</label><div class="input-suffix"><input name="referral_pct" inputmode="numeric" value="<?= (int)$s['referral_pct'] ?>"><span>%</span></div></div></div>
    <?php foreach ($top as $t): ?><div class="list-item"><span class="grow"><?= e(trim($t['first_name'] . ' ' . $t['last_name']) ?: $t['email']) ?></span><b><?= (int)$t['n'] ?> ami(s)</b></div><?php endforeach; ?></div>
  <div class="card stack"><div class="row between"><h2>Rapport de fin d'événement</h2><label class="switch"><input type="checkbox" name="end_report" value="1" <?= $s['end_report'] ? 'checked' : '' ?>><span></span></label></div>
    <p class="muted mb0"><small>Le lendemain de chaque événement, les responsables reçoivent un e-mail avec les premiers chiffres et le bilan PDF (billetterie, présence, bars, heure par heure, budget).</small></p></div>
</div>
<div><button class="btn">Enregistrer</button></div>
</form>
<div class="card table-card mt"><div class="card-head"><h2>Derniers codes envoyés</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Scénario</th><th>Pour</th><th>Code</th><th>Événement</th><th>Utilisé</th></tr></thead><tbody>
  <?php foreach ($codes as $c): ?><tr><td><small><?= e(short_date($c['created_at'])) ?></small></td><td><?= e($sc[$c['scenario']] ?? $c['scenario']) ?></td><td><small><?= e($c['email']) ?></small></td><td class="mono"><?= e($c['code']) ?></td><td><small><?= e((string)$c['ev']) ?></small></td><td><?= (int)$c['used'] ? '<span class="tag green">oui</span>' : '<span class="muted">non</span>' ?></td></tr><?php endforeach; ?>
  <?php if (!$codes): ?><tr><td colspan="6" class="empty">Aucun code envoyé pour l'instant.</td></tr><?php endif; ?></tbody></table></div></div>
