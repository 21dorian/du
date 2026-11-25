<?php /** @var array $list, $counts, $batches, $events; string $q, $f */
$ST = ['stock' => ['En stock', ''], 'active' => ['Actif', 'ok'], 'blocked' => ['Bloqué', 'bad'], 'lost' => ['Perdu', 'warn'], 'destroyed' => ['Détruit', '']]; ?>
<div class="pk"><?php foreach ($ST as $k => [$l, $c]): ?><div class="<?= $c === 'bad' && !empty($counts[$k]) ? 'warn' : '' ?>"><span><?= $l ?></span><b><?= (int)($counts[$k] ?? 0) ?></b></div><?php endforeach; ?></div>
<div class="pgrid">
  <div class="stack">
    <div class="card"><form class="psearch"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Numéro imprimé, UID NFC ou lot"><select name="f"><option value="">Tous</option><?php foreach ($ST as $k => [$l]): ?><option value="<?= $k ?>" <?= $f === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><button class="btn">Filtrer</button></form></div>
    <div class="card table-card"><div class="table-wrap"><table class="table table-sm"><thead><tr><th>N°</th><th>UID NFC</th><th>Lot</th><th>Profil</th><th>État</th><th>Porte-monnaie</th><th></th></tr></thead><tbody>
      <?php foreach ($list as $b): ?><tr><td><b><?= e((string)$b['number']) ?></b></td><td class="mono"><?= e($b['uid']) ?></td><td><?= e((string)$b['batch']) ?></td><td><?= e(Pay::PROFILES[$b['profile']] ?? $b['profile']) ?></td>
        <td><span class="ptag <?= $ST[$b['status']][1] ?? '' ?>"><?= e($ST[$b['status']][0] ?? $b['status']) ?></span></td>
        <td><?= $b['wcode'] ? '<a class="mono" href="' . Pay::url('/pro/e/' . $b['wev'] . '/porte-monnaie/' . $b['wallet_id']) . '">' . e($b['wcode']) . '</a> · ' . e(money((int)$b['balance'])) : '—' ?></td>
        <td class="num"><form method="post" class="row" style="justify-content:flex-end"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><select name="status" onchange="this.form.submit()"><option value="">Changer…</option><option value="lost">Perdu</option><option value="blocked">Bloqué</option><option value="destroyed">Détruit</option><option value="stock">Remettre en stock</option></select></form></td></tr><?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="7" class="pempty">Aucun bracelet. Importe ton premier lot à droite.</td></tr><?php endif; ?></tbody></table></div></div>
  </div>
  <div class="stack">
    <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="import"><h2>Importer un lot</h2>
      <p class="pnote" style="margin:0">Fichier du fabricant : une ligne par bracelet, « numéro;UID » ou seulement l'UID. Les bracelets s'activent ensuite à la caisse (point de recharge) ou en les associant à un billet.</p>
      <textarea name="lines" rows="7" placeholder="000001;04A2B3C4D5E680&#10;000002;04A2B3C4D5E681" required></textarea>
      <input name="batch" placeholder="Nom du lot (ex. Bracelets tissu 2026)">
      <select name="profile"><?php foreach (Pay::PROFILES as $pk => $pl): ?><option value="<?= $pk ?>"><?= e($pl) ?></option><?php endforeach; ?></select>
      <select name="event_id"><option value="">Tous les événements</option><?php foreach ($events as $e2): ?><option value="<?= (int)$e2['id'] ?>"><?= e($e2['name']) ?></option><?php endforeach; ?></select>
      <button class="btn">Ajouter au stock</button></form>
    <div class="card"><h2>Lots</h2><?php foreach ($batches as $bt): ?><div class="list-item"><span class="grow"><?= e((string)$bt['batch']) ?><br><small class="muted"><?= e(Pay::PROFILES[$bt['profile']] ?? '') ?></small></span><b><?= (int)$bt['used'] ?> / <?= (int)$bt['n'] ?></b></div><?php endforeach; ?><?php if (!$batches): ?><p class="pnote">Aucun lot.</p><?php endif; ?></div>
  </div>
</div>
