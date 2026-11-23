<?php
/** @var array $list, $f, $events, $sum, $allTags, $counts */
$qs = array_filter($f, fn($v) => $v !== '' && $v !== 0);
$sortUrl = function (string $k) use ($qs, $sort, $dir) {
    $d = $sort === $k && $dir === 'DESC' ? 'asc' : 'desc';
    return url('/pro/commandes', $qs + ['sort' => $k, 'dir' => $d]);
};
$th = function (string $k, string $label, string $cls = '') use ($sortUrl, $sort, $dir) {
    $on = $sort === $k;
    $aria = $on ? ($dir === 'ASC' ? 'ascending' : 'descending') : 'none';
    return '<th class="' . $cls . '" aria-sort="' . $aria . '"><a class="th-sort' . ($on ? ' on' : '') . '" href="' . e($sortUrl($k)) . '">' . e($label)
        . '<span class="th-arrow">' . ($on ? ($dir === 'ASC' ? '↑' : '↓') : '↕') . '</span></a></th>';
};
$active = count($qs);
?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Commandes</span><b><?= number_format($total, 0, ',', ' ') ?></b></div>
  <div class="kpi-mini"><span>Billets payés</span><b><?= number_format((int)$sum['tickets'], 0, ',', ' ') ?></b></div>
  <div class="kpi-mini"><span>Encaissé net</span><b><?= e(money((int)$sum['revenue'])) ?></b></div>
  <?php if ($counts['refund_request']): ?><a class="kpi-mini warn" href="<?= url('/pro/commandes', ['st' => 'refund_request']) ?>"><span>💸 Remboursements demandés</span><b><?= (int)$counts['refund_request'] ?></b></a><?php endif; ?>
</div>

