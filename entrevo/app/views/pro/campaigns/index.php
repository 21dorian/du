<?php /** @var array $list, $counts, $tot, $templates */ $pct = fn($a, $b) => $b ? number_format($a / $b * 100, 1, ',', ' ') . ' %' : '—'; ?>
<p class="muted">Préviens tes anciens participants de ton prochain événement. On n'écrit qu'aux personnes qui l'ont accepté (ou à tes clients pour des événements du même genre), avec un lien de désinscription dans chaque e-mail.</p>
<div class="page-head-row">
  <div class="kpi-mini"><span>Inscrits aux actus</span><b><?= number_format($counts['optin'], 0, ',', ' ') ?></b></div>
  <div class="kpi-mini"><span>Clients joignables</span><b><?= number_format($counts['customers'], 0, ',', ' ') ?></b></div>
  <div class="kpi-mini"><span>Taux d'ouverture</span><b><?= $pct($tot['opened'], $tot['sent']) ?></b></div>
  <div class="kpi-mini"><span>Taux de clic</span><b><?= $pct($tot['clicked'], $tot['sent']) ?></b></div>
  <div class="kpi-mini"><span>Ventes générées</span><b><?= e(money($tot['revenue'])) ?></b></div>
</div>

<div class="card">
  <div class="card-head"><h2>Nouvelle campagne</h2><small class="muted">Choisis un point de départ, tout se modifie ensuite</small></div>
  <form method="post" action="<?= url('/pro/campagnes/nouvelle') ?>" class="tpl-grid"><?= csrf_field() ?>
    <?php foreach ($templates as $k => [$label, $subject]): ?>
      <button class="tpl" name="modele" value="<?= e($k) ?>"><span class="tpl-ico"><?= ui_icon(['annonce' => 'megaphone', 'dernieres' => 'clock', 'merci' => 'star', 'vide' => 'mail'][$k] ?? 'mail') ?></span><b><?= e($label) ?></b><small><?= e($subject ?: 'Commence de zéro') ?></small></button>
    <?php endforeach; ?>
  </form>
</div>

<div class="card table-card">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Campagne</th><th>Statut</th><th class="num">Destinataires</th><th class="num">Ouvertures</th><th class="num">Clics</th><th class="num">Ventes</th><th>Date</th></tr></thead>
    <tbody>
    <?php foreach ($list as $c): $s = $c['stats']; ?>
      <tr data-href="<?= url('/pro/campagnes/' . $c['id']) ?>">
        <td><a href="<?= url('/pro/campagnes/' . $c['id']) ?>"><b><?= e($c['name']) ?></b></a><br><small class="muted"><?= e($c['subject'] ?: '(sans objet)') ?></small></td>
        <td><?= Campaigns::badge($c['status']) ?><?php if ($c['status'] === 'sending' && $s): ?><br><small class="muted"><?= $s['sent'] ?> / <?= $s['total'] ?></small><?php endif; ?></td>
        <td class="num"><?= $s ? number_format($s['total'], 0, ',', ' ') : '<span class="muted">—</span>' ?></td>
        <td class="num"><?= $s ? number_format($s['open_rate'], 1, ',', ' ') . ' %' : '<span class="muted">—</span>' ?></td>
        <td class="num"><?= $s ? number_format($s['click_rate'], 1, ',', ' ') . ' %' : '<span class="muted">—</span>' ?></td>
        <td class="num"><?= $s && $s['revenue'] ? '<b>' . e(money($s['revenue'])) . '</b>' : '<span class="muted">—</span>' ?></td>
        <td class="nowrap"><small><?= e(short_date($c['sent_at'] ?: $c['scheduled_at'] ?: $c['updated_at'] ?: $c['created_at'])) ?></small></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="7" class="empty">Pas encore de campagne. Choisis un modèle juste au-dessus</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<p class="muted" style="font-size:13px">Les acheteurs peuvent cocher « recevoir les prochains événements » au moment de payer : ta liste grossit toute seule. Les e-mails partent par lots de <?= Campaigns::BATCH ?> toutes les minutes environ, pour rester dans les limites de ton hébergement.</p>
