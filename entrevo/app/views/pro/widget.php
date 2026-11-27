<?php /** @var array $ev, $ws, $stats, $hosts */
$js = abs_url('/widget.js');
$inline = '<div data-entrevo-event="' . $ev['slug'] . '"></div>' . "\n" . '<script src="' . $js . '" async></script>';
$button = '<div data-entrevo-event="' . $ev['slug'] . '" data-mode="button" data-label="' . e($ws['button']) . '" data-color="' . e($ws['color']) . '"></div>' . "\n" . '<script src="' . $js . '" async></script>'; ?>
<div class="kpi-grid"><div class="kpi-mini"><span>Ventes par le widget</span><b><?= (int)$stats['n'] ?></b><small class="muted">commandes payées</small></div><div class="kpi-mini"><span>Recettes widget</span><b><?= e(money((int)$stats['t'])) ?></b></div>
  <?php foreach (array_slice($hosts, 0, 2) as $h): ?><div class="kpi-mini"><span><?= e((string)($h['host'] ?: 'site inconnu')) ?></span><b><?= (int)$h['n'] ?></b><small class="muted"><?= e(money((int)$h['t'])) ?></small></div><?php endforeach; ?></div>
<div class="wg-layout mt">
  <div class="stack">
    <div class="card stack"><h2>Un vrai tunnel d'achat sur ton site</h2>
      <p class="muted mb0">Tes visiteurs choisissent la date, les billets et les options, entrent leur code promo, remplissent tes questions et paient <b>sans quitter ton site</b>. Seul le paiement s'ouvre dans une fenêtre sécurisée du prestataire, puis le widget affiche la confirmation et les billets.</p></div>
    <form method="post" class="card stack"><?= csrf_field() ?><h2>Apparence</h2>
      <div class="grid2"><div class="field"><label>Couleur</label><input type="color" name="color" value="<?= e($ws['color']) ?>"></div><div class="field"><label>Arrondi des angles</label><input type="range" name="radius" min="0" max="28" value="<?= (int)$ws['radius'] ?>"></div></div>
      <div class="grid2"><div class="field"><label>Thème</label><select name="theme"><option value="light">Clair</option><option value="dark" <?= $ws['theme'] === 'dark' ? 'selected' : '' ?>>Sombre</option></select></div><div class="field"><label>Texte du bouton</label><input name="button" value="<?= e($ws['button']) ?>"></div></div>
      <label class="check"><input type="checkbox" name="show_image" value="1" <?= $ws['show_image'] ? 'checked' : '' ?>> Afficher l'image de l'événement</label>
      <label class="check"><input type="checkbox" name="show_desc" value="1" <?= $ws['show_desc'] ? 'checked' : '' ?>> Afficher le début de la description</label>
      <label class="check"><input type="checkbox" name="products" value="1" <?= $ws['products'] ? 'checked' : '' ?>> Proposer les options et produits</label>
      <label class="check"><input type="checkbox" name="promo" value="1" <?= $ws['promo'] ? 'checked' : '' ?>> Champ « code promo / code d'accès »</label>
      <button class="btn">Enregistrer</button></form>
    <div class="card stack"><h2>Code à coller sur ton site</h2>
      <div class="field"><label>Widget intégré dans la page (s'adapte en hauteur)</label><textarea readonly rows="3" class="mono u-fs13"><?= e($inline) ?></textarea><button type="button" class="btn btn-sm btn-light" data-copy="<?= e($inline) ?>">Copier</button></div>
      <div class="field"><label>Bouton qui ouvre la billetterie par-dessus la page</label><textarea readonly rows="3" class="mono u-fs13"><?= e($button) ?></textarea><button type="button" class="btn btn-sm btn-light" data-copy="<?= e($button) ?>">Copier</button></div>
      <small class="muted">WordPress, Wix, Squarespace, Webflow : ajoute un bloc « HTML personnalisé » et colle le code. Les ventes du widget sont repérées dans tes statistiques (canal « widget », avec le nom du site).</small></div>
  </div>
  <div class="card wg-preview"><div class="card-head"><h2>Aperçu en direct</h2><a class="btn btn-sm btn-ghost" target="_blank" href="<?= url('/widget/achat/' . $ev['slug']) ?>">Ouvrir seul</a></div>
    <div class="wg-site"><div class="wg-bar"><i></i><i></i><i></i><span>ton-site.fr</span></div>
      <div data-entrevo-event="<?= e($ev['slug']) ?>"></div></div></div>
</div>
<script src="<?= e($js) ?>" async></script>
