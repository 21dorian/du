/* Entrevo Pro (4.3) — appli organisateur : direct, vente rapide, alertes et notifications, message express. */
(function () {
  'use strict';
  var B = document.body, base = B.dataset.base, csrf = B.dataset.csrf, ev = +B.dataset.ev;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var since = 0, unseen = 0, first = true, tab = 'live';
  var CH = { web: '', '': '', guichet: 'guichet', appli: 'appli', widget: 'widget', reservation: 'réservation', stock: 'stock', groupe: 'groupe', invitation: 'invitation', partner: 'partenaire', import: 'import', caisse: 'caisse' };
  var native = window.EntrevoNative || null; // pont fourni par l'APK Entrevo Pro (notifications natives)
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var store = { get: function (k) { try { return localStorage.getItem('oa:' + k); } catch (e) { return null; } }, set: function (k, v) { try { localStorage.setItem('oa:' + k, v); } catch (e) {} } };

  function api(path, data, json) {
    var o = { credentials: 'same-origin', headers: { 'X-CSRF': csrf, 'Accept': 'application/json' } };
    if (data) { o.method = 'POST'; if (json) { o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(data); } else { o.body = data; } }
    var url = base + path + (path.indexOf('?') < 0 ? '?' : '&') + 'e=' + ev;
    return fetch(url, o).then(function (r) { if (r.status === 401 || r.redirected && /connexion/.test(r.url)) { location.href = base.replace(/\/pro\/app$/, '') + '/connexion?retour=' + encodeURIComponent(base); throw new Error('auth'); } return r.json(); });
  }
  function toast(title, body) {
    var t = document.createElement('div'); t.className = 'oa-toast'; t.innerHTML = '<b>' + esc(title) + '</b>' + (body ? '<small>' + esc(body) + '</small>' : '');
    $('[data-toasts]').appendChild(t); setTimeout(function () { t.remove(); }, 5000);
  }
  function vibrate() { try { navigator.vibrate && navigator.vibrate(30); } catch (e) {} }

  // ---------- onglets ----------
  function go(t) {
    tab = t; $$('.oa-tab').forEach(function (s) { s.hidden = s.dataset.tab !== t; });
    $$('.oa-nav button').forEach(function (b) { b.classList.toggle('on', b.dataset.go === t); b.setAttribute('aria-current', b.dataset.go === t ? 'page' : 'false'); });
    if (t === 'alerts') { unseen = 0; badge(); }
    store.set('tab', t); window.scrollTo(0, 0);
  }
  $$('.oa-nav button').forEach(function (b) { b.addEventListener('click', function () { go(b.dataset.go); }); });
  function badge() { var b = $('[data-badge]'); if (!b) return; b.hidden = !unseen; b.textContent = unseen > 9 ? '9+' : unseen; }
  var sel = $('#oa-ev'); if (sel) sel.addEventListener('change', function () { location.href = base + '?e=' + sel.value; });
  if (!ev) return;
  var saved = store.get('tab'); if (saved && $('[data-tab="' + saved + '"]')) go(saved);

  // ---------- direct ----------
  function set(k, v) { $$('[data-k="' + k + '"]').forEach(function (el) { el.textContent = v; }); }
  function render(d) {
    var e = d.event; if (!e) return;
    set('sold', e.sold); set('cap', e.capacity ? 'sur ' + e.capacity : 'billets vendus'); set('date', e.date); set('name', e.name);
    set('today', d.today ? e.today : '—'); set('today_n', e.today_n + ' commande' + (e.today_n > 1 ? 's' : '')); set('net', e.net);
    set('inside', e.inside); set('came', e.came + ' entrés au total');
    $('[data-ring]').style.setProperty('--p', e.capacity ? Math.min(100, Math.round(e.sold / e.capacity * 100)) : (e.sold ? 100 : 0));
    var st = $('[data-k="status"]'); st.textContent = e.status === 'published' ? 'Ventes ouvertes' : 'Ventes clôturées'; st.className = 'oa-chip ' + (e.status === 'published' ? '' : 'closed');
    var tg = $('[data-toggle-sales]'); tg.textContent = e.status === 'published' ? 'Clôturer' : 'Rouvrir'; tg.dataset.to = e.status === 'published' ? 'closed' : 'published';
    var max = Math.max.apply(null, e.days.concat([1]));
    $('[data-spark]').innerHTML = e.days.map(function (n, i) { return '<i style="height:' + Math.max(3, Math.round(n / max * 100)) + '%"' + (i === e.days.length - 1 ? ' class="today"' : '') + ' title="' + n + ' billet(s)"></i>'; }).join('');
    $('[data-rates]').innerHTML = e.rates.map(function (r) {
      var p = r.quota ? Math.min(100, Math.round(r.sold / r.quota * 100)) : 0;
      return '<div><div class="t"><b>' + esc(r.name) + (r.active ? '' : ' <small class="muted">(masqué)</small>') + '</b><span>' + r.sold + (r.quota ? ' / ' + r.quota : '') + ' · ' + esc(r.price) + '</span></div>' +
        (r.quota ? '<div class="oa-bar" role="progressbar" aria-valuenow="' + p + '" aria-valuemin="0" aria-valuemax="100" aria-label="' + esc(r.name) + '"><i class="' + (p >= 100 ? 'full' : '') + '" style="width:' + p + '%"></i></div>' : '') + '</div>';
    }).join('') || '<p class="muted u-m0">Aucun tarif.</p>';
    $('[data-orders]').innerHTML = e.orders.map(function (o) {
      return '<li><a href="' + esc(o.url) + '"><span><b>' + esc(o.who || 'Sans nom') + '</b><small>' + o.n + ' billet' + (o.n > 1 ? 's' : '') + ' · ' + esc(o.at) + (CH[o.channel] !== undefined ? (CH[o.channel] ? ' · ' + CH[o.channel] : '') : (o.channel ? ' · ' + esc(o.channel) : '')) + '</small></span><b>' + esc(o.total) + '</b></a></li>';
    }).join('') || '<li class="muted">Pas encore de commande.</li>';
  }
  function alerts(list) {
    if (!list.length) return;
    var ul = $('[data-alerts]'); if (since === 0) ul.innerHTML = '';
    list.slice().reverse().forEach(function (a) {
      var li = document.createElement('li');
      li.innerHTML = '<i class="k ' + esc(a.kind) + '" aria-hidden="true"></i><a class="grow" href="' + esc(a.url) + '"><span><b>' + esc(a.title) + '</b><small>' + esc(a.body) + '</small></span><small>' + esc(a.at) + '</small></a>';
      ul.insertBefore(li, ul.firstChild);
      if (!first) { toast(a.title, a.body); vibrate(); if (native && native.notify) try { native.notify(a.title, a.body || ''); } catch (e) {} if (tab !== 'alerts') unseen++; }
    });
    since = Math.max.apply(null, list.map(function (a) { return a.id; }).concat([since]));
    badge();
  }
  var timer = null, live = $('[data-live]');
  function refresh() {
    api('/donnees?since=' + since).then(function (d) { live.classList.remove('off'); render(d); alerts(d.alerts || []); first = false; })
      .catch(function (e) { if (e.message !== 'auth') live.classList.add('off'); });
  }
  function loop() { clearTimeout(timer); refresh(); timer = setTimeout(loop, document.hidden ? 60000 : 15000); }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) loop(); });
  loop();

  $('[data-toggle-sales]').addEventListener('click', function () {
    var to = this.dataset.to;
    if (!confirm(to === 'closed' ? 'Clôturer les ventes ? Les billets vendus restent valables.' : 'Rouvrir les ventes ?')) return;
    var fd = new FormData(); fd.set('status', to);
    api('/statut', fd).then(function (r) { if (r.ok) { toast(to === 'closed' ? 'Ventes clôturées' : 'Ventes rouvertes'); refresh(); } else toast('Impossible', r.error); });
  });

  // ---------- vente rapide ----------
  var sf = $('[data-sell]');
  function total() {
    var n = 0, s = 0; $$('input[data-price]', sf).forEach(function (i) { var q = Math.max(0, +i.value || 0); n += q; s += q * +i.dataset.price; });
    var free = (sf.querySelector('input[name=pay_method]:checked') || {}).value === 'offert';
    var b = $('[data-sell-btn]'); b.disabled = !n;
    b.textContent = n ? (free ? 'Offrir ' + n + ' billet' + (n > 1 ? 's' : '') : 'Encaisser ' + (s / 100).toFixed(2).replace('.', ',') + ' € · ' + n + ' billet' + (n > 1 ? 's' : '')) : 'Choisis les billets';
  }
  sf.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-d]'); if (!btn) return;
    var i = btn.parentNode.querySelector('input'); i.value = Math.max(0, Math.min(50, (+i.value || 0) + +btn.dataset.d)); vibrate(); total();
  });
  sf.addEventListener('input', total); sf.addEventListener('change', total); total();
  sf.addEventListener('submit', function (e) {
    e.preventDefault(); var b = $('[data-sell-btn]'); b.disabled = true; var out = $('[data-sell-done]');
    api('/vente', new FormData(sf)).then(function (r) {
      out.hidden = false; out.className = 'oa-done' + (r.ok ? '' : ' err');
      out.innerHTML = r.ok ? '<b>Vente enregistrée · ' + esc(r.total) + '</b><br>' + r.tickets + ' billet(s) · ' + esc(r.ref) + (r.sent ? ' · envoyés par e-mail' : '') + '<div class="oa-row"><a class="btn btn-sm" href="' + esc(r.pdf) + '" target="_blank" rel="noopener">Billets PDF</a><a class="btn btn-sm btn-light" href="' + esc(r.detail) + '">Détail</a></div>' : esc(r.error || 'Erreur');
      if (r.ok) { $$('input[data-price]', sf).forEach(function (i) { i.value = 0; }); ['first_name', 'last_name', 'email'].forEach(function (n) { sf.elements[n].value = ''; }); vibrate(); refresh(); }
      total();
    }).catch(function () { out.hidden = false; out.className = 'oa-done err'; out.textContent = 'Connexion perdue : la vente n\'a pas été enregistrée.'; total(); });
  });

  // ---------- message express ----------
  var mf = $('[data-msg]');
  mf.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!confirm('Envoyer ce message par e-mail ' + (mf.elements.who.value === 'in' ? 'aux participants déjà entrés' : 'à tous les participants') + ' ?')) return;
    var b = mf.querySelector('button'); b.disabled = true; b.textContent = 'Envoi…';
    api('/message', new FormData(mf)).then(function (r) { toast(r.ok ? 'Message envoyé' : 'Pas envoyé', r.ok ? r.sent + ' personne(s)' + (r.failed ? ', ' + r.failed + ' échec(s)' : '') : r.error); if (r.ok) mf.reset(); })
      .finally(function () { b.disabled = false; b.textContent = 'Envoyer'; });
  });
  var sh = $('[data-share]'); if (sh) sh.addEventListener('click', function () {
    var u = sh.dataset.share; if (navigator.share) navigator.share({ url: u }).catch(function () {}); else if (navigator.clipboard) navigator.clipboard.writeText(u).then(function () { toast('Lien copié', u); });
  });
  var nh = $('[data-native-home]'); if (nh && native && native.home) { nh.hidden = false; nh.addEventListener('click', function () { native.home(); }); }
  var lo = $('[data-logout]'); if (lo) lo.addEventListener('click', function () { $('[data-logout-form]').submit(); });

  // ---------- notifications ----------
  var nState = $('[data-notif-state]'), on = $('[data-notif-on]'), off = $('[data-notif-off]'), test = $('[data-notif-test]'), prefsBox = $('[data-prefs]'), help = $('[data-notif-help]');
  var reg = null, sub = null;
  var b64 = function (s) { var p = '='.repeat((4 - s.length % 4) % 4), r = atob((s + p).replace(/-/g, '+').replace(/_/g, '/')), a = new Uint8Array(r.length); for (var i = 0; i < r.length; i++) a[i] = r.charCodeAt(i); return a; };
  function showPrefs(p) {
    prefsBox.hidden = false;
    $$('[data-pref]', prefsBox).forEach(function (el) { var k = el.dataset.pref; if (el.type === 'checkbox') el.checked = !!+p[k]; else el.value = p[k]; });
  }
  function state(s) {
    nState.textContent = s === 'on' ? 'Activées' : (s === 'denied' ? 'Bloquées' : (s === 'unsupported' ? 'Pas disponibles ici' : 'Désactivées'));
    on.hidden = s === 'on' || s === 'unsupported'; off.hidden = test.hidden = s !== 'on';
    if (s === 'denied') help.textContent = 'Les notifications sont bloquées pour ce site : autorise-les dans les réglages du navigateur.';
    if (s === 'unsupported') help.textContent = native ? 'Dans l\'appli Entrevo Pro, les alertes s\'affichent tant que l\'appli est ouverte ou en arrière-plan récent.' : 'Sur iPhone, ajoute d\'abord l\'appli à l\'écran d\'accueil (Partager › Sur l\'écran d\'accueil), puis reviens ici.';
  }
  var supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  if (!supported) state('unsupported');
  else navigator.serviceWorker.register(B.dataset.sw, { scope: base + '/' }).then(function (r) { reg = r; return r.pushManager.getSubscription(); }).then(function (s) {
    sub = s; if (Notification.permission === 'denied') return state('denied');
    if (!s) return state('off');
    return fetch(base + '/notifications?action=status', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf, 'Content-Type': 'application/json' }, body: JSON.stringify({ endpoint: s.endpoint }) })
      .then(function (r) { return r.json(); }).then(function (j) { if (j.subscribed) { state('on'); showPrefs(j.prefs); } else state('off'); });
  }).catch(function () { state('unsupported'); });
  function push(action, data) { return fetch(base + '/notifications?action=' + action, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf, 'Content-Type': 'application/json' }, body: JSON.stringify(data || {}) }).then(function (r) { return r.json(); }); }
  on.addEventListener('click', function () {
    Notification.requestPermission().then(function (p) {
      if (p !== 'granted') return state(p === 'denied' ? 'denied' : 'off');
      return push('key').then(function (k) { return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64(k.key) }); })
        .then(function (s) { sub = s; return push('subscribe', { subscription: s.toJSON() }); })
        .then(function (r) { if (r.ok) { state('on'); showPrefs(r.prefs); toast('Notifications activées'); } else toast('Impossible', r.error); });
    }).catch(function (e) { toast('Impossible d\'activer les notifications', e.message); });
  });
  off.addEventListener('click', function () { if (!sub) return; push('unsubscribe', { endpoint: sub.endpoint }).then(function () { return sub.unsubscribe(); }).then(function () { sub = null; prefsBox.hidden = true; state('off'); }); });
  test.addEventListener('click', function () { push('test', { endpoint: sub.endpoint }).then(function (r) { toast(r.ok ? 'Notification envoyée' : 'Échec', r.ok ? 'Elle arrive dans quelques secondes.' : r.error); }); });
  prefsBox.addEventListener('change', function () {
    var p = {}; $$('[data-pref]', prefsBox).forEach(function (el) { p[el.dataset.pref] = el.type === 'checkbox' ? (el.checked ? 1 : 0) : +el.value; });
    push('prefs', { endpoint: sub.endpoint, prefs: p }).then(function (r) { if (r.ok) toast('Préférences enregistrées'); });
  });
})();
