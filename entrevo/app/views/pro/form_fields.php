<?php
$types = ['text' => 'Texte court', 'textarea' => 'Texte long', 'select' => 'Liste de choix', 'checkbox' => 'Case à cocher', 'date' => 'Date', 'email' => 'E-mail', 'phone' => 'Téléphone', 'number' => 'Nombre'];
$form = function (?array $f) use ($types, $rates) {
    $p = $f ?? ['id' => 0, 'label' => '', 'type' => 'text', 'options' => '', 'required' => 0, 'per_ticket' => 1, 'sort' => 0, 'rate_ids' => ''];
    $sel = array_map('intval', array_filter(explode(',', (string)$p['rate_ids'])));
    ob_start(); ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="field_id" value="<?= (int)$p['id'] ?>">
      <div class="grid2">
        <div class="field"><label>Question *</label><input name="label" value="<?= e($p['label']) ?>" required placeholder="Ex. : Taille de t-shirt"></div>
        <div class="field"><label>Type de réponse</label><select name="type"><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>" <?= $p['type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="field"><label>Choix possibles (un par ligne, pour « Liste de choix »)</label><textarea name="options" rows="3"><?= e($p['options']) ?></textarea></div>
      <div class="field"><label>Pour les tarifs</label><div class="row" style="gap:6px 16px"><?php foreach ($rates as $r): ?><label class="check" style="font-weight:400"><input type="checkbox" name="rate_ids[]" value="<?= (int)$r['id'] ?>" <?= in_array((int)$r['id'], $sel, true) ? 'checked' : '' ?>> <?= e($r['name']) ?></label><?php endforeach; ?></div><small>Aucun coché = tous les tarifs.</small></div>
      <div class="row" style="gap:22px;margin-bottom:14px">
        <label class="check"><input type="radio" name="per_ticket" value="1" <?= $p['per_ticket'] ? 'checked' : '' ?>> Demandée pour chaque participant</label>
        <label class="check"><input type="radio" name="per_ticket" value="0" <?= !$p['per_ticket'] ? 'checked' : '' ?>> Une fois par commande</label>
        <label class="check"><input type="checkbox" name="required" value="1" <?= $p['required'] ? 'checked' : '' ?>> Obligatoire</label>
        <label style="display:flex;gap:8px;align-items:center;font-weight:500">Ordre <input type="number" name="sort" value="<?= (int)$p['sort'] ?>" style="width:80px"></label>
      </div>
      <div class="form-actions"><button class="btn"><?= $p['id'] ? 'Enregistrer' : 'Ajouter la question' ?></button>
      <?php if ($p['id']): ?><button class="btn btn-ghost" style="color:var(--red)" name="action" value="delete" formnovalidate data-confirm="Supprimer cette question ? Les réponses déjà données restent dans les commandes.">Supprimer</button><?php endif; ?></div>
    </form>
<?php return ob_get_clean(); };
?>
<p class="muted">Nom, prénom et e-mail sont toujours demandés. Ajoute tes propres questions (taille de t-shirt, régime alimentaire, club…) : elles apparaissent à l'achat et dans l'export. 📋</p>
<?php foreach ($fields as $f): ?>
  <details class="box"><summary><span><?= e($f['label']) ?><?= $f['required'] ? ' *' : '' ?></span><span class="muted" style="font-weight:500"><?= e($types[$f['type']]) ?> · <?= $f['per_ticket'] ? 'par participant' : 'par commande' ?></span></summary><?= $form($f) ?></details>
<?php endforeach; ?>
<details class="box" <?= !$fields ? 'open' : '' ?>><summary style="color:var(--primary)">+ Ajouter une question</summary><?= $form(null) ?></details>
