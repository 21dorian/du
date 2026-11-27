// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const LIVRAISONS = __path.resolve(__dirname, '../livraisons') + '/';
const { chromium } = require('playwright'); const { execSync } = require('child_process');
const B = BASE, C = CAPT; 
const sql = (q) => execSync(`mysql -N entrevo -e "${q}"`).toString().trim();
const out = (k, v) => console.log(k.padEnd(24), String(v).replace(/\s+/g, ' ').slice(0, 200));
(async () => { const br = await chromium.launch(); const errs = [];
  const np = async () => { const p = await (await br.newContext({ viewport: { width: 1200, height: 900 } })).newPage(); p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.accept()); return p; };
  const b = await np(); await b.goto(B + '/e/festival-lumieres-d-ete'); await b.waitForTimeout(400);
  await b.click('[data-dw-open]'); await b.waitForTimeout(400);
  if (!(await b.locator('.drawer .rate .qty button').count())) { await b.click('.drawer .dw-next'); await b.waitForTimeout(200); }
  await b.locator('.drawer .rate .qty button').nth(1).click(); await b.waitForTimeout(200);
  await b.click('.drawer .dw-next'); await b.waitForTimeout(800);
  await b.fill('#dw-buyer [name=first_name]', 'Rémi'); await b.fill('#dw-buyer [name=last_name]', 'Resa'); await b.fill('#dw-buyer [name=email]', 'resa@t.fr');
  for (const h of await b.locator('.drawer [data-slot=fields] input:visible').all()) { if (!(await h.inputValue())) await h.fill('Test').catch(()=>{}); }
  await b.click('.drawer .dw-next'); await b.waitForTimeout(800);
  out('choix visible', await b.locator('.drawer .resa-choice').innerText()); await b.check('.drawer input[name=pay_later][value="1"]');
  await b.screenshot({ path: C + 'resa-tiroir.png' });
  if (await b.locator('[data-cgv]').count()) await b.check('[data-cgv]');
  await b.click('.drawer .dw-next'); await b.waitForURL(/commande/, { timeout: 8000 }).catch(()=>{}); await b.waitForLoadState();
  out('url', b.url()); out('erreurs tiroir', await b.locator('[data-errors]').innerText().catch(()=>''));
  out('page commande', await b.locator('h1').innerText() + ' | ' + await b.locator('.auth-box .muted').first().innerText()); await b.screenshot({ path: C + 'resa-commande.png' });
  const row = sql("SELECT CONCAT(id,' ',status,' ',channel,' ',expires_at) FROM orders WHERE email='resa@t.fr' ORDER BY id DESC LIMIT 1"); out('commande', row); const id = row.split(' ')[0];
  const p = await np(); await p.goto(B + '/connexion'); await p.fill('#email', 'orga@demo.entrevo'); await p.fill('#password', 'demo1234'); await p.click('button.btn-lg'); await p.waitForLoadState();
  await p.goto(B + '/pro/commandes/' + id); out('bloc pro', await p.locator('.resa-admin').innerText()); await p.screenshot({ path: C + 'resa-pro.png', fullPage: true });
  await p.click('button[value=resa_extend]'); await p.waitForLoadState(); out('prolonger', await p.locator('.toasts, .flash').first().innerText().catch(()=>''));
  await p.click('button[value=resa_resend]'); await p.waitForLoadState(); out('renvoyer', await p.locator('.toasts, .flash').first().innerText().catch(()=>''));
  await p.selectOption('.resa-admin select[name=pay_method]', 'cheque'); await p.click('button[value=resa_paid]'); await p.waitForLoadState(); out('encaisser', await p.locator('.toasts, .flash').first().innerText().catch(()=>''));
  out('après', sql(`SELECT CONCAT(status,' ',pay_method,' billets=',(SELECT COUNT(*) FROM tickets WHERE order_id=${id})) FROM orders WHERE id=${id}`));
  out('JS', JSON.stringify(errs)); await br.close(); })();
