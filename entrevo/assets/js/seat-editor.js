/* Entrevo — éditeur de plan de salle (organisateur) */
(function () {
  'use strict';
  var field = document.getElementById('seatmap-json'); if (!field) return;
  var m = JSON.parse(field.value || '{}');
  m.categories = m.categories || []; m.blocks = m.blocks || []; m.disabled = m.disabled || [];
  var off = {}; m.disabled.forEach(function (k) { off[k] = 1; });
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function rowLabel(start, i) {
    if (/^\d+$/.test(start)) return String(parseInt(start, 10) + i);
    var n = 0; start.toUpperCase().split('').forEach(function (ch) { n = n * 26 + ch.charCodeAt(0) - 64; }); n += i;
    var s = ''; while (n > 0) { var r = (n - 1) % 26; s = String.fromCharCode(65 + r) + s; n = Math.floor((n - 1) / 26); } return s;
  }
  function seats() {
    var out = [];
    m.blocks.forEach(function (b) {
      for (var r = 0; r < Math.min(60, b.rows); r++) for (var c = 0; c < Math.min(80, b.cols); c++) {
        var row = rowLabel(b.row_start || 'A', r), num = (parseInt(b.num_start, 10) || 1) + c;
        out.push({ k: b.id + '-' + row + num, x: (+b.x || 0) + c, y: (+b.y || 0) + r, cat: b.cat, b: b.name, row: row, num: num });
      }
    });
    return out;
  }
  function save() { m.disabled = Object.keys(off); field.value = JSON.stringify(m); }
  function draw() {
    var list = seats(), cats = {}, s = 22, pad = 20, maxX = 0, maxY = 0, n = 0;
    m.categories.forEach(function (c) { cats[c.id] = c; });
    list.forEach(function (x) { maxX = Math.max(maxX, x.x); maxY = Math.max(maxY, x.y); if (!off[x.k]) n++; });
    var w = (maxX + 1) * s + pad * 2, h = (maxY + 1) * s + pad * 2 + 34;
    var svg = '<svg viewBox="0 0 ' + w + ' ' + h + '" width="' + w + '" style="max-width:100%;height:auto"><rect x="' + pad + '" y="4" width="' + (w - pad * 2) + '" height="20" fill="#333"/><text x="' + w / 2 + '" y="18" fill="#ddd" font-size="11" text-anchor="middle" font-family="sans-serif">' + esc((m.stage || 'Scène').toUpperCase()) + '</text>';
    list.forEach(function (x) {
      var cx = pad + x.x * s, cy = 34 + pad + x.y * s, col = (cats[x.cat] || { color: '#888' }).color;
      svg += '<g class="seat" data-k="' + esc(x.k) + '"><title>' + esc(x.b + ' · ' + x.row + x.num) + '</title><rect x="' + cx + '" y="' + cy + '" width="18" height="18" rx="3" fill="' + (off[x.k] ? '#222' : col) + '" stroke="' + (off[x.k] ? '#555' : 'none') + '"/>'
        + (off[x.k] ? '<text x="' + (cx + 9) + '" y="' + (cy + 13) + '" font-size="11" fill="#777" text-anchor="middle">✕</text>' : '') + '</g>';
    });
    document.getElementById('ed-canvas').innerHTML = svg + '</svg>';
    document.getElementById('ed-count').textContent = n + ' places en vente · ' + Object.keys(off).length + ' indisponibles';
    save();
  }
  function input(label, val, attrs) { return '<div class="field"><label>' + label + '</label><input ' + attrs + ' value="' + esc(val) + '"></div>'; }
  function renderCats() {
    document.getElementById('ed-cats').innerHTML = m.categories.map(function (c, i) {
      return '<div class="row" style="gap:6px;flex-wrap:nowrap;margin-bottom:8px"><input type="color" data-c="' + i + '" data-f="color" value="' + esc(c.color) + '"><input data-c="' + i + '" data-f="name" value="' + esc(c.name) + '"><button type="button" class="btn btn-sm btn-light" data-delcat="' + i + '" aria-label="Supprimer la catégorie"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>';
    }).join('');
    renderBlocks();
  }
  function renderBlocks() {
    var catOpts = function (sel) { return m.categories.map(function (c) { return '<option value="' + esc(c.id) + '"' + (c.id === sel ? ' selected' : '') + '>' + esc(c.name) + '</option>'; }).join(''); };
    document.getElementById('ed-blocks').innerHTML = m.blocks.map(function (b, i) {
      return '<details class="box"' + (i === m.blocks.length - 1 ? ' open' : '') + '><summary><span>' + esc(b.name) + '</span><span class="muted">' + b.rows + ' × ' + b.cols + '</span></summary><div style="padding:14px">'
        + '<div class="grid2">' + input('Nom', b.name, 'data-b="' + i + '" data-f="name"') + input('Code (court)', b.id, 'data-b="' + i + '" data-f="id" maxlength="8"')
        + input('Rangs', b.rows, 'type="number" min="1" max="60" data-b="' + i + '" data-f="rows"') + input('Sièges par rang', b.cols, 'type="number" min="1" max="80" data-b="' + i + '" data-f="cols"')
        + input('Premier rang', b.row_start, 'data-b="' + i + '" data-f="row_start"') + input('Premier numéro', b.num_start, 'type="number" min="1" data-b="' + i + '" data-f="num_start"')
        + input('Position X', b.x, 'type="number" min="0" data-b="' + i + '" data-f="x"') + input('Position Y', b.y, 'type="number" min="0" data-b="' + i + '" data-f="y"') + '</div>'
        + '<div class="field"><label>Catégorie</label><select data-b="' + i + '" data-f="cat">' + catOpts(b.cat) + '</select></div>'
        + '<button type="button" class="btn btn-sm btn-light" data-delblock="' + i + '">Supprimer ce bloc</button></div></details>';
    }).join('');
    draw();
  }
  document.addEventListener('input', function (e) {
    var t = e.target, f = t.dataset.f;
    if (t.id === 'ed-stage') { m.stage = t.value; draw(); return; }
    if (t.dataset.c !== undefined) { m.categories[t.dataset.c][f] = t.value; draw(); return; }
    if (t.dataset.b !== undefined) { var b = m.blocks[t.dataset.b]; b[f] = ['rows', 'cols', 'x', 'y', 'num_start'].indexOf(f) >= 0 ? (parseInt(t.value, 10) || 0) : (f === 'id' ? t.value.toUpperCase().replace(/[^A-Z0-9]/g, '') : t.value); draw(); }
  });
  document.addEventListener('change', function (e) { if (e.target.dataset.b !== undefined && e.target.dataset.f === 'cat') { m.blocks[e.target.dataset.b].cat = e.target.value; draw(); } });
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (t.dataset.delcat !== undefined) { m.categories.splice(+t.dataset.delcat, 1); renderCats(); }
    if (t.dataset.delblock !== undefined) { m.blocks.splice(+t.dataset.delblock, 1); renderBlocks(); }
    var g = t.closest && t.closest('#ed-canvas .seat'); if (g) { var k = g.dataset.k; if (off[k]) delete off[k]; else off[k] = 1; draw(); }
  });
  document.getElementById('ed-addcat').onclick = function () { var id = 'cat' + Date.now().toString(36).slice(-4); m.categories.push({ id: id, name: 'Nouvelle catégorie', color: '#' + Math.floor(Math.random() * 0xffffff).toString(16).padStart(6, '0') }); renderCats(); };
  document.getElementById('ed-addblock').onclick = function () {
    var maxX = 0; m.blocks.forEach(function (b) { maxX = Math.max(maxX, (+b.x || 0) + (+b.cols || 0)); });
    m.blocks.push({ id: 'B' + (m.blocks.length + 1), name: 'Bloc ' + (m.blocks.length + 1), rows: 6, cols: 8, x: maxX + 2, y: 0, row_start: 'A', num_start: 1, cat: (m.categories[0] || {}).id || '' }); renderBlocks();
  };
  document.getElementById('ed-stage').value = m.stage || 'Scène';
  renderCats();
})();
