/* Entrevo Scan — contrôle d'accès (caméra, douchette, saisie), fonctionne hors ligne. */
(function () {
  'use strict';
  var body = document.body;
  var BASE = body.dataset.base || '';
  var csrf = body.dataset.csrf || '';
  var $ = function (id) { return document.getElementById(id); };

  // ---------- Stockage local (protégé : peut être indisponible) ----------
  var store = {
    get: function (k, def) { try { var v = localStorage.getItem('entrevo:' + k); return v === null ? def : JSON.parse(v); } catch (e) { return def; } },
    set: function (k, v) { try { localStorage.setItem('entrevo:' + k, JSON.stringify(v)); return true; } catch (e) { return false; } },
    del: function (k) { try { localStorage.removeItem('entrevo:' + k); } catch (e) { /* rien */ } }
  };
  var DEVICE = (function () { var d = store.get('device'); if (!d) { d = 'app-' + Math.random().toString(36).slice(2, 10); store.set('device', d); } return d; })();

  var cfg = store.get('cfg', { eventId: null, apId: null, dir: 'in', sound: true, mine: {} });
  if (cfg.awake === undefined) cfg.awake = true;
  if (!cfg.msgSeen) cfg.msgSeen = {};
  var hist = [], live = null;
  var me = null, data = null, queue = [], online = navigator.onLine, busy = false, lastCode = '', lastAt = 0, lastScan = null, conflicts = 0;

  function saveCfg() { store.set('cfg', cfg); }
  function saveData() { if (data) store.set('ev:' + data.event.id, data); }
  function saveQueue() { if (data) store.set('queue:' + data.event.id, queue); updateNet(); }
  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) { var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); });
  }
  function nowSql() {
    var d = new Date(), p = function (n) { return (n < 10 ? '0' : '') + n; };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
  }
  function hhmm(s) { if (!s) return ''; var m = String(s).match(/(\d{2}):(\d{2})/); return m ? m[1] + 'h' + m[2] : s; }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function fold(s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }

  // ---------- Réseau ----------
  function api(method, path, payload, timeout) {
    var ctrl = window.AbortController ? new AbortController() : null;
    var t = setTimeout(function () { if (ctrl) ctrl.abort(); }, timeout || 8000);
    return fetch(BASE + path, {
      method: method, credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined,
      headers: { 'Content-Type': 'application/json', 'X-CSRF': csrf, 'Accept': 'application/json' },
      body: payload ? JSON.stringify(payload) : undefined
    }).then(function (r) {
      clearTimeout(t);
      if (r.status === 401) { var e = new Error('auth'); e.auth = true; throw e; }
      return r.json().then(function (j) { if (!r.ok) { var er = new Error(j.error || ('HTTP ' + r.status)); er.http = r.status; throw er; } setOnline(true); return j; });
    }, function (err) { clearTimeout(t); setOnline(false); throw err; });
  }
  function setOnline(v) { online = v; updateNet(); }
  window.addEventListener('online', function () { setOnline(true); sync(); });
  window.addEventListener('offline', function () { setOnline(false); });
  function updateNet() {
    var n = $('net'); if (!n) return;
    n.classList.toggle('off', !online);
    n.title = online ? 'En ligne' : 'Hors ligne : les passages sont gardés sur le téléphone';
    $('pending').textContent = queue.length ? queue.length + '' : (online ? '' : 'hors ligne');
    var info = $('m-sync-info');
    if (info) info.textContent = (queue.length ? queue.length + ' passage(s) en attente d\'envoi. ' : 'Tout est envoyé ') + (conflicts ? conflicts + ' conflit(s) corrigé(s) par le serveur.' : '') + (data && data.server_time ? ' Dernière mise à jour : ' + hhmm(data.server_time) : '');
  }

  // ---------- Écrans ----------
  function show(id) {
    ['login', 'events', 'setup', 'scan'].forEach(function (s) { $('s-' + s).hidden = s !== id; });
    if (id === 'scan') startCamera(); else { stopCamera(); wake(false); }
  }
  function overlay(id, open) { $('s-' + id).hidden = !open; if (id === 'search' && open) { $('search-input').value = ''; renderSearch(); setTimeout(function () { $('search-input').focus(); }, 50); } if (id === 'history' && open) renderHistory(); if (id === 'stats' && open) { renderStats(); ping(); } }
  document.addEventListener('click', function (e) {
    var go = e.target.closest('[data-go]'), cl = e.target.closest('[data-close]');
    if (go) { var g = go.dataset.go; overlay('menu', false); if (g === 'menu') { updateNet(); overlay('menu', true); } else if (g === 'events') loadEvents(); else if (g === 'setup') openSetup(cfg.eventId); }
    if (cl) overlay(cl.dataset.close, false);
  });

  // ---------- Démarrage ----------
  function boot() {
    if ('serviceWorker' in navigator) navigator.serviceWorker.register(BASE + '/sw.js', { scope: BASE + '/' }).catch(function () { /* facultatif */ });
    if (body.dataset.logged !== '1') { show('login'); return; }
    loadEvents(true);
  }

  function loadEvents(auto) {
    api('GET', '/api/scan/me').then(function (j) {
      me = j; csrf = j.csrf || csrf; store.set('me', j); renderEvents(auto);
    }).catch(function (e) {
      if (e.auth) { store.del('me'); show('login'); return; }
      me = store.get('me'); if (!me) { show('login'); return; }
      renderEvents(auto);
    });
  }

  function renderEvents(auto) {
    $('hello').textContent = 'Salut ' + me.user.name.split(' ')[0] + ' · ' + me.org;
    var list = $('events-list'); list.innerHTML = '';
    me.events.forEach(function (ev) {
      var b = document.createElement('button');
      b.className = 'pick' + (ev.id === cfg.eventId ? ' on' : '');
      var cached = store.get('ev:' + ev.id);
      b.innerHTML = '<b>' + esc(ev.name) + '</b><small>' + esc(ev.date) + ' · ' + ev.sold + ' billets' + (cached ? ' · dispo hors ligne' : '') + '</small>';
      b.onclick = function () { openSetup(ev.id); };
      list.appendChild(b);
    });
    $('events-empty').hidden = me.events.length > 0;
    // reprise directe si un contrôle était en cours
    if (auto && cfg.eventId && store.get('ev:' + cfg.eventId) && cfg.running) { openSetup(cfg.eventId, true); return; }
    show('events');
  }

  // ---------- Préparation ----------
  function openSetup(eventId, resume) {
    if (!eventId) { loadEvents(); return; }
    if (cfg.eventId !== eventId) { cfg.apId = null; }
    cfg.eventId = eventId; saveCfg();
    data = store.get('ev:' + eventId, null);
    queue = store.get('queue:' + eventId, []);
    var evInfo = me && me.events.filter(function (e) { return e.id === eventId; })[0];
    $('setup-title').textContent = (data && data.event.name) || (evInfo && evInfo.name) || '…';
    $('start-btn').disabled = !data;
    renderAps();
    show('setup');
    download(!data).then(function () { if (resume) startScan(); });
  }

  function download(full) {
    var id = cfg.eventId;
    $('dl-text').textContent = full ? 'Téléchargement des billets…' : 'Mise à jour…';
    $('dl-bar').style.width = '30%';
    var since = !full && data ? '?since=' + encodeURIComponent(data.server_time) : '';
    return api('GET', '/api/scan/event/' + id + '/data' + since, null, 30000).then(function (j) {
      merge(j);
      $('dl-bar').style.width = '100%';
      $('dl-text').textContent = '' + Object.keys(data.tickets).length + ' billets sur ce téléphone. Prêt même sans réseau.';
      $('start-btn').disabled = false;
      renderAps();
    }).catch(function (e) {
      if (e.auth) { show('login'); return; }
      $('dl-bar').style.width = data ? '100%' : '0';
      $('dl-text').textContent = data ? 'Pas de réseau : on utilise la liste déjà téléchargée (' + Object.keys(data.tickets).length + ' billets).' : 'Impossible de télécharger les billets. Il faut du réseau la première fois.';
      $('start-btn').disabled = !data;
    });
  }

  function merge(j) {
    if (!data || j.full || !data.tickets) data = { event: j.event, aps: j.access_points, rates: j.rates, tickets: {}, server_time: j.server_time, stats: j.stats };
    data.event = j.event; data.aps = j.access_points; data.rates = j.rates; data.server_time = j.server_time; data.stats = j.stats;
    data.sessions = j.sessions || []; data.pops = j.populations || {}; data.rules = j.rules || {};
    if (j.message !== undefined) showMessage(j.message);
    j.tickets.forEach(function (t) {
      // si un passage local n'est pas encore envoyé, on garde l'état local du billet
      var pendingLocal = queue.some(function (q) { return q.code === t[0]; });
      if (pendingLocal && data.tickets[t[0]]) return;
      data.tickets[t[0]] = { n: t[1], r: t[2], v: t[3], i: t[4], e: t[5], c: t[6], s: t[7] || 0, m: t[8] || 1, d: t[9] || 0, p: t[10] || 0, ty: t[11] || 'ticket', rn: t[12] || '', st: t[13] || '', l: t[14] || null, u: t[15] || null };
    });
    indexNfc();
    saveData();
    renderCounters();
  }

  function renderAps() {
    var list = $('ap-list'); list.innerHTML = '';
    var aps = data ? data.aps : [];
    if (!aps.length) aps = [{ id: null, name: 'Entrée générale', rates: [], active: 1 }];
    if (!aps.some(function (a) { return a.id === cfg.apId; })) cfg.apId = aps[0].id;
    aps.forEach(function (a) {
      var b = document.createElement('button');
      b.className = 'pick' + (a.id === cfg.apId ? ' on' : '');
      var rates = a.rates.length && data ? a.rates.map(function (r) { return data.rates[r] || '?'; }).join(', ') : 'Tous les billets';
      b.innerHTML = '<b>' + esc(a.name) + (a.active ? '' : '') + '</b><small>' + esc(rates) + '</small>';
      b.onclick = function () { cfg.apId = a.id; saveCfg(); renderAps(); };
      list.appendChild(b);
    });
    var sl = document.getElementById('session-list');
    if (sl) {
      var ss = data ? (data.sessions || []) : [];
      document.getElementById('session-box').hidden = !ss.length;
      if (ss.length && !ss.some(function (x) { return x.id === cfg.sessionId; })) {
        var now = Date.now(), best = ss[0];
        ss.forEach(function (x) { if (Math.abs(new Date(x.start.replace(' ', 'T')) - now) < Math.abs(new Date(best.start.replace(' ', 'T')) - now)) best = x; });
        cfg.sessionId = best.id;
      }
      if (!ss.length) cfg.sessionId = null;
      sl.innerHTML = '';
      ss.forEach(function (x) { var b = document.createElement('button'); b.className = 'pick' + (x.id === cfg.sessionId ? ' on' : ''); b.innerHTML = '<b>' + esc(x.label) + '</b>'; b.onclick = function () { cfg.sessionId = x.id; saveCfg(); renderAps(); }; sl.appendChild(b); });
    }
    document.querySelectorAll('.seg button').forEach(function (b) { b.classList.toggle('on', b.dataset.dir === cfg.dir); });
    $('reentry-note').textContent = data ? (data.event.reentry ? 'Sorties et ré-entrées autorisées sur cet événement.' : '1 billet = 1 entrée. Le mode Sortie ne sert que si les ré-entrées sont activées.') : '';
    saveCfg();
  }
  document.querySelectorAll('.seg button').forEach(function (b) { b.onclick = function () { cfg.dir = b.dataset.dir; saveCfg(); renderAps(); }; });
  $('start-btn').onclick = function () { unlockAudio(); startScan(); };

  function currentAp() { return data && data.aps.filter(function (a) { return a.id === cfg.apId; })[0] || null; }

  function startScan() {
    cfg.running = true; saveCfg();
    indexNfc(); if (cfg.linkMode) nfcBar('Mode association : scanne le QR code du billet.');
    var ap = currentAp();
    $('scan-ev').textContent = data.event.name;
    var sess = (data.sessions || []).filter(function (x) { return x.id === cfg.sessionId; })[0];
    $('scan-ap').textContent = (ap ? ap.name : 'Entrée générale') + (sess ? ' · ' + sess.label : '');
    renderDir();
    hist = store.get('hist:' + data.event.id, []);
    if (data.message) showMessage(data.message);
    wake(true);
    setTimeout(ping, 500);
    resetResult();
    renderCounters();
    updateNet();
    show('scan');
  }

  // ---------- Caméra ----------
  var stream = null, raf = null, detector = null, lastFrame = 0, track = null;
  function startCamera() {
    if (stream) return;
    var off = $('cam-off');
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      off.hidden = false; $('cam-start').hidden = true;
      $('cam-msg').textContent = window.isSecureContext ? 'Caméra non disponible sur cet appareil.' : 'La caméra exige une adresse en https://. Utilise la saisie ou une douchette.';
      return;
    }
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false }).then(function (s) {
      stream = s; off.hidden = true;
      var v = $('video'); v.srcObject = s; v.setAttribute('playsinline', ''); v.play();
      track = s.getVideoTracks()[0];
      try { var caps = track.getCapabilities ? track.getCapabilities() : {}; $('torch').hidden = !caps.torch; } catch (e) { /* rien */ }
      if ('BarcodeDetector' in window) {
        try { detector = new window.BarcodeDetector({ formats: ['qr_code'] }); } catch (e) { detector = null; }
      }
      loop();
    }).catch(function (err) {
      off.hidden = false; $('cam-start').hidden = false;
      $('cam-msg').textContent = err && err.name === 'NotAllowedError' ? 'Autorise la caméra dans les réglages du navigateur.' : 'Caméra indisponible.';
    });
  }
  function stopCamera() {
    if (raf) cancelAnimationFrame(raf); raf = null;
    if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
    stream = null; track = null;
  }
  $('cam-start').onclick = function () { unlockAudio(); startCamera(); };
  var torchOn = false;
  $('torch').onclick = function () { if (!track) return; torchOn = !torchOn; track.applyConstraints({ advanced: [{ torch: torchOn }] }).catch(function () { /* rien */ }); };

  function loop(ts) {
    raf = requestAnimationFrame(loop);
    var v = $('video');
    if (!v.videoWidth || busy || (ts && ts - lastFrame < 110)) return;
    lastFrame = ts || 0;
    if (detector) {
      busy = true;
      detector.detect(v).then(function (codes) { busy = false; if (codes.length) handle(codes[0].rawValue, 'camera'); }).catch(function () { busy = false; detector = null; });
      return;
    }
    var c = $('canvas'), w = Math.min(640, v.videoWidth), h = Math.round(v.videoHeight * w / v.videoWidth);
    c.width = w; c.height = h;
    var ctx = c.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(v, 0, 0, w, h);
    var img = ctx.getImageData(0, 0, w, h);
    var r = window.jsQR ? window.jsQR(img.data, w, h, { inversionAttempts: 'dontInvert' }) : null;
    if (r && r.data) handle(r.data, 'camera');
  }

  // ---------- Saisie manuelle et douchette ----------
  $('manual').onsubmit = function (e) { e.preventDefault(); var v = $('manual-input').value; $('manual-input').value = ''; $('manual-input').blur(); if (v.trim()) handle(v, 'manual'); };
  var wedge = '', wedgeAt = 0;
  document.addEventListener('keydown', function (e) {
    if ($('s-scan').hidden || document.activeElement && /INPUT|TEXTAREA/.test(document.activeElement.tagName)) return;
    var now = Date.now();
    if (now - wedgeAt > 120) wedge = '';
    wedgeAt = now;
    if (e.key === 'Enter') { if (wedge.length >= 6) handle(wedge, 'douchette'); wedge = ''; return; }
    if (e.key.length === 1) wedge += e.key;
  });

  // ---------- Validation ----------
  var LABELS = {
    ok: 'Bienvenue !', exit: 'Sortie OK', already: 'Déjà entré', invalid: 'Billet inconnu', forged: 'Code invalide',
    cancelled: 'Billet annulé', wrong_event: 'Autre événement', not_allowed: 'Pas pour cette porte', not_inside: 'Pas entré', closed: 'Porte fermée',
    wrong_session: 'Mauvaise date', max_reached: 'Entrées épuisées', not_today: 'Déjà venu aujourd\'hui'
  };
  function levelOf(r) { return r === 'ok' || r === 'exit' ? 'ok' : (['already', 'not_inside', 'not_today', 'max_reached'].indexOf(r) >= 0 ? 'warn' : 'error'); }
  function normalize(raw) {
    var s = String(raw || '').trim(), m = s.match(/[?&]c=([A-Za-z0-9]+)/);
    if (m) s = m[1];
    return s.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 40);
  }

  function today() { return nowSql().slice(0, 10); }
  function localCheck(code) {
    var t = data.tickets[code], ap = currentAp();
    if (!t) return { result: 'invalid' };
    if (!t.v) return { result: 'cancelled', t: t };
    if (cfg.sessionId && t.s && t.s !== cfg.sessionId) return { result: 'wrong_session', t: t };
    if (ap && !ap.active) return { result: 'closed', t: t };
    var nowS = nowSql();
    if (ap && ((ap.opens && nowS < ap.opens) || (ap.closes && nowS > ap.closes))) return { result: 'closed', t: t };
    var pop = t.p ? data.pops[t.p] : null;
    if (ap && pop && pop.zones.length && pop.zones.indexOf(ap.id) < 0) return { result: 'not_allowed', t: t };
    if (ap && !pop && ap.rates.length && ap.rates.indexOf(t.r) < 0) return { result: 'not_allowed', t: t };
    if (cfg.dir === 'out') return { result: t.i ? 'exit' : 'not_inside', t: t };
    if (t.i) return { result: 'already', t: t };
    if (t.d && !data.event.reentry && t.e > 0 && t.l && String(t.l).slice(0, 10) === today()) return { result: 'not_today', t: t };
    if (!t.d && !data.event.reentry && t.e >= (t.m || 1)) return { result: (t.m || 1) > 1 ? 'max_reached' : 'already', t: t };
    return { result: 'ok', t: t };
  }
  function applyLocal(code, result) {
    var t = data.tickets[code]; if (!t) return;
    if (result === 'ok') { t.i = (data.event.reentry || ((t.m || 1) === 1 && !t.d)) ? 1 : 0; t.e = (t.e || 0) + 1; if (!t.c) t.c = nowSql(); t.l = nowSql(); }
    if (result === 'exit') t.i = 0;
    saveData();
  }

  // ---------- 4.3 : bracelets NFC ----------
  var nfcIdx = {}, linkCode = null, nfcReader = null;
  function indexNfc() { nfcIdx = {}; if (data) Object.keys(data.tickets).forEach(function (c) { var u = data.tickets[c].u; if (u) nfcIdx[u] = c; }); }
  function nfcBar(text) { $('nfc-bar').hidden = !text; $('nfc-text').textContent = text || ''; }
  function onNfc(uid) {
    uid = String(uid || '').replace(/[^0-9a-fA-F]/g, '').toUpperCase();
    if (uid.length < 6 || !data) return;
    if (cfg.linkMode) {
      if (!linkCode) { nfcBar('Scanne d\u2019abord le billet, puis approche le bracelet.'); beep(300, 0.15); return; }
      var code = linkCode; nfcBar('Association…');
      api('POST', '/api/scan/event/' + data.event.id + '/nfc', { code: code, uid: uid }).then(function (j) {
        Object.keys(data.tickets).forEach(function (c) { if (data.tickets[c].u === uid) data.tickets[c].u = null; });
        if (data.tickets[code]) data.tickets[code].u = uid; indexNfc(); saveData();
        linkCode = null; beep(1320, 0.12); vibrate(60);
        nfcBar('Bracelet associé à ' + j.name + (j.replaced ? ' (retiré de ' + j.replaced + ')' : '') + '. Billet suivant ?');
      }).catch(function (e) { linkCode = null; beep(220, 0.3); nfcBar(e.http ? (e.message || 'Association refusée') : 'Pas de réseau : l\u2019association demande une connexion.'); });
      return;
    }
    handle('NFC' + uid, 'nfc');
  }
  window.addEventListener('entrevo-nfc', function (e) { onNfc(e.detail); }); // pont natif de l'appli Entrevo Pro
  function startNfc() {
    if (window.EntrevoNative && window.EntrevoNative.nfc) { try { window.EntrevoNative.nfc(true); } catch (e) {} $('m-nfc-info').textContent = 'Lecteur du téléphone actif'; return true; }
    if (!('NDEFReader' in window)) { $('m-nfc-info').textContent = 'Pas de lecteur NFC accessible ici (Chrome Android ou appli Entrevo Pro)'; return false; }
    if (nfcReader) return true;
    try {
      nfcReader = new window.NDEFReader();
      nfcReader.scan().then(function () { $('m-nfc-info').textContent = 'Lecteur actif : approche un bracelet'; }).catch(function (e) { nfcReader = null; $('m-nfc-info').textContent = 'NFC refusé : ' + e.message; });
      nfcReader.onreading = function (ev) { onNfc(ev.serialNumber || ''); };
      return true;
    } catch (e) { $('m-nfc-info').textContent = 'NFC indisponible'; return false; }
  }
  $('m-nfc').onclick = function () { if (startNfc()) { overlay('menu', false); nfcBar(cfg.linkMode ? 'Mode association : scanne un billet.' : 'Lecture NFC active : approche un bracelet.'); setTimeout(function () { if (!cfg.linkMode) nfcBar(''); }, 4000); } };
  $('m-link').onclick = function () { cfg.linkMode = true; saveCfg(); linkCode = null; startNfc(); overlay('menu', false); nfcBar('Mode association : scanne le QR code du billet.'); };
  if (window.EntrevoNative && window.EntrevoNative.home) { $('m-home').hidden = false; $('m-home').onclick = function () { window.EntrevoNative.home(); }; }
  $('nfc-stop').onclick = function () { cfg.linkMode = false; saveCfg(); linkCode = null; nfcBar(''); };

  function handle(raw, source) {
    var code = normalize(raw);
    if (!code || !data) return;
    if (cfg.linkMode && source !== 'nfc') { // 4.3 : association d'un bracelet
      if (!data.tickets[code]) { beep(220, 0.3); nfcBar('Billet inconnu : ' + code + '. Réessaie.'); return; }
      linkCode = code; beep(880, 0.08); vibrate(30); nfcBar('Approche le bracelet de ' + (data.tickets[code].n || code) + '.'); return;
    }
    if (/^NFC[0-9A-F]{6,}$/.test(code) && !data.tickets[code] && nfcIdx[code.slice(3)]) code = nfcIdx[code.slice(3)];
    var now = Date.now();
    if (source === 'camera' && code === lastCode && now - lastAt < 2800) return; // même billet encore devant la caméra
    lastCode = code; lastAt = now;
    var id = uuid(), at = nowSql();
    var local = localCheck(code);
    var payload = { uuid: id, code: code, ap: cfg.apId, session: cfg.sessionId || null, direction: cfg.dir, device: DEVICE };

    var offline = function () {
      applyLocal(code, local.result);
      queue.push({ uuid: id, code: code, ap: cfg.apId, session: cfg.sessionId || null, direction: cfg.dir, at: at, device: DEVICE, local: local.result });
      saveQueue();
      finish({ result: local.result, ticket: local.t ? { name: local.t.n, rate: data.rates[local.t.r] || local.t.rn || '', rate_id: local.t.r, code: code, seat: local.t.st } : null,
        info: { last_at: local.t && (local.t.l || local.t.c), population: local.t && local.t.p && data.pops[local.t.p] ? data.pops[local.t.p].name : undefined } }, id, true);
    };

    if (!online && navigator.onLine === false) { offline(); return; }
    busy = true;
    api('POST', '/api/scan/event/' + data.event.id + '/check', payload, 3000).then(function (r) {
      busy = false;
      if (r.ticket) { var old = data.tickets[r.ticket.code] || {}; data.tickets[r.ticket.code] = Object.assign(old, { n: r.ticket.name, r: r.ticket.rate_id, v: r.ticket.status === 'valid' ? 1 : 0, i: r.ticket.inside, e: r.ticket.entries,
        c: old.c || (r.result === 'ok' ? at : null), s: r.ticket.session_id || 0, m: r.ticket.max || 1, d: r.ticket.per_day || 0, ty: r.ticket.type, st: r.ticket.seat || '', l: r.result === 'ok' ? at : old.l }); }
      if (r.stats) data.stats = r.stats;
      saveData();
      finish(r, id, false);
    }).catch(function (e) {
      busy = false;
      if (e.auth) { show('login'); return; }
      if (e.http) { toastResult('error', 'Erreur serveur', e.message); return; }
      offline();
    });
  }

  function finish(r, id, wasOffline) {
    var level = levelOf(r.result);
    if (r.result === 'ok') { cfg.mine[data.event.id] = (cfg.mine[data.event.id] || 0) + 1; saveCfg(); }
    var name = r.ticket ? r.ticket.name : '';
    var info = [];
    if (r.ticket && r.ticket.rate) info.push(r.ticket.rate);
    if (r.ticket && r.ticket.seat) info.push('' + r.ticket.seat);
    if (r.info && r.info.population) info.push('' + r.info.population);
    if (r.info && r.info.left !== undefined) info.push('encore ' + r.info.left + ' entrée(s)');
    if (r.result === 'wrong_session' && r.info && r.info.session) info.push('Billet pour le ' + r.info.session);
    if (r.result === 'already' && r.info && (r.info.last_at)) info.push('entré à ' + hhmm(r.info.last_at) + (r.info.last_ap ? ' · ' + r.info.last_ap : ''));
    if (r.result === 'wrong_event' && r.info && r.info.other_event) info.push('Billet pour : ' + r.info.other_event);
    if (r.info && r.info.reentry) info.push('ré-entrée');
    if (r.result === 'invalid' && wasOffline) info.push('Pas dans la liste du téléphone (billet acheté à l\'instant ?)');
    if (wasOffline) info.push('hors ligne');
    lastScan = (r.result === 'ok' || r.result === 'exit') ? { uuid: id, code: r.ticket ? r.ticket.code : '', result: r.result } : null;
    var rule = r.result === 'ok' && r.ticket && data.rules ? data.rules[r.ticket.rate_id] : null;
    toastResult(level, LABELS[r.result] || r.label || r.result, name || (r.code || ''), info.join(' · '), rule);
    hist.unshift({ uuid: id, code: r.ticket ? r.ticket.code : (r.code || ''), name: name, label: LABELS[r.result] || r.label || r.result, level: level, result: r.result, at: nowSql().slice(11, 19), off: wasOffline ? 1 : 0 });
    if (hist.length > 150) hist.length = 150;
    store.set('hist:' + data.event.id, hist);
    renderCounters();
  }

  var resultTimer = null;
  function toastResult(level, label, name, info, rule) {
    var box = $('result');
    box.className = 'result ' + level + (rule ? ' has-rule' : '');
    if (/^NFC[0-9A-F]{6,}$/.test(lastCode || '') && (label === 'Code invalide' || label === 'Billet inconnu')) { label = 'Bracelet non associé'; name = 'Associe-le à un billet depuis le menu'; }
    $('r-label').textContent = label;
    $('r-name').textContent = name || '';
    $('r-info').textContent = info || '';
    $('r-rule').hidden = !rule;
    if (rule) { $('r-rule-color').style.background = rule.color || 'transparent'; $('r-rule-color').hidden = !rule.color; $('r-rule-text').textContent = rule.note || 'Bracelet de cette couleur'; }
    $('r-undo').hidden = !lastScan;
    var f = $('flash'); f.className = 'flash-screen ' + level + ' show';
    setTimeout(function () { f.className = 'flash-screen ' + level; }, 60);
    feedback(level);
    clearTimeout(resultTimer);
    resultTimer = setTimeout(function () { if (box.className.indexOf('idle') < 0) box.className = 'result ' + level + ' faded' + (rule ? ' has-rule' : ''); }, rule ? 9000 : 4000);
  }
  function resetResult() {
    $('result').className = 'result idle';
    $('r-label').textContent = 'Vise un QR code';
    $('r-name').textContent = 'Ou tape le code en dessous';
    $('r-info').textContent = ''; $('r-undo').hidden = true; $('r-rule').hidden = true;
  }

  $('r-undo').onclick = function () {
    if (!lastScan) return;
    var s = lastScan; lastScan = null; $('r-undo').hidden = true;
    undo(s);
  };
  function undo(s, done) {
    var idx = queue.map(function (q) { return q.uuid; }).indexOf(s.uuid);
    var revert = function () {
      var t = data.tickets[s.code];
      if (t && s.result === 'ok') { t.e = Math.max(0, t.e - 1); t.i = 0; if (!t.e) t.c = null; }
      if (t && s.result === 'exit') t.i = 1;
      if (s.result === 'ok') { cfg.mine[data.event.id] = Math.max(0, (cfg.mine[data.event.id] || 1) - 1); saveCfg(); }
      saveData(); renderCounters();
      hist.forEach(function (h) { if (h.uuid === s.uuid) h.undone = 1; }); store.set('hist:' + data.event.id, hist);
      toastResult('warn', 'Passage annulé', t ? t.n : s.code, '');
      if (done) done(true);
    };
    if (idx >= 0) { queue.splice(idx, 1); saveQueue(); revert(); return; }
    api('POST', '/api/scan/event/' + data.event.id + '/undo', { uuid: s.uuid }, 4000).then(function (j) { if (j.stats) data.stats = j.stats; revert(); })
      .catch(function () { toastResult('error', 'Annulation impossible', 'Pas de réseau', 'Réessaie quand le réseau revient.'); if (done) done(false); });
  }

  // ---------- Compteurs ----------
  function renderCounters() {
    if (!data || !$('c-sold')) return;
    var sold = 0, checked = 0, inside = 0;
    Object.keys(data.tickets).forEach(function (k) { var t = data.tickets[k]; if (t.v && t.ty !== 'accreditation') { sold++; if (t.e > 0) checked++; if (t.i) inside++; } });
    $('c-sold').textContent = sold;
    $('c-checked').textContent = checked;
    $('c-inside').textContent = inside;
    $('c-mine').textContent = cfg.mine[data.event.id] || 0;
    var cap = data.event.capacity || 0, srv = (live && live.stats) || data.stats || {}, ins = online && srv.inside !== undefined ? srv.inside : inside, g = $('gauge');
    if (g) { g.hidden = !cap; if (cap) { var pc = Math.round(ins / cap * 100); g.textContent = ins + ' / ' + cap; g.className = 'gauge-chip' + (pc >= 100 ? ' full' : (pc >= 90 ? ' warn' : '')); g.title = 'Jauge : ' + pc + ' %'; } }
  }

  // ---------- 4.3 : liste des invités (invitations, accréditations) ----------
  $('m-guests').onclick = function () { overlay('menu', false); $('s-guests').hidden = false; $('guests-input').value = ''; renderGuests(); };
  $('guests-input').oninput = function () { renderGuests(); };
  function renderGuests() {
    var q = fold($('guests-input').value.trim()), list = $('guests-list'), out = [];
    Object.keys(data.tickets).forEach(function (code) { var t = data.tickets[code]; if ((t.ty === 'invitation' || t.ty === 'accreditation') && (!q || fold(t.n).indexOf(q) >= 0)) out.push([code, t]); });
    out.sort(function (a, b) { return (a[1].e > 0) - (b[1].e > 0) || fold(a[1].n).localeCompare(fold(b[1].n)); });
    var came = out.filter(function (x) { return x[1].e > 0; }).length;
    $('guests-count').textContent = out.length ? out.length + ' invité(s) · ' + came + ' arrivé(s)' : '';
    list.innerHTML = out.length ? '' : '<p class="muted">Aucun invité' + (q ? ' à ce nom' : ' sur cet événement') + '.</p>';
    out.slice(0, 300).forEach(function (x) {
      var code = x[0], t = x[1], div = document.createElement('div'); div.className = 'search-item';
      var st = !t.v ? 'annulé' : (t.i ? 'sur place' : (t.e ? 'sorti' : 'attendu'));
      div.innerHTML = '<div><b>' + esc(t.n || 'Sans nom') + '</b><span class="guest-tag">' + (t.ty === 'accreditation' ? 'Accréditation' : 'Invitation') + '</span><small>' + esc(data.rates[t.r] || t.rn || '') + ' · ' + st + '</small></div>';
      if (t.v && !t.i) { var b = document.createElement('button'); b.className = 'btn btn-sm'; b.textContent = 'Faire entrer'; b.onclick = function () { $('s-guests').hidden = true; handle(code, 'search'); }; div.appendChild(b); }
      list.appendChild(div);
    });
  }

  // ---------- Recherche par nom ----------
  $('search-btn').onclick = function () { overlay('search', true); };
  $('search-input').oninput = renderSearch;
  function renderSearch() {
    var q = fold($('search-input').value.trim()), list = $('search-list'), out = [];
    if (!data) return;
    if (q.length >= 2) {
      Object.keys(data.tickets).some(function (code) {
        var t = data.tickets[code];
        if (fold(t.n).indexOf(q) >= 0 || code.toLowerCase().indexOf(q) >= 0) out.push([code, t]);
        return out.length >= 60;
      });
    }
    list.innerHTML = q.length < 2 ? '<p class="muted">Tape au moins 2 lettres.</p>' : (out.length ? '' : '<p class="muted">Personne à ce nom</p>');
    out.forEach(function (x) {
      var code = x[0], t = x[1], div = document.createElement('div');
      var st = !t.v ? 'annulé' : (t.i ? 'sur place' : (t.e ? 'sorti' : 'pas encore arrivé'));
      div.className = 'search-item';
      div.innerHTML = '<div><b>' + esc(t.n) + '</b><small>' + esc(data.rates[t.r] || t.rn || '') + (t.st ? ' · ' + esc(t.st) : '') + ' · ' + st + '</small><small class="mono">' + esc(code) + '</small></div>';
      if (t.v) { var b = document.createElement('button'); b.className = 'btn btn-sm'; b.textContent = cfg.dir === 'out' ? 'Sortie' : 'Valider'; b.onclick = function () { overlay('search', false); handle(code, 'search'); }; div.appendChild(b); }
      list.appendChild(div);
    });
  }

  // ---------- Synchronisation ----------
  var syncing = false;
  function sync() {
    if (syncing || !data || !queue.length) return Promise.resolve();
    syncing = true;
    var batch = queue.slice(0, 200);
    return api('POST', '/api/scan/event/' + data.event.id + '/sync', { scans: batch }, 20000).then(function (j) {
      var done = {};
      j.results.forEach(function (r) { done[r.uuid] = 1; if (r.local && r.local !== r.result) conflicts++; });
      queue = queue.filter(function (q) { return !done[q.uuid]; });
      saveQueue();
      syncing = false;
      if (queue.length) return sync();
      return refresh();
    }).catch(function () { syncing = false; });
  }
  function refresh() {
    if (!data || syncing) return Promise.resolve();
    return api('GET', '/api/scan/event/' + data.event.id + '/data?since=' + encodeURIComponent(data.server_time), null, 15000).then(merge).then(updateNet).catch(function () { /* hors ligne */ });
  }
  setInterval(function () { if (!data || $('s-scan').hidden) return; if (queue.length) sync(); }, 8000);
  setInterval(function () { if (!data || $('s-scan').hidden || queue.length) return; refresh(); }, 25000);
  $('m-sync').onclick = function () { $('m-sync-info').textContent = 'Synchronisation…'; sync().then(refresh).then(function () { updateNet(); }); };

  // ---------- Son et vibration ----------
  var audio = null;
  function unlockAudio() { try { if (!audio) audio = new (window.AudioContext || window.webkitAudioContext)(); if (audio.state === 'suspended') audio.resume(); } catch (e) { audio = null; } }
  function beep(freq, dur, delay) {
    if (!audio || !cfg.sound) return;
    var o = audio.createOscillator(), g = audio.createGain(), t = audio.currentTime + (delay || 0);
    o.type = 'square'; o.frequency.value = freq; g.gain.setValueAtTime(0.12, t); g.gain.exponentialRampToValueAtTime(0.001, t + dur);
    o.connect(g); g.connect(audio.destination); o.start(t); o.stop(t + dur);
  }
  function feedback(level) {
    if (level === 'ok') { beep(1320, 0.12); vibrate(60); }
    else if (level === 'warn') { beep(660, 0.12); beep(660, 0.12, 0.18); vibrate([80, 60, 80]); }
    else { beep(220, 0.45); vibrate([250, 80, 250]); }
  }
  function vibrate(p) { try { if (navigator.vibrate) navigator.vibrate(p); } catch (e) { /* rien */ } }
  function renderSound() { $('m-sound-info').textContent = cfg.sound ? 'Activé (bip vert, double bip orange, buzz rouge)' : 'Coupé'; }
  $('m-sound').onclick = function () { cfg.sound = !cfg.sound; saveCfg(); renderSound(); unlockAudio(); feedback('ok'); };
  renderSound();

  $('logout-form').addEventListener('submit', function (e) {
    if (queue.length && !confirm(queue.length + ' passage(s) pas encore envoyés seront perdus. Te déconnecter quand même ?')) { e.preventDefault(); return; }
    cfg.running = false; saveCfg(); store.del('me');
  });
  document.addEventListener('visibilitychange', function () { if (document.hidden) stopCamera(); else if (!$('s-scan').hidden) startCamera(); });

  // ---------- Sens au toucher ----------
  function renderDir() {
    $('dir-pill').textContent = cfg.dir === 'out' ? 'Sortie' : 'Entrée';
    $('dir-pill').classList.toggle('out', cfg.dir === 'out');
    document.querySelectorAll('.seg button').forEach(function (b) { b.classList.toggle('on', b.dataset.dir === cfg.dir); });
  }
  $('dir-pill').onclick = function () { cfg.dir = cfg.dir === 'out' ? 'in' : 'out'; saveCfg(); renderDir(); vibrate(30); resetResult(); };

  // ---------- Historique ----------
  function renderHistory() {
    var list = $('history-list'); list.textContent = '';
    if (!hist.length) { var p = document.createElement('p'); p.className = 'muted'; p.textContent = 'Aucun passage sur ce téléphone pour l\u2019instant.'; list.appendChild(p); return; }
    hist.forEach(function (h) {
      var div = document.createElement('div'); div.className = 'search-item hist ' + h.level + (h.undone ? ' undone' : '');
      var txt = document.createElement('div');
      var b = document.createElement('b'); b.textContent = h.name || h.code; txt.appendChild(b);
      var sm = document.createElement('small'); sm.textContent = h.at.slice(0, 5) + ' · ' + h.label + (h.off ? ' ·' : '') + (h.undone ? ' · annulé' : ''); txt.appendChild(sm);
      div.appendChild(txt);
      if (!h.undone && (h.result === 'ok' || h.result === 'exit')) {
        var bt = document.createElement('button'); bt.className = 'btn btn-sm btn-ghost'; bt.textContent = 'Annuler';
        bt.onclick = function () { if (!confirm('Annuler ce passage de ' + (h.name || h.code) + ' ?')) return; undo({ uuid: h.uuid, code: h.code, result: h.result }, function () { renderHistory(); }); };
        div.appendChild(bt);
      }
      list.appendChild(div);
    });
  }
  $('m-history').onclick = function () { overlay('menu', false); overlay('history', true); };

  // ---------- Message du PC ----------
  function showMessage(m) {
    if (!m || !data) return;
    var seen = cfg.msgSeen[data.event.id] || 0;
    if (m.id <= seen) return;
    $('pc-msg-text').textContent = m.body; $('pc-msg-at').textContent = m.at || '';
    var box = $('pc-msg'); box.hidden = false; box.className = 'pc-msg ' + (m.level || 'info') + (m.ack ? ' must' : '');
    box.dataset.id = m.id; box.dataset.ack = m.ack ? 1 : 0; $('pc-msg-ok').textContent = m.ack ? 'J\u2019ai lu' : 'OK';
    beep(880, 0.1); beep(1100, 0.1, 0.14); beep(1320, 0.14, 0.28); vibrate([100, 60, 100, 60, 200]);
  }
  $('pc-msg-ok').onclick = function () {
    var box = $('pc-msg'), mid = parseInt(box.dataset.id, 10) || 0; cfg.msgSeen[data.event.id] = mid; saveCfg(); box.hidden = true;
    if (box.dataset.ack === '1') api('POST', '/api/scan/event/' + data.event.id + '/ack', { message: mid, device: DEVICE }).catch(function () { /* renvoyé au prochain message */ });
  };

  // ---------- Signal de vie + stats ----------
  var battery = null;
  try { if (navigator.getBattery) navigator.getBattery().then(function (b) { battery = b; }); } catch (e) { /* rien */ }
  function ping() {
    if (!data || !online) return;
    var ap = currentAp();
    api('POST', '/api/scan/event/' + data.event.id + '/ping', { device: DEVICE, ap: ap ? ap.name : '', dir: cfg.dir, pending: queue.length,
      battery: battery ? Math.round(battery.level * 100) : null, scans: cfg.mine[data.event.id] || 0, queue: (cfg.queue || {})[data.event.id] != null ? cfg.queue[data.event.id] : null }, 6000).then(function (j) {
      live = j; if (j.stats) { data.stats = j.stats; }
      if (j.message) showMessage(j.message);
      renderCounters(); if (!$('s-stats').hidden) renderStats();
    }).catch(function () { /* hors ligne */ });
  }
  setInterval(function () { if (data && !$('s-scan').hidden) ping(); }, 30000);
  function renderStats() {
    var box = $('stats-body'); box.textContent = '';
    var add = function (tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text !== undefined) n.textContent = text; box.appendChild(n); return n; };
    var st = (live && live.stats) || (data && data.stats) || { sold: 0, checked: 0, inside: 0 };
    var big = add('div', 'stats-big');
    [['Entrés', st.checked], ['Sur place', st.inside], ['Billets', st.sold]].forEach(function (x) { var d = document.createElement('div'); var b = document.createElement('b'); b.textContent = x[1]; var s2 = document.createElement('small'); s2.textContent = x[0]; d.appendChild(b); d.appendChild(s2); big.appendChild(d); });
    var pct = st.sold ? Math.round(st.checked / st.sold * 100) : 0;
    var bar = add('div', 'stats-bar'); var bi = document.createElement('i'); bi.style.width = pct + '%'; bar.appendChild(bi);
    add('p', 'muted', pct + ' % des billets sont passés' + (st.capacity ? ' · jauge ' + Math.round(st.inside / st.capacity * 100) + ' %' : ''));
    add('h2', '', 'File d\u2019attente');
    add('p', 'muted', (st.per_min ? st.per_min + ' entrées par minute' : 'Pas d\u2019entrée ces 5 dernières minutes') + (st.wait ? ' · attente estimée ' + st.wait + ' min pour ' + st.queue + ' personne(s)' : ''));
    cfg.queue = cfg.queue || {}; var qv = cfg.queue[data.event.id] || 0;
    var qc = add('div', 'queue-ctl'); var lbl = document.createElement('small'); lbl.textContent = 'Devant ta porte :';
    var mb = document.createElement('button'); mb.type = 'button'; mb.textContent = '−'; mb.setAttribute('aria-label', 'Moins de monde');
    var qb = document.createElement('b'); qb.textContent = qv;
    var pb = document.createElement('button'); pb.type = 'button'; pb.textContent = '+'; pb.setAttribute('aria-label', 'Plus de monde');
    var setQ = function (d) { qv = Math.max(0, qv + d); cfg.queue[data.event.id] = qv; saveCfg(); qb.textContent = qv; clearTimeout(setQ.t); setQ.t = setTimeout(ping, 800); };
    mb.onclick = function () { setQ(-10); }; pb.onclick = function () { setQ(10); };
    qc.appendChild(lbl); qc.appendChild(mb); qc.appendChild(qb); qc.appendChild(pb);
    if (!live) { add('p', 'muted', online ? 'Chargement…' : 'Hors ligne : chiffres de ce téléphone uniquement.'); return; }
    add('h2', '', 'Par porte');
    if (!live.per_ap.length) add('p', 'muted', 'Pas encore de passage.');
    var mx = 1; live.per_ap.forEach(function (a) { mx = Math.max(mx, a.in); });
    live.per_ap.forEach(function (a) {
      var row = add('div', 'stats-row'); var l = document.createElement('span'); l.textContent = a.name; row.appendChild(l);
      var b = document.createElement('span'); b.className = 'stats-hbar'; var i = document.createElement('i'); i.style.width = Math.round(a.in / mx * 100) + '%'; b.appendChild(i); row.appendChild(b);
      var v = document.createElement('b'); v.textContent = a.in + (a.out ? ' · ↑' + a.out : ''); row.appendChild(v);
    });
    add('h2', '', 'Entrées par heure');
    if (!live.per_hour.length) add('p', 'muted', 'Pas encore d\u2019entrée ces 6 dernières heures.');
    var hx = 1; live.per_hour.forEach(function (h) { hx = Math.max(hx, h.n); });
    var cols = add('div', 'stats-cols');
    live.per_hour.forEach(function (h) { var c = document.createElement('div'); var bb = document.createElement('i'); bb.style.height = Math.max(4, Math.round(h.n / hx * 100)) + '%'; var lb = document.createElement('small'); lb.textContent = h.h; var nb = document.createElement('b'); nb.textContent = h.n; c.appendChild(nb); c.appendChild(bb); c.appendChild(lb); cols.appendChild(c); });
  }
  $('m-stats').onclick = function () { overlay('menu', false); overlay('stats', true); };

  // ---------- Écran toujours allumé ----------
  var lock = null;
  function wake(on) {
    if (!('wakeLock' in navigator)) return;
    if (on && cfg.awake && !lock && !document.hidden) navigator.wakeLock.request('screen').then(function (l) { lock = l; l.addEventListener('release', function () { lock = null; }); }).catch(function () { lock = null; });
    if (!on && lock) { lock.release().catch(function () {}); lock = null; }
  }
  function renderAwake() { $('m-awake-info').textContent = !('wakeLock' in navigator) ? 'Pas disponible sur ce navigateur' : (cfg.awake ? 'Activé pendant le scan' : 'Désactivé (l\u2019écran se met en veille)'); }
  $('m-awake').onclick = function () { cfg.awake = !cfg.awake; saveCfg(); renderAwake(); wake(cfg.awake && !$('s-scan').hidden); };
  document.addEventListener('visibilitychange', function () { if (!document.hidden && !$('s-scan').hidden) wake(true); });
  renderAwake();

  boot();
})();
