<?php $paid = $o['status'] === 'paid'; ?>
<div class="state-head">
  <div class="state-mark<?= $paid ? '' : ' wait' ?>"><?= ui_icon($paid ? 'check' : 'clock', 30) ?></div>
  <h1><?= $paid ? 'Merci ' . e((string)$o['first_name']) . ' !' : 'Commande en attente' ?></h1>
  <p class="muted">Commande <b><?= e($o['ref']) ?></b> · <?= e($org['name']) ?><?= $paid ? '' : ' · en attente du paiement' ?></p>
  <?php if (!$paid && $o['status'] === 'pending'): ?><a class="btn" href="<?= url('/paiement/' . $o['ref'], ['t' => $o['token']]) ?>">Reprendre le paiement</a><?php endif; ?>
</div>
<div style="max-width:760px;margin:0 auto" class="stack">
  <?php foreach ($cards as $c): ?>
    <div class="giftcard lg"><small>Carte cadeau · <?= e($org['name']) ?></small><b><?= e(money((int)$c['initial'])) ?></b><span><?= $c['recipient_name'] ? 'Pour ' . e($c['recipient_name']) . ' · ' : '' ?>valable jusqu'au <?= e(date('d/m/Y', strtotime((string)$c['expires_at']))) ?></span><code><?= e($c['code']) ?></code></div>
    <p class="center"><a class="btn btn-light" href="<?= url('/commande/' . $o['ref'] . '/carte-cadeau', ['t' => $o['token'], 'code' => $c['code']]) ?>" target="_blank">Télécharger le PDF</a> <span class="muted"><?= $c['recipient_email'] ? 'Envoyée à ' . e($c['recipient_email']) : 'Envoyée à ton adresse' ?></span></p>
  <?php endforeach; ?>
  <?php foreach ($passes as $p): ?>
    <div class="card pass-done"><small class="muted">Abonnement actif</small><h2><?= e($p['type_name']) ?></h2><p class="muted">Abonné n° <?= e($p['code']) ?><?= $p['uses_left'] !== null ? ' · ' . (int)$p['uses_left'] . ' entrées' : '' ?></p><a class="btn" href="<?= url('/pass/' . $p['token']) ?>">Réserver mes places</a></div>
  <?php endforeach; ?>
  <?php $shop = array_filter($items, fn($i) => $i['kind'] === 'shop'); if ($shop): ?>
    <div class="card"><h2>Ta commande</h2>
      <?php foreach ($shop as $i): ?><div class="summary-line"><span><?= (int)$i['qty'] ?> × <?= e($i['name']) ?></span><span><?= e(money((int)$i['qty'] * (int)$i['unit_price'])) ?></span></div><?php endforeach; ?>
      <div class="summary-line total"><span>Total</span><span><?= e(money((int)$o['total'])) ?></span></div>
      <p class="muted mt"><?= $o['delivery'] === 'ship' ? 'Envoi à : ' . nl2br(e((string)$o['ship_address'])) : 'Retrait sur place' . ($org['pickup_info'] ? ' : ' . e($org['pickup_info'])  : '') . ' — présente ton numéro de commande.' ?><?= $o['fulfilled_at'] ? '<br><b>' . ($o['delivery'] === 'ship' ? 'Expédiée' : 'Retirée') . ' le ' . e(short_date((string)$o['fulfilled_at'])) . '</b>' : '' ?></p></div>
  <?php endif; ?>
  <p class="center"><a href="<?= url('/o/' . $org['slug']) ?>">Voir les événements de <?= e($org['name']) ?></a></p>
</div>
