<?php /** @var array $devices, $ev; string $latest, $base */
$on = count(array_filter($devices, fn($d) => $d['last_seen'] && strtotime($d['last_seen']) > time() - 300));
$low = count(array_filter($devices, fn($d) => $d['battery'] !== null && (int)$d['battery'] < 20));
$late = count(array_filter($devices, fn($d) => (int)$d['queue_size'] > 0)); ?>
<div class="pk">
  <div><span>Terminaux</span><b><?= count($devices) ?></b></div>
  <div class="good"><span>En ligne</span><b><?= $on ?></b><small>vus il y a moins de 5 min</small></div>
  <div class="<?= $low ? 'warn' : '' ?>"><span>Batterie faible</span><b><?= $low ?></b><small>moins de 20 %</small></div>
  <div class="<?= $late ? 'warn' : '' ?>"><span>Ventes en attente d'envoi</span><b><?= array_sum(array_map(fn($d) => (int)$d['queue_size'], $devices)) ?></b><small>sur <?= $late ?> terminal(aux) hors ligne</small></div>
</div>
<div class="card table-card"><div class="card-head"><h2>Parc de terminaux</h2><a class="btn btn-sm" href="<?= url($base . '/appareils') ?>">Appairer un terminal</a></div><div class="table-wrap"><table class="table pfleet">
  <thead><tr><th>Terminal</th><th>État</th><th>Poste actuel</th><th>Batterie</th><th>Version</th><th>Dernière synchro</th><th class="num">Ventes</th><th class="num">Hors ligne</th></tr></thead><tbody>
  <?php foreach ($devices as $d): $online = $d['last_seen'] && strtotime($d['last_seen']) > time() - 300; $pairing = !$d['token_hash'];
    $b = $d['battery'] !== null ? (int)$d['battery'] : null; ?><tr>
    <td><b><?= e($d['name']) ?></b><br><small class="muted"><?= e(ucfirst((string)($d['platform'] ?: 'web'))) ?></small></td>
    <td><?= $pairing ? '<span class="ptag warn">à appairer</span>' : ($online ? '<span class="ptag ok">en ligne</span>' : '<span class="ptag">hors ligne · ' . e($d['last_seen'] ? ago($d['last_seen']) : 'jamais') . '</span>') ?></td>
    <td><?= $d['station_at'] ? e(($d['bar_name'] ?: 'Tous') . ' · ' . (Pos::MODE_LABELS[$d['mode']] ?? 'Vente')) . '<br><small class="muted">' . e((string)$d['staff_name']) . '</small>' : '—' ?></td>
    <td><?php if ($b !== null): ?><span class="pbat"><i style="--b:<?= max(2, (int)round($b * 0.22)) ?>px;--c:<?= $b < 20 ? '#ff453a' : ($b < 40 ? '#ff9f0a' : '#30d158') ?>"></i><?= $b ?> %<?= (int)$d['charging'] ? ' ⚡' : '' ?></span><?php else: ?>—<?php endif; ?></td>
    <td><?= $d['app_version'] ? e($d['app_version']) . ($latest && $d['app_version'] !== $latest ? ' <span class="ptag warn">à mettre à jour</span>' : '') : '—' ?></td>
    <td><?= $d['last_sync'] ? e(ago($d['last_sync'])) : '—' ?><?= (int)$d['queue_size'] ? '<br><span class="ptag warn">' . (int)$d['queue_size'] . ' en attente</span>' : '' ?></td>
    <td class="num"><?= (int)$d['sales'] ?><br><small class="muted"><?= e(money((int)$d['ttc'])) ?></small></td><td class="num"><?= (int)$d['offline_sales'] ?></td></tr><?php endforeach; ?>
  <?php if (!$devices): ?><tr><td colspan="8" class="pempty">Aucun terminal. Appaire une tablette ou un téléphone depuis Points de vente & appareils.</td></tr><?php endif; ?>
</tbody></table></div></div>
<p class="pnote mt">La batterie, la version et la file d'attente sont envoyées par l'application toutes les 20 secondes. « Hors ligne » compte les ventes encaissées sans réseau puis synchronisées.</p>
