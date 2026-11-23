<?php $init = $map ?: ['stage' => 'Scène', 'categories' => [['id' => 'cat1', 'name' => 'Catégorie 1', 'color' => '#ffb703'], ['id' => 'cat2', 'name' => 'Catégorie 2', 'color' => '#4cc9f0']],
    'blocks' => [['id' => 'ORCH', 'name' => 'Orchestre', 'rows' => 8, 'cols' => 16, 'x' => 0, 'y' => 0, 'row_start' => 'A', 'num_start' => 1, 'cat' => 'cat1']], 'disabled' => []]; ?>
<?php if (!$map): ?><div class="notice mb">💺 Pas de plan : placement libre. Crée un plan pour que les acheteurs choisissent leur siège. Chaque tarif peut être limité à une catégorie de places (onglet Tarifs).</div><?php endif; ?>
<?php if ($sold): ?><div class="flash warning"><?= $sold ?> places numérotées sont déjà vendues : évite de renommer ou de supprimer des blocs existants.</div><?php endif; ?>
<form method="post" id="seat-editor"><?= csrf_field() ?>
<input type="hidden" name="seatmap" id="seatmap-json" value="<?= e(json_encode($init, JSON_UNESCAPED_UNICODE)) ?>">
<div class="split-3">
  <div class="card">
    <div class="card-head"><h2>Aperçu</h2><small>Clique sur un siège pour le rendre indisponible (pilier, régie, PMR…)</small></div>
    <div class="seat-canvas" id="ed-canvas" style="background:#111"></div>
    <div class="row mt" style="gap:16px"><span id="ed-count" class="muted"></span></div>
  </div>
  <div>
    <div class="card"><h2>Scène</h2><input id="ed-stage" placeholder="Scène"></div>
    <div class="card"><div class="card-head"><h2>Catégories</h2><button type="button" class="btn btn-sm btn-light" id="ed-addcat">+ Catégorie</button></div><div id="ed-cats"></div></div>
    <div class="card"><div class="card-head"><h2>Blocs</h2><button type="button" class="btn btn-sm btn-light" id="ed-addblock">+ Bloc</button></div><div id="ed-blocks"></div>
      <small class="muted">Position X/Y en sièges depuis le coin haut-gauche : place deux blocs côte à côte avec un écart pour faire une allée.</small></div>
    <div class="form-actions"><button class="btn btn-lg">Enregistrer le plan</button><?php if ($map): ?><button class="btn btn-ghost" name="action" value="remove" data-confirm="Supprimer le plan (placement libre) ?">Supprimer</button><?php endif; ?></div>
  </div>
</div>
</form>
<script src="<?= asset('js/seat-editor.js') ?>"></script>
