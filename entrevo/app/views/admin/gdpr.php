<?php /** @var array $list, $k; string $f; int $years, $toAnon */ ?>
<p class="muted lead-sm">Demandes d'accès, d'effacement, de rectification ou d'opposition. Délai légal : un mois après confirmation. Formulaire public : <a href="<?= url('/donnees-personnelles') ?>" target="_blank"><?= e(preg_replace('~^https?://~', '', abs_url('/donnees-personnelles'))) ?></a></p>
<div class="page-head-row">
  <div class="kpi-mini"><span>À traiter</span><b><?= (int)$k['pending'] ?></b></div>
  <div class="kpi-mini<?= (int)$k['urgent'] ? ' kpi-alert' : '' ?>"><span>Échéance sous 7 jours</span><b><?= (int)$k['urgent'] ?></b></div>
  <div class="kpi-mini"><span>Traitées (12 mois)</span><b><?= (int)$k['done12'] ?></b></div>
  <div class="kpi-mini"><span>Conservation</span><b><?= $years ? $years . ' an' . ($years > 1 ? 's' : '') : 'illimitée' ?></b><small class="muted"><?= $years ? $toAnon . ' commande(s) à anonymiser' : 'à régler' ?></small></div>
</div>
<div class="split-3">
  <div>
    <nav class="pills mb"><?php foreach (['a-traiter' => 'À traiter', 'non-confirmees' => 'Non confirmées', 'traitees' => 'Traitées', 'toutes' => 'Toutes'] as $kk => $l): ?><a class="<?= $f === $kk ? 'on' : '' ?>" href="<?= url('/admin/rgpd', ['f' => $kk]) ?>"><?= $l ?></a><?php endforeach; ?></nav>
    <div class="card table-card"><div class="table-wrap"><table class="table">
      <thead><tr><th>Personne</th><th>Demande</th><th>Reçue</th><th>À traiter avant</th><th>Statut</th></tr></thead><tbody>
      <?php foreach ($list as $r): $late = $r['status'] === 'pending' && $r['due_on'] && strtotime($r['due_on']) < time() + 7 * 86400; ?>
        <tr data-href="<?= url('/admin/rgpd/' . $r['id']) ?>"><td><a href="<?= url('/admin/rgpd/' . $r['id']) ?>"><b><?= e($r['email']) ?></b></a></td><td><?= e(Gdpr::KINDS[$r['kind']] ?? $r['kind']) ?></td>
          <td class="nowrap"><small><?= e(short_date($r['created_at'])) ?></small></td><td class="nowrap"><?= $r['due_on'] ? '<span class="' . ($late ? 'text-red' : '') . '">' . e(date('d/m/Y', strtotime($r['due_on']))) . '</span>' : '<span class="muted">—</span>' ?></td><td><?= Gdpr::badge($r['status']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="5" class="empty">Aucune demande ici.</td></tr><?php endif; ?>
      </tbody></table></div></div>
  </div>
  <div class="stack">
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="lookup">
      <h2>Demande reçue autrement</h2><p class="muted u-fs13">Courrier, e-mail, téléphone : saisis-la ici pour l'inventaire et le suivi du délai.</p>
      <div class="field"><label>Adresse e-mail</label><input type="email" name="email" required></div>
      <div class="field"><label>Type</label><select name="kind"><?php foreach (Gdpr::KINDS as $kk => $l): ?><option value="<?= $kk ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
      <button class="btn btn-block">Ouvrir</button></form>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <h2>Réglages</h2>
      <div class="field"><label>Anonymiser les acheteurs après</label><select name="retention_years"><?php foreach ([0 => 'Jamais (déconseillé)', 1 => '1 an', 2 => '2 ans', 3 => '3 ans (recommandé)', 5 => '5 ans'] as $y => $l): ?><option value="<?= $y ?>" <?= $years === $y ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><small>Compté à partir de la fin de l'événement. Les montants restent pour la comptabilité.</small></div>
      <div class="field"><label>Prévenir le référent données</label><input type="email" name="dpo_email" value="<?= e((string)setting('dpo_email')) ?>" placeholder="<?= e((string)setting('contact_email')) ?>"></div>
      <button class="btn btn-light btn-block">Enregistrer</button></form>
  </div>
</div>
