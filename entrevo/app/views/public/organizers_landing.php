<?php $pname = (string)setting('platform_name'); $ex = ['fee_percent' => null, 'fee_fixed' => null]; $f20 = Tickets::computeFees($ex, 2000, 1); ?>
<section class="vit-hero">
  <div class="vit-hero-copy">
    <span class="eyebrow">Pour les organisateurs</span>
    <h1>Ta billetterie en ligne en 5 minutes. Sans abonnement.</h1>
    <p class="lead">Vends tes billets, remplis ta salle et contrôle les entrées avec ton téléphone, même sans réseau. Tu ne paies que quand tu vends.</p>
    <div class="vit-cta"><a class="btn btn-lg" href="<?= url('/inscription') ?>">Créer ma billetterie gratuitement</a><?= pub_soon('demo-visio', 'Démo en visio', 'btn btn-lg btn-ghost soon-btn') ?></div>
    <div class="vit-proof"><span>Billets gratuits : 0 €</span><span>Paiement sécurisé</span><span>Scan hors ligne</span></div>
  </div>
  <div class="vit-hero-visual" aria-hidden="true">
    <div class="vit-phone"><div class="vit-phone-in"><b>Bienvenue !</b><span>LÉA MARTIN</span><small>Pass 3 jours</small></div></div>
    <div class="vit-float one"><small>Ventes du jour</small><b>+42 billets</b></div>
    <div class="vit-float two"><small>Remplissage</small><b>87 %</b></div>
  </div>
</section>
<?php if ($stats['tickets'] >= 100): ?>
<section class="vit-stats"><div><b><?= number_format($stats['events'], 0, ',', ' ') ?></b><span>événements</span></div><div><b><?= number_format($stats['tickets'], 0, ',', ' ') ?></b><span>billets émis</span></div><div><b><?= number_format($stats['orgs'], 0, ',', ' ') ?></b><span>organisateurs</span></div></section>
<?php endif; ?>
<section class="vit-steps">
  <h2>Comment ça marche ?</h2>
  <div class="vit-steps-grid">
    <div><span>1</span><b>Crée ton événement</b><p>Date, lieu, affiche et tarifs : c'est en ligne en quelques minutes.</p></div>
    <div><span>2</span><b>Vends partout</b><p>Ta page, ton site (widget), tes réseaux, le guichet. Paiement par carte, PayPal…</p></div>
    <div><span>3</span><b>Scanne à l'entrée</b><p>Ton téléphone devient un contrôleur, même sans réseau. Suivi en direct.</p></div>
    <div><span>4</span><b>Reçois ton argent</b><p>Reversements réguliers, factures et exports comptables prêts.</p></div>
  </div>
</section>
<section class="vit-features">
  <h2>Tout ce qu'il faut, déjà inclus</h2>
  <div class="mod-grid"><?php foreach ($live as $k => $f): ?><div class="mod-card"><span class="mod-ico"><?= feature_icon($f) ?></span><span class="mod-body"><b><?= e($f['name']) ?></b><span><?= e($f['pitch']) ?></span></span></div><?php endforeach; ?></div>
</section>
<section class="vit-features soon">
  <h2>Et ça arrive bientôt <span class="soon-tag big">en préparation</span></h2>
  <div class="mod-grid"><?php foreach ($soon as $k => $f): ?><a class="mod-card is-soon" href="<?= url('/bientot/' . $k) ?>"><span class="mod-ico"><?= feature_icon($f) ?></span><span class="mod-body"><b><?= e($f['name']) ?></b><span><?= e($f['pitch']) ?></span></span><span class="mod-foot"><span class="soon-tag">bientôt</span><span class="muted">En savoir plus →</span></span></a><?php endforeach; ?></div>
</section>
<section class="vit-price" id="tarifs">
  <div>
    <h2>Un tarif simple</h2>
    <p class="vit-price-big"><b><?= e(rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',')) ?> %</b> + <b><?= e(money($fixed)) ?></b> par billet payant</p>
    <ul class="soon-points"><li>Billets gratuits : 0 €, toujours</li><li>Pas d'abonnement, pas d'engagement</li><li>Tu choisis : frais payés par l'acheteur ou par toi</li><li>Toutes les fonctions incluses</li></ul>
  </div>
  <div class="vit-sim" data-sim data-pct="<?= e((string)$pct) ?>" data-fixed="<?= (int)$fixed ?>">
    <h3>Simulateur</h3>
    <div class="grid2"><div class="field mb0"><label for="sim-price">Prix du billet</label><div class="input-suffix"><input id="sim-price" type="number" min="0" step="0.5" value="20"><span>€</span></div></div>
      <div class="field mb0"><label for="sim-qty">Billets vendus</label><input id="sim-qty" type="number" min="0" step="10" value="300"></div></div>
    <div class="vit-sim-out"><div><small>Frais par billet</small><b id="sim-fee"><?= e(money($f20)) ?></b></div><div><small>Tu reçois (frais payés par l'acheteur)</small><b id="sim-net">—</b></div></div>
  </div>
</section>
<section class="vit-faq">
  <h2>Questions fréquentes</h2>
  <details><summary>Quand est-ce que je reçois l'argent ?</summary><p>Les paiements en ligne sont encaissés par <?= e($pname) ?> puis reversés sur ton compte bancaire par virement, avec un relevé et une facture de commission chaque mois.</p></details>
  <details><summary>Il faut du matériel pour contrôler les billets ?</summary><p>Non : n'importe quel téléphone suffit (appli web, sans installation). Une douchette USB ou Bluetooth fonctionne aussi. Le scan marche même sans réseau.</p></details>
  <details><summary>Je peux vendre des billets gratuits ?</summary><p>Oui, et c'est gratuit pour toi comme pour les participants.</p></details>
  <details><summary>Je peux intégrer la billetterie à mon site ?</summary><p>Oui, avec le widget : un petit code à copier, et tes visiteurs achètent sans quitter ton site.</p></details>
  <details><summary>Et si je dois annuler ou rembourser ?</summary><p>Tu rembourses en un clic, en entier ou en partie, depuis la fiche de la commande.</p></details>
</section>
<section class="vit-end">
  <h2>Prêt à remplir ta salle ?</h2>
  <a class="btn btn-lg" href="<?= url('/inscription') ?>">Créer ma billetterie</a>
  <div class="soon-row" style="justify-content:center"><?= pub_soon('ressources', 'Guides & ressources') ?><?= pub_soon('migration', 'Migrer depuis une autre billetterie') ?></div>
</section>
<script>
(function () { var s = document.querySelector('[data-sim]'); if (!s) return; var pct = parseFloat(s.dataset.pct) || 0, fx = parseInt(s.dataset.fixed, 10) || 0;
  var f = function (c) { return (c / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' }); };
  var up = function () { var p = Math.round((parseFloat(document.getElementById('sim-price').value) || 0) * 100), q = parseInt(document.getElementById('sim-qty').value, 10) || 0;
    var fee = p > 0 ? Math.round(p * pct / 100) + fx : 0; document.getElementById('sim-fee').textContent = f(fee); document.getElementById('sim-net').textContent = f(p * q); };
  s.addEventListener('input', up); up(); })();
</script>
