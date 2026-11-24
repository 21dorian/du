/* Entrevo — tiroir d'achat : Billets → Toi → Récap → Paiement, sans quitter la page de l'événement. */
(function () {
  'use strict';
  var dw = document.getElementById('drawer'), buy = document.getElementById('buy');
  if (!dw || !buy) return;
  var body = document.body, csrf = dw.dataset.csrf, step = 1, summary = null, timer = null, lastFocus = null;
  var $ = function (sel, root) { return (root || dw).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || dw).querySelectorAll(sel)); };
  var buyer = document.getElementById('dw-buyer');
  var store = { get: function (k) { try { return JSON.parse(localStorage.getItem('entrevo:' + k)); } catch (e) { return null; } }, set: function (k, v) { try { localStorage.setItem('entrevo:' + k, JSON.stringify(v)); } catch (e) { /* rien */ } } };

  // ---------- mise en place : on déplace le formulaire de billets dans le tiroir ----------
  body.classList.add('has-drawer');
  var launch = document.querySelector('.buy-launch'); if (launch) launch.hidden = false;
  $('[data-slot=rates]').appendChild(buy);
  var opts = $('[data-slot=options]');
  var optionRows = [];
  var head = buy.querySelector('.extras-head'); if (head) optionRows.push(head);
  buy.querySelectorAll('input[name^="product["]').forEach(function (i) { optionRows.push(i.closest('.rate')); });
  var don = buy.querySelector('#donation'); if (don) optionRows.push(don.closest('.rate'));
  if (optionRows.length) {
    var h = document.createElement('h3'); h.className = 'dw-h'; h.textContent = '✨ Envie d’un petit plus ?'; opts.appendChild(h);
    optionRows.forEach(function (r) { if (!r) return; r.querySelectorAll('input').forEach(function (i) { i.setAttribute('form', 'buy'); }); if (r.classList.contains('extras-head')) r.remove(); else opts.appendChild(r); });
  }
  // prérenseignement : lien de connexion, sinon cet appareil
  var pre = null; try { pre = JSON.parse(dw.dataset.buyer || 'null'); } catch (e) { pre = null; }
  pre = pre || store.get('buyer');
  if (pre) ['first_name', 'last_name', 'email', 'phone'].forEach(function (k) { var i = buyer.querySelector('[name=' + k + ']'); if (i && pre[k]) i.value = pre[k]; });
  if (pre && pre.email) $('[data-quick]').hidden = true;

  // ---------- ouverture / fermeture ----------
  function open() {
    lastFocus = document.activeElement;
    dw.hidden = false; $('.dw-backdrop', document).hidden = false;
    requestAnimationFrame(function () { dw.classList.add('open'); body.classList.add('dw-lock'); });
    go(step === 4 ? 4 : 1);
    setTimeout(function () { var f = $('.dw-panel:not([hidden]) input:not([type=hidden]), .dw-panel:not([hidden]) button'); if (f) f.focus(); }, 250);
  }
  function close() {
    dw.classList.remove('open'); body.classList.remove('dw-lock');
    setTimeout(function () { dw.hidden = true; $('.dw-backdrop', document).hidden = true; }, 250);
    if (lastFocus) lastFocus.focus();
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-dw-open]')) { e.preventDefault(); open(); }
    else if (e.target.closest('[data-dw-close]')) { e.preventDefault(); close(); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !dw.hidden) close(); });
  if (location.hash === '#acheter') setTimeout(open, 300);
  window.addEventListener('hashchange', function () { if (location.hash === '#acheter' && dw.hidden) open(); });

  // ---------- étapes ----------
  function go(n) {
    step = n;
    $$('.dw-panel').forEach(function (p) { p.hidden = +p.dataset.panel !== n; });
    $$('.dw-steps li').forEach(function (li) { var s = +li.dataset.step; li.className = s < n ? 'done' : (s === n ? 'on' : ''); });
    $('[data-dw-back]').hidden = n === 1 || n === 4;
    var next = $('[data-dw-next]');
    next.hidden = n === 4;
    next.textContent = n === 3 ? (summary && summary.total_cents ? 'Payer ' + summary.total + ' →' : 'Valider ma réservation →') : 'Continuer →';
    errors([]);
    $('.dw-body').scrollTop = 0;
    footer();
  }
  function errors(list) {
    var box = $('[data-errors]'); box.textContent = '';
    box.hidden = !list.length;
    list.forEach(function (m) { var p = document.createElement('p'); p.textContent = m; box.appendChild(p); });
    if (list.length) box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }
  function count() { var n = 0; buy.querySelectorAll('input[name^="qty["]').forEach(function (i) { var q = i.closest('.qty'); n += (parseInt(i.value, 10) || 0) * (q ? parseInt(q.dataset.pack || '1', 10) : 1); }); return n; }
  function footer() {
    var n = summary && step > 1 ? summary.places : count();
    $('[data-count]').textContent = n + ' billet' + (n > 1 ? 's' : '');
    var t = document.getElementById('buy-total');
    $('[data-total]').textContent = summary && step > 1 ? summary.total : (t ? t.textContent : '');
  }
  buy.addEventListener('input', function () { summary = null; setTimeout(footer, 0); });
  buy.addEventListener('click', function () { summary = null; setTimeout(footer, 0); });

  function post(url, data) {
    return fetch(url, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-CSRF': csrf, 'Accept': 'application/json' } })
      .then(function (r) { if (!r.ok && r.status !== 422) throw new Error('HTTP ' + r.status); return r.json(); });
  }
  function cartData(action) { var fd = new FormData(buy); fd.delete('action'); if (action) fd.set('action', action); return fd; }
  function busy(on) { var b = $('[data-dw-next]'); b.disabled = on; b.classList.toggle('loading', on); }

  function refresh(action, extra) {
    var fd = cartData(action); if (extra) Object.keys(extra).forEach(function (k) { fd.set(k, extra[k]); });
    return post(dw.dataset.cart, fd).then(function (j) { if (j.summary) { summary = j.summary; recap(); footer(); } return j; });
  }
  function recap() {
    var box = $('[data-slot=recap]'); box.textContent = '';
    if (!summary) return;
    var t = document.createElement('div'); t.className = 'dw-ticket';
    var add = function (cls, a, b) { var r = document.createElement('div'); r.className = 'dw-line ' + (cls || ''); var x = document.createElement('span'); x.textContent = a; var y = document.createElement('span'); y.textContent = b; r.appendChild(x); r.appendChild(y); t.appendChild(r); };
    var hd = document.createElement('div'); hd.className = 'dw-ticket-head'; hd.textContent = '🎟️ Ton récap'; t.appendChild(hd);
    summary.lines.forEach(function (l) { add('', l.qty + ' × ' + l.name, l.total); });
    if (summary.group) add('good', '👥 Tarif groupe (−' + summary.group.pct + ' %)', '−' + summary.group.amount);
    if (summary.discount) add('good', 'Réduction' + (summary.promo ? ' (' + summary.promo + ')' : ''), '−' + summary.discount);
    if (summary.fees) add('muted', 'Frais de service', summary.fees);
    if (summary.donation) add('', '💛 Don', summary.donation);
    add('total', 'Total', summary.total);
    box.appendChild(t);
  }

  $('[data-dw-next]').addEventListener('click', function () {
    if (step === 1) {
      if (!count()) { errors(['Choisis au moins un billet 😉']); return; }
      busy(true);
      refresh('start').then(function (j) {
        busy(false);
        if (!j.ok) { errors(j.errors || ['Oups, réessaie.']); return; }
        var slot = $('[data-slot=fields]'); slot.innerHTML = j.fields_html; // HTML construit côté serveur (valeurs échappées)
        go(2);
      }).catch(function () { busy(false); errors(['Connexion perdue : vérifie ton réseau et réessaie.']); });
    } else if (step === 2) {
      var bad = Array.prototype.filter.call(buyer.querySelectorAll('input,select,textarea'), function (i) { return i.form === buyer && !i.checkValidity(); })[0];
      if (bad) { bad.reportValidity ? bad.reportValidity() : bad.focus(); return; }
      busy(true);
      refresh().then(function () { busy(false); go(3); }).catch(function () { busy(false); go(3); });
    } else if (step === 3) {
      var cgv = $('[data-cgv]'); if (cgv && !cgv.checked) { errors(['Coche les conditions de vente pour continuer 😉']); return; }
      var fd = cartData(); new FormData(buyer).forEach(function (v, k) { fd.append(k, v); });
      busy(true);
      post(dw.dataset.order, fd).then(function (j) {
        busy(false);
        if (!j.ok) { errors(j.errors || ['Oups, réessaie.']); return; }
        if ($('[data-remember]').checked) { var b = {}; ['first_name', 'last_name', 'email', 'phone'].forEach(function (k) { b[k] = buyer.querySelector('[name=' + k + ']').value; }); store.set('buyer', b); }
        if (j.free) { location.href = j.url; return; }
        $('[data-slot=pay]').innerHTML = j.pay_html; // fragment serveur (échappé)
        go(4); countdown();
      }).catch(function () { busy(false); errors(['Connexion perdue : vérifie ton réseau et réessaie.']); });
    }
  });
  $('[data-dw-back]').addEventListener('click', function () { if (step > 1 && step < 4) go(step - 1); });

  // options et code promo : récap mis à jour en direct
  var deb = null;
  opts.addEventListener('input', function () { clearTimeout(deb); deb = setTimeout(function () { refresh().then(function () { go(3); }); }, 400); });
  opts.addEventListener('click', function (e) {
    var b = e.target.closest('.qty button[data-d]');
    if (b) { var i = b.parentNode.querySelector('input'); i.value = Math.max(0, Math.min(parseInt(i.max, 10) || 50, (parseInt(i.value, 10) || 0) + parseInt(b.dataset.d, 10))); }
    if (e.target.closest('button')) { clearTimeout(deb); deb = setTimeout(function () { refresh().then(function () { go(3); }); }, 300); } });
  var codeBtn = $('[data-code-apply]');
  if (codeBtn) codeBtn.addEventListener('click', function () {
    var c = $('[data-code]').value.trim(); if (!c) return;
    refresh('code', { code: c }).then(function (j) {
      if (!j.ok) { errors(j.errors); return; }
      if (j.message === 'unlock') { location.hash = 'acheter'; location.reload(); return; }
      $('[data-code]').value = ''; go(3);
    });
  });

  // minuteur de réservation (réel : à partir de la création de la commande)
  function countdown() {
    clearInterval(timer);
    var el = $('[data-expires]'); if (!el) return;
    var end = new Date(el.dataset.expires).getTime(), left = $('[data-left]', el);
    var tick = function () {
      var s = Math.max(0, Math.round((end - Date.now()) / 1000));
      left.textContent = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
      el.classList.toggle('hurry', s < 120);
      if (!s) { clearInterval(timer); el.textContent = '⌛ Temps écoulé : tes places sont reparties. Recharge la page pour retenter ta chance.'; var f = $('.dw-pay-form'); if (f) f.hidden = true; }
    };
    tick(); timer = setInterval(tick, 1000);
  }

  // e-mail : on montre où partent les billets
  var em = buyer.querySelector('[name=email]');
  em.addEventListener('input', function () { var hEl = $('[data-email-hint]'); hEl.textContent = em.value ? 'Tes billets arriveront à ' + em.value + ' 📬' : 'Tes billets arriveront ici.'; });

  // connexion rapide par lien
  $('[data-quick-open]').addEventListener('click', function () { $('.dw-quick-form').hidden = false; $('[data-quick-mail]').focus(); });
  $('[data-quick-send]').addEventListener('click', function () {
    var m = $('[data-quick-mail]').value.trim(); if (!m) return;
    var fd = new FormData(); fd.set('email', m); fd.set('back', dw.dataset.back); fd.set('_csrf', csrf);
    post(dw.dataset.link, fd).then(function (j) { var q = $('[data-quick]'); q.textContent = j.message; });
  });
  footer();
})();
