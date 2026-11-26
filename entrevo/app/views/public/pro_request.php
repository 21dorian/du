<?php /** @var string $kind */ $demo = $kind === 'demo'; ?>
<div class="container" style="max-width:640px;padding:28px 16px">
  <h1><?= $demo ? 'Démo en visio' : 'Migrer depuis une autre billetterie' ?></h1>
  <p class="muted"><?= $demo ? '20 minutes avec l\'équipe pour voir ta future billetterie, poser tes questions et partir avec un compte prêt.' : 'On importe tes événements à venir, tes contacts (avec leur consentement) et les billets déjà vendus pour qu\'ils passent au scan. Sans coupure de vente.' ?></p>
  <form method="post" class="card stack mt"><?= csrf_field() ?>
    <div class="grid2"><div class="field"><label>Ton nom</label><input name="name" required></div><div class="field"><label>Structure</label><input name="org_name" placeholder="Association, salle, festival…"></div></div>
    <div class="grid2"><div class="field"><label>E-mail</label><input type="email" name="email" required></div><div class="field"><label>Téléphone</label><input name="phone" inputmode="tel"></div></div>
    <?php if ($demo): ?><div class="field"><label>Créneau souhaité</label><select name="slot"><option>En semaine, le matin</option><option>En semaine, l'après-midi</option><option>En semaine, après 18 h</option><option>Peu importe</option></select></div>
    <?php else: ?><div class="grid2"><div class="field"><label>Billetterie actuelle</label><input name="from" placeholder="Weezevent, HelloAsso, Billetweb…"></div><div class="field"><label>Billets par an</label><select name="volume"><option>Moins de 1 000</option><option>1 000 à 10 000</option><option>10 000 à 50 000</option><option>Plus de 50 000</option></select></div></div><?php endif; ?>
    <div class="field"><label>Ton projet en quelques mots</label><textarea name="message" rows="4"></textarea></div>
    <input name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
    <button class="btn"><?= $demo ? 'Demander une démo' : 'Demander une migration' ?></button>
  </form>
</div>
