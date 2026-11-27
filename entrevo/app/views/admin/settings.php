<?php
$s = fn($k) => e(setting($k));
$c = fn($k) => setting($k) === '1' ? 'checked' : '';
$tabs = ['general' => 'Général', 'apparence' => 'Apparence', 'fees' => 'Commission', 'payments' => 'Paiements', 'emails' => 'E-mails', 'sms' => 'SMS', 'plateforme' => 'Plateforme', 'securite' => 'Sécurité', 'legal' => 'Textes légaux'];
$secret = fn($k) => setting($k) ? '•••••• (enregistré, laisser vide pour ne pas changer)' : '';
?>
<nav class="tabs"><?php foreach ($tabs as $k => $l): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= url('/admin/reglages', ['tab' => $k]) ?>"><?= $l ?></a><?php endforeach; ?></nav>
<form method="post" action="<?= url('/admin/reglages', ['tab' => $tab]) ?>" enctype="multipart/form-data"><?= csrf_field() ?>
<?php if ($tab === 'general'): ?>
  <div class="card" style="max-width:820px">
    <div class="grid2">
      <div class="field"><label>Nom de la plateforme</label><input name="platform_name" value="<?= $s('platform_name') ?>" required></div>
      <div class="field"><label>Slogan</label><input name="tagline" value="<?= $s('tagline') ?>"></div>
      <div class="field"><label>E-mail de contact</label><input type="email" name="contact_email" value="<?= $s('contact_email') ?>"><small>Reçoit les alertes (nouvel organisateur…).</small></div>
      <div class="field"><label>Adresse du site</label><input name="base_url" value="<?= $s('base_url') ?>" placeholder="https://billetterie.mondomaine.fr"><small>Utilisée dans les e-mails et pour revenir des paiements.</small></div>
      <div class="field"><label>Couleur d'accent</label><input type="color" name="primary_color" value="<?= $s('primary_color') ?>"></div>
      <div class="field"><label>Places bloquées pendant le paiement (minutes)</label><input type="number" name="pending_minutes" min="5" max="60" value="<?= $s('pending_minutes') ?>"></div>
    </div>
    <label class="check"><input type="checkbox" name="signup_open" value="1" <?= $c('signup_open') ?>> Inscription libre des organisateurs</label>
    <label class="check"><input type="checkbox" name="org_approval" value="1" <?= $c('org_approval') ?>> <span>Valider moi-même chaque nouvel organisateur<br><small class="muted">Il peut préparer ses événements, mais pas les publier tant que tu ne l'as pas validé (Organisateurs › À valider).</small></span></label>
  </div>
  <div class="card" style="max-width:820px">
    <h2>Tâche planifiée (recommandé)</h2>
    <p class="muted" style="font-size:14px">Les tâches de fond (paiements à revérifier, liste d'attente, publications programmées…) tournent toutes seules quand le site a des visites. Pour qu'elles tournent même la nuit, ajoute cette commande dans cPanel › « Tâches Cron », toutes les 5 minutes :</p>
    <input readonly class="mono" onclick="this.select()" value="wget -q -O /dev/null &quot;<?= e(abs_url('/cron', ['cle' => Jobs::cronKey()])) ?>&quot;">
  </div>
<?php elseif ($tab === 'fees'): ?>
  <div class="card" style="max-width:820px">
    <p class="muted">Taux par défaut pour tous les organisateurs (tu peux faire un prix perso à chacun dans sa fiche). Seuls les billets payants vendus en ligne sont concernés.</p>
    <div class="grid3">
      <div class="field"><label>Pourcentage</label><div class="input-suffix"><input name="fee_percent" value="<?= $s('fee_percent') ?>" inputmode="decimal"><span>%</span></div></div>
      <div class="field"><label>Fixe par billet</label><div class="input-suffix"><input name="fee_fixed" value="<?= e(money_input((int)setting('fee_fixed'))) ?>" inputmode="decimal"><span>€</span></div></div>
      <div class="field"><label>Payée par défaut par</label><select name="fee_mode_default"><option value="buyer" <?= setting('fee_mode_default') === 'buyer' ? 'selected' : '' ?>>L'acheteur</option><option value="organizer" <?= setting('fee_mode_default') === 'organizer' ? 'selected' : '' ?>>L'organisateur</option></select><small>Chaque organisateur peut changer.</small></div>
    </div>
    <label class="check"><input type="checkbox" name="refund_fees" value="1" <?= $c('refund_fees') ?>> Rembourser aussi les frais de service lors d'un remboursement</label>
    <?php $ex = ['fee_percent' => null, 'fee_fixed' => null]; ?>
    <div class="notice mt">Exemple : billet à 20 € → commission de <b><?= e(money(Tickets::computeFees($ex, 2000, 1))) ?></b>.</div>
  </div>
  <div class="card" style="max-width:820px">
    <h2>Vérification des organisateurs (KYC)</h2>
    <label class="check"><input type="checkbox" name="kyc_required" value="1" <?= $c('kyc_required') ?>> <span>Bloquer les reversements tant que les justificatifs ne sont pas vérifiés<br><small class="muted">Pièce d'identité, Kbis/statuts et RIB, déposés par l'organisateur dans « Vérification du compte ».</small></span></label>
  </div>
  <div class="card" style="max-width:820px">
    <h2>Factures de commission</h2>
    <p class="muted">Tes informations d'émetteur, imprimées sur chaque facture.</p>
    <div class="grid2">
      <div class="field"><label>Raison sociale</label><input name="invoice_name" value="<?= $s('invoice_name') ?>" placeholder="Entrevo SAS"></div>
      <div class="field"><label>Préfixe des numéros</label><input name="invoice_prefix" value="<?= e((string)setting('invoice_prefix', 'COM')) ?>" maxlength="8"></div>
    </div>
    <div class="field"><label>Adresse</label><textarea name="invoice_address" rows="2"><?= $s('invoice_address') ?></textarea></div>
    <div class="grid3">
      <div class="field"><label>SIRET</label><input name="invoice_siret" value="<?= $s('invoice_siret') ?>"></div>
      <div class="field"><label>N° de TVA</label><input name="invoice_vat_number" value="<?= $s('invoice_vat_number') ?>"></div>
      <div class="field"><label>Taux de TVA</label><div class="input-suffix"><input name="invoice_vat_rate" value="<?= e((string)setting('invoice_vat_rate', '20')) ?>" inputmode="decimal"><span>%</span></div><small>0 = non assujetti (mention 293 B)</small></div>
    </div>
    <div class="field"><label>Mentions de bas de page <small class="muted">(capital, RCS…)</small></label><input name="invoice_footer" value="<?= $s('invoice_footer') ?>"></div>
    <label class="check"><input type="checkbox" name="invoice_auto" value="1" <?= $c('invoice_auto') ?>> Générer et envoyer automatiquement les factures le 1er de chaque mois</label>
  </div>
<?php elseif ($tab === 'payments'): ?>
  <div class="notice mb" style="max-width:820px">Adresses de notification (webhooks) à déclarer chez chaque prestataire, pour que les commandes soient validées même si l'acheteur ferme son navigateur :<br>
    Stripe (événement « checkout.session.completed ») : <code><?= e(abs_url('/paiement/webhook/stripe')) ?></code><br>
    PayPal (« CHECKOUT.ORDER.APPROVED » et « PAYMENT.CAPTURE.COMPLETED ») : <code><?= e(abs_url('/paiement/webhook/paypal')) ?></code><br>
    SumUp et Mollie : automatique.</div>
  <div class="notice warn mb" style="max-width:820px">Les paiements arrivent sur <b>tes</b> comptes. Fais toujours un achat test avec les clés de test du prestataire avant d'activer les vraies clés.</div>
  <div class="split" style="max-width:1100px">
    <div class="card"><h2>Stripe (CB, Apple Pay, Google Pay)</h2>
      <label class="check mb"><input type="checkbox" name="stripe_enabled" value="1" <?= $c('stripe_enabled') ?>> Activer</label>
      <div class="field"><label>Clé secrète (sk_live_… ou sk_test_…)</label><input name="stripe_secret" placeholder="<?= e($secret('stripe_secret')) ?>" autocomplete="off"></div>
      <small class="muted">Tableau de bord Stripe › Développeurs › Clés API.</small></div>
    <div class="card"><h2>PayPal</h2>
      <label class="check mb"><input type="checkbox" name="paypal_enabled" value="1" <?= $c('paypal_enabled') ?>> Activer</label>
      <div class="field"><label>Client ID</label><input name="paypal_client_id" value="<?= $s('paypal_client_id') ?>" autocomplete="off"></div>
      <div class="field"><label>Secret</label><input name="paypal_secret" placeholder="<?= e($secret('paypal_secret')) ?>" autocomplete="off"></div>
      <label class="check"><input type="checkbox" name="paypal_sandbox" value="1" <?= $c('paypal_sandbox') ?>> Mode test (sandbox)</label>
      <small class="muted">developer.paypal.com › Apps & Credentials.</small></div>
    <div class="card"><h2>SumUp</h2>
      <label class="check mb"><input type="checkbox" name="sumup_enabled" value="1" <?= $c('sumup_enabled') ?>> Activer</label>
      <div class="field"><label>Clé API (sup_sk_…)</label><input name="sumup_api_key" placeholder="<?= e($secret('sumup_api_key')) ?>" autocomplete="off"></div>
      <div class="field"><label>Code marchand</label><input name="sumup_merchant_code" value="<?= $s('sumup_merchant_code') ?>"></div>
      <small class="muted">Le paiement hébergé (« hosted checkout ») doit être activé sur ton compte SumUp.</small></div>
    <div class="card"><h2>Mollie</h2>
      <label class="check mb"><input type="checkbox" name="mollie_enabled" value="1" <?= $c('mollie_enabled') ?>> Activer</label>
      <div class="field"><label>Clé API (live_… ou test_…)</label><input name="mollie_api_key" placeholder="<?= e($secret('mollie_api_key')) ?>" autocomplete="off"></div></div>
    <div class="card"><h2>Paiement en plusieurs fois</h2>
      <label class="check mb"><input type="checkbox" name="alma_enabled" value="1" <?= $c('alma_enabled') ?>> Activer Alma (2, 3 ou 4 fois sans frais pour l'acheteur)</label>
      <div class="field"><label>Clé API Alma (sk_live_… ou sk_test_…)</label><input name="alma_api_key" placeholder="<?= e($secret('alma_api_key')) ?>" autocomplete="off"></div>
      <div class="grid3"><div class="field"><label>Montant mini</label><input name="alma_min" value="<?= setting('alma_min') ? e(money_input((int)setting('alma_min'))) : '' ?>" placeholder="50"></div><div class="field"><label>Montant maxi</label><input name="alma_max" value="<?= setting('alma_max') ? e(money_input((int)setting('alma_max'))) : '' ?>" placeholder="2000"></div><div class="field"><label>Échéances</label><input name="alma_counts" value="<?= $s('alma_counts') ?: '3' ?>" placeholder="2,3,4"></div></div>
      <small class="muted">L'organisateur est payé en une fois, Alma porte le risque d'impayé. Frais Alma à la charge du marchand.</small>
      <label class="check mt"><input type="checkbox" name="stripe_klarna" value="1" <?= $c('stripe_klarna') ?>> Proposer Klarna dans le paiement Stripe (à activer d'abord dans ton tableau de bord Stripe)</label>
      <label class="check"><input type="checkbox" name="paypal_paylater" value="1" <?= $c('paypal_paylater') ?>> Mettre en avant le « paiement en 4 fois » PayPal</label>
      <small class="muted">Mollie : in3 et Klarna s'affichent tout seuls sur sa page de paiement s'ils sont activés sur ton compte.</small></div>
    <div class="card"><h2>Billets dans le Wallet</h2>
      <label class="check mb"><input type="checkbox" name="wallet_apple" value="1" <?= $c('wallet_apple') ?>> Apple Wallet</label>
      <div class="grid2"><div class="field"><label>Pass Type ID</label><input name="apple_pass_type" value="<?= $s('apple_pass_type') ?>" placeholder="pass.fr.monsite.billet"></div><div class="field"><label>Team ID</label><input name="apple_team_id" value="<?= $s('apple_team_id') ?>"></div></div>
      <div class="grid2"><div class="field"><label>Certificat du pass (.p12) <?= setting('apple_cert_file') ? '· chargé' : '' ?></label><input type="file" name="apple_cert" accept=".p12"></div><div class="field"><label>Mot de passe du .p12</label><input name="apple_pass_password" placeholder="<?= e($secret('apple_pass_password')) ?>" autocomplete="off"></div></div>
      <div class="field"><label>Certificat Apple WWDR (.pem) <?= setting('apple_wwdr_file') ? '· chargé' : '' ?></label><input type="file" name="apple_wwdr" accept=".pem,.cer"></div>
      <label class="check mb mt"><input type="checkbox" name="wallet_google" value="1" <?= $c('wallet_google') ?>> Google Wallet</label>
      <div class="grid2"><div class="field"><label>Issuer ID</label><input name="google_issuer_id" value="<?= $s('google_issuer_id') ?>"></div><div class="field"><label>E-mail du compte de service</label><input name="google_sa_email" value="<?= $s('google_sa_email') ?>"></div></div>
      <div class="field"><label>Clé privée du compte de service (PEM)</label><textarea name="google_sa_key" rows="3" placeholder="<?= e($secret('google_sa_key') ?: '-----BEGIN PRIVATE KEY-----') ?>"></textarea></div>
      <small class="muted">Apple : developer.apple.com › Identifiers › Pass Type IDs. Google : pay.google.com/business/console › Google Wallet API. Les boutons « Ajouter au Wallet » apparaissent sur les billets dès que c'est rempli.</small></div>
    <div class="card"><h2>Paiement de démonstration</h2>
      <label class="check"><input type="checkbox" name="demo_enabled" value="1" <?= $c('demo_enabled') ?>> Activer (aucun argent réel)</label>
      <small class="muted">À couper dès que tu vends pour de vrai !</small></div>
  </div>
<?php elseif ($tab === 'emails'): ?>
  <div class="card" style="max-width:820px">
    <p class="muted">Sans serveur SMTP, l'hébergement envoie avec sa fonction mail() (souvent classé en spam). Chez Dyjix, crée une adresse dans cPanel › Comptes de messagerie et mets ses infos ici.</p>
    <div class="grid2">
      <div class="field"><label>Serveur SMTP</label><input name="smtp_host" value="<?= $s('smtp_host') ?>" placeholder="mail.mondomaine.fr"></div>
      <div class="field"><label>Port</label><input name="smtp_port" value="<?= $s('smtp_port') ?>"></div>
      <div class="field"><label>Sécurité</label><select name="smtp_secure"><?php foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'Aucune'] as $k => $l): ?><option value="<?= $k ?>" <?= setting('smtp_secure') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Identifiant</label><input name="smtp_user" value="<?= $s('smtp_user') ?>" autocomplete="off"></div>
      <div class="field"><label>Mot de passe</label><input type="password" name="smtp_pass" placeholder="<?= e($secret('smtp_pass')) ?>" autocomplete="new-password"></div>
      <div class="field"><label>Adresse d'expédition</label><input name="mail_from" value="<?= $s('mail_from') ?>" placeholder="billets@mondomaine.fr"></div>
      <div class="field"><label>Nom d'expéditeur</label><input name="mail_from_name" value="<?= $s('mail_from_name') ?>" placeholder="<?= $s('platform_name') ?>"></div>
    </div>
    <div class="row mt"><input type="email" name="test_email" placeholder="Envoyer un test à…" style="max-width:300px"><button class="btn btn-ghost" name="send_test" value="1">Enregistrer et tester</button></div>
  </div>
<?php elseif ($tab === 'sms'): ?>
  <?php $smsStats = one("SELECT COALESCE(SUM(sent), 0) sent, COALESCE(SUM(sent * segments), 0) seg, COALESCE(SUM(cost), 0) cost FROM sms_campaigns WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"); ?>
  <div class="card" style="max-width:820px">
    <p class="muted">Les campagnes SMS des organisateurs passent par l'API SMS de <b>Brevo</b> (compte Brevo › SMS › crédits, puis Paramètres › Clés API). Sans clé, tout fonctionne en <b>mode test</b> : rien ne part, chaque SMS est écrit dans le journal « sms ».</p>
    <div class="grid2">
      <div class="field"><label>Clé API Brevo</label><input type="password" name="sms_api_key" placeholder="<?= e($secret('sms_api_key')) ?: 'xkeysib-…' ?>" autocomplete="new-password"><?php if (Sms::live()): ?><label class="check mt"><input type="checkbox" name="sms_key_clear" value="1"> <span>Retirer la clé (repasser en mode test)</span></label><?php endif; ?></div>
      <div class="field"><label>Prix facturé par SMS</label><div class="input-suffix"><input name="sms_price" value="<?= e(money_input(Sms::price())) ?>" inputmode="decimal"><span>€</span></div><small>Par tranche de 160 caractères, affiché aux organisateurs.</small></div>
      <div class="field"><label>Refacturation</label><label class="check"><input type="checkbox" name="sms_deduct" value="1" <?= setting('sms_deduct', '1') === '1' ? 'checked' : '' ?>> <span>Retenir le coût des SMS sur les reversements</span></label></div>
      <div class="field"><label>Mention de désinscription</label><input name="sms_stop" value="<?= e(Sms::stopText()) ?>"><small>Ajoutée à la fin de chaque SMS (obligatoire en France pour les SMS commerciaux).</small></div>
      <div class="field"><label>Ce mois-ci</label><p class="mb0"><b><?= number_format((int)$smsStats['sent'], 0, ',', ' ') ?></b> destinataires · <b><?= number_format((int)$smsStats['seg'], 0, ',', ' ') ?></b> SMS · <b><?= e(money((int)$smsStats['cost'])) ?></b> à refacturer</p></div>
    </div>
    <div class="row mt"><input type="tel" name="test_sms" placeholder="Envoyer un test au 06…" style="max-width:300px"><button class="btn btn-ghost">Enregistrer et tester</button></div>
  </div>
<?php elseif ($tab === 'plateforme'): ?>
  <div class="card" style="max-width:820px">
    <h2>Mode maintenance</h2>
    <label class="check"><input type="checkbox" name="maintenance" value="1" <?= $c('maintenance') ?>> <span><b>Mettre le site en maintenance</b><br><small class="muted">Le public voit une page d'attente. Restent ouverts : ton super-admin, l'appli de scan (le jour J n'est jamais bloqué), les retours de paiement.</small></span></label>
    <div class="field mt"><label>Message affiché</label><textarea name="maintenance_message" rows="3" placeholder="La billetterie fait une courte pause pour maintenance…"><?= $s('maintenance_message') ?></textarea></div>
  </div>
  <div class="card" style="max-width:820px">
    <h2>Limites</h2>
    <div class="grid2">
      <div class="field"><label>Événements à venir par organisateur</label><input type="number" name="max_events" min="0" value="<?= (int)setting('max_events', '0') ?>"><small>0 = illimité. Évite les abus des comptes gratuits.</small></div>
      <div class="field"><label>Sous-domaine Entrevo Pay (cashless)</label><input name="pay_host" value="<?= e((string)setting('pay_host', '')) ?>" placeholder="pay.mondomaine.fr"><small>Crée le sous-domaine chez ton hébergeur (Dyjix : Domaines › Sous-domaines) en le faisant pointer vers le même dossier qu'Entrevo, active le certificat SSL, puis saisis-le ici. Vide : Entrevo Pay reste sur <?= e(abs_url('/pay')) ?>.</small></div>
      <div class="field"><label>Poids maximum des images</label><div class="input-suffix"><input type="number" name="max_upload_mb" min="1" max="50" value="<?= (int)(setting('max_upload_mb', '5') ?: 5) ?>"><span>Mo</span></div><small>Limité aussi par l'hébergement (<?= e((string)ini_get('upload_max_filesize')) ?>).</small></div>
    </div>
  </div>
  <div class="card" style="max-width:820px">
    <h2>Modules coupés pour tout le monde</h2>
    <p class="muted" style="font-size:14px">Un module coupé ici disparaît chez tous les organisateurs, quelle que soit leur formule (utile tant qu'un service n'est pas branché).</p>
    <?php $dis = explode(',', (string)setting('disabled_modules', '')); ?>
    <div class="mod-grid"><?php foreach (Billing::MODULES as $mk => $ml): ?><label class="check"><input type="checkbox" name="disabled_modules[]" value="<?= $mk ?>" <?= in_array($mk, $dis, true) ? 'checked' : '' ?>> <span><?= e($ml) ?></span></label><?php endforeach; ?></div>
  </div>
<?php elseif ($tab === 'securite'): ?>
  <?php $sk = one("SELECT SUM(role = 'superadmin') admins, SUM(role = 'superadmin' AND totp_enabled = 1) admins2fa, SUM(role = 'owner') owners, SUM(role = 'owner' AND totp_enabled = 1) owners2fa FROM users WHERE active = 1"); ?>
  <div class="card" style="max-width:820px">
    <h2>Double authentification</h2>
    <p class="muted" style="font-size:14px">Équipe plateforme : <b><?= (int)$sk['admins2fa'] ?> / <?= (int)$sk['admins'] ?></b> avec 2FA · Responsables d'organisateurs : <b><?= (int)$sk['owners2fa'] ?> / <?= (int)$sk['owners'] ?></b>.</p>
    <label class="check"><input type="checkbox" name="admin_2fa" value="1" <?= $c('admin_2fa') ?>> <span><b>Obligatoire pour l'équipe plateforme</b><br><small class="muted">Un super-admin sans 2FA est envoyé sur « Mon compte » pour l'activer avant d'accéder au super-admin. Active-la d'abord sur ton compte.</small></span></label>
  </div>
  <div class="card" style="max-width:820px">
    <h2>Verrouillage des comptes</h2>
    <div class="grid2">
      <div class="field"><label>Verrouiller après</label><div class="input-suffix"><input type="number" name="lock_after" min="3" max="50" value="<?= (int)(setting('lock_after', '8') ?: 8) ?>"><span>échecs</span></div></div>
      <div class="field"><label>Pendant</label><div class="input-suffix"><input type="number" name="lock_minutes" min="5" max="1440" value="<?= (int)(setting('lock_minutes', '15') ?: 15) ?>"><span>minutes</span></div></div>
    </div>
    <p class="muted mb0" style="font-size:13px">En plus, une même adresse IP est freinée après 5 essais ratés. Le journal complet est dans <a href="<?= url('/admin/utilisateurs') ?>">Utilisateurs & sécurité</a>.</p>
  </div>
<?php elseif ($tab === 'apparence'): ?>
  <div class="card" style="max-width:820px">
    <div class="grid2">
      <div class="field"><label>Nom de la plateforme</label><input name="platform_name" value="<?= $s('platform_name') ?>" required></div>
      <div class="field"><label>Slogan</label><input name="tagline" value="<?= $s('tagline') ?>"><small>Pied de page et titres.</small></div>
      <div class="field"><label>Couleur d'accent</label><input type="color" name="primary_color" value="<?= $s('primary_color') ?>"><small>Liens et éléments actifs ; les boutons restent noirs.</small></div>
      <div class="field"><label>Logo <small class="muted">(carré, PNG transparent)</small></label>
        <div class="row"><span class="logo-preview"><?= view('partials/logo') ?></span><input type="file" name="platform_logo" accept="image/png,image/webp,image/jpeg"></div>
        <?php if (setting('platform_logo')): ?><label class="check mt"><input type="checkbox" name="logo_remove" value="1"> <span>Revenir au logo par défaut</span></label><?php endif; ?></div>
    </div>
  </div>
<?php else: ?>
  <div class="card" style="max-width:900px">
    <div class="field"><label>Conditions générales de vente</label><textarea name="cgv" rows="12"><?= $s('cgv') ?></textarea><small>Si rempli, l'acheteur doit les accepter avant de payer.</small></div>
    <div class="field"><label>Mentions légales</label><textarea name="legal" rows="8"><?= $s('legal') ?></textarea></div>
    <div class="field"><label>Infos société (pied des documents)</label><textarea name="company_info" rows="3"><?= $s('company_info') ?></textarea></div>
  </div>
<?php endif; ?>
<div class="form-actions mt"><button class="btn btn-lg">Enregistrer</button></div>
</form>
<div class="mt"></div>
<?php if ($tab === 'general'): ?>
<?= soon_card('Réglages à venir', [
  'maintenance' => ['Mode maintenance', 'Mettre le site en pause avec une page d\'attente'],
  'connexion-sociale' => ['Connexion Google & Apple', 'Sans mot de passe, pour tout le monde'],
  'langues' => ['Langues de la plateforme', 'Anglais, espagnol, allemand…'],
  'marque-blanche' => ['Marque blanche', 'La billetterie sous la marque d\'un client'],
  'contenus' => ['Page d\'accueil personnalisable', 'Mises en avant, bannières, pages libres'],
], 'admin') ?>
<?php elseif ($tab === 'fees'): ?>
<?= soon_card('Commissions avancées', [
  'commission-paliers' => ['Commission par palier ou catégorie', 'Dégressive selon le volume'],
  'formules' => ['Formules organisateurs', 'Gratuit, Pro, Premium avec abonnement'],
], 'admin') ?>
<?php elseif ($tab === 'payments'): ?>
<?= soon_card('Paiements à venir', [
  'litiges' => ['Gestion des litiges', 'Contestations de paiement et preuves automatiques'],
  'fraude' => ['Détection de fraude', 'Alertes sur les achats suspects'],
  'revendeurs' => ['Points de vente physiques', 'Bars, disquaires, offices de tourisme'],
], 'admin') ?>
<?php elseif ($tab === 'emails'): ?>
<?= soon_card('E-mails à venir', [
  'emails-modeles' => ['Modèles d\'e-mails modifiables', 'Textes de confirmation, rappel, remboursement…'],
  'delivrabilite' => ['Délivrabilité', 'Vérification SPF, DKIM, DMARC et rebonds'],
], 'admin') ?>
<?php endif; ?>
