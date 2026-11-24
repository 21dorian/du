<?php $icon = ['ok' => '✅', 'warn' => '⚠️', 'bad' => '❌']; $all = array_merge(...array_values($checks)); $bad = count(array_filter($all, fn($c) => $c['level'] === 'bad')); $warn = count(array_filter($all, fn($c) => $c['level'] === 'warn')); ?>
<div class="health-hero <?= $bad ? 'bad' : ($warn ? 'warn' : 'ok') ?>">
  <b><?= $bad ? '❌ ' . $bad . ' problème(s) à corriger' : ($warn ? '⚠️ ' . $warn . ' point(s) à surveiller' : '✅ Tout est au vert') ?></b>
  <div class="row" style="gap:6px"><?= soon_btn('alertes', '🚨 Alertes SMS / Slack', 'admin') ?><?= soon_btn('sauvegardes', '💾 Sauvegardes auto', 'admin') ?><form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-light" name="action" value="run_jobs">⚙️ Lancer les tâches maintenant</button></form></div>
</div>
<div class="split">
  <div>
    <?php foreach ($checks as $group => $items): ?>
      <div class="card">
        <h2><?= e($group) ?></h2>
        <?php foreach ($items as $c): ?>
          <div class="check-row <?= $c['level'] ?>"><span><?= $icon[$c['level']] ?></span><div class="grow"><b><?= e($c['label']) ?></b><?php if ($c['detail'] !== ''): ?><small><?= e($c['detail']) ?></small><?php endif; ?><?php if ($c['fix'] !== '' && $c['level'] !== 'ok'): ?><small class="fix">👉 <?= e($c['fix']) ?></small><?php endif; ?></div></div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div>
    <div class="card"><h2>📬 E-mails envoyés (7 jours)</h2>
      <?= Viz::chart('columns', ['labels' => array_keys($mails), 'xlabel' => 'Jour', 'series' => [['name' => 'E-mails', 'values' => array_values($mails)]]], 'E-mails envoyés par jour', 'int', 180) ?></div>
    <?php foreach (['🐞 Dernières erreurs' => $errors, '📮 Erreurs d\'e-mails' => $mailErrors, '⚙️ Erreurs des tâches' => $taskErrors] as $t => $rows): ?>
      <div class="card"><h2><?= $t ?></h2>
        <?php foreach ($rows as $r): ?><pre class="log-line"><?= e(mb_strimwidth($r, 0, 600, '…')) ?></pre><?php endforeach; ?>
        <?php if (!$rows): ?><p class="muted">Rien à signaler 👌</p><?php endif; ?></div>
    <?php endforeach; ?>
    <div class="card"><h2>🔔 Notifications des prestataires de paiement</h2>
      <?php foreach ($hooks as $h): ?><div class="list-item"><span class="grow"><b><?= e(provider_label($h['provider'] ?? '')) ?></b> <small class="muted"><?= e((string)($h['created_at'] ?? '')) ?></small><br><small><?= e(mb_strimwidth((string)($h['result'] ?? $h['summary'] ?? ''), 0, 140, '…')) ?></small></span></div><?php endforeach; ?>
      <?php if (!$hooks): ?><p class="muted">Aucune notification reçue pour l'instant.</p><?php endif; ?></div>
  </div>
</div>
