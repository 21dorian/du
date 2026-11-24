<?php $n = (int)$kpi['n']; $avg = $n ? round((float)$kpi['avg'], 1) : null; $maxD = max(1, max($dist)); ?>
<p class="muted">Le lendemain de chaque événement, les participants venus reçoivent un e-mail « T'as kiffé ? » (réglable dans <a href="<?= url('/pro/campagnes') ?>">Campagnes › Envois automatiques</a>). Les avis visibles s'affichent sur ta <a href="<?= url('/o/' . $org['slug']) ?>" target="_blank">page publique</a>.</p>
<div class="rv-top">
  <div class="card rv-score">
    <b class="rv-avg"><?= $avg === null ? '—' : e(number_format($avg, 1, ',', '')) ?></b>
    <span class="rv-stars" aria-hidden="true"><?= $avg === null ? '☆☆☆☆☆' : Buyers::stars($avg) ?></span>
    <small class="muted"><?= $n ?> avis<?= $asked >= $n && $asked ? ' · ' . e(number_format(min(100, $n / $asked * 100), 0, ',', ' ')) . ' % de réponse' : '' ?></small>
  </div>
  <div class="card rv-dist" aria-label="Répartition des notes">
    <?php for ($k = 5; $k >= 1; $k--): ?>
      <a class="rv-row <?= $note === $k ? 'on' : '' ?>" href="<?= url('/pro/avis', array_filter(['ev' => $evId ?: null, 'note' => $note === $k ? null : $k])) ?>" title="<?= $dist[$k] ?> avis à <?= $k ?> étoile<?= $k > 1 ? 's' : '' ?>">
        <span class="rv-k"><?= $k ?> ★</span><span class="rv-bar"><i style="width:<?= round($dist[$k] / $maxD * 100) ?>%"></i></span><span class="rv-n"><?= $dist[$k] ?></span></a>
    <?php endfor; ?>
  </div>
  <div class="card rv-side">
    <div class="kpi-mini"><span>Avec commentaire</span><b><?= (int)$kpi['comments'] ?></b></div>
    <div class="kpi-mini"><span>Vérifiés (billet scanné)</span><b><?= (int)$kpi['verified'] ?></b></div>
  </div>
</div>

<?php if ($byEvent && !$evId): ?>
<div class="card table-card">
  <div class="card-head"><h2>Par événement</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Événement</th><th>Date</th><th class="num">Avis</th><th class="num">Note moyenne</th></tr></thead>
    <tbody><?php foreach ($byEvent as $b): ?><tr data-href="<?= url('/pro/avis', ['ev' => $b['id']]) ?>"><td><a href="<?= url('/pro/avis', ['ev' => $b['id']]) ?>"><?= e($b['name']) ?></a></td><td><?= e(short_date($b['starts_at'])) ?></td><td class="num"><?= (int)$b['n'] ?></td><td class="num"><b><?= e(number_format((float)$b['avg'], 1, ',', '')) ?></b> <span class="rv-stars sm" aria-hidden="true"><?= Buyers::stars((float)$b['avg']) ?></span></td></tr><?php endforeach; ?></tbody>
  </table></div>
</div>
<?php endif; ?>

<form class="filters" method="get">
  <select name="ev" onchange="this.form.submit()"><option value="">Tous les événements</option><?php foreach ($events as $x): ?><option value="<?= (int)$x['id'] ?>" <?= $evId === (int)$x['id'] ? 'selected' : '' ?>><?= e($x['name']) ?></option><?php endforeach; ?></select>
  <?php if ($note): ?><input type="hidden" name="note" value="<?= $note ?>"><a class="btn btn-sm btn-light" href="<?= url('/pro/avis', array_filter(['ev' => $evId ?: null])) ?>"><?= $note ?> ★ ✕</a><?php endif; ?>
</form>

<div class="rv-list">
<?php foreach ($list as $r): ?>
  <div class="card rv-item <?= $r['visible'] ? '' : 'hidden-rv' ?>">
    <div class="rv-head">
      <span class="rv-stars" aria-label="<?= (int)$r['rating'] ?> sur 5"><?= Buyers::stars((float)$r['rating']) ?></span>
      <b><?= e($r['author'] ?: 'Anonyme') ?></b><?= $r['verified'] ? ' <span class="badge green">Venu·e</span>' : '' ?><?= $r['visible'] ? '' : ' <span class="badge grey">Masqué</span>' ?>
      <small class="muted"><?= e($r['ev_name']) ?> · <?= e(short_date($r['created_at'])) ?> · <a href="<?= url('/pro/commandes', ['q' => $r['ref']]) ?>"><?= e($r['ref']) ?></a></small>
      <form method="post" class="inline-form rv-act"><?= csrf_field() ?><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-light" name="action" value="toggle"><?= $r['visible'] ? 'Masquer' : 'Afficher' ?></button></form>
    </div>
    <?php if ($r['comment']): ?><p class="rv-text"><?= nl2br(e($r['comment'])) ?></p><?php else: ?><p class="muted rv-text">Note seule, pas de commentaire.</p><?php endif; ?>
    <details class="rv-reply" <?= $r['reply'] ? 'open' : '' ?>>
      <summary><?= $r['reply'] ? 'Ta réponse (publique)' : 'Répondre publiquement' ?></summary>
      <form method="post" class="rv-reply-form"><?= csrf_field() ?><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="reply">
        <textarea name="reply" rows="2" maxlength="1000" placeholder="Merci pour ton retour ! …"><?= e((string)$r['reply']) ?></textarea>
        <button class="btn btn-sm"><?= $r['reply'] ? 'Mettre à jour' : 'Publier la réponse' ?></button></form>
    </details>
  </div>
<?php endforeach; ?>
<?php if (!$list): ?><div class="card empty">Pas encore d'avis<?= $evId || $note ? ' avec ces filtres' : '' ?>. Ils arrivent le lendemain de tes événements</div><?php endif; ?>
</div>
