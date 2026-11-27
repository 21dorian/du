<?php /** @var array $r, $o, $items; string $token */ ?>
<section class="store-wrap rs-wrap" style="max-width:560px">
  <a class="back-link" href="<?= url('/revendeur/' . $token) ?>">‹ <?= e($r['name']) ?></a>
  <div class="card rs-receipt">
    <span class="eyebrow">Vente <?= e($o['ref']) ?></span>
    <h1><?= e(money((int)$o['total'])) ?></h1>
    <p class="muted"><?= badge($o['status']) ?> · <?= e(date('d/m/Y H:i', strtotime($o['created_at']))) ?></p>
    <div class="list-item"><span class="grow"><b><?= e((string)$o['event_name']) ?></b><br><small class="muted"><?= $o['starts_at'] ? e(fr_date($o['starts_at'])) : '' ?><?= $o['venue'] ? ' · ' . e($o['venue']) : '' ?></small></span></div>
    <?php $map = Seating::map(one('SELECT * FROM events WHERE id = ?', [$o['event_id']]) ?: []); foreach ($items as $i): ?><div class="list-item"><span class="grow"><?= (int)$i['qty'] ?> × <?= e($i['name']) ?><?php if ($i['seats']): ?><br><small class="muted"><?= e(implode(', ', array_map(fn($k) => Seating::label($k, $map), explode(',', (string)$i['seats'])))) ?></small><?php endif; ?></span><span><?= e(money((int)$i['qty'] * (int)$i['unit_price'])) ?></span></div><?php endforeach; ?>
    <div class="list-item"><span class="grow">Acheteur</span><span><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?><br><small class="muted"><?= e($o['email']) ?></small></span></div>
    <div class="list-item"><span class="grow">Ta commission</span><span><?= e(money((int)$o['reseller_commission'])) ?></span></div>
    <p class="muted mt mb0"><small>Les billets ont été envoyés par e-mail à l'acheteur<?= $o['email_sent_at'] ? '' : ' (envoi en cours)' ?>. Il peut aussi les retrouver depuis « Retrouver mes billets » avec son adresse.</small></p>
  </div>
  <div class="row"><a class="btn" href="<?= url('/revendeur/' . $token . '/e/' . $o['event_id']) ?>">Nouvelle vente</a><button class="btn btn-light" data-print>Imprimer le reçu</button></div>
</section>
