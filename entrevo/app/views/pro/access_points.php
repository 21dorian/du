<?php
$rateName = array_column($rates, 'name', 'id');
$form = function (?array $a) use ($rates) {
    $p = $a ?? ['id' => 0, 'name' => '', 'rate_ids' => '', 'active' => 1, 'capacity' => null, 'opens_at' => null, 'closes_at' => null];
    $sel = array_map('intval', array_filter(explode(',', (string)$p['rate_ids'])));
    ob_start(); ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="ap_id" value="<?= (int)$p['id'] ?>">
      <div class="field"><label>Nom</label><input name="name" value="<?= e($p['name']) ?>" required placeholder="Entrée principale, Porte B, Espace VIP…"></div>
      <div class="field"><label>Tarifs autorisés à ce point</label>
        <?php foreach ($rates as $r): ?><label class="check u-mb6"><input type="checkbox" name="rate_ids[]" value="<?= (int)$r['id'] ?>" <?= in_array((int)$r['id'], $sel, true) ? 'checked' : '' ?>> <?= e($r['name']) ?></label><?php endforeach; ?>
        <small>Aucun coché = tous les tarifs. Exemple : ne coche que « Pass VIP » pour l'espace VIP. Les autres resteront dehors</small></div>
      <div class="field"><label>Jauge de la zone <small class="muted">(facultatif)</small></label><input type="number" name="capacity" min="0" value="<?= $p['capacity'] !== null ? (int)$p['capacity'] : '' ?>" placeholder="Ex. : 400"><small>Nombre maximum de personnes en même temps derrière cette porte. Le PC sécurité alerte à 90 % et préviens les contrôleurs.</small></div>
      <div class="grid2"><div class="field"><label>Ouverture automatique</label><input type="datetime-local" name="opens_at" value="<?= e(dt_input($p['opens_at'] ?? null)) ?>"></div>
        <div class="field"><label>Fermeture automatique</label><input type="datetime-local" name="closes_at" value="<?= e(dt_input($p['closes_at'] ?? null)) ?>"></div></div>
      <small class="muted" style="display:block;margin:-6px 0 12px">Vide = pas d'horaire. En dehors, le scan répond « Porte fermée » (même hors ligne).</small>
      <label class="check" style="margin-bottom:14px"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> Ouvert</label>
      <div class="form-actions"><button class="btn"><?= $p['id'] ? 'Enregistrer' : 'Ajouter' ?></button>
      <?php if ($p['id']): ?><button class="btn btn-ghost u-red" name="action" value="delete" formnovalidate data-confirm="Supprimer ce point d'accès ?">Supprimer</button><?php endif; ?></div>
    </form>
<?php return ob_get_clean(); };
?>
<div class="split-3">
  <div>
    <div class="card">
      <div class="card-head"><h2>Appli de contrôle</h2><a class="btn" href="<?= url('/scan') ?>" target="_blank">Ouvrir le scanner</a></div>
      <p>Sur chaque téléphone de contrôle, ouvre <b><?= e(abs_url('/scan')) ?></b>, connecte-toi avec un compte <b>Contrôleur</b>, choisis l'événement et la porte. C'est tout.</p>
      <ul class="muted" style="padding-left:18px;margin:0">
        <li>Scan avec la caméra ou une douchette USB/Bluetooth, et saisie manuelle du code.</li>
        <li><b>Mode hors ligne</b> : plus de réseau ? Le scan continue, tout se synchronise au retour de la 4G.</li>
        <li>Ajoute-la à l'écran d'accueil du téléphone : elle s'ouvre comme une vraie appli.</li>
      </ul>
      <div class="row mt"><a class="btn btn-sm btn-light" href="<?= url('/pro/equipe') ?>">Créer des comptes contrôleurs</a>
        <span class="muted u-fs14"><?= count($scanners) ?> contrôleur<?= count($scanners) > 1 ? 's' : '' ?> actif<?= count($scanners) > 1 ? 's' : '' ?></span></div>
    </div>
    <h2 class="mt">Points d'accès</h2>
    <?php foreach ($aps as $a): $ids = array_filter(explode(',', (string)$a['rate_ids'])); ?>
      <details class="box"><summary><span><?= e($a['name']) ?> <?= $a['active'] ? '' : badge('closed') ?><?= $a['opens_at'] || $a['closes_at'] ? ' <span class="badge blue">' . e(trim(($a['opens_at'] ? date('H\hi', strtotime($a['opens_at'])) : '…') . ' → ' . ($a['closes_at'] ? date('H\hi', strtotime($a['closes_at'])) : '…'))) . '</span>' : '' ?></span><span class="muted u-fw500"><?= $ids ? e(implode(', ', array_map(fn($i) => $rateName[$i] ?? '?', $ids))) : 'Tous les tarifs' ?> · <?= (int)$a['entries'] ?> entrées</span></summary><?= $form($a) ?></details>
    <?php endforeach; ?>
    <details class="box"><summary style="color:var(--primary)">+ Ajouter un point d'accès</summary><?= $form(null) ?></details>
  </div>
  <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reentry">
    <h2>Règles d'entrée</h2>
    <label class="check"><input type="checkbox" name="allow_reentry" value="1" <?= $ev['allow_reentry'] ? 'checked' : '' ?>> Autoriser les sorties et ré-entrées</label>
    <p class="muted" style="font-size:14px;margin-top:8px">Activé : le contrôleur scanne en mode « Sortie » quand quelqu'un sort, et la personne peut revenir avec le même billet. Désactivé : une entrée par billet, point.</p>
    <button class="btn btn-sm">Enregistrer</button>
  </form>
</div>
