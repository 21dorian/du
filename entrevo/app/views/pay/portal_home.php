<?php /** @var array $events */ ?>
<div class="pp-hero"><h1>Mon cashless</h1><p>Recharge, solde, achats, bracelet perdu, remboursement : tout se passe ici.</p></div>
<form method="post" class="pp-card pp-stack"><?= csrf_field() ?>
  <h2>Retrouver ma carte</h2><p>Code de ton billet (sous le QR code), de ta carte cashless ou numéro imprimé sur ton bracelet.</p>
  <input name="ref" placeholder="Ex. 7KQ2M9XA" autocomplete="off" autocapitalize="characters" required><button class="pp-btn">Ouvrir ma carte</button></form>
<form method="post" class="pp-card pp-stack"><?= csrf_field() ?><input type="hidden" name="action" value="mail">
  <h2>Je n'ai pas mon code</h2><p>On t'envoie le lien de tes cartes à l'adresse utilisée pour ton billet ou ta carte.</p>
  <input name="email" type="email" placeholder="ton@email.fr" required><button class="pp-btn light">Recevoir mes liens</button></form>
<?php if ($events): ?><div class="pp-card"><h2>Événements en cashless</h2><p>Pas de billet ? Crée ta carte cashless virtuelle.</p><div class="pp-ev-list">
  <?php foreach ($events as $e2): ?><a href="<?= Pay::url('/e/' . $e2['slug']) ?>"><span><b><?= e($e2['name']) ?></b><br><small><?= e(fr_date($e2['starts_at'], false)) ?><?= $e2['city'] ? ' · ' . e($e2['city']) : '' ?></small></span><span>›</span></a><?php endforeach; ?></div></div><?php endif; ?>
