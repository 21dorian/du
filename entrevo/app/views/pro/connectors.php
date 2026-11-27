<?php /** @var array $org, $s, $sync, $capi, $hooks; ?array $lastErr; int $optins */
$feed = fn(string $t) => abs_url('/flux/' . $s['sheets_token'] . '/' . $t . '.csv');
$mask = fn(string $v) => $v === '' ? '' : str_repeat('•', 8) . substr($v, -4); ?>
<p class="muted">Branche ta billetterie à tes outils, sans développeur. Tout est optionnel et réversible.</p>
<div class="split">
  <div class="card stack"><h2>Google Sheets &amp; Excel</h2>
    <p class="muted mb0"><small>Un tableur qui se remplit tout seul. Dans Google Sheets, tape dans la case A1 : <span class="mono">=IMPORTDATA("lien")</span>. Google met à jour environ toutes les heures. Dans Excel : Données › À partir du web.</small></p>
    <?php if ($s['sheets_token']): ?>
      <?php foreach (['commandes' => 'Commandes', 'participants' => 'Participants (billets)', 'ventes' => 'Ventes par jour'] as $k => $l): ?>
        <div class="field"><label><?= $l ?></label><div class="row"><input readonly value="<?= e($feed($k)) ?>" class="grow mono u-fs12"><button type="button" class="btn btn-sm btn-light" data-copy='=IMPORTDATA("<?= e($feed($k)) ?>")'>Copier la formule</button></div></div>
      <?php endforeach; ?>
      <small class="muted">Ajoute <span class="mono">?e=ID</span> pour un seul événement. Ces liens sont secrets : ne les publie pas.</small>
      <form method="post" class="row"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" name="action" value="sheets" data-confirm="Les anciens liens arrêteront de fonctionner. Continuer ?">Régénérer</button><button class="btn btn-sm btn-ghost" name="action" value="sheets_off">Couper</button></form>
    <?php else: ?><form method="post"><?= csrf_field() ?><button class="btn" name="action" value="sheets">Activer les flux Google Sheets</button></form><?php endif; ?>
  </div>
  <div class="card stack"><h2>Zapier, Make &amp; n8n</h2>
    <p class="muted mb0"><small>Connecte-toi avec ta clé API (en-tête <span class="mono">Authorization: Bearer</span>). Déclencheurs instantanés par abonnement REST, actions : créer une invitation.</small></p>
    <?php if ($org['api_key']): ?><div class="field"><label>Clé API</label><div class="row"><input readonly value="<?= e($org['api_key']) ?>" class="grow mono u-fs12"><button type="button" class="btn btn-sm btn-light" data-copy="<?= e($org['api_key']) ?>">Copier</button></div></div>
    <?php else: ?><form method="post"><?= csrf_field() ?><button class="btn btn-sm" name="action" value="api_key">Créer ma clé API</button></form><?php endif; ?>
    <div class="code-block mono" style="font-size:12px;white-space:pre-wrap;background:var(--panel2,#f5f5f7);border-radius:12px;padding:12px">POST <?= e(abs_url('/api/v1/hooks')) . "\n" ?>{"url": "https://hooks.zapier.com/…", "event": "order.paid"}
DELETE <?= e(abs_url('/api/v1/hooks/{id}')) . "\n" ?>
GET <?= e(abs_url('/api/v1/samples/order.paid')) ?>   (exemple pour le mappage)
POST <?= e(abs_url('/api/v1/invitations')) ?></div>
    <small class="muted">Événements : <?= e(implode(', ', array_keys(Hooks::EVENTS))) ?>.</small>
    <?php foreach ($hooks as $h): ?><div class="list-item"><span class="grow"><b><?= e($h['label']) ?></b> <small class="muted">· <?= e($h['events']) ?></small><br><small class="muted mono"><?= e(mb_strimwidth($h['url'], 0, 60, '…')) ?></small></span><?= $h['active'] ? '<span class="tag green">actif</span>' : '<span class="tag">coupé</span>' ?></div><?php endforeach; ?>
    <a class="btn btn-sm btn-light" href="<?= url('/pro/integrations') ?>">Webhooks et journal des envois</a>
  </div>
