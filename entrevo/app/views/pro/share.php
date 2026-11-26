<?php /** @var array $ev, $links */ ?>
<div class="split" style="grid-template-columns:3fr 2fr">
  <div class="card table-card"><div class="card-head"><h2>Liens de partage</h2><a class="btn btn-sm btn-light" href="<?= url('/pro/evenements/' . $ev['id'] . '/rapport.pdf') ?>" target="_blank">Bilan PDF</a></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Pour</th><th>Chiffres visibles</th><th class="num">Vues</th><th>Expire</th><th></th></tr></thead><tbody>
    <?php foreach ($links as $l): $u = abs_url('/stats/' . $l['token']); $dead = $l['revoked'] || ($l['expires_at'] && strtotime($l['expires_at']) < time()); ?>
      <tr style="<?= $dead ? 'opacity:.5' : '' ?>"><td><b><?= e($l['label']) ?></b><br><small class="muted"><?= e(short_date($l['created_at'])) ?></small></td>
        <td><small><?= e(implode(', ', array_map(fn($k) => SHARE_SECTIONS[$k] ?? $k, explode(',', $l['sections'])))) ?></small></td>
        <td class="num"><?= (int)$l['views'] ?></td><td><small><?= $l['revoked'] ? 'désactivé' : ($l['expires_at'] ? e(fr_date($l['expires_at'], false)) : 'jamais') ?></small></td>
        <td class="right nowrap"><?php if (!$dead): ?><button type="button" class="btn btn-sm btn-light" data-copy="<?= e($u) ?>">Copier</button><a class="btn btn-sm btn-ghost" href="<?= e($u) ?>" target="_blank">Voir</a>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="link_id" value="<?= (int)$l['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="revoke" data-confirm="Désactiver ce lien ?">Désactiver</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$links): ?><tr><td colspan="5" class="empty">Aucun lien : crée-en un pour une salle, un coproducteur ou un financeur.</td></tr><?php endif; ?>
    </tbody></table></div></div>
  <form method="post" class="card stack"><?= csrf_field() ?><h2>Nouveau lien</h2>
    <p class="muted mb0"><small>Une page en lecture seule, mise à jour en direct, sans accès à ton compte ni aux données personnelles des acheteurs.</small></p>
    <div class="field"><label>Pour qui ?</label><input name="label" placeholder="Ex. : Salle des fêtes, Région, coproducteur" required></div>
    <div class="field"><label>Chiffres visibles</label><?php foreach (SHARE_SECTIONS as $k => $l): ?><label class="check"><input type="checkbox" name="sections[]" value="<?= $k ?>" <?= in_array($k, ['ventes', 'presence', 'courbe'], true) ? 'checked' : '' ?>> <?= e($l) ?></label><?php endforeach; ?></div>
    <div class="field"><label>Durée</label><select name="days"><option value="30">30 jours</option><option value="90">3 mois</option><option value="365">1 an</option><option value="0">Sans limite</option></select></div>
    <button class="btn">Créer le lien</button>
  </form>
</div>
