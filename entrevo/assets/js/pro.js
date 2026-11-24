/* Entrevo — espace pro : recherche globale (Ctrl+K), notifications, toasts. */
(function () {
  'use strict';
  var me = document.getElementById('pro-js'), BASE = me.dataset.base, AREA = me.dataset.area;
  var $ = function (id) { return document.getElementById(id); };
  // ---------- Toasts ----------
  document.querySelectorAll('.toasts .flash').forEach(function (f, i) {
    var x = document.createElement('button'); x.className = 'toast-x'; x.type = 'button'; x.setAttribute('aria-label', 'Fermer'); x.textContent = '✕';
    x.onclick = function () { f.remove(); }; f.appendChild(x);
    if (!f.classList.contains('error') && !f.classList.contains('warning')) setTimeout(function () { f.classList.add('out'); setTimeout(function () { f.remove(); }, 400); }, 5500 + i * 400);
  });
  // ---------- Recherche globale ----------
  var box = $('cmdk'), input = $('cmdk-input'), res = $('cmdk-results'), timer = null, sel = 0, items = [];
  function open() { box.hidden = false; input.value = ''; input.focus(); }
  function close() { box.hidden = true; }
  $('cmdk-open').onclick = open;
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); box.hidden ? open() : close(); }
    if (!box.hidden && e.key === 'Escape') close();
    if (!box.hidden && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { e.preventDefault(); sel = Math.max(0, Math.min(items.length - 1, sel + (e.key === 'ArrowDown' ? 1 : -1))); mark(); }
    if (!box.hidden && e.key === 'Enter' && items[sel]) location.href = items[sel].dataset.href;
  });
  box.addEventListener('click', function (e) { if (e.target === box) close(); });
  function mark() { items.forEach(function (a, i) { a.classList.toggle('on', i === sel); }); if (items[sel]) items[sel].scrollIntoView({ block: 'nearest' }); }
  input.addEventListener('input', function () {
    clearTimeout(timer);
    var q = input.value.trim();
    if (q.length < 2) { res.innerHTML = '<p class="muted">Tape au moins 2 caractères.</p>'; items = []; return; }
    timer = setTimeout(function () {
      fetch(BASE + '/' + AREA + '/recherche?q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        res.textContent = ''; items = []; sel = 0;
        if (!j.groups.length) { var p = document.createElement('p'); p.className = 'muted'; p.textContent = 'Rien trouvé 🤷'; res.appendChild(p); return; }
        j.groups.forEach(function (g) {
          var h = document.createElement('div'); h.className = 'cmdk-group'; h.textContent = g.label; res.appendChild(h);
          g.items.forEach(function (it) {
            var a = document.createElement('a'); a.href = it.url; a.dataset.href = it.url; a.className = 'cmdk-item';
            var t = document.createElement('b'); t.textContent = it.title; a.appendChild(t);
            if (it.sub) { var s = document.createElement('small'); s.textContent = it.sub; a.appendChild(s); }
            res.appendChild(a); items.push(a);
          });
        });
        mark();
      });
    }, 180);
  });
  // ---------- Notifications ----------
  var notif = $('notif');
  function loadNotifs() {
    fetch(BASE + '/' + AREA + '/notifications', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      var c = notif.querySelector('.notif-count'), list = notif.querySelector('.notif-list');
      c.hidden = !j.items.length; c.textContent = j.items.length > 9 ? '9+' : j.items.length;
      list.textContent = '';
      if (!j.items.length) { var p = document.createElement('p'); p.className = 'muted'; p.textContent = 'Rien à signaler, tout roule 😎'; list.appendChild(p); return; }
      j.items.forEach(function (n) {
        var a = document.createElement('a'); a.href = n.url; a.className = 'notif-item ' + (n.level || '');
        var t = document.createElement('b'); t.textContent = n.title; a.appendChild(t);
        if (n.sub) { var s = document.createElement('small'); s.textContent = n.sub; a.appendChild(s); }
        list.appendChild(a);
      });
    }).catch(function () {});
  }
  loadNotifs(); setInterval(loadNotifs, 60000);
  // fermer les menus au clic ailleurs
  document.addEventListener('click', function (e) { document.querySelectorAll('details.menu[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); }); });
  // ---------- Comptes à rebours ----------
  function tick() {
    document.querySelectorAll('[data-countdown]').forEach(function (el) {
      var t = new Date(el.dataset.countdown.replace(' ', 'T')) - Date.now();
      if (t <= 0) { el.textContent = el.dataset.done || "C'est parti !"; return; }
      var d = Math.floor(t / 864e5), h = Math.floor(t % 864e5 / 36e5), m = Math.floor(t % 36e5 / 6e4);
      el.textContent = d > 0 ? 'J-' + d + ' · ' + h + ' h' : h + ' h ' + (m < 10 ? '0' : '') + m;
    });
  }
  tick(); setInterval(tick, 30000);
  // ---------- Flux d'activité en direct ----------
  var feed = document.querySelector('[data-feed]');
  if (feed) setInterval(function () {
    fetch(feed.dataset.feed, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (h) { if (h.trim()) feed.innerHTML = h; });
  }, 30000);

  // ---------- Tableaux : sélection groupée, lignes cliquables, filtres repliables ----------
  document.querySelectorAll('[data-bulk]').forEach(function (form) {
    var bar = form.querySelector('[data-bulk-bar]'), count = form.querySelector('[data-bulk-count]'), allIn = form.querySelector('[data-bulk-all]');
    var toggle = form.querySelector('[data-bulk-toggle]'), boxes = form.querySelectorAll('input[name="ids[]"]');
    function refresh() {
      var n = 0; boxes.forEach(function (b) { if (b.checked) n++; b.closest('tr').classList.toggle('selected', b.checked); });
      bar.hidden = n === 0 && allIn.value !== '1';
      if (allIn.value !== '1') count.textContent = n + (n > 1 ? ' sélectionnées' : ' sélectionnée');
      if (toggle) { toggle.checked = n > 0 && n === boxes.length; toggle.indeterminate = n > 0 && n < boxes.length; }
    }
    boxes.forEach(function (b) { b.addEventListener('change', function () { allIn.value = '0'; refresh(); }); });
    if (toggle) toggle.addEventListener('change', function () { allIn.value = '0'; boxes.forEach(function (b) { b.checked = toggle.checked; }); refresh(); });
    var all = form.querySelector('[data-bulk-select-all]');
    if (all) all.addEventListener('click', function () { allIn.value = '1'; boxes.forEach(function (b) { b.checked = true; }); refresh(); count.textContent = all.textContent.replace('Sélectionner les', 'Les') + ' sont sélectionnées'; all.hidden = true; });
    form.addEventListener('submit', function (e) {
      var btn = e.submitter;
      if (btn && (btn.value === 'tag' || btn.value === 'untag') && !form.querySelector('[name="new_tag"]').value.trim()) { e.preventDefault(); form.querySelector('[name="new_tag"]').focus(); }
    });
    refresh();
  });
  document.querySelectorAll('tr[data-href]').forEach(function (tr) {
    tr.addEventListener('click', function (e) { if (e.target.closest('a,button,input,label,select')) return; window.location.href = tr.dataset.href; });
  });
  document.querySelectorAll('[data-toggle-more]').forEach(function (b) {
    b.addEventListener('click', function () { var m = b.closest('form').querySelector('.filters-more'); m.hidden = !m.hidden; b.setAttribute('aria-expanded', String(!m.hidden)); });
  });
  document.querySelectorAll('[data-toggle]').forEach(function (b) {
    b.addEventListener('click', function () { var t = document.querySelector(b.dataset.toggle); if (t) { t.hidden = !t.hidden; if (!t.hidden) { var i = t.querySelector('input'); if (i) i.focus(); } } });
  });
  // remboursement partiel : cocher des billets pré-remplit le montant
  document.querySelectorAll('[data-partial-refund]').forEach(function (f) {
    var amount = f.querySelector('[name="amount"]'), max = parseInt(amount.dataset.max, 10) || 0;
    f.querySelectorAll('input[name="tickets[]"]').forEach(function (c) {
      c.addEventListener('change', function () {
        var s = 0; f.querySelectorAll('input[name="tickets[]"]:checked').forEach(function (x) { s += parseInt(x.dataset.price, 10) || 0; });
        amount.value = s ? (Math.min(s, max) / 100).toFixed(2).replace('.', ',') : '';
      });
    });
  });
  // annonce de la plateforme : masquable (souvenir local, par annonce)
  document.querySelectorAll('[data-announce]').forEach(function (a) {
    var k = 'entrevo:ann:' + a.dataset.announce;
    try { if (localStorage.getItem(k)) { a.remove(); return; } } catch (e) { /* rien */ }
    a.querySelector('.announce-x').onclick = function () { try { localStorage.setItem(k, '1'); } catch (e) { /* rien */ } a.remove(); };
  });
  // menu : rubriques repliables (mémorisées) et option pour masquer les modules en développement
  (function () {
    var nav = document.querySelector('[data-side-nav]'); if (!nav) return;
    var get = function (k) { try { return localStorage.getItem('entrevo:' + k); } catch (e) { return null; } };
    var set = function (k, v) { try { if (v === null) localStorage.removeItem('entrevo:' + k); else localStorage.setItem('entrevo:' + k, v); } catch (e) { /* rien */ } };
    nav.querySelectorAll('details[data-sec]').forEach(function (d) {
      if (!d.hasAttribute('data-has-on') && get('sec:' + d.dataset.sec) === 'closed') d.open = false;
      d.addEventListener('toggle', function () { set('sec:' + d.dataset.sec, d.open ? null : 'closed'); });
    });
    var cb = nav.querySelector('[data-hide-soon]');
    var apply = function () { nav.classList.toggle('hide-soon', cb.checked); };
    cb.checked = get('hide-soon') === '1'; apply();
    cb.addEventListener('change', function () { set('hide-soon', cb.checked ? '1' : null); apply(); });
  })();
  // tâches : glisser-déposer entre colonnes
  document.querySelectorAll('[data-kanban]').forEach(function (kb) {
    var drag = null;
    kb.querySelectorAll('[data-task]').forEach(function (t) {
      t.addEventListener('dragstart', function (e) { drag = t; t.classList.add('dragging'); e.dataTransfer.setData('text/plain', t.dataset.task); });
      t.addEventListener('dragend', function () { t.classList.remove('dragging'); kb.querySelectorAll('.kcol').forEach(function (c) { c.classList.remove('over'); }); });
    });
    kb.querySelectorAll('.kcol').forEach(function (col) {
      col.addEventListener('dragover', function (e) { e.preventDefault(); col.classList.add('over'); });
      col.addEventListener('dragleave', function () { col.classList.remove('over'); });
      col.addEventListener('drop', function (e) {
        e.preventDefault(); col.classList.remove('over'); if (!drag) return;
        col.querySelector('.kcol-list').appendChild(drag);
        var fd = new FormData(); fd.append('_csrf', kb.dataset.csrf); fd.append('action', 'move'); fd.append('task_id', drag.dataset.task); fd.append('status', col.dataset.status); fd.append('ajax', '1');
        fetch(kb.dataset.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function () {
          kb.querySelectorAll('.kcol').forEach(function (c) { c.querySelector('h3 small').textContent = c.querySelectorAll('[data-task]').length; });
        });
      });
    });
  });
})();
