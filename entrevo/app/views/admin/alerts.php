<?php /** @var array $problems, $log */ $checks = explode(',', (string)setting('alert_checks', 'cron,sauvegarde,paiements,erreurs,disque,litige,smtp')); ?>
<p class="muted lead-sm">Être prévenu tout de suite si quelque chose ne va pas, par e-mail et sur ta messagerie d'équipe (Slack, Discord, Mattermost, Teams…). Vérification toutes les 10 minutes.</p>
<div class="split-3">
  <div>
    <div class="card"><div class="card-head"><h2>En ce moment</h2><form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-light" name="action" value="check">Vérifier maintenant</button></form></div>
      <?php foreach ($problems as $k => [$lvl, $t, $b]): ?><div class="alert-row lvl-<?= $lvl ?>"><b><?= e($t) ?></b><small class="muted"><?= e($b) ?></small></div><?php endforeach; ?>
      <?php if (!$problems): ?><p class="mb0"><span class="badge green">Tout va bien</span> <span class="muted">Aucun problème détecté.</span></p><?php endif; ?>
    </div>
    <div class="card table-card"><div class="card-head"><h2>Historique</h2></div><div class="table-wrap"><table class="table table-sm"><tbody>
      <?php foreach ($log as $l): ?><tr><td class="nowrap"><small><?= e(date('d/m H:i', strtotime($l['created_at']))) ?></small></td><td><span class="badge <?= ['alert' => 'red', 'warn' => 'orange', 'ok' => 'green'][$l['level']] ?? 'grey' ?>"><?= ['alert' => 'Alerte', 'warn' => 'Attention', 'ok' => 'Rétabli'][$l['level']] ?? $l['level'] ?></span></td>
        <td><?= e($l['title']) ?><?= $l['resolved_at'] ? '<br><small class="muted">résolu ' . e(ago($l['resolved_at'])) . '</small>' : '' ?></td><td><small class="muted"><?= e((string)$l['channels']) ?></small></td></tr><?php endforeach; ?>
      <?php if (!$log): ?><tr><td class="empty">Aucune alerte envoyée.</td></tr><?php endif; ?>
    </tbody></table></div></div>
  </div>
  <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
    <h2>Où prévenir</h2>
    <div class="field"><label>E-mails</label><input name="alert_emails" value="<?= e((string)setting('alert_emails')) ?>" placeholder="<?= e((string)setting('contact_email')) ?>"><small>Plusieurs adresses séparées par des virgules.</small></div>
    <div class="field"><label>Webhook (Slack, Discord, Teams…)</label><input name="alert_webhook" value="<?= e((string)setting('alert_webhook')) ?>" placeholder="https://hooks.slack.com/services/…"><small>Adresse d'« incoming webhook » de ta messagerie.</small></div>
    <h3 class="mt">Quoi surveiller</h3>
    <?php foreach (Alerts::CHECKS as $k => $l): ?><label class="check"><input type="checkbox" name="checks[]" value="<?= $k ?>" <?= in_array($k, $checks, true) ? 'checked' : '' ?>> <span><?= e($l) ?></span></label><?php endforeach; ?>
    <div class="field mt"><label>Répéter une alerte au plus toutes les</label><div class="input-suffix" style="max-width:160px"><input type="number" name="alert_cooldown" min="1" max="48" value="<?= (int)setting('alert_cooldown', '6') ?>"><span>h</span></div></div>
    <div class="row"><button class="btn">Enregistrer</button><button class="btn btn-light" name="action" value="test" formnovalidate>Envoyer un test</button></div>
  </form>
</div>
