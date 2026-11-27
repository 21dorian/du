<?php
$types = ['text' => 'Texte court', 'textarea' => 'Texte long', 'select' => 'Liste de choix', 'checkbox' => 'Case à cocher', 'date' => 'Date', 'email' => 'E-mail', 'phone' => 'Téléphone', 'number' => 'Nombre', 'country' => 'Pays', 'file' => 'Envoi de fichier', 'title' => 'Titre de section'];
$form = function (?array $f) use ($types, $rates, $fields) {
    $p = $f ?? ['id' => 0, 'label' => '', 'type' => 'text', 'options' => '', 'required' => 0, 'per_ticket' => 1, 'sort' => 0, 'rate_ids' => '', 'help' => '', 'cond_field' => null, 'cond_value' => '', 'default_on' => 0];
    $sel = array_map('intval', array_filter(explode(',', (string)$p['rate_ids'])));
    ob_start(); ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="field_id" value="<?= (int)$p['id'] ?>">
      <div class="grid2">
        <div class="field"><label>Question *</label><input name="label" value="<?= e($p['label']) ?>" required placeholder="Ex. : Taille de t-shirt"></div>
        <div class="field"><label>Type de réponse</label><select name="type"><?php foreach ($types as $k => $l): ?><option value="<?= $k ?>" <?= $p['type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="field"><label>Commentaire sous la question</label><input name="help" value="<?= e((string)($p['help'] ?? '')) ?>" placeholder="Ex. : sert à préparer les badges"></div>
      <div class="grid2"><div class="field"><label>Afficher seulement si…</label><select name="cond_field"><option value="">Toujours affichée</option><?php foreach ($fields as $o): if ((int)$o['id'] === (int)$p['id'] || $o['type'] === 'title') continue; ?><option value="<?= (int)$o['id'] ?>" <?= (int)($p['cond_field'] ?? 0) === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['label']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>… a pour réponse</label><input name="cond_value" value="<?= e((string)($p['cond_value'] ?? '')) ?>" placeholder="Ex. : Oui, Végétarien…"></div></div>
      <label class="check mb"><input type="checkbox" name="default_on" value="1" <?= !empty($p['default_on']) ? 'checked' : '' ?>> Case cochée par défaut (pour « Case à cocher »)</label>
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
<p class="muted">Nom, prénom et e-mail sont toujours demandés. Ajoute tes propres questions (taille de t-shirt, régime alimentaire, club…) : elles apparaissent à l'achat et dans l'export.</p>
<?php foreach ($fields as $f): ?>
  <details class="box"><summary><span><?= e($f['label']) ?><?= $f['required'] ? ' *' : '' ?></span><span class="muted" style="font-weight:500"><?= e($types[$f['type']]) ?> · <?= $f['per_ticket'] ? 'par participant' : 'par commande' ?></span></summary><?= $form($f) ?></details>
<?php endforeach; ?>
<details class="box" <?= !$fields ? 'open' : '' ?>><summary style="color:var(--primary)">+ Ajouter une question</summary><?= $form(null) ?></details>
