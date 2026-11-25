<?php /** @var array $org, $eps, $log; ?array $edit */ ?>
<p class="muted lead-sm">Envoie automatiquement tes ventes, entrées et avis vers tes autres outils : tableur, CRM, comptabilité, Slack, ton site… Chaque envoi est signé, mis en file et retenté tout seul en cas de panne de l'autre côté.</p>
<div class="split-3">
  <div>
    <div class="card table-card"><div class="card-head"><h2>Adresses de réception</h2><a class="btn btn-sm" href="<?= url('/pro/integrations', ['edit' => 'new']) ?>#editeur">+ Adresse</a></div>
      <div class="table-wrap" style="overflow:visible"><table class="table users-table"><tbody>
      <?php foreach ($eps as $ep): ?><tr><td><b><?= e($ep['label'] ?: parse_url($ep['url'], PHP_URL_HOST)) ?></b><br><small class="muted mono"><?= e(mb_strimwidth($ep['url'], 0, 60, '…')) ?></small><br><small class="muted"><?= e(implode(' · ', array_map(fn($k) => Hooks::EVENTS[$k] ?? $k, explode(',', $ep['events'])))) ?></small></td>
        <td class="nowrap"><?= $ep['active'] ? ($ep['fail_streak'] ? '<span class="badge orange">' . (int)$ep['fail_streak'] . ' échec(s)</span>' : '<span class="badge green">Actif</span>') : '<span class="badge grey">En pause</span>' ?><br><small class="muted"><?= (int)$ep['ok7'] ?> reçus · <?= (int)$ep['ko7'] ?> ratés (7 j)</small></td>
        <td><details class="row-menu"><summary class="btn btn-sm btn-light">Gérer</summary><div class="row-menu-pop">
          <form method="post" class="perm-form"><?= csrf_field() ?><input type="hidden" name="endpoint_id" value="<?= (int)$ep['id'] ?>"><b>Envoyer un test</b><select name="event"><?php foreach (Hooks::EVENTS as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select><button class="btn btn-sm" name="action" value="test">Tester</button></form>
          <div class="perm-form"><b>Clé de signature</b><small class="mono" style="word-break:break-all"><?= e($ep['secret']) ?></small></div>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="endpoint_id" value="<?= (int)$ep['id'] ?>"><a href="<?= url('/pro/integrations', ['edit' => $ep['id']]) ?>#editeur" style="display:block;padding:9px 12px">Modifier</a><button name="action" value="toggle"><?= $ep['active'] ? 'Mettre en pause' : 'Réactiver' ?></button><button name="action" value="delete" class="danger-text" data-confirm="Supprimer cette adresse ?">Supprimer</button></form>
        </div></details></td></tr><?php endforeach; ?>
      <?php if (!$eps): ?><tr><td class="empty">Aucune adresse. Ajoute celle que te donne Zapier, Make ou ton développeur.</td></tr><?php endif; ?>
      </tbody></table></div></div>
    <?php if ($edit !== null): $ep = $edit ?: ['id' => 0, 'url' => '', 'label' => '', 'events' => 'order.paid']; $sel = explode(',', $ep['events']); ?>
    <form class="card" method="post" id="editeur"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="endpoint_id" value="<?= (int)$ep['id'] ?>">
      <h2><?= $ep['id'] ? 'Modifier l\'adresse' : 'Nouvelle adresse' ?></h2>
      <div class="grid2"><div class="field"><label>Nom</label><input name="label" value="<?= e((string)$ep['label']) ?>" placeholder="Google Sheets, CRM…"></div><div class="field"><label>Adresse (https)</label><input name="url" required value="<?= e($ep['url']) ?>" placeholder="https://hooks.zapier.com/hooks/catch/…"></div></div>
      <div class="mod-grid"><?php foreach (Hooks::EVENTS as $k => $l): ?><label class="check"><input type="checkbox" name="events[]" value="<?= $k ?>" <?= in_array($k, $sel, true) ? 'checked' : '' ?>> <span><?= e($l) ?> <small class="muted mono"><?= $k ?></small></span></label><?php endforeach; ?></div>
      <button class="btn mt">Enregistrer</button></form>
    <?php endif; ?>
    <div class="card table-card"><div class="card-head"><h2>Derniers envois</h2></div><div class="table-wrap"><table class="table table-sm"><tbody>
      <?php foreach ($log as $d): ?><tr><td class="nowrap"><small><?= e(date('d/m H:i:s', strtotime($d['created_at']))) ?></small></td><td><small class="mono"><?= e($d['event']) ?></small><br><small class="muted"><?= e($d['label'] ?: parse_url($d['url'], PHP_URL_HOST)) ?></small></td>
        <td><span class="badge <?= ['ok' => 'green', 'failed' => 'red', 'pending' => 'orange'][$d['status']] ?? 'grey' ?>"><?= $d['status'] === 'ok' ? 'Reçu' : ($d['status'] === 'failed' ? 'Abandonné' : ((int)$d['attempts'] ? 'Nouvel essai ' . e(date('H:i', strtotime((string)$d['next_try_at']))) : 'En file')) ?></span><?= $d['http_code'] ? ' <small class="muted">HTTP ' . (int)$d['http_code'] . '</small>' : '' ?></td>
        <td class="right"><?php if ($d['status'] !== 'pending'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="delivery_id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-ghost" name="action" value="replay">Renvoyer</button></form><?php endif; ?></td></tr><?php endforeach; ?>
      <?php if (!$log): ?><tr><td class="empty">Aucun envoi pour l'instant.</td></tr><?php endif; ?>
    </tbody></table></div></div>
  </div>
  <div class="stack">
    <div class="card"><h2>Recettes toutes prêtes</h2>
      <div class="recipe"><b>Google Sheets : une ligne par vente</b><small>Zapier : déclencheur « Webhooks by Zapier · Catch Hook », copie l'adresse donnée ici avec « Commande payée », action « Google Sheets · Create Spreadsheet Row ».</small></div>
      <div class="recipe"><b>Slack : un message à chaque vente</b><small>Make : module « Webhooks · Custom webhook » puis « Slack · Create a message » avec {{data.first_name}} et {{data.total}}.</small></div>
      <div class="recipe"><b>CRM / newsletter</b><small>« Commande payée » vers Brevo, HubSpot ou Mailchimp pour ajouter l'acheteur (si il a accepté de recevoir tes actus).</small></div>
      <div class="recipe"><b>Comptage en direct</b><small>« Entrée scannée » vers ton écran géant ou ton propre tableau de bord.</small></div></div>
    <div class="card"><h2>Pour ton développeur</h2>
      <p style="font-size:14px" class="mb0">Envoi <span class="mono">POST</span> JSON <span class="mono">{event, id, date, data}</span>. Vérifie l'en-tête <span class="mono">X-Entrevo-Signature</span> : <span class="mono">sha256=</span> + HMAC-SHA256 du corps avec la clé de signature. Réponds en 2xx sous 8 s ; sinon nouvel essai après 1 min, 5 min, 30 min, 2 h, 6 h, 24 h. Le champ <span class="mono">id</span> permet d'ignorer les doublons.</p>
      <?php if ($org['api_key']): ?><p class="mt mb0" style="font-size:14px">API de lecture : clé dans <a href="<?= url('/pro/organisation') ?>">Mon organisation</a>.</p><?php endif; ?></div>
  </div>
</div>