<form class="filters card" method="get" id="orders-filters">
  <div class="filters-main">
    <label class="search-field"><span aria-hidden="true">🔎</span><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Référence, nom, e-mail, téléphone…" aria-label="Rechercher"></label>
    <select name="ev" aria-label="Événement"><option value="">Tous les événements</option><?php foreach ($events as $e2): ?><option value="<?= (int)$e2['id'] ?>" <?= $f['ev'] === (int)$e2['id'] ? 'selected' : '' ?>><?= e($e2['name']) ?></option><?php endforeach; ?></select>
    <select name="st" aria-label="Statut"><option value="">Payées, annulées, remboursées</option><?php foreach (['refund_request' => '💸 Remboursement demandé', 'paid' => 'Payées', 'partial' => 'Remboursées en partie', 'pending' => 'En attente de paiement', 'refunded' => 'Remboursées', 'cancelled' => 'Annulées', 'expired' => 'Expirées'] as $k => $l): ?><option value="<?= $k ?>" <?= $f['st'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm">Filtrer</button>
    <button type="button" class="btn btn-sm btn-light" data-toggle-more aria-expanded="<?= $f['m'] || $f['tag'] || $f['from'] || $f['to'] ? 'true' : 'false' ?>">Plus de filtres</button>
  </div>
  <div class="filters-more" <?= $f['m'] || $f['tag'] || $f['from'] || $f['to'] ? '' : 'hidden' ?>>
    <label>Vente <select name="m"><option value="">Toutes</option><?php foreach (['online' => 'En ligne', 'guichet' => 'Guichet', 'gratuit' => 'Gratuit', 'invitation' => 'Invitation'] as $k => $l): ?><option value="<?= $k ?>" <?= $f['m'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
    <label>Étiquette <select name="tag"><option value="">Toutes</option><?php foreach ($allTags as $t => $n): ?><option value="<?= e($t) ?>" <?= $f['tag'] === $t ? 'selected' : '' ?>><?= e($t) ?> (<?= (int)$n ?>)</option><?php endforeach; ?></select></label>
    <label>Du <input type="date" name="from" value="<?= e($f['from']) ?>"></label>
    <label>Au <input type="date" name="to" value="<?= e($f['to']) ?>"></label>
  </div>
  <?php if ($active): ?>
  <div class="chips">
    <?php
    $labels = ['q' => fn($v) => '« ' . $v . ' »', 'ev' => fn($v) => '📅 ' . (array_column($events, 'name', 'id')[$v] ?? '#' . $v), 'st' => fn($v) => '🚦 ' . (['refund_request' => 'Remboursement demandé', 'partial' => 'Remboursées en partie', 'paid' => 'Payées', 'pending' => 'En attente', 'refunded' => 'Remboursées', 'cancelled' => 'Annulées', 'expired' => 'Expirées'][$v] ?? $v),
      'm' => fn($v) => '🛒 ' . method_label($v), 'tag' => fn($v) => '🏷️ ' . $v, 'from' => fn($v) => 'Depuis le ' . short_date($v . ' 00:00:00'), 'to' => fn($v) => 'Jusqu\'au ' . short_date($v . ' 00:00:00')];
    foreach ($qs as $k => $v): $without = $qs; unset($without[$k]); ?>
      <a class="chip" href="<?= url('/pro/commandes', $without) ?>" title="Retirer ce filtre"><?= e(($labels[$k])($v)) ?> <span aria-hidden="true">×</span></a>
    <?php endforeach; ?>
    <a class="chip chip-clear" href="<?= url('/pro/commandes') ?>">Tout effacer</a>
  </div>
  <?php endif; ?>
</form>

<form method="post" action="<?= url('/pro/commandes/lot') ?>" class="card table-card" data-bulk>
  <?= csrf_field() ?>
  <?php foreach ($qs as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string)$v) ?>"><?php endforeach; ?>
  <input type="hidden" name="all" value="0" data-bulk-all>
  <div class="bulk-bar" data-bulk-bar hidden>
    <b data-bulk-count>0 sélectionnée</b>
    <?php if ($total > count($list)): ?><button type="button" class="link-btn" data-bulk-select-all>Sélectionner les <?= (int)$total ?> commandes du filtre</button><?php endif; ?>
    <span class="grow"></span>
    <span class="bulk-tag"><input name="new_tag" placeholder="Étiquette" list="tag-list" aria-label="Étiquette" maxlength="30"><datalist id="tag-list"><?php foreach ($allTags as $t => $n): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
      <button class="btn btn-sm btn-light" name="bulk" value="tag">🏷️ Étiqueter</button><button class="btn btn-sm btn-light" name="bulk" value="untag">Retirer</button></span>
    <button class="btn btn-sm btn-light" name="bulk" value="resend" data-confirm="Renvoyer les billets par e-mail aux commandes sélectionnées ?">📬 Renvoyer</button>
    <button class="btn btn-sm btn-light" name="bulk" value="export">⬇️ Exporter</button>
    <button class="btn btn-sm btn-danger" name="bulk" value="cancel" data-confirm="Annuler les commandes sélectionnées SANS remboursement ? Les billets seront refusés à l'entrée.">Annuler</button>
  </div>
  <div class="table-head-row">
    <span class="muted"><?= $total ? 'Résultats ' . ((($page - 1) * ORDER_PER_PAGE) + 1) . '–' . ((($page - 1) * ORDER_PER_PAGE) + count($list)) . ' sur ' . number_format($total, 0, ',', ' ') : 'Aucun résultat' ?></span>
    <a class="btn btn-sm btn-ghost" href="<?= url('/pro/commandes', $qs + ['csv' => 1, 'sort' => $sort, 'dir' => strtolower($dir)]) ?>">⬇️ Tout exporter (CSV)</a>
  </div>
  <div class="table-wrap"><table class="table table-orders">
    <thead><tr>
      <th class="cb"><input type="checkbox" data-bulk-toggle aria-label="Tout sélectionner sur cette page"></th>
      <?= $th('ref', 'Commande') ?><?= $th('date', 'Date') ?><?= $th('name', 'Acheteur') ?><?= $th('event', 'Événement') ?><?= $th('qty', 'Billets', 'num') ?><?= $th('total', 'Total', 'num') ?>
      <th>Vente</th><th>Statut</th>
    </tr></thead>
    <tbody>
    <?php foreach ($list as $o): $tags = order_tags($o['tags']); ?>
      <tr data-href="<?= url('/pro/commandes/' . $o['id']) ?>">
        <td class="cb"><input type="checkbox" name="ids[]" value="<?= (int)$o['id'] ?>" aria-label="Sélectionner <?= e($o['ref']) ?>"></td>
        <td><a href="<?= url('/pro/commandes/' . $o['id']) ?>"><b class="mono"><?= e($o['ref']) ?></b></a>
          <?php if ($o['internal_note']): ?><span class="note-dot" title="<?= e(mb_strimwidth($o['internal_note'], 0, 120, '…')) ?>">📝</span><?php endif; ?></td>
        <td class="nowrap"><?= e(short_date($o['created_at'])) ?><br><small><?= e(ago($o['created_at'])) ?></small></td>
        <td><?= e($o['first_name'] . ' ' . $o['last_name']) ?><br><small><?= e($o['email']) ?></small>
          <?php if ($tags): ?><div class="tag-list"><?php foreach ($tags as $t): ?><a class="tag" href="<?= url('/pro/commandes', ['tag' => $t]) ?>"><?= e($t) ?></a><?php endforeach; ?></div><?php endif; ?></td>
        <td><?= e($o['ev_name']) ?></td>
        <td class="num"><?= (int)$o['qty'] ?></td>
        <td class="num"><b><?= e(money((int)$o['total'])) ?></b><?php if ((int)$o['refunded_amount'] && $o['status'] === 'paid'): ?><br><small class="neg">−<?= e(money((int)$o['refunded_amount'])) ?></small><?php endif; ?></td>
        <td><?= e(method_label($o['method'])) ?><?= $o['provider'] === 'demo' ? ' <small>(démo)</small>' : '' ?></td>
        <td><?= badge($o['status']) ?><?php if ($o['status'] === 'paid' && $o['refund_requested_at']): ?> <span class="badge orange" title="Remboursement demandé">💸</span><?php endif; ?><?php if ($o['status'] === 'paid' && (int)$o['refunded_amount']): ?> <span class="badge grey">Partiel</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="9" class="empty"><?= $active ? 'Rien ne correspond à ces filtres 🤷 <a href="' . url('/pro/commandes') . '">Tout afficher</a>' : 'Pas encore de commande. Ça va venir 💪' ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pages">
    <?php $pq = $qs + ['sort' => $sort, 'dir' => strtolower($dir)]; ?>
    <?php if ($page > 1): ?><a class="btn btn-sm btn-light" href="<?= url('/pro/commandes', $pq + ['page' => $page - 1]) ?>">← Précédente</a><?php endif; ?>
    <?php for ($i = 1; $i <= $pages; $i++): if ($i !== 1 && $i !== $pages && abs($i - $page) > 2) { if (abs($i - $page) === 3) echo '<span class="muted">…</span>'; continue; } ?>
      <a class="pager-n<?= $i === $page ? ' on' : '' ?>" href="<?= url('/pro/commandes', $pq + ['page' => $i]) ?>" <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a class="btn btn-sm btn-light" href="<?= url('/pro/commandes', $pq + ['page' => $page + 1]) ?>">Suivante →</a><?php endif; ?>
  </nav>
  <?php endif; ?>
</form>
