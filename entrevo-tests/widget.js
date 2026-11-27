// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const LIVRAISONS = __path.resolve(__dirname, '../livraisons') + '/';
const { chromium } = require('playwright');
const C = CAPT;
(async () => { const br = await chromium.launch(); const ctx = await br.newContext({ viewport: { width: 1100, height: 1000 } }); const p = await ctx.newPage(); const errs = [];
  ctx.on('page', pg => pg.on('pageerror', e => errs.push('popup ' + e.message))); p.on('pageerror', e => errs.push(e.message)); p.on('console', m => { if (m.type() === 'error') errs.push('console ' + m.text()); });
  await p.goto('http://127.0.0.1:8099/'); await p.waitForTimeout(1500);
  const f = p.frameLocator('iframe[data-entrevo]').first();
  await f.locator('.q[data-rate] [data-d="1"]').first().click(); await f.locator('.q[data-rate] [data-d="1"]').first().click(); await p.waitForTimeout(800);
  console.log('total :', (await f.locator('#sum1').innerText()).replace(/\n/g, ' | '));
  const h = await p.locator('iframe[data-entrevo]').first().evaluate(e => e.style.height); console.log('hauteur auto :', h);
  await p.screenshot({ path: C + 'widget-1.png', fullPage: true });
  await f.locator('#go2').click(); await f.locator('#fn').fill('Léa'); await f.locator('#ln').fill('Widget'); await f.locator('#em').fill('lea.widget@exemple.fr');
  const cgv = f.locator('input[name=cgv]'); if (await cgv.count()) await cgv.check();
  await p.screenshot({ path: C + 'widget-2.png', fullPage: true });
  const [pop] = await Promise.all([ctx.waitForEvent('page'), f.locator('#pay').click()]);
  await pop.waitForLoadState(); await pop.waitForTimeout(800); console.log('fenêtre de paiement :', pop.url().replace(/\?.*/, ''));
  await pop.screenshot({ path: C + 'widget-3-paiement.png' });
  // paiement de démonstration
  const demo = pop.locator('button:has-text("Payer"), button.btn-lg, button[type=submit]').first(); if (await demo.count()) { await demo.click().catch(() => {}); }
  await p.waitForTimeout(5000);
  console.log('widget :', (await f.locator('#ok3').isVisible()) ? (await f.locator('#ok3').innerText()).replace(/\n/g, ' | ').slice(0, 200) : 'pas confirmé');
  console.log('popup fermée :', pop.isClosed());
  await p.screenshot({ path: C + 'widget-4-ok.png', fullPage: true });
  // bouton overlay
  await p.click('button:has-text("Billets jazz")'); await p.waitForTimeout(1500); await p.screenshot({ path: C + 'widget-5-bouton.png' });
  console.log('JS', errs); await br.close(); })();
