<?php /** @var array $ev, $blocks; int $artists */ ?>
<div class="pbe">
  <div class="stack">
    <div class="card"><h2>Compose ta page</h2><p class="muted mb0">Ajoute des blocs sous la description de l'événement : vidéo, line-up, galerie, partenaires… Glisse-les pour changer l'ordre, puis enregistre : la page publique est mise à jour aussitôt.</p>
      <div class="pbe-add mt"><?php foreach (PageBlocks::TYPES as $k => $l): ?><button type="button" data-add="<?= $k ?>"><?= e($l) ?></button><?php endforeach; ?></div></div>
    <div id="list" class="pbe-list"></div>
    <div class="row"><button type="button" class="btn" id="save">Enregistrer la page</button><a class="btn btn-light" target="_blank" href="<?= url('/e/' . $ev['slug']) ?>">Voir la page</a><span class="muted" id="msg"></span></div>
  </div>
  <div class="card pbe-preview"><div class="card-head"><h2>Aperçu</h2><small class="muted">après enregistrement</small></div><iframe id="pv" src="<?= url('/e/' . $ev['slug']) ?>#pb" title="Aperçu de la page"></iframe></div>
</div>
<script>
(function(){
  var B = <?= json_encode($blocks, JSON_UNESCAPED_UNICODE) ?>, TYPES = <?= json_encode(PageBlocks::TYPES, JSON_UNESCAPED_UNICODE) ?>, URL = <?= json_encode(url('/pro/evenements/' . $ev['id'] . '/blocs')) ?>, CSRF = <?= json_encode(csrf_token()) ?>, BASE = <?= json_encode(url('/')) ?>, ARTISTS = <?= (int)$artists ?>;
  var list = document.getElementById('list'), dragI = null;
  var esc = function(s){ return String(s == null ? '' : s).replace(/[&<>"]/g, function(c){ return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var src = function(p){ return p ? BASE.replace(/\/$/, '') + '/' + p : ''; };
  var NEW = { text: { title: '', body: '', align: 'left' }, image: { src: '', caption: '', wide: 0 }, gallery: { images: [] }, video: { url: '' }, lineup: { title: 'Line-up', auto: ARTISTS ? 1 : 0, items: [] }, highlights: { title: 'Pourquoi venir', items: [{ title: '', text: '' }, { title: '', text: '' }, { title: '', text: '' }] },
    quote: { text: '', author: '' }, button: { label: 'Réserver mes billets', url: '#billets' }, map: { title: 'Venir', address: '' }, partners: { title: 'Ils nous soutiennent', logos: [] }, countdown: { title: 'Le compte à rebours' }, divider: {} };
  function inp(i, k, v, ph, tag){ return tag === 'ta' ? '<textarea data-i="' + i + '" data-k="' + k + '" rows="4" placeholder="' + esc(ph || '') + '">' + esc(v) + '</textarea>' : '<input data-i="' + i + '" data-k="' + k + '" value="' + esc(v) + '" placeholder="' + esc(ph || '') + '">'; }
  function upl(i, k, sub){ return '<label class="btn btn-sm btn-light pbe-up">Ajouter une image<input type="file" accept="image/*" data-up="' + i + '" data-k="' + k + '"' + (sub !== undefined ? ' data-sub="' + sub + '"' : '') + ' hidden></label>'; }
  function body(b, i){ var h = '';
    if (!['button', 'divider'].includes(b.type)) h += '<div class="field"><label>Titre</label>' + inp(i, 'title', b.title, 'Titre du bloc (facultatif)') + '</div>';
    switch (b.type) {
      case 'text': h += '<div class="field"><label>Texte</label>' + inp(i, 'body', b.body, 'Ton texte…', 'ta') + '</div><label class="check"><input type="checkbox" data-i="' + i + '" data-k="align" data-on="center"' + (b.align === 'center' ? ' checked' : '') + '> Centrer</label>'; break;
      case 'image': h += (b.src ? '<img class="pbe-img" src="' + src(b.src) + '" alt="">' : '') + upl(i, 'src') + '<div class="field mt"><label>Légende</label>' + inp(i, 'caption', b.caption) + '</div><label class="check"><input type="checkbox" data-i="' + i + '" data-k="wide" data-on="1"' + (b.wide ? ' checked' : '') + '> Pleine largeur</label>'; break;
      case 'gallery': h += '<div class="pbe-thumbs">' + b.images.map(function(p, j){ return '<span><img src="' + src(p) + '" alt=""><button type="button" data-rm="' + i + '" data-j="' + j + '" data-k="images">×</button></span>'; }).join('') + '</div>' + upl(i, 'images', 'push'); break;
      case 'video': h += '<div class="field"><label>Lien YouTube ou Vimeo</label>' + inp(i, 'url', b.url, 'https://www.youtube.com/watch?v=…') + '</div>'; break;
      case 'lineup': h += '<label class="check"><input type="checkbox" data-i="' + i + '" data-k="auto" data-on="1"' + (b.auto ? ' checked' : '') + '> Reprendre automatiquement les artistes et horaires du module Artistes (' + ARTISTS + ')</label>';
        if (!b.auto) h += b.items.map(function(a, j){ return '<div class="pbe-sub">' + (a.photo ? '<img src="' + src(a.photo) + '" alt="">' : '') + '<input data-i="' + i + '" data-k="items" data-j="' + j + '" data-f="name" value="' + esc(a.name) + '" placeholder="Artiste"><input data-i="' + i + '" data-k="items" data-j="' + j + '" data-f="when" value="' + esc(a.when) + '" placeholder="Sam. 21h"><input data-i="' + i + '" data-k="items" data-j="' + j + '" data-f="text" value="' + esc(a.text) + '" placeholder="Style, une phrase">' + upl(i, 'items', j) + '<button type="button" class="btn btn-sm btn-ghost" data-rm="' + i + '" data-j="' + j + '" data-k="items">×</button></div>'; }).join('') + '<button type="button" class="btn btn-sm btn-light" data-push="' + i + '" data-k="items">+ Artiste</button>'; break;
      case 'highlights': h += b.items.map(function(a, j){ return '<div class="pbe-sub"><input data-i="' + i + '" data-k="items" data-j="' + j + '" data-f="title" value="' + esc(a.title) + '" placeholder="Ex. 3 scènes"><input data-i="' + i + '" data-k="items" data-j="' + j + '" data-f="text" value="' + esc(a.text) + '" placeholder="Une phrase"><button type="button" class="btn btn-sm btn-ghost" data-rm="' + i + '" data-j="' + j + '" data-k="items">×</button></div>'; }).join('') + (b.items.length < 6 ? '<button type="button" class="btn btn-sm btn-light" data-push="' + i + '" data-k="items">+ Point fort</button>' : ''); break;
      case 'quote': h += '<div class="field"><label>Citation</label>' + inp(i, 'text', b.text, '« Le meilleur festival de l\'été »', 'ta') + '</div><div class="field"><label>Auteur</label>' + inp(i, 'author', b.author, 'Le Progrès') + '</div>'; break;
      case 'button': h += '<div class="grid2"><div class="field"><label>Texte</label>' + inp(i, 'label', b.label) + '</div><div class="field"><label>Lien</label>' + inp(i, 'url', b.url, '#billets ou https://…') + '</div></div>'; break;
      case 'map': h += '<div class="field"><label>Adresse</label>' + inp(i, 'address', b.address, 'Vide = adresse de l\'événement') + '</div>'; break;
      case 'partners': h += '<div class="pbe-thumbs">' + b.logos.map(function(l, j){ return '<span><img src="' + src(l.src) + '" alt=""><input data-i="' + i + '" data-k="logos" data-j="' + j + '" data-f="url" value="' + esc(l.url) + '" placeholder="Lien du site"><button type="button" data-rm="' + i + '" data-j="' + j + '" data-k="logos">×</button></span>'; }).join('') + '</div>' + upl(i, 'logos', 'push'); break;
      case 'countdown': h += '<p class="muted mb0">Affiche le temps restant avant le début de l\'événement.</p>'; break;
      case 'divider': h += '<p class="muted mb0">Une ligne de séparation.</p>'; break;
    } return h; }
  function render(){ list.innerHTML = B.map(function(b, i){ return '<div class="card pbe-block" draggable="true" data-b="' + i + '"><div class="pbe-head"><span class="pbe-grip" title="Glisser">⋮⋮</span><b>' + esc(TYPES[b.type]) + '</b><span class="grow"></span><button type="button" class="btn btn-sm btn-ghost" data-mv="' + i + '" data-d="-1" aria-label="Monter">↑</button><button type="button" class="btn btn-sm btn-ghost" data-mv="' + i + '" data-d="1" aria-label="Descendre">↓</button><button type="button" class="btn btn-sm btn-ghost" data-del="' + i + '">Supprimer</button></div>' + body(b, i) + '</div>'; }).join('') || '<div class="card"><p class="muted mb0">Aucun bloc : choisis un type de bloc ci-dessus.</p></div>'; }
  document.querySelectorAll('[data-add]').forEach(function(btn){ btn.onclick = function(){ B.push(Object.assign({ type: btn.dataset.add, title: '' }, JSON.parse(JSON.stringify(NEW[btn.dataset.add])))); render(); list.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'center' }); }; });
  list.addEventListener('input', function(e){ var t = e.target, i = t.dataset.i; if (i === undefined || !t.dataset.k) return; var b = B[i];
    if (t.dataset.j !== undefined) { b[t.dataset.k][t.dataset.j][t.dataset.f] = t.value; return; }
    if (t.type === 'checkbox') { b[t.dataset.k] = t.checked ? (t.dataset.on === '1' ? 1 : t.dataset.on) : (t.dataset.k === 'align' ? 'left' : 0); if (t.dataset.k === 'auto') render(); return; }
    b[t.dataset.k] = t.value; });
  list.addEventListener('click', function(e){ var t = e.target.closest('button'); if (!t) return;
    if (t.dataset.del !== undefined && confirm('Supprimer ce bloc ?')) { B.splice(+t.dataset.del, 1); render(); }
    if (t.dataset.mv !== undefined) { var i = +t.dataset.mv, j = i + +t.dataset.d; if (j >= 0 && j < B.length) { var x = B[i]; B[i] = B[j]; B[j] = x; render(); } }
    if (t.dataset.rm !== undefined) { B[t.dataset.rm][t.dataset.k].splice(+t.dataset.j, 1); render(); }
    if (t.dataset.push !== undefined) { B[t.dataset.push].items.push(B[t.dataset.push].type === 'lineup' ? { name: '', when: '', text: '', photo: '' } : { title: '', text: '' }); render(); } });
  list.addEventListener('change', function(e){ var t = e.target; if (!t.dataset.up || !t.files[0]) return; var d = new FormData(); d.append('image', t.files[0]); d.append('_csrf', CSRF);
    fetch(URL, { method: 'POST', credentials: 'same-origin', body: d }).then(function(r){ return r.json(); }).then(function(j){ if (j.error) return alert(j.error); var b = B[t.dataset.up], k = t.dataset.k, s = t.dataset.sub;
      if (k === 'src') b.src = j.src; else if (k === 'images') b.images.push(j.src); else if (k === 'logos') b.logos.push({ src: j.src, url: '' }); else if (k === 'items') b.items[+s].photo = j.src; render(); }); });
  list.addEventListener('dragstart', function(e){ var c = e.target.closest('[data-b]'); if (c) dragI = +c.dataset.b; });
  list.addEventListener('dragover', function(e){ e.preventDefault(); });
  list.addEventListener('drop', function(e){ e.preventDefault(); var c = e.target.closest('[data-b]'); if (!c || dragI === null) return; var to = +c.dataset.b; var x = B.splice(dragI, 1)[0]; B.splice(to, 0, x); dragI = null; render(); });
  document.getElementById('save').onclick = function(){ var m = document.getElementById('msg'); fetch(URL, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF }, body: JSON.stringify({ blocks: B }) })
    .then(function(r){ return r.json(); }).then(function(j){ m.textContent = j.ok ? 'Page enregistrée (' + j.n + ' bloc(s)).' : (j.error || 'Erreur'); m.style.color = j.ok ? '#1a7f37' : '#b3261e'; var f = document.getElementById('pv'); f.src = f.src.split('#')[0] + '?r=' + Date.now() + '#pb'; }); };
  render();
})();
</script>
