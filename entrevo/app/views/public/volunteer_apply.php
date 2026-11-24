<div class="vol-wrap">
  <div class="vol-hero"><span class="eyebrow">On recrute des bénévoles</span><h1><?= e($ev['name']) ?></h1>
    <p class="muted"><?= e(fr_date($ev['starts_at'])) ?> · <?= e(trim($ev['venue'] . ' ' . $ev['city'])) ?> · organisé par <?= e($org['name']) ?></p>
    <?php if ($ev['volunteer_intro']): ?><div class="vol-intro"><?= nl2br(e($ev['volunteer_intro'])) ?></div><?php endif; ?></div>
  <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="card stack"><?= csrf_field() ?>
    <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
    <h2>Toi</h2>
    <div class="grid2"><div class="field mb0"><label>Prénom</label><input name="first_name" value="<?= e(input('first_name')) ?>" required autocomplete="given-name"></div><div class="field mb0"><label>Nom</label><input name="last_name" value="<?= e(input('last_name')) ?>" required autocomplete="family-name"></div></div>
    <div class="grid2"><div class="field mb0"><label>E-mail</label><input type="email" name="email" value="<?= e(input('email')) ?>" required autocomplete="email"></div><div class="field mb0"><label>Téléphone</label><input type="tel" name="phone" value="<?= e(input('phone')) ?>" autocomplete="tel"></div></div>
    <div class="grid2"><div class="field mb0"><label>Ce que tu aimerais faire</label><input name="team" value="<?= e(input('team')) ?>" placeholder="Bar, accueil, montage…"></div>
      <div class="field mb0"><label>Taille de t-shirt</label><select name="tshirt"><option value="">—</option><?php foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL'] as $t): ?><option><?= $t ?></option><?php endforeach; ?></select></div></div>
    <div class="field mb0"><label>Compétences utiles <small class="muted">(facultatif)</small></label><input name="skills" value="<?= e(input('skills')) ?>" placeholder="Permis B, secourisme, anglais, caisse…"></div>
    <?php if ($shifts): ?>
      <h2>Tes disponibilités</h2>
      <div class="vol-shifts"><?php foreach ($shifts as $s): $free = Staff::free($s); ?>
        <label class="vol-shift<?= $free <= 0 ? ' full' : '' ?>"><input type="checkbox" name="shifts[]" value="<?= (int)$s['id'] ?>" <?= $free <= 0 ? 'disabled' : '' ?>>
          <span><b><?= e($s['title']) ?></b><small><?= e(fr_date($s['starts_at'])) ?> → <?= e(date('H:i', strtotime($s['ends_at']))) ?><?= $s['location'] ? ' · ' . e($s['location']) : '' ?></small></span>
          <em><?= $free <= 0 ? 'Complet' : $free . ' place' . ($free > 1 ? 's' : '') ?></em></label>
      <?php endforeach; ?></div>
    <?php endif; ?>
    <div class="field mb0"><label>Un petit mot ?</label><textarea name="message" rows="2"><?= e(input('message')) ?></textarea></div>
    <button class="btn btn-lg">Je candidate</button>
    <small class="muted">Tes coordonnées ne servent qu'à l'organisation de cet événement par <?= e($org['name']) ?>.</small>
  </form>
</div>
