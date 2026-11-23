<?php
$rateName = array_column($rates, 'name', 'id');
$form = function (?array $a) use ($rates) {
    $p = $a ?? ['id' => 0, 'name' => '', 'rate_ids' => '', 'active' => 1];
    $sel = array_map('intval', array_filter(explode(',', (string)$p['rate_ids'])));
    ob_start(); ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="ap_id" value="<?= (int)$p['id'] ?>">
      <div class="field"><label>Nom</label><input name="name" value="<?= e($p['name']) ?>" required placeholder="Entrée principale, Porte B, Espace VIP…"></div>
      <div class="field"><label>Tarifs autorisés à ce point</label>
        <?php foreach ($rates as $r): ?><label class="check" style="margin-bottom:6px"><input type="checkbox" name="rate_ids[]" value="<?= (int)$r['id'] ?>" <?= in_array((int)$r['id'], $sel, true) ? 'checked' : '' ?>> <?= e($r['name']) ?></label><?php endforeach; ?>
        <small>Aucun coché = tous les tarifs. Exemple : ne cocher que « Pass VIP » pour l'espace VIP.</small></div>
      <label class="check" style="margin-bottom:14px"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> Ouvert</label>
      <div class="form-actions"><button class="btn"><?= $p['id'] ? 'Enregistrer' : 'Ajouter' ?></button>
      <?php if ($p['id']): ?><button class="btn btn-ghost" style="color:var(--red)" name="action" value="delete" formnovalidate data-confirm="Supprimer ce point d'accès ?">Supprimer</button><?php endif; ?></div>
    </form>
<?php return ob_get_clean(); };
?>
<div class="split-3">
  <div>
    <div class="card">
      <div class="card-head"><h2>Application de contrôle</h2><a class="btn" href="<?= url('/scan') ?>" target="_blank">Ouvrir le scanner</a></div>
      <p>Sur chaque téléphone de contrôle, ouvrez <b><?= e(abs_url('/scan')) ?></b>, connectez-vous avec un compte <b>Contrôleur</b> puis choisissez l'événement et le point d'accès.</p>
      <ul class="muted" style="padding-left:18px;margin:0">
        <li>Scan avec la caméra ou une douchette USB/Bluetooth, et saisie manuelle du code.</li>
        <li><b>Mode hors ligne</b> : la liste des billets est gardée sur le téléphone, les passages sont synchronisés au retour du réseau.</li>
        <li>Ajoutez l'appli à l'écran d'accueil du téléphone pour l'ouvrir comme une application.</li>
      </ul>
      <div class="row mt"><a class="btn btn-sm btn-light" href="<?= url('/pro/equipe') ?>">Créer des comptes contrôleurs</a>
        <span class="muted" style="font-size:14px"><?= count($scanners) ?> contrôleur<?= count($scanners) > 1 ? 's' : '' ?> actif<?= count($scanners) > 1 ? 's' : '' ?></span></div>
    </div>
    <h2 class="mt">Points d'accès</h2>
    <?php foreach ($aps as $a): $ids = array_filter(explode(',', (string)$a['rate_ids'])); ?>
      <details class="box"><summary><span><?= e($a['name']) ?> <?= $a['active'] ? '' : badge('closed') ?></span><span class="muted" style="font-weight:500"><?= $ids ? e(implode(', ', array_map(fn($i) => $rateName[$i] ?? '?', $ids))) : 'Tous les tarifs' ?> · <?= (int)$a['entries'] ?> entrées</span></summary><?= $form($a) ?></details>
    <?php endforeach; ?>
    <details class="box"><summary style="color:var(--primary)">+ Ajouter un point d'accès</summary><?= $form(null) ?></details>
  </div>
  <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reentry">
    <h2>Règles d'entrée</h2>
    <label class="check"><input type="checkbox" name="allow_reentry" value="1" <?= $ev['allow_reentry'] ? 'checked' : '' ?>> Autoriser les sorties et ré-entrées</label>
    <p class="muted" style="font-size:14px;margin-top:8px">Si activé, le contrôleur scanne en mode « Sortie » quand une personne sort ; elle pourra ensuite rentrer avec le même billet. Sinon, chaque billet ne permet qu'une seule entrée.</p>
    <button class="btn btn-sm">Enregistrer</button>
  </form>
</div>
