// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const LIVRAISONS = __path.resolve(__dirname, '../livraisons') + '/';
const { chromium } = require('playwright'); const { execSync } = require('child_process');
const B = BASE, C = CAPT;
const sql = (q) => execSync(`mysql -N entrevo -e "${q}"`).toString().trim();
const out = (k, v) => console.log(k.padEnd(24), String(v).replace(/\s+/g, ' ').slice(0, 220));
(async () => { const br = await chromium.launch(); const errs = [];
  const p = await (await br.newContext({ viewport: { width: 1440, height: 1000 } })).newPage(); p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.accept());
  const toast = async () => (await p.locator('.toasts, .flash').first().innerText().catch(() => '')).trim();
  await p.goto(B + '/connexion'); await p.fill('#email', 'orga@demo.entrevo'); await p.fill('#password', 'demo1234'); await p.click('button.btn-lg'); await p.waitForLoadState();
  await p.goto(B + '/pro/evenements/1/stocks'); await p.fill('input[name=qty]', '7'); await p.fill('input[name=label]', 'Office de tourisme'); await p.click('form:has([value=create]) button.btn'); await p.waitForLoadState(); out('création', await toast());
  const sid = sql("SELECT id FROM ticket_stocks ORDER BY id DESC LIMIT 1");
  for (const f of ['', '?format=boca', '?format=ticket']) { const r = await p.request.get(B + '/pro/evenements/1/stocks/' + sid + '.pdf' + f); const b = await r.body(); out('pdf ' + (f || 'A4'), r.status() + ' ' + r.headers()['content-type'] + ' ' + b.length + 'o ' + b.slice(0, 5)); require('fs').writeFileSync('stock' + (f.split('=')[1] || 'a4') + '.pdf', b); }
  await p.screenshot({ path: C + 'stocks.png', fullPage: true });
  const code = sql(`SELECT code FROM tickets WHERE order_id=(SELECT order_id FROM ticket_stocks WHERE id=${sid}) ORDER BY id LIMIT 1`);
  await p.click('details.menu summary:has-text("Solder")'); await p.fill('input[name=unsold]', '2'); await p.fill('textarea[name=codes]', code); await p.click('button[value=close]'); await p.waitForLoadState(); out('solde', await toast());
  out('base', sql(`SELECT CONCAT(s.sold,' vendus, commande ',o.status,' ',o.total,' ',o.pay_method,', annulés=',(SELECT COUNT(*) FROM tickets WHERE order_id=o.id AND status='cancelled'),', 1er code annulé=',(SELECT status FROM tickets WHERE code='${code}')) FROM ticket_stocks s JOIN orders o ON o.id=s.order_id WHERE s.id=${sid}`));
  const oid = sql("SELECT id FROM orders WHERE event_id=1 AND status='paid' AND method<>'stock' ORDER BY id DESC LIMIT 1");
  let r = await p.request.get(B + '/pro/commandes/' + oid + '/billets-boca'); let b = await r.body(); out('boca commande', r.status() + ' ' + b.length); require('fs').writeFileSync('boca.pdf', b);
  r = await p.request.get(B + '/pro/abonnements/cartes.pdf'); b = await r.body(); out('cartes CR80', r.status() + ' ' + b.length + ' ' + b.slice(0, 40)); if (r.status() === 200) require('fs').writeFileSync('cartes.pdf', b);
  r = await p.request.get(B + '/pro/abonnements/cartes.pdf?format=a4'); b = await r.body(); out('cartes A4', r.status() + ' ' + b.length); if (r.status() === 200) require('fs').writeFileSync('cartesa4.pdf', b);
  // visuel par tarif
  await p.goto(B + '/pro/evenements/1/tarifs?edit=1').catch(()=>{});
  out('JS', JSON.stringify(errs)); await br.close(); })();
