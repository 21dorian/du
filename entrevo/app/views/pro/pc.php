<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PC sécurité · <?= e($ev['name']) ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="icon" href="<?= asset('img/icon.svg') ?>" type="image/svg+xml">
</head>
<body class="pc-body" data-url="<?= url('/pro/evenements/' . $ev['id'] . '/pc/donnees') ?>" data-csrf="<?= e(csrf_token()) ?>">
<header class="pc-head">
  <div class="pc-title"><span class="live-dot">live</span><b><?= e($ev['name']) ?></b><small id="pc-at"><?= e($snap['at']) ?></small></div>
  <div class="pc-head-actions">
    <a class="pc-btn" href="<?= url('/pro/evenements/' . $ev['id'] . '/controle') ?>">← Contrôle</a>
    <button class="pc-btn" type="button" id="pc-full">⛶ Plein écran</button>
  </div>
</header>
<main class="pc-grid">
  <section class="pc-card pc-total">
    <h2>Sur place</h2>
    <div class="pc-big"><b id="t-inside"><?= (int)$snap['total']['inside'] ?></b><span id="t-cap"><?= $snap['total']['capacity'] ? '/ ' . (int)$snap['total']['capacity'] : '' ?></span></div>
    <div class="pc-gauge"><i id="t-bar" style="width:<?= (int)($snap['total']['pct'] ?? 0) ?>%"></i></div>
    <div class="pc-kv"><span>Entrés <b id="t-checked"><?= (int)$snap['total']['checked'] ?></b></span><span>Encore attendus <b id="t-rem"><?= (int)$snap['total']['remaining'] ?></b></span></div>
    <div class="pc-kv"><span>Rythme <b id="t-rate"><?= e((string)$snap['rate']) ?></b> /min</span><span id="t-eta"><?= $snap['eta'] ? 'Tout le monde entré dans ~' . (int)$snap['eta'] . ' min' : '' ?></span></div>
  </section>
  <section class="pc-card pc-zones"><h2>Zones</h2><div id="zones"></div></section>
  <section class="pc-card pc-flux"><h2>Entrées par 5 minutes (2 h)</h2><div class="pc-cols" id="flux"></div></section>
  <section class="pc-card"><h2>Refus récents</h2><div id="refusals" class="pc-list"></div></section>
  <section class="pc-card"><h2>Téléphones de contrôle</h2><div id="devices" class="pc-list"></div></section>
  <section class="pc-card"><h2>Message aux contrôleurs</h2>
    <form id="pc-msg" class="pc-form"><textarea name="body" rows="2" maxlength="280" placeholder="Ex. : Porte B fermée, tout le monde par la A" required></textarea>
      <div class="pc-form-row"><select name="level"><option value="info">ℹ️ Info</option><option value="warn">⚠️ Attention</option><option value="alert">🚨 Urgent</option></select><button class="pc-btn primary">Envoyer</button></div></form>
    <div id="messages" class="pc-list"></div>
  </section>
</main>
<script>
(function () {
  'use strict';
  var body = document.body, url = body.dataset.url, $ = function (id) { return document.getElementById(id); };
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text !== undefined) n.textContent = text; return n; }
  function render(d) {
    $('pc-at').textContent = 'mis à jour à ' + d.at;
    $('t-inside').textContent = d.total.inside; $('t-cap').textContent = d.total.capacity ? '/ ' + d.total.capacity : '';
    $('t-bar').style.width = (d.total.pct || 0) + '%'; $('t-bar').className = d.total.pct >= 100 ? 'bad' : (d.total.pct >= 90 ? 'warn' : '');
    $('t-checked').textContent = d.total.checked; $('t-rem').textContent = d.total.remaining; $('t-rate').textContent = d.rate;
    $('t-eta').textContent = d.eta ? 'Tout le monde entré dans ~' + d.eta + ' min' : '';
    var z = $('zones'); z.textContent = '';
    d.zones.forEach(function (x) {
      var row = el('div', 'pc-zone ' + x.level + (x.open ? '' : ' closed'));
      var top = el('div', 'pc-zone-top'); top.appendChild(el('b', '', x.name + (x.open ? '' : ' (fermée)')));
      top.appendChild(el('span', '', x.inside + (x.capacity ? ' / ' + x.capacity + ' · ' + x.pct + ' %' : ' présents')));
      row.appendChild(top);
      var g = el('div', 'pc-gauge'), i = el('i', x.level); i.style.width = (x.capacity ? Math.min(100, x.pct) : 0) + '%'; g.appendChild(i); row.appendChild(g);
      row.appendChild(el('small', '', x.per_min + ' entrées/min' + (x.refused ? ' · ' + x.refused + ' refus (30 min)' : '') + (x.capacity ? '' : ' · pas de jauge définie')));
      z.appendChild(row);
    });
    if (!d.zones.length) z.appendChild(el('p', 'pc-muted', 'Aucune porte définie.'));
    var f = $('flux'); f.textContent = ''; var mx = 1; d.flux.forEach(function (b) { mx = Math.max(mx, b.n); });
    d.flux.forEach(function (b, k) { var c = el('div', 'pc-col'); var bar = el('i'); bar.style.height = Math.max(2, Math.round(b.n / mx * 100)) + '%'; bar.title = b.t + ' : ' + b.n + ' entrée(s)'; c.appendChild(bar); c.appendChild(el('small', '', k % 4 === 0 ? b.t : '')); f.appendChild(c); });
    var r = $('refusals'); r.textContent = '';
    d.refusals.forEach(function (x) { var it = el('div', 'pc-item'); it.appendChild(el('span', 'pc-time', x.at)); it.appendChild(el('span', 'pc-grow', x.label + ' · ' + x.who)); it.appendChild(el('small', '', x.ap)); r.appendChild(it); });
    if (!d.refusals.length) r.appendChild(el('p', 'pc-muted', 'Aucun refus 👌'));
    var dv = $('devices'); dv.textContent = '';
    d.devices.forEach(function (x) { var it = el('div', 'pc-item'); it.appendChild(el('span', 'pc-dot' + (x.online ? ' on' : ''))); it.appendChild(el('span', 'pc-grow', x.name + ' · ' + x.ap + (x.dir === 'out' ? ' (sortie)' : ''))); it.appendChild(el('small', x.battery !== null && x.battery <= 20 ? 'pc-low' : '', (x.pending ? '⏳ ' + x.pending + ' · ' : '') + (x.battery !== null ? '🔋 ' + x.battery + ' %' : ''))); dv.appendChild(it); });
    if (!d.devices.length) dv.appendChild(el('p', 'pc-muted', 'Aucun téléphone connecté.'));
    var m = $('messages'); m.textContent = '';
    (d.messages || []).forEach(function (x) { var it = el('div', 'pc-item ' + x.level); it.appendChild(el('span', 'pc-time', x.at)); it.appendChild(el('span', 'pc-grow', x.body)); m.appendChild(it); });
  }
  function load() { fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(render).catch(function () { $('pc-at').textContent = '📵 connexion perdue, nouvelle tentative…'; }); }
  load(); setInterval(load, 5000);
  $('pc-msg').addEventListener('submit', function (e) {
    e.preventDefault(); var fd = new FormData(e.target); fd.append('_csrf', body.dataset.csrf);
    fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function () { e.target.reset(); load(); });
  });
  $('pc-full').onclick = function () { if (document.fullscreenElement) document.exitFullscreen(); else if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen(); };
  try { if (navigator.wakeLock) navigator.wakeLock.request('screen').catch(function () { /* écran de veille : sans gravité */ }); } catch (e) { /* rien */ }
})();
</script>
</body>
</html>
