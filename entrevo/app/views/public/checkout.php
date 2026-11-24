<?php
$input = 'custom_field_html';?>
<div class="steps"><span class="done">Billets</span><span class="on">Informations</span><span>Paiement</span><span>Confirmation</span></div>
<div class="checkout">
  <form method="post" id="checkout"><?= csrf_field() ?>
    <div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
    <?php if ($waitlist): ?><div class="flash info">⏳ Place réservée pour toi jusqu'au <?= e(fr_date($waitlist['expires_at'])) ?>.</div><?php endif; ?>
    <?php if ($partner): ?><div class="flash info">🤝 Lot partenaire : <?= e($partner['name']) ?></div><?php endif; ?>
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
      <p class="muted mb0">Facultatif : laisse vide et les billets seront à ton nom, tu pourras les renommer après l'achat. 🦇</p>
      <?php foreach ($sum['lines'] as $l): if ($l['kind'] !== 'rate') continue;
          $tf = array_values(array_filter($ticketFields, fn($f) => !$f['only'] || in_array($l['rate_id'], $f['only'], true)));
          $n = $l['qty'] * $l['pack'];
          for ($i = 0; $i < $n; $i++): $p = 'h[' . $l['rate_id'] . '][' . $i . ']'; $o = $old['h'][$l['rate_id']][$i] ?? []; $seat = $l['seats'][$i] ?? null; ?>
        <div class="holder">
          <div class="holder-title"><span>🎟️ <?= e($l['name']) ?><?= $n > 1 ? ' n°' . ($i + 1) : '' ?></span><?php if ($seat): ?><span class="ticket-seat">💺 <?= e($seatLabels[$seat] ?? $seat) ?></span><?php endif; ?></div>
          <div class="grid2">
            <div class="field"><label>Prénom</label><input name="<?= $p ?>[first_name]" value="<?= e($o['first_name'] ?? '') ?>" class="h-first"></div>
            <div class="field"><label>Nom</label><input name="<?= $p ?>[last_name]" value="<?= e($o['last_name'] ?? '') ?>" class="h-last"></div>
          </div>
          <?php foreach ($tf as $f) echo $input($f, $p . '[f][' . $f['id'] . ']', (string)($o['f'][$f['id']] ?? '')); ?>
        </div>
      <?php endfor; endforeach; ?>
    </div>

    <div class="card">
      <label class="check"><input type="checkbox" name="optin" value="1" <?= !empty($_POST['optin']) ? 'checked' : '' ?>> <span>📬 Je veux recevoir les prochains événements de <b><?= e($org['name']) ?></b> par e-mail (désinscription en un clic).</span></label>
      <div style="height:10px"></div>
      <?= Buyers::ageFieldsHtml(Buyers::requiredAge($ev, array_values(array_map(fn($l) => $l['rate_id'], array_filter($sum['lines'], fn($l) => $l['kind'] === 'rate'))))) ?>
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
      <p class="muted" style="font-size:14px"><?= e(fr_date($session['starts_at'] ?? $ev['starts_at'])) ?><br><?= e($ev['venue']) ?></p>
      <?php foreach ($sum['lines'] as $l): ?>
        <div class="summary-line"><span><?= (int)$l['qty'] ?> × <?= $l['kind'] === 'product' ? '✨ ' : '' ?><?= e($l['name']) ?><?= $l['pack'] > 1 ? ' (pack ' . $l['pack'] . ')' : '' ?></span><span><?= e(money($l['qty'] * $l['unit_price'])) ?></span></div>
      <?php endforeach; ?>
      <?php if ($sum['donation']): ?><div class="summary-line"><span>💛 Don</span><span><?= e(money($sum['donation'])) ?></span></div><?php endif; ?>
      <?php $gd = (int)($sum['group']['amount'] ?? 0); if ($gd): ?><div class="summary-line" style="color:var(--green)"><span>👥 Tarif groupe (−<?= (int)$sum['group']['pct'] ?> %)</span><span>−<?= e(money($gd)) ?></span></div><?php endif; ?>
      <?php if ($sum['discount'] - $gd > 0): ?><div class="summary-line" style="color:var(--green)"><span>Code <?= e($sum['promo']['code'] ?? '') ?></span><span>−<?= e(money($sum['discount'] - $gd)) ?></span></div><?php endif; ?>
      <?php if ($sum['fees']): ?><div class="summary-line"><span>Frais de service</span><span><?= e(money($sum['fees'])) ?></span></div><?php endif; ?>
      <div class="summary-line total"><span>Total</span><span><?= e(money($sum['total'])) ?></span></div>
      <a href="<?= url('/e/' . $ev['slug'], $session ? ['seance' => $session['id']] : []) ?>" style="font-size:14px">← Je change d'avis</a>
    </div>
  </aside>
</div>
