<?php $progRows = array_merge($prog, array_fill(0, max(2, 4 - count($prog)), ['when' => '', 'title' => '', 'text' => '']));
$faqRows = array_merge($faq, array_fill(0, max(2, 3 - count($faq)), ['q' => '', 'a' => '']));
$pracFields = ['acces' => ['Venir', 'Adresse précise, transports en commun, navettes…'], 'parking' => ['Stationnement', 'Parkings, tarifs, covoiturage conseillé…'], 'pmr' => ['Accessibilité', 'Accès PMR, places réservées, accompagnateurs…'],
  'regles' => ['À savoir', 'Âge, pièce d\'identité, objets interdits, vestiaire…'], 'food' => ['Bar & restauration', 'Food-trucks, paiement sur place, eau gratuite…'], 'contact' => ['Contact', 'Une question le jour J ? Téléphone, e-mail…']]; ?>
<p class="muted lead-sm">Ce que tu remplis ici s'affiche sur la page publique de l'événement, sous la description. Les questions fréquentes sont aussi lues par Google.</p>
<form method="post" class="stack page-editor"><?= csrf_field() ?>
  <div class="card">
    <div class="card-head"><h2>Programme</h2><a class="btn btn-sm btn-light" href="<?= url('/e/' . $ev['slug']) ?>" target="_blank">Voir la page</a></div>
    <div class="rows" data-rows="prog">
      <?php foreach ($progRows as $p): ?>
        <div class="row-prog"><input name="p_when[]" value="<?= e($p['when']) ?>" placeholder="20h30" aria-label="Heure"><input name="p_title[]" value="<?= e($p['title']) ?>" placeholder="Ouverture des portes, Nova Kid…" aria-label="Titre"><input name="p_text[]" value="<?= e($p['text']) ?>" placeholder="Détail (facultatif)" aria-label="Détail"></div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-sm btn-light" data-add-row="prog">Ajouter une ligne</button>
  </div>
  <div class="card">
    <h2>Infos pratiques</h2>
    <div class="grid2">
      <?php foreach ($pracFields as $k => [$lab, $ph]): ?>
        <div class="field"><label><?= $lab ?></label><textarea name="prac[<?= $k ?>]" rows="3" placeholder="<?= e($ph) ?>"><?= e((string)($prac[$k] ?? '')) ?></textarea></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <h2>Questions fréquentes</h2>
    <div class="rows" data-rows="faq">
      <?php foreach ($faqRows as $q): ?>
        <div class="row-faq"><input name="f_q[]" value="<?= e($q['q']) ?>" placeholder="Question : Peut-on venir avec des enfants ?" aria-label="Question"><textarea name="f_a[]" rows="2" placeholder="Réponse" aria-label="Réponse"><?= e($q['a']) ?></textarea></div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-sm btn-light" data-add-row="faq">Ajouter une question</button>
  </div>
  <div class="form-actions"><button class="btn btn-lg">Enregistrer la page</button></div>
</form>
<script>
document.querySelectorAll('[data-add-row]').forEach(function (b) {
  b.addEventListener('click', function () {
    var box = document.querySelector('[data-rows="' + b.dataset.addRow + '"]'), last = box.lastElementChild.cloneNode(true);
    last.querySelectorAll('input,textarea').forEach(function (i) { i.value = ''; });
    box.appendChild(last); last.querySelector('input').focus();
  });
});
</script>
