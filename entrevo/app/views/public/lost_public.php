<?php /** @var array $org, $events, $errors, $found; bool $done */ ?>
<section class="store-wrap u-mw820">
  <span class="eyebrow"><?= e($org['name']) ?></span>
  <h1>Objets perdus, objets trouvés</h1>
  <?php if ($done): ?><div class="card"><h2>C'est noté</h2><p class="mb0">Si l'objet est retrouvé, <?= e($org['name']) ?> te prévient par e-mail. Garde un signe distinctif en tête pour prouver qu'il est à toi.</p></div>
  <?php else: ?>
  <p class="lead">Tu as perdu quelque chose pendant un événement ? Décris-le : l'équipe compare avec les objets ramassés et te prévient.</p>
  <?php foreach ($errors as $er): ?><div class="flash error"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="card"><?= csrf_field() ?>
    <div class="grid2">
      <div class="field"><label>Événement</label><select name="event_id"><option value="">Je ne sais plus</option><?php foreach ($events as $e2): ?><option value="<?= (int)$e2['id'] ?>"><?= e($e2['name']) ?> · <?= e(short_date($e2['starts_at'])) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Quoi</label><select name="category"><?php foreach (LOST_CATS as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="field"><label>Description</label><textarea name="description" rows="3" required placeholder="Marque, couleur, contenu, où tu l'as vu pour la dernière fois…"></textarea></div>
    <div class="grid2"><div class="field"><label>Ton nom</label><input name="name" required></div><div class="field"><label>E-mail</label><input type="email" name="email" required></div></div>
    <div class="field" style="max-width:50%"><label>Téléphone <small class="muted">(facultatif)</small></label><input name="phone"></div>
    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
    <button class="btn btn-lg">Déclarer ma perte</button>
  </form>
  <?php endif; ?>
  <?php if ($found): ?><div class="card"><h2>Ramassés récemment</h2><p class="muted u-fs13">Les détails restent secrets : ils servent à vérifier que l'objet est bien à toi.</p>
    <?php foreach ($found as $i): ?><div class="list-item"><span class="grow"><b><?= e($i['title']) ?></b><br><small class="muted"><?= e(LOST_CATS[$i['category']] ?? '') ?><?= $i['ev_name'] ? ' · ' . e($i['ev_name']) : '' ?></small></span><small class="muted"><?= e(date('d/m', strtotime($i['found_at']))) ?></small></div><?php endforeach; ?></div><?php endif; ?>
</section>
