<?php /** @var array $data */ ?>
<p class="muted lead-sm">Des visuels prêts à publier, générés à partir de ta page : affiche, date, lieu, prix et un QR code qui mène à la billetterie (les ventes sont suivies dans tes statistiques, source « réseaux »).</p>
<div class="vis-controls card">
  <div class="field"><label>Message</label><div class="seg seg-full" id="vmode"><?php foreach (['annonce' => 'Annonce', 'derniers' => 'Dernières places', 'jourj' => 'C\'est ce soir', 'complet' => 'Complet'] as $k => $l): ?><label class="seg-r"><input type="radio" name="mode" value="<?= $k ?>" <?= $k === 'annonce' ? 'checked' : '' ?>><span><?= $l ?></span></label><?php endforeach; ?></div></div>
  <div class="field"><label>Style</label><div class="seg seg-full" id="vstyle"><?php foreach (['dark' => 'Sombre', 'light' => 'Clair', 'photo' => 'Plein cadre'] as $k => $l): ?><label class="seg-r"><input type="radio" name="style" value="<?= $k ?>" <?= $k === 'dark' ? 'checked' : '' ?>><span><?= $l ?></span></label><?php endforeach; ?></div></div>
  <div class="field"><label>Phrase d'accroche <small class="muted">(facultatif)</small></label><input id="vtag" maxlength="60" placeholder="Le rendez-vous de l'hiver"></div>
</div>
<div class="vis-grid">
  <?php foreach (['story' => ['Story Instagram / TikTok', 1080, 1920], 'post' => ['Publication Instagram', 1080, 1350], 'square' => ['Carré', 1080, 1080], 'banner' => ['Facebook / LinkedIn', 1200, 630]] as $k => [$l, $w, $h]): ?>
    <figure class="vis-item"><canvas data-format="<?= $k ?>" width="<?= $w ?>" height="<?= $h ?>"></canvas><figcaption><span><b><?= $l ?></b><small><?= $w ?> × <?= $h ?></small></span><button class="btn btn-sm btn-light" type="button" data-dl="<?= $k ?>">Télécharger</button></figcaption></figure>
  <?php endforeach; ?>
