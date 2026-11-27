// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const LIVRAISONS = __path.resolve(__dirname, '../livraisons') + '/';
const { chromium } = require('playwright');
const B = BASE;
(async () => { const br = await chromium.launch(); const c = await br.newContext({ viewport: { width: 390, height: 844 } }); const p = await c.newPage();
  await p.goto(B + '/connexion'); await p.fill('#email', 'orga@demo.entrevo'); await p.fill('#password', 'demo1234'); await p.click('button.btn-lg'); await p.waitForLoadState();
  for (const u of (process.argv[2] || '/pro/commandes,/pro,/pro/evenements/1/tarifs,/pro/evenements/1,/e/festival-lumieres-d-ete,/pro/participants,/pro/evenements/1/stocks,/pro/evenements/1/equipes').split(',')) {
    await p.goto(B + u); await p.waitForTimeout(300);
    const r = await p.evaluate(() => { const W = window.innerWidth; const out = []; document.querySelectorAll('body *').forEach(e => { const b = e.getBoundingClientRect(); if (b.right > W + 2 && b.width > 0 && getComputedStyle(e).position !== 'fixed') { let p = e.parentElement, clipped = false; while (p) { const o = getComputedStyle(p).overflowX; if (o === 'auto' || o === 'scroll' || o === 'hidden') { clipped = true; break; } p = p.parentElement; } if (!clipped) out.push(e.tagName + '.' + (e.className.baseVal ?? e.className).toString().slice(0, 40) + ' ' + Math.round(b.right)); } }); return [document.documentElement.scrollWidth, out.slice(0, 4)]; });
    console.log(u, r[0], r[1].join(' | '));
  } await br.close(); })();
