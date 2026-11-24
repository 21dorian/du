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
        <div class="grid2">
          <div class="field"><label>Catégorie</label><input name="category" value="<?= e($ev['category'] ?? '') ?>" list="cats" placeholder="Concert, Festival, Sport, Théâtre…"><datalist id="cats"><?php foreach (['Concert', 'Festival', 'Théâtre', 'Humour', 'Sport', 'Salon', 'Conférence', 'Soirée', 'Atelier', 'Famille', 'Exposition'] as $c): ?><option value="<?= $c ?>"><?php endforeach; ?></datalist></div>
          <?php if (!$isNew): ?><div class="field"><label>Adresse de la billetterie</label><div class="row" style="gap:6px;flex-wrap:nowrap"><small class="muted nowrap">/e/</small><input name="slug" value="<?= e($ev['slug'] ?? '') ?>"></div></div><?php endif; ?>
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
        <div class="field"><label for="revenue_goal">🎯 Objectif de recettes <small class="muted">(optionnel)</small></label><div class="input-suffix" style="max-width:220px"><input id="revenue_goal" name="revenue_goal" inputmode="decimal" value="<?= !empty($ev['revenue_goal']) ? e(money_input((int)$ev['revenue_goal'])) : '' ?>" placeholder="Ex. 15 000"><span>€</span></div><small>Ton seuil de rentabilité ou ton rêve : on affiche la progression sur le tableau de bord.</small></div>
        <div class="field"><label for="max_per_order">Billets max. par commande</label><input type="number" id="max_per_order" name="max_per_order" min="0" value="<?= (int)($ev['max_per_order'] ?? 10) ?>"></div>
        <label class="check"><input type="checkbox" name="allow_reentry" value="1" <?= !empty($ev['allow_reentry']) ? 'checked' : '' ?>> Autoriser les sorties / ré-entrées</label>
      </div>
      <div class="card">
        <h2>🔐 Accès & publication</h2>
        <div class="field"><label>Mot de passe (événement privé)</label><input name="password" value="<?= e($ev['password'] ?? '') ?>" autocomplete="off" placeholder="Vide = public"><small>Masqué de l'agenda, accessible avec le mot de passe uniquement.</small></div>
        <div class="field"><label>Publication programmée</label><input type="datetime-local" name="publish_at" value="<?= e(dt_input($ev['publish_at'] ?? null)) ?>"><small>Reste en brouillon puis se publie tout seul à cette heure-là.</small></div>
        <label class="check"><input type="checkbox" name="queue_enabled" value="1" <?= !empty($ev['queue_enabled']) ? 'checked' : '' ?>> 🚦 File d'attente virtuelle (grosse ouverture des ventes)</label>
        <div class="field mt" data-show-if="queue_enabled=1"><label>Personnes admises par minute</label><input type="number" name="queue_rate" min="5" value="<?= (int)($ev['queue_rate'] ?? 50) ?>"></div>
        <div class="field mt"><label>🔞 Âge minimum</label><select name="min_age"><?php foreach (['' => 'Tout public', '12' => '12 ans et +', '16' => '16 ans et +', '18' => '18 ans et +', '21' => '21 ans et +'] as $k => $l): ?><option value="<?= $k ?>" <?= (string)($ev['min_age'] ?? '') === (string)$k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><small>Date de naissance demandée à l'achat, mention sur la page et le billet, rappel « vérifier l'âge » au scan. Réglable aussi par tarif.</small></div>
      </div>
      <div class="card">
        <h2>🔒 Prévente privée</h2>
        <p class="muted mt0">Avant une date d'ouverture, seules les personnes autorisées peuvent acheter : elles reçoivent un lien d'accès par e-mail.</p>
        <div class="field"><label>Ouverture au grand public</label><input type="datetime-local" name="presale_until" value="<?= e(dt_input($ev['presale_until'] ?? null)) ?>"><small>Vide = pas de prévente. Avant cette date, la page affiche « Prévente privée ».</small></div>
        <label class="check mb"><input type="checkbox" name="presale_past" value="1" <?= ($ev['presale_past'] ?? 1) ? 'checked' : '' ?>> Autoriser tes anciens participants (déjà acheté chez toi)</label>
        <div class="field mb0"><label>Liste d'e-mails autorisés</label><textarea name="presale_list" rows="4" placeholder="Une adresse par ligne (abonnés, membres, bénévoles…)"><?= e($ev['presale_list'] ?? '') ?></textarea>
          <?php if (!empty($ev['id'])): $pa = one('SELECT COUNT(*) n, SUM(used_at IS NOT NULL) used FROM presale_access WHERE event_id = ?', [$ev['id']]); ?><small><?= count(array_filter(preg_split('~[\s,;]+~', (string)($ev['presale_list'] ?? '')), 'is_email')) ?> adresse(s) dans la liste · <?= (int)$pa['n'] ?> lien(s) d'accès envoyé(s) · <?= (int)$pa['used'] ?> utilisé(s)</small><?php endif; ?></div>
      </div>
      <div class="card">
        <h2>🛒 Options de vente</h2>
        <label class="check mb"><input type="checkbox" name="waitlist_enabled" value="1" <?= ($ev['waitlist_enabled'] ?? 1) ? 'checked' : '' ?>> ⏳ Liste d'attente quand c'est complet</label>
        <label class="check mb"><input type="checkbox" name="transfer_enabled" value="1" <?= ($ev['transfer_enabled'] ?? 1) ? 'checked' : '' ?>> 🎁 Les acheteurs peuvent donner leur billet à un ami</label>
        <label class="check mb"><input type="checkbox" name="exchange_enabled" value="1" <?= !empty($ev['exchange_enabled']) ? 'checked' : '' ?>> 📅 Les acheteurs peuvent changer de date eux-mêmes (événement à plusieurs dates)</label>
        <div class="field" data-show-if="exchange_enabled=1"><label>Jusqu'à combien d'heures avant la date d'origine ?</label><div class="input-suffix"><input type="number" name="exchange_hours" min="0" max="720" value="<?= (int)($ev['exchange_hours'] ?? 24) ?>"><span>h</span></div></div>
        <label class="check mb"><input type="checkbox" name="resale_enabled" value="1" <?= !empty($ev['resale_enabled']) ? 'checked' : '' ?>> 🔁 Bourse aux billets officielle</label>
        <div class="field" data-show-if="resale_enabled=1"><label>Frais de revente payés par le vendeur</label><div class="input-suffix"><input name="resale_fee_percent" value="<?= e($ev['resale_fee_percent'] ?? '5') ?>" inputmode="decimal"><span>%</span></div></div>
        <label class="check mb"><input type="checkbox" name="refund_request_enabled" value="1" <?= !empty($ev['refund_request_enabled']) ? 'checked' : '' ?>> 💸 Les acheteurs peuvent demander un remboursement</label>
        <div class="field" data-show-if="refund_request_enabled=1"><label>Jusqu'au</label><input type="datetime-local" name="refund_deadline" value="<?= e(dt_input($ev['refund_deadline'] ?? null)) ?>"></div>
        <label class="check mb"><input type="checkbox" name="donation_enabled" value="1" <?= !empty($ev['donation_enabled']) ? 'checked' : '' ?>> 💛 Proposer un don à la commande</label>
        <div class="field" data-show-if="donation_enabled=1"><label>Phrase d'appel au don</label><input name="donation_label" value="<?= e($ev['donation_label'] ?? '') ?>" placeholder="Un petit don pour soutenir l'asso ?"></div>
      </div>
      <div class="card">
        <h2>👥 Tarif dégressif</h2>
        <p class="muted mt0">Plus on prend de billets dans une même commande, moins c'est cher. La remise s'applique à tous les billets payants de la commande.</p>
        <?php $tiers = Tickets::tiers($ev); for ($k = 0; $k < 3; $k++): $t = $tiers[$k] ?? ['min' => '', 'pct' => '']; ?>
          <div class="tier-row"><span>Dès</span><input type="number" name="tier_min[]" min="2" max="500" value="<?= e((string)$t['min']) ?>" placeholder="<?= [5, 10, 20][$k] ?>" aria-label="Nombre de billets"><span>billets :</span><div class="input-suffix"><input type="number" name="tier_pct[]" min="1" max="90" value="<?= e((string)$t['pct']) ?>" placeholder="<?= [10, 15, 20][$k] ?>" aria-label="Remise"><span>%</span></div><span>de remise</span></div>
        <?php endfor; ?>
        <small class="muted">Laisse vide pour ne pas en proposer. Non cumulable avec les partenaires et la revente ; cumulable avec un code promo.</small>
      </div>
      <div class="card">
        <h2>🎨 Billet & e-mail</h2>
        <div class="grid2">
          <div class="field"><label>Couleur du billet</label><input type="color" name="ticket_color" value="<?= e($ev['ticket_color'] ?? setting('primary_color', '#0032fa')) ?>"></div>
          <div class="field"><label>Logo sur le billet</label><?php if (!empty($ev['ticket_logo'])): ?><img src="<?= url($ev['ticket_logo']) ?>" alt="" style="max-height:40px;margin-bottom:6px"><?php endif; ?><input type="file" name="ticket_logo" accept="image/png,image/jpeg"></div>
        </div>
        <div class="field mb0"><label>Message dans l'e-mail de confirmation</label><textarea name="email_message" rows="3" placeholder="Infos pratiques, accès, parking…"><?= e($ev['email_message'] ?? '') ?></textarea></div>
      </div>
      <div class="card">
        <h2>📝 Texte sur le billet</h2>
        <textarea name="ticket_info" rows="4" placeholder="Ex. : Ouverture des portes 19 h. Pièce d'identité demandée."><?= e($ev['ticket_info'] ?? '') ?></textarea>
      </div>
      <?= soon_card('🧪 Options avancées', [
        'billet-nominatif-id' => ['Contrôle d\'identité', 'Billet strictement nominatif, vérifié avec la pièce d\'identité'],
        'places-pmr' => ['Places PMR & accompagnateurs', 'Quota dédié et billet accompagnateur offert'],
        'assurance-annulation' => ['Assurance annulation', 'Proposée à l\'acheteur au moment du paiement'],
        'multilingue' => ['Page en plusieurs langues', 'Anglais, espagnol, allemand…'],
      ]) ?>
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
