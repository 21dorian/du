// Entrevo — scripts communs
(function () {
  'use strict';
  var fmt = function (c) { return (c / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' }); };

  // Sélecteurs de quantité + total
  var buy = document.getElementById('buy');
  if (buy) {
    var update = function () {
      var total = 0;
      // estimation fidèle au calcul serveur : frais = % du net + fixe par billet payant (si frais à la charge de l'acheteur)
      var pct = parseFloat(buy.dataset.feePct || '0'), fixed = parseInt(buy.dataset.feeFixed || '0', 10), net = 0, paid = 0, legacy = !buy.querySelector('.qty[data-base]');
      buy.querySelectorAll('.qty').forEach(function (q) {
        var n = parseInt(q.querySelector('input').value, 10) || 0;
        if (legacy || q.dataset.base === undefined) { total += n * parseInt(q.dataset.price, 10); return; }
        var price = parseInt(q.dataset.base, 10);
        if (q.dataset.freeprice) { var f = buy.querySelector('[data-free="' + q.dataset.rate + '"]'); if (f) price = Math.round((parseFloat(String(f.value).replace(',', '.')) || 0) * 100); }
        net += n * price;
        if (q.dataset.unit && price > 0) paid += n;
      });
      if (!legacy) total += net + (net > 0 ? Math.round(net * pct / 100) + fixed * paid : 0);
      var don = document.getElementById('donation');
      if (don) total += Math.round((parseFloat(String(don.value).replace(',', '.')) || 0) * 100);
      var el = document.getElementById('buy-total');
      if (el) el.textContent = fmt(total);
    };
    buy.querySelectorAll('.qty').forEach(function (q) {
      var input = q.querySelector('input');
      q.querySelectorAll('button').forEach(function (b) {
        b.addEventListener('click', function () {
          var v = (parseInt(input.value, 10) || 0) + parseInt(b.dataset.d, 10);
          input.value = Math.max(0, Math.min(parseInt(input.max, 10) || 50, v));
          update();
        });
      });
      input.addEventListener('input', update);
    });
    buy.addEventListener('input', update);
    update();
  }

  // Tunnel d'achat : recopier l'acheteur dans le premier billet
  var copy = document.getElementById('copy-buyer');
  if (copy) copy.addEventListener('click', function () {
    var f = document.querySelector('.h-first'), l = document.querySelector('.h-last');
    if (f) f.value = document.getElementById('first_name').value;
    if (l) l.value = document.getElementById('last_name').value;
  });

  // Confirmation avant action
  document.addEventListener('submit', function (e) {
    var f = e.target, btn = e.submitter;
    var msg = (btn && btn.dataset.confirm) || f.dataset.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
  }, true);

  // Copier dans le presse-papiers
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      navigator.clipboard.writeText(b.dataset.copy).then(function () {
        var t = b.textContent; b.textContent = 'Copié ✓'; setTimeout(function () { b.textContent = t; }, 1500);
      });
    });
  });

  // Zone de dépôt de fichier
  document.querySelectorAll('.dropzone').forEach(function (z) {
    var input = z.querySelector('input[type=file]'), label = z.querySelector('.dz-name');
    if (z.tagName !== 'LABEL') z.addEventListener('click', function (e) { if (e.target !== input) input.click(); });
    ['dragenter', 'dragover'].forEach(function (ev) { z.addEventListener(ev, function (e) { e.preventDefault(); z.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { z.addEventListener(ev, function (e) { e.preventDefault(); z.classList.remove('over'); }); });
    z.addEventListener('drop', function (e) { if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; input.dispatchEvent(new Event('change')); } });
    input.addEventListener('change', function () { if (label && input.files[0]) label.textContent = input.files[0].name + ' (' + Math.round(input.files[0].size / 1024) + ' Ko)'; });
  });

  // Afficher / masquer selon une case ou une liste
  document.querySelectorAll('[data-show-if]').forEach(function (el) {
    var parts = el.dataset.showIf.split('='), src = document.querySelector('[name="' + parts[0] + '"]');
    if (!src) return;
    var sync = function () {
      var v = src.type === 'checkbox' ? (src.checked ? '1' : '0') : src.value;
      el.style.display = parts[1].split('|').indexOf(v) >= 0 ? '' : 'none';
    };
    src.addEventListener('change', sync); sync();
  });

  // Rafraîchissement automatique (contrôle en direct)
  var auto = document.querySelector('[data-autorefresh]');
  if (auto) setTimeout(function () { location.reload(); }, parseInt(auto.dataset.autorefresh, 10) * 1000);
})();
