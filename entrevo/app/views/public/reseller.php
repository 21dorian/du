<?php /** @var array $r, $events, $statement, $orders, $today; string $month, $token */ ?>
<section class="store-wrap rs-wrap">
  <div class="rs-top"><div><span class="eyebrow">Portail revendeur</span><h1><?= e($r['name']) ?></h1></div>
    <div class="rs-today"><span>Aujourd'hui</span><b><?= e(money((int)$today['t'])) ?></b><small><?= (int)$today['n'] ?> vente(s)</small></div></div>
  <h2 class="rs-h">Vendre</h2>
  <div class="rs-events">
    <?php foreach ($events as $ev): ?>
      <a class="rs-ev" href="<?= url('/revendeur/' . $token . '/e/' . $ev['id']) ?>">
        <?php if ($ev['image']): ?><img src="<?= e(url($ev['image'])) ?>" alt="" loading="lazy"><?php else: ?><span class="rs-ev-ph"></span><?php endif; ?>
        <span class="rs-ev-txt"><b><?= e($ev['name']) ?></b><small><?= e(fr_date($ev['starts_at'])) ?></small><small class="muted"><?= e(trim(($ev['venue'] ?? '') . ' · ' . $ev['org_name'], ' ·')) ?></small></span>
      </a>
    <?php endforeach; ?>
    <?php if (!$events): ?><div class="card"><p class="mb0 muted">Aucun événement à vendre pour l'instant. Les organisateurs qui t'autorisent apparaissent ici dès qu'ils publient un événement.</p></div><?php endif; ?>
  </div>
  <div class="split mt">
    <div class="card"><h2>Dernières ventes</h2>
      <?php foreach ($orders as $o): $canUndo = $o['status'] === 'paid' && strtotime((string)$o['paid_at']) > time() - 1800; ?>
        <div class="list-item"><span class="grow"><a class="mono" href="<?= url('/revendeur/' . $token . '/vente/' . $o['ref']) ?>"><?= e($o['ref']) ?></a> <?= badge($o['status']) ?><br><small class="muted"><?= e(date('d/m H:i', strtotime($o['created_at']))) ?> · <?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?> · <?= e((string)$o['event_name']) ?> · <?= (int)$o['n'] ?> billet(s)</small></span>
          <b><?= e(money((int)$o['total'])) ?></b>
          <?php if ($canUndo): ?><form method="post" onsubmit="return confirm('Annuler cette vente ? Les billets ne seront plus valables.')"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><button class="btn btn-sm btn-light">Annuler</button></form><?php endif; ?></div>
      <?php endforeach; ?>
      <?php if (!$orders): ?><p class="muted mb0">Pas encore de vente.</p><?php endif; ?>
    </div>
    <div class="card"><div class="card-head"><h2>Relevé</h2><form class="row rs-month"><select name="mois" onchange="this.form.submit()"><?php foreach (Resellers::months() as $m): ?><option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= e(month_fr($m)) ?></option><?php endforeach; ?></select><a class="btn btn-sm btn-light" href="<?= url('/revendeur/' . $token, ['mois' => $month, 'export' => 'csv']) ?>">CSV</a></form></div>
      <?php foreach ($statement as $s): ?><div class="list-item"><span class="grow"><b><?= e($s['org_name']) ?></b><br><small class="muted"><?= (int)$s['tickets'] ?> billet(s) · <?= e(money((int)$s['total'])) ?> vendus · ta commission <?= e(money((int)$s['commission'])) ?></small></span><span class="num"><small class="muted">À reverser</small><br><b><?= e(money((int)$s['owed'])) ?></b></span></div><?php endforeach; ?>
      <?php if (!$statement): ?><p class="muted mb0">Aucune vente en <?= e(month_fr($month)) ?>.</p><?php endif; ?>
      <?php if ($statement): ?><p class="muted mt mb0"><small>Total à reverser : <b><?= e(money(array_sum(array_column($statement, 'owed')))) ?></b>, à chaque organisateur, par virement, en début de mois suivant.</small></p><?php endif; ?>
    </div>
  </div>
</section>
