<?php
/** @var array $c, $org, $events, $counts */
$aud = Campaigns::audience($c);
$evOpts = array_map(fn($e) => ['id' => (int)$e['id'], 'name' => $e['name'], 'date' => short_date($e['starts_at'])], $events);
?>
<form method="post" id="camp-form" class="camp-layout" data-preview="<?= url('/pro/campagnes/' . $c['id'] . '/apercu') ?>"
  data-blocks="<?= e(json_encode(Campaigns::blocks($c), JSON_UNESCAPED_UNICODE)) ?>" data-events="<?= e(json_encode($evOpts, JSON_UNESCAPED_UNICODE)) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="blocks" id="blocks-input">
  <div class="camp-main">
    <?php if ($c['status'] === 'scheduled'): ?><div class="flash warning">Programmée pour le <b><?= e(fr_date($c['scheduled_at'])) ?></b>. Tu peux encore la modifier, ou <button class="link-btn" name="action" value="cancel">annuler la programmation</button>.</div><?php endif; ?>

    <div class="card">
      <div class="card-head"><h2>1. L'e-mail</h2><?= Campaigns::badge($c['status']) ?></div>
      <div class="field"><label for="c-name">Nom interne <small class="muted">(toi seul le vois)</small></label><input id="c-name" name="name" value="<?= e($c['name']) ?>" maxlength="160"></div>
      <div class="field"><label for="c-subject">Objet</label><input id="c-subject" name="subject" value="<?= e($c['subject']) ?>" maxlength="190" placeholder="Ex. : {prenom}, on remet ça le 12 juillet" data-live>
        <small><span id="subj-len"><?= mb_strlen((string)$c['subject']) ?></span> caractères · idéalement moins de 50 · <code>{prenom}</code> est remplacé par le prénom</small></div>
      <div class="field mb0"><label for="c-pre">Texte d'aperçu <small class="muted">(la ligne grise après l'objet dans la boîte de réception)</small></label><input id="c-pre" name="preheader" value="<?= e((string)$c['preheader']) ?>" maxlength="190" placeholder="Ex. : Les places partent vite, on te prévient en premier" data-live></div>
    </div>

    <div class="card">
      <div class="card-head"><h2>2. À qui ?</h2><span class="aud-count" id="aud-count"><b><?= number_format($audienceCount, 0, ',', ' ') ?></b> destinataire<?= $audienceCount > 1 ? 's' : '' ?></span></div>
      <div class="aud-source">
        <label class="aud-opt"><input type="radio" name="source" value="optin" <?= $aud['source'] !== 'customers' ? 'checked' : '' ?> data-aud>
          <span><b>Inscrits aux actus</b><small><?= number_format($counts['optin'], 0, ',', ' ') ?> contacts ont coché « recevoir les prochains événements »</small></span></label>
        <label class="aud-opt"><input type="radio" name="source" value="customers" <?= $aud['source'] === 'customers' ? 'checked' : '' ?> data-aud>
          <span><b>Tous mes clients</b><small><?= number_format($counts['customers'], 0, ',', ' ') ?> acheteurs · seulement pour annoncer des événements du même genre (règle RGPD des « produits analogues »)</small></span></label>
      </div>
      <div class="grid2 mt">
        <div class="field mb0"><label for="c-seg">Segment</label><select id="c-seg" name="segment" data-aud><?php foreach (Campaigns::SEGMENTS as $k => $l): ?><option value="<?= e($k) ?>" <?= $aud['segment'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="field mb0"><label for="c-not">Exclure ceux qui ont déjà un billet pour</label><select id="c-not" name="not_event" data-aud><option value="0">— personne d'exclu —</option><?php foreach ($events as $e2): ?><option value="<?= (int)$e2['id'] ?>" <?= (int)$aud['not_event'] === (int)$e2['id'] ? 'selected' : '' ?>><?= e($e2['name']) ?></option><?php endforeach; ?></select></div>
      </div>
      <?php if ($events): ?>
      <details class="mt" <?= $aud['events'] ? 'open' : '' ?>><summary class="link-btn">Seulement les personnes venues à certains événements<?= $aud['events'] ? ' (' . count($aud['events']) . ')' : '' ?></summary>
        <div class="check-grid"><?php foreach ($events as $e2): ?><label class="check"><input type="checkbox" name="events[]" value="<?= (int)$e2['id'] ?>" <?= in_array((int)$e2['id'], $aud['events'], true) ? 'checked' : '' ?> data-aud> <?= e($e2['name']) ?> <small class="muted"><?= e(short_date($e2['starts_at'])) ?></small></label><?php endforeach; ?></div>
      </details>
      <?php endif; ?>
      <p class="muted mb0" style="font-size:13px;margin-top:10px">Les <?= (int)$counts['unsub'] ?> personne(s) désinscrite(s) sont toujours exclues automatiquement.</p>
    </div>

    <div class="card">
      <div class="card-head"><h2>3. Le contenu</h2><small class="muted">Glisse ou utilise ↑ ↓ pour réordonner</small></div>
      <div id="blocks" class="blocks"></div>
      <div class="block-add">
        <span class="muted">Ajouter :</span>
        <button type="button" class="btn btn-sm btn-light" data-add="heading">Titre</button>
        <button type="button" class="btn btn-sm btn-light" data-add="text">Texte</button>
        <button type="button" class="btn btn-sm btn-light" data-add="event">Événement</button>
        <button type="button" class="btn btn-sm btn-light" data-add="button">Bouton</button>
        <button type="button" class="btn btn-sm btn-light" data-add="image">Image</button>
        <button type="button" class="btn btn-sm btn-light" data-add="divider">Séparateur</button>
      </div>
      <p class="muted mb0" style="font-size:13px;margin-top:10px">Astuces : <code>**gras**</code>, <code>{prenom}</code>. Le bloc « Événement » met l'affiche, la date, le prix et un bouton suivi : les ventes qu'il génère sont comptées dans les stats.</p>
    </div>

    <div class="card camp-send">
      <h2>4. On envoie ?</h2>
      <div class="row" style="gap:8px;align-items:flex-end">
        <div class="field mb0 grow"><label for="test-email">Envoyer un test à</label><input type="email" id="test-email" name="test_email" value="<?= e(user()['email']) ?>"></div>
        <button class="btn btn-light" name="action" value="test">Envoyer le test</button>
      </div>
      <hr>
      <div class="row" style="gap:8px;flex-wrap:wrap;align-items:flex-end">
        <button class="btn btn-lg" name="action" value="send" data-confirm="Envoyer maintenant à toute l'audience ? On ne peut pas rappeler un e-mail parti">Envoyer maintenant</button>
        <span class="muted">ou</span>
        <div class="field mb0"><label for="sched">Programmer pour le</label><input type="datetime-local" id="sched" name="scheduled_at" value="<?= $c['scheduled_at'] ? e(date('Y-m-d\TH:i', strtotime($c['scheduled_at']))) : '' ?>" min="<?= e(date('Y-m-d\TH:i')) ?>"></div>
        <button class="btn btn-light" name="action" value="schedule">Programmer</button>
        <span class="grow"></span>
        <button class="btn btn-ghost" name="action" value="save">Enregistrer le brouillon</button>
      </div>
      <div class="row mt" style="gap:12px"><button class="link-btn" name="action" value="duplicate">Dupliquer</button><?php if ($c['status'] === 'draft'): ?><button class="link-btn neg" name="action" value="delete" data-confirm="Supprimer ce brouillon ?">Supprimer</button><?php endif; ?></div>
    </div>
  </div>

  <aside class="camp-preview">
    <div class="preview-bar">
      <b>Aperçu</b>
      <div class="seg-mini" role="group" aria-label="Taille d'aperçu"><button type="button" class="on" data-size="desktop">Ordinateur</button><button type="button" data-size="mobile">Mobile</button></div>
    </div>
    <div class="inbox-line"><span class="inbox-from"><?= e($org['name']) ?></span><span class="inbox-subj" id="pv-subj"><?= e($c['subject'] ?: '(sans objet)') ?></span><span class="inbox-pre" id="pv-pre"><?= e((string)$c['preheader']) ?></span></div>
    <div class="preview-frame" id="pv-wrap"><iframe id="pv" title="Aperçu de l'e-mail" sandbox="allow-same-origin"></iframe></div>
  </aside>
</form>
<script src="<?= asset('js/campaigns.js') ?>"></script>
