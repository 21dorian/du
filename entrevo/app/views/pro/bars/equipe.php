<?php /** @var array $staff, $pins, $settings; ?array $sel; string $btab, $bbase */ echo view('pro/bars/_nav', compact('btab', 'bbase')); ?>
<p class="muted lead-sm">Chaque serveur ouvre la caisse avec son code personnel : ses ventes sont à son nom et ses droits limitent ce qu'il peut faire. Une action protégée (offrir, remise, annulation…) peut être validée sur place par le code d'un responsable. L'équipe est commune à tous tes événements.</p>
<?php if (!$settings['require_staff']): ?><div class="notice info mb">La connexion par code n'est pas obligatoire pour cet événement : active-la dans <a href="<?= url($bbase . '/reglages') ?>">Réglages</a> pour que chaque vente soit au nom d'un serveur.</div><?php endif; ?>
<?php if ($pins): ?><div class="card pins-card mb"><h2>Codes à transmettre</h2><p class="muted">Affichés une seule fois.</p><div class="pins"><?php foreach ($pins as [$n, $pin]): ?><div><span><?= e($n) ?></span><b class="mono"><?= e($pin) ?></b></div><?php endforeach; ?></div><button class="btn btn-sm btn-light no-print" data-print>Imprimer</button></div><?php endif; ?>
<div class="split">
  <div class="card table-card"><div class="card-head"><h2>Équipe</h2><a class="btn btn-sm" href="<?= url($bbase . '/equipe', ['s' => 'new']) ?>#fiche">+ Équipier</a></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Nom</th><th>Rôle</th><th class="num">Ventes</th><th class="num">Offert / validé</th><th></th></tr></thead><tbody>
      <?php foreach ($staff as $s): ?><tr class="<?= (int)$s['active'] ? '' : 'muted' ?>"><td><a href="<?= url($bbase . '/equipe', ['s' => $s['id']]) ?>#fiche"><b><?= e($s['name']) ?></b></a><br><small class="muted"><?= $s['last_login'] ? 'connecté ' . e(ago($s['last_login'])) : 'jamais connecté' ?></small></td>
        <td><span class="badge <?= $s['role'] === 'manager' ? 'violet' : 'grey' ?>"><?= e(Till::ROLES[$s['role']][0] ?? $s['role']) ?></span><?= $s['perms'] !== null ? ' <small class="muted">droits sur mesure</small>' : '' ?></td>
        <td class="num"><?= (int)$s['n'] ?><br><small class="muted"><?= e(money((int)$s['t'])) ?></small></td><td class="num"><?= e(money((int)$s['o'])) ?></td>
        <td class="num"><form method="post"><?= csrf_field() ?><input type="hidden" name="staff_id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="toggle"><?= (int)$s['active'] ? 'Désactiver' : 'Réactiver' ?></button></form></td></tr><?php endforeach; ?>
      <?php if (!$staff): ?><tr><td colspan="5" class="empty">Personne pour l'instant.</td></tr><?php endif; ?>
    </tbody></table></div></div>
  <div>
    <?php if ($sel !== null): $s = $sel ?: ['id' => 0, 'name' => '', 'role' => 'server', 'perms' => null]; $cur = Till::perms($s ?: ['role' => 'server', 'perms' => null]); ?>
    <form method="post" class="card" id="fiche"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="staff_id" value="<?= (int)$s['id'] ?>">
      <h2><?= $s['id'] ? e($s['name']) : 'Nouvel équipier' ?></h2>
      <div class="grid2"><div class="field"><label>Nom</label><input name="name" required value="<?= e($s['name']) ?>"></div>
        <div class="field"><label>Code <?= $s['id'] ? '<small class="muted">laisser vide pour garder</small>' : '' ?></label><input name="pin" inputmode="numeric" maxlength="6" autocomplete="off" placeholder="4 à 6 chiffres" <?= $s['id'] ? '' : 'required' ?>></div></div>
      <div class="field"><label>Rôle</label><div class="seg seg-full"><?php foreach (Till::ROLES as $k => [$l]): ?><label class="seg-r"><input type="radio" name="role" value="<?= $k ?>" <?= $s['role'] === $k ? 'checked' : '' ?>><span><?= $l ?></span></label><?php endforeach; ?></div></div>
      <label class="check"><input type="checkbox" name="custom" value="1" <?= $s['perms'] !== null ? 'checked' : '' ?>> <span>Droits sur mesure</span></label>
      <div class="perm-grid" data-show-if="custom=1"><?php foreach (Till::PERMS as $k => $l): ?><label class="check"><input type="checkbox" name="perms[]" value="<?= $k ?>" <?= in_array($k, $cur, true) ? 'checked' : '' ?>> <span><?= e($l) ?></span></label><?php endforeach; ?></div>
      <p class="muted"><small>Serveur : vendre et rendre les consignes. Responsable : en plus offrir, remiser, annuler, gérer les espèces et clôturer.</small></p>
      <button class="btn">Enregistrer</button></form>
    <?php endif; ?>
    <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="generate"><h2>Créer plusieurs serveurs</h2><textarea name="names" rows="5" placeholder="Un prénom par ligne"></textarea><small class="muted">Un code à 4 chiffres est créé pour chacun et affiché une seule fois.</small><button class="btn btn-sm mt">Créer</button></form>
  </div>
</div>
