<?php /** @var array $list, $k, $me; string $f, $q */
$frt = $k['frt'] !== null ? (int)$k['frt'] : null;
$frtTxt = $frt === null ? '—' : ($frt < 60 ? $frt . ' min' : ($frt < 1440 ? round($frt / 60, 1) . ' h' : round($frt / 1440, 1) . ' j'));
?>
<div class="page-head-row">
  <div class="kpi-mini<?= (int)$k['open'] ? ' kpi-alert' : '' ?>"><span>À traiter</span><b><?= (int)$k['open'] ?></b></div>
  <div class="kpi-mini"><span>En attente de réponse</span><b><?= (int)$k['pending'] ?></b></div>
  <div class="kpi-mini"><span>Prioritaires ouvertes</span><b><?= (int)$k['hot'] ?></b></div>
  <div class="kpi-mini"><span>1re réponse (30 j)</span><b><?= e($frtTxt) ?></b></div>
  <div class="kpi-mini"><span>Résolues (30 j)</span><b><?= (int)$k['solved30'] ?></b></div>
</div>
<div class="row between mb">
  <nav class="pills"><?php foreach (['actives' => 'À traiter', 'moi' => 'Les miennes', 'attente' => 'En attente', 'resolues' => 'Résolues', 'toutes' => 'Toutes'] as $kk => $l): ?><a class="<?= $f === $kk ? 'on' : '' ?>" href="<?= url('/admin/support', ['f' => $kk]) ?>"><?= $l ?></a><?php endforeach; ?></nav>
  <form method="get" class="inline-form"><input type="hidden" name="f" value="toutes"><input name="q" value="<?= e($q) ?>" placeholder="Réf., e-mail, sujet, commande" style="width:260px"></form>
</div>
<div class="card table-card"><div class="table-wrap"><table class="table">
  <thead><tr><th>Demande</th><th>De</th><th>Priorité</th><th>Statut</th><th>Suivi</th><th>Mise à jour</th></tr></thead><tbody>
  <?php foreach ($list as $t): ?>
    <tr data-href="<?= url('/admin/support/' . $t['id']) ?>"><td><a href="<?= url('/admin/support/' . $t['id']) ?>"><b><?= e($t['subject']) ?></b></a><br><small class="muted"><?= e($t['ref']) ?> · <?= e(Support::CATEGORIES[$t['category']] ?? '') ?><?= $t['order_ref'] ? ' · ' . e($t['order_ref']) : '' ?></small></td>
      <td><?= e((string)($t['name'] ?: $t['email'])) ?><br><small class="muted"><?= $t['org_name'] ? e($t['org_name']) : e($t['email']) ?></small></td>
      <td><?= in_array($t['priority'], ['high', 'urgent'], true) ? '<span class="badge ' . ($t['priority'] === 'urgent' ? 'red' : 'orange') . '">' . e(Support::PRIORITY[$t['priority']]) . '</span>' : '<small class="muted">' . e(Support::PRIORITY[$t['priority']] ?? '') . '</small>' ?></td>
      <td><?= Support::badge($t['status']) ?><?= $t['status'] === 'open' && $t['last_author'] === 'customer' && (int)$t['n'] > 1 ? '<br><small class="muted">a répondu</small>' : '' ?></td>
      <td><small><?= $t['agent'] ? e($t['agent']) : '<span class="muted">personne</span>' ?></small></td>
      <td class="nowrap"><small><?= e(ago($t['updated_at'])) ?></small></td></tr>
  <?php endforeach; ?>
  <?php if (!$list): ?><tr><td colspan="6" class="empty">Rien à traiter. Bravo.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<details class="card mt"><summary><b>Réglages du support</b> <small class="muted">adresse de notification, réponses enregistrées</small></summary>
  <form method="post" class="mt"><?= csrf_field() ?><input type="hidden" name="action" value="macros">
    <div class="field" style="max-width:420px"><label>Prévenir à chaque nouvelle demande</label><input type="email" name="support_email" value="<?= e((string)setting('support_email')) ?>" placeholder="<?= e((string)setting('contact_email')) ?>"></div>
    <h3>Réponses enregistrées</h3>
    <?php foreach (array_merge(Support::macros(), [['', '']]) as $m): ?>
      <div class="grid-macro"><input name="m_title[]" value="<?= e($m[0]) ?>" placeholder="Titre"><textarea name="m_body[]" rows="3" placeholder="Texte de la réponse"><?= e($m[1]) ?></textarea></div>
    <?php endforeach; ?>
    <button class="btn">Enregistrer</button>
  </form>
</details>
