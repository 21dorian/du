<?php
$form = function (?array $r) use ($org, $sessions, $seatCats, $rates) {
    $p = $r ?? ['id' => 0, 'name' => '', 'description' => '', 'price' => 0, 'quota' => 0, 'max_per_order' => 0, 'sale_start' => null, 'sale_end' => null, 'hidden' => 0, 'active' => 1, 'sort' => 0,
        'price_mode' => 'fixed', 'pack_size' => 1, 'max_entries' => 1, 'per_day' => 0, 'session_ids' => '', 'seat_category' => '', 'scan_note' => '', 'scan_color' => '', 'vat_rate' => null, 'after_rate_id' => null, 'min_age' => null];
    $selS = array_map('intval', array_filter(explode(',', (string)$p['session_ids'])));
    ob_start(); ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="rate_id" value="<?= (int)$p['id'] ?>">
      <div class="grid3">
        <div class="field" style="grid-column:span 2"><label>Nom du tarif *</label><input name="name" value="<?= e($p['name']) ?>" required placeholder="Plein tarif, Réduit, VIP…"></div>
        <div class="field"><label>Prix <?= '' ?></label><div class="input-suffix"><input name="price" value="<?= e(money_input((int)$p['price'])) ?>" inputmode="decimal"><span>€</span></div><small>Prix libre : c'est le minimum.</small></div>
      </div>
      <div class="grid4">
        <div class="field"><label>Type de prix</label><select name="price_mode"><option value="fixed" <?= $p['price_mode'] !== 'free' ? 'selected' : '' ?>>Prix fixe</option><option value="free" <?= $p['price_mode'] === 'free' ? 'selected' : '' ?>>Prix libre</option></select></div>
        <div class="field"><label>Billets par achat (pack)</label><input type="number" name="pack_size" min="1" max="50" value="<?= (int)$p['pack_size'] ?>"><small>4 = « pack famille » à 4 billets.</small></div>
        <div class="field"><label>Entrées autorisées</label><input type="number" name="max_entries" min="1" max="100" value="<?= (int)$p['max_entries'] ?>"><small>3 = pass 3 entrées.</small></div>
        <div class="field"><label>&nbsp;</label><label class="check"><input type="checkbox" name="per_day" value="1" <?= $p['per_day'] ? 'checked' : '' ?>> 1 entrée par jour (pass multi-jours)</label></div>
      </div>
      <?php if ($sessions): ?><div class="field"><label>Valable pour les dates</label><div class="row" style="gap:6px 16px">
        <?php foreach ($sessions as $x): ?><label class="check" style="font-weight:400"><input type="checkbox" name="session_ids[]" value="<?= (int)$x['id'] ?>" <?= in_array((int)$x['id'], $selS, true) ? 'checked' : '' ?>> <?= e(short_date($x['starts_at'])) ?></label><?php endforeach; ?></div><small>Aucune cochée = toutes les dates.</small></div><?php endif; ?>
      <?php if ($seatCats): ?><div class="field"><label>Catégorie de place (plan de salle)</label><select name="seat_category"><option value="">Toutes les places</option><?php foreach ($seatCats as $c): ?><option value="<?= e($c['id']) ?>" <?= $p['seat_category'] === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
      <div class="field"><label>Description</label><input name="description" value="<?= e($p['description']) ?>" placeholder="Ex. : sur présentation d'un justificatif"></div>
      <div class="field" style="max-width:320px"><label>Âge minimum pour ce tarif</label><select name="min_age"><?php foreach (['' => 'Comme l\'événement', '12' => '12 ans et +', '16' => '16 ans et +', '18' => '18 ans et +', '21' => '21 ans et +'] as $k => $l): ?><option value="<?= $k ?>" <?= (string)($p['min_age'] ?? '') === (string)$k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="field" style="max-width:320px"><label>TVA de ce tarif</label><select name="vat_rate"><?php foreach (Vat::RATES as $k => $l): ?><option value="<?= e((string)$k) ?>" <?= ($p['vat_rate'] === null ? $k === '' : ($k !== '' && (float)$p['vat_rate'] === (float)$k)) ? 'selected' : '' ?>><?= e($l) ?><?= $k === '' ? ' (' . e(Vat::label($org['vat_rate'] !== null ? (float)$org['vat_rate'] : null)) . ')' : '' ?></option><?php endforeach; ?></select></div>
      <div class="grid3 scan-rule">
        <div class="field" style="grid-column:span 2"><label>Consigne affichée au contrôle</label><input name="scan_note" value="<?= e((string)($p['scan_note'] ?? '')) ?>" maxlength="160" placeholder="Ex. : Vérifier la carte étudiant · Remettre le bracelet VIP"><small>S'affiche en grand sur l'appli de scan quand ce billet passe.</small></div>
        <div class="field"><label>Couleur de bracelet</label><div class="color-pick"><?php foreach (['' => 'Aucune', '#e5372b' => 'Rouge', '#f08c00' => 'Orange', '#f5c400' => 'Jaune', '#1f9d55' => 'Vert', '#0032fa' => 'Bleu', '#7c3aed' => 'Violet', '#0f0f14' => 'Noir'] as $hex => $nm): ?>
          <label title="<?= $nm ?>"><input type="radio" name="scan_color" value="<?= $hex ?>" <?= (string)($p['scan_color'] ?? '') === $hex ? 'checked' : '' ?>><span style="<?= $hex ? 'background:' . $hex : '' ?>" class="<?= $hex ? '' : 'none' ?>" aria-label="<?= $nm ?>"></span></label><?php endforeach; ?></div><small>Le scanner affiche un bandeau de cette couleur.</small></div>
      </div>
      <div class="grid4">
        <div class="field"><label>Quota</label><input type="number" name="quota" min="0" value="<?= (int)$p['quota'] ?>"><small>0 = illimité</small></div>
        <div class="field"><label>Max. par commande</label><input type="number" name="max_per_order" min="0" value="<?= (int)$p['max_per_order'] ?>"><small>0 = selon l'événement</small></div>
        <div class="field"><label>Début des ventes</label><input type="datetime-local" name="sale_start" value="<?= e(dt_input($p['sale_start'])) ?>"></div>
        <div class="field"><label>Fin des ventes</label><input type="datetime-local" name="sale_end" value="<?= e(dt_input($p['sale_end'])) ?>"></div>
      </div>
      <?php $others = array_filter($rates, fn($x) => (int)$x['id'] !== (int)$p['id']); if ($others): ?>
      <div class="field tier-field"><label>Paliers (early bird)</label>
        <select name="after_rate_id"><option value="">Ouvert selon ses dates de vente</option><?php foreach ($others as $x): ?><option value="<?= (int)$x['id'] ?>" <?= (int)($p['after_rate_id'] ?? 0) === (int)$x['id'] ? 'selected' : '' ?>>S'ouvre quand « <?= e($x['name']) ?> » (<?= e(money((int)$x['price'])) ?>) est épuisé ou terminé</option><?php endforeach; ?></select>
        <small>Ex. : « Early bird » 100 places à 15 €, puis « Normal » à 20 € qui s'ouvre tout seul. Le palier suivant s'affiche grisé en attendant, et un compte à rebours apparaît la dernière semaine si le tarif a une date de fin.</small></div>
      <?php endif; ?>
      <div class="row" style="gap:22px;margin-bottom:14px">
        <label class="check"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> Actif (en vente)</label>
        <label class="check"><input type="checkbox" name="hidden" value="1" <?= $p['hidden'] ? 'checked' : '' ?>> Tarif secret (visible seulement avec un code d'accès)</label>
        <label style="display:flex;gap:8px;align-items:center;font-weight:500">Ordre <input type="number" name="sort" value="<?= (int)$p['sort'] ?>" style="width:80px"></label>
      </div>
      <div class="form-actions">
        <button class="btn"><?= $p['id'] ? 'Enregistrer' : 'Ajouter le tarif' ?></button>
        <?php if ($p['id']): ?><button class="btn btn-ghost" style="color:var(--red)" name="action" value="delete" formnovalidate data-confirm="Supprimer ce tarif ?">Supprimer</button><?php endif; ?>
      </div>
    </form>
<?php return ob_get_clean(); };
[$pct, $fixed] = Tickets::feeRates($org);
$editId = (string)input('edit');
$editing = $editId === 'new' ? null : (array_values(array_filter($rates, fn($r) => (string)$r['id'] === $editId))[0] ?? false);
$tot = ['sold' => array_sum(array_column($rates, 'sold')), 'revenue' => array_sum(array_column($rates, 'revenue')), 'sold7' => array_sum(array_column($rates, 'sold7'))];
$quotaSum = array_sum(array_map(fn($r) => (int)$r['quota'] * max(1, (int)$r['pack_size']), array_filter($rates, fn($r) => $r['active'])));
$unlimited = (bool)array_filter($rates, fn($r) => $r['active'] && !(int)$r['quota']);
$cap = (int)$ev['capacity'];
$state = function (array $r): array {
    $s = $r['state'];
    if (!$r['active']) return ['En pause', 'grey'];
    if ($s['ok']) return [$r['hidden'] ? 'En vente · secret' : 'En vente', 'green'];
    $w = (string)($s['why'] ?? '');
    if (!empty($s['next'])) return ['Palier suivant', 'blue'];
    if ($w === 'Complet') return ['Complet', 'red'];
    if (str_starts_with($w, 'En vente le')) return ['Programmé', 'orange'];
    if ($w === 'Vente terminée') return ['Terminé', 'grey'];
    return [$w ?: 'Fermé', 'grey'];
};
$base = '/pro/evenements/' . $ev['id'] . '/tarifs';
?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Billets vendus</span><b><?= number_format($tot['sold'], 0, ',', ' ') ?></b><small class="muted"><?= $tot['sold7'] ?> ces 7 derniers jours</small></div>
  <div class="kpi-mini"><span>Recettes billets</span><b><?= e(money((int)$tot['revenue'])) ?></b><small class="muted">hors frais de service</small></div>
  <div class="kpi-mini"><span>Prix moyen</span><b><?= $tot['sold'] ? e(money((int)round($tot['revenue'] / $tot['sold']))) : '—' ?></b></div>
  <div class="kpi-mini"><span>Places mises en vente</span><b><?= $unlimited ? 'Illimité' : number_format($quotaSum, 0, ',', ' ') ?></b><small class="muted"><?= $cap ? 'jauge ' . number_format($cap, 0, ',', ' ') : 'pas de jauge' ?></small></div>
</div>
<?php if ($cap && !$unlimited && $quotaSum < $cap): ?><div class="notice info mb">Tes tarifs actifs ne couvrent que <b><?= $quotaSum ?></b> places sur une jauge de <b><?= $cap ?></b> : <?= $cap - $quotaSum ?> places ne pourront pas être vendues. Augmente un quota ou ajoute un tarif.</div><?php endif; ?>

<?php if ($editing !== false && ($editing || $editId === 'new')): ?>
<div class="card" id="editeur">
  <div class="card-head"><h2><?= $editing ? 'Modifier « ' . e($editing['name']) . ' »' : 'Nouveau tarif' ?></h2><a class="btn btn-sm btn-ghost" href="<?= url($base) ?>">Fermer</a></div>
  <?= $form($editing ?: null) ?>
</div>
<?php endif; ?>

<div class="card table-card">
  <div class="card-head"><h2>Tarifs <small class="muted">(<?= count($rates) ?>)</small></h2><div class="row"><small class="muted rate-hint">Glisse les lignes pour changer l'ordre d'affichage</small><a class="btn btn-sm" href="<?= url($base, ['edit' => 'new']) ?>#editeur">+ Ajouter un tarif</a></div></div>
  <div class="table-wrap"><table class="table rate-table">
    <thead><tr><th></th><th>Tarif</th><th>Statut</th><th class="num">Prix</th><th style="width:22%">Ventes</th><th class="num">Recettes</th><th></th></tr></thead>
    <tbody data-reorder="<?= url($base) ?>">
    <?php foreach ($rates as $r): [$sl, $sc] = $state($r); $fee = Tickets::computeFees($org, (int)$r['price'], (int)$r['price'] > 0 ? 1 : 0);
      $q = (int)$r['quota']; $p = $q ? min(100, round($r['sold'] / max(1, $q * max(1, (int)$r['pack_size'])) * 100)) : null; ?>
      <tr draggable="true" data-id="<?= (int)$r['id'] ?>" class="<?= $r['active'] ? '' : 'is-paused' ?>">
        <td class="drag" title="Glisser pour réordonner">⋮⋮</td>
        <td><a href="<?= url($base, ['edit' => $r['id']]) ?>#editeur"><b><?= e($r['name']) ?></b></a>
          <div class="rate-tags">
            <?php if ($r['price_mode'] === 'free'): ?><span>Prix libre</span><?php endif; ?>
            <?php if ((int)$r['pack_size'] > 1): ?><span>Pack de <?= (int)$r['pack_size'] ?></span><?php endif; ?>
            <?php if ((int)$r['max_entries'] > 1): ?><span><?= (int)$r['max_entries'] ?> entrées</span><?php endif; ?>
            <?php if ($r['hidden']): ?><span>Secret</span><?php endif; ?>
            <?php if ($r['min_age']): ?><span><?= (int)$r['min_age'] ?> ans et +</span><?php endif; ?>
            <?php if ($r['session_ids']): ?><span><?= count(Tickets::rateSessions($r)) ?> date(s)</span><?php endif; ?>
            <?php if ($r['after_rate_id']): $pv = array_values(array_filter($rates, fn($x) => (int)$x['id'] === (int)$r['after_rate_id'])); ?><span>après « <?= e($pv[0]['name'] ?? '?') ?> »</span><?php endif; ?>
          </div>
          <?php if ($r['sale_start'] || $r['sale_end']): ?><small class="muted"><?= $r['sale_start'] ? 'du ' . e(short_date($r['sale_start'])) : '' ?><?= $r['sale_end'] ? ' jusqu\'au ' . e(short_date($r['sale_end'])) : '' ?></small><?php endif; ?>
        </td>
        <td><span class="badge <?= $sc ?>"><?= e($sl) ?></span><?php if ($sl === 'Programmé' || $sl === 'Palier suivant'): ?><br><small class="muted"><?= e((string)$r['state']['why']) ?></small><?php endif; ?></td>
        <td class="num nowrap"><b><?= (int)$r['price'] ? e(money((int)$r['price'])) : 'Gratuit' ?></b><?php if ($fee): ?><br><small class="muted"><?= $org['fee_mode'] === 'buyer' ? 'payé ' . e(money((int)$r['price'] + $fee)) : 'reçu ' . e(money((int)$r['price'] - $fee)) ?></small><?php endif; ?></td>
        <td><div class="rate-sold"><b><?= (int)$r['sold'] ?></b><span class="muted"><?= $q ? ' / ' . $q * max(1, (int)$r['pack_size']) : '' ?></span><?php if ((int)$r['sold7']): ?><small class="up">+<?= (int)$r['sold7'] ?> en 7 j</small><?php endif; ?></div>
          <?php if ($p !== null): ?><div class="bar-thin"><i style="width:<?= $p ?>%" class="<?= $p >= 90 ? 'hot' : '' ?>"></i></div><?php endif; ?>
          <?php if ((int)$r['sold']): ?><small class="muted"><?= (int)$r['inside'] ?> entré<?= (int)$r['inside'] > 1 ? 's' : '' ?></small><?php endif; ?></td>
        <td class="num"><?= (int)$r['revenue'] ? e(money((int)$r['revenue'])) : '<span class="muted">—</span>' ?></td>
        <td class="right nowrap">
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="rate_id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm btn-light" name="action" value="toggle"><?= $r['active'] ? 'Mettre en pause' : 'Remettre en vente' ?></button>
            <button class="btn btn-sm btn-ghost" name="action" value="duplicate" title="Dupliquer">Dupliquer</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rates): ?><tr><td colspan="7" class="empty">Aucun tarif : ajoute-en un pour ouvrir la vente.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<p class="muted" style="font-size:13px">Frais de service : <b><?= e(rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',')) ?> % + <?= e(money($fixed)) ?></b> par billet payant,
  <?= $org['fee_mode'] === 'buyer' ? 'payés par l\'acheteur' : 'déduits de ton reversement' ?> (<a href="<?= url('/pro/organisation') ?>">changer</a>). Billets gratuits : 0 €.</p>
<script>
(() => {
  const tb = document.querySelector('[data-reorder]'); if (!tb) return;
  let drag = null;
  tb.addEventListener('dragstart', e => { drag = e.target.closest('tr'); drag.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
  tb.addEventListener('dragover', e => { e.preventDefault(); const tr = e.target.closest('tr'); if (!tr || tr === drag) return; const r = tr.getBoundingClientRect(); tb.insertBefore(drag, e.clientY > r.top + r.height / 2 ? tr.nextSibling : tr); });
  tb.addEventListener('dragend', () => {
    drag.classList.remove('dragging'); drag = null;
    const d = new FormData(); d.append('action', 'reorder'); d.append('ajax', '1'); d.append('_csrf', document.querySelector('[name=_csrf]').value);
    tb.querySelectorAll('tr[data-id]').forEach(tr => d.append('ids[]', tr.dataset.id));
    fetch(tb.dataset.reorder, { method: 'POST', body: d, credentials: 'same-origin' }).then(r => r.json()).then(() => { const h = document.querySelector('.rate-hint'); if (h) { h.textContent = 'Ordre enregistré'; setTimeout(() => h.textContent = 'Glisse les lignes pour changer l\'ordre d\'affichage', 2500); } }).catch(() => {});
  });
})();
</script>
