<?php /** @var array $list, $org; string $subject */ ?>
<p class="muted lead-sm">Une question, un souci, une demande de module ? Écris à l'équipe <?= e(setting('platform_name', 'Entrevo')) ?> : la réponse arrive ici et par e-mail.</p>
<div class="split-3">
  <div class="card table-card">
    <div class="card-head"><h2>Tes demandes</h2></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Demande</th><th>Statut</th><th>Mise à jour</th></tr></thead><tbody>
    <?php foreach ($list as $t): ?><tr data-href="<?= url('/pro/aide/' . $t['ref']) ?>"><td><a href="<?= url('/pro/aide/' . $t['ref']) ?>"><b><?= e($t['subject']) ?></b></a><br><small class="muted"><?= e($t['ref']) ?> · <?= e(Support::CATEGORIES[$t['category']] ?? '') ?> · <?= (int)$t['n'] ?> message<?= (int)$t['n'] > 1 ? 's' : '' ?></small></td>
      <td><?= Support::badge($t['status']) ?></td><td class="nowrap"><small><?= e(short_date($t['updated_at'])) ?></small></td></tr><?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="3" class="empty">Aucune demande pour l'instant.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
  <form class="card" method="post"><?= csrf_field() ?>
    <h2>Nouvelle demande</h2>
    <div class="field"><label>Sujet</label><input name="subject" required maxlength="190" value="<?= e($subject) ?>"></div>
    <div class="grid2">
      <div class="field"><label>Thème</label><select name="category"><?php foreach (Support::CATEGORIES as $k => $l): ?><option value="<?= $k ?>" <?= ($subject !== '' && $k === 'formule') || ($subject === '' && $k === 'organisateur') ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Urgence</label><select name="priority"><option value="normal">Normale</option><option value="high">Haute</option><option value="urgent">Urgente (jour J)</option></select></div>
    </div>
    <div class="field"><label>Commande concernée <small class="muted">(facultatif)</small></label><input name="order_ref" class="mono"></div>
    <div class="field"><label>Message</label><textarea name="message" rows="6" required></textarea></div>
    <button class="btn btn-block">Envoyer</button>
  </form>
</div>
