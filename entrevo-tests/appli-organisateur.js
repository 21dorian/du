// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const { chromium, devices } = require('playwright'); const { execSync } = require('child_process');
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -N entrevo -e "${q}"`).toString().trim();
const out = (k, v) => console.log(k.padEnd(22), String(v).replace(/\s+/g, ' ').slice(0, 230));
(async () => { const br = await chromium.launch(); const errs = [];
  const ctx = await br.newContext({ ...devices['iPhone 13'], permissions: ['notifications'] }); const p = await ctx.newPage(); p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.accept());
  await p.goto(BASE + '/connexion'); await p.fill('#email', 'orga@demo.entrevo'); await p.fill('#password', 'demo1234'); await p.click('button.btn-lg'); await p.waitForLoadState();
  await p.goto(BASE + '/pro/app?e=1'); await p.waitForTimeout(1500);
  out('direct', await p.locator('[data-tab=live] .oa-hero').innerText() + ' | ' + await p.locator('.oa-kpis').innerText());
  out('tarifs', (await p.locator('[data-rates]').innerText()).slice(0, 120)); await p.screenshot({ path: CAPT + 'appli-direct.png', fullPage: true });
  // vente rapide
  await p.click('[data-go=sell]'); await p.locator('.oa-rate-row').first().locator('[data-d="1"]').click(); await p.locator('.oa-rate-row').first().locator('[data-d="1"]').click();
  out('bouton', await p.locator('[data-sell-btn]').innerText()); await p.click('.oa-pay label:has-text("Carte")');
  await p.click('[data-sell-btn]'); await p.waitForSelector('[data-sell-done]:not([hidden])'); out('vente', await p.locator('[data-sell-done]').innerText()); await p.screenshot({ path: CAPT + 'appli-vente.png', fullPage: true });
  out('base', sql("SELECT CONCAT(ref,' ',channel,' ',pay_method,' ',total,' ',status) FROM orders ORDER BY id DESC LIMIT 1"));
  // alerte générée par la vente → fil d'activité au prochain rafraîchissement
  await p.click('[data-go=alerts]'); await p.evaluate(() => {}); await p.waitForTimeout(16000);
  out('activité', (await p.locator('[data-alerts]').innerText()).slice(0, 160)); await p.screenshot({ path: CAPT + 'appli-alertes.png', fullPage: true });
  out('état notif', await p.locator('[data-notif-state]').innerText());
  // ouverture / clôture
  await p.click('[data-go=live]'); await p.click('[data-toggle-sales]'); await p.waitForTimeout(800); out('statut', sql('SELECT status FROM events WHERE id=1'));
  await p.click('[data-toggle-sales]'); await p.waitForTimeout(800); out('statut après', sql('SELECT status FROM events WHERE id=1'));
  // message express
  await p.click('[data-go=more]'); await p.fill('#oa-subj', 'Test appli'); await p.fill('#oa-body', 'Bonjour'); await p.selectOption('#oa-who', 'in'); await p.click('[data-msg] button'); await p.waitForTimeout(3000);
  out('message', await p.locator('.oa-toast').last().innerText().catch(() => '')); await p.screenshot({ path: CAPT + 'appli-plus.png', fullPage: true });
  const man = await p.request.get(BASE + '/pro/app/manifest.webmanifest'); out('manifeste', man.status() + ' ' + (await man.json()).name);
  const sw = await p.request.get(BASE + '/pro/app/sw.js'); out('service worker', sw.status() + ' ' + sw.headers()['content-type']);
  out('JS', JSON.stringify(errs)); await br.close(); })();