<?php $ao = $auto['org']; $rc = $auto['recovery']; ?>
<div class="card auto-card">
  <div class="card-head"><h2>Envois automatiques</h2><small class="muted">Ils partent tout seuls · chiffres des 90 derniers jours</small></div>
  <form method="post" action="<?= url('/pro/campagnes/automatiques') ?>" class="auto-grid"><?= csrf_field() ?>
    <label class="auto-item"><input type="checkbox" name="auto_recovery" value="1" <?= $ao['auto_recovery'] ? 'checked' : '' ?> onchange="this.form.requestSubmit()">
      <span class="auto-body"><b>Relance des paniers abandonnés</b><small>1 h après un paiement pas terminé, avec le panier prêt à reprendre.</small>
      <span class="auto-stats"><span><b><?= (int)$rc['sent'] ?></b> relancé<?= (int)$rc['sent'] === 1 ? '' : 's' ?></span><span><b><?= (int)$rc['recovered'] ?></b> récupéré<?= (int)$rc['recovered'] === 1 ? '' : 's' ?> (<?= e(number_format($rc['rate'], 1, ',', ' ')) ?> %)</span><span><b><?= e(money($rc['revenue'])) ?></b> de ventes</span></span></span></label>
    <label class="auto-item"><input type="checkbox" name="auto_reminder" value="1" <?= $ao['auto_reminder'] ? 'checked' : '' ?> onchange="this.form.requestSubmit()">
      <span class="auto-body"><b>Rappel la veille</b><small>Heure, adresse, itinéraire et lien vers les billets, envoyé la veille de l'événement.</small>
      <span class="auto-stats"><span><b><?= (int)$auto['reminders'] ?></b> rappel<?= (int)$auto['reminders'] === 1 ? '' : 's' ?> envoyé<?= (int)$auto['reminders'] === 1 ? '' : 's' ?></span></span></span></label>
    <label class="auto-item"><input type="checkbox" name="auto_review" value="1" <?= $ao['auto_review'] ? 'checked' : '' ?> onchange="this.form.requestSubmit()">
      <span class="auto-body"><b>Demande d'avis</b><small>Le lendemain, aux participants venus (note en un clic + commentaire).</small>
      <span class="auto-stats"><span><b><?= (int)$auto['reviews_sent'] ?></b> demande<?= (int)$auto['reviews_sent'] === 1 ? '' : 's' ?></span><span><b><?= (int)$auto['reviews'] ?></b> avis reçu<?= (int)$auto['reviews'] === 1 ? '' : 's' ?></span><a href="<?= url('/pro/avis') ?>">Voir les avis →</a></span></span></label>
    <noscript><button class="btn btn-sm">Enregistrer</button></noscript>
    <details class="auto-more">
      <summary>Réglages fins</summary>
      <input type="hidden" name="details" value="1">
      <div class="auto-more-grid">
        <label class="check"><input type="checkbox" name="recovery_second" value="1" <?= $ao['recovery_second'] ? 'checked' : '' ?>> <span>2<sup>e</sup> relance 24 h après la première (dernier rappel)</span></label>
        <label class="inline-field">Code promo offert dans la 2<sup>e</sup> relance <span class="input-suffix" style="width:110px"><input type="number" name="recovery_pct" min="0" max="50" value="<?= (int)$ao['recovery_pct'] ?>"><span>%</span></span> <small class="muted">0 = pas de code. Code unique, 1 utilisation, 72 h.</small></label>
        <label class="inline-field">Envoyer le rappel <select name="reminder_hours"><?php foreach ([24 => 'la veille (24 h avant)', 48 => '2 jours avant', 72 => '3 jours avant', 168 => 'une semaine avant'] as $h => $l): ?><option value="<?= $h ?>" <?= (int)$ao['reminder_hours'] === $h ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
      </div>
      <button class="btn btn-sm">Enregistrer les réglages</button>
    </details>
  </form>
</div>
<?php $rf = $auto['referral']; ?>
<div class="card">
  <div class="card-head"><h2>Bouche-à-oreille</h2><small class="muted">Après l'achat, chaque acheteur reçoit un lien perso à partager · 90 derniers jours</small></div>
  <div class="page-head-row" style="margin:0 0 10px">
    <div class="kpi-mini"><span>Ventes via un ami</span><b><?= (int)$rf['sales'] ?></b></div>
    <div class="kpi-mini"><span>Chiffre d'affaires</span><b><?= e(money($rf['revenue'])) ?></b></div>
  </div>
  <?php if ($rf['top']): ?><p class="mb0"><b>Meilleurs ambassadeurs :</b> <?= implode(' · ', array_map(fn($t) => e(trim($t['first_name'] . ' ' . mb_substr((string)$t['last_name'], 0, 1) . '.')) . ' <span class="muted">(' . (int)$t['n'] . ', ' . e($t['ev_name']) . ')</span>', $rf['top'])) ?></p>
  <?php else: ?><p class="muted mb0">Pas encore de vente par invitation. Le lien est dans la page de commande et l'e-mail de confirmation.</p><?php endif; ?>
</div>
<?= soon_card('Bientôt : encore plus d\'automatique', [
  'codes-auto' => ['Codes promo automatiques', 'Anniversaire, fidélité, retour après longtemps'],
  'sms' => ['Campagnes SMS', 'Le canal le plus lu, en complément des e-mails'],
  'parrainage' => ['Parrainage acheteurs', 'Chaque acheteur fait venir ses amis'],
]) ?>
