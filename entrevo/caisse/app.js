/* Entrevo Caisse — application de bar : cashless (QR du billet ou bracelet NFC), carte bancaire (TPE autonome ou Nepting), espèces.
   Fonctionne en web app (dossier /caisse/ de la billetterie) et dans l'application native (Capacitor), qui ajoute le NFC et le TPE Nepting. */
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

  var S = {
    server: NATIVE ? LS.get('server', '') : webBase(),
    token: LS.get('token', ''),
    config: LS.get('config', null),
    shift: LS.get('shift', null),
    queue: LS.get('queue', []),
    history: LS.get('history', []),
    cart: [], cat: null, online: navigator.onLine
  };
  var save = function () { LS.set('queue', S.queue); LS.set('history', S.history.slice(0, 60)); LS.set('shift', S.shift); LS.set('config', S.config); };

  // ---------- API ----------
  function api(action, data, timeout) {
    var ctl = window.AbortController ? new AbortController() : null;
    var t = setTimeout(function () { ctl && ctl.abort(); }, timeout || 15000);
    var body = Object.assign({ action: action }, data || {});
    return fetch(S.server + '/api/caisse', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Pos-Token': S.token || '' }, body: JSON.stringify(body), signal: ctl ? ctl.signal : undefined, credentials: 'omit' })
      .then(function (r) {
        clearTimeout(t);
        return r.json().catch(function () { return { ok: false, error: 'Réponse illisible du serveur.' }; }).then(function (j) {
          if (r.status === 401) { unpair(true); }
          if (!j.ok) { var e = new Error(j.error || 'Erreur'); e.server = true; e.status = r.status; throw e; }
          setOnline(true);
          return j;
        });
      }, function (err) { clearTimeout(t); setOnline(false); var e = new Error('Pas de réseau.'); e.network = true; throw e; });
  }

  // ---------- écrans ----------
  function show(id) { $$('.screen').forEach(function (s) { s.hidden = s.id !== id; }); }
  function toast(msg, kind) {
    var t = $('#toast'); t.textContent = msg; t.className = 'toast ' + (kind || ''); t.hidden = false;
    clearTimeout(toast.t); toast.t = setTimeout(function () { t.hidden = true; }, 2600);
  }
  function setOnline(v) {
    S.online = v;
    var n = $('#net'); if (!n) return;
    n.classList.toggle('off', !v);
    var q = S.queue.length;
    $('span', n).textContent = v ? (q ? 'Envoi de ' + q + ' vente(s)…' : 'En ligne') : 'Hors ligne' + (q ? ' · ' + q + ' en attente' : '');
    $$('[data-pay="cashless"]').forEach(function (b) { b.title = v ? '' : 'Le cashless a besoin du réseau'; });
    renderCart();
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
    $('#pair-go').disabled = true;
    S.token = '';
    api('pair', { code: code, platform: NATIVE ? (CAP.getPlatform ? CAP.getPlatform() : 'native') : 'web' }).then(function (j) {
      S.token = j.token; LS.set('token', j.token); S.config = j.config; S.shift = null; S.history = []; save();
      if (location.hash) history.replaceState(null, '', location.pathname);
      buzz(40); startShift();
    }, function (e) { err.textContent = e.message; }).then(function () { $('#pair-go').disabled = false; });
  }
  function unpair(expired) {
    S.token = ''; S.config = null; S.shift = null; LS.del('token'); LS.del('config'); LS.del('shift');
    closeSheet(); closeDrawer();
    $('#pair-err').textContent = expired ? 'Cet appareil a été déconnecté par l\'organisateur. Demande un nouveau code.' : '';
    show('s-pair');
  }

  // ---------- début de service ----------
  function startShift() {
    var c = S.config; if (!c) return show('s-pair');
    $('#shift-event').textContent = c.event.name;
    var fixed = c.device.bar_id;
    $('#shift-bar-row').hidden = !!fixed;
    var sel = fixed || (S.shift && S.shift.bar_id) || (c.bars.length === 1 ? c.bars[0].id : null);
    $('#shift-bars').innerHTML = c.bars.map(function (b) { return '<button class="chip' + (b.id === sel ? ' on' : '') + '" data-bar="' + b.id + '">' + esc(b.name) + '</button>'; }).join('') || '<p class="muted">Aucun bar ouvert : l\'organisateur doit en créer un.</p>';
    $('#shift-staff').value = (S.shift && S.shift.staff) || '';
    $('#shift-go').disabled = !sel;
    $('#shift-go').dataset.bar = sel || '';
    show('s-shift');
  }

  // ---------- caisse ----------
  function bar() { var id = S.config.device.bar_id || (S.shift && S.shift.bar_id); return S.config.bars.filter(function (b) { return b.id === id; })[0] || null; }
  function products() { var b = bar(); return S.config.products.filter(function (p) { return !p.bars.length || (b && p.bars.indexOf(b.id) >= 0); }); }
  function openPos() {
    var c = S.config, b = bar();
    if (!b) return startShift();
    $('#pos-bar').textContent = b.name;
    $('#pos-event').textContent = c.event.name + (S.shift && S.shift.staff ? ' · ' + S.shift.staff : '');
    if (c.org && c.org.accent) document.documentElement.style.setProperty('--accent', c.org.accent);
    var cats = []; products().forEach(function (p) { if (cats.indexOf(p.category) < 0) cats.push(p.category); });
    if (cats.indexOf(S.cat) < 0) S.cat = cats[0] || null;
    $('#cats').innerHTML = cats.length > 1 ? cats.map(function (k) { return '<button data-cat="' + esc(k) + '" class="' + (k === S.cat ? 'on' : '') + '">' + esc(k) + '</button>'; }).join('') : '';
    $('#cats').hidden = cats.length < 2;
    $$('[data-pay]').forEach(function (b) { b.hidden = c.methods.indexOf(b.dataset.pay) < 0; });
    renderGrid(); renderCart(); setOnline(S.online); show('s-pos'); keepAwake();
  }
  function renderGrid() {
    var list = products().filter(function (p) { return !S.cat || p.category === S.cat; });
    $('#grid').innerHTML = list.map(function (p) {
      var q = S.cart.filter(function (l) { return l.id === p.id; })[0];
      return '<button class="prod" data-prod="' + p.id + '"><b>' + esc(p.name) + '</b><span>' + money(p.price + p.deposit) + '</span>' + (p.deposit ? '<small>dont consigne ' + money(p.deposit) + '</small>' : '') + (q ? '<em>' + q.qty + '</em>' : '') + '</button>';
    }).join('') || '<p class="muted">Aucun produit pour ce bar. L\'organisateur les ajoute dans Cashless &amp; bars.</p>';
  }
  function total() { return S.cart.reduce(function (t, l) { return t + l.qty * l.unit; }, 0); }
  function renderCart() {
    var el = $('#cart-lines'); if (!el) return;
    el.innerHTML = S.cart.length ? S.cart.map(function (l) {
      return '<div class="line"><div class="line-name"><b>' + esc(l.name) + '</b><span>' + money(l.unit) + '</span></div><div class="qty"><button data-q="-1" data-id="' + l.id + '" aria-label="Moins">−</button><output>' + l.qty + '</output><button data-q="1" data-id="' + l.id + '" aria-label="Plus">+</button></div><div class="line-sum">' + money(l.qty * l.unit) + '</div></div>';
    }).join('') : '<p class="cart-empty">Touche un produit pour l\'ajouter.</p>';
    $('#cart-total').textContent = money(total());
    var empty = total() <= 0;
    $$('[data-pay]').forEach(function (b) { b.disabled = empty || (b.dataset.pay === 'cashless' && !S.online); });
  }
  function addProduct(id, n) {
    var p = S.config.products.filter(function (x) { return x.id === id; })[0]; if (!p) return;
    var l = S.cart.filter(function (x) { return x.id === id; })[0];
    if (!l) { if (n < 0) return; l = { id: id, name: p.name, unit: p.price + p.deposit, qty: 0 }; S.cart.push(l); }
    l.qty += n;
    S.cart = S.cart.filter(function (x) { return x.qty > 0; });
    buzz(8); renderGrid(); renderCart();
  }
  function items() { return S.cart.map(function (l) { return { id: l.id, qty: l.qty, unit: l.unit, name: l.name }; }); }

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

  // ---------- NFC (bracelets) ----------
  var NFC = {
    plugin: function () { return CAP && CAP.Plugins && CAP.Plugins.EntrevoNfc; },
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
    plugin: function () { return CAP && CAP.Plugins && CAP.Plugins.EntrevoTpe; },
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

  // ---------- enregistrement des ventes ----------
  function newSale(method, extra) {
    return Object.assign({ client_id: uid(), method: method, items: items(), bar_id: bar().id, staff: (S.shift && S.shift.staff) || '', at: new Date().toISOString(), amount: total() }, extra || {});
  }
  function remember(sale, srv, pending) {
    S.history.unshift({ client_id: sale.client_id, id: srv ? srv.id : null, method: sale.method, amount: srv ? srv.amount : sale.amount, at: sale.at, pending: !!pending, undone: false, recv: Date.now() });
    S.history = S.history.slice(0, 60); save();
  }
  /** Carte et espèces : enregistrées tout de suite si possible, sinon mises en file (hors ligne). */
  function record(sale) {
    var done = function () { S.cart = []; renderGrid(); renderCart(); };
    if (!S.online) { S.queue.push(sale); remember(sale, null, true); save(); setOnline(false); done(); return Promise.resolve({ queued: true }); }
    return api('sale', sale).then(function (j) { remember(sale, j.sale, false); done(); return j; }, function (e) {
      // l'argent est encaissé : la vente ne doit jamais être perdue
      S.queue.push(Object.assign(sale, { error: e.server ? e.message : null })); remember(sale, null, true); save(); setOnline(S.online); done();
      return { queued: true, error: e.server ? e.message : null };
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

  // ---------- paiements ----------
  function payCashless() {
    var amount = total(), stopCam = function () {}, busy = false;
    var nfcOk = NFC.available();
    sheet('<h2>Cashless · ' + money(amount) + '</h2><div class="cam"><video muted></video></div>'
      + (nfcOk ? '<div class="hint nfc-pulse"><svg viewBox="0 0 24 24"><path d="M6 8.5a8 8 0 0 1 0 7M9.5 6a12 12 0 0 1 0 12M13 4a15 15 0 0 1 0 16"/></svg>Ou approche le bracelet de l\'appareil</div>' : '')
      + '<div class="row"><input class="input-big" id="cl-code" placeholder="Code du billet" autocomplete="off" autocapitalize="characters"><button class="btn btn-primary" id="cl-ok" style="flex:0 0 auto">OK</button></div>'
      + '<button class="btn" data-close>Annuler</button>', function () { stopCam(); NFC.stop(); });
    var go = function (who) {
      if (busy) return; busy = true; stopCam(); NFC.stop();
      var sale = newSale('cashless', who);
      $('#sheet-in').innerHTML = '<div class="result"><p>Paiement en cours…</p></div>';
      api('sale', sale).then(function (j) {
        remember(sale, j.sale, false); S.cart = []; renderGrid(); renderCart(); buzz([30, 40, 30]);
        $('#sheet-in').innerHTML = resultView(true, 'Payé ' + money(j.sale.amount), (j.name ? esc(j.name) + ' · ' : '') + 'nouveau solde <b>' + money(j.balance) + '</b>', '<button class="btn btn-primary btn-xl" data-close>Vente suivante</button>');
        setTimeout(function () { if (!$('#sheet').hidden && $('.result.ok')) closeSheet(); }, 3500);
      }, function (e) {
        buzz([80, 60, 80]);
        $('#sheet-in').innerHTML = resultView(false, 'Paiement refusé', esc(e.message), '<div class="row"><button class="btn" data-close>Fermer</button><button class="btn btn-primary" id="cl-retry">Réessayer</button></div>');
        $('#cl-retry').onclick = payCashless;
      });
    };
    stopCam = startCam($('#sheet video'), function (code) { buzz(30); go({ code: code }); });
    if (nfcOk) NFC.start(function (u) { go({ nfc: u }); }).catch(function () { var h = $('.hint'); if (h) h.remove(); });
    $('#cl-ok').onclick = function () { var v = $('#cl-code').value.trim(); if (v) go({ code: v }); };
    $('#cl-code').onkeydown = function (e) { if (e.key === 'Enter') $('#cl-ok').click(); };
  }

  function payCard() {
    var amount = total(), t = S.config.tpe || { mode: 'manual' };
    var manual = function (note) {
      sheet('<h2>Carte bancaire</h2><div class="big">' + money(amount) + '</div>' + (note ? '<div class="hint">' + note + '</div>' : '') + '<p class="muted" style="text-align:center;margin:0">Encaisse ce montant sur le terminal de paiement, puis confirme quand le paiement est accepté.</p>'
        + '<button class="btn btn-primary btn-xl" id="cd-ok">Paiement accepté</button><button class="btn" data-close>Annuler</button>');
      $('#cd-ok').onclick = function () {
        this.disabled = true;
        record(newSale('card', { payment: { via: 'tpe' } })).then(function (r) { closeSheet(); toast(r.queued ? 'Vente carte gardée : envoi au retour du réseau.' : 'Vente carte enregistrée.', 'ok'); buzz(30); });
      };
    };
    if (t.mode !== 'nepting') return manual('');
    if (!TPE.plugin()) return manual('Le terminal Nepting n\'est piloté que depuis l\'application Android. Encaisse à la main sur le terminal.');
    var sale = newSale('card');
    var run = function () {
      sheet('<h2>Carte bancaire</h2><div class="big">' + money(amount) + '</div><div class="hint nfc-pulse"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3 10h18"/></svg>Le client présente sa carte sur le terminal Nepting…</div>');
      TPE.pay(t, amount, sale.client_id).then(function (pm) {
        sale.payment = pm;
        record(sale).then(function (r) {
          buzz([30, 40, 30]);
          $('#sheet-in').innerHTML = resultView(true, 'Carte acceptée', (pm.auth ? 'Autorisation ' + esc(pm.auth) : '') + (pm.paid !== amount ? '<br>Montant payé : ' + money(pm.paid) : '') + (r.queued ? '<br>Vente gardée, envoi au retour du réseau.' : ''), '<button class="btn btn-primary btn-xl" data-close>Vente suivante</button>');
          setTimeout(function () { if ($('.result.ok')) closeSheet(); }, 3500);
        });
      }, function (e) {
        buzz([80, 60, 80]);
        $('#sheet-in').innerHTML = resultView(false, 'Paiement carte non effectué', esc(e.message), '<div class="row"><button class="btn" data-close>Fermer</button><button class="btn btn-primary" id="cd-retry">Réessayer</button></div><button class="btn btn-ghost" id="cd-manual">Encaisser sans le terminal connecté</button>');
        $('#cd-retry').onclick = run; $('#cd-manual').onclick = function () { manual(''); };
      });
    };
    run();
  }

  function payCash() {
    var amount = total(), given = '';
    var render = function () {
      var g = given === '' ? 0 : parseInt(given, 10), ch = g - amount;
      $('#cs-given').textContent = given === '' ? '—' : money(g);
      var c = $('#cs-change'); c.className = 'change' + (g && ch < 0 ? ' short' : '');
      c.innerHTML = g ? (ch >= 0 ? '<span>À rendre</span><b>' + money(ch) + '</b>' : '<span>Il manque</span><b>' + money(-ch) + '</b>') : '<span>À rendre</span><b>—</b>';
      $('#cs-ok').disabled = g && ch < 0;
    };
    var quick = [amount], notes = [500, 1000, 2000, 5000];
    notes.forEach(function (n) { var v = Math.ceil(amount / n) * n; if (quick.indexOf(v) < 0 && quick.length < 4) quick.push(v); });
    sheet('<h2>Espèces · ' + money(amount) + '</h2><div class="row">' + quick.map(function (v) { return '<button class="btn" data-given="' + v + '">' + (v === amount ? 'Compte juste' : money(v)) + '</button>'; }).join('') + '</div>'
      + '<div class="row" style="align-items:baseline"><span class="muted">Reçu</span><b id="cs-given" style="text-align:right;font-size:24px">—</b></div>'
      + '<div class="keys">' + ['1', '2', '3', '4', '5', '6', '7', '8', '9', '00', '0', '⌫'].map(function (k) { return '<button data-key="' + k + '">' + k + '</button>'; }).join('') + '</div>'
      + '<div class="change" id="cs-change"></div><button class="btn btn-green btn-xl" id="cs-ok">Valider la vente</button><button class="btn" data-close>Annuler</button>');
    $$('[data-given]').forEach(function (b) { b.onclick = function () { given = b.dataset.given; render(); }; });
    $$('[data-key]').forEach(function (b) { b.onclick = function () { var k = b.dataset.key; given = k === '⌫' ? given.slice(0, -1) : (given + k).replace(/^0+/, '').slice(0, 7); render(); }; });
    $('#cs-ok').onclick = function () {
      this.disabled = true;
      var g = given === '' ? amount : parseInt(given, 10);
      record(newSale('cash', { cash_given: g })).then(function (r) {
        $('#sheet-in').innerHTML = resultView(true, 'Rendre ' + money(Math.max(0, g - amount)), 'Vente espèces de ' + money(amount) + (r.queued ? ' gardée, envoi au retour du réseau.' : ' enregistrée.'), '<button class="btn btn-primary btn-xl" data-close>Vente suivante</button>');
        buzz(30);
      });
    };
    render();
  }

  // ---------- menu ----------
  function closeDrawer() { $('#drawer').hidden = true; if ($('#sheet').hidden) $('#sheet-bg').hidden = true; }
  function openDrawer() {
    var c = S.config;
    $('#dr-device').textContent = c.device.name;
    $('#dr-staff').textContent = (bar() ? bar().name : '') + (S.shift && S.shift.staff ? ' · ' + S.shift.staff : '');
    $('#dr-foot').textContent = (NATIVE ? 'Application' : 'Web app') + ' · ' + S.server.replace(/^https?:\/\//, '') + (S.queue.length ? ' · ' + S.queue.length + ' vente(s) en attente' : '');
    $('#drawer').hidden = false; $('#sheet-bg').hidden = false;
  }
  var MENU = {
    history: function () {
      var rows = S.history.slice(0, 40).map(function (h) {
        var canUndo = h.id && !h.undone && !h.pending && Date.now() - h.recv < 9.5 * 60000;
        return '<div class="it"><span class="badge ' + h.method + '">' + { cashless: 'Cashless', card: 'Carte', cash: 'Espèces' }[h.method] + '</span><div class="g"><b>' + money(h.amount) + '</b><span>' + new Date(h.at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) + (h.undone ? ' · annulée' : '') + '</span></div>'
          + (h.pending ? '<span class="badge wait">en attente</span>' : '') + (canUndo ? '<button class="btn btn-sm btn-red" data-undo="' + h.id + '">Annuler</button>' : '') + '</div>';
      }).join('');
      sheet('<h2>Dernières ventes</h2><div class="list">' + (rows || '<p class="muted">Aucune vente sur cet appareil.</p>') + '</div><p class="muted" style="margin:0;font-size:14px">Une vente peut être annulée pendant 10 minutes (erreur de saisie). Le cashless est recrédité.</p><button class="btn" data-close>Fermer</button>');
      $$('[data-undo]').forEach(function (b) {
        b.onclick = function () {
          if (!confirm('Annuler cette vente ?')) return;
          b.disabled = true;
          api('undo', { sale_id: parseInt(b.dataset.undo, 10) }).then(function (j) {
            S.history.forEach(function (h) { if (h.id === parseInt(b.dataset.undo, 10)) h.undone = true; }); save();
            toast('Vente annulée' + (j.balance != null ? ' · solde ' + money(j.balance) : '') + '.', 'ok'); MENU.history();
          }, function (e) { toast(e.message, 'ko'); b.disabled = false; });
        };
      });
    },
    lookup: function () {
      var stop = function () {}, nfcOk = NFC.available();
      sheet('<h2>Solde d\'un billet</h2><div class="cam"><video muted></video></div>' + (nfcOk ? '<div class="hint nfc-pulse"><svg viewBox="0 0 24 24"><path d="M6 8.5a8 8 0 0 1 0 7M9.5 6a12 12 0 0 1 0 12"/></svg>Ou approche le bracelet</div>' : '') + '<div class="row"><input class="input-big" id="lk-code" placeholder="Code du billet"><button class="btn btn-primary" id="lk-ok" style="flex:0 0 auto">OK</button></div><button class="btn" data-close>Fermer</button>', function () { stop(); NFC.stop(); });
      var go = function (who) {
        stop(); NFC.stop();
        api('lookup', who).then(function (j) { $('#sheet-in').innerHTML = resultView(true, money(j.balance), (j.name ? esc(j.name) + ' · ' : '') + (j.nfc ? 'bracelet associé' : 'sans bracelet'), '<button class="btn btn-primary btn-xl" data-close>Fermer</button>'); },
          function (e) { $('#sheet-in').innerHTML = resultView(false, 'Introuvable', esc(e.message), '<button class="btn" data-close>Fermer</button>'); });
      };
      stop = startCam($('#sheet video'), function (c) { go({ code: c }); });
      if (nfcOk) NFC.start(function (u) { go({ nfc: u }); }).catch(function () { /* rien */ });
      $('#lk-ok').onclick = function () { var v = $('#lk-code').value.trim(); if (v) go({ code: v }); };
    },
    link: function () {
      if (!NFC.available()) { sheet(resultView(false, 'NFC indisponible', 'Les bracelets NFC se lisent avec l\'application Android Entrevo Caisse, ou Chrome sur Android.', '<button class="btn" data-close>Fermer</button>')); return; }
      var stop = function () {};
      sheet('<h2>Associer un bracelet</h2><p class="muted" style="margin:0">1. Scanne le QR code du billet.</p><div class="cam"><video muted></video></div><div class="row"><input class="input-big" id="ln-code" placeholder="Code du billet"><button class="btn btn-primary" id="ln-ok" style="flex:0 0 auto">OK</button></div><button class="btn" data-close>Annuler</button>', function () { stop(); NFC.stop(); });
      var step2 = function (code) {
        stop();
        $('#sheet-in').innerHTML = '<h2>Associer un bracelet</h2><div class="hint nfc-pulse"><svg viewBox="0 0 24 24"><path d="M6 8.5a8 8 0 0 1 0 7M9.5 6a12 12 0 0 1 0 12M13 4a15 15 0 0 1 0 16"/></svg>2. Approche maintenant le bracelet de l\'appareil</div><p class="muted" style="margin:0">Billet <b>' + esc(code.replace(/^.*[\/=]/, '')) + '</b></p><button class="btn" data-close>Annuler</button>';
        NFC.start(function (u) {
          NFC.stop();
          api('link_nfc', { code: code, nfc: u }).then(function (j) { $('#sheet-in').innerHTML = resultView(true, 'Bracelet associé', (j.name ? esc(j.name) + ' · ' : '') + 'solde ' + money(j.balance), '<div class="row"><button class="btn" data-close>Fermer</button><button class="btn btn-primary" id="ln-next">Bracelet suivant</button></div>'); $('#ln-next').onclick = MENU.link; },
            function (e) { $('#sheet-in').innerHTML = resultView(false, 'Association impossible', esc(e.message), '<div class="row"><button class="btn" data-close>Fermer</button><button class="btn btn-primary" id="ln-next">Recommencer</button></div>'); $('#ln-next').onclick = MENU.link; });
        }).catch(function (e) { toast(e.message, 'ko'); });
      };
      stop = startCam($('#sheet video'), step2);
      $('#ln-ok').onclick = function () { var v = $('#ln-code').value.trim(); if (v) step2(v); };
    },
    summary: function () {
      sheet('<h2>Récapitulatif de caisse</h2><p class="muted" style="margin:0">Chargement…</p>');
      var since = (S.shift && S.shift.since) || new Date(new Date().setHours(0, 0, 0, 0)).toISOString();
      api('summary', { since: since, bar_id: bar() && bar().id }).then(function (j) {
        var s = j.summary, m = function (k) { return (s.methods[k] || { n: 0, total: 0 }); };
        var pend = S.queue.reduce(function (t, x) { return t + (x.method === 'cash' ? x.amount : 0); }, 0);
        var prods = Object.keys(s.products).map(function (k) { return '<div class="it"><div class="g">' + esc(k) + '</div><span class="muted">× ' + s.products[k].qty + '</span><b style="min-width:80px;text-align:right">' + money(s.products[k].total) + '</b></div>'; }).join('');
        $('#sheet-in').innerHTML = '<h2>Récapitulatif · ' + esc(bar() ? bar().name : '') + '</h2><p class="muted" style="margin:0">Depuis ' + new Date(since).toLocaleString('fr-FR', { weekday: 'short', hour: '2-digit', minute: '2-digit' }) + ' · ' + s.count + ' vente(s)</p>'
          + '<div class="sum-grid"><div><span>Cashless</span><b>' + money(m('cashless').total) + '</b></div><div><span>Carte</span><b>' + money(m('card').total) + '</b></div><div><span>Espèces</span><b>' + money(m('cash').total) + '</b></div></div>'
          + '<div class="change"><span>Espèces attendues dans la caisse</span><b>' + money(m('cash').total + pend) + '</b></div>' + (pend ? '<p class="muted" style="margin:0">Dont ' + money(pend) + ' de ventes pas encore envoyées.</p>' : '')
          + '<div class="list">' + (prods || '<p class="muted">Aucune vente.</p>') + '</div>'
          + '<div class="row"><button class="btn" data-close>Fermer</button><button class="btn btn-primary" id="sm-reset">Clôturer et repartir à zéro</button></div>';
        $('#sm-reset').onclick = function () { if (!confirm('Clôturer ? Le prochain récapitulatif partira de maintenant.')) return; S.shift.since = new Date().toISOString(); save(); closeSheet(); toast('Nouvelle session de caisse.', 'ok'); };
      }, function (e) { $('#sheet-in').innerHTML = resultView(false, 'Récapitulatif indisponible', esc(e.message), '<button class="btn" data-close>Fermer</button>'); });
    },
    shift: function () { closeSheet(); startShift(); },
    refresh: function () { api('config').then(function (j) { S.config = j.config; save(); openPos(); toast('Produits à jour.', 'ok'); }, function (e) { toast(e.message, 'ko'); }); },
    unpair: function () {
      if (S.queue.length && !confirm(S.queue.length + ' vente(s) ne sont pas encore envoyées et seront perdues. Déconnecter quand même ?')) return;
      if (!S.queue.length && !confirm('Déconnecter cet appareil ? Il faudra un nouveau code pour le réutiliser.')) return;
      S.queue = []; save(); unpair(false);
    }
  };

  // ---------- écran toujours allumé ----------
  var lock = null;
  function keepAwake() { try { if ('wakeLock' in navigator && !lock) navigator.wakeLock.request('screen').then(function (l) { lock = l; l.addEventListener('release', function () { lock = null; }); }, function () {}); } catch (e) { /* rien */ } }

  // ---------- évènements ----------
  document.addEventListener('click', function (e) {
    var t = e.target.closest('button, .sheet-bg'); if (!t) return;
    if (t.id === 'sheet-bg') { closeDrawer(); if (!$('#sheet').hidden && !$('.result.ok')) return; closeSheet(); return; }
    if (t.hasAttribute('data-close')) return closeSheet();
    if (t.dataset.prod) return addProduct(parseInt(t.dataset.prod, 10), 1);
    if (t.dataset.q) return addProduct(parseInt(t.dataset.id, 10), parseInt(t.dataset.q, 10));
    if (t.dataset.cat) { S.cat = t.dataset.cat; $$('#cats button').forEach(function (b) { b.classList.toggle('on', b === t); }); return renderGrid(); }
    if (t.dataset.pay) { if (!total()) return; return { cashless: payCashless, card: payCard, cash: payCash }[t.dataset.pay](); }
    if (t.dataset.bar) { $$('#shift-bars .chip').forEach(function (b) { b.classList.toggle('on', b === t); }); $('#shift-go').disabled = false; $('#shift-go').dataset.bar = t.dataset.bar; return; }
    if (t.dataset.menu) { closeDrawer(); return MENU[t.dataset.menu](); }
  });
  $('#menu-btn').onclick = openDrawer;
  $('#cart-clear').onclick = function () { if (S.cart.length && confirm('Vider le panier ?')) { S.cart = []; renderGrid(); renderCart(); } };
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
  $('#shift-go').onclick = function () {
    var b = parseInt(this.dataset.bar, 10); if (!b) return;
    var keep = S.shift && S.shift.bar_id === b;
    S.shift = { bar_id: b, staff: $('#shift-staff').value.trim().slice(0, 40), since: keep ? S.shift.since : new Date().toISOString() };
    save(); openPos();
  };
  window.addEventListener('online', function () { setOnline(true); sync(); });
  window.addEventListener('offline', function () { setOnline(false); });
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { keepAwake(); sync(); } });
  setInterval(function () { if (S.token) { if (S.queue.length) sync(); else if (!S.online) api('ping').then(function (j) { S.config = j.config; save(); }, function () {}); } }, 20000);
  setInterval(function () { if (S.token && S.online && $('#sheet').hidden) api('config').then(function (j) { S.config = j.config; save(); if (!$('#s-pos').hidden) { renderGrid(); } }, function () {}); }, 600000);

  // ---------- démarrage ----------
  if (NATIVE) $('#pair-server-row').hidden = false;
  if (!NATIVE && 'serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(function () { /* facultatif */ });
  var hp = parsePair(location.href);
  if (hp && /#pair=/.test(location.hash)) { show('s-pair'); $('#pair-code').value = hp.code; pair(hp.code, hp.server); return; }
  if (!S.token || !S.config) return show('s-pair');
  if (S.shift) openPos(); else startShift();
  api('config').then(function (j) { S.config = j.config; save(); if (!$('#s-pos').hidden) openPos(); }, function () { /* hors ligne : configuration en cache */ });
  sync();
})();
