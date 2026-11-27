<?php /** @var array $list, $me, $k, $fails, $suspicious, $log; string $q, $role, $f */
$res = ['ok' => ['Réussie', 'green'], 'fail' => ['Mot de passe faux', 'red'], 'bad_code' => ['Code 2FA faux', 'red'], 'locked' => ['Verrouillé', 'orange'], 'disabled' => ['Compte désactivé', 'grey'], 'unknown' => ['E-mail inconnu', 'grey']]; ?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Comptes actifs</span><b><?= (int)$k['active'] ?></b><small class="muted">sur <?= (int)$k['n'] ?></small></div>
  <div class="kpi-mini"><span>Équipe plateforme</span><b><?= (int)$k['admins'] ?></b></div>
  <div class="kpi-mini"><span>Double authentification</span><b><?= (int)$k['n'] ? round((int)$k['mfa'] / (int)$k['n'] * 100) : 0 ?> %</b><small class="muted"><?= (int)$k['mfa'] ?> compte(s)</small></div>
  <div class="kpi-mini<?= (int)$k['locked'] ? ' kpi-alert' : '' ?>"><span>Verrouillés</span><b><?= (int)$k['locked'] ?></b></div>
  <div class="kpi-mini<?= (int)$fails['fails'] >= 20 ? ' kpi-alert' : '' ?>"><span>Échecs (24 h)</span><b><?= (int)$fails['fails'] ?></b><small class="muted"><?= (int)$fails['ips'] ?> adresse(s) IP</small></div>
