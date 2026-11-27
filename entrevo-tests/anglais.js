// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const LIVRAISONS = __path.resolve(__dirname, '../livraisons') + '/';
const { chromium } = require('playwright'); const { execSync } = require('child_process');
const B = BASE, C = CAPT;
const sql = (q) => execSync(`mysql -N entrevo -e "${q}"`).toString().trim();
const out = (k, v) => console.log(k.padEnd(22), String(v).replace(/\s+/g, ' ').slice(0, 300));
(async () => { const br = await chromium.launch(); const errs = [];
  const b = await (await br.newContext({ viewport: { width: 1280, height: 900 } })).newPage(); b.on('pageerror', e => errs.push(e.message)); b.on('console', m => m.type() === 'error' && errs.push(m.text()));
  await b.goto(B + '/e/festival-lumieres-d-ete?lang=en'); await b.waitForTimeout(500);
  out('h1', await b.locator('h1').first().innerText()); out('lang', await b.getAttribute('html', 'lang'));
  out('infos', await b.locator('.ev-facts, .event-facts, dl').first().innerText().catch(()=>''));
  await b.screenshot({ path: C + 'en-event.png' });
  await b.click('[data-dw-open]'); await b.waitForTimeout(400);
  if (!(await b.locator('.drawer .rate .qty button').count())) { await b.click('.drawer .dw-next'); await b.waitForTimeout(200); }
  await b.locator('.drawer .rate .qty button').nth(1).click(); await b.waitForTimeout(300);
  out('tiroir 1', await b.locator('.drawer').innerText()); await b.screenshot({ path: C + 'en-drawer.png' });
  await b.click('.drawer .dw-next'); await b.waitForTimeout(800);
  await b.fill('#dw-buyer [name=first_name]', 'John'); await b.fill('#dw-buyer [name=last_name]', 'Smith'); await b.fill('#dw-buyer [name=email]', 'john@t.co');
  for (const h of await b.locator('.drawer [data-slot=fields] input:visible').all()) { if (!(await h.inputValue())) await h.fill('Test').catch(()=>{}); }
  await b.click('.drawer .dw-next'); await b.waitForTimeout(800); out('tiroir 3', await b.locator('.drawer .dw-body').innerText()); out('bouton', await b.locator('.drawer .dw-next').innerText());
  if (await b.locator('[data-cgv]').count()) await b.check('[data-cgv]');
  await b.click('.drawer .dw-next'); await b.waitForTimeout(1500); out('tiroir 4', await b.locator('.drawer .dw-body').innerText());
  const demo = b.locator('.dw-paybtn'); if (await demo.count()) { await demo.click(); await b.waitForLoadState(); await b.waitForTimeout(500); }
  const fake = b.locator('button:has-text("Pretend I paid")'); if (await fake.count()) { await fake.click(); await b.waitForLoadState(); }
  out('url', b.url()); out('commande', (await b.locator('main').innerText()).slice(0, 600)); await b.screenshot({ path: C + 'en-order.png', fullPage: true });
  out('lang commande', sql("SELECT CONCAT(ref,' ',lang,' ',status) FROM orders WHERE email='john@t.co' ORDER BY id DESC LIMIT 1"));
  const pdf = b.url().replace(/(\/commande\/[^?]+)/, '$1/pdf'); const r = await b.request.get(pdf); require('fs').writeFileSync(CAPT + 'en-tickets.pdf', await r.body()); out('pdf', r.status());
  await b.goto(B + '/?lang=fr'); out('retour fr', await b.locator('footer').innerText());
  out('JS', JSON.stringify(errs)); await br.close(); })();
