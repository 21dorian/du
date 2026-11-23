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

  // ---------- Relief côté acheteur (1.2) ----------
  var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  // apparitions au scroll
  var rev = document.querySelectorAll('.reveal');
  if (rev.length) {
    if (calm || !('IntersectionObserver' in window)) rev.forEach(function (r) { r.classList.add('in'); });
    else {
      var io = new IntersectionObserver(function (es) { es.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } }); }, { rootMargin: '0px 0px -6% 0px' });
      rev.forEach(function (r, i) { r.style.transitionDelay = Math.min(i, 6) * 40 + 'ms'; io.observe(r); });
    }
  }
  // comptes à rebours
  var cds = document.querySelectorAll('[data-countdown]');
  if (cds.length) {
    var tick = function () {
      cds.forEach(function (c) {
        var s = Math.round((new Date(c.dataset.countdown).getTime() - Date.now()) / 1000), t = c.querySelector('[data-cd-text]');
        if (!t) return;
        if (s <= 0) { t.textContent = 'C’est parti !'; return; }
        var d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60), sec = s % 60;
        t.textContent = d > 0 ? 'Dans ' + d + ' j ' + h + ' h ' + String(m).padStart(2, '0') + ' min'
          : 'Dans ' + h + ' h ' + String(m).padStart(2, '0') + ' min ' + String(sec).padStart(2, '0') + ' s';
      });
    };
    tick(); setInterval(tick, 1000);
  }
  // barre d'achat collante (mobile) : visible tant que le bloc billets n'est pas à l'écran
  var sticky = document.querySelector('[data-buy-sticky]'), box = document.getElementById('billets'), top = document.querySelector('.event-top .btn-lg');
  if (sticky && box && 'IntersectionObserver' in window) {
    var seen = { box: false, top: true };
    var upd = function () { sticky.hidden = seen.box || seen.top; };
    new IntersectionObserver(function (es) { es.forEach(function (en) { seen[en.target === box ? 'box' : 'top'] = en.isIntersecting; }); upd(); }).observe(box);
    if (top) new IntersectionObserver(function (es) { seen.top = es[0].isIntersecting; upd(); }).observe(top); else seen.top = false;
  }
  // rail d'affiches
  document.querySelectorAll('[data-rail]').forEach(function (b) {
    b.addEventListener('click', function () {
      var tr = b.closest('.rail-wrap').querySelector('[data-rail-track]');
      tr.scrollBy({ left: parseInt(b.dataset.rail, 10) * tr.clientWidth * 0.8, behavior: calm ? 'auto' : 'smooth' });
    });
  });
  // confettis à la confirmation (une seule fois par commande)
  var cf = document.querySelector('[data-confetti]');
  if (cf && !calm) {
    var key = 'confetti-' + cf.dataset.confetti, done = false;
    try { done = sessionStorage.getItem(key) === '1'; sessionStorage.setItem(key, '1'); } catch (e) {}
    if (!done) {
      var cv = document.createElement('canvas'); cv.className = 'confetti'; cv.setAttribute('aria-hidden', 'true'); document.body.appendChild(cv);
      var ctx = cv.getContext('2d'), W = cv.width = window.innerWidth, H = cv.height = window.innerHeight;
      var cols = ['#0032fa', '#b388ff', '#2dd2be', '#ffd23f', '#fa3e4e'], parts = [];
      for (var i = 0; i < 160; i++) parts.push({ x: W / 2 + (Math.random() - .5) * 120, y: H * .35, vx: (Math.random() - .5) * 14, vy: -Math.random() * 13 - 4,
        r: Math.random() * Math.PI, vr: (Math.random() - .5) * .3, w: 6 + Math.random() * 6, h: 8 + Math.random() * 8, c: cols[i % cols.length] });
      var t0 = performance.now();
      var frame = function (now) {
        var el = now - t0; ctx.clearRect(0, 0, W, H);
        parts.forEach(function (p) { p.vy += .32; p.vx *= .99; p.x += p.vx; p.y += p.vy; p.r += p.vr;
          ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r); ctx.globalAlpha = Math.max(0, 1 - el / 3200); ctx.fillStyle = p.c; ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h); ctx.restore(); });
        if (el < 3200) requestAnimationFrame(frame); else cv.remove();
      };
      requestAnimationFrame(frame);
    }
  }
})();
