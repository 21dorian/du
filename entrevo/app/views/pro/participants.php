<?php
$qs = array_filter(['q' => $q, 'ev' => $evId ?: null, 'seg' => $seg ?: null], fn($v) => $v !== null && $v !== '');
$th = function (string $k, string $label, string $cls = '') use ($qs, $sort, $dir) {
    $on = $sort === $k; $d = $on && $dir === 'DESC' ? 'asc' : 'desc';
    return '<th class="' . $cls . '" aria-sort="' . ($on ? ($dir === 'ASC' ? 'ascending' : 'descending') : 'none') . '"><a class="th-sort' . ($on ? ' on' : '') . '" href="' . e(url('/pro/participants', $qs + ['sort' => $k, 'dir' => $d])) . '">' . e($label) . '<span class="th-arrow">' . ($on ? ($dir === 'ASC' ? '↑' : '↓') : '↕') . '</span></a></th>';
};
$segs = ['' => 'Tout le monde', 'fideles' => '💜 Fidèles (2+ événements)', 'gros' => '💎 100 € et plus', 'recents' => '🆕 Achat < 30 jours', 'dormants' => '😴 Pas revenus depuis 6 mois'];
?>
<p class="muted">Tous tes acheteurs, tous événements confondus : un contact par adresse e-mail, avec son historique. La base idéale pour tes prochaines campagnes 📣</p>
<div class="page-head-row">
  <div class="kpi-mini"><span>Contacts</span><b><?= number_format((int)$kpi['contacts'], 0, ',', ' ') ?></b></div>
  <div class="kpi-mini"><span>Fidèles</span><b><?= number_format((int)$kpi['fideles'], 0, ',', ' ') ?></b></div>
  <div class="kpi-mini"><span>Nouveaux (30 j)</span><b><?= number_format((int)$kpi['new30'], 0, ',', ' ') ?></b></div>
  <div class="kpi-mini"><span>Entrées scannées</span><b><?= number_format((int)$kpi['came'], 0, ',', ' ') ?></b></div>
</div>
<nav class="pill-tabs" aria-label="Segments"><?php foreach ($segs as $k => $l): ?><a class="<?= $seg === $k ? 'on' : '' ?>" href="<?= url('/pro/participants', array_filter(['q' => $q, 'ev' => $evId ?: null, 'seg' => $k ?: null])) ?>"><?= $l ?></a><?php endforeach; ?></nav>
<form class="filters card" method="get">
  <?php if ($seg): ?><input type="hidden" name="seg" value="<?= e($seg) ?>"><?php endif; ?>
  <div class="filters-main">
    <label class="search-field"><span aria-hidden="true">🔎</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, e-mail, téléphone…" aria-label="Rechercher"></label>
    <select name="ev" aria-label="Événement"><option value="">Venus à n'importe quel événement</option><?php foreach ($events as $e2): ?><option value="<?= (int)$e2['id'] ?>" <?= $evId === (int)$e2['id'] ? 'selected' : '' ?>>Venus à : <?= e($e2['name']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm">Filtrer</button>
    <span class="grow"></span>
    <a class="btn btn-sm btn-ghost" href="<?= url('/pro/participants', $qs + ['csv' => 1]) ?>">⬇️ Exporter (CSV)</a>
    <a class="btn btn-sm btn-light" href="<?= url('/pro/bientot/campagnes') ?>">✉️ Écrire à ce segment <span class="soon-pill">Bientôt</span></a>
  </div>
</form>
<div class="card table-card">
  <div class="table-head-row"><span class="muted"><?= number_format($total, 0, ',', ' ') ?> contact<?= $total > 1 ? 's' : '' ?></span></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><?= $th('name', 'Contact') ?><th>Téléphone</th><?= $th('events', 'Événements', 'num') ?><?= $th('orders', 'Commandes', 'num') ?><th class="num">Billets</th><th>Présence</th><?= $th('spent', 'Dépensé', 'num') ?><?= $th('last', 'Dernier achat') ?></tr></thead>
    <tbody>
    <?php foreach ($list as $c): $pres = (int)$c['tickets'] ? min(100, round((int)$c['came'] / (int)$c['tickets'] * 100)) : 0; ?>
      <tr data-href="<?= url('/pro/commandes', ['q' => $c['email']]) ?>">
        <td><span class="avatar-row"><span class="avatar-sm" style="--h:<?= crc32($c['email']) % 360 ?>"><?= e(mb_strtoupper(mb_substr((string)$c['first_name'], 0, 1) . mb_substr((string)$c['last_name'], 0, 1))) ?></span>
          <span><a href="<?= url('/pro/commandes', ['q' => $c['email']]) ?>"><b><?= e(trim($c['first_name'] . ' ' . $c['last_name'])) ?></b></a><?= (int)$c['events'] >= 2 ? ' <span class="tag">Fidèle</span>' : '' ?><br><small class="muted"><?= e($c['email']) ?></small></span></span></td>
        <td><small><?= e((string)$c['phone']) ?: '<span class="muted">—</span>' ?></small></td>
        <td class="num"><?= (int)$c['events'] ?></td>
        <td class="num"><?= (int)$c['orders'] ?></td>
        <td class="num"><?= (int)$c['tickets'] ?></td>
        <td><span class="mini-meter" title="<?= (int)$c['came'] ?> entrée(s) sur <?= (int)$c['tickets'] ?> billet(s)"><i style="width:<?= $pres ?>%"></i></span> <small><?= $pres ?> %</small></td>
        <td class="num"><b><?= e(money((int)$c['spent'])) ?></b></td>
        <td class="nowrap"><?= e(short_date($c['last_at'])) ?><br><small class="muted"><?= e(ago($c['last_at'])) ?></small></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="8" class="empty">Personne ne correspond. Dès la première vente, tes contacts arrivent ici 🙌</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?php if ($pages > 1): $pq = $qs + ['sort' => $sort, 'dir' => strtolower($dir)]; ?>
  <nav class="pager" aria-label="Pages">
    <?php if ($page > 1): ?><a class="btn btn-sm btn-light" href="<?= url('/pro/participants', $pq + ['page' => $page - 1]) ?>">← Précédente</a><?php endif; ?>
    <span class="muted">Page <?= $page ?> / <?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-sm btn-light" href="<?= url('/pro/participants', $pq + ['page' => $page + 1]) ?>">Suivante →</a><?php endif; ?>
  </nav>
  <?php endif; ?>
</div>
