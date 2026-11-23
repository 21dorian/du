<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($ev['name']) ?></title><link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>:root{--primary:<?= e(setting('primary_color', '#ff5a4e')) ?>}body{background:transparent}.w{padding:12px}.tickets-box{max-width:none}</style></head>
<body class="pub"><div class="w">
<?php $target = url('/e/' . $ev['slug'] . '/panier'); ?>
<form class="tickets-box" method="get" action="<?= e($target) ?>" target="_blank" id="buy">
  <div class="tickets-box-head"><h2>🎟️ <?= e($ev['name']) ?></h2><small><?= e($session ? fr_date($session['starts_at']) : fr_date($ev['starts_at'], false)) ?></small></div>
  <?php if ($sessions): ?>
    <div class="rate"><select onchange="location.href='?seance='+this.value" aria-label="Date"><option value="">📅 Choisis ta date…</option>
      <?php foreach ($sessions as $x): ?><option value="<?= (int)$x['id'] ?>" <?= $session && (int)$session['id'] === (int)$x['id'] ? 'selected' : '' ?>><?= e(fr_date($x['starts_at'])) ?></option><?php endforeach; ?></select></div>
    <?php if ($session): ?><input type="hidden" name="seance" value="<?= (int)$session['id'] ?>"><?php endif; ?>
  <?php endif; ?>
  <?php foreach ($rates as $r): $fee = Tickets::unitFee($org, (int)$r['price']); $max = $r['sale']['ok'] ? min(20, (int)$r['sale']['remaining']) : 0; ?>
    <div class="rate <?= $max ? '' : 'off' ?>"><div class="rate-info"><b><?= e($r['name']) ?></b><?php if (!$max): ?><span class="rate-tag" style="color:var(--muted)"><?= e($r['sale']['why'] ?? '') ?></span><?php endif; ?></div>
      <div class="rate-price"><?= $r['price_mode'] === 'free' ? 'Prix libre' : ((int)$r['price'] ? e(money((int)$r['price'])) : 'Gratuit') ?><?php if ($fee): ?><small>+ <?= e(money($fee)) ?></small><?php endif; ?></div>
      <?php if ($max): ?><div class="qty" data-price="<?= (int)$r['price'] + $fee ?>"><button type="button" data-d="-1">−</button><input type="number" name="qty[<?= (int)$r['id'] ?>]" value="0" min="0" max="<?= $max ?>"><button type="button" data-d="1">+</button></div><?php endif; ?></div>
  <?php endforeach; ?>
  <?php if ($rates): ?><div class="buy-foot"><div class="buy-total"><span>Total</span><span id="buy-total">0,00 €</span></div><button class="btn btn-block btn-lg">Réserver →</button>
  <p class="secure-note">🔒 Paiement sécurisé sur <?= e(setting('platform_name')) ?> (nouvel onglet)</p></div><?php endif; ?>
</form>
</div><script src="<?= asset('js/app.js') ?>"></script></body></html>
