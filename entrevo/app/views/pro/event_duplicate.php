<?php /** @var array $ev, $counts; string $suggest */
$items = ['sessions' => ['Dates & séances', $counts['sessions']], 'fields' => ['Formulaire', $counts['fields']], 'aps' => ['Points d\'accès', $counts['aps']], 'products' => ['Options', $counts['products']],
  'promos' => ['Codes promo (hors lots)', $counts['promos']], 'shifts' => ['Créneaux bénévoles (sans les personnes)', $counts['shifts']], 'budget' => ['Budget prévisionnel (sans le réalisé)', $counts['budget']], 'tasks' => ['Tâches du rétroplanning (remises à faire)', $counts['tasks']]]; ?>
<form method="post" class="card u-mw760"><?= csrf_field() ?><input type="hidden" name="confirm" value="1">
  <h2>Reprendre « <?= e($ev['name']) ?> »</h2>
  <p class="muted">Pour la prochaine édition : tout est recopié en brouillon, et <b>toutes les dates sont décalées d'autant</b> (ouverture des ventes, paliers early bird, codes promo, créneaux bénévoles, échéances des tâches…). Aucune commande ni aucun participant n'est copié.</p>
  <div class="grid2">
    <div class="field"><label>Nom</label><input name="name" value="<?= e(preg_replace('~\b(20\d\d)\b~', (string)date('Y', strtotime($suggest)), $ev['name'])) ?>" maxlength="190"></div>
    <div class="field"><label>Début du nouvel événement</label><input type="datetime-local" name="starts_at" value="<?= e($suggest) ?>" required data-shift-from="<?= e(date('Y-m-d\TH:i', strtotime($ev['starts_at']))) ?>"><small data-shift-info></small></div>
  </div>
  <h3 class="mt">À recopier <small class="muted">(les <?= (int)$counts['rates'] ?> tarifs le sont toujours)</small></h3>
  <div class="mod-grid">
    <?php foreach ($items as $k => [$l, $n]): ?><label class="check"><input type="checkbox" name="copy[]" value="<?= $k ?>" <?= $n ? 'checked' : 'disabled' ?>> <span><?= e($l) ?> <small class="muted">(<?= (int)$n ?>)</small></span></label><?php endforeach; ?>
  </div>
  <div class="form-actions mt"><button class="btn btn-lg">Créer le brouillon</button><a class="btn btn-ghost" href="<?= url('/pro/evenements/' . $ev['id'] . '/modifier') ?>">Annuler</a></div>
</form>
<script>
(() => { const i = document.querySelector('[data-shift-from]'), o = document.querySelector('[data-shift-info]'); if (!i) return;
  const upd = () => { const d = Math.round((new Date(i.value) - new Date(i.dataset.shiftFrom)) / 86400000); o.textContent = isNaN(d) ? '' : (d === 0 ? 'Même date' : 'Dates décalées de ' + Math.abs(d) + ' jour' + (Math.abs(d) > 1 ? 's' : '') + (d < 0 ? ' en arrière' : '')); };
  i.addEventListener('input', upd); upd(); })();
</script>
