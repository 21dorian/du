<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="split-3">
  <div>
    <div class="card">
      <h2>Infos publiques</h2>
      <div class="grid2">
        <div class="field"><label>Nom affiché *</label><input name="name" value="<?= e($org['name']) ?>" required></div>
        <div class="field"><label>E-mail de contact *</label><input type="email" name="email" value="<?= e($org['email']) ?>" required><small>Les acheteurs t'écrivent ici.</small></div>
        <div class="field"><label>Téléphone</label><input name="phone" value="<?= e($org['phone']) ?>"></div>
        <div class="field"><label>Site web</label><input name="website" value="<?= e($org['website']) ?>" placeholder="https://"></div>
      </div>
      <div class="field"><label>Présentation</label><textarea name="description" rows="4"><?= e($org['description']) ?></textarea></div>
      <div class="field"><label>Logo</label><?php if ($org['logo']): ?><img src="<?= url($org['logo']) ?>" alt="" style="max-height:50px;margin-bottom:6px"><?php endif; ?><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></div>
      <input type="hidden" name="page_settings" value="1">
      <h3 class="mt">Ta page publique</h3>
      <div class="field"><label>Accroche</label><input name="tagline" maxlength="190" value="<?= e((string)($org['tagline'] ?? '')) ?>" placeholder="Ex. : Concerts et festivals en plein air depuis 2012"></div>
      <div class="field"><label>Image de couverture</label><?php if (!empty($org['cover'])): ?><img src="<?= url($org['cover']) ?>" alt="" style="max-height:90px;border-radius:12px;margin-bottom:6px;display:block"><label class="check"><input type="checkbox" name="remove_cover" value="1"> <span>Retirer la couverture</span></label><?php endif; ?><input type="file" name="cover" accept="image/png,image/jpeg,image/webp"><small>Format large conseillé (2400 × 900 px).</small></div>
      <?php $soc = json_decode((string)($org['socials'] ?? ''), true) ?: []; ?>
      <div class="grid2"><?php foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'spotify' => 'Spotify', 'linkedin' => 'LinkedIn'] as $k => $l): ?>
        <div class="field"><label><?= $l ?></label><input name="soc_<?= $k ?>" value="<?= e((string)($soc[$k] ?? '')) ?>" placeholder="https://"></div><?php endforeach; ?></div>
      <label class="check mb"><input type="checkbox" name="show_past" value="1" <?= ($org['show_past'] ?? 1) ? 'checked' : '' ?>> <span>Montrer les événements passés (avec leurs avis)</span></label>
      <p class="mb0"><a href="<?= url('/o/' . $org['slug']) ?>" target="_blank">Voir ta page publique</a></p>
    </div>
    <div class="card">
      <h2>Intégrer la billetterie à ton site</h2>
      <p class="muted" style="font-size:14px">Copie-colle ce code dans ton site (WordPress, Wix, Squarespace…) : la liste de tes événements s'affiche, l'achat s'ouvre sur <?= e(setting('platform_name')) ?>. Pour un seul événement, prends le code dans son onglet « Vue d'ensemble ».</p>
      <?php $w = '<iframe src="' . abs_url('/widget/o/' . $org['slug']) . '" style="width:100%;min-height:520px;border:0" loading="lazy" title="Billetterie"></iframe>'; ?>
      <textarea readonly rows="3" onclick="this.select()" class="mono" style="font-size:13px"><?= e($w) ?></textarea>
      <button type="button" class="btn btn-sm btn-light mt" data-copy="<?= e($w) ?>">Copier le code</button>
    </div>
    <div class="card">
      <h2>Suivi d'audience</h2>
      <p class="muted" style="font-size:14px">Mesure tes ventes dans tes propres outils. Les codes s'ajoutent à tes pages d'événement.</p>
      <div class="grid3">
        <div class="field"><label>Google Analytics 4</label><input name="ga4_id" value="<?= e($org['ga4_id']) ?>" placeholder="G-XXXXXXX"></div>
        <div class="field"><label>Google Tag Manager</label><input name="gtm_id" value="<?= e($org['gtm_id']) ?>" placeholder="GTM-XXXXXX"></div>
        <div class="field"><label>Pixel Meta (Facebook)</label><input name="fb_pixel" value="<?= e($org['fb_pixel']) ?>" placeholder="123456789012345"></div>
      </div>
    </div>
    <div class="card">
      <h2>API & webhooks</h2>
      <p class="muted" style="font-size:14px">Pour brancher ton CRM, Zapier, Make ou ton logiciel : à chaque commande payée, on envoie les infos à ton URL (signées avec la clé secrète).</p>
      <div class="field"><label>URL de webhook (https)</label><input name="webhook_url" value="<?= e($org['webhook_url']) ?>" placeholder="https://hooks.zapier.com/…"><?php if ($org['webhook_secret']): ?><small>Clé de signature (en-tête X-Entrevo-Signature, HMAC-SHA256) : <span class="mono"><?= e($org['webhook_secret']) ?></span></small><?php endif; ?></div>
      <div class="field mb0"><label>Clé API</label>
        <?php if ($org['api_key']): ?><input readonly class="mono" value="<?= e($org['api_key']) ?>" onclick="this.select()"><small>À envoyer dans l'en-tête <code>Authorization: Bearer …</code> · Points d'accès : <code>/api/v1/events</code>, <code>/api/v1/orders</code>, <code>/api/v1/tickets?event=ID</code>, <code>POST /api/v1/invitations</code></small><?php endif; ?>
        <button class="btn btn-sm btn-light mt" name="action" value="api_key" formnovalidate data-confirm="Générer une nouvelle clé API ?"><?= $org['api_key'] ? 'Régénérer la clé' : 'Générer une clé API' ?></button></div>
    </div>
    <div class="card">
      <h2>Infos légales & reversements</h2>
      <p class="muted">On s'en sert pour te reverser l'argent de tes ventes. Promis, on ne le dépense pas en confettis.</p>
      <div class="grid2">
        <div class="field"><label>Raison sociale</label><input name="legal_name" value="<?= e($org['legal_name']) ?>"></div>
        <div class="field"><label>SIRET / RNA</label><input name="siret" value="<?= e($org['siret']) ?>"></div>
        <div class="field"><label>N° TVA</label><input name="vat_number" value="<?= e($org['vat_number']) ?>"></div>
        <div class="field"><label>Taux de TVA par défaut sur tes billets</label><select name="vat_rate"><option value="">Non assujetti</option><?php foreach (['0' => '0 %', '2.1' => '2,1 %', '5.5' => '5,5 % (spectacles)', '10' => '10 %', '20' => '20 %'] as $k => $l): ?><option value="<?= $k ?>" <?= $org['vat_rate'] !== null && (float)$org['vat_rate'] === (float)$k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><small>Modifiable tarif par tarif. Sert aux reçus et au récap de TVA.</small></div>
        <div class="field"><label>Adresse</label><input name="address" value="<?= e($org['address']) ?>"></div>
        <div class="field"><label>IBAN</label><input name="iban" value="<?= e($org['iban']) ?>" class="mono"></div>
        <div class="field"><label>BIC</label><input name="bic" value="<?= e($org['bic']) ?>" class="mono"></div>
      </div>
    </div>
  </div>
  <div class="card" id="notifications">
    <h2>Notifications par e-mail</h2>
    <div class="field"><label for="recap">Récap des ventes</label>
      <select id="recap" name="recap"><option value="">Jamais</option><option value="daily" <?= $org['recap'] === 'daily' ? 'selected' : '' ?>>Chaque matin (ventes de la veille)</option><option value="weekly" <?= $org['recap'] === 'weekly' ? 'selected' : '' ?>>Chaque lundi (ventes de la semaine)</option></select>
      <small>Envoyé à <?= e($org['email']) ?> et aux responsables du compte. Les alertes de remplissage (50 %, 80 %, complet) partent toujours.</small></div>
    <button class="btn btn-sm btn-light" name="action" value="recap_test">M'envoyer un exemple maintenant</button>
  </div>
  <div class="card">
    <h2>Qui paie les frais ?</h2>
    <p class="muted">Commission : <b><?= e(rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',')) ?> % + <?= e(money($fixed)) ?></b> par billet payant. Billets gratuits : 0 €.</p>
    <label class="check" style="margin-bottom:12px"><input type="radio" name="fee_mode" value="buyer" <?= $org['fee_mode'] === 'buyer' ? 'checked' : '' ?>> <span><b>L'acheteur</b><br><small>Billet à 20 € → l'acheteur paie <?= e(money(2000 + Tickets::computeFees($org, 2000, 1))) ?>, tu reçois 20,00 €.</small></span></label>
    <label class="check"><input type="radio" name="fee_mode" value="organizer" <?= $org['fee_mode'] === 'organizer' ? 'checked' : '' ?>> <span><b>Moi, l'organisateur</b><br><small>Billet à 20 € → l'acheteur paie 20,00 €, tu reçois <?= e(money(2000 - Tickets::computeFees($org, 2000, 1))) ?>.</small></span></label>
  </div>
</div>
<div class="form-actions mt"><button class="btn btn-lg">Enregistrer</button></div>
<div class="split mt">
<?= soon_card('Ta marque', [
  'domaine-perso' => ['Domaine personnalisé', 'billetterie.ton-site.fr avec tes couleurs'],
  'mini-site' => ['Mini-site d\'événement', 'Line-up, plan, FAQ, partenaires'],
  'multilingue' => ['Billetterie en plusieurs langues', 'Pour accueillir les touristes'],
]) ?>
<?= soon_card('Connexions', [
  'stripe-connect' => ['Paiement direct (Stripe Connect)', 'Les ventes arrivent directement sur ton compte'],
  'google-sheets' => ['Google Sheets', 'Commandes synchronisées dans un tableur'],
  'emailing-externe' => ['Mailchimp & Brevo', 'Tes inscrits envoyés vers ton outil d\'e-mailing'],
  'zapier' => ['Zapier, Make & n8n', 'Relier Entrevo à 5 000 applis'],
  'pixels-avances' => ['Suivi publicitaire avancé', 'Conversions Meta, Google et TikTok côté serveur'],
]) ?>
</div>
</form>
