<?php if ($created): ?>
<div class="flash success">Compte prêt ! Transmets ces identifiants (ils ne seront plus affichés) :<br>
  <span class="mono">E-mail : <?= e($created['email']) ?> · Mot de passe : <?= e($created['password']) ?></span><br>
  <?= $created['role'] === 'scanner' ? 'Appli de contrôle : ' . e(abs_url('/scan')) : 'Connexion : ' . e(abs_url('/connexion')) ?></div>
<?php endif; ?>
<div class="split-3">
  <div class="card">
    <h2>Ton équipe</h2>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Nom</th><th>Rôle</th><th>Dernière connexion</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($members as $m): $me = $m['id'] == user()['id']; ?>
        <tr style="<?= $m['active'] ? '' : 'opacity:.5' ?>">
          <td><b><?= e($m['name']) ?></b><?= $me ? ' <small>(toi)</small>' : '' ?><br><small><?= e($m['email']) ?></small></td>
          <td><?php if ($me): ?><?= e(role_label($m['role'])) ?><?php else: ?>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
            <select name="role" onchange="this.form.submit()" style="width:auto;padding:6px 8px"><?php foreach (['owner', 'manager', 'scanner'] as $r): ?><option value="<?= $r ?>" <?= $m['role'] === $r && empty($m['custom_role_id']) ? 'selected' : '' ?>><?= e(role_label($r)) ?></option><?php endforeach; ?><?php foreach ($croles as $cr): ?><option value="c<?= (int)$cr['id'] ?>" <?= (int)($m['custom_role_id'] ?? 0) === (int)$cr['id'] && $m['role'] === 'manager' ? 'selected' : '' ?>><?= e($cr['name']) ?></option><?php endforeach; ?></select></form><?php endif; ?></td>
          <td class="nowrap"><small><?= $m['last_login'] ? e(short_date($m['last_login'])) : 'jamais' ?></small></td>
          <td class="right nowrap"><?php if (!$me): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-sm btn-light" name="action" value="password" data-confirm="Générer un nouveau mot de passe pour <?= e($m['name']) ?> ?">Nouveau mot de passe</button>
            <button class="btn btn-sm btn-light" name="action" value="toggle"><?= $m['active'] ? 'Désactiver' : 'Réactiver' ?></button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="muted mt mb0 u-fs14"><b>Responsable</b> : tout, finances comprises · <b>Gestionnaire</b> : événements, ventes, participants · <b>Contrôleur</b> : uniquement l'appli de scan</p>
  </div>
  <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <h2>+ Ajouter quelqu'un</h2>
    <div class="field"><label>Nom</label><input name="name" required placeholder="Ex. : Accueil porte A"></div>
    <div class="field"><label>E-mail (identifiant)</label><input type="email" name="email" required></div>
    <div class="field"><label>Rôle</label><select name="role"><option value="scanner">Contrôleur (scan des billets)</option><option value="manager">Gestionnaire</option><option value="owner">Responsable</option><?php foreach ($croles as $cr): ?><option value="c<?= (int)$cr['id'] ?>"><?= e($cr['name']) ?> (rôle perso)</option><?php endforeach; ?></select></div>
    <div class="field"><label>Mot de passe</label><input name="password" placeholder="Vide = généré pour toi"></div>
    <button class="btn btn-block">Créer le compte</button>
  </form>
</div>
<?php $er = $editRole ?: ['id' => 0, 'name' => '', 'sections' => '', 'event_ids' => '']; $ers = array_filter(explode(',', (string)$er['sections'])); $ere = array_filter(array_map('intval', explode(',', (string)$er['event_ids']))); ?>
<div class="split mt" id="roles">
  <div class="card"><h2>Rôles personnalisés</h2>
    <p class="muted"><small>Un gestionnaire limité à certaines rubriques (et, si tu veux, à certains événements) : « billetterie seulement », « compta », « jour J »…</small></p>
    <?php foreach ($croles as $cr): ?><div class="list-item"><span class="grow"><b><?= e($cr['name']) ?></b> <small class="muted">· <?= (int)$cr['n'] ?> personne(s)</small><br><small class="muted"><?= e(implode(', ', array_map(fn($k) => Extras::SECTIONS[$k][0] ?? $k, array_filter(explode(',', (string)$cr['sections']))))) ?><?= $cr['event_ids'] ? ' · ' . count(explode(',', (string)$cr['event_ids'])) . ' événement(s)' : '' ?></small></span>
      <a class="btn btn-sm btn-light" href="<?= url('/pro/equipe', ['crole' => $cr['id']]) ?>#roles">Modifier</a>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="crole_id" value="<?= (int)$cr['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="crole_delete" data-confirm="Supprimer ce rôle ?">Supprimer</button></form></div><?php endforeach; ?>
    <?php if (!$croles): ?><p class="empty">Aucun rôle personnalisé.</p><?php endif; ?>
    <p class="muted mb0"><small>Double authentification : chacun peut l'activer dans <a href="<?= url('/mon-compte') ?>">Mon compte</a>.</small></p></div>
  <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="crole_save"><input type="hidden" name="crole_id" value="<?= (int)$er['id'] ?>">
    <h2><?= $er['id'] ? 'Modifier le rôle' : 'Nouveau rôle' ?></h2>
    <div class="field"><label>Nom</label><input name="crole_name" value="<?= e($er['name']) ?>" placeholder="Ex. : Billetterie seulement" required></div>
    <div class="field"><label>Rubriques autorisées</label><?php foreach (Extras::SECTIONS as $k => [$l]): ?><label class="check"><input type="checkbox" name="crole_sections[]" value="<?= $k ?>" <?= in_array($k, $ers, true) || (!$er['id'] && in_array($k, ['dashboard', 'events', 'orders'], true)) ? 'checked' : '' ?>> <?= e($l) ?></label><?php endforeach; ?></div>
    <div class="field"><label>Limiter à certains événements (facultatif)</label><select name="crole_events[]" multiple size="5"><?php foreach ($evList as $e2): ?><option value="<?= (int)$e2['id'] ?>" <?= in_array((int)$e2['id'], $ere, true) ? 'selected' : '' ?>><?= e($e2['name']) ?> · <?= e(fr_date($e2['starts_at'], false)) ?></option><?php endforeach; ?></select><small class="muted">Aucun sélectionné : tous les événements.</small></div>
    <div class="row"><button class="btn"><?= $er['id'] ? 'Enregistrer' : 'Créer le rôle' ?></button><?php if ($er['id']): ?><a class="btn btn-ghost" href="<?= url('/pro/equipe') ?>#roles">Annuler</a><?php endif; ?></div>
  </form>
</div>
