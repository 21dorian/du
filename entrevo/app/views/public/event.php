<?php
$anyOnSale = (bool)array_filter($rates, fn($r) => $r['sale']['ok']);
$hasCodes = (bool)val('SELECT COUNT(*) FROM promos WHERE event_id = ? AND active = 1', [$ev['id']]);
$addr = trim($ev['venue'] . ' ' . $ev['address'] . ' ' . $ev['city']);
?>
<?php if ($ev['status'] === 'draft'): ?><div class="flash warning">👀 Aperçu : cet événement est en brouillon. Toi seul le vois.</div><?php endif; ?>
<div class="event-top">
  <div class="event-poster"><?php if ($ev['image']): ?><img src="<?= url($ev['image']) ?>" alt="<?= e($ev['name']) ?>"><?php endif; ?></div>
  <div>
    <span class="eyebrow"><?= e($org['name']) ?></span>
    <h1 class="event-title"><?= e($ev['name']) ?></h1>
    <dl class="event-facts">
      <div class="fact"><dt>Quand</dt><dd><b><?= e(fr_date($ev['starts_at'])) ?></b><?php if ($ev['ends_at']): ?><span class="muted">jusqu'au <?= e(fr_date($ev['ends_at'])) ?></span><?php endif; ?></dd></div>
      <?php if ($addr !== ''): ?>
      <div class="fact"><dt>Où</dt><dd><b><?= e($ev['venue'] ?: $ev['city']) ?></b><span class="muted"><?= e(trim($ev['address'] . ' ' . $ev['city'])) ?></span><br><a href="https://www.google.com/maps/search/?api=1&amp;query=<?= urlencode($addr) ?>" target="_blank" rel="noopener">📍 Y aller</a></dd></div>
      <?php endif; ?>
      <div class="fact"><dt>Par</dt><dd><a href="<?= url('/o/' . $org['slug']) ?>"><?= e($org['name']) ?></a></dd></div>
    </dl>
    <?php if ($anyOnSale): ?><a class="btn btn-lg" href="#billets">Je réserve 🎟️</a><?php else: ?><span class="badge grey"><?= $rates ? e($rates[0]['sale']['why']) : 'Billetterie fermée 🔒' ?></span><?php endif; ?>
  </div>
</div>

<div class="event-layout">
  <div>
    <?php if ($ev['description']): ?><h2>Le programme</h2><div class="prose"><?= e($ev['description']) ?></div><?php endif; ?>
  </div>
  <aside class="buy-box" id="billets">
    <form class="tickets-box" method="post" id="buy" action="<?= url('/e/' . $ev['slug']) ?>"><?= csrf_field() ?>
      <div class="tickets-box-head"><h2>🎟️ Billets</h2><small><?= $org['fee_mode'] === 'buyer' ? 'Frais inclus dans le total' : 'Prix tout compris, zéro surprise' ?></small></div>
      <?php if (!$rates): ?><div class="rate"><span class="muted">Pas encore de billets. Patience… 🍿</span></div><?php endif; ?>
      <?php foreach ($rates as $r): $fee = Tickets::unitFee($org, (int)$r['price']); $max = $r['sale']['ok'] ? min(50, (int)$r['sale']['remaining'], (int)$r['max_per_order'] ?: 50, (int)$ev['max_per_order'] ?: 50) : 0; ?>
        <div class="rate <?= $r['sale']['ok'] ? '' : 'off' ?>">
          <div class="rate-info">
            <b><?= e($r['name']) ?></b>
            <?php if ($r['description']): ?><small><?= e($r['description']) ?></small><?php endif; ?>
            <?php if (!$r['sale']['ok']): ?><span class="rate-tag" style="color:var(--muted)"><?= e($r['sale']['why']) ?></span>
            <?php elseif ($r['sale']['remaining'] <= 10): ?><span class="rate-tag">😬 Plus que <?= (int)$r['sale']['remaining'] ?> place<?= $r['sale']['remaining'] > 1 ? 's' : '' ?></span>
            <?php elseif ($r['hidden']): ?><span class="rate-tag" style="color:var(--primary)">🤫 Tarif secret débloqué</span><?php endif; ?>
          </div>
          <div class="rate-price"><?= (int)$r['price'] === 0 ? 'Gratuit' : e(money((int)$r['price'])) ?><?php if ($fee): ?><small>+ <?= e(money($fee)) ?> de frais</small><?php endif; ?></div>
          <?php if ($max > 0): ?>
          <div class="qty" data-price="<?= (int)$r['price'] + $fee ?>">
            <button type="button" data-d="-1" aria-label="Moins">−</button>
            <input type="number" name="qty[<?= (int)$r['id'] ?>]" value="<?= (int)($cart['q'][$r['id']] ?? 0) ?>" min="0" max="<?= $max ?>" inputmode="numeric" aria-label="Quantité <?= e($r['name']) ?>">
            <button type="button" data-d="1" aria-label="Plus">+</button>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if ($promo): ?>
        <div class="applied-code"><span>🎉 Code <?= e($promo['code']) ?> : <?= $promo['kind'] === 'percent' ? '−' . (int)$promo['value'] . ' %' : '−' . e(money((int)$promo['value'])) . ' par billet' ?></span>
        <button class="linklike" name="action" value="remove_code" formnovalidate>retirer</button></div>
      <?php endif; ?>
      <?php if ($anyOnSale): ?>
        <div class="buy-foot">
          <div class="buy-total"><span>Total</span><span id="buy-total">0,00 €</span></div>
          <button class="btn btn-block btn-lg" name="action" value="buy">C'est parti →</button>
          <p class="secure-note">🔒 Paiement sécurisé. Tes billets arrivent direct par e-mail.</p>
        </div>
      <?php endif; ?>
    </form>
    <?php if ($hasCodes && $anyOnSale): ?>
    <form method="post" action="<?= url('/e/' . $ev['slug']) ?>"><?= csrf_field() ?>
      <details class="code-box"><summary>T'as un code ? 🤫</summary>
        <div class="row" style="margin-top:12px;flex-wrap:nowrap;gap:0"><input type="text" name="code" placeholder="CODE" style="text-transform:uppercase" required aria-label="Code"><button class="btn" name="action" value="code">OK</button></div>
      </details>
    </form>
    <?php endif; ?>
  </aside>
</div>
