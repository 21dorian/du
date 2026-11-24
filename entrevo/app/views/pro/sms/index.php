<?php /** @var array $org, $list, $optouts, $events, $kpi; int $reach */ $win = Sms::windowOpen(); ?>
<p class="muted lead-sm">Un SMS la veille ou pour les dernières places : c'est le message le plus lu. On n'écrit qu'aux acheteurs qui ont accepté de recevoir tes actus et laissé un mobile. La mention STOP est ajoutée à chaque envoi.</p>
<?php if (!Sms::live()): ?><div class="notice info"><span><b>Mode test.</b> Le service SMS n'est pas encore branché par la plateforme : tu peux tout préparer et tester, les messages sont écrits dans le journal au lieu de partir.</span></div><?php endif; ?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Joignables par SMS</span><b><?= number_format($reach, 0, ',', ' ') ?></b></div>
  <div class="kpi-mini"><span>SMS envoyés</span><b><?= number_format((int)$kpi['sent'], 0, ',', ' ') ?></b></div>
  <div class="kpi-mini"><span>Coût</span><b><?= e(money((int)$kpi['cost'])) ?></b></div>
  <div class="kpi-mini"><span>Ventes générées</span><b><?= e(money((int)$kpi['revenue'])) ?></b></div>
</div>

<div class="split-3">
  <form class="card sms-compose" method="post" id="sms-form" data-estimate="<?= url('/pro/sms/estimation') ?>"><?= csrf_field() ?>
    <div class="card-head"><h2>Nouveau SMS</h2><small class="muted">Expéditeur : <b><?= e(Sms::sender($org)) ?></b></small></div>
    <div class="sms-grid">
      <div>
        <div class="field"><label>Nom de la campagne <small class="muted">(pour toi)</small></label><input name="name" maxlength="190" placeholder="Dernières places Jazz au Port"></div>
        <div class="field"><label>Message</label>
          <textarea name="body" rows="5" maxlength="600" data-sms-body placeholder="{prenom}, plus que 20 places pour samedi ! Réserve ici : {lien}"></textarea>
          <div class="sms-tools"><button type="button" class="chip" data-insert="{prenom}">+ Prénom</button><button type="button" class="chip" data-insert="{lien}">+ Lien vers l'événement</button>
            <span class="sms-count" data-sms-count>0 caractère · 1 SMS</span></div>
        </div>
        <div class="field"><label>Événement du lien <small class="muted">(pour {lien}, suivi des ventes)</small></label>
          <select name="event_id"><option value="">Aucun</option><?php foreach ($events as $e): ?><option value="<?= (int)$e['id'] ?>"><?= e($e['name']) ?> · <?= e(short_date($e['starts_at'])) ?></option><?php endforeach; ?></select></div>
        <h3 class="mt">À qui ?</h3>
        <div class="grid2">
          <div class="field"><label>Profil</label><select name="segment"><?php foreach (Campaigns::SEGMENTS as $k => $l): if ($k === 'absents') continue; ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Sauf les inscrits à</label><select name="not_event"><option value="0">Personne</option><?php foreach ($events as $e): ?><option value="<?= (int)$e['id'] ?>"><?= e($e['name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <details class="sms-events"><summary>Seulement les personnes venues à…</summary>
          <div class="check-list"><?php foreach ($events as $e): ?><label class="check"><input type="checkbox" name="events[]" value="<?= (int)$e['id'] ?>"> <span><?= e($e['name']) ?> <small class="muted"><?= e(short_date($e['starts_at'])) ?></small></span></label><?php endforeach; ?></div>
        </details>
        <h3 class="mt">Quand ?</h3>
        <div class="seg seg-full"><label class="seg-r"><input type="radio" name="when" value="now" checked><span>Tout de suite</span></label><label class="seg-r"><input type="radio" name="when" value="later"><span>Programmer</span></label></div>
        <div class="field mt" data-show-if="when=later"><label>Date et heure</label><input type="datetime-local" name="scheduled_at"></div>
        <p class="mt mb0"><small class="muted">Les SMS commerciaux ne partent que du lundi au samedi, de 8 h à 20 h, hors jours fériés.<?= $win ? '' : ' En ce moment c\'est fermé : l\'envoi démarrera le ' . e(fr_date(date('Y-m-d H:i:s', Sms::nextWindow()))) . '.' ?></small></p>
      </div>
      <aside class="sms-side">
        <div class="phone-mock"><div class="phone-top"><b><?= e(Sms::sender($org)) ?></b><small>SMS</small></div><div class="bubble" data-sms-preview><span class="muted">Ton message apparaîtra ici.</span></div></div>
        <div class="sms-estimate"><div><span>Destinataires</span><b data-sms-n><?= number_format($reach, 0, ',', ' ') ?></b></div><div><span>Coût estimé</span><b data-sms-cost>—</b></div></div>
        <div class="field mt"><label>Tester sur mon mobile</label><div class="row"><input name="test_phone" type="tel" placeholder="06 12 34 56 78"><button class="btn btn-light" name="action" value="test">Tester</button></div></div>
        <label class="check mt"><input type="checkbox" name="confirm" value="1"> <span>J'ai relu, ces personnes ont accepté de recevoir mes actus.</span></label>
        <button class="btn btn-lg btn-block mt" name="action" value="send">Envoyer la campagne</button>
      </aside>
    </div>
  </form>

  <div class="stack">
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <h2>Expéditeur</h2>
      <div class="field"><label>Nom affiché sur le téléphone</label><input name="sms_sender" maxlength="11" value="<?= e((string)$org['sms_sender']) ?>" placeholder="<?= e(Sms::sender($org)) ?>"><small>3 à 11 lettres ou chiffres, sans espace ni accent.</small></div>
      <button class="btn btn-block">Enregistrer</button>
    </form>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="optout">
      <h2>Liste STOP <small class="muted">(<?= count($optouts) ?>)</small></h2>
      <p class="muted" style="font-size:13px">Personnes qui ne veulent plus de SMS. Elles sont exclues de toutes tes campagnes.</p>
      <div class="field"><textarea name="phones" rows="2" placeholder="Un numéro par ligne"></textarea></div>
      <button class="btn btn-light btn-block">Ajouter</button>
      <?php if ($optouts): ?><ul class="optout-list"><?php foreach (array_slice($optouts, 0, 12) as $o): ?><li><span class="mono"><?= e(Sms::pretty($o['phone'])) ?></span><button class="linklike" form="rm-<?= e(md5($o['phone'])) ?>">Retirer</button></li><?php endforeach; ?></ul><?php endif; ?>
    </form>
    <?php foreach (array_slice($optouts, 0, 12) as $o): ?><form id="rm-<?= e(md5($o['phone'])) ?>" method="post" hidden><?= csrf_field() ?><input type="hidden" name="action" value="optout_remove"><input type="hidden" name="phone" value="<?= e($o['phone']) ?>"></form><?php endforeach; ?>
  </div>
</div>

<div class="card table-card">
  <div class="card-head"><h2>Historique</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Campagne</th><th>Statut</th><th class="num">Envoyés</th><th class="num">SMS / pers.</th><th class="num">Coût</th><th>Date</th></tr></thead>
    <tbody>
    <?php foreach ($list as $c): ?>
      <tr data-href="<?= url('/pro/sms/' . $c['id']) ?>">
        <td><a href="<?= url('/pro/sms/' . $c['id']) ?>"><b><?= e($c['name']) ?></b></a><br><small class="muted"><?= e(mb_strimwidth((string)$c['body'], 0, 70, '…')) ?></small></td>
        <td><?= Sms::badge($c['status']) ?></td>
        <td class="num"><?= (int)$c['sent'] ?> / <?= (int)$c['total'] ?><?= (int)$c['failed'] ? '<br><small class="muted">' . (int)$c['failed'] . ' échec(s)</small>' : '' ?></td>
        <td class="num"><?= (int)$c['segments'] ?></td>
        <td class="num"><?= e(money((int)$c['cost'])) ?></td>
        <td class="nowrap"><small><?= e(short_date($c['sent_at'] ?: $c['scheduled_at'] ?: $c['created_at'])) ?></small></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="6" class="empty">Pas encore de campagne SMS.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<script>
(() => {
  const f = document.getElementById('sms-form'); if (!f) return;
  const body = f.querySelector('[data-sms-body]');
  const $ = s => f.querySelector(s);
  let t;
  const run = () => {
    const d = new FormData(f); d.delete('action');
    fetch(f.dataset.estimate, { method: 'POST', body: d, credentials: 'same-origin', headers: { 'X-CSRF': f.querySelector('[name=_csrf]').value } })
      .then(r => r.json()).then(j => {
        $('[data-sms-n]').textContent = j.recipients.toLocaleString('fr-FR');
        $('[data-sms-cost]').textContent = j.cost;
        const c = $('[data-sms-count]');
        c.textContent = j.chars + ' caractère' + (j.chars > 1 ? 's' : '') + ' · ' + j.segments + ' SMS' + (j.unicode ? ' · accents spéciaux ou emoji : SMS plus courts' : '');
        c.classList.toggle('warn', j.segments > 1);
        const p = $('[data-sms-preview]'); p.textContent = body.value.trim() ? j.preview : ''; if (!body.value.trim()) p.innerHTML = '<span class="muted">Ton message apparaîtra ici.</span>';
      }).catch(() => {});
  };
  f.addEventListener('input', () => { clearTimeout(t); t = setTimeout(run, 250); });
  f.addEventListener('change', () => { clearTimeout(t); t = setTimeout(run, 50); });
  f.querySelectorAll('[data-insert]').forEach(b => b.addEventListener('click', () => {
    const v = b.dataset.insert, s = body.selectionStart ?? body.value.length;
    body.value = body.value.slice(0, s) + v + body.value.slice(body.selectionEnd ?? s); body.focus(); body.selectionStart = body.selectionEnd = s + v.length; run();
  }));
  run();
})();
</script>
