<?php $r = (int)($rev['rating'] ?? 0); ?>
<div class="card auth-box review-box">
  <?php if ($done): ?>
    <div class="state-mark"><?= ui_icon('star', 30) ?></div>
    <h1>Merci <?= e($o['first_name']) ?> !</h1>
    <p class="muted">Ton avis est bien arrivé chez <?= e($org['name']) ?>. Ça les aide vraiment à faire encore mieux la prochaine fois.</p>
    <p><a class="btn" href="<?= url('/o/' . $org['slug']) ?>">Voir leurs prochains événements</a></p>
  <?php else: ?>
    <span class="eyebrow"><?= e($org['name']) ?></span>
    <h1>Alors, <?= e($ev['name']) ?> ?</h1>
    <p class="muted"><?= $rev ? 'Ta note est enregistrée. Tu veux ajouter un petit mot ?' : 'Ta note, de 1 (bof) à 5 (on y retourne demain).' ?></p>
    <form method="post" class="stack"><?= csrf_field() ?>
      <div class="star-pick" role="radiogroup" aria-label="Ta note">
        <?php for ($k = 5; $k >= 1; $k--): ?><input type="radio" name="rating" value="<?= $k ?>" id="st<?= $k ?>" <?= $r === $k ? 'checked' : '' ?> required><label for="st<?= $k ?>" title="<?= $k ?>/5">★</label><?php endfor; ?>
      </div>
      <div class="field"><label>Un commentaire ? <small class="muted">(facultatif)</small></label><textarea name="comment" rows="4" maxlength="1000" placeholder="Le son, l'ambiance, l'accueil, la queue au bar…"><?= e((string)($rev['comment'] ?? '')) ?></textarea></div>
      <label class="check"><input type="checkbox" name="anonymous" value="1" <?= $rev && $rev['author'] === null ? 'checked' : '' ?>> <span>Publier sans mon prénom</span></label>
      <button class="btn btn-lg btn-block">Envoyer mon avis</button>
    </form>
    <p class="muted u-fs13">Ton avis peut être affiché sur la page de l'organisateur (prénom et initiale seulement).</p>
  <?php endif; ?>
</div>
