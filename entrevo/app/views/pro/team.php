<?php if ($created): ?>
<div class="flash success">✅ Compte prêt ! Transmets ces identifiants (ils ne seront plus affichés) :<br>
  <span class="mono">E-mail : <?= e($created['email']) ?> · Mot de passe : <?= e($created['password']) ?></span><br>
  <?= $created['role'] === 'scanner' ? 'Appli de contrôle : ' . e(abs_url('/scan')) : 'Connexion : ' . e(abs_url('/connexion')) ?></div>
<?php endif; ?>
<div class="split-3">
  <div class="card">
    <h2>👥 Ton équipe</h2>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Nom</th><th>Rôle</th><th>Dernière connexion</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($members as $m): $me = $m['id'] == user()['id']; ?>
        <tr style="<?= $m['active'] ? '' : 'opacity:.5' ?>">
          <td><b><?= e($m['name']) ?></b><?= $me ? ' <small>(toi)</small>' : '' ?><br><small><?= e($m['email']) ?></small></td>
          <td><?php if ($me): ?><?= e(role_label($m['role'])) ?><?php else: ?>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
            <select name="role" onchange="this.form.submit()" style="width:auto;padding:6px 8px"><?php foreach (['owner', 'manager', 'scanner'] as $r): ?><option value="<?= $r ?>" <?= $m['role'] === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option><?php endforeach; ?></select></form><?php endif; ?></td>
          <td class="nowrap"><small><?= $m['last_login'] ? e(short_date($m['last_login'])) : 'jamais' ?></small></td>
          <td class="right nowrap"><?php if (!$me): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-sm btn-light" name="action" value="password" data-confirm="Générer un nouveau mot de passe pour <?= e($m['name']) ?> ?">Nouveau mot de passe</button>
            <button class="btn btn-sm btn-light" name="action" value="toggle"><?= $m['active'] ? 'Désactiver' : 'Réactiver' ?></button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="muted mt mb0" style="font-size:14px"><b>Responsable</b> : tout, finances comprises · <b>Gestionnaire</b> : événements, ventes, participants · <b>Contrôleur</b> : uniquement l'appli de scan 📷</p>
  </div>
  <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <h2>+ Ajouter quelqu'un</h2>
    <div class="field"><label>Nom</label><input name="name" required placeholder="Ex. : Accueil porte A"></div>
    <div class="field"><label>E-mail (identifiant)</label><input type="email" name="email" required></div>
    <div class="field"><label>Rôle</label><select name="role"><option value="scanner">Contrôleur (scan des billets)</option><option value="manager">Gestionnaire</option><option value="owner">Responsable</option></select></div>
    <div class="field"><label>Mot de passe</label><input name="password" placeholder="Vide = généré pour toi"></div>
    <button class="btn btn-block">Créer le compte</button>
  </form>
</div>
<?= soon_card('🔐 Sécurité & droits', [
  'roles-perso' => ['Rôles personnalisés', 'Billetterie seulement, compta, lecture seule…'],
  'double-auth' => ['Double authentification', 'Un code en plus du mot de passe'],
]) ?>
