<?php
$s = fn($k) => e(setting($k));
$c = fn($k) => setting($k) === '1' ? 'checked' : '';
$tabs = ['general' => '⚙️ Général', 'fees' => '💸 Commission', 'payments' => '💳 Paiements', 'emails' => '📬 E-mails', 'legal' => '📜 Textes légaux'];
$secret = fn($k) => setting($k) ? '•••••• (enregistré, laisser vide pour ne pas changer)' : '';
?>
<nav class="tabs"><?php foreach ($tabs as $k => $l): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= url('/admin/reglages', ['tab' => $k]) ?>"><?= $l ?></a><?php endforeach; ?></nav>
<form method="post" action="<?= url('/admin/reglages', ['tab' => $tab]) ?>"><?= csrf_field() ?>
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
    <h2>⏱️ Tâche planifiée (recommandé)</h2>
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
    <h2>🪪 Vérification des organisateurs (KYC)</h2>
    <label class="check"><input type="checkbox" name="kyc_required" value="1" <?= $c('kyc_required') ?>> <span>Bloquer les reversements tant que les justificatifs ne sont pas vérifiés<br><small class="muted">Pièce d'identité, Kbis/statuts et RIB, déposés par l'organisateur dans « Vérification du compte ».</small></span></label>
  </div>
  <div class="card" style="max-width:820px">
    <h2>🧾 Factures de commission</h2>
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
  <div class="notice mb" style="max-width:820px">🔔 Adresses de notification (webhooks) à déclarer chez chaque prestataire, pour que les commandes soient validées même si l'acheteur ferme son navigateur :<br>
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
    <div class="card"><h2>🧪 Paiement de démonstration</h2>
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
<?= soon_card('🧪 Réglages à venir', [
  'maintenance' => ['Mode maintenance', 'Mettre le site en pause avec une page d\'attente'],
  'connexion-sociale' => ['Connexion Google & Apple', 'Sans mot de passe, pour tout le monde'],
  'langues' => ['Langues de la plateforme', 'Anglais, espagnol, allemand…'],
  'marque-blanche' => ['Marque blanche', 'La billetterie sous la marque d\'un client'],
  'contenus' => ['Page d\'accueil personnalisable', 'Mises en avant, bannières, pages libres'],
], 'admin') ?>
<?php elseif ($tab === 'fees'): ?>
<?= soon_card('🧪 Commissions avancées', [
  'commission-paliers' => ['Commission par palier ou catégorie', 'Dégressive selon le volume'],
  'formules' => ['Formules organisateurs', 'Gratuit, Pro, Premium avec abonnement'],
], 'admin') ?>
<?php elseif ($tab === 'payments'): ?>
<?= soon_card('🧪 Paiements à venir', [
  'litiges' => ['Gestion des litiges', 'Contestations de paiement et preuves automatiques'],
  'fraude' => ['Détection de fraude', 'Alertes sur les achats suspects'],
  'revendeurs' => ['Points de vente physiques', 'Bars, disquaires, offices de tourisme'],
], 'admin') ?>
<?php elseif ($tab === 'emails'): ?>
<?= soon_card('🧪 E-mails à venir', [
  'emails-modeles' => ['Modèles d\'e-mails modifiables', 'Textes de confirmation, rappel, remboursement…'],
  'delivrabilite' => ['Délivrabilité', 'Vérification SPF, DKIM, DMARC et rebonds'],
], 'admin') ?>
<?php endif; ?>
