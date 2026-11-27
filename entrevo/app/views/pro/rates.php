<?php
$form = function (?array $r) use ($org, $sessions, $seatCats, $rates, $groups) {
    $p = $r ?? ['id' => 0, 'name' => '', 'description' => '', 'price' => 0, 'quota' => 0, 'max_per_order' => 0, 'sale_start' => null, 'sale_end' => null, 'hidden' => 0, 'active' => 1, 'sort' => 0,
        'price_mode' => 'fixed', 'pack_size' => 1, 'max_entries' => 1, 'per_day' => 0, 'session_ids' => '', 'seat_category' => '', 'scan_note' => '', 'scan_color' => '', 'vat_rate' => null, 'after_rate_id' => null, 'min_age' => null, 'dyn_json' => null, 'companion' => 0,
        'visible_from' => null, 'visible_until' => null, 'soldout_mode' => 'show', 'show_left' => 0, 'badge' => null, 'min_per_order' => 0, 'proof' => null, 'quota_group_id' => null, 'session_prices' => null];
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
      <?php $sp = RatesPlus::sessionPrices($p); $isStd = $p['badge'] === null || array_key_exists((string)$p['badge'], RatesPlus::BADGES); ?>
      <details class="vis-box" <?= $p['visible_from'] || $p['visible_until'] || $p['quota_group_id'] || $sp || $p['badge'] || $p['proof'] || (int)$p['show_left'] || $p['soldout_mode'] === 'hide' || (int)$p['min_per_order'] ? 'open' : '' ?>>
        <summary><b>Visibilité, affichage et contingent</b> <small class="muted">quand le tarif apparaît, comment il se présente, avec qui il partage ses places</small></summary>
        <div class="grid4 mt">
          <div class="field"><label>Visible à partir du</label><input type="datetime-local" name="visible_from" value="<?= e(dt_input($p['visible_from'])) ?>"><small>Vide = tout de suite</small></div>
          <div class="field"><label>Visible jusqu'au</label><input type="datetime-local" name="visible_until" value="<?= e(dt_input($p['visible_until'])) ?>"><small>Ensuite il disparaît de la page</small></div>
          <div class="field"><label>Quand il est complet</label><select name="soldout_mode"><option value="show">Afficher « Complet »</option><option value="hide" <?= $p['soldout_mode'] === 'hide' ? 'selected' : '' ?>>Le masquer</option></select></div>
          <div class="field"><label>Afficher « plus que X places »</label><input type="number" name="show_left" min="0" value="<?= (int)$p['show_left'] ?>"><small>Sous ce seuil (0 = jamais)</small></div>
        </div>
        <div class="grid4">
          <div class="field"><label>Mise en avant</label><select name="badge"><?php foreach (RatesPlus::BADGES as $k => $l): ?><option value="<?= e($k) ?>" <?= (string)$p['badge'] === $k && $isStd ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><input name="badge_custom" class="mt" style="margin-top:6px" value="<?= $isStd ? '' : e((string)$p['badge']) ?>" placeholder="ou ton texte (ex. Fosse)"></div>
          <div class="field"><label>Justificatif à présenter</label><input name="proof" value="<?= e((string)$p['proof']) ?>" placeholder="Carte étudiant, carte d'invalidité…"><small>Affiché à l'achat et rappelé au contrôle</small></div>
          <div class="field"><label>Minimum par commande</label><input type="number" name="min_per_order" min="0" max="50" value="<?= (int)$p['min_per_order'] ?>"><small>Ex. 2 pour un tarif « duo »</small></div>
          <div class="field"><label>Contingent partagé</label><select name="quota_group_id"><option value="">Aucun</option><?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>" <?= (int)$p['quota_group_id'] === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?> (<?= (int)$g['quota'] ?: '∞' ?>)</option><?php endforeach; ?></select><small><a href="#contingents">Gérer les contingents</a></small></div>
        </div>
        <?php if ($sessions): ?><div class="field"><label>Prix selon la date (laisser vide = prix du tarif)</label><div class="sp-grid">
          <?php foreach ($sessions as $x): ?><label><span><?= e(short_date($x['starts_at'])) ?><?= $x['label'] ? ' · ' . e($x['label']) : '' ?></span><span class="input-suffix"><input name="session_price[<?= (int)$x['id'] ?>]" inputmode="decimal" value="<?= isset($sp[(string)$x['id']]) ? e(money_input((int)$sp[(string)$x['id']])) : '' ?>" placeholder="<?= e(money_input((int)$p['price'])) ?>"><span>€</span></span></label><?php endforeach; ?></div>
          <small class="muted">Ex. le samedi plus cher que la semaine, ou la première plus chère.</small></div><?php endif; ?>
      </details>
      <?php $others = array_filter($rates, fn($x) => (int)$x['id'] !== (int)$p['id']); if ($others): ?>
      <div class="field tier-field"><label>Paliers (early bird)</label>
        <select name="after_rate_id"><option value="">Ouvert selon ses dates de vente</option><?php foreach ($others as $x): ?><option value="<?= (int)$x['id'] ?>" <?= (int)($p['after_rate_id'] ?? 0) === (int)$x['id'] ? 'selected' : '' ?>>S'ouvre quand « <?= e($x['name']) ?> » (<?= e(money((int)$x['price'])) ?>) est épuisé ou terminé</option><?php endforeach; ?></select>
        <small>Ex. : « Early bird » 100 places à 15 €, puis « Normal » à 20 € qui s'ouvre tout seul. Le palier suivant s'affiche grisé en attendant, et un compte à rebours apparaît la dernière semaine si le tarif a une date de fin.</small></div>
      <?php endif; ?>
      <?php $dc = DynPrice::config($p); $ds = $dc ? $dc['steps'] : [['limit' => 100, 'price' => (int)$p['price']], ['limit' => 300, 'price' => (int)round((int)$p['price'] * 1.2)], ['limit' => null, 'price' => (int)round((int)$p['price'] * 1.4)]];
        $hist = !empty($p['id']) ? all('SELECT * FROM rate_prices WHERE rate_id = ? ORDER BY id DESC LIMIT 8', [$p['id']]) : []; ?>
      <details class="dyn-box"<?= $dc ? ' open' : '' ?>><summary><b>Prix dynamique</b> <small class="muted">le prix monte tout seul par paliers<?= $dc ? ' · activé' : '' ?></small></summary>
        <label class="check mt"><input type="checkbox" name="dyn_on" value="1" <?= $dc ? 'checked' : '' ?>> <span>Activer les paliers pour ce tarif (le prix saisi plus haut est remplacé par celui du palier en cours)</span></label>
        <div class="field mt"><label>Le palier change selon</label><div class="seg"><label class="seg-r"><input type="radio" name="dyn_mode" value="sold" <?= ($dc['mode'] ?? 'sold') === 'sold' ? 'checked' : '' ?>><span>les places vendues</span></label><label class="seg-r"><input type="radio" name="dyn_mode" value="date" <?= ($dc['mode'] ?? '') === 'date' ? 'checked' : '' ?>><span>la date</span></label></div></div>
        <table class="table table-sm dyn-steps"><thead><tr><th>Palier</th><th>Jusqu'à (places vendues ou date)</th><th>Prix</th></tr></thead><tbody>
          <?php for ($k = 0; $k < 5; $k++): $st = $ds[$k] ?? null; $isDate = ($dc['mode'] ?? 'sold') === 'date'; ?><tr><td><?= $k + 1 ?></td>
            <td><input name="dyn_limit[<?= $k ?>]" value="<?= $st && $st['limit'] !== null ? e($isDate ? dt_input((string)$st['limit']) : (string)$st['limit']) : '' ?>" placeholder="<?= $k ? 'ex. 300 ou 2027-03-01 00:00' : 'ex. 100' ?>"></td>
            <td><div class="input-suffix"><input name="dyn_price[<?= $k ?>]" inputmode="decimal" value="<?= $st ? e(money_input((int)$st['price'])) : '' ?>"><span>€</span></div></td></tr><?php endfor; ?>
        </tbody></table>
        <small class="muted">Le dernier palier rempli n'a pas de limite (« ensuite »). Selon les ventes : nombre total de places vendues pour ce tarif (paniers en cours compris). Selon la date : AAAA-MM-JJ HH:MM. La page de l'événement affiche « Plus que X places à ce prix » ou la date du prochain prix, et les commandes en cours gardent leur prix.</small>
        <?php if ($hist): ?><div class="mt"><b>Historique</b><?php foreach ($hist as $hx): ?><div class="list-item"><span class="grow"><?= e(fr_date($hx['created_at'])) ?> · <?= e((string)$hx['reason']) ?></span><b><?= e(money((int)$hx['old_price'])) ?> → <?= e(money((int)$hx['price'])) ?></b></div><?php endforeach; ?></div><?php endif; ?>
      </details>
      <div class="row" style="gap:22px;margin-bottom:14px">
        <label class="check"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> Actif (en vente)</label>
        <label class="check"><input type="checkbox" name="hidden" value="1" <?= $p['hidden'] ? 'checked' : '' ?>> Tarif secret (visible seulement avec un code d'accès)</label>
        <label class="check"><input type="checkbox" name="companion" value="1" <?= !empty($p['companion']) ? 'checked' : '' ?>> Tarif PMR : un billet accompagnateur gratuit est ajouté à chaque billet</label>
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
            <?php if (!empty($r['companion'])): ?><span>PMR + accompagnateur</span><?php endif; ?>
            <?php if ($r['badge']): ?><span class="tag-hl"><?= e($r['badge']) ?></span><?php endif; ?>
            <?php if ($r['quota_group_id']): $gg = array_values(array_filter($groups, fn($g) => (int)$g['id'] === (int)$r['quota_group_id'])); ?><span>Contingent « <?= e($gg[0]['name'] ?? '?') ?> »</span><?php endif; ?>
            <?php if ($r['proof']): ?><span>Justificatif</span><?php endif; ?>
            <?php if ((int)$r['min_per_order'] > 1): ?><span>Min. <?= (int)$r['min_per_order'] ?></span><?php endif; ?>
            <?php if ($r['session_prices']): ?><span>Prix selon la date</span><?php endif; ?>
            <?php if ($r['visible_from'] && strtotime($r['visible_from']) > time()): ?><span>Visible le <?= e(short_date($r['visible_from'])) ?></span><?php elseif ($r['visible_until'] && strtotime($r['visible_until']) < time()): ?><span>Masqué</span><?php endif; ?>
            <?php if ($r['soldout_mode'] === 'hide'): ?><span>Masqué si complet</span><?php endif; ?>
            <?php if ($dst = DynPrice::state($r)): ?><span>Prix dynamique · palier <?= $dst['index'] + 1 ?>/<?= $dst['count'] ?><?= $dst['next'] !== null ? ' · ' . ($dst['mode'] === 'sold' ? $dst['left'] . ' pl. avant ' . money($dst['next']) : money($dst['next']) . ' le ' . short_date((string)$dst['until'])) : '' ?></span><?php endif; ?>
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
<?php $launch = $ev['on_sale_at'] ?? null; $pending = RatesPlus::launchPending($ev); ?>
<div class="rates-tools">
<form method="post" class="card stack" id="lancement"><?= csrf_field() ?><input type="hidden" name="action" value="launch">
  <div class="card-head" style="padding:0"><h2>Lancement des ventes</h2><?= $pending ? '<span class="badge orange">Programmé</span>' : ($launch ? '<span class="badge green">Ouvert</span>' : '') ?></div>
  <p class="muted mb0"><small>Annonce ton événement avant d'ouvrir la billetterie : la page est en ligne avec un <b>compte à rebours</b> et un bouton « Préviens-moi ». À l'heure dite, tous les tarifs s'ouvrent en même temps et les inscrits reçoivent un e-mail.</small></p>
  <div class="grid2"><div class="field"><label>Ouverture des ventes</label><input type="datetime-local" name="on_sale_at" value="<?= e(dt_input($launch)) ?>"></div>
    <div class="field"><label>Inscrits « préviens-moi »</label><b style="font-size:26px"><?= (int)$alerts ?></b></div></div>
  <label class="check"><input type="checkbox" name="queue_enabled" value="1" <?= (int)$ev['queue_enabled'] ? 'checked' : '' ?>> File d'attente virtuelle à l'ouverture (recommandé au-delà de 500 personnes attendues)</label>
  <div class="row"><button class="btn btn-sm">Enregistrer</button><?php if ($launch): ?><button class="btn btn-sm btn-ghost" name="on_sale_at" value="">Supprimer la programmation</button><?php endif; ?></div>
</form>
<div class="card stack" id="contingents"><h2>Contingents partagés</h2>
  <p class="muted mb0"><small>Plusieurs tarifs puisent dans le même stock de places : « Fosse » pour Plein + Réduit + Étudiant, « Carré or », « Balcon »… Le premier qui vend prend la place.</small></p>
  <?php foreach ($groups as $g): $gp = (int)$g['quota'] ? min(100, round($g['used'] / max(1, (int)$g['quota']) * 100)) : null; ?>
    <form method="post" class="cg-row"><?= csrf_field() ?><input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
      <input name="group_name" value="<?= e($g['name']) ?>"><input type="number" name="group_quota" min="0" value="<?= (int)$g['quota'] ?>" title="Places (0 = illimité)">
      <span class="cg-use"><b><?= (int)$g['used'] ?></b><?= (int)$g['quota'] ? ' / ' . (int)$g['quota'] : '' ?> · <?= (int)$g['n'] ?> tarif(s)<?php if ($gp !== null): ?><i class="bar-thin"><i style="width:<?= $gp ?>%" class="<?= $gp >= 90 ? 'hot' : '' ?>"></i></i><?php endif; ?></span>
      <button class="btn btn-sm btn-light" name="action" value="group_save">OK</button><button class="btn btn-sm btn-ghost" name="action" value="group_delete" data-confirm="Supprimer ce contingent ?">×</button></form>
  <?php endforeach; ?>
  <form method="post" class="cg-row"><?= csrf_field() ?><input name="group_name" placeholder="Nouveau contingent (ex. Fosse)"><input type="number" name="group_quota" min="0" placeholder="Places"><span></span><button class="btn btn-sm" name="action" value="group_save">Créer</button></form>
</div>
</div>
<?php $gc = $grid['cats']; $gt = $grid['types'] ?: ['Plein' => '', 'Réduit' => 'Étudiant, demandeur d\'emploi', 'Abonné' => '']; $gcN = array_keys($gc); $gtN = array_keys($gt); ?>
<form method="post" class="card" id="grille"><?= csrf_field() ?><input type="hidden" name="action" value="grid">
  <div class="card-head"><h2>Grille catégories × tarifs</h2><small class="muted">pour les salles de spectacle</small></div>
  <p class="muted"><small>Comme dans une billetterie de théâtre : une ligne par catégorie de places (avec son contingent), une colonne par type de tarif. Chaque case remplie crée ou met à jour le tarif « Catégorie · Type ». Laisse une case vide pour ne pas proposer la combinaison. Les catégories qui portent le même nom que celles du plan de salle y sont reliées.</small></p>
  <div class="table-wrap"><table class="table grid-table"><thead><tr><th>Catégorie</th><th>Places</th>
    <?php for ($t = 0; $t < 5; $t++): ?><th><input name="gtype[<?= $t ?>]" value="<?= e($gtN[$t] ?? '') ?>" placeholder="Type <?= $t + 1 ?>"><input name="gproof[<?= $t ?>]" value="<?= e(isset($gtN[$t]) ? (string)$gt[$gtN[$t]] : '') ?>" placeholder="justificatif" class="gproof"></th><?php endfor; ?></tr></thead><tbody>
    <?php for ($c = 0; $c < 5; $c++): $cn = $gcN[$c] ?? ''; ?><tr><td><input name="gcat[<?= $c ?>]" value="<?= e($cn) ?>" placeholder="<?= ['Ex. Orchestre', 'Ex. Balcon', 'Ex. Corbeille', 'Catégorie 4', 'Catégorie 5'][$c] ?>"></td><td><input type="number" name="gquota[<?= $c ?>]" min="0" value="<?= $cn !== '' ? (int)$gc[$cn] : '' ?>" style="width:90px"></td>
      <?php for ($t = 0; $t < 5; $t++): $tn = $gtN[$t] ?? ''; $v = $cn !== '' && $tn !== '' && isset($grid['cells'][$cn][$tn]) ? money_input((int)$grid['cells'][$cn][$tn]) : ''; ?><td><span class="input-suffix"><input name="gprice[<?= $c ?>][<?= $t ?>]" inputmode="decimal" value="<?= e($v) ?>"><span>€</span></span></td><?php endfor; ?></tr><?php endfor; ?>
  </tbody></table></div>
  <div class="form-actions"><button class="btn">Appliquer la grille</button></div>
</form>
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
