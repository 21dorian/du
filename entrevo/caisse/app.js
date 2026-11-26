/* Entrevo Caisse Pro — caisse événementielle : cashless (QR du billet ou bracelet NFC), carte (TPE autonome ou Nepting), espèces, crédits offerts.
   Serveurs avec code, offerts et remises validés par un responsable, consignes, happy hours, sessions de caisse, ticket par QR code et e-mail.
   Fonctionne en web app (/caisse/) et dans l'application native (Capacitor : NFC et terminal Nepting). */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var LS = {
    get: function (k, d) { try { var v = localStorage.getItem('pos.' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
    set: function (k, v) { try { localStorage.setItem('pos.' + k, JSON.stringify(v)); } catch (e) { /* stockage indisponible */ } },
    del: function (k) { try { localStorage.removeItem('pos.' + k); } catch (e) { /* rien */ } }
  };
  var CAP = window.Capacitor;
  var NATIVE = !!(CAP && CAP.isNativePlatform && CAP.isNativePlatform());
  var webBase = function () { return location.origin + location.pathname.replace(/\/caisse(\/.*)?$/, ''); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var money = function (c) { return (c / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' }); };
  var uid = function () { return 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10); };
  var buzz = function (p) { try { navigator.vibrate && navigator.vibrate(p); } catch (e) { /* rien */ } };
  var APP_V = '3.2.0';
  var METHOD = { cashless: 'Cashless', card: 'Carte', cash: 'Espèces', allowance: 'Crédit', offered: 'Offert' };

  var S = {
    server: NATIVE ? LS.get('server', '') : webBase(),
    token: LS.get('token', ''), config: LS.get('config', null), shift: LS.get('shift', null),
    queue: LS.get('queue', []), history: LS.get('history', []), online: navigator.onLine, cat: null,
    cart: { items: [], returns: {}, discount: 0, offerAll: false, reason: '', approver: null }
  };
  var save = function () { LS.set('queue', S.queue); LS.set('history', S.history.slice(0, 60)); LS.set('shift', S.shift); LS.set('config', S.config); };
  var C = function () { return S.config; };
  var staff = function () { return S.shift && S.shift.staff; };
  var can = function (perm) { var s = staff(); return !!(s && s.perms.indexOf(perm) >= 0); };
  var staffPayload = function () { var s = staff(); return s ? { staff_id: s.id, staff_token: s.token } : { staff: (S.shift && S.shift.name) || '' }; };

  // ---------- API ----------
  function api(action, data, timeout) {
    var ctl = window.AbortController ? new AbortController() : null;
    var t = setTimeout(function () { ctl && ctl.abort(); }, timeout || 15000);
    var body = Object.assign({ action: action }, staffPayload(), S.shift && S.shift.bar_id ? { bar_id: S.shift.bar_id } : {}, data || {});
    return fetch(S.server + '/api/caisse', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Pos-Token': S.token || '' }, body: JSON.stringify(body), signal: ctl ? ctl.signal : undefined, credentials: 'omit' })
      .then(function (r) {
        clearTimeout(t);
        return r.json().catch(function () { return { ok: false, error: 'Réponse illisible du serveur.' }; }).then(function (j) {
          if (r.status === 401) unpair(true);
          if (!j.ok) { var e = new Error(j.error || 'Erreur'); e.server = true; e.status = r.status; if (j.till_closed) tillClosed(); throw e; }
          setOnline(true); return j;
        });
      }, function () { clearTimeout(t); setOnline(false); var e = new Error('Pas de réseau.'); e.network = true; throw e; });
  }

  /** La caisse du point de vente a été clôturée depuis un autre appareil : on revient à l'ouverture. */
  function tillClosed() {
    if (S.config) S.config.session = null;
    setTimeout(function () { closeSheet(); toast('La caisse de ce point de vente a été clôturée.', 'ko'); api('config').then(function (j) { S.config = j.config; save(); enterStation(); }, function () { openSessionScreen(); }); }, 50);
  }

  // ---------- écrans ----------
  function show(id) { $$('.screen').forEach(function (s) { s.hidden = s.id !== id; }); }
  function toast(msg, kind) { var t = $('#toast'); t.textContent = msg; t.className = 'toast ' + (kind || ''); t.hidden = false; clearTimeout(toast.t); toast.t = setTimeout(function () { t.hidden = true; }, 2800); }
  function setOnline(v) {
    S.online = v;
    var n = $('#net'); if (!n) return;
    n.classList.toggle('off', !v);
    var q = S.queue.length;
    $('span', n).textContent = v ? (q ? 'Envoi de ' + q + '…' : 'En ligne') : 'Hors ligne' + (q ? ' · ' + q + ' en attente' : '');
    if (!$('#s-pos').hidden) renderCart();
  }

  // ---------- appairage ----------
  function parsePair(text) {
    text = String(text || '').trim();
    var m = text.match(/^(https?:\/\/.+?)\/caisse\/?(?:index\.html)?#pair=(\d{6})/);
    if (m) return { server: m[1], code: m[2] };
    var c = text.replace(/\D/g, '');
    return c.length === 6 ? { server: null, code: c } : null;
  }
  function pair(code, server) {
    var err = $('#pair-err'); err.textContent = '';
    if (NATIVE) {
      server = (server || $('#pair-server').value || '').trim().replace(/\/+$/, '').replace(/\/caisse$/, '');
      if (!/^https?:\/\//.test(server)) { err.textContent = 'Indique l\'adresse de la billetterie (https://…).'; return; }
      S.server = server; LS.set('server', server);
    }
    $('#pair-go').disabled = true; S.token = '';
    api('pair', { code: code, platform: NATIVE ? (CAP.getPlatform ? CAP.getPlatform() : 'native') : 'web' }).then(function (j) {
      S.token = j.token; LS.set('token', j.token); S.config = j.config; S.shift = null; S.history = []; save();
      if (location.hash) history.replaceState(null, '', location.pathname);
      buzz(40); if (['prep', 'display'].indexOf(C().device.mode) >= 0) openMain(); else startShift();
    }, function (e) { err.textContent = e.message; }).then(function () { $('#pair-go').disabled = false; });
  }
  function unpair(expired) {
    S.token = ''; S.config = null; S.shift = null; LS.del('token'); LS.del('config'); LS.del('shift');
    closeSheet(); closeDrawer();
    $('#pair-err').textContent = expired ? 'Cet appareil a été déconnecté par l\'organisateur. Demande un nouveau code.' : '';
    show('s-pair');
  }

  // ---------- 2.9 : démarrage. 1) qui es-tu (code) 2) ton poste (point de vente + rôle) 3) caisse du point de vente ----------
  var pin = '';
  var ROLES = [
    { id: 'bar', t: 'Vendre', s: 'Encaisser les consommations', till: true, ic: '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3 10h18M7 15h4"/>' },
    { id: 'topup', t: 'Recharger', s: 'Créditer billets et bracelets', till: true, ic: '<rect x="3" y="6" width="18" height="13" rx="3"/><path d="M12 9.5v6M9 12.5h6"/>' },
    { id: 'prep', t: 'Préparer', s: 'Écran cuisine ou comptoir', ic: '<path d="M5 11h14v3a6 6 0 0 1-6 6h-2a6 6 0 0 1-6-6z"/><path d="M9 4v4M12 3v5M15 4v4"/>' },
    { id: 'display', t: 'Écran d\'appel', s: 'Numéros prêts, sur une TV', ic: '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>' }
  ];
  function keypad(el, keys, onKey) {
    el.innerHTML = keys.map(function (k) { return '<button type="button" data-k="' + k + '">' + k + '</button>'; }).join('');
    $$('button', el).forEach(function (b) { b.onclick = function () { buzz(6); onKey(b.dataset.k); }; });
  }
  /** Étape 1 : la personne qui prend l'appareil (code serveur, ou prénom si l'équipe n'est pas configurée). */
  function startShift(lockOnly) {
    var c = C(); if (!c) return show('s-pair');
    var need = c.settings.require_staff;
    stopPoll(); stopOpenPoll();
    $('#shift-event').textContent = c.event.name;
    $('#shift-title').textContent = lockOnly ? 'Appareil verrouillé' : 'Bonjour';
    $('#shift-sub').textContent = lockOnly && S.shift && S.shift.bar_id ? 'Poste : ' + ((bar() || {}).name || '') + ' · ' + roleName(S.shift.mode) + '. Tape ton code pour continuer.' : (need ? 'Tape ton code personnel.' : 'Indique ton prénom pour le récapitulatif de caisse.');
    $('#shift-pin').hidden = !need; $('#shift-name-row').hidden = !!need; $('#shift-go').hidden = !!need;
    $('#shift-staff').value = (S.shift && S.shift.name) || '';
    S.lockOnly = !!lockOnly;
    pin = ''; renderPin(); $('#pin-err').textContent = '';
    keypad($('#pin-keys'), ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', '⌫'], function (k) {
      if (k === '⌫') pin = pin.slice(0, -1); else if (k && pin.length < 6) pin += k;
      renderPin();
      if (pin.length >= 4) login();
    });
    show('s-shift');
  }
  function renderPin() { $('#pin-dots').innerHTML = [0, 1, 2, 3].concat(pin.length > 4 ? [4, 5].slice(0, pin.length - 4) : []).map(function (i) { return '<i class="' + (i < pin.length ? 'on' : '') + '"></i>'; }).join(''); }
  var loginT = null;
  function login() {
    clearTimeout(loginT);
    loginT = setTimeout(function () {
      var tried = pin;
      fetch(S.server + '/api/caisse', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Pos-Token': S.token }, body: JSON.stringify({ action: 'staff_login', pin: tried }), credentials: 'omit' })
        .then(function (r) { return r.json(); }).then(function (j) {
          if (!j.ok) { if (tried.length >= 6 || tried === pin) { $('#pin-err').textContent = j.error; buzz([60, 40, 60]); pin = ''; renderPin(); } return; }
          setOnline(true); signedIn(j.staff, j.staff.name);
        }, function () { $('#pin-err').textContent = 'Pas de réseau : la connexion par code a besoin du réseau.'; });
    }, pin.length < 6 ? 450 : 0);
  }
  function signedIn(st, name) {
    var keep = S.lockOnly && S.shift && S.shift.bar_id && S.shift.mode;
    S.shift = Object.assign({}, S.shift || {}, { staff: st || null, name: name || '' });
    if (!keep) S.shift.since = new Date().toISOString();
    save(); buzz(30);
    if (keep) return saveStation(S.shift.bar_id, S.shift.mode, true);
    openStation();
  }
  function roleName(id) { var r = ROLES.filter(function (x) { return x.id === id; })[0]; return r ? r.t : 'Vendre'; }

  /** Étape 2 : le poste. Le point de vente puis ce que fait l'appareil ; rien n'est imposé depuis le site. */
  var ST = { bar: null, mode: null };
  function openStation() {
    var c = C(), open = c.open_bars || [];
    stopPoll(); stopOpenPoll();
    ST.bar = (S.shift && S.shift.bar_id) || c.device.bar_id || (c.bars.length === 1 ? c.bars[0].id : null);
    ST.mode = (S.shift && S.shift.mode) || null;
    $('#st-who').textContent = (staff() ? staff().name : (S.shift && S.shift.name) || 'Ton poste') + ' · ' + c.event.name;
    var renderSt = function () {
      var all = ST.bar === 0;
      $('#st-bars').innerHTML = c.bars.map(function (b) {
        var o = open.indexOf(b.id) >= 0;
        return '<button class="place' + (ST.bar === b.id ? ' on' : '') + '" data-stbar="' + b.id + '"><b>' + esc(b.name) + '</b>' + (c.settings.sessions ? '<span class="' + (o ? 'ok' : '') + '"><i></i>' + (o ? 'Caisse ouverte' : 'Caisse fermée') + '</span>' : '') + '</button>';
      }).join('') + (c.bars.length > 1 ? '<button class="place place-all' + (all ? ' on' : '') + '" data-stbar="0"><b>Tous</b><span>Préparation et écran d\'appel</span></button>' : '')
        || '<p class="muted">Aucun point de vente : l\'organisateur doit en créer un.</p>';
      var roles = ROLES.filter(function (r) { return r.id !== 'topup' || c.event.cashless; });
      if (ST.mode && !roles.some(function (r) { return r.id === ST.mode; })) ST.mode = null;
      $('#st-roles').innerHTML = roles.map(function (r) {
        var off = all && r.till;
        return '<button class="role' + (ST.mode === r.id ? ' on' : '') + '" data-strole="' + r.id + '"' + (off ? ' disabled' : '') + '><svg viewBox="0 0 24 24">' + r.ic + '</svg><b>' + r.t + '</b><span>' + r.s + '</span></button>';
      }).join('');
      if (all && ST.mode && ROLES.filter(function (r) { return r.id === ST.mode; })[0].till) ST.mode = null;
      $('#st-go').disabled = ST.bar === null || !ST.mode;
      $('#st-err').textContent = '';
    };
    S.renderStation = renderSt;
    renderSt(); show('s-station');
  }
  /** Enregistre le poste sur le serveur (l'organisateur le voit dans « Appareils »), puis ouvre l'écran. */
  function saveStation(barId, mode, quiet) {
    var go = function () { S.shift.bar_id = barId || null; S.shift.mode = mode; save(); enterStation(); };
    api('station', { bar_id: barId || 0, mode: mode }).then(function (j) { S.config = j.config; go(); }, function (e) {
      if (e.network) return go(); // hors ligne : le poste sera envoyé avec les ventes
      if (quiet) return openStation();
      $('#st-err').textContent = e.message;
    });
  }
  /** Étape 3 : vendre et recharger demandent la caisse du point de vente ouverte (une fois, par un responsable). */
  function enterStation() {
    var mode = (S.shift && S.shift.mode) || 'bar', role = ROLES.filter(function (r) { return r.id === mode; })[0];
    if (role && role.till && C().settings.sessions && !C().session) return openSessionScreen();
    openMain();
  }
  /** Écran principal selon le poste choisi (vente, recharge, préparation, écran d'appel). */
  function openMain() {
    var mode = (S.shift && S.shift.mode) || C().device.mode || 'bar';
    stopPoll(); stopOpenPoll();
    if (mode === 'topup') return openTopup();
    if (mode === 'prep') return openPrep();
    if (mode === 'display') return openDisplay();
    openPos();
  }

  // ---------- ouverture de la caisse du point de vente ----------
  var openPoll = null;
  function stopOpenPoll() { if (openPoll) { clearInterval(openPoll); openPoll = null; } }
  function openSessionScreen() {
    var f = '', b = bar() || {};
    stopPoll(); stopOpenPoll();
    $('#open-bar').textContent = b.name || '';
    var boss = !C().settings.has_staff || can('close');
    var form = function (on) { $('#open-form').hidden = !on; $('#open-wait').hidden = on; };
    form(boss);
    $('#open-hint').textContent = boss ? 'La caisse de ' + (b.name || 'ce point de vente') + ' est fermée. Ouvre-la une fois : tous les appareils de ce point de vente vendront dedans jusqu\'à la clôture.' : 'Un responsable ouvre la caisse une fois pour tout le point de vente. Dès qu\'elle est ouverte, sur cet appareil ou un autre, tu peux encaisser.';
    var render = function () { $('#open-float').textContent = money(f ? parseInt(f, 10) : 0); };
    keypad($('#open-keys'), ['1', '2', '3', '4', '5', '6', '7', '8', '9', '00', '0', '⌫'], function (k) { f = k === '⌫' ? f.slice(0, -1) : (f + k).replace(/^0+/, '').slice(0, 7); render(); });
    var go = function (amount, count) {
      $('#open-err').textContent = '';
      withPerm('close', 'Ouvrir la caisse', function (approver) {
        api('session_open', { float: amount, count: count || undefined, approver_pin: approver }).then(function (j) { S.config = j.config; save(); toast(j.joined ? 'La caisse était déjà ouverte : tu la rejoins.' : 'Caisse ouverte.', 'ok'); openMain(); },
          function (e) { $('#open-err').textContent = e.message; });
      });
    };
    $('#open-go').onclick = function () { go(f ? parseInt(f, 10) : 0); };
    $('#open-skip').onclick = function () { go(0); };
    $('#open-count').hidden = !C().settings.count_denoms;
    $('#open-count').onclick = function () { countSheet('Fond de caisse', 'Compte les billets et pièces du tiroir.', function (a, c) { closeSheet(); f = String(a); render(); go(a, c); }, { ok: 'Ouvrir la caisse avec ce fond', note: false }); };
    $('#open-mgr').onclick = function () { form(true); };
    $('#open-back').onclick = function () { stopOpenPoll(); openStation(); };
    var check = function (manual) {
      api('config', {}, 8000).then(function (j) { S.config = j.config; save(); if (j.config.session) { toast('La caisse est ouverte.', 'ok'); openMain(); } else if (manual) toast('Toujours fermée.'); }, function (e) { if (manual) toast(e.message, 'ko'); });
    };
    $('#open-check').onclick = function () { check(true); };
    render(); show('s-open');
    openPoll = setInterval(function () { if (!$('#s-open').hidden) check(false); else stopOpenPoll(); }, 8000);
  }

  /** Action protégée : faite directement si le serveur a le droit, sinon code d'un responsable. */
  function withPerm(perm, label, cb) {
    if (!C().settings.has_staff || (!C().settings.require_staff && perm !== 'offer' && perm !== 'discount')) return cb(null);
    if (can(perm)) return cb(null);
    if (S.cart.approver && perm !== 'close' && perm !== 'cash') return cb(S.cart.approver);
    var p = '';
    sheet('<h2>Validation d\'un responsable</h2><p class="muted" style="margin:0">' + esc(label) + ' : un responsable tape son code.</p><div class="pin-dots" id="ap-dots"><i></i><i></i><i></i><i></i></div><div class="keys pin-keys" id="ap-keys"></div><p class="err" id="ap-err"></p><button class="btn" data-close>Annuler</button>');
    var dots = function () { $('#ap-dots').innerHTML = [0, 1, 2, 3].concat(p.length > 4 ? [4, 5].slice(0, p.length - 4) : []).map(function (i) { return '<i class="' + (i < p.length ? 'on' : '') + '"></i>'; }).join(''); };
    var t = null;
    keypad($('#ap-keys'), ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', '⌫'], function (k) {
      if (k === '⌫') p = p.slice(0, -1); else if (k && p.length < 6) p += k;
      dots(); clearTimeout(t);
      if (p.length >= 4) t = setTimeout(function () {
        var tried = p;
        if (!S.online) { closeSheet(); return cb(tried); }
        api('check_pin', { pin: tried, perm: perm }).then(function (j) { closeSheet(); toast('Validé par ' + j.name + '.', 'ok'); cb(tried); },
          function (e) { if (tried.length >= 6 || tried === p) { $('#ap-err').textContent = e.message; p = ''; dots(); buzz([60, 40, 60]); } });
      }, p.length < 6 ? 450 : 0);
    });
  }

  // ---------- catalogue et prix (le serveur recalcule tout, ceci sert à l'affichage et au hors ligne) ----------
  function bar() { var id = S.shift && S.shift.mode ? S.shift.bar_id : C().device.bar_id; return C().bars.filter(function (b) { return b.id === id; })[0] || null; }
  function product(id) { return C().products.filter(function (p) { return p.id === id; })[0]; }
  function products() { var b = bar(); return C().products.filter(function (p) { return !p.bars.length || (b && p.bars.indexOf(b.id) >= 0); }); }
  function stockLeft(p) { if (!p.stock) return null; var b = bar(); var q = p.stock[b.id]; return q == null ? 0 : q; }
  function basePrice(p, v) { if (v != null && p.variants[v]) return p.variants[v].price; var bp = p.bar_prices[bar().id]; return bp != null ? bp : p.price; }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function promoActive(pr, d) {
    var day = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()), t = pad(d.getHours()) + ':' + pad(d.getMinutes()), dow = d.getDay() || 7;
    if (pr.starts_on && day < pr.starts_on) return false; if (pr.ends_on && day > pr.ends_on) return false;
    if (pr.days.length && pr.days.indexOf(dow) < 0) return false;
    if (pr.bars.length && pr.bars.indexOf(bar().id) < 0) return false;
    if (pr.from && pr.to) { var inn = pr.from <= pr.to ? (t >= pr.from && t < pr.to) : (t >= pr.from || t < pr.to); if (!inn) return false; }
    return true;
  }
  function promoApplies(pr, p) { return (!pr.products.length && !pr.categories.length) || pr.products.indexOf(p.id) >= 0 || (p.category_id && pr.categories.indexOf(p.category_id) >= 0); }
  function bestPrice(p, unit, qty) {
    var best = { total: unit * qty, promo: null }, now = new Date();
    C().promos.forEach(function (pr) {
      if (!promoActive(pr, now) || !promoApplies(pr, p)) return;
      var v = pr.value, tot = pr.kind === 'percent' ? Math.round(unit * (100 - Math.min(100, v)) / 100) * qty : pr.kind === 'amount' ? Math.max(0, unit - v) * qty : pr.kind === 'price' ? Math.min(unit, v) * qty : pr.kind === 'second' ? unit * (qty - Math.floor(qty / 2)) : unit * qty;
      if (tot < best.total) best = { total: tot, promo: pr.name };
    });
    return best;
  }
  function optPrice(p, opts) { return (opts || []).reduce(function (t, o) { var a = o.split(':'), g = p.options[+a[0]]; return t + (g && g.items[+a[1]] ? g.items[+a[1]].price : 0); }, 0); }
  function itemName(p, it) {
    var n = p.name + (it.variant != null && p.variants[it.variant] ? ' · ' + p.variants[it.variant].name : '');
    var o = (it.opts || []).map(function (x) { var a = x.split(':'), g = p.options[+a[0]]; return g && g.items[+a[1]] ? g.items[+a[1]].name : ''; }).filter(Boolean);
    if (o.length) n += ' · ' + o.join(', ');
    if (it.picks && it.picks.length) n += ' (' + it.picks.map(function (id) { var sp = product(id); return sp ? sp.name : ''; }).join(', ') + ')';
    return n;
  }
  function activePromos() { var now = new Date(); return C().promos.filter(function (pr) { return promoActive(pr, now); }); }
  function quoteLocal() {
    var lines = [], sub = 0, offered = 0, dep = {}, cart = S.cart;
    cart.items.forEach(function (it) {
      if (it.free) { var fo = it.offered || cart.offerAll, ft = it.free * it.qty; lines.push({ key: it.key, kind: 'product', name: it.name || 'Montant libre', qty: it.qty, unit: it.free, full: ft, total: fo ? 0 : ft, offered: fo }); if (fo) offered += ft; else sub += ft; return; }
      var p = product(it.id); if (!p) return;
      var unit = basePrice(p, it.variant) + optPrice(p, it.opts), off = it.offered || cart.offerAll, bp = bestPrice(p, unit, it.qty);
      lines.push({ key: it.key, kind: 'product', name: itemName(p, it), qty: it.qty, unit: unit, full: unit * it.qty, total: off ? 0 : bp.total, promo: bp.promo, offered: off });
      if (off) offered += bp.total; else sub += bp.total;
      var dIds = p.deposit_type_id ? [p.deposit_type_id] : [];
      (it.picks || []).forEach(function (pid) { var sp = product(pid); if (sp && sp.deposit_type_id) dIds.push(sp.deposit_type_id); });
      dIds.forEach(function (d) { dep[d] = (dep[d] || 0) + it.qty; });
    });
    var discount = cart.discount ? Math.round(sub * cart.discount / 100) : 0;
    var deps = C().deposits;
    Object.keys(dep).forEach(function (id) { var d = deps.filter(function (x) { return x.id === +id; })[0]; if (d) lines.push({ kind: 'deposit', name: d.name + (d.refundable ? ' (consigne)' : ''), qty: dep[id], unit: d.amount, full: d.amount * dep[id], total: d.amount * dep[id] }); });
    Object.keys(cart.returns).forEach(function (id) { var d = deps.filter(function (x) { return x.id === +id; })[0], q = cart.returns[id]; if (d && q) lines.push({ kind: 'return', depId: d.id, name: 'Retour ' + d.name, qty: q, unit: -d.amount, full: -d.amount * q, total: -d.amount * q }); });
    var total = lines.reduce(function (t, l) { return t + l.total; }, 0) - discount;
    return { lines: lines, subtotal: sub, discount: discount, offered: offered, total: total, products: cart.items.reduce(function (t, i) { return t + i.qty; }, 0) };
  }
  function payload() {
    var c = S.cart, ret = [];
    Object.keys(c.returns).forEach(function (id) { if (c.returns[id]) ret.push({ deposit_id: +id, qty: c.returns[id] }); });
    return { items: c.items.map(function (i) { if (i.free) return { id: 0, free: 1, qty: i.qty, offered: i.offered ? 1 : 0, unit: i.free, name: i.name || 'Montant libre' }; var p = product(i.id); return { id: i.id, variant: i.variant, qty: i.qty, offered: i.offered ? 1 : 0, opts: i.opts || [], picks: i.picks || [], unit: p ? basePrice(p, i.variant) + optPrice(p, i.opts) : 0, name: p ? p.name : '' }; }),
      returns: ret, discount_pct: c.discount || 0, offer_all: c.offerAll ? 1 : 0, offer_reason: c.reason || '', approver_pin: c.approver || undefined, bar_id: S.shift.bar_id, tab_id: c.tab ? c.tab.id : undefined };
  }
  function emptyCart() { S.cart = { items: [], returns: {}, discount: 0, offerAll: false, reason: '', approver: null }; }

  // ---------- caisse ----------
  function openPos() {
    var c = C(), b = bar();
    if (!b) return openStation();
    if (c.settings.require_staff && !staff()) return startShift(true);
    $('#pos-bar').textContent = b.name;
    $('#pos-event').textContent = c.event.name;
    var st = staff(); $('#staff-pill').hidden = !st && !(S.shift && S.shift.name);
    $('#staff-pill').textContent = st ? st.name : (S.shift && S.shift.name) || '';
    if (c.org && c.org.accent) document.documentElement.style.setProperty('--accent', c.org.accent);
    document.body.classList.toggle('big-keys', !!c.settings.big);
    var cats = c.categories.filter(function (k) { return products().some(function (p) { return p.category_id === k.id; }); });
    if (products().some(function (p) { return !p.category_id; })) cats.push({ id: 0, name: 'Divers', color: null });
    if (!cats.some(function (k) { return k.id === S.cat; })) S.cat = cats.length ? cats[0].id : null;
    $('#cats').innerHTML = '<label class="cat-search"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/></svg><input id="pos-q" type="search" placeholder="Rechercher" autocomplete="off" value="' + esc(S.q || '') + '"></label>' + (cats.length > 1 ? cats.map(function (k) { return '<button data-cat="' + k.id + '" class="' + (k.id === S.cat ? 'on' : '') + '" style="--c:' + esc(k.color || '#8e8e93') + '"><i></i>' + esc(k.name) + '</button>'; }).join('') : '');
    $('#cats').hidden = false;
    $('#pos-q').oninput = function () { S.q = this.value; $$('#cats [data-cat]').forEach(function (b) { b.classList.toggle('on', !S.q && +b.dataset.cat === S.cat); }); renderGrid(); };
    refreshTabs();
    $$('[data-pay]').forEach(function (btn) { if (btn.dataset.pay !== 'offered') btn.hidden = c.methods.indexOf(btn.dataset.pay) < 0; });

    $('#t-return').hidden = !c.deposits.some(function (d) { return d.refundable; });
    renderGrid(); renderCart(); setOnline(S.online); show('s-pos'); keepAwake();
  }
  function catColor(id) { var k = C().categories.filter(function (x) { return x.id === id; })[0]; return (k && k.color) || '#3a3a44'; }
  function renderGrid() {
    var photos = C().settings.photos;
    var qs = (S.q || '').trim().toLowerCase();
    var list = products().filter(function (p) { return qs ? p.name.toLowerCase().indexOf(qs) >= 0 : (S.cat == null || (p.category_id || 0) === S.cat); });
    var ap = activePromos();
    $('#promo-pill').hidden = !ap.length; $('#promo-pill').textContent = ap.map(function (p) { return p.name; }).join(' · ');
    $('#grid').innerHTML = list.map(function (p) {
      var q = S.cart.items.filter(function (l) { return l.id === p.id; }).reduce(function (t, l) { return t + l.qty; }, 0);
      var left = stockLeft(p), out = left !== null && left <= 0, unit = basePrice(p, p.variants.length ? 0 : null), bp = bestPrice(p, unit, 1);
      var priceTxt = p.variants.length ? p.variants.map(function (v) { return money(v.price); }).join(' / ') : (bp.promo ? '<s>' + money(unit) + '</s> ' + money(bp.total) : money(unit));
      if (p.kind === 'menu') priceTxt = 'Formule · ' + priceTxt; else if (p.options.length) priceTxt += ' <i class="opt-dot">+ options</i>';
      return '<button class="prod' + (photos && p.photo ? ' has-photo' : '') + (out ? ' out' : '') + '" data-prod="' + p.id + '" style="--c:' + esc(p.color || catColor(p.category_id)) + '">'
        + (photos && p.photo ? '<img src="' + esc(p.photo) + '" alt="" loading="lazy">' : '') + (bp.promo && !p.variants.length ? '<u>' + esc(bp.promo) + '</u>' : '') + '<span class="prod-txt"><b>' + esc(p.name) + '</b><span>' + priceTxt + '</span>'
        + (p.deposit_type_id ? '<small>+ consigne</small>' : '') + (left !== null ? '<small class="' + (left <= 5 ? 'low' : '') + '">' + (out ? 'stock épuisé' : left + ' en stock') + '</small>' : '') + '</span>'
        + (q ? '<em>' + q + '</em>' : '') + '</button>';
    }).join('') + (C().settings.free_amount && !qs ? '<button class="prod prod-free" data-free="1"><span class="prod-txt"><b>Montant libre</b><span>Hors carte</span></span></button>' : '') || (qs ? '<p class="muted">Aucun produit pour « ' + esc(S.q) + ' ».</p>' : '') || '<p class="muted">Aucun produit pour ce point de vente. L\'organisateur les ajoute dans Bars &amp; caisses › Catalogue.</p>';
  }
  function renderCart() {
    var el = $('#cart-lines'); if (!el || !C()) return;
    var q = quoteLocal(), cart = S.cart;
    el.innerHTML = q.lines.length ? q.lines.map(function (l) {
      if (l.kind === 'product') return '<div class="line' + (l.offered ? ' offered' : '') + '" data-line="' + l.key + '"><div class="line-name"><b>' + esc(l.name) + '</b><span>' + money(l.unit) + (l.promo ? ' · ' + esc(l.promo) : '') + (l.offered ? ' · offert' : '') + '</span></div><div class="qty"><button data-q="-1" data-key="' + l.key + '" aria-label="Moins">−</button><output>' + l.qty + '</output><button data-q="1" data-key="' + l.key + '" aria-label="Plus">+</button></div><div class="line-sum">' + (l.offered ? '<s>' + money(l.full) + '</s>' : money(l.total)) + '</div></div>';
      return '<div class="line sub-line' + (l.kind === 'return' ? ' ret' : '') + '"><div class="line-name"><b>' + esc(l.name) + '</b><span>' + l.qty + ' × ' + money(Math.abs(l.unit)) + '</span></div>' + (l.kind === 'return' ? '<div class="qty"><button data-rq="-1" data-dep="' + l.depId + '">−</button><output>' + l.qty + '</output><button data-rq="1" data-dep="' + l.depId + '">+</button></div>' : '') + '<div class="line-sum">' + money(l.total) + '</div></div>';
    }).join('') : '<p class="cart-empty">Touche un produit pour l\'ajouter.</p>';
    var subs = [];
    if (q.discount) subs.push('Remise ' + cart.discount + ' % : −' + money(q.discount));
    if (q.offered) subs.push('Offert : ' + money(q.offered));
    $('#cart-sub').innerHTML = subs.map(esc).join(' · ');
    $('#cart-total').textContent = money(Math.abs(q.total));
    $('#cart-total-label').textContent = q.total < 0 ? 'À rendre' : 'Total';
    var empty = !q.lines.length, refund = q.total < 0, free = !empty && q.total === 0 && (q.offered > 0);
    $$('[data-pay]').forEach(function (b) {
      var m = b.dataset.pay;
      if (m === 'offered') { b.hidden = !free; return; }
      if (C().methods.indexOf(m) < 0) { b.hidden = true; return; }
      b.hidden = free || (refund && (m === 'card' || m === 'allowance'));
      b.disabled = empty || ((m === 'cashless' || m === 'allowance') && !S.online);
    });
    $('#pay-more').hidden = free || refund; $('#pay-more').disabled = empty;
    renderTabsBtn();
    $('#t-discount').classList.toggle('on', !!cart.discount);
  }
  function addProduct(p, variant, opts, picks) {
    opts = (opts || []).slice().sort(); picks = picks || [];
    var key = p.id + ':' + (variant == null ? '' : variant) + ':' + opts.join(',') + ':' + picks.join(',');
    var l = S.cart.items.filter(function (x) { return x.key === key && !x.offered; })[0];
    if (!l) { l = { key: key, id: p.id, variant: variant == null ? null : variant, opts: opts, picks: picks, qty: 0, offered: false }; S.cart.items.push(l); }
    l.qty++; buzz(8); renderGrid(); renderCart();
  }
  /** Composition : variante, options, étapes d'une formule. */
  function tapProduct(id) {
    var p = product(id); if (!p) return;
    var isMenu = p.kind === 'menu' && p.menu.length;
    if (!p.variants.length && !p.options.length && !isMenu) return addProduct(p, null);
    var st = { variant: p.variants.length ? null : undefined, opts: [], picks: [] };
    var render = function () {
      var html = '<h2>' + esc(p.name) + '</h2>' + (p.desc ? '<p class="muted" style="margin:0">' + esc(p.desc) + '</p>' : '');
      if (p.variants.length) html += '<div class="cfg-h">Taille</div><div class="variants">' + p.variants.map(function (v, i) { return '<button class="variant' + (st.variant === i ? ' on' : '') + '" data-cv="' + i + '"><b>' + esc(v.name) + '</b><span>' + money(v.price) + '</span></button>'; }).join('') + '</div>';
      if (isMenu) p.menu.forEach(function (step, k) {
        html += '<div class="cfg-h">' + esc(step.name) + '</div><div class="variants">' + step.products.map(function (pid) {
          var sp = product(pid); if (!sp) return ''; var left = stockLeft(sp);
          return '<button class="variant' + (st.picks[k] === pid ? ' on' : '') + (left !== null && left <= 0 ? ' dim' : '') + '" data-cs="' + k + ':' + pid + '"><b>' + esc(sp.name) + '</b>' + (left !== null && left <= 0 ? '<span>stock épuisé</span>' : '') + '</button>';
        }).join('') + '</div>';
      });
      p.options.forEach(function (g, gi) {
        html += '<div class="cfg-h">' + esc(g.name) + (g.required ? ' <span class="req">obligatoire</span>' : '') + (g.multi ? ' <span class="muted">plusieurs choix</span>' : '') + '</div><div class="variants">' + g.items.map(function (it, ii) {
          var k = gi + ':' + ii;
          return '<button class="variant' + (st.opts.indexOf(k) >= 0 ? ' on' : '') + '" data-co="' + k + '"><b>' + esc(it.name) + '</b><span>' + (it.price ? '+ ' + money(it.price) : 'inclus') + '</span></button>';
        }).join('') + '</div>';
      });
      var ok = st.variant !== null && (!isMenu || p.menu.every(function (s2, k) { return st.picks[k]; })) && p.options.every(function (g, gi) { return !g.required || st.opts.some(function (o) { return +o.split(':')[0] === gi; }); });
      var unit = (st.variant === null ? p.price : basePrice(p, st.variant === undefined ? null : st.variant)) + optPrice(p, st.opts);
      html += '<div class="row cfg-foot"><button class="btn" data-close>Annuler</button><button class="btn btn-primary" id="cfg-add"' + (ok ? '' : ' disabled') + '>Ajouter · ' + money(bestPrice(p, unit, 1).total) + '</button></div>';
      $('#sheet-in').innerHTML = html;
      $$('[data-cv]').forEach(function (b) { b.onclick = function () { st.variant = +b.dataset.cv; render(); }; });
      $$('[data-cs]').forEach(function (b) { b.onclick = function () { var a = b.dataset.cs.split(':'); st.picks[+a[0]] = +a[1]; render(); }; });
      $$('[data-co]').forEach(function (b) {
        b.onclick = function () {
          var k = b.dataset.co, gi = +k.split(':')[0], g = p.options[gi], i = st.opts.indexOf(k);
          if (i >= 0) st.opts.splice(i, 1); else { if (!g.multi) st.opts = st.opts.filter(function (o) { return +o.split(':')[0] !== gi; }); st.opts.push(k); }
          render();
        };
      });
      $('#cfg-add').onclick = function () { closeSheet(); addProduct(p, st.variant === undefined ? null : st.variant, st.opts, isMenu ? st.picks.slice(0, p.menu.length) : []); };
    };
    sheet(''); render();
  }
  function lineMenu(key) {
    var it = S.cart.items.filter(function (x) { return x.key === key; })[0]; if (!it) return;
    var p = it.free ? { name: it.name || 'Montant libre' } : product(it.id);
    sheet('<h2>' + esc(p ? p.name : '') + '</h2><div class="row"><button class="btn" id="lm-remove">Retirer du panier</button><button class="btn' + (it.offered ? '' : ' btn-gift') + '" id="lm-offer">' + (it.offered ? 'Ne plus offrir' : 'Offrir cet article') + '</button></div><button class="btn btn-ghost" data-close>Fermer</button>');
    $('#lm-remove').onclick = function () { S.cart.items = S.cart.items.filter(function (x) { return x !== it; }); closeSheet(); renderGrid(); renderCart(); };
    $('#lm-offer').onclick = function () {
      if (it.offered) { it.offered = false; closeSheet(); renderCart(); return; }
      offerFlow('Offrir ' + (p ? p.name : 'un article'), function () { it.offered = true; renderCart(); });
    };
  }
  function offerFlow(label, done) {
    var reasons = C().settings.reasons;
    var ask = function () {
      sheet('<h2>Motif</h2><div class="variants">' + reasons.map(function (r) { return '<button class="variant" data-r="' + esc(r) + '"><b>' + esc(r) + '</b></button>'; }).join('') + '</div><input class="input-big" id="of-other" placeholder="Autre motif"><button class="btn btn-primary" id="of-ok">Valider</button><button class="btn" data-close>Annuler</button>');
      var set = function (r) { S.cart.reason = r; closeSheet(); done(); };
      $$('[data-r]').forEach(function (b) { b.onclick = function () { set(b.dataset.r); }; });
      $('#of-ok').onclick = function () { set($('#of-other').value.trim() || 'Offert'); };
    };
    withPerm('offer', label, function (approver) { if (approver) S.cart.approver = approver; ask(); });
  }
  function discountFlow() {
    if (S.cart.discount) { S.cart.discount = 0; renderCart(); return; }
    withPerm('discount', 'Faire une remise', function (approver) {
      if (approver) S.cart.approver = approver;
      sheet('<h2>Remise sur le panier</h2><div class="variants">' + [10, 20, 30, 50].map(function (v) { return '<button class="variant" data-d="' + v + '"><b>−' + v + ' %</b></button>'; }).join('') + '</div><div class="row"><input class="input-big" id="ds-v" inputmode="numeric" placeholder="Autre %"><button class="btn btn-primary" id="ds-ok" style="flex:0 0 auto">OK</button></div><button class="btn" data-close>Annuler</button>');
      var set = function (v) { v = Math.max(0, Math.min(100, v | 0)); S.cart.discount = v; closeSheet(); renderCart(); };
      $$('[data-d]').forEach(function (b) { b.onclick = function () { set(+b.dataset.d); }; });
      $('#ds-ok').onclick = function () { set(parseInt($('#ds-v').value, 10)); };
    });
  }
  function returnFlow() {
    var deps = C().deposits.filter(function (d) { return d.refundable; }); if (!deps.length) return;
    var add = function (d) { S.cart.returns[d.id] = (S.cart.returns[d.id] || 0) + 1; renderCart(); };
    withPerm('deposit', 'Rendre une consigne', function (approver) {
      if (approver) S.cart.approver = approver;
      if (deps.length === 1) return add(deps[0]);
      sheet('<h2>Retour de consigne</h2><div class="variants">' + deps.map(function (d) { return '<button class="variant" data-dep="' + d.id + '"><b>' + esc(d.name) + '</b><span>' + money(d.amount) + '</span></button>'; }).join('') + '</div><button class="btn" data-close>Fermer</button>');
      $$('[data-dep]', $('#sheet')).forEach(function (b) { b.onclick = function () { add(deps.filter(function (d) { return d.id === +b.dataset.dep; })[0]); toast('Retour ajouté.', 'ok'); }; });
    });
  }

  // ---------- fenêtre ----------
  var onClose = null;
  function sheet(html, cleanup) { closeSheet(); $('#sheet-in').innerHTML = html; $('#sheet').hidden = false; $('#sheet-bg').hidden = false; onClose = cleanup || null; }
  function closeSheet() { if (onClose) { try { onClose(); } catch (e) { /* rien */ } onClose = null; } $('#sheet').hidden = true; $('#sheet-bg').hidden = true; }
  function resultView(ok, title, sub, extra) {
    return '<div class="result ' + (ok ? 'ok' : 'ko') + '"><div class="ring">' + (ok ? '<svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>' : '<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>') + '</div><h2>' + esc(title) + '</h2>' + (sub ? '<p>' + sub + '</p>' : '') + '</div>' + (extra || '');
  }
  // ---------- caméra (QR) ----------
  function startCam(video, onCode) {
    var stream = null, raf = 0, stopped = false, canvas = document.createElement('canvas'), ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { video.parentNode.innerHTML = '<p class="muted" style="padding:20px">Caméra indisponible : saisis le code à la main.</p>'; return function () {}; }
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false }).then(function (s) {
      if (stopped) { s.getTracks().forEach(function (t) { t.stop(); }); return; }
      stream = s; video.srcObject = s; video.setAttribute('playsinline', ''); video.play();
      var tick = function () {
        if (stopped) return;
        if (video.readyState === video.HAVE_ENOUGH_DATA && window.jsQR) {
          var w = canvas.width = Math.min(640, video.videoWidth), h = canvas.height = Math.round(video.videoHeight * w / video.videoWidth);
          ctx.drawImage(video, 0, 0, w, h);
          var r = window.jsQR(ctx.getImageData(0, 0, w, h).data, w, h, { inversionAttempts: 'dontInvert' });
          if (r && r.data) { onCode(r.data); return; }
        }
        raf = requestAnimationFrame(tick);
      };
      tick();
    }, function () { video.parentNode.innerHTML = '<p class="muted" style="padding:20px">Accès à la caméra refusé : saisis le code à la main.</p>'; });
    return function () { stopped = true; cancelAnimationFrame(raf); if (stream) stream.getTracks().forEach(function (t) { t.stop(); }); };
  }

  /** Module natif (application Android) : absent dans la web app. */
  var plugins = {};
  function nativePlugin(name) {
    if (!NATIVE) return null;
    if (plugins[name] !== undefined) return plugins[name];
    var p = (CAP.Plugins && CAP.Plugins[name]) || null;
    if (!p && CAP.isPluginAvailable && CAP.isPluginAvailable(name) && CAP.registerPlugin) p = CAP.registerPlugin(name);
    return (plugins[name] = p);
  }

  // ---------- NFC (bracelets) ----------
  var NFC = {
    plugin: function () { return nativePlugin('EntrevoNfc'); },
    available: function () { return !!this.plugin() || 'NDEFReader' in window; },
    start: function (cb) {
      var self = this, last = '', lastAt = 0;
      var fire = function (u) { u = String(u || '').replace(/[^0-9a-f]/gi, '').toUpperCase(); if (!u || (u === last && Date.now() - lastAt < 2500)) return; last = u; lastAt = Date.now(); buzz(30); cb(u); };
      var p = this.plugin();
      if (p) return Promise.resolve(p.addListener('tag', function (e) { fire(e.uid); })).then(function (h) { self.h = h; return p.startReading(); });
      if ('NDEFReader' in window) {
        this.ac = new AbortController(); var r = new window.NDEFReader();
        return r.scan({ signal: this.ac.signal }).then(function () { r.onreading = function (e) { fire(e.serialNumber); }; });
      }
      return Promise.reject(new Error('NFC indisponible sur cet appareil.'));
    },
    stop: function () {
      var p = this.plugin();
      if (p) { try { p.stopReading(); } catch (e) { /* rien */ } if (this.h && this.h.remove) this.h.remove(); this.h = null; }
      if (this.ac) { this.ac.abort(); this.ac = null; }
    }
  };

  // ---------- TPE Nepting (protocole Caisse-AP, trame TLV sur TCP) ----------
  var TPE = {
    plugin: function () { return nativePlugin('EntrevoTpe'); },
    tlv: function (tag, v) { v = String(v); return tag + ('00' + v.length).slice(-3) + v; },
    frame: function (t, amount, ref) {
      return this.tlv('CZ', t.cz) + this.tlv('CJ', t.cj) + this.tlv('CA', t.ca) + this.tlv('CB', amount) + this.tlv('CD', '0') + this.tlv('CE', '978') + this.tlv('CF', String(ref).slice(0, 32)) + this.tlv('CK', '000');
    },
    parse: function (s) {
      var out = {}, i = 0; s = String(s || '').replace(/[\r\n]+$/, '');
      while (i + 5 <= s.length) {
        var tag = s.substr(i, 2), len = parseInt(s.substr(i + 2, 3), 10);
        if (!/^[A-Z]{2}$/.test(tag) || isNaN(len)) break;
        out[tag] = s.substr(i + 5, len); i += 5 + len;
      }
      return out;
    },
    REASONS: { '00': 'Refus (motif inconnu)', '02': 'Appel phonie demandé', '03': 'Forçage', '04': 'Carte refusée', '05': 'Opération interdite', '06': 'Paiement abandonné', '07': 'Opération non terminée', '08': 'Temps de saisie dépassé', '09': 'Message mal formé', '10': 'Mauvaise sélection', '11': 'Abandon de la banque', '12': 'Opération inconnue', '13': 'Devise non acceptée' },
    pay: function (t, amount, ref) {
      var p = this.plugin(), self = this;
      if (!p) return Promise.reject(new Error('Le terminal Nepting n\'est utilisable que depuis l\'application Android Entrevo Caisse.'));
      if (!t.host) return Promise.reject(new Error('Adresse du terminal non renseignée par l\'organisateur.'));
      var req = this.frame(t, amount, ref) + (t.crlf ? '\r\n' : '');
      return p.exchange({ host: t.host, port: t.port, data: req, timeout: t.timeout * 1000 }).then(function (r) {
        var f = self.parse(r.data);
        if (f.AE === '10') return { via: 'nepting', paid: parseInt(f.CB || amount, 10), auth: f.AC || '', app: f.CC || '', ref: f.CF || ref };
        var code = (f.AF || '').slice(0, 2);
        throw new Error(self.REASONS[code] || (f.AE ? 'Paiement non effectué' : 'Réponse du terminal illisible'));
      });
    }
  };


  // ---------- ventes ----------
  function newSale(method, extra) {
    return Object.assign({ client_id: uid(), method: method, at: new Date().toISOString(), amount: quoteLocal().total }, payload(), extra || {});
  }
  function remember(sale, srv, pending) {
    S.history.unshift({ client_id: sale.client_id, id: srv ? srv.id : null, ticket: srv ? srv.ticket : null, method: sale.method, amount: srv ? srv.amount : sale.amount, at: sale.at, pending: !!pending, undone: false, recv: Date.now(), receipt: srv ? srv.receipt : null });
    S.history = S.history.slice(0, 60); save();
  }
  function afterSale() { var hadTab = !!S.cart.tab; emptyCart(); renderGrid(); renderCart(); if (hadTab || TABS.length) refreshTabs(); }
  /** Carte et espèces : enregistrées tout de suite si possible, sinon gardées sur l'appareil. */
  function record(sale) {
    if (!S.online) { S.queue.push(sale); remember(sale, null, true); save(); setOnline(false); afterSale(); return Promise.resolve({ queued: true }); }
    return api('sale', sale).then(function (j) { remember(sale, j.sale, false); afterSale(); return j; }, function (e) {
      if (!e.network) throw e; // refus du serveur (droit manquant, panier…) : le serveur corrige puis réessaie
      S.queue.push(sale); remember(sale, null, true); save(); setOnline(false); afterSale();
      return { queued: true };
    });
  }
  function sync() {
    if (!S.token || !S.queue.length || sync.busy) return;
    sync.busy = true;
    api('sync', { sales: S.queue.slice(0, 50) }, 30000).then(function (j) {
      j.results.forEach(function (r) {
        if (!r.ok) return;
        S.queue = S.queue.filter(function (s) { return s.client_id !== r.client_id; });
        S.history.forEach(function (h) { if (h.client_id === r.client_id) { h.pending = false; h.id = r.id; } });
      });
      save(); setOnline(true);
    }, function () { /* on réessaiera */ }).then(function () { sync.busy = false; });
  }
  /** Montant exact : demandé au serveur si possible (happy hours, prix du bar), sinon calcul local. */
  function quote() {
    if (!S.online) return Promise.resolve(quoteLocal());
    return api('quote', payload(), 6000).then(function (j) { return j.quote; }, function (e) { if (e.server) throw e; return quoteLocal(); });
  }
  function done(j, extra) {
    var s = j.sale, mode = C().settings.receipt;
    if (s.tip) extra = (extra ? extra + '<br>' : '') + 'Pourboire <b>' + money(s.tip) + '</b> · merci !';
    buzz([30, 40, 30]);
    var html = (s.order ? '<div class="order-no"><span>Commande</span><b>' + s.order + '</b><small>Donne ce numéro au client : il sera appelé quand c\'est prêt.</small></div>' : '')
      + resultView(true, (s.amount < 0 ? 'Rendu ' + money(-s.amount) : s.amount === 0 ? 'Validé' : 'Payé ' + money(s.amount)), (extra || '') + (s.ticket ? '<br><span class="muted">Ticket n° ' + s.ticket + '</span>' : ''));
    if (mode !== 'off' && s.receipt) {
      html += '<div class="receipt-box"><img class="receipt-qr" src="' + esc(s.receipt) + '/qr" alt="QR code du ticket"><div><b>Ticket de caisse</b><p class="muted">Le client scanne le code pour le voir ou le télécharger.</p>'
        + (mode === 'ask' ? '<div class="row"><input class="input-big" id="rc-mail" type="email" inputmode="email" placeholder="E-mail du client"><button class="btn btn-primary" id="rc-send" style="flex:0 0 auto">Envoyer</button></div>' : '') + '</div></div>';
    }
    html += '<button class="btn btn-primary btn-xl" data-close>Vente suivante</button>';
    $('#sheet-in').innerHTML = html;
    var b = $('#rc-send');
    if (b) b.onclick = function () { var m = $('#rc-mail').value.trim(); if (!m) return; b.disabled = true; api('receipt', { sale_id: s.id, email: m }).then(function () { toast('Ticket envoyé à ' + m + '.', 'ok'); b.textContent = 'Envoyé'; }, function (e) { toast(e.message, 'ko'); b.disabled = false; }); };
    if (mode === 'off' || !s.receipt) setTimeout(function () { if ($('.result.ok') && !$('#rc-mail')) closeSheet(); }, 3500);
  }
  function fail(e, retry) {
    buzz([80, 60, 80]);
    $('#sheet-in').innerHTML = resultView(false, 'Paiement refusé', esc(e.message), '<div class="row"><button class="btn" data-close>Fermer</button>' + (retry ? '<button class="btn btn-primary" id="rt">Réessayer</button>' : '') + '</div>');
    if (retry) $('#rt').onclick = retry;
  }

  /** Paiement par scan (cashless ou crédit) : QR à la caméra, bracelet NFC ou code saisi. */
  function scanPay(method) {
    var stopCam = function () {}, busy = false, nfcOk = NFC.available();
    sheet('<h2>' + (method === 'allowance' ? 'Crédit offert' : 'Cashless') + '</h2><p class="muted" style="margin:0" id="sp-amount">Calcul…</p><div class="cam"><video muted></video></div>'
      + (nfcOk ? '<div class="hint nfc-pulse"><svg viewBox="0 0 24 24"><path d="M6 8.5a8 8 0 0 1 0 7M9.5 6a12 12 0 0 1 0 12M13 4a15 15 0 0 1 0 16"/></svg>Ou approche le bracelet</div>' : '')
      + '<div class="row"><input class="input-big" id="sp-code" placeholder="' + (method === 'allowance' ? 'Code du badge' : 'Code du billet') + '" autocomplete="off" autocapitalize="characters"><button class="btn btn-primary" id="sp-ok" style="flex:0 0 auto">OK</button></div><button class="btn" data-close>Annuler</button>', function () { stopCam(); NFC.stop(); });
    quote().then(function (q) { var a = $('#sp-amount'); if (a) a.textContent = q.total < 0 ? 'À recréditer : ' + money(-q.total) : 'À payer : ' + money(q.total); }, function (e) { fail(e); });
    var go = function (who) {
      if (busy) return; busy = true; stopCam(); NFC.stop();
      var sale = newSale(method, who);
      $('#sheet-in').innerHTML = '<div class="result"><p>Paiement en cours…</p></div>';
      api('sale', sale).then(function (j) {
        remember(sale, j.sale, false); afterSale();
        done(j, method === 'allowance' ? esc(j.holder) + ' · reste ' + esc(j.unlimited ? 'illimité' : j.left) + ' aujourd\'hui' : (j.name ? esc(j.name) + ' · ' : '') + 'nouveau solde <b>' + money(j.balance) + '</b>');
      }, function (e) { fail(e, function () { scanPay(method); }); });
    };
    stopCam = startCam($('#sheet video'), function (code) { buzz(30); go({ code: code }); });
    if (nfcOk) NFC.start(function (u) { go({ nfc: u }); }).catch(function () { var h = $('.hint'); if (h) h.remove(); });
    $('#sp-ok').onclick = function () { var v = $('#sp-code').value.trim(); if (v) go({ code: v }); };
    $('#sp-code').onkeydown = function (e) { if (e.key === 'Enter') $('#sp-ok').click(); };
  }

  function payCard() {
    var t = C().tpe || { mode: 'manual' };
    sheet('<h2>Carte bancaire</h2><p class="muted" style="margin:0">Calcul du montant…</p>');
    quote().then(function (q) { withTip(q.total, function (tip) {
      var amount = q.total + tip;
      var manual = function (note) {
        sheet('<h2>Carte bancaire</h2><div class="big">' + money(amount) + '</div>' + (note ? '<div class="hint">' + note + '</div>' : '') + '<p class="muted" style="text-align:center;margin:0">Encaisse ce montant sur le terminal de paiement, puis confirme quand il est accepté.</p><button class="btn btn-primary btn-xl" id="cd-ok">Paiement accepté</button><button class="btn" data-close>Annuler</button>');
        $('#cd-ok').onclick = function () { this.disabled = true; record(newSale('card', { payment: { via: 'tpe' }, tip: tip })).then(function (r) { if (r.queued) { closeSheet(); toast('Vente carte gardée : envoi au retour du réseau.', 'ok'); } else done(r); }, function (e) { fail(e); }); };
      };
      if (t.mode !== 'nepting') return manual('');
      if (!TPE.plugin()) return manual('Le terminal Nepting n\'est piloté que depuis l\'application Android : encaisse à la main sur le terminal.');
      var sale = newSale('card', { tip: tip });
      var run = function () {
        sheet('<h2>Carte bancaire</h2><div class="big">' + money(amount) + '</div><div class="hint nfc-pulse"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3 10h18"/></svg>Le client présente sa carte sur le terminal…</div>');
        TPE.pay(t, amount, sale.client_id).then(function (pm) {
          sale.payment = pm;
          record(sale).then(function (r) { if (r.queued) { closeSheet(); toast('Carte acceptée. Vente gardée, envoi au retour du réseau.', 'ok'); } else done(r, pm.auth ? 'Autorisation ' + esc(pm.auth) : ''); });
        }, function (e) {
          $('#sheet-in').innerHTML = resultView(false, 'Paiement carte non effectué', esc(e.message), '<div class="row"><button class="btn" data-close>Fermer</button><button class="btn btn-primary" id="cd-retry">Réessayer</button></div><button class="btn btn-ghost" id="cd-manual">Encaisser sans le terminal connecté</button>');
          $('#cd-retry').onclick = run; $('#cd-manual').onclick = function () { manual(''); };
        });
      };
      run();
    }); }, function (e) { fail(e); });
  }

  function payCash() {
    sheet('<h2>Espèces</h2><p class="muted" style="margin:0">Calcul du montant…</p>');
    quote().then(function (q) { withTip(q.total, function (tip) {
      var amount = q.total + tip;
      if (amount <= 0) {
        sheet('<h2>Rendre en espèces</h2><div class="big">' + money(-amount) + '</div><p class="muted" style="text-align:center;margin:0">Remboursement des consignes rendues.</p><button class="btn btn-green btn-xl" id="cs-ok">Valider</button><button class="btn" data-close>Annuler</button>');
        $('#cs-ok').onclick = function () { this.disabled = true; record(newSale('cash')).then(function (r) { if (r.queued) { closeSheet(); toast('Retour gardé, envoi au retour du réseau.', 'ok'); } else done(r); }, function (e) { fail(e); }); };
        return;
      }
      var given = '';
      var render = function () {
        var g = given === '' ? 0 : parseInt(given, 10), ch = g - amount;
        $('#cs-given').textContent = given === '' ? '—' : money(g);
        var c = $('#cs-change'); c.className = 'change' + (g && ch < 0 ? ' short' : '');
        c.innerHTML = g ? (ch >= 0 ? '<span>À rendre</span><b>' + money(ch) + '</b>' : '<span>Il manque</span><b>' + money(-ch) + '</b>') : '<span>À rendre</span><b>—</b>';
        $('#cs-ok').disabled = g && ch < 0;
      };
      var quick = [amount];
      [500, 1000, 2000, 5000].forEach(function (n) { var v = Math.ceil(amount / n) * n; if (quick.indexOf(v) < 0 && quick.length < 4) quick.push(v); });
      sheet('<h2>Espèces · ' + money(amount) + '</h2><div class="row">' + quick.map(function (v) { return '<button class="btn" data-given="' + v + '">' + (v === amount ? 'Compte juste' : money(v)) + '</button>'; }).join('') + '</div>'
        + '<div class="row" style="align-items:baseline"><span class="muted">Reçu</span><b id="cs-given" style="text-align:right;font-size:24px">—</b></div>'
        + '<div class="keys" id="cs-keys"></div><div class="change" id="cs-change"></div><button class="btn btn-green btn-xl" id="cs-ok">Valider la vente</button><button class="btn" data-close>Annuler</button>');
      $$('[data-given]').forEach(function (b) { b.onclick = function () { given = b.dataset.given; render(); }; });
      keypad($('#cs-keys'), ['1', '2', '3', '4', '5', '6', '7', '8', '9', '00', '0', '⌫'], function (k) { given = k === '⌫' ? given.slice(0, -1) : (given + k).replace(/^0+/, '').slice(0, 7); render(); });
      $('#cs-ok').onclick = function () {
        this.disabled = true;
        var g = given === '' ? amount : parseInt(given, 10);
        record(newSale('cash', { cash_given: g, tip: tip })).then(function (r) {
          if (r.queued) { $('#sheet-in').innerHTML = resultView(true, 'Rendre ' + money(Math.max(0, g - amount)), 'Vente gardée, envoi au retour du réseau.', '<button class="btn btn-primary btn-xl" data-close>Vente suivante</button>'); return; }
          done(r, 'Rendre <b>' + money(Math.max(0, g - amount)) + '</b>');
        }, function (e) { fail(e); });
      };
      render();
    }); }, function (e) { fail(e); });
  }

  function payOffered() {
    if (!S.cart.reason) return offerFlow('Offrir', payOffered);
    sheet('<div class="result"><p>Enregistrement…</p></div>');
    var sale = newSale('offered');
    api('sale', sale).then(function (j) { remember(sale, j.sale, false); afterSale(); done(j, 'Offert : ' + money(j.sale.offered)); }, function (e) { if (e.network) { S.queue.push(sale); remember(sale, null, true); save(); afterSale(); closeSheet(); toast('Gardé, envoi au retour du réseau.', 'ok'); } else fail(e); });
  }

  /** 2.9 : les paiements moins courants regroupés derrière « Autre » (le bar garde trois gros boutons). */
  function payMore() {
    var q = quoteLocal(); if (!q.lines.length) return;
    var m = C().methods, opts = [];
    if (m.indexOf('allowance') >= 0) opts.push(['allowance', 'Crédit staff ou artiste', 'Scanne le badge : les consommations sont décomptées de son crédit.', '<rect x="4" y="3" width="16" height="18" rx="3"/><path d="M9 8h6M9 12h6M9 16h3"/>']);
    if (m.filter(function (x) { return x !== 'allowance'; }).length >= 2) opts.push(['split', 'Plusieurs moyens', 'Par exemple le solde cashless puis le reste en espèces.', '<path d="M4 12h6M14 12h6M12 4v16"/>']);
    opts.push(['offer', 'Offrir la commande', 'Avec un motif ; un responsable valide si besoin.', '<rect x="4" y="9" width="16" height="11" rx="2"/><path d="M12 9v11M4 13h16"/>']);
    sheet('<h2>Autre paiement · ' + money(q.total) + '</h2><div class="more-list">' + opts.map(function (o) {
      return '<button class="more" data-more="' + o[0] + '"' + (o[0] === 'allowance' && !S.online ? ' disabled' : '') + '><svg viewBox="0 0 24 24">' + o[3] + '</svg><span><b>' + o[1] + '</b><small>' + o[2] + '</small></span></button>';
    }).join('') + '</div><button class="btn" data-close>Annuler</button>');
    $$('[data-more]').forEach(function (b) {
      b.onclick = function () {
        var k = b.dataset.more; closeSheet();
        if (k === 'allowance') return scanPay('allowance');
        if (k === 'split') return paySplit();
        offerFlow('Offrir la commande', function () { S.cart.offerAll = true; renderCart(); payOffered(); });
      };
    });
  }

  // ---------- 2.8 : paiement en plusieurs fois ----------
  function paySplit() {
    sheet('<h2>Paiement en plusieurs fois</h2><p class="muted" style="margin:0">Calcul du montant…</p>');
    quote().then(function (q) {
      var total = q.total, parts = [];
      if (total <= 0) return fail(new Error('Rien à fractionner.'));
      var left = function () { return total - parts.reduce(function (t, p) { return t + p.amount; }, 0); };
      var render = function () {
        var r = left(), m = C().methods;
        sheet('<h2>Paiement en plusieurs fois</h2><div class="split-total"><span>Total</span><b>' + money(total) + '</b></div>'
          + '<div class="list">' + parts.map(function (p, i) { return '<div class="it"><span class="badge ' + p.method + '">' + METHOD[p.method] + '</span><div class="g"><b>' + money(p.amount) + '</b>' + (p.label ? '<span>' + esc(p.label) + '</span>' : '') + '</div>' + (p.method === 'card' && p.payment && p.payment.via === 'nepting' ? '' : '<button class="btn btn-sm btn-red" data-rm="' + i + '">Retirer</button>') + '</div>'; }).join('') + '</div>'
          + (r > 0 ? '<div class="change short"><span>Reste à payer</span><b>' + money(r) + '</b></div><div class="row">'
            + (m.indexOf('cashless') >= 0 && S.online && !parts.some(function (p) { return p.method === 'cashless'; }) ? '<button class="btn" data-part="cashless">Cashless</button>' : '')
            + (m.indexOf('card') >= 0 ? '<button class="btn" data-part="card">Carte</button>' : '') + (m.indexOf('cash') >= 0 ? '<button class="btn" data-part="cash">Espèces</button>' : '') + '</div>'
            : '<div class="change"><span>Tout est réglé</span><b>' + money(total) + '</b></div><button class="btn btn-green btn-xl" id="sp-go">Valider la vente</button>')
          + '<button class="btn" data-close>Annuler</button>');
        $$('[data-rm]').forEach(function (b) { b.onclick = function () { parts.splice(+b.dataset.rm, 1); render(); }; });
        $$('[data-part]').forEach(function (b) { b.onclick = function () { addPart(b.dataset.part); }; });
        var go = $('#sp-go');
        if (go) go.onclick = function () {
          go.disabled = true;
          var sale = newSale('split', { payments: parts.map(function (p) { return Object.assign({ method: p.method, amount: p.amount }, p.who || {}, p.payment ? { payment: p.payment } : {}, p.given ? { cash_given: p.given } : {}); }) });
          sheet('<div class="result"><p>Enregistrement…</p></div>');
          api('sale', sale).then(function (j) { remember(sale, j.sale, false); afterSale(); done(j, parts.map(function (p) { return METHOD[p.method] + ' ' + money(p.amount); }).join(' + ')); }, function (e) { fail(e, render); });
        };
      };
      var addPart = function (method) {
        var r = left();
        if (method === 'cashless') {
          scanSheet('Cashless : scanne le billet', 'Code du billet', function (who) {
            api('lookup', who).then(function (j) {
              if (j.balance <= 0) { toast('Solde nul sur ce porte-monnaie.', 'ko'); return render(); }
              parts.unshift({ method: 'cashless', amount: Math.min(j.balance, r), who: who, label: (j.name ? j.name + ' · ' : '') + 'solde ' + money(j.balance) });
              render();
            }, function (e) { toast(e.message, 'ko'); render(); });
          });
          return;
        }
        amountSheet(method === 'card' ? 'Part en carte' : 'Part en espèces', 'Reste à payer : ' + money(r), function (a) {
          a = Math.min(a || r, r);
          if (method === 'cash') { parts.push({ method: 'cash', amount: a, given: a }); return render(); }
          var t = C().tpe || { mode: 'manual' };
          if (t.mode === 'nepting' && TPE.plugin()) {
            sheet('<h2>Carte bancaire</h2><div class="big">' + money(a) + '</div><div class="hint nfc-pulse">Le client présente sa carte sur le terminal…</div>');
            TPE.pay(t, a, uid()).then(function (pm) { parts.push({ method: 'card', amount: a, payment: pm, label: pm.auth ? 'autorisation ' + pm.auth : '' }); render(); }, function (e) { toast(e.message, 'ko'); render(); });
          } else {
            sheet('<h2>Carte bancaire</h2><div class="big">' + money(a) + '</div><p class="muted" style="text-align:center;margin:0">Encaisse ce montant sur le terminal, puis confirme.</p><button class="btn btn-primary btn-xl" id="pc-ok">Paiement accepté</button><button class="btn" id="pc-no">Annuler</button>');
            $('#pc-ok').onclick = function () { parts.push({ method: 'card', amount: a, payment: { via: 'tpe' } }); render(); };
            $('#pc-no').onclick = render;
          }
        }, false, r);
      };
      render();
    }, function (e) { fail(e); });
  }

  // ---------- 2.8 : point de recharge ----------
  var TOP = { who: null, info: null };
  function openTopup() {
    var b = bar(); if (!b) return openStation();
    $('#tp-bar').textContent = b.name + ' · ' + C().event.name;
    $('#tp-staff').textContent = staff() ? staff().name : (S.shift && S.shift.name) || '';
    $('#tp-cash').hidden = C().methods.indexOf('cash') < 0; $('#tp-card').hidden = C().methods.indexOf('card') < 0;
    topReset(); show('s-topup'); keepAwake();
  }
  function topReset() {
    TOP = { who: null, info: null, amount: 0 };
    $('#tp-who').innerHTML = '<p class="muted">Scanne le QR code du billet, approche le bracelet ou tape le code.</p>';
    $('#tp-step2').hidden = true; $('#tp-code').value = '';
    topScan();
  }
  var topStop = function () {};
  function topScan() {
    topStop(); NFC.stop();
    var v = $('#tp-video'); if (!v) return;
    var go = function (who) { topStop(); NFC.stop(); topIdentify(who); };
    topStop = startCam(v, function (c) { buzz(30); go({ code: c }); });
    if (NFC.available()) NFC.start(function (u) { go({ nfc: u }); }).catch(function () { /* rien */ });
  }
  function topIdentify(who) {
    api('lookup', who).then(function (j) {
      TOP.who = who; TOP.info = j;
      $('#tp-who').innerHTML = '<div class="tp-card"><span>' + esc(j.name || (who.nfc ? 'Bracelet' : 'Billet')) + (j.nfc ? ' · bracelet' : '') + '</span><b>' + money(j.balance) + '</b><small>solde actuel</small></div>';
      $('#tp-step2').hidden = false; TOP.amount = 0; topAmount();
    }, function (e) {
      if (who.nfc && /non associé/.test(e.message)) {
        $('#tp-who').innerHTML = '<div class="tp-card"><span>Bracelet neuf</span><b>—</b><small>pas encore activé</small></div><button class="btn btn-primary" id="tp-activate">Activer ce bracelet</button>';
        $('#tp-activate').onclick = function () { api('new_wallet', { nfc: who.nfc }).then(function () { toast('Bracelet activé.', 'ok'); topIdentify(who); }, function (e2) { toast(e2.message, 'ko'); }); };
        return;
      }
      toast(e.message, 'ko'); topReset();
    });
  }
  function topAmount() {
    $$('#tp-amounts [data-amt]').forEach(function (b) { b.classList.toggle('on', +b.dataset.amt === TOP.amount); });
    $('#tp-total').textContent = TOP.amount ? money(TOP.amount) : '—';
    $('#tp-cash').disabled = $('#tp-card').disabled = !TOP.amount;
  }
  function topPay(method) {
    if (!TOP.who || !TOP.amount) return;
    var run = function (payment) {
      sheet('<div class="result"><p>Recharge en cours…</p></div>');
      api('topup', Object.assign({ method: method, amount: TOP.amount, client_id: uid(), bar_id: S.shift.bar_id }, TOP.who, payment ? { payment: payment } : {})).then(function (j) {
        buzz([30, 40, 30]);
        sheet(resultView(true, '+ ' + money(j.sale.amount), (j.name ? esc(j.name) + ' · ' : '') + 'nouveau solde <b>' + money(j.balance) + '</b>' + (j.sale.ticket ? '<br><span class="muted">Ticket n° ' + j.sale.ticket + '</span>' : ''), '<button class="btn btn-primary btn-xl" data-close>Recharge suivante</button>'));
        topReset();
        setTimeout(function () { if ($('.result.ok')) closeSheet(); }, 3500);
      }, function (e) { fail(e); });
    };
    if (method === 'card') {
      var t = C().tpe || { mode: 'manual' };
      if (t.mode === 'nepting' && TPE.plugin()) {
        sheet('<h2>Carte bancaire</h2><div class="big">' + money(TOP.amount) + '</div><div class="hint nfc-pulse">Le client présente sa carte sur le terminal…</div>');
        return TPE.pay(t, TOP.amount, uid()).then(function (pm) { run(pm); }, function (e) { fail(e); });
      }
      sheet('<h2>Carte bancaire</h2><div class="big">' + money(TOP.amount) + '</div><p class="muted" style="text-align:center;margin:0">Encaisse ce montant sur le terminal, puis confirme.</p><button class="btn btn-primary btn-xl" id="tc-ok">Paiement accepté</button><button class="btn" data-close>Annuler</button>');
      $('#tc-ok').onclick = function () { run({ via: 'tpe' }); };
      return;
    }
    run(null);
  }
  function topCashout() {
    if (!TOP.who) return;
    withPerm('cashout', 'Rembourser un solde', function (approver) {
      if (!confirm('Rembourser ' + money(TOP.info.balance) + ' en espèces ? Le porte-monnaie sera clôturé.')) return;
      api('cashout', Object.assign({ approver_pin: approver || undefined, client_id: uid(), bar_id: S.shift.bar_id }, TOP.who)).then(function (j) {
        sheet(resultView(true, 'Rendre ' + money(j.refunded), (j.name ? esc(j.name) + ' · ' : '') + 'solde remboursé, porte-monnaie clôturé.', '<button class="btn btn-primary btn-xl" data-close>Terminé</button>'));
        topReset();
      }, function (e) { toast(e.message, 'ko'); });
    });
  }

  // ---------- 2.8 : préparation et écran d'appel ----------
  var poll = null;
  function stopPoll() { if (poll) { clearInterval(poll); poll = null; } }
  var ago2 = function (at) { var m = Math.max(0, Math.round((Date.now() - new Date(at.replace(' ', 'T')).getTime()) / 60000)); return m < 1 ? 'à l\'instant' : m + ' min'; };
  function openPrep() {
    $('#pr-title').textContent = (bar() ? bar().name : 'Tous les points de vente') + ' · ' + C().event.name;
    show('s-prep'); keepAwake();
    var load = function () {
      api('prep_list', {}, 8000).then(function (j) { renderPrep(j.orders); }, function () { /* hors ligne */ });
    };
    stopPoll(); load(); poll = setInterval(load, 4000);
  }
  var seen = {};
  function renderPrep(orders) {
    var col = function (st) {
      return orders.filter(function (o) { return o.status === st; }).map(function (o) {
        var fresh = !seen[o.id]; seen[o.id] = 1;
        return '<button class="prep-card' + (fresh ? ' fresh' : '') + '" data-prep="' + o.id + '" data-st="' + o.status + '"><div class="prep-top"><b>' + o.order + '</b><span>' + ago2(o.at) + '</span></div>'
          + (bar() ? '' : '<small>' + esc(o.bar) + '</small>') + '<ul>' + o.lines.map(function (l) { return '<li><b>' + l.qty + '×</b> ' + esc(l.name) + '</li>'; }).join('') + '</ul>'
          + '<span class="prep-act">' + (o.status === 'new' ? 'Marquer prêt' : 'Servi') + '</span></button>';
      }).join('') || '<p class="muted">Rien pour l\'instant.</p>';
    };
    if (orders.some(function (o) { return !seen[o.id] && o.status === 'new'; })) buzz([40, 30, 40]);
    $('#pr-new').innerHTML = col('new'); $('#pr-ready').innerHTML = col('ready');
    $('#pr-count-new').textContent = orders.filter(function (o) { return o.status === 'new'; }).length;
    $('#pr-count-ready').textContent = orders.filter(function (o) { return o.status === 'ready'; }).length;
  }
  function prepTap(id, st) {
    api('prep_set', { sale_id: id, status: st === 'new' ? 'ready' : 'served' }).then(function (j) { renderPrep(j.orders); }, function (e) { toast(e.message, 'ko'); });
  }
  var lastReady = [];
  function openDisplay() {
    show('s-display');
    $('#ds-title').textContent = (bar() ? bar().name : C().event.name);
    var load = function () {
      api('calls', {}, 8000).then(function (j) {
        var ready = j.orders.filter(function (o) { return o.status === 'ready'; }).map(function (o) { return o.order; });
        var fresh = ready.filter(function (n) { return lastReady.indexOf(n) < 0; });
        lastReady = ready;
        $('#ds-ready').innerHTML = ready.map(function (n) { return '<b class="' + (fresh.indexOf(n) >= 0 ? 'fresh' : '') + '">' + n + '</b>'; }).join('') || '<p>—</p>';
        $('#ds-prep').innerHTML = j.preparing.map(function (n) { return '<span>' + n + '</span>'; }).join('') || '<p>—</p>';
        if (fresh.length) chime();
      }, function () { /* hors ligne */ });
    };
    stopPoll(); load(); poll = setInterval(load, 3000);
  }
  function chime() {
    try { var A = window.AudioContext || window.webkitAudioContext; if (!A) return; var c = chime.c || (chime.c = new A()), o = c.createOscillator(), g = c.createGain();
      o.connect(g); g.connect(c.destination); o.frequency.value = 880; g.gain.setValueAtTime(.0001, c.currentTime); g.gain.exponentialRampToValueAtTime(.3, c.currentTime + .02); g.gain.exponentialRampToValueAtTime(.0001, c.currentTime + .6); o.start(); o.stop(c.currentTime + .65); } catch (e) { /* rien */ }
  }

  // ---------- 2.10 : comptage par billets et pièces ----------
  /** Feuille de comptage : quantités par billet/pièce, ou total saisi directement. cb(total, count|null, note). */
  function countSheet(title, sub, cb, opts) {
    opts = opts || {};
    var D = C().settings.denoms || [50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100, 50, 20, 10, 5, 2, 1];
    if (!C().settings.count_denoms) return amountSheet(title, sub, function (a, n) { cb(a, null, n); }, opts.required, opts.preset);
    var q = {}, lbl = function (v) { return v >= 100 ? (v / 100) + ' €' : v + ' c'; };
    var tot = function () { return D.reduce(function (t, v) { return t + v * (q[v] || 0); }, 0); };
    var row = function (v) { return '<div class="cnt-row' + (v >= 500 ? ' bill' : '') + '"><span class="cnt-d">' + lbl(v) + '</span><button data-cm="' + v + '">−</button><input inputmode="numeric" data-cq="' + v + '" value="' + (q[v] || '') + '" placeholder="0"><button data-cp="' + v + '">+</button><b data-csum="' + v + '">' + (q[v] ? money(v * q[v]) : '') + '</b></div>'; };
    sheet('<h2>' + esc(title) + '</h2>' + (sub ? '<p class="muted" style="margin:0">' + esc(sub) + '</p>' : '')
      + '<div class="cnt"><div class="cnt-col"><small>Billets</small>' + D.filter(function (v) { return v >= 500; }).map(row).join('') + '</div><div class="cnt-col"><small>Pièces</small>' + D.filter(function (v) { return v < 500; }).map(row).join('') + '</div></div>'
      + '<div class="change"><span>Total compté</span><b id="cnt-tot">0,00 €</b></div>'
      + (opts.note === false ? '' : '<input class="input-big" id="cnt-note" placeholder="Note (facultatif)">')
      + '<button class="btn btn-primary btn-xl" id="cnt-ok">' + esc(opts.ok || 'Valider') + '</button><div class="row"><button class="btn btn-ghost" id="cnt-total">Saisir le total</button><button class="btn" data-close>Annuler</button></div>');
    var upd = function (v) {
      var i = $('[data-cq="' + v + '"]'); if (i && document.activeElement !== i) i.value = q[v] || '';
      $('[data-csum="' + v + '"]').textContent = q[v] ? money(v * q[v]) : '';
      $('#cnt-tot').textContent = money(tot());
    };
    $$('[data-cp]').forEach(function (b) { b.onclick = function () { var v = +b.dataset.cp; q[v] = (q[v] || 0) + 1; buzz(5); upd(v); }; });
    $$('[data-cm]').forEach(function (b) { b.onclick = function () { var v = +b.dataset.cm; q[v] = Math.max(0, (q[v] || 0) - 1); upd(v); }; });
    $$('[data-cq]').forEach(function (i) { i.oninput = function () { var v = +i.dataset.cq; q[v] = Math.max(0, parseInt(i.value.replace(/\D/g, ''), 10) || 0); upd(v); }; });
    $('#cnt-ok').onclick = function () { var t = tot(); if (!t && opts.required === false) return; var c = {}; D.forEach(function (v) { if (q[v]) c[v] = q[v]; }); cb(t, Object.keys(c).length ? c : null, $('#cnt-note') ? $('#cnt-note').value.trim() : ''); };
    $('#cnt-total').onclick = function () { amountSheet(title, sub, function (a, n) { cb(a, null, n); }, opts.required, opts.preset); };
  }

  // ---------- 2.10 : additions ouvertes (tables, comptes), partagées par le point de vente ----------
  var TABS = [];
  function tabsOn() { return C().settings.tabs && S.online; }
  function refreshTabs() {
    if (!C() || !C().settings.tabs || !bar()) return Promise.resolve([]);
    return api('tabs', {}, 8000).then(function (j) { TABS = j.tabs; renderTabsBtn(); return TABS; }, function () { return TABS; });
  }
  function renderTabsBtn() {
    var b = $('#tabs-btn'); if (!b) return;
    b.hidden = !C().settings.tabs; $('#tabs-n').textContent = TABS.length; $('#tabs-n').hidden = !TABS.length;
    var t = S.cart.tab; $('#cart-tab').hidden = !t;
    if (t) $('#cart-tab-l').textContent = t.label;
    $('#t-hold').hidden = !C().settings.tabs;
    $('#t-hold').classList.toggle('on', !!t);
  }
  function cartForTab() { var p = payload(); return { items: p.items, returns: p.returns }; }
  /** « Mettre en attente » : enregistre le panier dans une addition (nouvelle ou celle en cours). */
  function holdCart() {
    if (!S.cart.items.length && !Object.keys(S.cart.returns).length) return toast('Ajoute d\'abord des produits.');
    if (!S.online) return toast('Les additions ont besoin du réseau.', 'ko');
    var t = S.cart.tab;
    var send = function (label) {
      api('tab_save', Object.assign({ cart: cartForTab(), label: label }, t ? { id: t.id, version: t.version } : {})).then(function (j) {
        closeSheet(); emptyCart(); renderGrid(); renderCart(); toast('Addition « ' + j.tab.label + ' » : ' + money(j.tab.total) + '.', 'ok'); refreshTabs();
      }, function (e) { toast(e.message, 'ko'); });
    };
    if (t) return send('');
    var used = TABS.map(function (x) { return x.label; });
    var sugg = []; for (var i = 1; i <= 12 && sugg.length < 8; i++) if (used.indexOf('Table ' + i) < 0) sugg.push('Table ' + i);
    sheet('<h2>Mettre en attente</h2><p class="muted" style="margin:0">L\'addition reste ouverte sur tous les appareils de ' + esc((bar() || {}).name || 'ce point de vente') + ' : on y ajoute des consommations et on la règle plus tard.</p>'
      + '<div class="variants">' + sugg.map(function (s) { return '<button class="variant" data-tl="' + esc(s) + '"><b>' + esc(s) + '</b></button>'; }).join('') + '</div>'
      + '<input class="input-big" id="tl-name" maxlength="80" placeholder="Nom : prénom, table, loge…"><button class="btn btn-primary btn-xl" id="tl-ok">Ouvrir l\'addition</button><button class="btn" data-close>Annuler</button>');
    $$('[data-tl]').forEach(function (b) { b.onclick = function () { send(b.dataset.tl); }; });
    $('#tl-ok').onclick = function () { var v = $('#tl-name').value.trim(); if (!v) return $('#tl-name').focus(); send(v); };
  }
  function agoTab(at) { var m = Math.max(0, Math.round((Date.now() - new Date(String(at).replace(' ', 'T')).getTime()) / 60000)); return m < 60 ? m + ' min' : Math.floor(m / 60) + ' h ' + (m % 60 ? m % 60 : ''); }
  /** Liste des additions ouvertes du point de vente. */
  function openTabs() {
    sheet('<h2>Additions ouvertes</h2><p class="muted" style="margin:0">Chargement…</p>');
    refreshTabs().then(function (list) {
      var sum = list.reduce(function (t, x) { return t + x.total; }, 0);
      sheet('<h2>Additions ouvertes · ' + esc((bar() || {}).name || '') + '</h2>' + (list.length ? '<p class="muted" style="margin:0">' + list.length + ' addition(s) · ' + money(sum) + ' à encaisser</p>' : '')
        + '<div class="tab-list">' + (list.map(function (t) {
          return '<button class="tab-it" data-tab="' + t.id + '"><span><b>' + esc(t.label) + '</b><small>' + t.items + ' article(s) · ouverte il y a ' + agoTab(t.created_at) + (t.staff ? ' · ' + esc(t.staff) : '') + '</small></span><b>' + money(t.total) + '</b></button>';
        }).join('') || '<p class="muted">Aucune addition ouverte. Dans le panier, « En attente » en crée une.</p>') + '</div><button class="btn" data-close>Fermer</button>');
      $$('[data-tab]').forEach(function (b) { b.onclick = function () { tabMenu(list.filter(function (x) { return x.id === +b.dataset.tab; })[0]); }; });
    });
  }
  function tabMenu(t) {
    var lines = (t.cart.items || []).map(function (i) { var p = i.free ? null : product(i.id); return '<div class="it"><div class="g">' + i.qty + ' × ' + esc(i.free ? (i.name || 'Montant libre') : p ? itemName(p, i) : (i.name || 'Article')) + '</div></div>'; }).join('');
    sheet('<h2>' + esc(t.label) + '</h2><p class="muted" style="margin:0">' + money(t.total) + ' · ouverte il y a ' + agoTab(t.created_at) + (t.staff ? ' par ' + esc(t.staff) : '') + '</p><div class="list">' + lines + '</div>'
      + '<button class="btn btn-primary btn-xl" id="tm-open">Reprendre (ajouter, encaisser)</button><button class="btn btn-red" id="tm-cancel">Annuler l\'addition</button><button class="btn" data-close>Retour</button>');
    $('#tm-open').onclick = function () { loadTab(t); };
    $('#tm-cancel').onclick = function () {
      if (!confirm('Annuler l\'addition « ' + t.label + ' » (' + money(t.total) + ') ? Rien ne sera encaissé.')) return;
      var go = function (approver) { api('tab_cancel', { id: t.id, approver_pin: approver || undefined }).then(function (j) { TABS = j.tabs; renderTabsBtn(); closeSheet(); toast('Addition annulée.', 'ok'); if (S.cart.tab && S.cart.tab.id === t.id) { emptyCart(); renderGrid(); renderCart(); } }, function (e) { toast(e.message, 'ko'); }); };
      if (t.total > 0) withPerm('undo', 'Annuler une addition', go); else go(null);
    };
  }
  function loadTab(t) {
    var keep = S.cart.items.length && !S.cart.tab ? S.cart.items.slice() : [];
    if (S.cart.tab && S.cart.tab.id !== t.id && S.cart.items.length && !confirm('Le panier en cours (addition « ' + S.cart.tab.label + ' ») sera remis en attente sans les derniers ajouts non enregistrés. Continuer ?')) return;
    emptyCart();
    (t.cart.items || []).forEach(function (i) {
      var it = { key: (i.free ? 'free:' + uid() : i.id + ':' + (i.variant == null ? '' : i.variant) + ':' + (i.opts || []).slice().sort().join(',') + ':' + (i.picks || []).join(',')), id: i.id, variant: i.variant == null ? null : i.variant, opts: i.opts || [], picks: i.picks || [], qty: i.qty, offered: !!i.offered };
      if (i.free) { it.free = i.unit; it.name = i.name; }
      S.cart.items.push(it);
    });
    (t.cart.returns || []).forEach(function (r) { S.cart.returns[r.deposit_id] = r.qty; });
    keep.forEach(function (k) { S.cart.items.push(k); });
    S.cart.tab = { id: t.id, label: t.label, version: t.version };
    closeSheet(); renderGrid(); renderCart();
    toast('Addition « ' + t.label + ' » reprise' + (keep.length ? ', avec le panier en cours' : '') + '.', 'ok');
  }

  // ---------- 2.10 : montant libre ----------
  function freeAmount() {
    amountSheet('Montant libre', 'Article hors carte, au prix saisi (TVA par défaut).', function (a, note) {
      closeSheet();
      S.cart.items.push({ key: 'free:' + uid(), id: 0, free: a, name: (note || 'Montant libre').slice(0, 60), variant: null, opts: [], picks: [], qty: 1, offered: false });
      buzz(8); renderCart();
    });
    $('#am-note').placeholder = 'Libellé (ex. Tote bag)';
  }

  // ---------- 2.10 : X de caisse, ventes par serveur ----------
  function staffTable(st) {
    var k = Object.keys(st || {}); if (!k.length) return '';
    return '<details open><summary class="linklike">Par serveur</summary><div class="list">' + k.map(function (n) { var x = st[n]; return '<div class="it"><div class="g"><b>' + esc(n) + '</b><small class="muted"> · ' + x.n + ' vente(s)' + (x.offered ? ' · offert ' + money(x.offered) : '') + '</small></div><span class="muted">esp. ' + money(x.cash) + '</span><b style="min-width:80px;text-align:right">' + money(x.total) + '</b></div>'; }).join('') + '</div></details>';
  }

  // ---------- 3.1 : pourboire ----------
  function withTip(total, cb) {
    var s = C().settings; if (!s.tips || total <= 0) return cb(0);
    var pcts = s.tip_pcts && s.tip_pcts.length ? s.tip_pcts : [5, 10, 15];
    var opts = [[0, 'Sans pourboire', '']];
    pcts.forEach(function (p) { var v = Math.max(10, Math.round(total * p / 1000) * 10); opts.push([v, p + ' %', '+ ' + money(v)]); });
    var up = Math.ceil((total + 1) / 100) * 100; opts.push([up - total, 'Arrondir', 'à ' + money(up)]);
    sheet('<h2>Pourboire ?</h2><p class="muted" style="margin:0">Total ' + money(total) + ' · le client choisit sur l\'écran.</p><div class="variants tips">' + opts.map(function (o) { return '<button class="variant" data-tip="' + o[0] + '"><b>' + esc(o[1]) + '</b><span>' + esc(o[2]) + '</span></button>'; }).join('')
      + '<button class="variant" id="tip-other"><b>Autre</b><span>montant libre</span></button></div><button class="btn" data-close>Annuler</button>');
    $$('[data-tip]').forEach(function (b) { b.onclick = function () { cb(parseInt(b.dataset.tip, 10)); }; });
    $('#tip-other').onclick = function () { amountSheet('Pourboire', 'Montant ajouté au paiement', function (a) { cb(a); }); };
  }

  // ---------- 3.1 : quantité (réassort, casse) ----------
  function qtySheet(title, sub, opts, cb) {
    var n = opts.start || 1, reason = opts.reasons ? opts.reasons[0][0] : '';
    sheet('<h2>' + esc(title) + '</h2><p class="muted" style="margin:0">' + esc(sub) + '</p>'
      + (opts.reasons ? '<div class="variants">' + opts.reasons.map(function (r, i) { return '<button class="variant' + (i ? '' : ' on') + '" data-qr="' + r[0] + '"><b>' + esc(r[1]) + '</b></button>'; }).join('') + '</div>' : '')
      + '<div class="qty-big"><button id="qs-m">−</button><output id="qs-n">' + n + '</output><button id="qs-p">+</button></div>'
      + '<div class="row">' + (opts.quick || [6, 12, 24, 48]).map(function (v) { return '<button class="btn btn-sm" data-qq="' + v + '">' + v + '</button>'; }).join('') + '</div>'
      + '<input class="input-big" id="qs-note" placeholder="Note (facultatif)"><button class="btn btn-primary btn-xl" id="qs-ok">' + esc(opts.ok || 'Valider') + '</button><button class="btn" data-close>Annuler</button>');
    var upd = function () { $('#qs-n').textContent = n; };
    $('#qs-m').onclick = function () { n = Math.max(1, n - 1); upd(); }; $('#qs-p').onclick = function () { n++; upd(); };
    $$('[data-qq]').forEach(function (b) { b.onclick = function () { n = parseInt(b.dataset.qq, 10); upd(); }; });
    $$('[data-qr]').forEach(function (b) { b.onclick = function () { reason = b.dataset.qr; $$('[data-qr]').forEach(function (x) { x.classList.toggle('on', x === b); }); }; });
    $('#qs-ok').onclick = function () { cb(n, $('#qs-note').value.trim(), reason); };
  }

  // ---------- 3.1 : remboursement d'un ticket ----------
  function refundSheet(s) {
    var q = {}, reason = '';
    var total = function () { return s.lines.reduce(function (t, l) { return t + Math.round(l.total * (q[l.i] || 0) / l.qty); }, 0); };
    var how = s.refund_method === 'cashless' ? 'recrédité sur le cashless du client' : (s.refund_method === 'card' ? 'à rembourser sur le terminal de paiement' : 'à rendre en espèces');
    var render = function () {
      var t = total();
      sheet('<h2>Ticket n° ' + s.ticket + '</h2><p class="muted" style="margin:0">' + esc(s.bar) + ' · ' + new Date(s.at.replace(' ', 'T')).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) + ' · ' + esc(METHOD[s.method] || s.method) + ' · ' + money(s.amount) + (s.refunded ? ' · déjà remboursé ' + money(s.refunded) : '') + '</p>'
        + (s.refundable ? '' : '<div class="hint">Vente offerte ou payée avec un crédit : rien à rembourser.</div>')
        + '<div class="list">' + s.lines.map(function (l) {
          return '<div class="it"><div class="g"><b>' + esc(l.name) + '</b><span>' + l.qty + ' × ' + money(l.unit) + (l.left < l.qty ? ' · ' + (l.qty - l.left) + ' déjà remboursé(s)' : '') + '</span></div>'
            + (l.left && s.refundable ? '<div class="qty"><button data-rfm="' + l.i + '">−</button><output>' + (q[l.i] || 0) + '</output><button data-rfp="' + l.i + '">+</button></div>' : '<span class="muted">—</span>') + '</div>';
        }).join('') + '</div>'
        + '<div class="change"><span>Remboursement ' + how + '</span><b>' + money(t) + '</b></div>'
        + '<input class="input-big" id="rf-r" placeholder="Motif (renversé, erreur de commande…)" value="' + esc(reason) + '"><button class="btn btn-primary btn-xl" id="rf-ok"' + (t ? '' : ' disabled') + '>Rembourser ' + money(t) + '</button><button class="btn" data-close>Annuler</button>');
      $('#rf-r').oninput = function () { reason = this.value; };
      $$('[data-rfp]').forEach(function (b) { b.onclick = function () { var l = s.lines.filter(function (x) { return x.i === +b.dataset.rfp; })[0]; q[l.i] = Math.min(l.left, (q[l.i] || 0) + 1); render(); }; });
      $$('[data-rfm]').forEach(function (b) { b.onclick = function () { var i = +b.dataset.rfm; q[i] = Math.max(0, (q[i] || 0) - 1); render(); }; });
      $('#rf-ok').onclick = function () {
        withPerm('refund', 'Rembourser un ticket', function (approver) {
          api('refund', { sale_id: s.id, lines: Object.keys(q).filter(function (k) { return q[k]; }).map(function (k) { return { i: +k, qty: q[k] }; }), reason: reason, approver_pin: approver || undefined }).then(function (j) {
            buzz([30, 40, 30]);
            sheet(resultView(true, 'Remboursé ' + money(j.refunded), (j.method === 'card' ? 'Fais le remboursement sur le terminal de paiement (fonction « remboursement »).' : j.method === 'cashless' ? 'Montant recrédité' + (j.balance != null ? ' · nouveau solde ' + money(j.balance) : '') + '.' : 'Rends ce montant en espèces.') + '<br><span class="muted">Ticket n° ' + j.sale.ticket + '</span>', '<button class="btn btn-primary btn-xl" data-close>Terminer</button>'));
          }, function (e) { toast(e.message, 'ko'); });
        });
      };
    };
    render();
  }

  // ---------- 3.1 : stock du bar (casse, réassort) ----------
  var LOSS = [['casse', 'Casse'], ['perime', 'Périmé'], ['offert', 'Offert hors caisse'], ['vol', 'Vol'], ['autre', 'Autre']];
  function stockSheet(list) {
    sheet('<h2>Stock · ' + esc((bar() || {}).name || '') + '</h2>'
      + (list.length ? '<div class="list">' + list.map(function (p) {
        return '<div class="it"><div class="g"><b>' + esc(p.name) + '</b><span>' + (p.requested ? p.requested + ' en réassort demandé' : (p.qty <= p.alert ? 'stock bas' : '')) + '</span></div><b class="stk' + (p.qty <= 0 ? ' neg' : p.qty <= p.alert ? ' low' : '') + '">' + p.qty + '</b>'
          + '<button class="btn btn-sm" data-sreq="' + p.id + '">Réassort</button><button class="btn btn-sm btn-red" data-sloss="' + p.id + '">Perte</button></div>';
      }).join('') + '</div>' : '<p class="muted">Aucun produit suivi en stock pour ce point de vente. L\'organisateur l\'active dans le catalogue (« Suivre le stock »).</p>')
      + '<button class="btn" data-close>Fermer</button>');
    var name = function (id) { return (list.filter(function (p) { return p.id === id; })[0] || {}).name || ''; };
    $$('[data-sreq]').forEach(function (b) { b.onclick = function () { var id = +b.dataset.sreq;
      qtySheet('Demande de réassort', name(id) + ' : la régie reçoit la demande dans Entrevo Pay.', { ok: 'Envoyer la demande', start: 12 }, function (n, note) {
        api('stock_request', { product_id: id, qty: n, note: note }).then(function (j) { toast('Demande envoyée à la régie.', 'ok'); stockSheet(j.products); }, function (e) { toast(e.message, 'ko'); });
      }); }; });
    $$('[data-sloss]').forEach(function (b) { b.onclick = function () { var id = +b.dataset.sloss;
      withPerm('stock', 'Déclarer une perte', function (approver) {
        qtySheet('Perte · ' + name(id), 'Retiré du stock du bar, valorisé au prix d\'achat dans Entrevo Pay.', { ok: 'Déclarer la perte', reasons: LOSS, quick: [1, 2, 6, 12] }, function (n, note, reason) {
          api('stock_loss', { product_id: id, qty: n, reason: reason, note: note, approver_pin: approver || undefined }).then(function (j) { toast('Perte enregistrée.', 'ok'); stockSheet(j.products); }, function (e) { toast(e.message, 'ko'); });
        });
      }); }; });
  }

  // ---------- menu ----------
  function closeDrawer() { $('#drawer').hidden = true; if ($('#sheet').hidden) $('#sheet-bg').hidden = true; }
  function openDrawer() {
    $('#dr-device').textContent = C().device.name;
    $('#dr-staff').textContent = ((bar() || {}).name || '') + (staff() ? ' · ' + staff().name : (S.shift && S.shift.name ? ' · ' + S.shift.name : ''));
    $('#dr-foot').textContent = (NATIVE ? 'Application' : 'Web app') + ' · ' + S.server.replace(/^https?:\/\//, '') + (S.queue.length ? ' · ' + S.queue.length + ' vente(s) en attente' : '');
    var mode = (S.shift && S.shift.mode) || 'bar', till = mode === 'bar' || mode === 'topup';
    $('[data-menu="session"]').hidden = !C().settings.sessions || !till || !bar();
    $('[data-menu="history"]').hidden = !till;
    $('[data-menu="refund"]').hidden = mode !== 'bar' || !bar();
    $('[data-menu="stock"]').hidden = mode !== 'bar' || !bar();
    $('[data-menu="credit"]').hidden = C().methods.indexOf('allowance') < 0;
    $('[data-menu="lock"]').hidden = !C().settings.require_staff || !till;
    $('#drawer').hidden = false; $('#sheet-bg').hidden = false;
  }
  function scanSheet(title, hint, onWho) {
    var stop = function () {}, nfcOk = NFC.available();
    sheet('<h2>' + esc(title) + '</h2><div class="cam"><video muted></video></div>' + (nfcOk ? '<div class="hint nfc-pulse"><svg viewBox="0 0 24 24"><path d="M6 8.5a8 8 0 0 1 0 7M9.5 6a12 12 0 0 1 0 12"/></svg>Ou approche le bracelet</div>' : '') + '<div class="row"><input class="input-big" id="ss-code" placeholder="' + esc(hint) + '"><button class="btn btn-primary" id="ss-ok" style="flex:0 0 auto">OK</button></div><button class="btn" data-close>Fermer</button>', function () { stop(); NFC.stop(); });
    var go = function (who) { stop(); NFC.stop(); onWho(who); };
    stop = startCam($('#sheet video'), function (c) { go({ code: c }); });
    if (nfcOk) NFC.start(function (u) { go({ nfc: u }); }).catch(function () { /* rien */ });
    $('#ss-ok').onclick = function () { var v = $('#ss-code').value.trim(); if (v) go({ code: v }); };
  }
  var MENU = {
    history: function () {
      var rows = S.history.slice(0, 40).map(function (h) {
        var canUndo = h.id && !h.undone && !h.pending && Date.now() - h.recv < 9.5 * 60000;
        return '<div class="it"><span class="badge ' + h.method + '">' + (METHOD[h.method] || h.method) + '</span><div class="g"><b>' + money(h.amount) + '</b><span>' + (h.ticket ? 'n° ' + h.ticket + ' · ' : '') + new Date(h.at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) + (h.undone ? ' · annulée' : '') + '</span></div>'
          + (h.pending ? '<span class="badge wait">en attente</span>' : '') + (h.receipt ? '<button class="btn btn-sm" data-rcpt="' + h.id + '">Ticket</button>' : '') + (canUndo ? '<button class="btn btn-sm btn-red" data-undo="' + h.id + '">Annuler</button>' : (h.id && !h.undone && !h.pending && h.amount > 0 ? '<button class="btn btn-sm" data-rfd="' + h.id + '">Rembourser</button>' : '')) + '</div>';
      }).join('');
      sheet('<h2>Dernières ventes</h2><div class="list">' + (rows || '<p class="muted">Aucune vente sur cet appareil.</p>') + '</div><p class="muted" style="margin:0;font-size:14px">Annulation possible pendant 10 minutes (le cashless est recrédité, le stock remis).</p><button class="btn" data-close>Fermer</button>');
      $$('[data-rcpt]').forEach(function (b) { b.onclick = function () { var h = S.history.filter(function (x) { return x.id === +b.dataset.rcpt; })[0]; if (!h) return; sheet('<div class="receipt-box"><img class="receipt-qr" src="' + esc(h.receipt) + '/qr" alt=""><div><b>Ticket n° ' + h.ticket + '</b><p class="muted">' + money(h.amount) + ' · ' + (METHOD[h.method] || '') + '</p><div class="row"><input class="input-big" id="rc-mail" type="email" placeholder="E-mail du client"><button class="btn btn-primary" id="rc-send" style="flex:0 0 auto">Envoyer</button></div></div></div><button class="btn" data-close>Fermer</button>');
        $('#rc-send').onclick = function () { var m = $('#rc-mail').value.trim(); if (m) api('receipt', { sale_id: h.id, email: m }).then(function () { toast('Ticket envoyé.', 'ok'); closeSheet(); }, function (e) { toast(e.message, 'ko'); }); }; }; });
      $$('[data-rfd]').forEach(function (b) { b.onclick = function () { MENU.refund(+b.dataset.rfd); }; });
      $$('[data-undo]').forEach(function (b) {
        b.onclick = function () {
          var id = parseInt(b.dataset.undo, 10);
          withPerm('undo', 'Annuler une vente', function (approver) {
            api('undo', { sale_id: id, approver_pin: approver || undefined }).then(function (j) {
              S.history.forEach(function (h) { if (h.id === id) h.undone = true; }); save();
              toast('Vente annulée' + (j.balance != null ? ' · solde ' + money(j.balance) : '') + '.', 'ok'); MENU.history();
            }, function (e) { toast(e.message, 'ko'); });
          });
        };
      });
    },
    session: function () {
      sheet('<h2>Caisse</h2><p class="muted" style="margin:0">Chargement…</p>');
      api('session').then(function (j) {
        var s = j.session;
        if (!s) { sheet('<h2>Caisse</h2><p class="muted">La caisse de ce point de vente est fermée.</p><button class="btn btn-primary" id="se-open">Ouvrir la caisse</button><button class="btn" data-close>Fermer</button>'); $('#se-open').onclick = function () { closeSheet(); openSessionScreen(); }; return; }
        var m = function (k) { return (s.methods[k] || { total: 0, n: 0 }); };
        var prods = Object.keys(s.products).slice(0, 12).map(function (k) { return '<div class="it"><div class="g">' + esc(k) + '</div><span class="muted">× ' + s.products[k].qty + '</span><b style="min-width:80px;text-align:right">' + money(s.products[k].total) + '</b></div>'; }).join('');
        var tabsSum = (s.tabs || []).reduce(function (t, x) { return t + x.total; }, 0);
        sheet('<h2>Caisse · ' + esc((bar() || {}).name || '') + ' <span class="x-tag">X</span></h2><p class="muted" style="margin:0">Ouverte ' + new Date(s.opened_at.replace(' ', 'T')).toLocaleString('fr-FR', { weekday: 'short', hour: '2-digit', minute: '2-digit' }) + ' · ' + s.count + ' vente(s)' + (s.opener ? ' · par ' + esc(s.opener) : '') + '</p>' + (s.devices && s.devices.length > 1 ? '<p class="muted" style="margin:0">Caisse partagée par ' + s.devices.map(esc).join(', ') + '</p>' : '')
          + '<div class="sum-grid"><div><span>Cashless</span><b>' + money(m('cashless').total) + '</b></div><div><span>Carte</span><b>' + money(m('card').total) + '</b></div><div><span>Espèces</span><b>' + money(m('cash').total) + '</b></div><div><span>Offert</span><b>' + money(s.offered) + '</b></div><div><span>Fond</span><b>' + money(s.float) + '</b></div><div><span>En banque</span><b>' + money(s.moves.drop) + '</b></div></div>'
          + '<div class="change"><span>Espèces attendues dans le tiroir</span><b>' + money(s.expected_cash) + '</b></div>'
          + '<div class="row"><button class="btn" id="se-drop">Remise en banque</button><button class="btn" id="se-add">Apport</button></div>'
          + (s.tabs && s.tabs.length ? '<div class="change warn"><span>' + s.tabs.length + ' addition(s) ouverte(s), pas encore encaissée(s)</span><b>' + money(tabsSum) + '</b></div>' : '')
          + (s.tips || s.refunds ? '<p class="muted" style="margin:0">' + (s.tips ? 'Pourboires ' + money(s.tips) : '') + (s.tips && s.refunds ? ' · ' : '') + (s.refunds ? 'Tickets remboursés ' + money(s.refunds) : '') + '</p>' : '')
          + staffTable(s.staff)
          + '<details><summary class="linklike">Articles vendus</summary><div class="list">' + (prods || '<p class="muted">Aucun.</p>') + '</div></details>'
          + '<button class="btn btn-primary" id="se-close">Clôturer la caisse du point de vente (Z)</button><button class="btn" data-close>Fermer</button>');
        var move = function (kind) {
          withPerm('cash', kind === 'drop' ? 'Remise en banque' : 'Apport d\'espèces', function (approver) {
            countSheet(kind === 'drop' ? 'Remise en banque' : 'Apport d\'espèces', kind === 'drop' ? 'Espèces retirées du tiroir et mises en lieu sûr.' : 'Espèces ajoutées dans le tiroir (monnaie).', function (amt, cnt, note) {
              if (!amt) return toast('Indique un montant.');
              api('cash_move', { kind: kind, amount: amt, count: cnt || undefined, note: note, approver_pin: approver || undefined }).then(function () { toast(kind === 'drop' ? 'Remise en banque enregistrée.' : 'Apport enregistré.', 'ok'); MENU.session(); }, function (e) { toast(e.message, 'ko'); });
            });
          });
        };
        $('#se-drop').onclick = function () { move('drop'); }; $('#se-add').onclick = function () { move('add'); };
        $('#se-close').onclick = function () {
          withPerm('close', 'Clôturer la caisse', function (approver) {
            var blind = C().settings.blind_close;
            countSheet('Clôture de caisse (Z)', 'Clôture pour tous les appareils de ce point de vente. Compte les espèces du tiroir' + (blind ? ' : le montant attendu s\'affiche après la validation.' : ' (attendu : ' + money(s.expected_cash) + ').') + (s.tabs && s.tabs.length ? ' ' + s.tabs.length + ' addition(s) restent ouvertes.' : ''), function (amt, cnt, note) {
              api('session_close', { counted: amt, count: cnt || undefined, note: note, approver_pin: approver || undefined }).then(function (r) {
                var gap = r.counted - r.totals.expected_cash;
                S.config.session = null; S.shift.since = new Date().toISOString(); save();
                sheet(resultView(gap === 0, 'Caisse clôturée · Z' + r.z, 'Attendu ' + money(r.totals.expected_cash) + ' · compté ' + money(r.counted) + '<br>' + (gap === 0 ? 'Aucun écart.' : 'Écart ' + (gap > 0 ? '+' : '') + money(gap)) + (r.open_tabs ? '<br>' + r.open_tabs + ' addition(s) restent ouvertes' : ''), '<button class="btn btn-primary btn-xl" id="z-next">Ouvrir une nouvelle caisse</button><button class="btn" id="z-end">Terminer mon service</button>'));
                $('#z-next').onclick = function () { closeSheet(); openSessionScreen(); };
                $('#z-end').onclick = function () { closeSheet(); S.shift = { bar_id: S.shift.bar_id, mode: S.shift.mode }; save(); startShift(); };
              }, function (e) { toast(e.message, 'ko'); });
            }, { required: true, ok: 'Clôturer' });
          });
        };
      }, function (e) { sheet(resultView(false, 'Indisponible', esc(e.message), '<button class="btn" data-close>Fermer</button>')); });
    },
    refund: function (saleId) {
      var go = function (q) { api('sale_get', q).then(function (j) { refundSheet(j.sale); }, function (e) { toast(e.message, 'ko'); }); };
      if (saleId) return go({ sale_id: saleId });
      sheet('<h2>Rembourser un ticket</h2><p class="muted" style="margin:0">Numéro du ticket, sur le reçu du client ou dans « Dernières ventes ».</p><input class="input-big" id="rf-t" inputmode="numeric" placeholder="N° de ticket" autofocus><button class="btn btn-primary btn-xl" id="rf-go">Rechercher</button><button class="btn" data-close>Annuler</button>');
      $('#rf-go').onclick = function () { var v = parseInt($('#rf-t').value, 10); if (v) go({ ticket: v }); };
    },
    stock: function () {
      sheet('<h2>Stock du bar</h2><p class="muted" style="margin:0">Chargement…</p>');
      api('stock').then(function (j) { stockSheet(j.products); }, function (e) { sheet(resultView(false, 'Indisponible', esc(e.message), '<button class="btn" data-close>Fermer</button>')); });
    },
    lookup: function () {
      scanSheet('Solde d\'un billet', 'Code du billet', function (who) {
        api('lookup', who).then(function (j) { sheet(resultView(true, money(j.balance), (j.name ? esc(j.name) + ' · ' : '') + (j.nfc ? 'bracelet associé' : 'sans bracelet'), '<button class="btn btn-primary btn-xl" data-close>Fermer</button>')); },
          function (e) { sheet(resultView(false, 'Introuvable', esc(e.message), '<button class="btn" data-close>Fermer</button>')); });
      });
    },
    credit: function () {
      scanSheet('Crédit d\'un badge', 'Code du badge', function (who) {
        api('allowance', who).then(function (j) { sheet(resultView(true, j.label, esc(j.holder) + (j.group ? ' · ' + esc(j.group) : '') + '<br>restant aujourd\'hui', '<button class="btn btn-primary btn-xl" data-close>Fermer</button>')); },
          function (e) { sheet(resultView(false, 'Introuvable', esc(e.message), '<button class="btn" data-close>Fermer</button>')); });
      });
    },
    link: function () {
      if (!NFC.available()) { sheet(resultView(false, 'NFC indisponible', 'Les bracelets se lisent avec l\'application Android Entrevo Caisse, ou Chrome sur Android.', '<button class="btn" data-close>Fermer</button>')); return; }
      var stop = function () {};
      sheet('<h2>Associer un bracelet</h2><p class="muted" style="margin:0">1. Scanne le QR code du billet.</p><div class="cam"><video muted></video></div><div class="row"><input class="input-big" id="ln-code" placeholder="Code du billet"><button class="btn btn-primary" id="ln-ok" style="flex:0 0 auto">OK</button></div><button class="btn" data-close>Annuler</button>', function () { stop(); NFC.stop(); });
      var step2 = function (code) {
        stop();
        $('#sheet-in').innerHTML = '<h2>Associer un bracelet</h2><div class="hint nfc-pulse"><svg viewBox="0 0 24 24"><path d="M6 8.5a8 8 0 0 1 0 7M9.5 6a12 12 0 0 1 0 12M13 4a15 15 0 0 1 0 16"/></svg>2. Approche le bracelet de l\'appareil</div><button class="btn" data-close>Annuler</button>';
        NFC.start(function (u) {
          NFC.stop();
          api('link_nfc', { code: code, nfc: u }).then(function (j) { $('#sheet-in').innerHTML = resultView(true, 'Bracelet associé', (j.name ? esc(j.name) + ' · ' : '') + 'solde ' + money(j.balance), '<div class="row"><button class="btn" data-close>Fermer</button><button class="btn btn-primary" id="ln-next">Bracelet suivant</button></div>'); $('#ln-next').onclick = MENU.link; },
            function (e) { $('#sheet-in').innerHTML = resultView(false, 'Association impossible', esc(e.message), '<div class="row"><button class="btn" data-close>Fermer</button><button class="btn btn-primary" id="ln-next">Recommencer</button></div>'); $('#ln-next').onclick = MENU.link; });
        }).catch(function (e) { toast(e.message, 'ko'); });
      };
      stop = startCam($('#sheet video'), step2);
      $('#ln-ok').onclick = function () { var v = $('#ln-code').value.trim(); if (v) step2(v); };
    },
    lock: function () { if (S.cart.items.length && !S.cart.tab && C().settings.tabs && S.online && confirm('Mettre le panier en cours en attente dans une addition avant de changer de serveur ?')) return holdCart(); if (S.cart.items.length && !confirm('Le panier en cours sera vidé. Continuer ?')) return; emptyCart(); if (S.shift) { S.shift.staff = null; S.shift.name = ''; } save(); startShift(true); },
    shift: function () { if (S.cart.items.length && !confirm('Le panier en cours sera vidé. Continuer ?')) return; emptyCart(); closeSheet(); openStation(); },
    refresh: function () { api('config').then(function (j) { S.config = j.config; save(); openMain(); toast('Carte à jour.', 'ok'); }, function (e) { toast(e.message, 'ko'); }); },
    unpair: function () {
      if (S.queue.length && !confirm(S.queue.length + ' vente(s) ne sont pas encore envoyées et seront perdues. Déconnecter quand même ?')) return;
      if (!S.queue.length && !confirm('Déconnecter cet appareil ? Il faudra un nouveau code pour le réutiliser.')) return;
      S.queue = []; save(); unpair(false);
    }
  };
  function amountSheet(title, sub, cb, required, preset) {
    var v = preset ? String(preset) : '';
    sheet('<h2>' + esc(title) + '</h2><p class="muted" style="margin:0">' + esc(sub) + '</p><div class="big" id="am-v">' + money(preset || 0) + '</div><div class="keys" id="am-keys"></div><input class="input-big" id="am-note" placeholder="Note (facultatif)"><button class="btn btn-primary btn-xl" id="am-ok">Valider</button><button class="btn" data-close>Annuler</button>');
    keypad($('#am-keys'), ['1', '2', '3', '4', '5', '6', '7', '8', '9', '00', '0', '⌫'], function (k) { v = k === '⌫' ? v.slice(0, -1) : (v + k).replace(/^0+/, '').slice(0, 8); $('#am-v').textContent = money(v ? parseInt(v, 10) : 0); });
    $('#am-ok').onclick = function () { var a = v ? parseInt(v, 10) : 0; if (!a && !required) return; cb(a, $('#am-note').value.trim()); };
  }

  // ---------- écran toujours allumé ----------
  var lock = null;
  function keepAwake() { try { if ('wakeLock' in navigator && !lock) navigator.wakeLock.request('screen').then(function (l) { lock = l; l.addEventListener('release', function () { lock = null; }); }, function () {}); } catch (e) { /* rien */ } }

  // ---------- évènements ----------
  document.addEventListener('click', function (e) {
    var t = e.target.closest('button, .sheet-bg, .line'); if (!t) return;
    if (t.id === 'sheet-bg') { closeDrawer(); if (!$('#sheet').hidden && !$('.result.ok')) return; closeSheet(); return; }
    if (t.hasAttribute('data-close')) return closeSheet();
    if (t.dataset.prod) return tapProduct(parseInt(t.dataset.prod, 10));
    if (t.dataset.q) { var it = S.cart.items.filter(function (x) { return x.key === t.dataset.key; })[0]; if (it) { it.qty += parseInt(t.dataset.q, 10); S.cart.items = S.cart.items.filter(function (x) { return x.qty > 0; }); buzz(6); renderGrid(); renderCart(); } return; }
    if (t.dataset.rq) { var d = +t.dataset.dep; S.cart.returns[d] = Math.max(0, (S.cart.returns[d] || 0) + parseInt(t.dataset.rq, 10)); if (!S.cart.returns[d]) delete S.cart.returns[d]; renderCart(); return; }
    if (t.classList.contains('line') && t.dataset.line) return lineMenu(t.dataset.line);
    if (t.dataset.free) return freeAmount();
    if (t.dataset.cat !== undefined && t.closest('#cats')) { S.cat = parseInt(t.dataset.cat, 10); S.q = ''; if ($('#pos-q')) $('#pos-q').value = ''; $$('#cats button').forEach(function (b) { b.classList.toggle('on', b === t); }); return renderGrid(); }
    if (t.dataset.pay) { var q = quoteLocal(); if (!q.lines.length) return; return { cashless: function () { scanPay('cashless'); }, card: payCard, cash: payCash, allowance: function () { scanPay('allowance'); }, offered: payOffered, split: paySplit }[t.dataset.pay](); }
    if (t.dataset.amt && t.closest('#tp-amounts')) { TOP.amount = +t.dataset.amt; topAmount(); return; }
    if (t.dataset.prep) return prepTap(+t.dataset.prep, t.dataset.st);
    if (t.dataset.stbar !== undefined) { ST.bar = parseInt(t.dataset.stbar, 10); buzz(6); return S.renderStation(); }
    if (t.dataset.strole) { ST.mode = t.dataset.strole; buzz(6); return S.renderStation(); }
    if (t.dataset.menu) { closeDrawer(); return MENU[t.dataset.menu](); }
  });
  $('#menu-btn').onclick = openDrawer;
  $$('[data-open-menu]').forEach(function (b) { b.onclick = openDrawer; });
  $('#tp-cash').onclick = function () { topPay('cash'); };
  $('#tp-card').onclick = function () { topPay('card'); };
  $('#tp-cashout').onclick = topCashout;
  $('#tp-reset').onclick = topReset;
  $('#tp-ok').onclick = function () { var v = $('#tp-code').value.trim(); if (v) { topStop(); NFC.stop(); topIdentify({ code: v }); } };
  $('#tp-other').onclick = function () { amountSheet('Autre montant', 'Montant de la recharge', function (a) { closeSheet(); TOP.amount = a; topAmount(); }); };
  $('#staff-pill').onclick = function () { if (C().settings.require_staff) MENU.lock(); };
  $('#t-return').onclick = returnFlow;
  $('#t-discount').onclick = function () { if (!S.cart.items.length && !S.cart.discount) return toast('Ajoute d\'abord des produits.'); discountFlow(); };
  $('#pay-more').onclick = payMore;
  $('#t-hold').onclick = holdCart;
  $('#tabs-btn').onclick = openTabs;
  $('#cart-clear').onclick = function () {
    if (S.cart.tab) { if (confirm('Fermer l\'addition « ' + S.cart.tab.label + ' » sans l\'encaisser ? Elle reste ouverte (les ajouts non mis en attente sont perdus).')) { emptyCart(); renderGrid(); renderCart(); } return; }
    if ((S.cart.items.length || Object.keys(S.cart.returns).length) && confirm('Vider le panier ?')) { emptyCart(); renderGrid(); renderCart(); }
  };
  $('#pair-go').onclick = function () { var p = parsePair($('#pair-code').value); if (!p) { $('#pair-err').textContent = 'Le code fait 6 chiffres.'; return; } pair(p.code, p.server); };
  $('#pair-code').oninput = function () { this.value = this.value.replace(/\D/g, '').slice(0, 6); if (this.value.length === 6 && (!NATIVE || $('#pair-server').value)) $('#pair-go').click(); };
  $('#pair-scan').onclick = function () {
    var stop = function () {};
    sheet('<h2>Scanner le QR d\'appairage</h2><div class="cam"><video muted></video></div><button class="btn" data-close>Annuler</button>', function () { stop(); });
    stop = startCam($('#sheet video'), function (txt) {
      var p = parsePair(txt); closeSheet();
      if (!p) { $('#pair-err').textContent = 'Ce QR code n\'est pas un code d\'appairage Entrevo Caisse.'; return; }
      if (p.server && NATIVE) $('#pair-server').value = p.server;
      $('#pair-code').value = p.code; pair(p.code, p.server);
    });
  };
  $('#shift-go').onclick = function () { signedIn(null, $('#shift-staff').value.trim().slice(0, 40)); };
  $('#st-go').onclick = function () { if (ST.bar === null || !ST.mode) return; saveStation(ST.bar, ST.mode); };
  window.addEventListener('online', function () { setOnline(true); sync(); });
  window.addEventListener('offline', function () { setOnline(false); });
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { keepAwake(); sync(); } });
  setInterval(function () { if (S.token) { if (S.queue.length) sync(); else if (!S.online) api('ping').then(function (j) { S.config = j.config; save(); }, function () {}); } }, 20000);
  setInterval(function () { if (S.token && S.online && $('#sheet').hidden && !$('#s-pos').hidden) api('config').then(function (j) { S.config = j.config; save(); renderGrid(); renderCart(); }, function () {}); }, 300000);
  setInterval(function () { if (!$('#s-pos').hidden && $('#sheet').hidden) { renderGrid(); renderCart(); } }, 60000);
  setInterval(function () { if (!$('#s-pos').hidden && S.online) refreshTabs(); }, 20000); // additions ouvertes sur les autres appareils
  // 3.0 : battement de cœur pour le parc de terminaux (batterie, version, ventes en attente)
  var batt = null;
  try { if (navigator.getBattery) navigator.getBattery().then(function (b) { batt = b; }); } catch (e) { /* non disponible */ }
  var beat = function () { if (!S.token || !S.online) return; api('beat', { app_version: APP_V, battery: batt ? Math.round(batt.level * 100) : undefined, charging: batt ? batt.charging : undefined, queue: S.queue.length }, 8000).then(function () {}, function () {}); };
  setTimeout(beat, 3000); setInterval(beat, 30000); // happy hours qui commencent ou finissent

  // ---------- démarrage ----------
  if (NATIVE) $('#pair-server-row').hidden = false;
  if (!NATIVE && 'serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(function () { /* facultatif */ });
  var hp = parsePair(location.href);
  if (hp && /#pair=/.test(location.hash)) { show('s-pair'); $('#pair-code').value = hp.code; pair(hp.code, hp.server); return; }
  if (!S.token || !S.config) return show('s-pair');
  var resume = function () {
    var sh = S.shift, mode = sh && sh.mode;
    if (!$('#sheet').hidden) return; // ne pas couper une action en cours
    if (mode === 'prep' || mode === 'display') return openMain(); // un écran reste sur son poste
    if (!sh || (!sh.staff && !sh.name && !C().settings.require_staff && !mode)) return startShift();
    if (C().settings.require_staff && !staff()) return startShift(!!mode);
    if (!mode) return openStation();
    if (!$('#s-pos').hidden || !$('#s-topup').hidden) { if (C().settings.sessions && !C().session) return openSessionScreen(); return; }
    enterStation();
  };
  if (S.config.settings) resume(); else show('s-pair');
  api('config').then(function (j) { S.config = j.config; save(); resume(); }, function () { /* hors ligne : configuration en cache */ });
  sync();
})();
