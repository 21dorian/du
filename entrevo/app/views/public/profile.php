<form class="card" method="post" style="max-width:640px"><?= csrf_field() ?>
  <h2>Mes informations</h2>
  <div class="grid2">
    <div class="field"><label>Nom</label><input name="name" value="<?= e($u['name']) ?>" required></div>
    <div class="field"><label>E-mail de connexion</label><input type="email" name="email" value="<?= e($u['email']) ?>" required></div>
  </div>
  <h3 class="mt">Changer de mot de passe</h3>
  <div class="grid2">
    <div class="field"><label>Mot de passe actuel</label><input type="password" name="current_password" autocomplete="current-password"></div>
    <div class="field"><label>Nouveau mot de passe</label><input type="password" name="new_password" minlength="8" autocomplete="new-password"><small>Laisser vide pour ne pas changer.</small></div>
  </div>
  <button class="btn">Enregistrer</button>
</form>
<?php /** 2.2 : sécurité du compte */ $sec = $sec ?? null; if ($sec): ?>
<div class="card" style="max-width:640px" id="securite">
  <h2>Double authentification</h2>
  <?php if ((int)$u['totp_enabled']): ?>
    <p><span class="badge green">Activée</span> Un code de ton application est demandé à chaque connexion.</p>
    <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="sec" value="totp_disable"><input type="password" name="password" placeholder="Ton mot de passe" required autocomplete="current-password" style="max-width:240px"><button class="btn btn-light">Désactiver</button></form>
  <?php elseif ($sec['pending']): ?>
    <p class="muted">1. Scanne ce QR code avec ton application (Google Authenticator, 1Password, Authy…). 2. Saisis le code affiché.</p>
    <div class="totp-setup"><div class="totp-qr"><?= Qr::svg($sec['uri'], 180) ?></div>
      <div><small class="muted">Ou saisis la clé à la main :</small><p class="mono totp-key"><?= e(trim(chunk_split($sec['pending'], 4, ' '))) ?></p>
        <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="sec" value="totp_confirm"><input name="code" inputmode="numeric" maxlength="7" placeholder="123456" required class="otp-input u-mw160"><button class="btn">Activer</button></form></div></div>
  <?php else: ?>
    <p class="muted">Protège ton compte même si ton mot de passe fuite : en plus du mot de passe, un code change toutes les 30 secondes dans ton téléphone.<?= $u['role'] === 'superadmin' && setting('admin_2fa') === '1' ? ' <b>Obligatoire pour les super-admins.</b>' : '' ?></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="sec" value="totp_start"><button class="btn">Activer la double authentification</button></form>
  <?php endif; ?>
</div>
<div class="card" style="max-width:640px">
  <div class="card-head"><h2>Appareils connectés</h2><?php if (count($sec['sessions']) > 1): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="sec" value="revoke_others"><button class="btn btn-sm btn-light">Déconnecter les autres</button></form><?php endif; ?></div>
  <?php foreach ($sec['sessions'] as $s): $me = $s['sid'] === Security::sid(); ?><div class="list-item"><span class="grow"><b><?= e(Security::device((string)$s['ua'])) ?></b><?= $me ? ' <span class="badge green">Cet appareil</span>' : '' ?><br><small class="muted"><?= e((string)$s['ip']) ?> · actif <?= e(ago($s['last_seen'])) ?></small></span>
    <?php if (!$me): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="sec" value="revoke"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-ghost">Déconnecter</button></form><?php endif; ?></div><?php endforeach; ?>
  <h3 class="mt">Dernières connexions</h3>
  <?php foreach ($sec['logins'] as $l): ?><div class="list-item"><span class="grow"><small><?= e(fr_date($l['created_at'])) ?> · <?= e((string)$l['ip']) ?> · <?= e(Security::device((string)$l['ua'])) ?></small></span><span class="badge <?= $l['result'] === 'ok' ? 'green' : 'red' ?>"><?= e(['ok' => 'Réussie', 'fail' => 'Mot de passe faux', 'bad_code' => 'Code faux', 'locked' => 'Verrouillé', 'disabled' => 'Désactivé'][$l['result']] ?? $l['result']) ?></span></div><?php endforeach; ?>
</div>
<?php endif; ?>