</div>
<script>
(() => {
  const D = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
  const load = src => new Promise(r => { if (!src) return r(null); const i = new Image(); i.onload = () => r(i); i.onerror = () => r(null); i.src = src; });
  const font = getComputedStyle(document.body).fontFamily;
  const wrap = (ctx, text, max) => { const words = text.split(' '), lines = []; let l = ''; words.forEach(w => { const t = l ? l + ' ' + w : w; if (ctx.measureText(t).width > max && l) { lines.push(l); l = w; } else l = t; }); if (l) lines.push(l); return lines; };
  const cover = (ctx, img, x, y, w, h) => { const r = Math.max(w / img.width, h / img.height), iw = img.width * r, ih = img.height * r; ctx.drawImage(img, x + (w - iw) / 2, y + (h - ih) / 2, iw, ih); };
  const rr = (ctx, x, y, w, h, r) => { ctx.beginPath(); ctx.roundRect ? ctx.roundRect(x, y, w, h, r) : ctx.rect(x, y, w, h); };
  let img, qr;
  const draw = () => {
    const mode = document.querySelector('[name=mode]:checked').value, style = document.querySelector('[name=style]:checked').value, tag = document.getElementById('vtag').value.trim();
    const badge = { annonce: D.price, derniers: D.left !== null && D.left < 200 ? 'Plus que ' + D.left + ' places' : 'Dernières places', jourj: 'C\'est ce soir', complet: 'Complet' }[mode];
    document.querySelectorAll('canvas[data-format]').forEach(cv => {
      const ctx = cv.getContext('2d'), W = cv.width, H = cv.height, land = W > H, u = Math.min(W, H) / 1080;
      const dark = style !== 'light', bg = dark ? '#0b0b0f' : '#f5f5f7', ink = dark ? '#ffffff' : '#1d1d1f', sub = dark ? 'rgba(255,255,255,.72)' : '#6e6e73';
      ctx.fillStyle = bg; ctx.fillRect(0, 0, W, H);
      const pad = 72 * u;
      // image
      let iy = 0, ih = 0;
      if (img) {
        if (style === 'photo') { cover(ctx, img, 0, 0, W, H); }
        else if (land) { ctx.save(); rr(ctx, pad, pad, H - 2 * pad, H - 2 * pad, 28 * u); ctx.clip(); cover(ctx, img, pad, pad, H - 2 * pad, H - 2 * pad); ctx.restore(); }
        else { ih = Math.min(W - 2 * pad, H * .52); iy = pad; ctx.save(); rr(ctx, pad, iy, W - 2 * pad, ih, 36 * u); ctx.clip(); cover(ctx, img, pad, iy, W - 2 * pad, ih); ctx.restore(); }
      }
      const tx = land && img && style !== 'photo' ? H + 8 * u : pad, tw = W - tx - pad;
      const photoInk = style === 'photo' ? '#fff' : ink, photoSub = style === 'photo' ? 'rgba(255,255,255,.8)' : sub;
      ctx.textBaseline = 'top';
      const q = (land ? 150 : 190) * u, qy = H - pad - q;
      const block = (y, on) => {
        const t = on ? (f) => f() : () => {};
        if (badge) { ctx.font = '600 ' + 30 * u + 'px ' + font; const bw = ctx.measureText(badge).width + 44 * u; rr(ctx, tx, y, bw, 58 * u, 29 * u);
        ctx.fillStyle = mode === 'complet' ? '#d70015' : (dark || style === 'photo' ? '#fff' : '#1d1d1f'); t(() => ctx.fill()); ctx.fillStyle = mode === 'complet' ? '#fff' : (dark || style === 'photo' ? '#1d1d1f' : '#fff'); T(badge, tx + 22 * u, y + 13 * u); y += 90 * u; }
      if (tag) { ctx.font = '500 ' + 34 * u + 'px ' + font; ctx.fillStyle = photoSub; T(tag, tx, y); y += 54 * u; }
      const size = (land ? 64 : (W === H ? 76 : 88)) * u; ctx.font = '700 ' + size + 'px ' + font; ctx.fillStyle = photoInk;
      wrap(ctx, D.name, tw).slice(0, 3).forEach(l => { T(l, tx, y); y += size * 1.08; });
      y += 18 * u; ctx.font = '500 ' + 36 * u + 'px ' + font; ctx.fillStyle = photoSub;
      [D.date, D.venue].filter(Boolean).forEach(l => { wrap(ctx, l, tw).slice(0, 2).forEach(x => { T(x, tx, y); y += 48 * u; }); });
        return y;
      };
      const T = (...a) => { if (T.on) ctx.fillText(...a); };
      T.on = false; const hgt = block(0, false);
      let y0 = land ? pad + 20 * u : iy + ih + 70 * u;
      if (style === 'photo') { y0 = Math.max(pad, qy - 60 * u - hgt); const g = ctx.createLinearGradient(0, y0 - 220 * u, 0, H); g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(.35, 'rgba(0,0,0,.72)'); g.addColorStop(1, 'rgba(0,0,0,.92)'); ctx.fillStyle = g; ctx.fillRect(0, 0, W, H); }
      T.on = true; block(y0, true);
      // pied : QR + adresse
      if (qr && mode !== 'complet') { rr(ctx, tx - 10 * u, qy - 10 * u, q + 20 * u, q + 20 * u, 18 * u); ctx.fillStyle = '#fff'; ctx.fill(); ctx.drawImage(qr, tx, qy, q, q); }
      ctx.font = '600 ' + 30 * u + 'px ' + font; ctx.fillStyle = photoInk; const fx = mode !== 'complet' ? tx + q + 36 * u : tx;
      ctx.fillText(mode === 'complet' ? 'Merci à toutes et à tous !' : 'Billets', fx, qy + q / 2 - 40 * u);
      ctx.font = '500 ' + 26 * u + 'px ' + font; ctx.fillStyle = photoSub; ctx.fillText(D.url.length > 38 ? D.url.slice(0, 36) + '…' : D.url, fx, qy + q / 2 + 2 * u); ctx.fillText(D.org, fx, qy + q / 2 + 40 * u);
    });
  };
  Promise.all([load(D.image), load(D.qr), document.fonts ? document.fonts.ready : null]).then(([a, b]) => { img = a; qr = b; draw(); });
  document.querySelectorAll('[name=mode],[name=style]').forEach(i => i.addEventListener('change', draw)); document.getElementById('vtag').addEventListener('input', draw);
  document.querySelectorAll('[data-dl]').forEach(b => b.addEventListener('click', () => { const cv = document.querySelector('canvas[data-format="' + b.dataset.dl + '"]'); const a = document.createElement('a'); a.download = (D.name.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-' + b.dataset.dl + '.png'); a.href = cv.toDataURL('image/png'); a.click(); }));
})();
</script>
