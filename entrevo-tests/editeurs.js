// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const LIVRAISONS = __path.resolve(__dirname, '../livraisons') + '/';
const { chromium } = require('playwright');
const B = BASE, C = CAPT;
(async () => { const br = await chromium.launch(); const p = await (await br.newContext({ viewport: { width: 1440, height: 1000 } })).newPage(); const errs = [];
  p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.accept());
  await p.goto(B + '/connexion'); await p.fill('#email', 'orga@demo.entrevo'); await p.fill('#password', 'demo1234'); await p.click('button.btn-lg'); await p.waitForLoadState();
  // billet
  await p.goto(B + '/pro/evenements/2/billet'); await p.click('[data-preset=affiche]'); await p.click('.td-el.td-text >> nth=0'); await p.fill('#props [data-p=size]', '19');
  await p.click('[data-add=text]'); await p.fill('#props [data-p=text]', 'Merci de venir 30 min avant'); await p.click('#save'); await p.waitForTimeout(800); console.log('billet :', await p.textContent('#msg'));
  await p.screenshot({ path: C + 'editeur-billet.png' });
  const r = await p.request.post(B + '/pro/evenements/2/billet/apercu.pdf', { form: { _csrf: await p.evaluate(() => document.querySelector('#pvf [name=_csrf]').value), layout: '' } }); console.log('aperçu PDF :', r.status(), r.headers()['content-type'], (await r.body()).length);
  // page par blocs
  await p.goto(B + '/pro/evenements/1/blocs');
  for (const t of ['highlights', 'video', 'lineup', 'image', 'quote', 'map', 'countdown', 'button']) await p.click('[data-add=' + t + ']');
  const hl = p.locator('.pbe-block >> nth=0'); await hl.locator('[data-f=title] >> nth=0').fill('3 scènes'); await hl.locator('[data-f=text] >> nth=0').fill('Rock, électro et jazz en plein air');
  await hl.locator('[data-f=title] >> nth=1').fill('40 artistes'); await hl.locator('[data-f=text] >> nth=1').fill('De la découverte aux têtes d\'affiche'); await hl.locator('[data-f=title] >> nth=2').fill('100 % local'); await hl.locator('[data-f=text] >> nth=2').fill('Bars et food trucks de la région');
  await p.locator('.pbe-block >> nth=1').locator('[data-k=url]').fill('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
  await p.locator('.pbe-block >> nth=3').locator('input[type=file]').setInputFiles(__path.resolve(__dirname, '../entrevo/uploads/demo/fest.jpg')); await p.waitForTimeout(800);
  await p.locator('.pbe-block >> nth=4').locator('[data-k=text]').fill('Le rendez-vous incontournable de l\'été lyonnais'); await p.locator('.pbe-block >> nth=4').locator('[data-k=author]').fill('Le Progrès');
  await p.click('#save'); await p.waitForTimeout(1500); console.log('page :', await p.textContent('#msg'));
  await p.screenshot({ path: C + 'editeur-page.png' });
  const q = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage(); q.on('pageerror', e => errs.push(e.message)); await q.goto(B + '/e/festival-lumieres-d-ete'); await q.waitForTimeout(800);
  console.log('blocs publics :', await q.locator('.pb').count()); await q.locator('#pb').scrollIntoViewIfNeeded(); await q.screenshot({ path: C + 'page-blocs.png', fullPage: true });
  console.log('JS', errs); await br.close(); })();