</div>
<?php if ($suspicious): ?><div class="notice warn mb"><b>Tentatives suspectes</b> ces dernières 24 h : <?= implode(' · ', array_map(fn($s) => e($s['ip']) . ' (' . (int)$s['n'] . ' échecs, ' . (int)$s['emails'] . ' compte' . ((int)$s['emails'] > 1 ? 's' : '') . ')', $suspicious)) ?>. Les comptes visés se verrouillent automatiquement.</div><?php endif; ?>
<div class="split-3">
  <div>
    <form class="card filters-card" method="get"><div class="filters-grid">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, e-mail, organisateur…" class="span2">
      <select name="role"><option value="">Tous les rôles</option><?php foreach (['superadmin', 'owner', 'manager', 'scanner'] as $r): ?><option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option><?php endforeach; ?></select>
      <select name="f" onchange="this.form.submit()"><option value="">Tous</option><option value="no2fa" <?= $f === 'no2fa' ? 'selected' : '' ?>>Responsables sans 2FA</option><option value="locked" <?= $f === 'locked' ? 'selected' : '' ?>>Verrouillés</option><option value="dormant" <?= $f === 'dormant' ? 'selected' : '' ?>>Inactifs depuis 6 mois</option><option value="inactive" <?= $f === 'inactive' ? 'selected' : '' ?>>Désactivés</option></select>
    </div></form>
    <div class="card table-card"><div class="table-wrap u-ovis"><table class="table users-table">
      <thead><tr><th>Compte</th><th>Rôle</th><th>Sécurité</th><th>Dernière connexion</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($list as $m): $locked = $m['locked_until'] && strtotime($m['locked_until']) > time(); $mine = (int)$m['id'] === (int)$me['id']; ?>
        <tr style="<?= $m['active'] ? '' : 'opacity:.5' ?>"><td><b><?= e($m['name']) ?></b><?= $mine ? ' <span class="badge grey">toi</span>' : '' ?><br><small class="muted"><?= e($m['email']) ?></small></td>
          <td><?= e(role_label($m['role'])) ?><br><small class="muted"><?= $m['role'] === 'superadmin' ? ($m['admin_perms'] === null || $m['admin_perms'] === '' ? 'accès complet' : 'accès limité') : e((string)$m['org_name']) ?></small></td>
          <td><?= (int)$m['totp_enabled'] ? '<span class="badge green">2FA</span>' : '<span class="badge grey">mot de passe seul</span>' ?><?= $locked ? ' <span class="badge red">Verrouillé</span>' : '' ?><br><small class="muted"><?= (int)$m['sessions'] ?> appareil(s)</small></td>
          <td class="nowrap"><small><?= $m['last_login'] ? e(ago($m['last_login'])) : 'jamais' ?><?= $m['last_ip'] ? '<br><span class="muted">' . e($m['last_ip']) . '</span>' : '' ?></small></td>
          <td class="right"><details class="row-menu"><summary class="btn btn-sm btn-light">Gérer</summary><div class="row-menu-pop">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
              <button name="action" value="reset_link">Envoyer un lien de mot de passe</button>
              <?php if ((int)$m['sessions']): ?><button name="action" value="revoke">Déconnecter partout</button><?php endif; ?>
              <?php if ($locked): ?><button name="action" value="unlock">Déverrouiller</button><?php endif; ?>
              <?php if (!$mine && (int)$m['totp_enabled']): ?><button name="action" value="reset_2fa" data-confirm="Réinitialiser la double authentification (téléphone perdu) ?">Réinitialiser la 2FA</button><?php endif; ?>
              <?php if (!$mine): ?><button name="action" value="toggle" class="<?= $m['active'] ? 'danger-text' : '' ?>"><?= $m['active'] ? 'Désactiver le compte' : 'Réactiver le compte' ?></button><?php endif; ?>
            </form>
            <?php if ($m['role'] === 'superadmin' && !$mine): $pp = $m['admin_perms'] === null || $m['admin_perms'] === '' ? null : explode(',', (string)$m['admin_perms']); ?>
              <form method="post" class="perm-form"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="action" value="perms">
                <label class="check"><input type="radio" name="scope" value="full" <?= $pp === null ? 'checked' : '' ?>> <span>Accès complet</span></label>
                <label class="check"><input type="radio" name="scope" value="limited" <?= $pp !== null ? 'checked' : '' ?>> <span>Limité à :</span></label>
                <?php foreach (Security::AREAS as $ak => $al): ?><label class="check sub"><input type="checkbox" name="perms[]" value="<?= $ak ?>" <?= $pp && in_array($ak, $pp, true) ? 'checked' : '' ?>> <span><?= e($al) ?></span></label><?php endforeach; ?>
                <button class="btn btn-sm">Enregistrer les droits</button></form>
            <?php endif; ?>
          </div></details></td></tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="5" class="empty">Aucun compte.</td></tr><?php endif; ?>
      </tbody>
    </table></div></div>
    <div class="card table-card"><div class="card-head"><h2>Journal des connexions</h2><small class="muted">30 dernières tentatives</small></div><div class="table-wrap"><table class="table table-sm"><tbody>
      <?php foreach ($log as $l): [$rl, $rc] = $res[$l['result']] ?? [$l['result'], 'grey']; ?><tr><td class="nowrap"><small><?= e(date('d/m H:i', strtotime($l['created_at']))) ?></small></td><td><small><?= e((string)($l['name'] ?: $l['email'])) ?></small></td><td><small class="muted"><?= e((string)$l['ip']) ?> · <?= e(Security::device((string)$l['ua'])) ?></small></td><td><span class="badge <?= $rc ?>"><?= e($rl) ?></span></td></tr><?php endforeach; ?>
      <?php if (!$log): ?><tr><td class="empty">Rien pour l'instant.</td></tr><?php endif; ?>
    </tbody></table></div></div>
  </div>
  <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add_admin">
    <h2>+ Équipe plateforme</h2>
    <p class="muted u-fs13">Pour un associé, un stagiaire au support, un comptable… avec seulement les rubriques nécessaires.</p>
    <div class="field"><label>Nom</label><input name="name" required></div>
    <div class="field"><label>E-mail</label><input type="email" name="email" required></div>
    <div class="field"><label>Mot de passe provisoire</label><input type="password" name="password" minlength="10" required autocomplete="new-password"></div>
    <label class="check"><input type="radio" name="scope" value="full" checked> <span>Accès complet</span></label>
    <label class="check"><input type="radio" name="scope" value="limited"> <span>Limité à :</span></label>
    <?php foreach (Security::AREAS as $ak => $al): ?><label class="check sub"><input type="checkbox" name="perms[]" value="<?= $ak ?>"> <span><?= e($al) ?></span></label><?php endforeach; ?>
    <button class="btn btn-block mt">Ajouter</button>
    <p class="muted mt mb0 u-fs13">Double authentification obligatoire pour l'équipe : <a href="<?= url('/admin/reglages', ['tab' => 'securite']) ?>"><?= setting('admin_2fa') === '1' ? 'activée' : 'à activer' ?></a>.</p>
  </form>
</div>
