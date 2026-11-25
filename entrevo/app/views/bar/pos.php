<?php /** @var array $ev, $bars, $products, $u */ ?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1"><title>Caisse · <?= e($ev['name']) ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>"><link rel="icon" href="<?= asset('img/icon.svg') ?>" type="image/svg+xml"></head>
<body class="pos-body" data-api="<?= url('/bar/' . $ev['id'] . '/api') ?>" data-csrf="<?= e(csrf_token()) ?>">
<?php if (!(int)$ev['cashless_enabled']): ?><div class="pos-off"><h1>Cashless désactivé</h1><p>Active-le dans l'événement, onglet Jour J › Cashless & bars.</p></div><?php else: ?>
<header class="pos-head"><b><?= e($ev['name']) ?></b><select id="bar"><?php foreach ($bars as $b): ?><option value="<?= (int)$b['id'] ?>" data-pin="<?= (int)$b['has_pin'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select><span class="pos-user"><?= e($u['name']) ?></span></header>
<main class="pos">
  <section class="pos-grid" id="grid">
    <?php $cat = null; foreach ($products as $p): if ($p['category'] !== $cat): $cat = $p['category']; ?><h3><?= e(ucfirst($cat)) ?></h3><?php endif; ?>
      <button class="pos-prod" data-id="<?= (int)$p['id'] ?>" data-price="<?= (int)$p['price'] + (int)$p['deposit'] ?>" data-bars="<?= e((string)$p['bar_ids']) ?>"><b><?= e($p['name']) ?></b><span><?= e(money((int)$p['price'] + (int)$p['deposit'])) ?></span></button>
    <?php endforeach; ?>
  </section>
  <aside class="pos-side">
    <div class="pos-scan"><video id="cam" playsinline muted hidden></video><canvas id="cv" hidden></canvas>
      <div class="row"><input id="code" placeholder="Scanne ou tape le code du billet" autocomplete="off"><button class="btn btn-light" id="camBtn" type="button">Caméra</button></div>
      <div class="pos-who" id="who">Aucun billet scanné</div></div>
    <div class="pos-cart" id="cart"></div>
    <div class="pos-total"><span>Total</span><b id="total">0,00 €</b></div>
    <button class="btn btn-lg btn-block" id="pay" disabled>Encaisser</button>
    <div class="row mt"><button class="btn btn-light grow" id="clear" type="button">Vider</button><button class="btn btn-light grow" id="topBtn" type="button">Recharger</button></div>
    <div class="pos-top" id="top" hidden><div class="row"><?php foreach ([1000, 2000, 5000] as $a): ?><button class="btn btn-light" data-amt="<?= $a ?>" type="button"><?= money($a) ?></button><?php endforeach; ?></div>
      <div class="row mt"><input id="topAmt" inputmode="decimal" placeholder="Montant"><select id="topMethod"><option value="cash">Espèces</option><option value="card">Carte (TPE)</option></select><button class="btn" id="topGo" type="button">Valider</button></div></div>
    <div class="pos-msg" id="msg"></div>
  </aside>
