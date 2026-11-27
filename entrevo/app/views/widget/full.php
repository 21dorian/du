<?php /** @var array $ev, $org, $ws, $sessions, $rates, $products, $fields; ?array $session; float $fpct; int $ffix; bool $seatmap */
$dark = $ws['theme'] === 'dark'; $popup = input('mode') === 'popup';
$orderFields = array_values(array_filter($fields, fn($f) => !$f['per_ticket']));
$feeBuyer = $org['fee_mode'] === 'buyer';
$anyOn = (bool)array_filter($rates, fn($r) => $r['sale']['ok']);
$self = url('/widget/achat/' . $ev['slug']);
?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($ev['name']) ?> · Billetterie</title>
<style>
:root{--c:<?= e($ws['color']) ?>;--r:<?= (int)$ws['radius'] ?>px;--bg:<?= $dark ? '#16161a' : '#fff' ?>;--bg2:<?= $dark ? '#222228' : '#f5f5f7' ?>;--t:<?= $dark ? '#f5f5f7' : '#1d1d1f' ?>;--m:<?= $dark ? '#a1a1aa' : '#6e6e73' ?>;--l:<?= $dark ? '#303038' : '#e5e5ea' ?>}
*{box-sizing:border-box}html,body{margin:0;background:<?= $popup ? 'var(--bg)' : 'transparent' ?>;color:var(--t);font:15px/1.45 -apple-system,BlinkMacSystemFont,"SF Pro Text","Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif}
.w{background:var(--bg);border:<?= $popup ? '0' : '1px solid var(--l)' ?>;border-radius:var(--r);overflow:hidden;max-width:640px;margin:0 auto}
.hd{display:flex;gap:14px;align-items:center;padding:16px 18px;border-bottom:1px solid var(--l)}.hd img{width:64px;height:64px;object-fit:cover;border-radius:calc(var(--r) * .6)}
.hd b{display:block;font-size:17px;line-height:1.2}.hd small{color:var(--m)}
.steps{display:flex;gap:6px;padding:12px 18px 0}.steps i{flex:1;height:4px;border-radius:4px;background:var(--l)}.steps i.on{background:var(--c)}
.pane{padding:14px 18px 18px}.pane[hidden]{display:none}
h3{margin:4px 0 10px;font-size:15px}
.dates{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;margin-bottom:12px}
.dates a{display:block;padding:10px;border:1.5px solid var(--l);border-radius:12px;text-decoration:none;color:inherit;font-size:13.5px}.dates a b{display:block;font-size:14.5px}
.dates a.on{border-color:var(--c);box-shadow:0 0 0 3px color-mix(in srgb,var(--c) 18%,transparent)}.dates a.full{opacity:.45;pointer-events:none}
.rate{display:grid;grid-template-columns:1fr auto;gap:4px 12px;align-items:center;padding:12px 0;border-bottom:1px solid var(--l)}.rate:last-child{border-bottom:0}
.rate .n{font-weight:600}.rate small{display:block;color:var(--m);font-size:12.5px}.rate .p{font-weight:700;text-align:right}.rate .p small{font-weight:400}
.rate.off{opacity:.55}.badge{display:inline-block;margin-left:6px;padding:1px 8px;border-radius:999px;background:var(--t);color:var(--bg);font-size:11px;font-weight:700;vertical-align:middle}
.left{color:#e5372b;font-size:12.5px;font-weight:600}
.q{display:flex;align-items:center;gap:6px;grid-column:2}.q button{width:34px;height:34px;border-radius:50%;border:1.5px solid var(--l);background:var(--bg);color:var(--t);font-size:18px;cursor:pointer}
.q button:hover{border-color:var(--c)}.q input{width:34px;text-align:center;border:0;background:transparent;color:var(--t);font:700 16px inherit;-moz-appearance:textfield}.q input::-webkit-inner-spin-button{display:none}
.field{display:flex;flex-direction:column;gap:4px;margin-bottom:10px}.field label{font-size:13px;font-weight:600}
input[type=text],input[type=email],input[type=tel],input[type=date],input[type=number]:not(.qi),select,textarea{width:100%;padding:11px 12px;border:1.5px solid var(--l);border-radius:12px;background:var(--bg);color:var(--t);font:inherit}
input:focus,select:focus,textarea:focus{outline:0;border-color:var(--c)}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.chk{display:flex;gap:8px;align-items:flex-start;font-size:13.5px;margin:8px 0}
.promo{display:flex;gap:8px;margin-top:10px}.promo input{flex:1}
.sum{margin-top:12px;padding:12px 14px;border-radius:14px;background:var(--bg2);font-size:14px}.sum div{display:flex;justify-content:space-between;gap:10px;padding:2px 0}.sum .tot{font-size:17px;font-weight:800;padding-top:6px;margin-top:6px;border-top:1px solid var(--l)}
.btn{display:flex;width:100%;justify-content:center;align-items:center;gap:8px;margin-top:14px;padding:14px 20px;border:0;border-radius:999px;background:var(--c);color:#fff;font:600 16px inherit;cursor:pointer;text-decoration:none}
.btn[disabled]{opacity:.45;cursor:not-allowed}.btn.ghost{background:transparent;color:var(--t);border:1.5px solid var(--l)}
.back{background:none;border:0;color:var(--m);cursor:pointer;font:inherit;padding:0;margin-bottom:8px}
.err{background:#ffe9e7;color:#b3261e;border-radius:12px;padding:10px 12px;font-size:13.5px;margin-top:10px}.err[hidden]{display:none}
.ok{text-align:center;padding:10px 0}.ok .ic{width:64px;height:64px;border-radius:50%;background:#e3f9e9;color:#1a7f37;display:grid;place-items:center;margin:0 auto 10px;font-size:32px}
.tk{display:flex;justify-content:space-between;border:1px dashed var(--l);border-radius:12px;padding:10px 12px;margin-top:8px;font-size:13.5px;text-align:left}.tk code{font-weight:700}
.wait{text-align:center;padding:16px 0}.spin{width:36px;height:36px;border:3px solid var(--l);border-top-color:var(--c);border-radius:50%;animation:s 1s linear infinite;margin:0 auto 10px}@keyframes s{to{transform:rotate(360deg)}}
.foot{padding:10px 18px 14px;color:var(--m);font-size:12px;text-align:center}.foot a{color:var(--m)}
.muted{color:var(--m)}.hp{position:absolute;left:-9999px}
@media (max-width:420px){.g2{grid-template-columns:1fr}}
</style></head>
<body><div class="w" id="w">
  <div class="hd"><?php if ($ws['show_image'] && $ev['image']): ?><img src="<?= e(abs_url($ev['image'])) ?>" alt=""><?php endif; ?>
    <div><b><?= e($ev['name']) ?></b><small><?= e($session ? fr_date($session['starts_at']) : fr_date($ev['starts_at'])) ?><?= $ev['venue'] ? ' · ' . e($ev['venue']) : '' ?></small><?php if ($ws['show_desc'] && $ev['description']): ?><small><?= e(mb_strimwidth(strip_tags((string)$ev['description']), 0, 140, '…')) ?></small><?php endif; ?></div></div>
  <div class="steps" aria-hidden="true"><i class="on" data-st="1"></i><i data-st="2"></i><i data-st="3"></i></div>

  <section class="pane" id="p1">
    <?php if (RatesPlus::launchPending($ev)): ?>
      <p><b>Ouverture de la billetterie le <?= e(fr_date((string)$ev['on_sale_at'])) ?>.</b></p>
      <a class="btn" target="_blank" rel="noopener" href="<?= e(abs_url('/e/' . $ev['slug'])) ?>">Être prévenu(e) de l'ouverture</a>
    <?php elseif ($seatmap): ?>
      <p>Cet événement est en placement numéroté : choisis tes places sur le plan de salle.</p>
      <a class="btn" target="_blank" rel="noopener" href="<?= e(abs_url('/e/' . $ev['slug'], ['utm_source' => 'widget'])) ?>">Choisir mes places</a>
    <?php else: ?>
      <?php if ($sessions): ?><h3>Choisis ta date</h3><div class="dates"><?php foreach ($sessions as $x): $full = isset($x['left']) && $x['left'] !== null && (int)$x['left'] <= 0; ?>
        <a href="<?= e($self . '?seance=' . (int)$x['id'] . (input('mode') ? '&mode=' . e((string)input('mode')) : '') . '&host=' . rawurlencode((string)input('host')) . (input('tarifs') ? '&tarifs=' . rawurlencode((string)input('tarifs')) . '&k=' . rawurlencode((string)input('k')) : '')) ?>" class="<?= $session && (int)$session['id'] === (int)$x['id'] ? 'on' : '' ?> <?= $full ? 'full' : '' ?>"><b><?= e(date('d/m', strtotime($x['starts_at']))) ?> · <?= e(date('H\hi', strtotime($x['starts_at']))) ?></b><?= $x['label'] ? e($x['label']) : e(fr_date($x['starts_at'], false)) ?><?= $full ? ' · complet' : '' ?></a><?php endforeach; ?></div><?php endif; ?>
      <?php if ($rates): ?><h3>Billets</h3><?php endif; ?>
      <?php foreach ($rates as $r): $ok = $r['sale']['ok']; $max = $ok ? min(20, (int)$r['sale']['remaining'], (int)$r['max_per_order'] ?: 20, (int)$ev['max_per_order'] ?: 20) : 0; $fee = Tickets::unitFee($org, (int)$r['price']); ?>
        <div class="rate <?= $max ? '' : 'off' ?>">
          <div><span class="n"><?= e($r['name']) ?></span><?php if (!empty($r['badge'])): ?><span class="badge"><?= e($r['badge']) ?></span><?php endif; ?>
            <?php if ($r['description']): ?><small><?= e($r['description']) ?></small><?php endif; ?>
            <?php if (!$ok): ?><small><?= e($r['sale']['why'] ?? '') ?></small><?php elseif (!empty($r['left_label'])): ?><span class="left"><?= e($r['left_label']) ?></span><?php endif; ?></div>
          <div class="p"><?= $r['price_mode'] === 'free' ? 'Prix libre' : ((int)$r['price'] ? e(money((int)$r['price'])) : 'Gratuit') ?><?php if ($fee && $feeBuyer): ?><small>+ <?= e(money($fee)) ?> de frais</small><?php endif; ?></div>
          <?php if ($max): ?><div class="q" data-rate="<?= (int)$r['id'] ?>" data-min="<?= (int)($r['min_per_order'] ?? 0) ?>"><button type="button" data-d="-1" aria-label="Moins">−</button><input class="qi" type="number" value="0" min="0" max="<?= $max ?>" aria-label="Quantité <?= e($r['name']) ?>" readonly><button type="button" data-d="1" aria-label="Plus">+</button></div><?php endif; ?>
          <?php if ($max && $r['price_mode'] === 'free'): ?><div class="field" style="grid-column:1/-1;margin:6px 0 0"><label>Ton prix (min. <?= e(money((int)$r['price'])) ?>)</label><input type="text" inputmode="decimal" data-free="<?= (int)$r['id'] ?>" value="<?= e(money_input((int)$r['price'])) ?>"></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if ($sessions && !$session): ?><p class="muted">Choisis une date pour voir les billets.</p><?php elseif (!$rates): ?><p class="muted">Pas de billets en vente pour l'instant.</p><?php endif; ?>
      <?php if ($products && $anyOn): ?><h3 style="margin-top:14px">Options</h3><?php foreach ($products as $pr): ?>
        <div class="rate"><div><span class="n"><?= e($pr['name']) ?></span><?php if ($pr['description']): ?><small><?= e($pr['description']) ?></small><?php endif; ?></div><div class="p"><?= e(money((int)$pr['price'])) ?></div>
          <div class="q" data-product="<?= (int)$pr['id'] ?>"><button type="button" data-d="-1" aria-label="Moins">−</button><input class="qi" type="number" value="0" min="0" max="20" readonly aria-label="Quantité <?= e($pr['name']) ?>"><button type="button" data-d="1" aria-label="Plus">+</button></div></div>
      <?php endforeach; endif; ?>
      <?php if ($anyOn && $ws['promo']): ?><div class="promo"><input type="text" id="promo" placeholder="Code promo ou d'accès" autocomplete="off"><button type="button" class="btn ghost" id="promo-ok" style="width:auto;margin:0;padding:10px 16px">OK</button></div><?php endif; ?>
      <?php if ($anyOn): ?><div class="sum" id="sum1" hidden></div><div class="err" id="e1" hidden></div>
      <button type="button" class="btn" id="go2" disabled>Continuer</button><?php endif; ?>
    <?php endif; ?>
  </section>

  <form class="pane" id="p2" hidden autocomplete="on" enctype="multipart/form-data">
    <button type="button" class="back" data-back="1">← Modifier les billets</button>
    <h3>Tes coordonnées</h3>
    <div class="g2"><div class="field"><label for="fn">Prénom</label><input type="text" id="fn" name="first_name" autocomplete="given-name" required></div><div class="field"><label for="ln">Nom</label><input type="text" id="ln" name="last_name" autocomplete="family-name" required></div></div>
    <div class="field"><label for="em">E-mail (tes billets y sont envoyés)</label><input type="email" id="em" name="email" autocomplete="email" required></div>
    <div class="field"><label for="ph">Téléphone (facultatif)</label><input type="tel" id="ph" name="phone" autocomplete="tel"></div>
    <?php if (Buyers::requiredAge($ev, array_map(fn($r) => (int)$r['id'], $rates))): ?><div class="field"><label for="bd">Date de naissance</label><input type="date" id="bd" name="birth_date" required></div><?php endif; ?>
    <?php foreach ($orderFields as $f) echo custom_field_html($f, 'f[' . (int)$f['id'] . ']', ''); // 4.2 : même rendu que le site (fichiers, pays, conditions) ?>
    <label class="chk"><input type="checkbox" name="optin" value="1"> Je veux recevoir les actus de <?= e($org['name']) ?></label>
    <?php if (setting('cgv')): ?><label class="chk"><input type="checkbox" name="cgv" value="1" required> J'accepte les <a href="<?= e(abs_url('/cgv')) ?>" target="_blank" rel="noopener">conditions de vente</a></label><?php endif; ?>
    <input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div class="sum" id="sum2"></div><div class="err" id="e2" hidden></div>
    <button class="btn" id="pay">Payer</button>
    <p class="muted" style="font-size:12px;text-align:center;margin:8px 0 0">Le paiement s'ouvre dans une fenêtre sécurisée. Tes billets arrivent aussi par e-mail.</p>
  </form>

  <section class="pane" id="p3" hidden>
    <div class="wait" id="w3"><div class="spin"></div><b>Paiement en cours dans la fenêtre sécurisée</b><p class="muted">Termine ton paiement : cette page se met à jour toute seule.</p>
      <a class="btn ghost" id="reopen" target="entrevo_pay" href="#">Rouvrir la fenêtre de paiement</a></div>
    <div class="ok" id="ok3" hidden><div class="ic">✓</div><b style="font-size:18px">C'est réservé !</b><p class="muted" id="okmail"></p><div id="tks"></div>
      <a class="btn" id="pdf" target="_blank" rel="noopener" href="#">Télécharger mes billets</a><a class="btn ghost" id="ord" target="_blank" rel="noopener" href="#">Gérer ma commande</a></div>
    <div class="err" id="e3" hidden></div>
  </section>
  <div class="foot">Billetterie sécurisée <a href="<?= e(abs_url('/e/' . $ev['slug'])) ?>" target="_blank" rel="noopener"><?= e((string)setting('platform_name', 'Entrevo')) ?></a></div>
</div>
<script>
(function(){
  var SELF = <?= json_encode($self) ?>, SEANCE = <?= (int)($session['id'] ?? 0) ?>, HOST = <?= json_encode((string)input('host')) ?>, st = { qty: {}, prod: {}, price: {}, promo: '' }, order = null, timer = null, win = null;
  var $ = function(s){ return document.querySelector(s); }, $$ = function(s){ return document.querySelectorAll(s); };
  var eur = function(c){ return (c / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' }); };
  function height(){ try { parent.postMessage({ entrevo: 'height', h: document.documentElement.scrollHeight }, '*'); } catch (e) {} }
  new ResizeObserver(height).observe(document.body); height();
  function show(n){ ['p1', 'p2', 'p3'].forEach(function(p, i){ $('#' + p).hidden = i + 1 !== n; }); $$('[data-st]').forEach(function(s){ s.classList.toggle('on', +s.dataset.st <= n); }); height(); }
  function body(){ var f = new FormData(); if (SEANCE) f.append('seance', SEANCE); f.append('host', HOST);
    Object.keys(st.qty).forEach(function(k){ if (st.qty[k] > 0) f.append('qty[' + k + ']', st.qty[k]); });
    Object.keys(st.prod).forEach(function(k){ if (st.prod[k] > 0) f.append('product[' + k + ']', st.prod[k]); });
    Object.keys(st.price).forEach(function(k){ f.append('price[' + k + ']', st.price[k]); }); if (st.promo) f.append('promo', st.promo);
    var qs = new URLSearchParams(location.search); if (qs.get('tarifs')) { f.append('tarifs', qs.get('tarifs')); f.append('k', qs.get('k') || ''); } return f; }
  function err(id, m){ var e = $(id); e.innerHTML = m; e.hidden = !m; height(); }
  function quote(){ var n = Object.values(st.qty).reduce(function(a, b){ return a + b; }, 0); $('#go2').disabled = !n; if (!n) { $('#sum1').hidden = true; height(); return; }
    fetch(SELF + '/total', { method: 'POST', body: body() }).then(function(r){ return r.json(); }).then(function(j){
      var h = j.lines.map(function(l){ return '<div><span>' + l.qty + ' × ' + esc(l.name) + '</span><span>' + eur(l.amount) + '</span></div>'; }).join('');
      if (j.discount) h += '<div><span>Réduction' + (j.promo ? ' (' + esc(j.promo) + ')' : '') + '</span><span>−' + eur(j.discount) + '</span></div>';
      if (j.fees) h += '<div><span>Frais de service</span><span>' + eur(j.fees) + '</span></div>';
      h += '<div class="tot"><span>Total</span><span>' + eur(j.total) + '</span></div>';
      $('#sum1').innerHTML = h; $('#sum2').innerHTML = h; $('#sum1').hidden = false; $('#pay').textContent = j.total > 0 ? 'Payer ' + eur(j.total) : 'Valider ma réservation';
      err('#e1', j.promo_error ? esc(j.promo_error) : ''); height(); }); }
  function esc(s){ return String(s).replace(/[&<>"]/g, function(c){ return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  $$('.q').forEach(function(q){ var inp = q.querySelector('input'), min = +(q.dataset.min || 0);
    q.addEventListener('click', function(e){ var b = e.target.closest('[data-d]'); if (!b) return; var v = +inp.value + +b.dataset.d;
      if (min > 1 && +b.dataset.d > 0 && v > 0 && v < min) v = min; if (min > 1 && +b.dataset.d < 0 && v < min) v = 0;
      v = Math.max(0, Math.min(+inp.max, v)); inp.value = v; if (q.dataset.rate) st.qty[q.dataset.rate] = v; else st.prod[q.dataset.product] = v; quote(); }); });
  $$('[data-free]').forEach(function(i){ i.addEventListener('change', function(){ st.price[i.dataset.free] = i.value; quote(); }); });
  if ($('#promo-ok')) $('#promo-ok').onclick = function(){ st.promo = $('#promo').value.trim(); quote(); };
  if ($('#go2')) $('#go2').onclick = function(){ show(2); var f = $('#fn'); if (f) f.focus(); };
  $$('[data-back]').forEach(function(b){ b.onclick = function(){ show(+b.dataset.back); }; });
  $('#p2').addEventListener('submit', function(e){ e.preventDefault(); var btn = $('#pay'); btn.disabled = true; err('#e2', '');
    var f = body(); new FormData($('#p2')).forEach(function(v, k){ f.append(k, v); }); f.append('email2', $('#em').value);
    // fenêtre ouverte tout de suite (sinon bloquée par le navigateur), remplie après la création de la commande
    win = window.open('', 'entrevo_pay', 'width=520,height=760');
    fetch(SELF + '/commande', { method: 'POST', body: f }).then(function(r){ return r.json(); }).then(function(j){ btn.disabled = false;
      if (j.errors) { if (win) win.close(); err('#e2', j.errors.map(esc).join('<br>')); return; }
      order = j; show(3);
      if (j.free) { if (win) win.close(); poll(); return; }
      $('#reopen').href = j.pay_url; if (win) win.location = j.pay_url; else { $('#reopen').hidden = false; }
      poll(); }).catch(function(){ btn.disabled = false; if (win) win.close(); err('#e2', 'Connexion perdue, réessaie.'); }); });
  function poll(){ clearTimeout(timer); fetch(<?= json_encode(url('/widget/commande/')) ?> + order.ref + '?t=' + order.token).then(function(r){ return r.json(); }).then(function(j){
      if (j.status === 'paid') { $('#w3').hidden = true; $('#ok3').hidden = false; $('#okmail').textContent = 'Tes billets sont envoyés à ' + j.email + '.';
        $('#tks').innerHTML = j.tickets.map(function(t){ return '<div class="tk"><span>' + esc(t.rate_name) + (t.seat ? ' · ' + esc(t.seat) : '') + '<br><small class="muted">' + esc((t.first_name + ' ' + t.last_name).trim()) + '</small></span><code>' + esc(t.code) + '</code></div>'; }).join('');
        $('#pdf').href = j.pdf_url; $('#ord').href = j.order_url; try { if (win && !win.closed) win.close(); } catch (e) {} height(); return; }
      if (j.status === 'cancelled' || j.status === 'expired') { $('#w3').hidden = true; err('#e3', 'Le paiement n\'a pas abouti. <a href="' + SELF + '">Recommencer</a>'); return; }
      timer = setTimeout(poll, 3000); }).catch(function(){ timer = setTimeout(poll, 5000); }); }
})();
</script>
<script>
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
</script>
</body></html>
