<?php /** @var array $ev, $s, $org */ ?>
<div class="pp-hero"><h1><?= e($ev['name']) ?></h1><p>Paiement cashless aux bars et aux stands · <?= e($org['name']) ?></p></div>
<?php if ($s['message']): ?><div class="pp-card"><p style="margin:0;color:var(--ink)"><?= nl2br(e((string)$s['message'])) ?></p></div><?php endif; ?>
<?php if ($s['virtual']): ?>
<form method="post" class="pp-card pp-stack"><?= csrf_field() ?><input type="hidden" name="action" value="virtual">
  <h2>Créer ma carte cashless</h2><p>Une carte virtuelle sur ton téléphone : recharge-la en ligne, puis présente son QR code au bar. Tu pourras lui associer un bracelet sur place.</p>
  <input name="name" placeholder="Prénom (facultatif)"><input name="email" type="email" placeholder="ton@email.fr" required>
  <button class="pp-btn">Créer ma carte</button><p class="pp-note u-m0">Ton e-mail sert à retrouver ta carte et à recevoir tes reçus.</p></form>
<?php endif; ?>
<form method="post" class="pp-card pp-stack"><?= csrf_field() ?>
  <h2>J'ai déjà un billet ou un bracelet</h2><p>Saisis le code de ton billet, de ta carte, ou le numéro imprimé sur ton bracelet.</p>
  <input name="ref" placeholder="Code ou numéro" autocapitalize="characters" required><button class="pp-btn light">Ouvrir ma carte</button></form>
