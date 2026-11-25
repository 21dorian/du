<?php /** @var array $members, $admins, $ops, $bars */ ?>
<div class="pgrid">
  <div class="stack">
    <div class="card table-card"><div class="card-head"><h2>Équipe cashless</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>Personne</th><th>Rôle</th><th>Périmètre</th><th>Dernière connexion</th><th></th></tr></thead><tbody>
      <?php foreach ($admins as $a): ?><tr><td><b><?= e($a['name']) ?></b><br><small class="muted"><?= e($a['email']) ?></small></td><td><span class="ptag vio">Administrateur</span></td><td><small class="muted">organisateur (<?= e(role_label($a['role'])) ?>)</small></td><td><?= $a['last_login'] ? e(ago($a['last_login'])) : '—' ?></td><td></td></tr><?php endforeach; ?>
      <?php foreach ($members as $x): ?><tr class="<?= (int)$x['active'] ? '' : 'muted' ?>"><td><b><?= e($x['name']) ?></b><br><small class="muted"><?= e($x['email']) ?></small></td><td><span class="ptag"><?= e(Pay::ROLES[$x['role']][0] ?? $x['role']) ?></span></td>
        <td><small class="muted"><?= $x['operator'] ? e($x['operator']) : ($x['bar_ids'] ? count(array_filter(explode(',', (string)$x['bar_ids']))) . ' point(s) de vente' : 'tout l\'organisateur') ?></small></td><td><?= $x['last_login'] ? e(ago($x['last_login'])) : 'jamais' ?></td>
        <td class="num"><form method="post" data-confirm="Retirer l'accès de <?= e($x['email']) ?> ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="remove">Retirer</button></form></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
    <div class="card"><h2>Les rôles</h2><?php foreach (Pay::ROLES as $rk => [$rl, $sec]): ?><div class="list-item"><span class="grow"><b><?= e($rl) ?></b><br><small class="muted"><?= $rk === 'admin' ? 'Tout, y compris l\'équipe et les réglages.' : ($rk === 'operator' ? 'Son espace exploitant : ventes, stocks et relevé de ses stands, rien d\'autre.' : e(implode(', ', array_map(fn($s) => ['live' => 'direct', 'wallets' => 'porte-monnaie', 'bracelets' => 'bracelets', 'refunds' => 'remboursements', 'packs' => 'crédits', 'ecocups' => 'éco-cups', 'exports' => 'exports', 'reglages' => 'réglages'][$s] ?? $s, $sec)))) ?></small></span></div><?php endforeach; ?></div>
  </div>
  <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="add"><h2>Ajouter une personne</h2>
    <p class="pnote" style="margin:0">Elle reçoit un e-mail pour choisir son mot de passe et se connecte sur Entrevo Pay, sans accès à la billetterie.</p>
    <input name="name" placeholder="Prénom et nom"><input name="email" type="email" placeholder="E-mail" required>
    <select name="role" id="pay-role" onchange="document.querySelectorAll('[data-role-x]').forEach(el => el.hidden = el.dataset.roleX !== this.value)"><?php foreach (Pay::ROLES as $rk => [$rl]): if ($rk === 'admin') continue; ?><option value="<?= $rk ?>"><?= e($rl) ?></option><?php endforeach; ?><option value="admin">Administrateur cashless</option></select>
    <div data-role-x="operator" hidden><select name="operator_id"><option value="">Exploitant…</option><?php foreach ($ops as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
    <div data-role-x="bar" hidden class="stack"><small class="muted">Points de vente gérés (vide : tous)</small><div style="max-height:160px;overflow:auto"><?php foreach ($bars as $b): ?><label class="check"><input type="checkbox" name="bar_ids[]" value="<?= (int)$b['id'] ?>"> <span><?= e($b['name']) ?> <small class="muted">· <?= e($b['ev']) ?></small></span></label><?php endforeach; ?></div></div>
    <button class="btn">Ajouter</button></form>
</div>
<script>document.getElementById('pay-role').dispatchEvent(new Event('change'));</script>
