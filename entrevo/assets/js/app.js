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
      // tarif dégressif : remise selon le nombre de billets payants
      var tiers = []; try { tiers = JSON.parse(buy.dataset.tiers || '[]'); } catch (e) { tiers = []; }
      var nbPaid = 0; buy.querySelectorAll('.qty[data-unit]').forEach(function (q) { var n = parseInt(q.querySelector('input').value, 10) || 0; if (parseInt(q.dataset.base, 10) > 0 || q.dataset.freeprice) nbPaid += n * (parseInt(q.dataset.pack || '1', 10) || 1); });
      var tier = null; tiers.forEach(function (t) { if (nbPaid >= t.min) tier = t; });
      if (tier && !legacy) net -= Math.round(net * tier.pct / 100);
      var nx = null; tiers.forEach(function (t) { if (!nx && nbPaid < t.min) nx = t; });
      var tierHint = tier ? 'Tarif groupe : −' + tier.pct + ' % appliqué' + (nx ? ' · encore ' + (nx.min - nbPaid) + ' billet' + (nx.min - nbPaid > 1 ? 's' : '') + ' pour −' + nx.pct + ' %' : '') : (nx && nbPaid > 0 ? 'Plus que ' + (nx.min - nbPaid) + ' billet' + (nx.min - nbPaid > 1 ? 's' : '') + ' pour −' + nx.pct + ' % sur tout le groupe' : '');
      var hintEl = document.querySelector('p[data-tier-hint]'); if (hintEl) { hintEl.textContent = tierHint; hintEl.hidden = !tierHint; }
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
        var t = b.textContent; b.textContent = 'Copié'; setTimeout(function () { b.textContent = t; }, 1500);
      });
    });
  });

  // Copier le contenu d'un champ (dossier de preuves, etc.)
  document.querySelectorAll('[data-copy-from]').forEach(function (b) {
    b.addEventListener('click', function () {
      var src = document.querySelector(b.dataset.copyFrom); if (!src) return;
      navigator.clipboard.writeText(src.value || src.textContent).then(function () { var t = b.textContent; b.textContent = 'Copié'; setTimeout(function () { b.textContent = t; }, 1500); });
    });
  });

  // Partage natif (téléphone) ; sinon copie du lien
  document.querySelectorAll('[data-share]').forEach(function (b) {
    if (!navigator.share && !navigator.clipboard) { b.hidden = true; return; }
    b.addEventListener('click', function () {
      if (navigator.share) { navigator.share({ text: b.dataset.text, url: b.dataset.url }).catch(function () { /* annulé */ }); return; }
      navigator.clipboard.writeText(b.dataset.text).then(function () { var t = b.textContent; b.textContent = 'Message copié'; setTimeout(function () { b.textContent = t; }, 1500); });
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
    var parts = el.dataset.showIf.split('='), scope = el.closest('form') || document;
    var srcs = scope.querySelectorAll('[name="' + parts[0] + '"]');
    if (!srcs.length) srcs = document.querySelectorAll('[name="' + parts[0] + '"]');
    if (!srcs.length) return;
    var sync = function () {
      var v = '';
      srcs.forEach(function (s) { if (s.type === 'radio') { if (s.checked) v = s.value; } else if (s.type === 'checkbox') v = s.checked ? '1' : '0'; else v = s.value; });
      el.style.display = parts[1].split('|').indexOf(v) >= 0 ? '' : 'none';
    };
    srcs.forEach(function (s) { s.addEventListener('change', sync); }); sync();
  });

  // Rafraîchissement automatique (contrôle en direct)
  var auto = document.querySelector('[data-autorefresh]');
  if (auto) {
    // rechargement auto, sauf si l'utilisateur est en train d'écrire quelque chose
    var every = parseInt(auto.dataset.autorefresh, 10) * 1000, typing = function () { var a = document.activeElement; return a && /INPUT|TEXTAREA|SELECT/.test(a.tagName) || Array.prototype.some.call(document.querySelectorAll('textarea'), function (t) { return t.value.trim() !== ''; }); };
    var tick = function () { if (typing() || document.hidden) setTimeout(tick, 5000); else location.reload(); };
    setTimeout(tick, every);
  }

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
        if (s <= 0) { t.textContent = c.dataset.cdEnd || 'C’est parti !'; return; }
        var d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60), sec = s % 60;
        var EN = document.documentElement.lang === 'en';
        t.textContent = d > 0 ? (EN ? 'In ' : 'Dans ') + d + (EN ? ' d ' : ' j ') + h + ' h ' + String(m).padStart(2, '0') + ' min'
          : (EN ? 'In ' : 'Dans ') + h + ' h ' + String(m).padStart(2, '0') + ' min ' + String(sec).padStart(2, '0') + ' s';
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
      var cols = ['#0071e3', '#b388ff', '#2dd2be', '#ffd23f', '#fa3e4e'], parts = [];
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

/* 4.2 : questions conditionnelles des formulaires d'inscription */
(function(){
  function refresh(){ document.querySelectorAll('[data-cond-field]').forEach(function(el){
    var scope = el.closest('.holder, fieldset, .card, .pane, form') || document, src = scope.querySelector('[data-fid="' + el.dataset.condField + '"]'), v = '';
    if (src) { var i = src.querySelector('input,select,textarea'); if (i) v = i.type === 'checkbox' ? (i.checked ? 'oui' : '') : String(i.value || ''); }
    var want = String(el.dataset.condValue || '').trim().toLowerCase(), show = v.trim().toLowerCase() === want || (v === 'oui' && ['oui', '1', 'coché', 'coche'].indexOf(want) >= 0);
    el.hidden = !show; el.querySelectorAll('input,select,textarea').forEach(function(x){ if (x.required) x.dataset.req = '1'; if (x.dataset.req) x.required = show; }); }); }
  document.addEventListener('input', refresh); document.addEventListener('change', refresh);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', refresh); else refresh();
  if (window.MutationObserver) new MutationObserver(function(m){ for (var k = 0; k < m.length; k++) if (m[k].addedNodes.length) { refresh(); break; } }).observe(document.documentElement, { childList: true, subtree: true });
})();

/* 4.2.1 (accessibilité) : nom accessible de secours pour les champs sans étiquette reliée
   (étiquette du bloc .field, en-tête de colonne d'un tableau, ou texte d'exemple). */
(function () {
  function name(el) {
    if ((el.labels && el.labels.length) || el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.title) return;
    var f = el.closest('.field'), l = f && f.querySelector('label');
    var t = l && l.textContent.replace(/\s+/g, ' ').trim();
    if (!t) { var td = el.closest('td'), tb = el.closest('table');
      if (td && tb) { var hr = tb.querySelector('thead tr') || tb.rows[0]; var th = hr && hr.cells[td.cellIndex] !== td ? hr.cells[td.cellIndex] : null; var rh = td.parentElement.cells[0]; if (rh && rh !== td && !td.parentElement.querySelector('td b, td strong')) th = th ? { textContent: th.textContent + ' · ' + rh.textContent } : rh; var row = td.parentElement.querySelector('td b, td strong');
        t = (th ? th.textContent.trim() : '') + (row && row !== el ? ' · ' + row.textContent.trim() : ''); } }
    if (!t) t = el.placeholder || '';
    if (t) el.setAttribute('aria-label', t.slice(0, 120));
  }
  function run(root) { (root || document).querySelectorAll('input:not([type=hidden]):not([type=submit]):not([type=button]),select,textarea').forEach(name); }
  run();
  if (window.MutationObserver) new MutationObserver(function (ms) { ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) run(n); }); }); }).observe(document.body, { childList: true, subtree: true });
})();

