/* Entrevo — éditeur de campagnes e-mail : blocs, aperçu en direct, compteur d'audience. */
(function () {
  'use strict';
  var form = document.getElementById('camp-form');
  if (!form) return;
  var blocks = []; try { blocks = JSON.parse(form.dataset.blocks || '[]'); } catch (e) { blocks = []; }
  var events = []; try { events = JSON.parse(form.dataset.events || '[]'); } catch (e) { events = []; }
  var list = document.getElementById('blocks'), input = document.getElementById('blocks-input'), frame = document.getElementById('pv');
  var NAMES = { heading: '🔠 Titre', text: '📝 Texte', event: '🎟️ Événement', button: '🔘 Bouton', image: '🖼️ Image', divider: '➖ Séparateur' };
  var DEFAULTS = { heading: { text: 'Un titre qui donne envie' }, text: { text: '' }, event: { event_id: events[0] ? events[0].id : 0, label: '' }, button: { label: 'J’y vais', url: '' }, image: { url: '' }, divider: {} };

  function el(tag, props, kids) {
    var n = document.createElement(tag);
    Object.keys(props || {}).forEach(function (k) { if (k === 'text') n.textContent = props[k]; else if (k === 'class') n.className = props[k]; else n.setAttribute(k, props[k]); });
    (kids || []).forEach(function (c) { if (c) n.appendChild(c); });
    return n;
  }
  function field(label, node) { var f = el('label', { class: 'blk-field' }, [el('span', { text: label }), node]); return f; }
  function bind(node, b, key) { node.value = b[key] == null ? '' : b[key]; node.addEventListener('input', function () { b[key] = node.value; changed(); }); return node; }

  function render() {
    list.textContent = '';
    blocks.forEach(function (b, i) {
      var head = el('div', { class: 'blk-head' }, [el('b', { text: NAMES[b.type] || b.type }), el('span', { class: 'grow' })]);
      [['↑', -1, 'Monter'], ['↓', 1, 'Descendre']].forEach(function (m) {
        var bt = el('button', { type: 'button', class: 'blk-btn', 'aria-label': m[2], title: m[2], text: m[0] });
        bt.disabled = (m[1] < 0 && i === 0) || (m[1] > 0 && i === blocks.length - 1);
        bt.onclick = function () { var j = i + m[1]; var t = blocks[i]; blocks[i] = blocks[j]; blocks[j] = t; render(); changed(); };
        head.appendChild(bt);
      });
      var del = el('button', { type: 'button', class: 'blk-btn del', 'aria-label': 'Supprimer le bloc', title: 'Supprimer', text: '✕' });
      del.onclick = function () { blocks.splice(i, 1); render(); changed(); };
      head.appendChild(del);
      var body = el('div', { class: 'blk-body' });
      if (b.type === 'heading') body.appendChild(field('Titre', bind(el('input', { maxlength: 200 }), b, 'text')));
      if (b.type === 'text') { var ta = bind(el('textarea', { rows: 4, placeholder: 'Ton message… (**gras**, {prenom})' }), b, 'text'); body.appendChild(field('Texte', ta)); }
      if (b.type === 'button') { body.appendChild(field('Texte du bouton', bind(el('input', { maxlength: 80 }), b, 'label'))); body.appendChild(field('Lien', bind(el('input', { type: 'url', placeholder: 'https://…' }), b, 'url'))); }
      if (b.type === 'image') body.appendChild(field('Adresse de l’image', bind(el('input', { type: 'url', placeholder: 'https://… (.jpg, .png)' }), b, 'url')));
      if (b.type === 'event') {
        var sel = el('select');
        events.forEach(function (e) { var o = el('option', { value: e.id, text: e.name + ' · ' + e.date }); sel.appendChild(o); });
        sel.value = b.event_id; sel.onchange = function () { b.event_id = parseInt(sel.value, 10); changed(); };
        body.appendChild(field('Événement', sel));
        body.appendChild(field('Texte du bouton', bind(el('input', { maxlength: 80, placeholder: 'Je prends ma place 🎟️' }), b, 'label')));
        if (!events.length) body.appendChild(el('p', { class: 'muted', text: 'Crée d’abord un événement.' }));
      }
      var card = el('div', { class: 'blk', draggable: 'true' }, [head, body]);
      card.dataset.i = i;
      card.addEventListener('dragstart', function (e) { e.dataTransfer.setData('text/plain', String(i)); card.classList.add('dragging'); });
      card.addEventListener('dragend', function () { card.classList.remove('dragging'); });
      card.addEventListener('dragover', function (e) { e.preventDefault(); card.classList.add('over'); });
      card.addEventListener('dragleave', function () { card.classList.remove('over'); });
      card.addEventListener('drop', function (e) {
        e.preventDefault(); card.classList.remove('over');
        var from = parseInt(e.dataTransfer.getData('text/plain'), 10), to = i;
        if (isNaN(from) || from === to) return;
        var m = blocks.splice(from, 1)[0]; blocks.splice(to, 0, m); render(); changed();
      });
      // les champs restent sélectionnables : on ne déclenche le glisser que depuis l'en-tête
      body.addEventListener('mousedown', function () { card.draggable = false; });
      body.addEventListener('mouseup', function () { card.draggable = true; });
      list.appendChild(card);
    });
    if (!blocks.length) list.appendChild(el('p', { class: 'muted center', text: 'Ton e-mail est vide. Ajoute un premier bloc 👇' }));
    input.value = JSON.stringify(blocks);
  }

  document.querySelectorAll('[data-add]').forEach(function (b) {
    b.onclick = function () { var t = b.dataset.add; blocks.push(Object.assign({ type: t }, JSON.parse(JSON.stringify(DEFAULTS[t])))); render(); changed(); var last = list.lastElementChild; if (last && last.querySelector('input,textarea')) last.querySelector('input,textarea').focus(); };
  });

  // ---------- aperçu ----------
  var timer = null, countTimer = null;
  function payload(extra) {
    input.value = JSON.stringify(blocks);
    var fd = new FormData(form); fd.delete('action');
    if (extra) Object.keys(extra).forEach(function (k) { fd.set(k, extra[k]); });
    return fd;
  }
  function preview() {
    fetch(form.dataset.preview, { method: 'POST', body: payload(), credentials: 'same-origin', headers: { 'X-CSRF': form.querySelector('[name=_csrf]').value } })
      .then(function (r) { return r.text(); }).then(function (h) { frame.srcdoc = h; }).catch(function () { /* réseau : on garde l'aperçu précédent */ });
  }
  function changed() { dirty = true; input.value = JSON.stringify(blocks); clearTimeout(timer); timer = setTimeout(preview, 450); }
  function count() {
    clearTimeout(countTimer);
    countTimer = setTimeout(function () {
      fetch(form.dataset.preview, { method: 'POST', body: payload({ count: '1' }), credentials: 'same-origin', headers: { 'X-CSRF': form.querySelector('[name=_csrf]').value } })
        .then(function (r) { return r.json(); }).then(function (j) {
          var n = j.count || 0, box = document.getElementById('aud-count');
          box.textContent = ''; var b = document.createElement('b'); b.textContent = n.toLocaleString('fr-FR'); box.appendChild(b);
          box.appendChild(document.createTextNode(' destinataire' + (n > 1 ? 's' : '')));
          box.classList.toggle('zero', n === 0);
        }).catch(function () { /* rien */ });
    }, 300);
  }
  form.querySelectorAll('[data-aud]').forEach(function (n) { n.addEventListener('change', count); });
  var subj = document.getElementById('c-subject'), pre = document.getElementById('c-pre');
  subj.addEventListener('input', function () { document.getElementById('subj-len').textContent = subj.value.length; document.getElementById('pv-subj').textContent = subj.value || '(sans objet)'; changed(); });
  pre.addEventListener('input', function () { document.getElementById('pv-pre').textContent = pre.value; changed(); });
  document.querySelectorAll('[data-size]').forEach(function (b) {
    b.onclick = function () { document.querySelectorAll('[data-size]').forEach(function (x) { x.classList.toggle('on', x === b); }); document.getElementById('pv-wrap').classList.toggle('mobile', b.dataset.size === 'mobile'); };
  });
  form.addEventListener('submit', function () { input.value = JSON.stringify(blocks); });
  // quitter avec des changements non enregistrés
  var dirty = false; form.addEventListener('input', function () { dirty = true; }); form.addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  render(); preview();
})();
