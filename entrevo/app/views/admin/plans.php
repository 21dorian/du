<?php /** @var array $plans, $global; ?array $edit; int $noPlan, $mrr */
$pctTxt = fn($v) => $v === null ? null : rtrim(rtrim(number_format((float)$v, 2, ',', ''), '0'), ',') . ' %';
$def = array_values(array_filter($plans, fn($p) => $p['is_default']))[0] ?? null;
?>
<p class="muted lead-sm">Une formule fixe la commission, un éventuel abonnement mensuel, le prix des SMS et les modules inclus. Un contrat négocié avec un organisateur passe toujours avant sa formule.</p>
<div class="page-head-row">
  <div class="kpi-mini"><span>Formules</span><b><?= count($plans) ?></b></div>
  <div class="kpi-mini"><span>Abonnements mensuels</span><b><?= e(money($mrr)) ?></b><small class="muted">revenu récurrent</small></div>
  <div class="kpi-mini"><span>Sans formule choisie</span><b><?= $noPlan ?></b><small class="muted"><?= $def ? '→ « ' . e($def['name']) . ' »' : '→ réglages globaux' ?></small></div>
  <div class="kpi-mini"><span>Commission globale</span><b><?= e($pctTxt($global[0])) ?> + <?= e(money($global[1])) ?></b><small class="muted"><a href="<?= url('/admin/reglages', ['tab' => 'fees']) ?>">réglages</a></small></div>
</div>

<?php if ($edit !== null): $p = $edit ?: ['id' => 0, 'name' => '', 'description' => '', 'fee_percent' => null, 'fee_fixed' => null, 'fee_cap' => null, 'monthly_price' => 0, 'sms_price' => null, 'modules' => null, 'public' => 1, 'active' => 1, 'sort' => 0];
  $mods = $p['modules'] === null || $p['modules'] === '' ? array_keys(Billing::MODULES) : explode(',', (string)$p['modules']); ?>
<form class="card" method="post" id="editeur"><?= csrf_field() ?><input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
  <div class="card-head"><h2><?= $p['id'] ? 'Modifier « ' . e($p['name']) . ' »' : 'Nouvelle formule' ?></h2><a class="btn btn-sm btn-ghost" href="<?= url('/admin/formules') ?>">Fermer</a></div>
  <div class="grid2">
    <div class="field"><label>Nom</label><input name="name" required maxlength="120" value="<?= e($p['name']) ?>" placeholder="Découverte, Pro, Festival…"></div>
    <div class="field"><label>Abonnement mensuel</label><div class="input-suffix"><input name="monthly_price" inputmode="decimal" value="<?= e(money_input((int)$p['monthly_price'])) ?>"><span>€ / mois</span></div><small>0 = sans abonnement. Retenu chaque mois sur le reversement.</small></div>
  </div>
  <div class="field"><label>Description <small class="muted">(montrée aux organisateurs)</small></label><input name="description" maxlength="500" value="<?= e((string)$p['description']) ?>"></div>
  <h3 class="mt">Commission sur les billets</h3>
  <p class="muted u-fs13">Laisse vide pour reprendre le réglage global (<?= e($pctTxt($global[0])) ?> + <?= e(money($global[1])) ?>).</p>
  <div class="grid4">
    <div class="field"><label>Pourcentage</label><div class="input-suffix"><input name="fee_percent" inputmode="decimal" value="<?= $p['fee_percent'] === null ? '' : e(str_replace('.', ',', (string)(float)$p['fee_percent'])) ?>"><span>%</span></div></div>
    <div class="field"><label>Fixe par billet</label><div class="input-suffix"><input name="fee_fixed" inputmode="decimal" value="<?= $p['fee_fixed'] === null ? '' : e(money_input((int)$p['fee_fixed'])) ?>"><span>€</span></div></div>
    <div class="field"><label>Plafond par billet</label><div class="input-suffix"><input name="fee_cap" inputmode="decimal" value="<?= $p['fee_cap'] === null ? '' : e(money_input((int)$p['fee_cap'])) ?>"><span>€</span></div><small>Utile pour les billets chers.</small></div>
    <div class="field"><label>Prix par SMS</label><div class="input-suffix"><input name="sms_price" inputmode="decimal" value="<?= $p['sms_price'] === null ? '' : e(money_input((int)$p['sms_price'])) ?>"><span>€</span></div></div>
  </div>
  <h3 class="mt">Modules inclus</h3>
  <div class="mod-grid"><?php foreach (Billing::MODULES as $k => $l): ?><label class="check"><input type="checkbox" name="modules[]" value="<?= e($k) ?>" <?= in_array($k, $mods, true) ? 'checked' : '' ?>> <span><?= e($l) ?></span></label><?php endforeach; ?></div>
  <p class="muted u-fs13">Billetterie, tarifs, codes promo, participants, contrôle d'accès et statistiques sont toujours inclus. Un module non coché affiche à l'organisateur une page « non inclus dans ta formule ».</p>
  <div class="row mt" style="gap:22px">
    <label class="check"><input type="checkbox" name="public" value="1" <?= $p['public'] ? 'checked' : '' ?>> <span>Proposée publiquement</span></label>
    <label class="check"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> <span>Active</span></label>
    <label class="inline-field">Ordre <input type="number" name="sort" value="<?= (int)$p['sort'] ?>" class="u-w80"></label>
  </div>
  <div class="form-actions mt"><button class="btn"><?= $p['id'] ? 'Enregistrer' : 'Créer la formule' ?></button>
    <?php if ($p['id']): ?><button class="btn btn-ghost u-red" name="action" value="delete" formnovalidate data-confirm="Supprimer cette formule ?">Supprimer</button><?php endif; ?></div>