/* 4.2.1 : comportements déclaratifs (remplacent les onclick en ligne) */
document.addEventListener('click', function (e) {
  var a = e.target.closest('a[data-confirm]');
  if (a && !window.confirm(a.dataset.confirm)) { e.preventDefault(); e.stopImmediatePropagation(); return; }
  if (e.target.closest('[data-print]')) { e.preventDefault(); window.print(); }
}, true);
document.addEventListener('focusin', function (e) { var t = e.target; if (t.matches && t.matches('[data-select]') && t.select) setTimeout(function () { t.select(); }, 0); });
document.addEventListener('click', function (e) { var t = e.target; if (t.matches && t.matches('[data-select]') && t.select) t.select(); });

/* 4.4 : liens qui ouvrent une carte repliée (#modifier, #annuler) ; montant libre de l'annulation */
document.addEventListener('click', function (e) {
  var a = e.target.closest('[data-open-details]'); if (!a) return;
  var d = document.getElementById(a.dataset.openDetails); if (d) { d.open = true; setTimeout(function () { d.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 30); }
});
(function () { if (/^#(modifier|annuler)$/.test(location.hash)) { var d = document.querySelector(location.hash); if (d) d.open = true; } })();
document.addEventListener('change', function (e) {
  var f = e.target.closest('[data-cancel-form]'); if (!f || e.target.name !== 'mode') return;
  var p = f.querySelector('[data-partial]'); if (p) p.hidden = e.target.value !== 'partial';
});
