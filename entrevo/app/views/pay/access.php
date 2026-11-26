<?php /** @var array $zones, $stats, $recent, $profiles, $devices, $ev; ?array $edit; string $base */
$z = $edit ?: ['id' => 0, 'name' => '', 'profiles' => '', 'capacity' => 0, 'color' => '#5e5ce6']; $zp = array_filter(explode(',', (string)$z['profiles'])); ?>
<div class="pk">
  <?php foreach ($zones as $x): $st = $stats[(int)$x['id']] ?? ['ins' => 0, 'outs' => 0, 'denied' => 0]; $cap = (int)$x['capacity']; $pct = $cap ? min(100, (int)round($x['inside'] / $cap * 100)) : null; ?>
    <div class="<?= $cap && $x['inside'] >= $cap ? 'warn' : '' ?>"><span><i class="zdot" style="background:<?= e($x['color'] ?: '#5e5ce6') ?>"></i><?= e($x['name']) ?></span><b><?= (int)$x['inside'] ?><?= $cap ? '<small> / ' . $cap . '</small>' : '' ?></b>
      <?php if ($pct !== null): ?><div class="pmeter"><i style="width:<?= $pct ?>%;background:<?= $pct >= 90 ? '#ff453a' : e($x['color'] ?: '#5e5ce6') ?>"></i></div><?php endif; ?>
      <small><?= (int)$st['ins'] ?> entrées · <?= (int)$st['outs'] ?> sorties · <?= (int)$st['denied'] ?> refus</small></div>
  <?php endforeach; ?>
  <?php if (!$zones): ?><div><span>Zones</span><b>0</b><small>crée ta première zone ci-dessous</small></div><?php endif; ?>
</div>
<div class="pgrid">
  <div class="stack">
    <div class="card table-card"><div class="card-head"><h2>Zones</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>Zone</th><th>Profils autorisés</th><th class="num">Jauge</th><th></th></tr></thead><tbody>
      <?php foreach ($zones as $x): ?><tr><td><i class="zdot" style="background:<?= e($x['color'] ?: '#5e5ce6') ?>"></i><b><?= e($x['name']) ?></b></td>
        <td><?= $x['profile_list'] ? implode(' ', array_map(fn($p) => '<span class="ptag vio">' . e(Pay::PROFILES[$p] ?? $p) . '</span>', $x['profile_list'])) : '<span class="ptag">tout le monde</span>' ?></td>
        <td class="num"><?= (int)$x['capacity'] ?: '—' ?></td>
        <td class="num"><a class="btn btn-sm btn-light" href="<?= url($base . '/acces', ['edit' => $x['id']]) ?>">Modifier</a>
          <form method="post" style="display:inline" data-confirm="Remettre le compteur de « <?= e($x['name']) ?> » à zéro ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="reset">Remise à zéro</button></form>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="archive">Archiver</button></form></td></tr><?php endforeach; ?>
      <?php if (!$zones): ?><tr><td colspan="4" class="pempty">Aucune zone : VIP, backstage, camping, loges…</td></tr><?php endif; ?></tbody></table></div></div>
    <div class="card table-card"><div class="card-head"><h2>Derniers passages</h2></div><div class="table-wrap"><table class="table table-sm"><tbody>
      <?php foreach ($recent as $r): ?><tr><td><?= e(date('H:i:s', strtotime($r['created_at']))) ?></td><td><?= e($r['zone']) ?></td><td><?= $r['direction'] === 'in' ? 'Entrée' : 'Sortie' ?></td>
        <td><?= $r['code'] ? '<span class="mono">' . e($r['code']) . '</span>' : '—' ?> <small class="muted"><?= e((string)$r['holder_name']) ?> <?= $r['profile'] ? '· ' . e(Pay::PROFILES[$r['profile']] ?? $r['profile']) : '' ?></small></td>
        <td><?= $r['result'] === 'ok' ? '<span class="ptag ok">autorisé</span>' : '<span class="ptag bad">' . e((string)$r['reason']) . '</span>' ?></td><td class="muted"><small><?= e((string)$r['staff']) ?></small></td></tr><?php endforeach; ?>
      <?php if (!$recent): ?><tr><td class="pempty">Aucun passage.</td></tr><?php endif; ?></tbody></table></div></div>
  </div>
  <div class="stack">
    <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="zone"><input type="hidden" name="id" value="<?= (int)$z['id'] ?>">
      <h2><?= $edit ? 'Modifier la zone' : 'Nouvelle zone' ?></h2>
      <div class="field"><label>Nom</label><input name="name" value="<?= e($z['name']) ?>" placeholder="Espace VIP, backstage, camping…" required></div>
      <div class="field"><label>Profils autorisés à entrer</label><?php foreach (Pay::PROFILES as $pk => $pl): ?><label class="check"><input type="checkbox" name="profiles[]" value="<?= $pk ?>" <?= in_array($pk, $zp, true) ? 'checked' : '' ?>> <span><?= e($pl) ?> <small class="muted">· <?= (int)($profiles[$pk] ?? 0) ?> porte-monnaie</small></span></label><?php endforeach; ?><small class="muted">Aucun coché : tout le monde peut entrer (on compte seulement la jauge).</small></div>
      <div class="grid2"><div class="field"><label>Jauge</label><input name="capacity" inputmode="numeric" value="<?= (int)$z['capacity'] ?: '' ?>" placeholder="illimitée"></div><div class="field"><label>Couleur</label><input type="color" name="color" value="<?= e($z['color'] ?: '#5e5ce6') ?>"></div></div>
      <button class="btn"><?= $edit ? 'Enregistrer' : 'Créer la zone' ?></button><?php if ($edit): ?><a class="btn btn-light" href="<?= url($base . '/acces') ?>">Annuler</a><?php endif; ?></form>
    <div class="card"><h2>Sur le terrain</h2><p class="pnote">Sur l'appli caisse, choisis le rôle « Contrôle d'accès » puis la zone : le contrôleur scanne le bracelet (NFC) ou le QR code, en entrée ou en sortie. Refus automatique si le profil n'est pas autorisé, si le bracelet est bloqué ou si la zone est pleine. Le profil se règle sur la fiche du porte-monnaie, à l'import des bracelets ou en masse.</p>
      <?php foreach ($devices as $d): ?><div class="list-item"><span class="grow"><?= e($d['name']) ?> <small class="muted">· <?= e($d['zone']) ?><?= $d['staff_name'] ? ' · ' . e($d['staff_name']) : '' ?></small></span><?= $d['last_seen'] && strtotime($d['last_seen']) > time() - 300 ? '<span class="ptag ok">en ligne</span>' : '<span class="ptag">hors ligne</span>' ?></div><?php endforeach; ?></div>
  </div>
</div>