</form>
<?php endif; ?>

<div class="card-head" style="margin:6px 0 12px"><h2>Formules</h2><a class="btn btn-sm" href="<?= url('/admin/formules', ['edit' => 'new']) ?>#editeur">+ Nouvelle formule</a></div>
<div class="plan-grid">
  <?php foreach ($plans as $p): $mods = $p['modules'] === null || $p['modules'] === '' ? null : explode(',', (string)$p['modules']); ?>
    <div class="plan-tile<?= $p['active'] ? '' : ' off' ?>">
      <div class="row between"><b class="plan-name"><?= e($p['name']) ?></b><span><?= $p['is_default'] ? '<span class="badge green">Par défaut</span>' : '' ?><?= !$p['active'] ? ' <span class="badge grey">Inactive</span>' : (!$p['public'] ? ' <span class="badge grey">Privée</span>' : '') ?></span></div>
      <div class="plan-price"><?= (int)$p['monthly_price'] ? e(money((int)$p['monthly_price'])) . '<small> / mois</small>' : 'Sans abonnement' ?></div>
      <?php if ($p['description']): ?><p class="muted"><?= e($p['description']) ?></p><?php endif; ?>
      <dl class="plan-dl">
        <dt>Commission</dt><dd><?= e($pctTxt($p['fee_percent'] ?? $global[0])) ?> + <?= e(money((int)($p['fee_fixed'] ?? $global[1]))) ?><?= $p['fee_cap'] !== null ? ' · max ' . e(money((int)$p['fee_cap'])) : '' ?></dd>
        <dt>SMS</dt><dd><?= e(money((int)($p['sms_price'] ?? Sms::price()))) ?></dd>
        <dt>Modules</dt><dd><?= $mods === null ? 'Tous' : count($mods) . ' / ' . count(Billing::MODULES) ?></dd>
        <dt>Organisateurs</dt><dd><?= (int)$p['orgs'] ?><?= $p['is_default'] && $noPlan ? ' + ' . $noPlan . ' par défaut' : '' ?></dd>
      </dl>
      <div class="row mt">
        <a class="btn btn-sm btn-light" href="<?= url('/admin/formules', ['edit' => $p['id']]) ?>#editeur">Modifier</a>
        <?php if (!$p['is_default'] && $p['active']): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="default">Mettre par défaut</button></form><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$plans): ?><div class="card empty">Pas encore de formule : tous les organisateurs ont la commission globale et tous les modules. Crée par exemple « Découverte » (gratuit, modules de base) et « Pro » (abonnement, tout inclus).</div><?php endif; ?>
</div>
