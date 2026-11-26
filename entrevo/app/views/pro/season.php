<?php /** @var array $ev, $created, $siblings, $counts */
$labels = ['sessions' => 'Dates / séances', 'fields' => 'Formulaire', 'aps' => 'Points d\'accès', 'products' => 'Options', 'promos' => 'Codes promo', 'shifts' => 'Créneaux bénévoles', 'budget' => 'Budget', 'tasks' => 'Tâches'];
$t0 = date('H:i', strtotime($ev['starts_at'])); ?>
<?php if ($created): ?><div class="card mb"><h2>Créé à l'instant</h2><?php foreach ($created as $c): ?><div class="list-item"><span class="grow"><b><?= e($c['name']) ?></b> <small class="muted">· <?= e(fr_date($c['starts_at'])) ?></small></span><?= badge($c['status']) ?><a class="btn btn-sm btn-light" href="<?= url('/pro/evenements/' . $c['id']) ?>">Ouvrir</a></div><?php endforeach; ?></div><?php endif; ?>
<div class="split" style="grid-template-columns:3fr 2fr">
  <form method="post" class="card stack" data-season><?= csrf_field() ?>
    <h2>Dupliquer sur toute une saison</h2>
    <p class="muted mb0"><small>« <?= e($ev['name']) ?> » sert de modèle : tarifs, page, réglages et ce que tu coches ci-dessous sont recopiés sur chaque date. Les dates de mise en vente des tarifs suivent le même décalage.</small></p>
    <nav class="pill-tabs"><a href="#" class="on" data-smode="list">Liste de dates</a><a href="#" data-smode="repeat">Répétition</a></nav>
    <input type="hidden" name="mode" value="list">
    <div data-spane="list" class="field"><label>Dates (une par ligne)</label><textarea name="dates" rows="6" placeholder="14/03/2027 20:30&#10;21/03/2027&#10;28/03/2027 18:00"></textarea><small class="muted">Sans heure, on prend l'heure ci-dessous. Jusqu'à 52 dates.</small></div>
    <div data-spane="repeat" hidden class="grid2"><div class="field"><label>Première date</label><input type="date" name="from"></div><div class="field"><label>Fréquence</label><select name="every"><option value="7">Chaque semaine</option><option value="14">Toutes les 2 semaines</option><option value="28">Toutes les 4 semaines</option><option value="1">Chaque jour</option></select></div><div class="field"><label>Nombre de dates</label><input name="count" inputmode="numeric" value="8"></div></div>
    <div class="grid2"><div class="field"><label>Heure par défaut</label><input type="time" name="time" value="<?= e($t0) ?>"></div><div class="field"><label>Nom de chaque date</label><input name="name_pattern" value="{nom} · {date}"><small class="muted">{nom} et {date} sont remplacés</small></div></div>
    <div class="field"><label>Recopier aussi</label><div class="grid2"><?php foreach ($labels as $k => $l): ?><label class="check"><input type="checkbox" name="copy[]" value="<?= $k ?>" <?= $counts[$k] ? 'checked' : 'disabled' ?>> <?= e($l) ?> <small class="muted">(<?= (int)$counts[$k] ?>)</small></label><?php endforeach; ?></div></div>
    <label class="check"><input type="checkbox" name="publish" value="1"> Mettre en vente tout de suite (sinon, brouillons à relire)</label>
    <button class="btn">Créer les dates</button>
  </form>
  <div class="card"><h2>Même série</h2><p class="muted"><small>Événements au nom proche déjà créés.</small></p>
    <?php foreach ($siblings as $s): ?><div class="list-item"><span class="grow"><a href="<?= url('/pro/evenements/' . $s['id']) ?>"><b><?= e($s['name']) ?></b></a><br><small class="muted"><?= e(fr_date($s['starts_at'])) ?> · <?= (int)$s['sold'] ?> billet(s)</small></span><?= badge($s['status']) ?></div><?php endforeach; ?>
    <?php if (!$siblings): ?><p class="empty">Aucune autre date pour l'instant.</p><?php endif; ?></div>
</div>
<script>document.querySelectorAll('[data-smode]').forEach(function(a){a.onclick=function(ev){ev.preventDefault();var m=a.dataset.smode;document.querySelector('[name=mode]').value=m;document.querySelectorAll('[data-smode]').forEach(function(x){x.classList.toggle('on',x===a)});document.querySelectorAll('[data-spane]').forEach(function(p){p.hidden=p.dataset.spane!==m})}});</script>
