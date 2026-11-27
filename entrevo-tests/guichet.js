// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const LIVRAISONS = __path.resolve(__dirname, '../livraisons') + '/';
const { chromium } = require('playwright');
const B = BASE, C = CAPT;
(async () => { const br = await chromium.launch(); const p = await (await br.newContext({ viewport: { width: 1440, height: 900 } })).newPage(); const errs = [];
  p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.accept(d.type() === 'prompt' ? 'cb' : undefined));
  await p.goto(B + '/connexion'); await p.fill('#email', 'orga@demo.entrevo'); await p.fill('#password', 'demo1234'); await p.click('button.btn-lg'); await p.waitForLoadState();
  await p.goto(B + '/pro/evenements/1/guichet'); await p.waitForSelector('[data-rate]');
  await p.click('[data-rate] >> nth=0'); await p.click('[data-rate] >> nth=0'); await p.click('[data-rate] >> nth=3');
  await p.fill('#disc', '10'); await p.click('[data-bill="100"]');
  console.log('total :', await p.textContent('#tot'), '| rendu :', (await p.textContent('#chg')).replace(/\s+/g, ' '));
  await p.fill('#fn', 'Hugo'); await p.fill('#ln', 'Guichet'); await p.screenshot({ path: C + 'guichet-1.png' });
  await p.click('#go'); await p.waitForSelector('#done:not([hidden])'); console.log('vente :', (await p.textContent('#dd')).trim());
  await p.screenshot({ path: C + 'guichet-2-ok.png' }); await p.click('#dnew');
  // réservation
  await p.click('[data-k="reservation"]'); await p.click('[data-rate] >> nth=1'); await p.fill('#ln', 'Réservé'); await p.fill('#fn', 'Inès'); await p.fill('#ph', '0601020304');
  await p.click('#go'); await p.waitForSelector('#done:not([hidden])'); console.log('réservation :', (await p.textContent('#dd')).trim()); await p.click('#dnew');
  await p.waitForSelector('[data-rpay]'); await p.screenshot({ path: C + 'guichet-3-resa.png', fullPage: true });
  await p.click('[data-rpay] >> nth=0'); await p.waitForSelector('#done:not([hidden])'); console.log('réservation encaissée :', (await p.textContent('#dt')).trim(), (await p.textContent('#dd')).trim());
  await p.click('#dnew'); await p.waitForTimeout(800); console.log('aujourd\'hui :', (await p.textContent('#today')).replace(/\s+/g, ' ').slice(0, 200));
  // recherche client
  await p.fill('#cq', 'dur'); await p.waitForTimeout(800); console.log('clients :', (await p.locator('#sug button').count()));
  const m = await (await br.newContext({ viewport: { width: 820, height: 1180 } })).newPage(); await m.context().addCookies(await p.context().cookies()); await m.goto(B + '/pro/evenements/2/guichet'); await m.waitForSelector('[data-rate]'); await m.screenshot({ path: C + 'guichet-4-tablette.png' });
  console.log('JS', errs); await br.close(); })();