</div>
<div class="split mt">
  <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="mail"><h2>Brevo &amp; Mailchimp</h2>
    <p class="muted mb0"><small>Les acheteurs qui ont accepté tes actus (<?= $optins ?> aujourd'hui) partent automatiquement dans ta liste, toutes les 10 minutes. Les désinscrits ne sont jamais envoyés.</small></p>
    <div class="field"><label>Outil</label><select name="mail_provider"><option value="">Aucun</option><option value="brevo" <?= $s['mail_provider'] === 'brevo' ? 'selected' : '' ?>>Brevo (ex-Sendinblue)</option><option value="mailchimp" <?= $s['mail_provider'] === 'mailchimp' ? 'selected' : '' ?>>Mailchimp</option></select></div>
    <div class="grid2"><div class="field"><label>Clé API</label><input name="mail_key" placeholder="<?= e($mask($s['mail_key']) ?: 'xkeysib-… ou …-us21') ?>" autocomplete="off"></div><div class="field"><label>Liste</label><input name="mail_list" value="<?= e($s['mail_list']) ?>" placeholder="Brevo : n° · Mailchimp : Audience ID"></div></div>
    <div class="row"><button class="btn">Enregistrer</button><?php if ($s['mail_key']): ?><button class="btn btn-light" name="action" value="mail_test">Tester la connexion</button><?php endif; ?></div>
    <?php foreach ($sync as $r): ?><div class="list-item"><span class="grow"><?= e(ucfirst($r['provider'])) ?> · <?= $r['status'] === 'ok' ? 'envoyés' : 'erreurs (' . e($r['status']) . ')' ?></span><b><?= (int)$r['n'] ?></b><small class="muted"><?= e(short_date($r['last'])) ?></small></div><?php endforeach; ?>
  </form>
  <form method="post" class="card stack"><?= csrf_field() ?><input type="hidden" name="action" value="ads"><h2>Suivi publicitaire côté serveur</h2>
    <p class="muted mb0"><small>Chaque commande payée est envoyée à l'API Conversions de Meta et à Google Analytics 4 (Measurement Protocol), même si l'acheteur bloque les cookies. Données personnelles hachées (SHA-256), identifiant d'événement commun pour éviter les doublons avec ton pixel.</small></p>
    <div class="grid2"><div class="field"><label>Meta · ID du pixel</label><input name="meta_pixel" value="<?= e($s['meta_pixel']) ?>" inputmode="numeric"></div><div class="field"><label>Meta · jeton d'accès</label><input name="meta_token" placeholder="<?= e($mask($s['meta_token']) ?: 'EAAG…') ?>" autocomplete="off"></div></div>
    <div class="field"><label>Meta · code de test (facultatif)</label><input name="meta_test" value="<?= e($s['meta_test']) ?>" placeholder="TEST12345"></div>
    <div class="grid2"><div class="field"><label>GA4 · ID de mesure</label><input name="ga4_id" value="<?= e($s['ga4_id']) ?>" placeholder="G-XXXXXXX"></div><div class="field"><label>GA4 · secret API</label><input name="ga4_secret" placeholder="<?= e($mask($s['ga4_secret']) ?: 'secret Measurement Protocol') ?>" autocomplete="off"></div></div>
    <button class="btn">Enregistrer</button>
    <?php foreach ($capi as $r): ?><div class="list-item"><span class="grow"><?= $r['provider'] === 'meta' ? 'Meta' : 'Google Analytics 4' ?> · <?= ['sent' => 'envoyées', 'pending' => 'en attente', 'failed' => 'en échec'][$r['status']] ?? e($r['status']) ?></span><b><?= (int)$r['n'] ?></b><small class="muted"><?= e(short_date($r['last'])) ?></small></div><?php endforeach; ?>
    <?php if ($lastErr): ?><small class="muted">Dernière erreur (<?= e($lastErr['provider']) ?>) : <span class="mono"><?= e(mb_strimwidth((string)$lastErr['response'], 0, 140, '…')) ?></span></small><?php endif; ?>
  </form>
</div>
