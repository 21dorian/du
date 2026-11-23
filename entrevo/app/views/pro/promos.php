<?php $rateName = array_column($rates, 'name', 'id'); ?>
<div class="split-3">
  <div class="card">
    <h2>Codes existants</h2>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Code</th><th>Effet</th><th>Utilisations</th><th>Validité</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($promos as $p): $ids = array_filter(explode(',', (string)$p['rate_ids'])); ?>
        <tr style="<?= $p['active'] ? '' : 'opacity:.5' ?>">
          <td><b class="mono"><?= e($p['code']) ?></b></td>
          <td><?= $p['kind'] === 'percent' ? '−' . (int)$p['value'] . ' %' : ($p['kind'] === 'amount' ? '−' . e(money((int)$p['value'])) . ' / billet' : '<span class="badge violet">Débloque un tarif</span>') ?>
            <?php if ($ids): ?><br><small><?= e(implode(', ', array_map(fn($i) => $rateName[$i] ?? '?', $ids))) ?></small><?php endif; ?></td>
          <td><?= (int)$p['used'] ?><?= $p['max_uses'] ? ' / ' . (int)$p['max_uses'] : '' ?></td>
          <td class="nowrap"><?= $p['valid_until'] ? 'jusqu\'au ' . e(short_date($p['valid_until'])) : '—' ?></td>
          <td class="right nowrap"><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="promo_id" value="<?= (int)$p['id'] ?>">
            <button class="btn btn-sm btn-ghost" name="action" value="toggle"><?= $p['active'] ? 'Désactiver' : 'Activer' ?></button>
            <button class="btn btn-sm btn-ghost" name="action" value="delete" data-confirm="Supprimer ce code ?">✕</button></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$promos): ?><tr><td colspan="5" class="empty">Aucun code pour l'instant.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
  <form class="card" method="post"><?= csrf_field() ?>
    <h2>Nouveau code</h2>
    <div class="field"><label>Type</label><select name="kind">
      <option value="percent">Réduction en %</option><option value="amount">Réduction en € par billet</option><option value="unlock">Code d'accès à un tarif réservé</option></select></div>
    <div class="field"><label>Code</label><input name="code" placeholder="Laisser vide pour générer" style="text-transform:uppercase"></div>
    <div class="field" data-show-if="kind=percent|amount"><label>Valeur (% ou €)</label><input name="value" value="10" inputmode="decimal"></div>
    <div class="field"><label>Tarifs concernés</label>
      <?php foreach ($rates as $r): ?><label class="check" style="margin-bottom:6px"><input type="checkbox" name="rate_ids[]" value="<?= (int)$r['id'] ?>"> <?= e($r['name']) ?><?= $r['hidden'] ? ' <span class="badge violet">Réservé</span>' : '' ?></label><?php endforeach; ?>
      <small>Aucun coché = tous les tarifs (pour une réduction).</small></div>
    <div class="grid2">
      <div class="field"><label>Utilisations max.</label><input type="number" name="max_uses" min="0" value="0"><small>0 = illimité</small></div>
      <div class="field"><label>Valable jusqu'au</label><input type="datetime-local" name="valid_until"></div>
    </div>
    <button class="btn btn-block">Créer le code</button>
  </form>
</div>
