/* Entrevo — choix des places sur le plan de salle */
(function () {
  'use strict';
  var form = document.getElementById('buy'); if (!form || !form.dataset.seatmap) return;
  var modal = document.getElementById('seat-modal'), canvas = document.getElementById('seat-canvas');
  var picked = {}; // clé -> rateId
  var data = null, info = {};
  var rates = [].slice.call(form.querySelectorAll('.qty[data-rate]'));
  function need() { // places demandées par tarif
    var n = {}; rates.forEach(function (q) { n[q.dataset.rate] = (parseInt(q.querySelector('input').value, 10) || 0) * parseInt(q.dataset.pack || '1', 10); }); return n;
  }
  function countFor(rid) { return Object.keys(picked).filter(function (k) { return picked[k] === rid; }).length; }
  function rateFor(cat) {
    var n = need(), best = null;
    rates.forEach(function (q) { var rid = q.dataset.rate; if (best || countFor(rid) >= n[rid]) return; if (!q.dataset.cat || q.dataset.cat === cat) best = rid; });
    return best;
  }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function sync() {
    var n = need(), tot = 0, got = 0, box = document.getElementById('seat-inputs'), html = '', list = [];
    Object.keys(picked).forEach(function (k) { if (!n[picked[k]] || countFor(picked[k]) > n[picked[k]]) delete picked[k]; });
    Object.keys(n).forEach(function (rid) { tot += n[rid]; });
    Object.keys(picked).forEach(function (k) { got++; html += '<input type="hidden" name="seats[' + picked[k] + '][]" value="' + esc(k) + '">'; var s = info[k]; list.push(s ? s[5] + ' ' + s[6] + s[7] : k); });
    box.innerHTML = html;
    document.getElementById('seat-count').textContent = tot ? got + ' / ' + tot : '';
    document.getElementById('seat-picked').textContent = got ? '✅ ' + list.join(' · ') : (tot ? '👉 Ouvre le plan pour choisir ' + tot + ' place' + (tot > 1 ? 's' : '') + '.' : 'Choisis tes billets, puis clique sur les places du plan.');
    if (data) draw();
  }
  function draw() {
    var maxX = 0, maxY = 0, cats = {}, s = 26, pad = 30;
    (data.categories || []).forEach(function (c) { cats[c.id] = c; });
    data.seats.forEach(function (x) { maxX = Math.max(maxX, x[1]); maxY = Math.max(maxY, x[2]); });
    var w = (maxX + 1) * s + pad * 2, h = (maxY + 1) * s + pad * 2 + 40;
    var svg = '<svg viewBox="0 0 ' + w + ' ' + h + '" width="' + w + '" style="max-width:100%;height:auto"><rect x="' + pad + '" y="6" width="' + (w - pad * 2) + '" height="24" fill="#2a2a2a"/><text x="' + w / 2 + '" y="23" text-anchor="middle" fill="#ccc" font-size="13" font-family="sans-serif" letter-spacing="3">' + esc((data.stage || 'Scène').toUpperCase()) + '</text>';
    data.seats.forEach(function (x) {
      var k = x[0], cx = pad + x[1] * s + s / 2, cy = 40 + pad + x[2] * s + s / 2, cat = cats[x[3]] || { color: '#888' };
      var mine = picked[k], st = x[4];
      var fill = mine ? '#ff5a4e' : (st ? '#333' : cat.color);
      svg += '<g class="seat' + (st && !mine ? ' taken' : '') + '" data-k="' + esc(k) + '"><title>' + esc(x[5] + ' · Rang ' + x[6] + ' · Place ' + x[7] + (st === 1 && !mine ? ' (prise)' : (st === 2 ? ' (indisponible)' : ' — ' + (cat.name || '')))) + '</title>'
        + '<rect x="' + (cx - 10) + '" y="' + (cy - 10) + '" width="20" height="20" rx="4" fill="' + fill + '" stroke="' + (mine ? '#fff' : 'none') + '" stroke-width="2" opacity="' + (st && !mine ? .35 : 1) + '"/>'
        + (mine ? '<text x="' + cx + '" y="' + (cy + 4) + '" text-anchor="middle" font-size="11" font-weight="700" fill="#111">✓</text>' : '') + '</g>';
    });
    canvas.innerHTML = svg + '</svg>';
  }
  canvas.addEventListener('click', function (e) {
    var g = e.target.closest('.seat'); if (!g) return;
    var k = g.dataset.k, s = info[k]; if (!s) return;
    if (picked[k]) { delete picked[k]; sync(); return; }
    if (s[4]) return;
    var rid = rateFor(s[3]);
    if (!rid) { document.getElementById('seat-hint').textContent = '🙅 Pas de billet disponible pour cette catégorie de place. Ajoute un billet du bon tarif, ou libère une place.'; return; }
    picked[k] = rid; document.getElementById('seat-hint').textContent = 'Clique sur une place libre pour la prendre, reclique pour la libérer.'; sync();
  });
  function load() {
    fetch(form.dataset.seatmap, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      data = j; info = {}; j.seats.forEach(function (x) { info[x[0]] = x; });
      var lg = ''; (j.categories || []).forEach(function (c) { lg += '<span><i style="background:' + esc(c.color) + '"></i>' + esc(c.name) + '</span>'; });
      document.getElementById('seat-legend').innerHTML = lg + '<span><i style="background:#ff5a4e"></i>Tes places</span><span><i style="background:#333"></i>Prise</span>';
      draw();
    });
  }
  document.getElementById('seat-open').onclick = function () { modal.hidden = false; if (!data) load(); else draw(); };
  document.getElementById('seat-close').onclick = function () { modal.hidden = true; sync(); };
  modal.addEventListener('click', function (e) { if (e.target === modal) { modal.hidden = true; sync(); } });
  form.addEventListener('input', sync); form.addEventListener('click', function (e) { if (e.target.closest('.qty button')) setTimeout(sync, 0); });
  form.addEventListener('submit', function (e) {
    if (e.submitter && e.submitter.value !== 'buy') return;
    var n = need(), ok = true; Object.keys(n).forEach(function (rid) { if (countFor(rid) !== n[rid]) ok = false; });
    if (!ok) { e.preventDefault(); modal.hidden = false; if (!data) load(); document.getElementById('seat-hint').textContent = '💺 Choisis une place pour chaque billet avant de continuer.'; }
  });
  sync();
})();
