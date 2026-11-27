<?php /** @var array $ev, $org, $sessions, $products; bool $seatmap */
$base = '/pro/evenements/' . $ev['id'];
$first = null; foreach ($sessions as $x) if (strtotime((string)($x['ends_at'] ?: $x['starts_at'])) >= time() - 6 * 3600) { $first = $x; break; } if (!$first && $sessions) $first = $sessions[0]; ?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Guichet · <?= e($ev['name']) ?></title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>"><style>:root{--primary:<?= e(setting('primary_color', '#0071e3')) ?>}</style>
<style>
body.box{margin:0;background:#eef0f4;min-height:100vh;font-family:-apple-system,BlinkMacSystemFont,"SF Pro Text","Segoe UI",Roboto,sans-serif;color:#1d1d1f}
.bx-top{display:flex;align-items:center;gap:14px;padding:10px 16px;background:#1d1d1f;color:#fff}.bx-top a{color:#fff;text-decoration:none;opacity:.75;font-size:14px}.bx-top a:hover{opacity:1}
.bx-top b{font-size:16px}.bx-top .sp{flex:1}.bx-cap{font-size:13px;opacity:.85}.bx-cap i{display:inline-block;width:120px;height:6px;border-radius:6px;background:rgba(255,255,255,.2);vertical-align:middle;margin-left:6px;overflow:hidden}.bx-cap i u{display:block;height:100%;background:#34c759}
.bx{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:14px;padding:14px;min-height:calc(100vh - 48px)}
.bx-panel{background:#fff;border-radius:20px;padding:16px;box-shadow:0 2px 10px rgba(0,0,0,.05)}
.bx-dates{display:flex;gap:8px;overflow-x:auto;margin-bottom:12px;padding-bottom:4px}.bx-dates button{flex:0 0 auto;border:1.5px solid #e5e5ea;background:#fff;border-radius:14px;padding:8px 12px;text-align:left;cursor:pointer;font:inherit}
.bx-dates button.on{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 8%,#fff)}.bx-dates b{display:block;font-size:14px}.bx-dates small{color:#6e6e73;font-size:12px}
.bx-tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px}
.bx-tile{position:relative;border:0;border-radius:16px;padding:14px;min-height:104px;text-align:left;background:#f5f5f7;cursor:pointer;font:inherit;display:flex;flex-direction:column;gap:4px;transition:transform .08s,background .15s}
.bx-tile:hover{background:#ececf1}.bx-tile:active{transform:scale(.97)}.bx-tile b{font-size:15px;line-height:1.2}.bx-tile .pr{font:800 20px/1 var(--display,inherit);margin-top:auto}.bx-tile small{color:#6e6e73;font-size:12px}
.bx-tile .stripe{position:absolute;left:0;top:12px;bottom:12px;width:4px;border-radius:0 4px 4px 0}.bx-tile.out{opacity:.4;pointer-events:none}.bx-tile.off{opacity:.7}.bx-tile .n{position:absolute;top:10px;right:10px;background:var(--primary);color:#fff;border-radius:999px;min-width:26px;height:26px;display:grid;place-items:center;font-weight:800;font-size:13px}
.bx-sec{margin:16px 0 8px;font:700 13px var(--font,inherit);color:#6e6e73;text-transform:uppercase;letter-spacing:.06em}
.bx-cart{display:flex;flex-direction:column;gap:10px;position:sticky;top:14px;max-height:calc(100vh - 76px);overflow:auto}
.bx-kind{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;background:#f2f2f5;border-radius:14px;padding:4px}.bx-kind button{border:0;background:transparent;border-radius:11px;padding:9px 6px;font:600 14px inherit;cursor:pointer}.bx-kind button.on{background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.12)}
.bx-lines{min-height:60px}.bx-line{display:grid;grid-template-columns:1fr auto auto;gap:8px;align-items:center;padding:8px 0;border-bottom:1px solid #f0f0f3}.bx-line .q{display:flex;gap:4px;align-items:center}
.bx-line .q button{width:30px;height:30px;border-radius:50%;border:1px solid #e5e5ea;background:#fff;cursor:pointer;font-size:16px}.bx-line input.seats{grid-column:1/-1;font-size:13px;padding:6px 10px}
.bx-tot{display:flex;justify-content:space-between;align-items:baseline;font-size:15px}.bx-tot b{font:800 34px/1 var(--display,inherit)}
.bx-pay{display:grid;grid-template-columns:repeat(3,1fr);gap:6px}.bx-pay button{border:1.5px solid #e5e5ea;background:#fff;border-radius:12px;padding:10px 4px;font:600 13px inherit;cursor:pointer}.bx-pay button.on{border-color:var(--primary);color:var(--primary)}
.bx-bills{display:flex;flex-wrap:wrap;gap:6px}.bx-bills button{border:0;background:#f2f2f5;border-radius:10px;padding:8px 10px;font:600 13px inherit;cursor:pointer}
.bx-change{display:flex;justify-content:space-between;font-size:15px;padding:8px 12px;border-radius:12px;background:#e3f9e9;color:#1a7f37}.bx-change.bad{background:#ffe9e7;color:#b3261e}
.bx-go{border:0;border-radius:16px;padding:18px;background:var(--primary);color:#fff;font:700 18px inherit;cursor:pointer}.bx-go:disabled{opacity:.4;cursor:not-allowed}
.bx-cl{position:relative}.bx-sug{position:absolute;left:0;right:0;top:100%;z-index:5;background:#fff;border:1px solid #e5e5ea;border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.12);overflow:hidden}.bx-sug button{display:block;width:100%;text-align:left;border:0;background:#fff;padding:9px 12px;cursor:pointer;font:inherit}.bx-sug button:hover{background:#f5f5f7}
.bx-side h3{margin:0 0 6px;font-size:15px}.bx-row{display:flex;justify-content:space-between;gap:8px;padding:7px 0;border-bottom:1px solid #f0f0f3;font-size:14px;align-items:center}
.bx-modal{position:fixed;inset:0;background:rgba(10,10,15,.5);display:grid;place-items:center;z-index:50;padding:16px}.bx-modal[hidden]{display:none}.bx-modal .box{background:#fff;border-radius:22px;padding:24px;max-width:420px;width:100%;text-align:center}
.bx-modal .ic{width:64px;height:64px;border-radius:50%;background:#e3f9e9;color:#1a7f37;display:grid;place-items:center;margin:0 auto 10px;font-size:30px}
.bx-modal .acts{display:grid;gap:8px;margin-top:16px}
.bx-err{background:#ffe9e7;color:#b3261e;border-radius:12px;padding:10px 12px;font-size:14px}.bx-err[hidden]{display:none}
.bx-grid2{display:grid;grid-template-columns:1fr 1fr;gap:8px}.bx input,.bx select{width:100%;padding:10px 12px;border:1.5px solid #e5e5ea;border-radius:12px;font:inherit;background:#fff}
.bx-cart>*{flex-shrink:0}.bx input[type=checkbox]{width:auto;padding:0}.bx .check{display:flex;gap:8px;align-items:center}
.bx-bottom{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px}
kbd{font-size:11px;background:#f2f2f5;border-radius:5px;padding:1px 5px;color:#6e6e73}
@media (max-width:1000px){.bx{grid-template-columns:1fr}.bx-cart{position:static;max-height:none}.bx-bottom{grid-template-columns:1fr}}
</style></head>
<body class="box">
<div class="bx-top"><a href="<?= url($base) ?>">← Retour</a><b>Guichet · <?= e($ev['name']) ?></b><span class="sp"></span><span class="bx-cap" id="cap"></span><a href="<?= url($base . '/caisse') ?>">Journal & clôture</a><a href="<?= url('/scan') ?>" target="_blank">Scan</a></div>
<div class="bx">
  <div>
    <div class="bx-panel">
      <?php if ($sessions): ?><div class="bx-dates" id="dates"><?php foreach ($sessions as $x): ?><button type="button" data-sid="<?= (int)$x['id'] ?>" class="<?= $first && (int)$first['id'] === (int)$x['id'] ? 'on' : '' ?>"><b><?= e(date('d/m H\hi', strtotime($x['starts_at']))) ?></b><small><?= e($x['label'] ?: fr_date($x['starts_at'], false)) ?></small></button><?php endforeach; ?></div><?php endif; ?>
      <div class="bx-tiles" id="tiles"><p class="muted">Chargement…</p></div>
      <?php if ($products): ?><div class="bx-sec">Options & produits</div><div class="bx-tiles"><?php foreach ($products as $p): ?><button type="button" class="bx-tile" data-prod="<?= (int)$p['id'] ?>" data-name="<?= e($p['name']) ?>" data-price="<?= (int)$p['price'] ?>"><b><?= e($p['name']) ?></b><span class="pr"><?= e(money((int)$p['price'])) ?></span></button><?php endforeach; ?></div><?php endif; ?>
    </div>
    <div class="bx-bottom">
      <div class="bx-panel bx-side"><h3>Réservations à régler</h3><div id="resa"><p class="muted">Aucune.</p></div></div>
      <div class="bx-panel bx-side"><h3>Aujourd'hui au guichet</h3><div id="today"></div><h3 style="margin-top:14px">Dernières ventes</h3><div id="last"></div></div>
    </div>
  </div>
  <aside class="bx-panel bx-cart">
    <div class="bx-kind" id="kind"><button type="button" data-k="sale" class="on">Vente</button><button type="button" data-k="reservation">Réservation</button><button type="button" data-k="invitation">Invitation</button></div>
    <div class="bx-lines" id="lines"><p class="muted" style="text-align:center;margin:18px 0">Touche un tarif pour l'ajouter.</p></div>
    <div class="bx-grid2"><input id="disc" inputmode="decimal" placeholder="Remise %"><input id="promo" placeholder="Code promo"></div>
    <div class="bx-tot"><span id="totl">Total</span><b id="tot">0,00 €</b></div>
    <div class="bx-cl"><input id="cq" placeholder="Client : nom, e-mail, téléphone…" autocomplete="off"><div class="bx-sug" id="sug" hidden></div></div>
    <div class="bx-grid2"><input id="fn" placeholder="Prénom"><input id="ln" placeholder="Nom"></div>
    <div class="bx-grid2"><input id="em" type="email" placeholder="E-mail (billets envoyés)"><input id="ph" placeholder="Téléphone"></div>
    <div id="hold" hidden><label class="muted" style="font-size:13px">Réservée jusqu'au</label><input id="holdu" type="datetime-local"></div>
    <div id="paybox">
      <div class="bx-pay" id="pay"><?php foreach (BOX_PAY as $k => $l): ?><button type="button" data-pm="<?= $k ?>" class="<?= $k === 'especes' ? 'on' : '' ?>"><?= e($l) ?></button><?php endforeach; ?></div>
      <div id="cash" style="margin-top:8px"><div class="bx-grid2"><input id="given" inputmode="decimal" placeholder="Reçu en espèces"><div class="bx-change" id="chg"><span>À rendre</span><b>—</b></div></div>
        <div class="bx-bills" style="margin-top:6px"><?php foreach ([5, 10, 20, 50, 100] as $bl): ?><button type="button" data-bill="<?= $bl ?>"><?= $bl ?> €</button><?php endforeach; ?><button type="button" data-bill="exact">Compte juste</button></div></div>
      <div id="mixed" hidden style="margin-top:8px"><div class="bx-grid2"><input id="mx1" inputmode="decimal" placeholder="Espèces €"><input id="mx2" inputmode="decimal" placeholder="Carte €"></div></div>
    </div>
    <?php if ($seatmap): ?><small class="muted">Placement numéroté : indique les sièges dans chaque ligne (ex. A12 A13).</small><?php endif; ?>
    <label class="check" style="font-size:14px"><input type="checkbox" id="send" checked> Envoyer les billets par e-mail</label>
    <label class="check" style="font-size:14px"><input type="checkbox" id="force"> Dépasser la jauge si besoin</label>
    <input id="note" placeholder="Note interne (partenaire, presse…)">
    <div class="bx-err" id="err" hidden></div>
    <button type="button" class="bx-go" id="go" disabled>Encaisser</button>
    <small class="muted" style="text-align:center">Raccourcis : <kbd>Entrée</kbd> valider · <kbd>Échap</kbd> vider le panier</small>
  </aside>
</div>
<div class="bx-modal" id="done" hidden><div class="box"><div class="ic">✓</div><b style="font-size:20px" id="dt"></b><p class="muted" id="dd"></p>
  <div class="acts"><a class="btn btn-lg" id="dpdf" target="_blank">Imprimer les billets</a><a class="btn btn-light" id="drec" target="_blank">Ticket de caisse</a><a class="btn btn-ghost" id="ddet" target="_blank">Voir la commande</a><button type="button" class="btn btn-ghost" id="dnew">Nouvelle vente</button></div></div></div>
<script>
(function(){
  var B = <?= json_encode(url($base . '/guichet')) ?>, CSRF = <?= json_encode(csrf_token()) ?>, SEAT = <?= $seatmap ? 'true' : 'false' ?>, PAY = <?= json_encode(BOX_PAY) ?>;
  var st = { sid: <?= (int)($first['id'] ?? 0) ?>, kind: 'sale', pm: 'especes', rates: [], cart: {}, prods: {}, seats: {} };
  var $ = function(s){ return document.querySelector(s); }, $$ = function(s){ return document.querySelectorAll(s); };
  var eur = function(c){ return (c / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' }); }, num = function(v){ return Math.round(parseFloat(String(v || '').replace(',', '.')) * 100) || 0; };
  var esc = function(s){ return String(s == null ? '' : s).replace(/[&<>"]/g, function(c){ return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  function load(){ fetch(B + '/etat?session_id=' + st.sid, { credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(j){ st.rates = j.rates; tiles(); side(j);
    $('#cap').innerHTML = j.cap ? j.used + ' / ' + j.cap + ' places<i><u style="width:' + Math.min(100, Math.round(j.used / j.cap * 100)) + '%"></u></i>' : j.used + ' places vendues'; }); }
  function tiles(){ $('#tiles').innerHTML = st.rates.map(function(r){ var n = st.cart[r.id] || 0, out = r.left !== null && r.left <= 0;
      return '<button type="button" class="bx-tile ' + (out ? 'out' : '') + (r.active ? '' : ' off') + '" data-rate="' + r.id + '">' + (r.color ? '<span class="stripe" style="background:' + esc(r.color) + '"></span>' : '') + (n ? '<span class="n">' + n + '</span>' : '')
        + '<b>' + esc(r.name) + '</b><small>' + (r.group ? esc(r.group) + ' · ' : '') + (r.left === null ? 'sans limite' : (out ? 'complet' : r.left + ' restante(s)')) + (r.active ? '' : ' · hors vente en ligne') + '</small><span class="pr">' + (r.price ? eur(r.price) : 'Gratuit') + '</span></button>'; }).join('') || '<p class="muted">Aucun tarif pour cette date.</p>'; }
  function side(j){ $('#resa').innerHTML = j.resa.length ? j.resa.map(function(o){ return '<div class="bx-row"><span><b>' + esc((o.first_name + ' ' + o.last_name).trim()) + '</b> · ' + o.n + ' billet(s)<br><small class="muted">' + esc(o.ref) + ' · jusqu\'au ' + esc(o.expires_at.slice(8, 10) + '/' + o.expires_at.slice(5, 7) + ' ' + o.expires_at.slice(11, 16)) + (o.phone ? ' · ' + esc(o.phone) : '') + '</small></span><span style="white-space:nowrap"><b>' + eur(+o.total) + '</b> <button class="btn btn-sm" data-rpay="' + o.id + '">Encaisser</button> <button class="btn btn-sm btn-ghost" data-rcancel="' + o.id + '">×</button></span></div>'; }).join('') : '<p class="muted">Aucune réservation en attente.</p>';
    var tot = 0, n = 0; $('#today').innerHTML = j.today.map(function(t){ tot += +t.t; n += +t.n; return '<div class="bx-row"><span>' + esc(PAY[t.m] || (t.m === 'invitation' ? 'Invitations' : t.m)) + '</span><span>' + t.n + ' · <b>' + eur(+t.t) + '</b></span></div>'; }).join('') + '<div class="bx-row"><b>Total</b><b>' + n + ' · ' + eur(tot) + '</b></div>';
    $('#last').innerHTML = j.last.map(function(o){ return '<div class="bx-row"><span>' + esc((o.first_name + ' ' + o.last_name).trim()) + ' <small class="muted">· ' + o.n + ' billet(s) · ' + esc(o.method === 'invitation' ? 'invitation' : (PAY[o.pay_method] || o.pay_method || '')) + '</small></span><a href="<?= url('/pro/commandes/') ?>' + o.id + '" target="_blank">' + eur(+o.total) + '</a></div>'; }).join('') || '<p class="muted">Pas encore de vente.</p>'; }
  function total(){ var t = 0; Object.keys(st.cart).forEach(function(id){ var r = st.rates.find(function(x){ return x.id == id; }); if (r) t += r.price * st.cart[id]; });
    $$('[data-prod]').forEach(function(b){ t += (+b.dataset.price) * (st.prods[b.dataset.prod] || 0); }); var d = parseFloat(($('#disc').value || '0').replace(',', '.')) || 0; return Math.round(t * (100 - Math.min(100, d)) / 100); }
  function render(){ var lines = [];
    Object.keys(st.cart).forEach(function(id){ var r = st.rates.find(function(x){ return x.id == id; }); if (!r || !st.cart[id]) return;
      lines.push('<div class="bx-line"><span><b>' + esc(r.name) + '</b><br><small class="muted">' + eur(r.price) + (r.proof ? ' · justificatif : ' + esc(r.proof) : '') + '</small></span><span class="q"><button type="button" data-dec="' + id + '">−</button><b>' + st.cart[id] + '</b><button type="button" data-inc="' + id + '">+</button></span><b>' + eur(r.price * st.cart[id]) + '</b>'
        + (SEAT ? '<input class="seats" data-seats="' + id + '" placeholder="Sièges (ex. A12 A13)" value="' + esc(st.seats[id] || '') + '">' : '') + '</div>'); });
    $$('[data-prod]').forEach(function(b){ var n = st.prods[b.dataset.prod] || 0; if (n) lines.push('<div class="bx-line"><span><b>' + esc(b.dataset.name) + '</b></span><span class="q"><button type="button" data-pdec="' + b.dataset.prod + '">−</button><b>' + n + '</b><button type="button" data-pinc="' + b.dataset.prod + '">+</button></span><b>' + eur(+b.dataset.price * n) + '</b></div>'); });
    $('#lines').innerHTML = lines.join('') || '<p class="muted" style="text-align:center;margin:18px 0">Touche un tarif pour l\'ajouter.</p>';
    var t = st.kind === 'invitation' ? 0 : total(); $('#tot').textContent = eur(t); $('#totl').textContent = st.kind === 'reservation' ? 'À régler plus tard' : (st.kind === 'invitation' ? 'Invitation' : 'Total');
    $('#go').disabled = !lines.length; $('#go').textContent = st.kind === 'invitation' ? 'Émettre les invitations' : (st.kind === 'reservation' ? 'Réserver' : 'Encaisser ' + eur(t));
    $('#paybox').hidden = st.kind !== 'sale'; $('#hold').hidden = st.kind !== 'reservation'; $('#cash').hidden = st.pm !== 'especes'; $('#mixed').hidden = st.pm !== 'mixte'; change(); tiles(); }
  function change(){ var g = num($('#given').value), t = total(), c = $('#chg'); if (!$('#given').value) { c.className = 'bx-change'; c.innerHTML = '<span>À rendre</span><b>—</b>'; return; }
    c.className = 'bx-change' + (g < t ? ' bad' : ''); c.innerHTML = g < t ? '<span>Manque</span><b>' + eur(t - g) + '</b>' : '<span>À rendre</span><b>' + eur(g - t) + '</b>'; }
  document.addEventListener('click', function(e){ var t = e.target.closest('button'); if (!t) return;
    if (t.dataset.rate) { var r = st.rates.find(function(x){ return x.id == t.dataset.rate; }); var n = (st.cart[r.id] || 0) + 1; if (r.left !== null && n > r.left && !$('#force').checked) return err('Plus que ' + r.left + ' place(s) pour ' + r.name + '. Coche « Dépasser la jauge » si besoin.'); st.cart[r.id] = n; err(''); render(); }
    if (t.dataset.inc) { st.cart[t.dataset.inc]++; render(); } if (t.dataset.dec) { st.cart[t.dataset.dec]--; if (st.cart[t.dataset.dec] <= 0) delete st.cart[t.dataset.dec]; render(); }
    if (t.dataset.prod) { st.prods[t.dataset.prod] = (st.prods[t.dataset.prod] || 0) + 1; render(); } if (t.dataset.pinc) { st.prods[t.dataset.pinc]++; render(); } if (t.dataset.pdec) { st.prods[t.dataset.pdec] = Math.max(0, st.prods[t.dataset.pdec] - 1); render(); }
    if (t.dataset.k) { st.kind = t.dataset.k; $$('#kind button').forEach(function(b){ b.classList.toggle('on', b === t); }); render(); }
    if (t.dataset.pm) { st.pm = t.dataset.pm; $$('#pay button').forEach(function(b){ b.classList.toggle('on', b === t); }); render(); }
    if (t.dataset.bill) { $('#given').value = t.dataset.bill === 'exact' ? (total() / 100).toFixed(2).replace('.', ',') : ((num($('#given').value) + t.dataset.bill * 100) / 100).toFixed(2).replace('.', ','); change(); }
    if (t.dataset.sid) { st.sid = +t.dataset.sid; st.cart = {}; $$('#dates button').forEach(function(b){ b.classList.toggle('on', b === t); }); load(); render(); }
    if (t.dataset.rpay) { var pm = prompt('Moyen de paiement ? (especes, cb, cheque, virement)', 'especes'); if (!pm) return; post('/reservation', { order_id: +t.dataset.rpay, action: 'pay', pay_method: pm.trim(), send: true }).then(done); }
    if (t.dataset.rcancel && confirm('Annuler cette réservation ? Les places sont libérées.')) post('/reservation', { order_id: +t.dataset.rcancel, action: 'cancel' }).then(function(){ load(); });
    if (t.dataset.cl) { var c = JSON.parse(t.dataset.cl); $('#fn').value = c.first_name || ''; $('#ln').value = c.last_name || ''; $('#em').value = c.email || ''; $('#ph').value = c.phone || ''; $('#sug').hidden = true; $('#cq').value = ''; } });
  document.addEventListener('input', function(e){ if (e.target.dataset.seats) st.seats[e.target.dataset.seats] = e.target.value; if (e.target.id === 'disc') render(); if (e.target.id === 'given') change(); });
  var ct; $('#cq').addEventListener('input', function(){ clearTimeout(ct); var q = this.value.trim(); if (q.length < 2) { $('#sug').hidden = true; return; }
    ct = setTimeout(function(){ fetch(B + '/clients?q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function(r){ return r.json(); }).then(function(j){
      $('#sug').innerHTML = j.list.map(function(c){ return '<button type="button" data-cl="' + esc(JSON.stringify(c)) + '"><b>' + esc((c.first_name + ' ' + c.last_name).trim()) + '</b> <small class="muted">' + esc(c.email) + ' · ' + c.n + ' commande(s)</small></button>'; }).join('') || '<button type="button" disabled>Aucun client trouvé</button>'; $('#sug').hidden = false; }); }, 250); });
  function err(m){ $('#err').textContent = m; $('#err').hidden = !m; return false; }
  function post(p, body){ return fetch(B + p, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF }, body: JSON.stringify(body) }).then(function(r){ return r.json(); }).then(function(j){ if (j.error) { err(j.error); throw new Error(j.error); } return j; }); }
  function sell(){ if ($('#go').disabled) return; if (st.kind === 'sale' && st.pm === 'especes' && $('#given').value && num($('#given').value) < total()) return err('Le montant reçu ne couvre pas le total.');
    if (st.kind === 'sale' && st.pm === 'mixte' && num($('#mx1').value) + num($('#mx2').value) !== total()) return err('Espèces + carte doivent faire ' + eur(total()) + '.');
    $('#go').disabled = true; err('');
    post('/vente', { kind: st.kind, session_id: st.sid, qty: st.cart, products: st.prods, seats: st.seats, pay_method: st.pm, discount_pct: $('#disc').value.replace(',', '.'), promo: $('#promo').value.trim(),
      buyer: { first_name: $('#fn').value, last_name: $('#ln').value, email: $('#em').value, phone: $('#ph').value }, send: $('#send').checked, force: $('#force').checked, note: $('#note').value,
      hold_until: $('#holdu').value, split: st.pm === 'mixte' ? [{ m: 'especes', a: num($('#mx1').value) }, { m: 'cb', a: num($('#mx2').value) }] : null }).then(done).catch(function(){ $('#go').disabled = false; }); }
  function done(j){ $('#dt').textContent = j.kind === 'reservation' ? 'Réservation enregistrée' : (j.kind === 'invitation' ? 'Invitation émise' : 'Vente enregistrée');
    $('#dd').textContent = j.order.ref + ' · ' + (j.order.name || '') + (j.kind === 'reservation' ? ' · à régler avant le ' + j.order.hold_until.slice(8, 10) + '/' + j.order.hold_until.slice(5, 7) + ' ' + j.order.hold_until.slice(11, 16) : ' · ' + eur(j.order.total));
    $('#dpdf').href = j.pdf; $('#drec').href = j.receipt; $('#ddet').href = j.detail; $('#dpdf').hidden = j.kind === 'reservation'; $('#drec').hidden = j.kind !== 'sale' && j.kind; $('#done').hidden = false; $('#dnew').focus(); }
  function reset(){ st.cart = {}; st.prods = {}; st.seats = {}; ['#fn', '#ln', '#em', '#ph', '#given', '#disc', '#promo', '#note', '#mx1', '#mx2'].forEach(function(s){ $(s).value = ''; }); $('#done').hidden = true; err(''); render(); load(); }
  $('#go').onclick = sell; $('#dnew').onclick = reset;
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') { if (!$('#done').hidden) reset(); else { st.cart = {}; st.prods = {}; render(); } } if (e.key === 'Enter' && !e.target.matches('textarea') && !$('#done').hidden) { reset(); e.preventDefault(); } else if (e.key === 'Enter' && e.target.id !== 'cq' && (e.ctrlKey || e.target === document.body)) { sell(); e.preventDefault(); } });
  load(); render(); setInterval(load, 20000);
})();
</script>
<script src="<?= asset('js/app.js') ?>"></script>
</body></html>
