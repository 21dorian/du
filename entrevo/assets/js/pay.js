/* Entrevo Pay : tableau de bord en direct (actualisation toutes les 15 s) et petits comportements. */
(function () {
  'use strict';
  var root = document.querySelector('[data-live-url]');
  var money = function (c) { return (c / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' }); };
  var set = function (k, v) { document.querySelectorAll('[data-live="' + k + '"]').forEach(function (el) { el.textContent = v; }); };
  function render(L) {
    set('at', L.at);
    set('ttc', money(L.ttc)); set('sales', L.sales + ' ventes'); set('basket', money(L.basket)); set('hour', money(L.hour)); set('hour_n', L.hour_n + ' ventes · ' + L.per_min + '/min');
    set('today', money(L.today)); set('float', money(L.float)); set('loaded', money(L.loaded)); set('wallets', L.wallets); set('devices', L.devices_online + ' / ' + L.devices);
    var bars = document.querySelector('[data-live-bars]');
    if (bars) {
      var max = Math.max.apply(null, Object.keys(L.slots).map(function (k) { return L.slots[k]; }).concat([1]));
      bars.innerHTML = Object.keys(L.slots).map(function (k) { return '<div title="' + k + ' : ' + money(L.slots[k]) + '"><i style="height:' + Math.round(L.slots[k] / max * 100) + '%"></i><small>' + k + '</small></div>'; }).join('');
    }
    var pl = document.querySelector('[data-live-pos]');
    if (pl) {
      var top = Math.max.apply(null, L.bars.map(function (b) { return b.ttc; }).concat([1]));
      pl.innerHTML = L.bars.map(function (b) {
        return '<div class="row-it"><div><b>' + esc(b.name) + '</b><small>' + (b.operator ? esc(b.operator) + ' · ' : '') + b.devices + ' appareil(s) · ' + (b.open ? 'caisse ouverte' : 'caisse fermée') + '</small><div class="pmeter"><i style="width:' + Math.round(b.ttc / top * 100) + '%"></i></div></div>'
          + '<div class="num"><small>15 min</small>' + money(b.ttc15) + '</div><div class="num"><small>' + b.n + ' ventes</small><b>' + money(b.ttc) + '</b></div><div class="num"><small>espèces</small>' + money(b.cash) + '</div></div>';
      }).join('') || '<p class="pempty">Aucun point de vente.</p>';
    }
    var al = document.querySelector('[data-live-alerts]');
    if (al) al.innerHTML = L.alerts.map(function (a) { return '<div class="' + a[0] + '">' + esc(a[1]) + '</div>'; }).join('') || '<p class="pnote">Aucune alerte. Tout roule.</p>';
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  if (root) {
    var tick = function () { fetch(root.dataset.liveUrl, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(render, function () {}); };
    tick(); setInterval(function () { if (!document.hidden) tick(); }, 15000);
  }
  // champs montant signés (crédit / débit)
  document.querySelectorAll('[data-confirm]').forEach(function (f) { f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); }); });
})();