</main>
<script src="<?= asset('js/jsQR.js') ?>"></script>
<script>
(() => {
  const $ = id => document.getElementById(id), api = document.body.dataset.api, csrf = document.body.dataset.csrf;
  const fmt = c => (c / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
  let cart = {}, code = '', lastTx = null, pin = '';
  try { const b = localStorage.getItem('pos-bar'); if (b) $('bar').value = b; } catch (e) {}
  const post = (data) => { const f = new FormData(); Object.entries(data).forEach(([k, v]) => f.append(k, v)); f.append('bar_id', $('bar').value); f.append('pin', pin); f.append('_csrf', csrf);
    return fetch(api, { method: 'POST', body: f, credentials: 'same-origin', headers: { 'X-CSRF': csrf } }).then(r => r.json()); };
  const msg = (t, ok) => { const m = $('msg'); m.innerHTML = t; m.className = 'pos-msg ' + (ok ? 'ok' : 'ko'); };
  const filter = () => { const bar = $('bar').value; document.querySelectorAll('.pos-prod').forEach(b => b.hidden = b.dataset.bars !== '' && !b.dataset.bars.split(',').includes(bar)); };
  const render = () => { let t = 0; $('cart').innerHTML = Object.entries(cart).map(([id, q]) => { const b = document.querySelector('.pos-prod[data-id="' + id + '"]'); t += q * +b.dataset.price;
      return '<div class="pos-line"><span>' + q + ' × ' + b.querySelector('b').textContent + '</span><span>' + fmt(q * +b.dataset.price) + ' <button data-minus="' + id + '">−</button></span></div>'; }).join('');
    $('total').textContent = fmt(t); $('pay').disabled = !t || !code; };
  const lookup = (c) => { code = c.trim(); if (!code) return; post({ action: 'lookup', code }).then(j => { if (j.error) { code = ''; $('who').innerHTML = '<span class="ko">' + j.error + '</span>'; } else $('who').innerHTML = '<b>' + (j.name || 'Billet') + '</b> · solde <b>' + fmt(j.balance) + '</b>'; render(); }); };
  $('bar').addEventListener('change', () => { try { localStorage.setItem('pos-bar', $('bar').value); } catch (e) {} pin = ''; filter(); });
  $('grid').addEventListener('click', e => { const b = e.target.closest('.pos-prod'); if (!b) return; cart[b.dataset.id] = (cart[b.dataset.id] || 0) + 1; render(); });
  $('cart').addEventListener('click', e => { const id = e.target.dataset.minus; if (!id) return; if (--cart[id] <= 0) delete cart[id]; render(); });
  $('clear').onclick = () => { cart = {}; code = ''; $('code').value = ''; $('who').textContent = 'Aucun billet scanné'; render(); };
  $('code').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); lookup($('code').value); } });
  const needPin = () => { const o = $('bar').selectedOptions[0]; if (o && o.dataset.pin === '1' && !pin) { pin = prompt('Code du bar') || ''; } };
  $('pay').onclick = () => { needPin(); post({ action: 'sale', code, items: JSON.stringify(Object.entries(cart).map(([i, q]) => [+i, q])) }).then(j => {
    if (j.error) return msg(j.error, false); lastTx = j.tx; msg('Payé ' + fmt(j.total) + ' · reste <b>' + fmt(j.balance) + '</b> <button id="undo">Annuler</button>', true);
    $('undo').onclick = () => post({ action: 'undo', tx_id: lastTx }).then(r => msg(r.error || 'Annulé · solde ' + fmt(r.balance), !r.error)); cart = {}; code = ''; $('code').value = ''; $('who').textContent = 'Aucun billet scanné'; render(); }); };
  $('topBtn').onclick = () => $('top').hidden = !$('top').hidden;
  document.querySelectorAll('[data-amt]').forEach(b => b.onclick = () => $('topAmt').value = (+b.dataset.amt / 100).toString());
  $('topGo').onclick = () => { if (!code) return msg('Scanne d\'abord le billet.', false); needPin(); post({ action: 'topup', code, amount: $('topAmt').value, method: $('topMethod').value }).then(j => { if (j.error) return msg(j.error, false); lastTx = j.tx; msg('Rechargé · nouveau solde <b>' + fmt(j.balance) + '</b>', true); $('who').innerHTML = '<b>' + (j.name || 'Billet') + '</b> · solde <b>' + fmt(j.balance) + '</b>'; $('top').hidden = true; }); };
  // caméra (jsQR)
  let stream = null; $('camBtn').onclick = async () => { const v = $('cam'); if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; v.hidden = true; return; }
    try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }); v.srcObject = stream; v.hidden = false; await v.play();
      const cv = $('cv'), cx = cv.getContext('2d', { willReadFrequently: true }); const tick = () => { if (!stream) return; if (v.videoWidth) { cv.width = v.videoWidth; cv.height = v.videoHeight; cx.drawImage(v, 0, 0);
        const r = jsQR(cx.getImageData(0, 0, cv.width, cv.height).data, cv.width, cv.height); if (r && r.data) { $('code').value = r.data; lookup(r.data); stream.getTracks().forEach(t => t.stop()); stream = null; v.hidden = true; return; } } requestAnimationFrame(tick); }; tick(); }
    catch (e) { msg('Caméra indisponible : utilise une douchette ou tape le code.', false); } };
  filter(); render(); $('code').focus();
})();
</script>
<?php endif; ?>
</body></html>
