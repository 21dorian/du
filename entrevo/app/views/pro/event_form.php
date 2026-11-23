<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
  <?php if ($errors): ?><div class="flash error"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
  <div class="split-3">
    <div>
      <div class="card">
        <h2>🎤 L'événement</h2>
        <div class="field"><label for="name">Nom de l'événement *</label><input id="name" name="name" value="<?= e($ev['name'] ?? '') ?>" required placeholder="Ex. : Le concert de l'année (rien que ça)"></div>
        <div class="grid2">
          <div class="field"><label for="starts_at">Début *</label><input type="datetime-local" id="starts_at" name="starts_at" value="<?= e(dt_input($ev['starts_at'] ?? null)) ?>" required></div>
          <div class="field"><label for="ends_at">Fin</label><input type="datetime-local" id="ends_at" name="ends_at" value="<?= e(dt_input($ev['ends_at'] ?? null)) ?>"></div>
        </div>
        <div class="field"><label for="description">Description</label><textarea id="description" name="description" rows="7" placeholder="Programme, artistes, informations pratiques…"><?= e($ev['description'] ?? '') ?></textarea></div>
      </div>
      <div class="card">
        <h2>📍 Le lieu</h2>
        <div class="field"><label for="venue">Nom du lieu</label><input id="venue" name="venue" value="<?= e($ev['venue'] ?? '') ?>" placeholder="Salle des fêtes, parc, théâtre…"></div>
        <div class="grid2">
          <div class="field"><label for="address">Adresse</label><input id="address" name="address" value="<?= e($ev['address'] ?? '') ?>"></div>
          <div class="field"><label for="city">Ville</label><input id="city" name="city" value="<?= e($ev['city'] ?? '') ?>"></div>
        </div>
      </div>
      <?php if ($isNew): ?>
      <div class="card">
        <h2>🎟️ Premier tarif</h2>
        <p class="muted">Tu pourras ajouter d'autres tarifs (réduit, VIP, groupe…) juste après.</p>
        <div class="grid2">
          <div class="field"><label>Nom du tarif</label><input name="first_rate" value="<?= e(input('first_rate', 'Plein tarif')) ?>"></div>
          <div class="field"><label>Prix (€)</label><div class="input-suffix"><input name="first_price" value="<?= e(input('first_price', '10,00')) ?>" inputmode="decimal"><span>€</span></div><small>0 = gratuit. Les gens adorent.</small></div>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <div>
      <div class="card">
        <h2>🖼️ L'affiche</h2>
        <?php if (!empty($ev['image'])): ?><img src="<?= url($ev['image']) ?>" alt="" style="border-radius:10px;margin-bottom:10px">
          <label class="check" style="margin-bottom:12px"><input type="checkbox" name="remove_image" value="1"> Supprimer l'image</label><?php endif; ?>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
        <small class="muted">Format carré conseillé (1200 × 1200 px), 5 Mo max. Une belle affiche = plus de ventes.</small>
      </div>
      <div class="card">
        <h2>📏 Jauge & règles</h2>
        <div class="field"><label for="capacity">Capacité totale</label><input type="number" id="capacity" name="capacity" min="0" value="<?= (int)($ev['capacity'] ?? 0) ?>"><small>Tous tarifs confondus. 0 = illimitée (vraiment ?).</small></div>
        <div class="field"><label for="max_per_order">Billets max. par commande</label><input type="number" id="max_per_order" name="max_per_order" min="0" value="<?= (int)($ev['max_per_order'] ?? 10) ?>"></div>
        <label class="check"><input type="checkbox" name="allow_reentry" value="1" <?= !empty($ev['allow_reentry']) ? 'checked' : '' ?>> Autoriser les sorties / ré-entrées</label>
      </div>
      <div class="card">
        <h2>📝 Texte sur le billet</h2>
        <textarea name="ticket_info" rows="4" placeholder="Ex. : Ouverture des portes 19 h. Pièce d'identité demandée."><?= e($ev['ticket_info'] ?? '') ?></textarea>
      </div>
    </div>
  </div>
  <div class="form-actions mt">
    <button class="btn btn-lg"><?= $isNew ? 'Créer l\'événement 🚀' : 'Enregistrer' ?></button>
    <?php if (!$isNew): ?>
      <button class="btn btn-ghost" formaction="<?= url('/pro/evenements/' . $ev['id'] . '/dupliquer') ?>" formnovalidate>Dupliquer</button>
      <button class="btn btn-ghost" style="color:var(--red)" formaction="<?= url('/pro/evenements/' . $ev['id'] . '/supprimer') ?>" formnovalidate data-confirm="Supprimer définitivement cet événement ?">Supprimer</button>
    <?php endif; ?>
  </div>
</form>
