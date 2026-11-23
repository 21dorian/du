<form class="toolbar" method="get">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, e-mail, code billet, commande…">
  <select name="rate"><option value="">Tous les tarifs</option><?php foreach ($rates as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $rate === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select>
  <select name="st">
    <option value="">Billets valides</option><option value="in" <?= $st === 'in' ? 'selected' : '' ?>>Déjà entrés ✅</option>
    <option value="out" <?= $st === 'out' ? 'selected' : '' ?>>Pas encore arrivés</option><option value="cancelled" <?= $st === 'cancelled' ? 'selected' : '' ?>>Annulés</option>
  </select>
  <button class="btn btn-sm">Filtrer</button>
  <span class="grow"></span>
  <a class="btn btn-sm btn-ghost" href="<?= url('/pro/evenements/' . $ev['id'] . '/participants', ['q' => $q, 'rate' => $rate ?: null, 'st' => $st ?: null, 'csv' => 1]) ?>">⬇️ Export Excel (CSV)</a>
</form>
<div class="card">
  <div class="card-head"><h2><?= count($rows) ?> participant<?= count($rows) > 1 ? 's' : '' ?></h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Participant</th><th>Tarif</th><th>Code</th><th>Commande</th><th>Entrée</th><?php foreach (array_slice($fields, 0, 2) as $f): ?><th><?= e($f['label']) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $ta = json_decode((string)$r['answers'], true) ?: []; $oa = (json_decode((string)$r['order_answers'], true) ?: [])['order'] ?? []; ?>
      <tr>
        <td><b><?= e($r['last_name']) ?></b> <?= e($r['first_name']) ?><br><small><?= e($r['email']) ?></small></td>
        <td><?= e($r['rate_name']) ?></td>
        <td class="mono" style="font-size:13px"><?= e($r['code']) ?></td>
        <td><a href="<?= url('/pro/commandes/' . $r['order_id']) ?>"><?= e($r['ref']) ?></a><br><small><?= e(method_label($r['method'])) ?></small></td>
        <td><?= $r['status'] !== 'valid' ? badge('cancelled') : ($r['entries'] > 0 ? '<span class="badge green">Entré ' . e(date('H:i', strtotime($r['checked_in_at']))) . '</span>' : '<span class="badge grey">Pas arrivé</span>') ?></td>
        <?php foreach (array_slice($fields, 0, 2) as $f): ?><td><?= e(($f['per_ticket'] ? $ta : $oa)[$f['id']]['value'] ?? '') ?></td><?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="empty">Personne pour l'instant 🦗 Partage le lien de ta billetterie !</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
