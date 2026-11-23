/* Entrevo — graphiques SVG interactifs (aires/courbes avec réticule, colonnes avec info-bulle).
   Règles : courbe 2px, aire à 10 %, colonnes ≤ 24px à bout arrondi, grille fine et discrète, une seule échelle,
   légende dès 2 séries, textes jamais dans la couleur de la série, info-bulle construite avec textContent. */
(function () {
  'use strict';
  var NS = 'http://www.w3.org/2000/svg';
  var COLORS = ['#e0453a', '#9a968c'];
  function el(tag, attrs, parent) { var n = document.createElementNS(NS, tag); for (var k in attrs) n.setAttribute(k, attrs[k]); if (parent) parent.appendChild(n); return n; }
  function fmt(v, f) {
    if (f === 'money') return (v / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: v % 100 ? 2 : 0 });
    if (f === 'pct') return v.toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' %';
    return Math.round(v).toLocaleString('fr-FR');
  }
  function axisFmt(v, f) {
    var n = f === 'money' ? v / 100 : v;
    var s = Math.abs(n) >= 10000 ? (n / 1000).toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' k' : Math.round(n).toLocaleString('fr-FR');
    return f === 'money' ? s + ' €' : s;
  }
  function niceMax(max) {
    if (max <= 0) return 1;
    var p = Math.pow(10, Math.floor(Math.log10(max))), m = max / p;
    return (m <= 1 ? 1 : m <= 2 ? 2 : m <= 2.5 ? 2.5 : m <= 5 ? 5 : 10) * p;
  }
  function tooltip(box) {
    var t = document.createElement('div'); t.className = 'viz-tip'; t.hidden = true; box.appendChild(t); return t;
  }
  function fillTip(t, title, rows, f) {
    t.textContent = '';
    var h = document.createElement('div'); h.className = 'viz-tip-title'; h.textContent = title; t.appendChild(h);
    rows.forEach(function (r) {
      var line = document.createElement('div'); line.className = 'viz-tip-row';
      var key = document.createElement('i'); key.style.background = r.color; line.appendChild(key);
      var val = document.createElement('b'); val.textContent = fmt(r.value, f); line.appendChild(val);
      if (r.name) { var nm = document.createElement('span'); nm.textContent = r.name; line.appendChild(nm); }
      t.appendChild(line);
    });
  }
  function placeTip(t, box, x, y) {
    t.hidden = false;
    var bw = box.clientWidth, tw = t.offsetWidth;
    t.style.left = Math.max(0, Math.min(bw - tw, x + 12)) + 'px';
    t.style.top = Math.max(0, y - t.offsetHeight - 10) + 'px';
  }

  function render(box) {
    var d; try { d = JSON.parse(box.dataset.chart); } catch (e) { return; }
    box.textContent = '';
    var W = box.clientWidth || 600, H = box.clientHeight || 240, padL = 52, padR = 12, padT = 12, padB = 26;
    var series = d.series || [], labels = d.labels || [], n = labels.length, f = d.format || 'int';
    if (series.length >= 2) {
      var lg = document.createElement('div'); lg.className = 'viz-legend';
      series.forEach(function (s, i) { var it = document.createElement('span'); var k = document.createElement('i'); k.style.background = COLORS[i] || '#999'; it.appendChild(k); it.appendChild(document.createTextNode(s.name)); lg.appendChild(it); });
      box.appendChild(lg); padT = 34;
    }
    var max = 0; series.forEach(function (s) { s.values.forEach(function (v) { max = Math.max(max, +v || 0); }); });
    if (d.ref) max = Math.max(max, d.ref.value);
    max = niceMax(max * 1.05);
    var svg = el('svg', { width: W, height: H, viewBox: '0 0 ' + W + ' ' + H, class: 'viz-svg' }, box);
    var iw = W - padL - padR, ih = H - padT - padB;
    var y = function (v) { return padT + ih - (v / max) * ih; };
    for (var g = 0; g <= 4; g++) {
      var gv = max / 4 * g, gy = y(gv);
      el('line', { x1: padL, x2: W - padR, y1: gy, y2: gy, stroke: g ? '#ebe9e4' : '#c9c7c0', 'stroke-width': 1 }, svg);
      var tx = el('text', { x: padL - 8, y: gy + 4, 'text-anchor': 'end', class: 'viz-axis' }, svg); tx.textContent = axisFmt(gv, f);
    }
    var step = Math.max(1, Math.ceil(n / Math.max(2, Math.floor(iw / 70))));
    var tip = tooltip(box);
    if (d.type === 'columns') {
      var band = iw / Math.max(1, n), bw = Math.min(24, Math.max(4, band - 2));
      labels.forEach(function (l, i) {
        var v = +series[0].values[i] || 0, x = padL + band * i + (band - bw) / 2, top = y(v), hgt = padT + ih - top;
        var r = Math.min(4, bw / 2, hgt);
        var path = hgt > 0 ? 'M' + x + ',' + (padT + ih) + 'V' + (top + r) + 'Q' + x + ',' + top + ' ' + (x + r) + ',' + top + 'H' + (x + bw - r) + 'Q' + (x + bw) + ',' + top + ' ' + (x + bw) + ',' + (top + r) + 'V' + (padT + ih) + 'Z' : '';
        if (path) el('path', { d: path, fill: COLORS[0] }, svg);
        var hit = el('rect', { x: padL + band * i, y: padT, width: band, height: ih, fill: 'transparent', tabindex: 0 }, svg);
        var show = function (ev) { fillTip(tip, l, [{ color: COLORS[0], value: v, name: series[0].name }], f); var b = box.getBoundingClientRect(); placeTip(tip, box, (ev.clientX || b.left + x) - b.left, top); };
        hit.addEventListener('pointermove', show); hit.addEventListener('focus', show);
        hit.addEventListener('pointerleave', function () { tip.hidden = true; }); hit.addEventListener('blur', function () { tip.hidden = true; });
        if (i % step === 0) { var t = el('text', { x: padL + band * i + band / 2, y: H - 8, 'text-anchor': 'middle', class: 'viz-axis' }, svg); t.textContent = l; }
      });
      return;
    }
    // aire / courbe
    var x = function (i) { return padL + (n <= 1 ? iw / 2 : iw * i / (n - 1)); };
    if (d.ref) {
      el('line', { x1: padL, x2: W - padR, y1: y(d.ref.value), y2: y(d.ref.value), stroke: '#141414', 'stroke-width': 1 }, svg);
      var rt = el('text', { x: W - padR, y: y(d.ref.value) - 6, 'text-anchor': 'end', class: 'viz-axis viz-ref' }, svg); rt.textContent = d.ref.label;
    }
    series.slice().reverse().forEach(function (s, ri) {
      var i0 = series.length - 1 - ri, c = COLORS[i0] || '#999', pts = s.values.map(function (v, i) { return x(i) + ',' + y(+v || 0); });
      if (i0 === 0) el('path', { d: 'M' + x(0) + ',' + (padT + ih) + 'L' + pts.join('L') + 'L' + x(n - 1) + ',' + (padT + ih) + 'Z', fill: c, 'fill-opacity': 0.1 }, svg);
      el('polyline', { points: pts.join(' '), fill: 'none', stroke: c, 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }, svg);
    });
    var last = n - 1; if (last >= 0) {
      el('circle', { cx: x(last), cy: y(+series[0].values[last] || 0), r: 4, fill: COLORS[0], stroke: '#fff', 'stroke-width': 2 }, svg);
      var lv = el('text', { x: x(last) - 8, y: y(+series[0].values[last] || 0) - 10, 'text-anchor': 'end', class: 'viz-val' }, svg); lv.textContent = fmt(+series[0].values[last] || 0, f);
    }
    labels.forEach(function (l, i) { if (i % step === 0 || i === last) { var t = el('text', { x: x(i), y: H - 8, 'text-anchor': i === 0 ? 'start' : (i === last ? 'end' : 'middle'), class: 'viz-axis' }, svg); t.textContent = l; } });
    var cross = el('line', { y1: padT, y2: padT + ih, stroke: '#141414', 'stroke-width': 1, opacity: 0 }, svg);
    var dots = series.map(function (s, i) { return el('circle', { r: 5, fill: COLORS[i] || '#999', stroke: '#fff', 'stroke-width': 2, opacity: 0 }, svg); });
    var hit = el('rect', { x: padL, y: padT, width: iw, height: ih, fill: 'transparent', tabindex: 0 }, svg);
    var at = function (i) {
      i = Math.max(0, Math.min(n - 1, i));
      cross.setAttribute('x1', x(i)); cross.setAttribute('x2', x(i)); cross.setAttribute('opacity', 0.35);
      dots.forEach(function (dt, k) { dt.setAttribute('cx', x(i)); dt.setAttribute('cy', y(+series[k].values[i] || 0)); dt.setAttribute('opacity', 1); });
      fillTip(tip, (d.tips && d.tips[i]) || labels[i], series.map(function (s, k) { return { color: COLORS[k] || '#999', value: +s.values[i] || 0, name: series.length > 1 ? s.name : '' }; }), f);
      placeTip(tip, box, x(i), y(+series[0].values[i] || 0));
    };
    var cur = n - 1;
    hit.addEventListener('pointermove', function (ev) { var b = svg.getBoundingClientRect(); cur = Math.round((ev.clientX - b.left - padL) / iw * (n - 1)); at(cur); });
    hit.addEventListener('focus', function () { at(cur); });
    hit.addEventListener('keydown', function (ev) { if (ev.key === 'ArrowLeft') at(--cur); if (ev.key === 'ArrowRight') at(++cur); });
    var hide = function () { tip.hidden = true; cross.setAttribute('opacity', 0); dots.forEach(function (dt) { dt.setAttribute('opacity', 0); }); };
    hit.addEventListener('pointerleave', hide); hit.addEventListener('blur', hide);
  }
  function all() { document.querySelectorAll('[data-chart]').forEach(render); }
  all();
  var t; window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(all, 150); });
  window.EntrevoCharts = { render: render };
})();
