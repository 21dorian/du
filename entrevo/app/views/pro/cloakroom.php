<?php /** @var array $ev, $rows, $st; int $next; string $f */ $last = $_SESSION['cr_last'] ?? null; unset($_SESSION['cr_last']); ?>
<div class="kpi-grid"><div class="kpi-mini"><span>Au vestiaire</span><b><?= (int)$st['inside'] ?></b></div><div class="kpi-mini"><span>Dépôts</span><b><?= (int)$st['n'] ?></b></div><div class="kpi-mini"><span>Encaissé</span><b><?= e(money((int)$st['fees'])) ?></b></div><div class="kpi-mini"><span>Prochain numéro</span><b><?= $next ?></b></div></div>
<?php if ($last): ?><div class="card mt center"><span class="muted">Numéro à donner</span><div style="font-size:64px;font-weight:800;line-height:1.1"><?= (int)$last ?></div></div><?php endif; ?>
<div class="split mt">
  <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="in"><h2>Déposer</h2>
    <div class="grid2"><div class="field"><label>Billet (facultatif)</label><input name="ticket_code" placeholder="Code du billet" autocapitalize="characters" autofocus></div><div class="field"><label>Numéro</label><input name="number" inputmode="numeric" placeholder="<?= $next ?> (auto)"></div></div>
    <div class="grid2"><div class="field"><label>Nom</label><input name="name" placeholder="repris du billet"></div><div class="field"><label>Prix</label><div class="input-suffix"><input name="fee" inputmode="decimal" placeholder="0"><span>€</span></div></div></div>
    <div class="field"><label>Affaires</label><input name="items" placeholder="Manteau noir, sac à dos…"></div>
    <button class="btn">Enregistrer le dépôt</button></form>
  <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="out"><h2>Rendre</h2>
    <p class="muted mb0"><small>Numéro du ticket de vestiaire, ou code du billet si la personne l'a perdu.</small></p>
    <input name="q" class="input-big" placeholder="N° ou code du billet" required autocapitalize="characters">
    <button class="btn">Rendre</button></form>
</div>
<div class="card table-card mt"><div class="card-head"><nav class="pill-tabs"><a class="<?= $f !== 'all' ? 'on' : '' ?>" href="?f=in">Au vestiaire</a><a class="<?= $f === 'all' ? 'on' : '' ?>" href="?f=all">Tout</a></nav><a class="btn btn-sm btn-ghost" href="?export=csv">CSV</a></div>
  <div class="table-wrap"><table class="table"><thead><tr><th class="num">N°</th><th>Nom</th><th>Billet</th><th>Affaires</th><th class="num">Prix</th><th>Déposé</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr style="<?= $r['status'] === 'out' ? 'opacity:.5' : '' ?>"><td class="num"><b><?= (int)$r['number'] ?></b></td><td><?= e((string)$r['name']) ?></td><td class="mono"><small><?= e((string)$r['ticket_code']) ?></small></td><td><small><?= e((string)$r['items']) ?></small></td><td class="num"><?= $r['fee'] ? e(money((int)$r['fee'])) : '—' ?></td><td><small><?= e(date('H:i', strtotime($r['created_at']))) ?><?= $r['staff'] ? ' · ' . e($r['staff']) : '' ?></small></td>
    <td class="right"><?php if ($r['status'] === 'in'): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="out"><input type="hidden" name="row_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="q" value="<?= (int)$r['number'] ?>"><button class="btn btn-sm btn-light">Rendu</button></form><?php else: ?><small class="muted">rendu <?= e(date('H:i', strtotime((string)$r['returned_at']))) ?></small><?php endif; ?></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="empty">Vestiaire vide.</td></tr><?php endif; ?></tbody></table></div></div>
