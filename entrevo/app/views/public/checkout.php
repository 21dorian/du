<?php
$input = function (array $f, string $name, string $val) {
    $req = $f['required'] ? ' required' : '';
    $id = 'x' . md5($name);
    $h = '<div class="field"><label for="' . $id . '">' . e($f['label']) . ($f['required'] ? ' *' : '') . '</label>';
    switch ($f['type']) {
        case 'textarea': $h .= '<textarea id="' . $id . '" name="' . e($name) . '"' . $req . '>' . e($val) . '</textarea>'; break;
        case 'select':
            $h .= '<select id="' . $id . '" name="' . e($name) . '"' . $req . '><option value="">— Choisir —</option>';
            foreach (field_options($f) as $o) $h .= '<option' . ($o === $val ? ' selected' : '') . '>' . e($o) . '</option>';
            $h .= '</select>'; break;
        case 'checkbox':
            $h = '<div class="field"><label class="check"><input type="checkbox" name="' . e($name) . '" value="1"' . ($val ? ' checked' : '') . $req . '> ' . e($f['label']) . ($f['required'] ? ' *' : '') . '</label>'; break;
        default:
            $type = ['date' => 'date', 'email' => 'email', 'phone' => 'tel', 'number' => 'number'][$f['type']] ?? 'text';
            $h .= '<input type="' . $type . '" id="' . $id . '" name="' . e($name) . '" value="' . e($val) . '"' . $req . '>';
    }
    return $h . '</div>';
};
?>
<div class="steps"><span class="done">Billets</span><span class="on">Informations</span><span>Paiement</span><span>Confirmation</span></div>
<div class="checkout">
  <form method="post" id="checkout"><?= csrf_field() ?>
    <?php if ($errors): ?><div class="flash error"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
    <div class="card">
      <h2>C'est toi ? 👋</h2>
      <div class="grid2">
        <div class="field"><label for="first_name">Prénom *</label><input id="first_name" name="first_name" value="<?= e($old['first_name'] ?? '') ?>" required autocomplete="given-name"></div>
        <div class="field"><label for="last_name">Nom *</label><input id="last_name" name="last_name" value="<?= e($old['last_name'] ?? '') ?>" required autocomplete="family-name"></div>
        <div class="field"><label for="email">E-mail *</label><input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>" required autocomplete="email"><small>Tes billets arrivent ici. Vérifie bien : on ne lit pas (encore) dans les pensées.</small></div>
        <div class="field"><label for="email2">Encore une fois, pour être sûr *</label><input type="email" id="email2" name="email2" value="<?= e($old['email2'] ?? '') ?>" required autocomplete="off"></div>
        <div class="field"><label for="phone">Téléphone</label><input type="tel" id="phone" name="phone" value="<?= e($old['phone'] ?? '') ?>" autocomplete="tel"></div>
      </div>
      <?php foreach ($orderFields as $f) echo $input($f, 'f[' . $f['id'] . ']', (string)($old['f'][$f['id']] ?? '')); ?>
    </div>

    <div class="card">
      <div class="card-head"><h2>Qui vient ? 👯</h2><button type="button" class="btn btn-sm btn-light" id="copy-buyer">C'est moi !</button></div>
      <p class="muted mb0">Un nom par billet. Pas de « Batman », merci. 🦇</p>
      <?php foreach ($sum['lines'] as $l): for ($i = 0; $i < $l['qty']; $i++): $p = 'h[' . $l['rate_id'] . '][' . $i . ']'; $o = $old['h'][$l['rate_id']][$i] ?? []; ?>
        <div class="holder">
          <div class="holder-title"><span>Billet « <?= e($l['name']) ?> »<?= $l['qty'] > 1 ? ' n°' . ($i + 1) : '' ?></span></div>
          <div class="grid2">
            <div class="field"><label>Prénom *</label><input name="<?= $p ?>[first_name]" value="<?= e($o['first_name'] ?? '') ?>" required class="h-first"></div>
            <div class="field"><label>Nom *</label><input name="<?= $p ?>[last_name]" value="<?= e($o['last_name'] ?? '') ?>" required class="h-last"></div>
          </div>
          <?php foreach ($ticketFields as $f) echo $input($f, $p . '[f][' . $f['id'] . ']', (string)($o['f'][$f['id']] ?? '')); ?>
        </div>
      <?php endfor; endforeach; ?>
    </div>

    <div class="card">
      <?php if (setting('cgv')): ?>
        <label class="check"><input type="checkbox" name="cgv" value="1" required> J'ai lu et j'accepte les <a href="<?= url('/cgv') ?>" target="_blank">conditions générales de vente</a> (oui, vraiment lu 😇).</label>
        <div style="height:14px"></div>
      <?php endif; ?>
      <button class="btn btn-lg btn-block"><?= $sum['total'] > 0 ? 'Payer ' . e(money($sum['total'])) . ' →' : 'Je réserve, c\'est gratuit 🎉' ?></button>
      <p class="muted center" style="font-size:13px;margin:10px 0 0">⏱️ Tes places sont bloquées <?= (int)setting('pending_minutes') ?> minutes. Pas de pression. (Un peu quand même.)</p>
    </div>
  </form>

  <aside class="buy-box">
    <div class="card">
      <h3><?= e($ev['name']) ?></h3>
      <p class="muted" style="font-size:14px"><?= e(fr_date($ev['starts_at'])) ?><br><?= e($ev['venue']) ?></p>
      <?php foreach ($sum['lines'] as $l): ?>
        <div class="summary-line"><span><?= (int)$l['qty'] ?> × <?= e($l['name']) ?></span><span><?= e(money($l['qty'] * $l['unit_price'])) ?></span></div>
      <?php endforeach; ?>
      <?php if ($sum['discount']): ?><div class="summary-line" style="color:var(--green)"><span>Code <?= e($sum['promo']['code']) ?></span><span>−<?= e(money($sum['discount'])) ?></span></div><?php endif; ?>
      <?php if ($sum['fees']): ?><div class="summary-line"><span>Frais de service</span><span><?= e(money($sum['fees'])) ?></span></div><?php endif; ?>
      <div class="summary-line total"><span>Total</span><span><?= e(money($sum['total'])) ?></span></div>
      <a href="<?= url('/e/' . $ev['slug']) ?>" style="font-size:14px">← Je change d'avis</a>
    </div>
  </aside>
</div>
