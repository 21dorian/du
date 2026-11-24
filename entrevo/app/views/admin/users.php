<div class="split-3">
  <div>
    <form class="toolbar" method="get"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Nom, e-mail, organisateur…"><button class="btn btn-sm">Chercher</button></form>
    <div class="card"><div class="table-wrap"><table class="table">
      <thead><tr><th>Nom</th><th>Rôle</th><th>Organisateur</th><th>Dernière connexion</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($list as $m): ?>
        <tr style="<?= $m['active'] ? '' : 'opacity:.5' ?>"><td><b><?= e($m['name']) ?></b><br><small><?= e($m['email']) ?></small></td><td><?= e(role_label($m['role'])) ?></td><td><?= e($m['org_name'] ?: '—') ?></td>
        <td class="nowrap"><small><?= $m['last_login'] ? e(short_date($m['last_login'])) : 'jamais' ?></small></td>
        <td class="right nowrap"><?php if ($m['id'] != $me['id']): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="password" data-confirm="Générer un nouveau mot de passe ?">🔑</button> <button class="btn btn-sm btn-light" name="action" value="toggle"><?= $m['active'] ? 'Désactiver' : 'Réactiver' ?></button></form><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div></div>
  </div>
  <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add_admin">
    <h2>+ Super-admin</h2>
    <p class="muted">Accès total à la plateforme. À donner avec parcimonie 👑</p>
    <div class="field"><label>Nom</label><input name="name" required></div>
    <div class="field"><label>E-mail</label><input type="email" name="email" required></div>
    <div class="field"><label>Mot de passe</label><input type="password" name="password" minlength="8" required></div>
    <button class="btn btn-block">Ajouter</button>
  </form>
</div>
<?= soon_card('🧪 Comptes à venir', [
  'rgpd' => ['Demandes RGPD', 'Export et suppression des données d\'une personne'],
  'connexion-sociale' => ['Connexion Google & Apple', 'Sans mot de passe'],
  'audit-plateforme' => ['Journal du super-admin', 'Toutes tes actions tracées'],
], 'admin') ?>
