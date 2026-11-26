<?php /** @var array $ev, $people, $rates; string $src, $q, $format; int $rate */
$qs = array_filter(['src' => $src, 'rate' => $rate ?: null, 'q' => $q, 'format' => $format, 'only' => input('only')]); ?>
<div class="card stack"><h2>Badges nominatifs</h2>
  <p class="muted mb0"><small>Pour les salons, congrès et soirées pros : un badge avec le nom, la structure, le tarif et le QR code du billet (scannable à l'entrée). Imprime tout d'un coup avant l'événement, ou un par un à l'arrivée (recherche + « Imprimer »).</small></p>
  <form class="row wrap" style="gap:8px">
    <select name="src" style="width:auto"><option value="tickets">Participants</option><option value="accred" <?= $src === 'accred' ? 'selected' : '' ?>>Accréditations</option></select>
    <select name="rate" style="width:auto"><option value="">Tous les tarifs</option><?php foreach ($rates as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $rate === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select>
    <select name="only" style="width:auto"><option value="">Tous</option><option value="in" <?= input('only') === 'in' ? 'selected' : '' ?>>Déjà entrés</option></select>
    <select name="format" style="width:auto"><option value="a4">A4 · 8 badges par page</option><option value="label" <?= $format === 'label' ? 'selected' : '' ?>>Étiquette 90 × 60 mm</option></select>
    <input name="q" value="<?= e($q) ?>" placeholder="Nom ou code" class="grow"><button class="btn btn-light">Filtrer</button>
  </form>
  <div class="row"><a class="btn" target="_blank" href="<?= url('/pro/evenements/' . $ev['id'] . '/badges-nominatifs', $qs + ['print' => 1]) ?>">Imprimer <?= count($people) ?> badge(s)</a></div>
</div>
<div class="card table-card mt"><div class="table-wrap"><table class="table"><thead><tr><th>Nom</th><th>Structure</th><th>Tarif</th><th>Code</th><th></th></tr></thead><tbody>
  <?php foreach (array_slice($people, 0, 200) as $p): ?><tr><td><b><?= e($p['name']) ?></b></td><td><?= e($p['company']) ?></td><td><small><?= e($p['role']) ?></small></td><td class="mono"><small><?= e($p['code']) ?></small></td>
    <td class="right"><a class="btn btn-sm btn-light" target="_blank" href="<?= url('/pro/evenements/' . $ev['id'] . '/badges-nominatifs', ['src' => $src, 'q' => $p['code'], 'format' => $format, 'print' => 1]) ?>">Imprimer</a></td></tr><?php endforeach; ?>
  <?php if (!$people): ?><tr><td colspan="5" class="empty">Personne avec ces filtres.</td></tr><?php endif; ?></tbody></table></div></div>
