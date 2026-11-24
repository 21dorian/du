<?php /** @var array $c, $stats, $timeline, $orders */ $s = $stats; ?>
<div class="order-top">
  <div><div class="order-ref"><?= Campaigns::badge($c['status']) ?> <span class="muted" style="font-size:15px;font-weight:500">« <?= e($c['subject']) ?> »</span></div>
    <div class="muted"><?= $c['started_at'] ? 'Envoi lancé le ' . e(fr_date($c['started_at'])) : '' ?><?= $c['sent_at'] ? ' · terminé le ' . e(fr_date($c['sent_at'])) : '' ?></div></div>
  <form method="post" class="row" style="gap:8px"><?= csrf_field() ?>
    <?php if ($c['status'] === 'sending'): ?><button class="btn btn-sm btn-light" name="action" value="cancel" data-confirm="Arrêter l'envoi ? Ceux qui l'ont déjà reçu l'ont reçu">Arrêter l'envoi</button><?php endif; ?>
    <button class="btn btn-sm" name="action" value="duplicate">Réutiliser</button>
  </form>
</div>
<?php if ($c['status'] === 'sending' && ($err = Campaigns::lastError($c))): ?>
  <div class="flash error">L'envoi est en pause : le serveur d'e-mails refuse les messages (« <?= e($err) ?> »). Vérifie la configuration e-mail de la plateforme ; l'envoi reprend tout seul dès que ça remarche.</div>
<?php endif; ?>
<?php if ($c['status'] === 'sending'): ?>
  <div class="card"><div class="card-head"><h2>Envoi en cours</h2><span><?= $s['sent'] ?> / <?= $s['total'] ?></span></div><div class="goal-bar"><i style="width:<?= $s['total'] ? round($s['sent'] / $s['total'] * 100) : 0 ?>%"></i></div>
    <p class="muted mb0" style="font-size:13px;margin-top:8px">Par lots de <?= Campaigns::BATCH ?> environ chaque minute. Tu peux fermer cette page, ça continue tout seul.</p></div>
<?php endif; ?>
<div class="stat-grid">
  <div class="stat"><div class="stat-top"><small>Envoyés</small></div><b><?= number_format($s['sent'], 0, ',', ' ') ?></b><div class="stat-sub"><?= $s['errors'] ? '<span class="neg">' . $s['errors'] . ' échec(s)</span>' : 'sur ' . $s['total'] . ' destinataires' ?></div></div>
  <div class="stat"><div class="stat-top"><small>Ouvertures</small></div><b><?= number_format($s['open_rate'], 1, ',', ' ') ?> %</b><div class="stat-sub"><?= $s['opened'] ?> personne(s)</div></div>
  <div class="stat"><div class="stat-top"><small>Clics</small></div><b><?= number_format($s['click_rate'], 1, ',', ' ') ?> %</b><div class="stat-sub"><?= $s['clicked'] ?> personne(s)</div></div>
  <div class="stat"><div class="stat-top"><small>Ventes générées</small></div><b><?= e(money($s['revenue'])) ?></b><div class="stat-sub"><?= $s['orders'] ?> commande(s) · <?= $s['tickets'] ?> billet(s)</div></div>
</div>
<div class="split-3">
  <div class="card">
    <div class="card-head"><h2>Parcours</h2><small>Sur les e-mails envoyés</small></div>
    <?php $steps = [['Envoyés', $s['sent']], ['Ouverts', $s['opened']], ['Cliqués', $s['clicked']], ['Commandes', $s['orders']]]; $mx = max(1, $s['sent']); ?>
    <?php foreach ($steps as [$l, $n]): ?>
      <div class="hbar"><span class="hbar-l"><?= $l ?></span><span class="hbar-b"><i style="width:<?= max(1, round($n / $mx * 100)) ?>%;background:<?= Viz::SERIES ?>"></i></span><span class="hbar-v"><?= number_format($n, 0, ',', ' ') ?></span></div>
    <?php endforeach; ?>
    <?php if ($timeline): $labels = array_map(fn($t) => substr($t['h'], 8, 2) . '/' . substr($t['h'], 5, 2) . ' ' . substr($t['h'], 11, 2) . 'h', $timeline); ?>
      <div class="mt"><?= Viz::chart('columns', ['labels' => $labels, 'xlabel' => 'Heure', 'series' => [['name' => 'Ouvertures', 'values' => array_map(fn($t) => (int)$t['n'], $timeline)]]], 'Ouvertures par heure', 'int', 200) ?></div>
    <?php endif; ?>
    <?php if ($s['unsub']): ?><p class="muted mb0" style="font-size:13px"><?= $s['unsub'] ?> désinscription(s) suite à cet e-mail.</p><?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h2>Commandes générées</h2></div>
    <?php foreach ($orders as $o): ?>
      <a class="todo" href="<?= url('/pro/commandes/' . $o['id']) ?>"><span class="todo-ic"></span><span><b><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?> · <?= e(money((int)$o['total'])) ?></b><small><?= e($o['ev']) ?> · <?= e(ago($o['paid_at'])) ?></small></span><i>→</i></a>
    <?php endforeach; ?>
    <?php if (!$orders): ?><p class="muted">Pas encore de commande attribuée. Elles apparaissent ici quand quelqu'un achète après avoir cliqué.</p><?php endif; ?>
  </div>
</div>
<div class="card">
  <div class="card-head"><h2>L'e-mail envoyé</h2></div>
  <iframe class="sent-preview" src="<?= url('/pro/campagnes/' . $c['id'] . '/apercu') ?>" title="E-mail envoyé" sandbox="allow-same-origin"></iframe>
</div>
