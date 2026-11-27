<?php /** @var array $ev, $org, $v, $errors; ?array $done */ $min = (int)setting('quote_min_' . $org['id'], '10'); ?>
<section class="store-wrap u-mw760">
  <span class="eyebrow"><?= e($ev['name']) ?> · <?= e(fr_date($ev['starts_at'])) ?></span>
  <h1>Venir en groupe</h1>
  <?php if ($done): ?>
    <div class="card"><h2>Demande envoyée</h2><p><?= e($org['name']) ?> prépare ton devis (référence <b class="mono"><?= e($done['ref']) ?></b>) et te l'envoie par e-mail. Tu pourras l'accepter et payer en ligne, ou par virement.</p><p><a class="btn btn-light" href="<?= url('/e/' . $ev['slug']) ?>">Retour à l'événement</a></p></div>
  <?php else: ?>
  <p class="lead">Comité d'entreprise, école, association, sortie entre amis : à partir de <?= $min ?> places, demande un devis avec un tarif de groupe. Paiement par carte ou par virement.</p>
  <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="card"><?= csrf_field() ?>
    <div class="grid2">
      <div class="field"><label>Structure <small class="muted">(facultatif)</small></label><input name="company" value="<?= e($v['company']) ?>" placeholder="CE Airbus, Lycée Jean-Moulin…"></div>
      <div class="field"><label>Nombre de places</label><input type="number" name="qty" min="<?= $min ?>" value="<?= max($min, (int)$v['qty']) ?>" required></div>
      <div class="field"><label>Ton nom</label><input name="contact_name" value="<?= e($v['contact_name']) ?>" required></div>
      <div class="field"><label>E-mail</label><input type="email" name="email" value="<?= e($v['email']) ?>" required></div>
      <div class="field"><label>Téléphone <small class="muted">(facultatif)</small></label><input name="phone" value="<?= e($v['phone']) ?>"></div>
    </div>
    <div class="field"><label>Précisions</label><textarea name="message" rows="4" placeholder="Tarifs souhaités, besoin d'une facture au nom de…, accessibilité…"><?= e($v['message']) ?></textarea></div>
    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
    <button class="btn btn-lg">Demander mon devis</button>
  </form>
  <?php endif; ?>
</section>
