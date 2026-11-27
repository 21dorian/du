<?php $form = function (?array $t) use ($events) {
  $p = $t ?? ['id' => 0, 'name' => '', 'description' => '', 'kind' => 'season', 'price' => 0, 'entries' => 10, 'event_ids' => '', 'valid_until' => null, 'quota' => 0, 'active' => 1, 'sort' => 0];
  $sel = array_map('intval', array_filter(explode(',', (string)$p['event_ids']))); ob_start(); ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="type_id" value="<?= (int)$p['id'] ?>">
    <div class="grid2">
      <div class="field"><label>Nom</label><input name="name" required value="<?= e($p['name']) ?>" placeholder="Pass saison 2026-2027, Carnet 5 concerts…"></div>
      <div class="field"><label>Type</label><select name="kind"><option value="season" <?= $p['kind'] === 'season' ? 'selected' : '' ?>>Pass saison (tous les événements choisis)</option><option value="carnet" <?= $p['kind'] === 'carnet' ? 'selected' : '' ?>>Carnet (un nombre d'entrées)</option></select></div>
      <div class="field"><label>Prix</label><div class="input-suffix"><input name="price" value="<?= e(money_input((int)$p['price'])) ?>" inputmode="decimal"><span>€</span></div></div>
      <div class="field" data-show-if="kind=carnet"><label>Nombre d'entrées</label><input type="number" name="entries" min="2" max="100" value="<?= (int)($p['entries'] ?: 10) ?>"></div>
      <div class="field"><label>Valable pour les événements jusqu'au</label><input type="datetime-local" name="valid_until" value="<?= e(dt_input($p['valid_until'])) ?>"></div>
      <div class="field"><label>Nombre maximum d'abonnés</label><input type="number" name="quota" min="0" value="<?= (int)$p['quota'] ?>"><small>0 = illimité</small></div>
    </div>
    <div class="field"><label>Description</label><input name="description" maxlength="500" value="<?= e((string)$p['description']) ?>" placeholder="Ce qui est compris, avantages…"></div>
    <div class="field"><label>Événements compris</label>
      <?php foreach ($events as $ev): ?><label class="check" style="margin-bottom:6px"><input type="checkbox" name="event_ids[]" value="<?= (int)$ev['id'] ?>" <?= in_array((int)$ev['id'], $sel, true) ? 'checked' : '' ?>> <span><?= e($ev['name']) ?> <small class="muted"><?= e(short_date($ev['starts_at'])) ?></small></span></label><?php endforeach; ?>
      <small>Aucun coché = tous tes événements à venir (y compris ceux que tu publieras plus tard).</small></div>
    <label class="check mb"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> <span>En vente</span></label>
    <button class="btn"><?= $p['id'] ? 'Enregistrer' : 'Créer l\'abonnement' ?></button>
  </form>
<?php return ob_get_clean(); }; ?>
<p class="muted lead-sm">Fidélise ton public : un pass saison ou un carnet d'entrées. L'abonné réserve sa place à chaque événement en un clic depuis son espace, sans repayer ; il reçoit un vrai billet scanné à l'entrée.</p>
<?php if ($types): ?><p><a class="btn btn-sm btn-light" href="<?= url('/o/' . $org['slug'] . '/abonnements') ?>" target="_blank">Voir la page de vente</a></p><?php endif; ?>
<?php foreach ($types as $t): ?>
  <details class="box"><summary><span><?= e($t['name']) ?> <?= $t['active'] ? '' : badge('draft') ?> <span class="badge"><?= $t['kind'] === 'carnet' ? (int)$t['entries'] . ' entrées' : 'Saison' ?></span></span><span class="muted" style="font-weight:500"><?= e(money((int)$t['price'])) ?> · <?= (int)$t['sold'] ?><?= $t['quota'] ? ' / ' . (int)$t['quota'] : '' ?> abonnés · <?= e(money((int)$t['revenue'])) ?></span></summary><?= $form($t) ?></details>
<?php endforeach; ?>
<details class="box add" <?= !$types ? 'open' : '' ?>><summary>+ Nouvel abonnement</summary><?= $form(null) ?></details>
<div class="card table-card mt"><div class="card-head"><h2>Abonnés</h2><span class="grow"></span><a class="btn btn-sm btn-light" target="_blank" href="<?= url('/pro/abonnements/cartes.pdf') ?>">Cartes membres (imprimante PVC)</a> <a class="btn btn-sm btn-light" target="_blank" href="<?= url('/pro/abonnements/cartes.pdf', ['format' => 'a4']) ?>">Planche A4</a></div>
  <div class="table-wrap"><table class="table"><thead><tr><th>Abonné</th><th>Abonnement</th><th class="num">Places réservées</th><th>Statut</th><th></th></tr></thead><tbody>
  <?php foreach ($passes as $p): ?><tr><td><b><?= e(trim($p['first_name'] . ' ' . $p['last_name'])) ?></b><br><small class="muted"><?= e((string)$p['email']) ?> · <?= e($p['code']) ?></small></td><td><?= e($p['type_name']) ?><?= $p['uses_left'] !== null ? '<br><small class="muted">' . (int)$p['uses_left'] . ' entrée(s) restante(s)</small>' : '' ?></td>
    <td class="num"><?= (int)$p['booked'] ?></td><td><?= $p['status'] === 'active' ? '<span class="badge green">Actif</span>' : '<span class="badge grey">Annulé</span>' ?></td>
    <td class="right nowrap"><a class="btn btn-sm btn-light" href="<?= url('/pass/' . $p['token']) ?>" target="_blank">Son espace</a><?php if ($p['status'] === 'active'): ?> <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="pass_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="cancel_pass" data-confirm="Annuler cet abonnement ?">Annuler</button></form><?php endif; ?></td></tr><?php endforeach; ?>
  <?php if (!$passes): ?><tr><td colspan="5" class="empty">Pas encore d'abonné.</td></tr><?php endif; ?></tbody></table></div></div>
