<?php /** @var array $ev, $presets; ?array $layout; ?string $poster, $logo */
$start = $layout ?: $presets['classique']; ?>
<div class="td">
  <div class="td-tools card">
    <h2>Modèles</h2>
    <div class="td-presets"><?php foreach ($presets as $k => $p): ?><button type="button" class="btn btn-sm btn-light" data-preset="<?= e($k) ?>"><?= e($p['name']) ?></button><?php endforeach; ?></div>
    <h2 class="mt">Ajouter</h2>
    <div class="td-add">
      <button type="button" data-add="text">Texte</button><button type="button" data-add="qr">QR code</button><button type="button" data-add="poster">Affiche</button>
      <button type="button" data-add="logo">Logo</button><button type="button" data-add="rect">Bloc de couleur</button><button type="button" data-add="line">Filet</button>
    </div>
    <h2 class="mt">Format</h2>
    <div class="grid2"><div class="field"><label>Largeur (mm)</label><input type="number" id="tw" min="60" max="190"></div><div class="field"><label>Hauteur (mm)</label><input type="number" id="th" min="40" max="260"></div></div>
    <div class="field"><label>Fond</label><input type="color" id="tbg"></div>
    <h2 class="mt">Variables</h2>
    <div class="td-vars"><?php foreach (TicketDesign::VARS as $k => $l): ?><button type="button" data-var="<?= e($k) ?>" title="<?= e($l) ?>"><?= e($k) ?></button><?php endforeach; ?></div>
    <small class="muted">Clique une variable pour l'insérer dans le texte sélectionné.</small>
  </div>
  <div class="td-main">
    <div class="card td-stage-wrap"><div class="td-stage" id="stage"></div>
      <p class="muted td-hint">Glisse les éléments pour les placer, tire le coin pour redimensionner. Flèches du clavier : déplacement fin (Maj : 5 mm). Suppr : effacer.</p></div>
    <div class="card td-props" id="props"><p class="muted">Sélectionne un élément pour le modifier.</p></div>
    <div class="row td-actions"><button type="button" class="btn" id="save">Enregistrer le billet</button><button type="button" class="btn btn-light" id="preview">Aperçu PDF</button>
      <?php if ($layout): ?><button type="button" class="btn btn-ghost" id="reset">Revenir au billet standard</button><?php endif; ?><span class="muted" id="msg"></span></div>
  </div>
