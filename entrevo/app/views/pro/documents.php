<?php
/** @var array $docs, $events, $f, $ev */
$base = $ev ? '/pro/evenements/' . $ev['id'] . '/documents' : '/pro/documents';
$today = date('Y-m-d'); $soon = date('Y-m-d', strtotime('+30 days'));
$expiring = count(array_filter($docs, fn($d) => $d['expires_at'] && $d['expires_at'] <= $soon));
?>
<div class="page-head-row">
  <div class="kpi-mini"><span>Documents</span><b><?= count($docs) ?></b></div>
  <div class="kpi-mini<?= $expiring ? ' warn' : '' ?>"><span>Échéance &lt; 30 j</span><b><?= $expiring ?></b></div>
  <div class="kpi-mini"><span>Espace utilisé</span><b><?= e(human_size($usage)) ?></b></div>
</div>
<div class="split-3">
  <div>
    <nav class="pill-tabs"><a class="<?= $f['cat'] === '' ? 'on' : '' ?>" href="<?= url($base, array_filter(['ev' => !$ev && $f['ev'] ? $f['ev'] : null])) ?>">Tout</a><?php foreach (DOC_CATEGORIES as $k => $l): ?><a class="<?= $f['cat'] === $k ? 'on' : '' ?>" href="<?= url($base, array_filter(['cat' => $k, 'ev' => !$ev && $f['ev'] ? $f['ev'] : null])) ?>"><?= $l ?></a><?php endforeach; ?></nav>
    <form class="filters card" method="get"><?php if ($f['cat']): ?><input type="hidden" name="cat" value="<?= e($f['cat']) ?>"><?php endif; ?>
      <div class="filters-main"><label class="search-field"><span aria-hidden="true"></span><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Titre, fichier, note…" aria-label="Rechercher"></label>
        <?php if (!$ev): ?><select name="ev" aria-label="Événement"><option value="">Tous les événements</option><?php foreach ($events as $e2): ?><option value="<?= (int)$e2['id'] ?>" <?= $f['ev'] === (int)$e2['id'] ? 'selected' : '' ?>><?= e($e2['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
        <button class="btn btn-sm">Filtrer</button></div></form>
    <div class="doc-grid">
      <?php foreach ($docs as $d): $exp = $d['expires_at']; $state = $exp ? ($exp < $today ? 'expired' : ($exp <= $soon ? 'soon' : '')) : ''; $ext = strtoupper(pathinfo((string)$d['original_name'], PATHINFO_EXTENSION) ?: 'DOC'); ?>
        <article class="doc <?= $state ?>">
          <a class="doc-ico" href="<?= url('/pro/documents/' . $d['id']) ?>" target="_blank"><span><?= e($ext) ?></span></a>
          <div class="doc-body">
            <a class="doc-title" href="<?= url('/pro/documents/' . $d['id']) ?>" target="_blank"><?= e($d['title']) ?></a>
            <small><?= DOC_CATEGORIES[$d['category']] ?? '' ?><?= !$ev && $d['ev_name'] ? ' · ' . e($d['ev_name']) : '' ?></small>
            <small class="muted"><?= e(human_size((int)$d['size'])) ?> · <?= e(short_date($d['created_at'])) ?><?= $d['uploader'] ? ' · ' . e($d['uploader']) : '' ?></small>
            <?php if ($exp): ?><span class="doc-exp"><?= $state === 'expired' ? 'Expiré le ' : ($state === 'soon' ? 'Échéance le ' : 'Valable jusqu\'au ') ?><?= e(date('d/m/Y', strtotime($exp))) ?></span><?php endif; ?>
            <?php if ($d['note']): ?><small><?= e($d['note']) ?></small><?php endif; ?>
          </div>
          <div class="doc-actions"><a class="icon-act" href="<?= url('/pro/documents/' . $d['id'], ['dl' => 1]) ?>" title="Télécharger" aria-label="Télécharger"></a>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>"><button class="icon-act" name="action" value="delete" title="Supprimer" aria-label="Supprimer" data-confirm="Supprimer « <?= e($d['title']) ?> » ?"></button></form></div>
        </article>
      <?php endforeach; ?>
      <?php if (!$docs): ?><p class="muted">Aucun document ici. Contrats, assurances, autorisations… tout au même endroit, accessible à ton équipe.</p><?php endif; ?>
    </div>
  </div>
  <div class="card">
    <h2>Ajouter un document</h2>
    <form method="post" enctype="multipart/form-data" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="upload">
      <div class="field mb0"><label>Fichier</label><input type="file" name="file" required><small>PDF, image, Word, Excel… 20 Mo max. Stocké en privé.</small></div>
      <div class="field mb0"><label>Titre</label><input name="title" placeholder="Ex. : Contrat Nova Kid"></div>
      <div class="field mb0"><label>Type</label><select name="category"><?php foreach (DOC_CATEGORIES as $k => $l): ?><option value="<?= $k ?>" <?= $f['cat'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <?php if (!$ev): ?><div class="field mb0"><label>Événement</label><select name="event_id"><option value="">Aucun (général)</option><?php foreach ($events as $e2): ?><option value="<?= (int)$e2['id'] ?>" <?= $f['ev'] === (int)$e2['id'] ? 'selected' : '' ?>><?= e($e2['name']) ?></option><?php endforeach; ?></select></div>
      <?php else: ?><input type="hidden" name="event_id" value="<?= (int)$ev['id'] ?>"><?php endif; ?>
      <div class="field mb0"><label>Date d'échéance <small class="muted">(assurance, autorisation…)</small></label><input type="date" name="expires_at"><small>On te le rappelle 30 jours avant dans « À traiter ».</small></div>
      <div class="field mb0"><label>Note</label><input name="note"></div>
      <button class="btn btn-sm">Ajouter</button>
    </form>
  </div>
</div>