</div>
<form id="pvf" method="post" action="<?= url('/pro/evenements/' . $ev['id'] . '/billet/apercu.pdf') ?>" target="_blank" hidden><?= csrf_field() ?><input type="hidden" name="layout"></form>
<script>
(function(){
  var PRESETS = <?= json_encode($presets, JSON_UNESCAPED_UNICODE) ?>, L = <?= json_encode($start, JSON_UNESCAPED_UNICODE) ?>, SAVE = <?= json_encode(url('/pro/evenements/' . $ev['id'] . '/billet')) ?>, CSRF = <?= json_encode(csrf_token()) ?>;
  var POSTER = <?= json_encode($poster) ?>, LOGO = <?= json_encode($logo) ?>, SAMPLE = <?= json_encode(TicketDesign::vars(['code' => 'K7Q2M9X4PZ', 'first_name' => 'Camille', 'last_name' => 'Exemple', 'rate_name' => 'Plein tarif', 'price' => 2500, 'type' => 'ticket'], ['ref' => 'E4K9T2QX', 'first_name' => '', 'last_name' => ''], $ev, current_org(), 0, 2, null), JSON_UNESCAPED_UNICODE) ?>;
  var stage = document.getElementById('stage'), props = document.getElementById('props'), sel = -1, S = 4;
  function scale(){ S = Math.min(5, (stage.parentNode.clientWidth - 40) / L.w); }
  function txt(t){ return String(t).replace(/\{[a-z]+\}/g, function(k){ return SAMPLE[k] !== undefined ? SAMPLE[k] : k; }); }
  function esc(s){ return String(s).replace(/[&<>"]/g, function(c){ return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function render(){ scale(); stage.style.width = L.w * S + 'px'; stage.style.height = L.h * S + 'px'; stage.style.background = L.bg;
    stage.innerHTML = L.elements.map(function(e, i){ var st = 'left:' + e.x * S + 'px;top:' + e.y * S + 'px;width:' + e.w * S + 'px;height:' + Math.max(2, e.h * S) + 'px;', inner = '';
      if (e.type === 'text') { st += 'font-size:' + (e.size * 0.3528 * S) + 'px;color:' + e.color + ';font-weight:' + (e.bold ? 800 : 400) + ';text-align:' + ({ L: 'left', C: 'center', R: 'right' })[e.align || 'L'] + ';line-height:' + e.h * S + 'px;' + (e.mono ? 'font-family:ui-monospace,Menlo,monospace;' : ''); inner = esc(txt(e.text)); }
      if (e.type === 'rect' || e.type === 'line') st += 'background:' + e.bg + ';border-radius:' + (e.radius || 0) * S + 'px;';
      if (e.type === 'qr') inner = '<svg viewBox="0 0 10 10" width="100%" height="100%"><path d="M0 0h3v3H0zM7 0h3v3H7zM0 7h3v3H0zM4 4h2v2H4zM7 7h1v1H7zM8 5h2v1H8zM4 0h1v2H4zM5 8h2v2H5z"/></svg>';
      if (e.type === 'poster' || e.type === 'logo') { var src = e.type === 'poster' ? POSTER : LOGO; inner = src ? '<img src="' + src + '" alt="">' : '<span>' + (e.type === 'poster' ? 'Affiche' : 'Logo') + '</span>'; }
      return '<div class="td-el td-' + e.type + (i === sel ? ' on' : '') + '" data-i="' + i + '" style="' + st + '">' + inner + (i === sel ? '<i class="td-rs" data-rs="' + i + '"></i>' : '') + '</div>'; }).join('');
    document.getElementById('tw').value = L.w; document.getElementById('th').value = L.h; document.getElementById('tbg').value = L.bg; panel(); }
  function panel(){ var e = L.elements[sel]; if (!e) { props.innerHTML = '<p class="muted">Sélectionne un élément pour le modifier.</p>'; return; }
    var h = '<div class="row between"><h2 class="u-m0">' + ({ text: 'Texte', qr: 'QR code', poster: 'Affiche', logo: 'Logo', rect: 'Bloc', line: 'Filet' })[e.type] + '</h2><div class="row"><button type="button" class="btn btn-sm btn-light" data-act="dup">Dupliquer</button><button type="button" class="btn btn-sm btn-light" data-act="front">Devant</button><button type="button" class="btn btn-sm btn-ghost" data-act="del">Supprimer</button></div></div>';
    if (e.type === 'text') h += '<div class="field mt"><label>Texte</label><input data-p="text" value="' + esc(e.text) + '"></div><div class="grid4"><div class="field"><label>Taille</label><input type="number" data-p="size" step="0.5" value="' + e.size + '"></div><div class="field"><label>Couleur</label><input type="color" data-p="color" value="' + e.color + '"></div><div class="field"><label>Alignement</label><select data-p="align">' + ['L', 'C', 'R'].map(function(a){ return '<option value="' + a + '"' + (a === e.align ? ' selected' : '') + '>' + ({ L: 'Gauche', C: 'Centre', R: 'Droite' })[a] + '</option>'; }).join('') + '</select></div><div class="field"><label>Style</label><label class="check"><input type="checkbox" data-p="bold"' + (e.bold ? ' checked' : '') + '> Gras</label><label class="check"><input type="checkbox" data-p="mono"' + (e.mono ? ' checked' : '') + '> Chasse fixe</label></div></div>';
    if (e.type === 'rect' || e.type === 'line') h += '<div class="grid2 mt"><div class="field"><label>Couleur</label><input type="color" data-p="bg" value="' + e.bg + '"></div><div class="field"><label>Arrondi (mm)</label><input type="number" data-p="radius" value="' + (e.radius || 0) + '"></div></div>';
    h += '<div class="grid4"><div class="field"><label>X (mm)</label><input type="number" step="0.5" data-p="x" value="' + e.x + '"></div><div class="field"><label>Y (mm)</label><input type="number" step="0.5" data-p="y" value="' + e.y + '"></div><div class="field"><label>Largeur</label><input type="number" step="0.5" data-p="w" value="' + e.w + '"></div><div class="field"><label>Hauteur</label><input type="number" step="0.5" data-p="h" value="' + e.h + '"></div></div>';
    props.innerHTML = h; }
  var drag = null;
  stage.addEventListener('pointerdown', function(ev){ var rs = ev.target.closest('[data-rs]'), el = ev.target.closest('.td-el'); if (!el) { sel = -1; render(); return; }
    sel = +el.dataset.i; var e = L.elements[sel]; drag = { mode: rs ? 'rs' : 'mv', sx: ev.clientX, sy: ev.clientY, x: e.x, y: e.y, w: e.w, h: e.h }; stage.setPointerCapture(ev.pointerId); render(); ev.preventDefault(); });
  stage.addEventListener('pointermove', function(ev){ if (!drag) return; var e = L.elements[sel], dx = (ev.clientX - drag.sx) / S, dy = (ev.clientY - drag.sy) / S, snap = function(v){ return Math.round(v * 2) / 2; };
    if (drag.mode === 'mv') { e.x = snap(Math.max(0, Math.min(L.w - e.w, drag.x + dx))); e.y = snap(Math.max(0, Math.min(L.h - Math.min(e.h, L.h), drag.y + dy))); }
    else { e.w = snap(Math.max(1, drag.w + dx)); e.h = snap(Math.max(e.type === 'line' ? 0.2 : 2, drag.h + dy)); if (e.type === 'qr') e.h = e.w; }
    render(); });
  stage.addEventListener('pointerup', function(){ drag = null; });
  props.addEventListener('input', function(ev){ var p = ev.target.dataset.p, e = L.elements[sel]; if (!p || !e) return; var v = ev.target.type === 'checkbox' ? (ev.target.checked ? 1 : 0) : ev.target.value;
    e[p] = ['x', 'y', 'w', 'h', 'size', 'radius'].indexOf(p) >= 0 ? parseFloat(v) || 0 : v; var keep = document.activeElement; render(); var again = props.querySelector('[data-p="' + p + '"]'); if (again && keep && keep.dataset.p === p) { again.focus(); if (again.setSelectionRange && again.type === 'text') { var n = again.value.length; again.setSelectionRange(n, n); } } });
  props.addEventListener('click', function(ev){ var a = ev.target.closest('[data-act]'); if (!a) return; var e = L.elements[sel];
    if (a.dataset.act === 'del') { L.elements.splice(sel, 1); sel = -1; } if (a.dataset.act === 'dup') { var c = JSON.parse(JSON.stringify(e)); c.x = Math.min(L.w - c.w, c.x + 4); c.y = Math.min(L.h - c.h, c.y + 4); L.elements.push(c); sel = L.elements.length - 1; }
    if (a.dataset.act === 'front') { L.elements.push(L.elements.splice(sel, 1)[0]); sel = L.elements.length - 1; } render(); });
  document.querySelectorAll('[data-add]').forEach(function(b){ b.onclick = function(){ var t = b.dataset.add, d = { text: { w: 80, h: 8, text: 'Ton texte', size: 11, bold: 0, color: '#1d1d1f', align: 'L', mono: 0 }, qr: { w: 34, h: 34 }, poster: { w: 50, h: 70 }, logo: { w: 30, h: 15 }, rect: { w: 60, h: 20, bg: '#0071e3', radius: 2 }, line: { w: 100, h: 0.4, bg: '#d2d2d7' } }[t];
    L.elements.push(Object.assign({ type: t, x: 8, y: 8 }, d)); sel = L.elements.length - 1; render(); }; });
  document.querySelectorAll('[data-preset]').forEach(function(b){ b.onclick = function(){ if (L.elements.length && !confirm('Remplacer la maquette actuelle par le modèle « ' + b.textContent + ' » ?')) return; L = JSON.parse(JSON.stringify(PRESETS[b.dataset.preset])); sel = -1; render(); }; });
  document.querySelectorAll('[data-var]').forEach(function(b){ b.onclick = function(){ var e = L.elements[sel]; if (!e || e.type !== 'text') { var nt = { type: 'text', x: 8, y: 8, w: 80, h: 8, text: b.dataset.var, size: 11, bold: 0, color: '#1d1d1f', align: 'L', mono: 0 }; L.elements.push(nt); sel = L.elements.length - 1; } else e.text = (e.text + ' ' + b.dataset.var).trim(); render(); }; });
  ['tw', 'th', 'tbg'].forEach(function(id){ document.getElementById(id).addEventListener('change', function(){ if (id === 'tbg') L.bg = this.value; else L[id === 'tw' ? 'w' : 'h'] = Math.max(id === 'tw' ? 60 : 40, Math.min(id === 'tw' ? 190 : 260, parseFloat(this.value) || 100)); render(); }); });
  document.addEventListener('keydown', function(ev){ if (sel < 0 || ev.target.matches('input,select,textarea')) return; var e = L.elements[sel], st = ev.shiftKey ? 5 : 0.5;
    if (ev.key === 'Delete' || ev.key === 'Backspace') { L.elements.splice(sel, 1); sel = -1; render(); ev.preventDefault(); return; }
    var m = { ArrowLeft: [-st, 0], ArrowRight: [st, 0], ArrowUp: [0, -st], ArrowDown: [0, st] }[ev.key]; if (m) { e.x = Math.max(0, e.x + m[0]); e.y = Math.max(0, e.y + m[1]); render(); ev.preventDefault(); } });
  function msg(t, ok){ var m = document.getElementById('msg'); m.textContent = t; m.style.color = ok ? '#1a7f37' : '#b3261e'; }
  document.getElementById('save').onclick = function(){ fetch(SAVE, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF }, body: JSON.stringify({ layout: L }) }).then(function(r){ return r.json(); }).then(function(j){ msg(j.error || 'Enregistré : les prochains billets utiliseront cette maquette (les billets déjà envoyés se mettent aussi à jour au téléchargement).', !j.error); }); };
  document.getElementById('preview').onclick = function(){ var f = document.getElementById('pvf'); f.layout.value = JSON.stringify(L); f.submit(); };
  var rb = document.getElementById('reset'); if (rb) rb.onclick = function(){ if (!confirm('Revenir au billet standard ?')) return; var d = new FormData(); d.append('action', 'reset'); d.append('_csrf', CSRF); fetch(SAVE, { method: 'POST', credentials: 'same-origin', body: d }).then(function(){ location.reload(); }); };
  window.addEventListener('resize', render); render();
})();
</script>
