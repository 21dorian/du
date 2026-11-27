// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const LIVRAISONS = __path.resolve(__dirname, '../livraisons') + '/';
const { chromium } = require('playwright'); const { execSync } = require('child_process');
const B = BASE, C = CAPT; 
const sql = (q) => execSync(`mysql -N entrevo -e "${q}"`).toString().trim();
(async () => { const br = await chromium.launch(); const errs = [], bad = [];
  const ctx = async (w, h) => { const p = await (await br.newContext({ viewport: { width: w, height: h } })).newPage(); p.on('pageerror', e => errs.push(p.url() + ' ' + e.message)); p.on('dialog', d => d.accept()); return p; };
  const check = async (p, u) => { const r = await p.goto(B + u); const t = await p.content(); if (r.status() >= 400 || t.includes('Oups') || t.includes('Warning:') || t.includes('Fatal')) bad.push(r.status() + ' ' + u); };
  const o = await ctx(1440, 1000);
  await o.goto(B + '/connexion'); await o.fill('#email', 'orga@demo.entrevo'); await o.fill('#password', 'demo1234'); await o.click('button.btn-lg'); await o.waitForLoadState();
  // 1. prix dynamique sur le premier tarif de l'événement 1
  const rid = sql("SELECT id FROM rates WHERE event_id = 1 AND price_mode <> 'free' AND price > 0 ORDER BY sort, id LIMIT 1");
  const sold = parseInt(sql('SELECT COUNT(*) FROM tickets WHERE rate_id = ' + rid + " AND status = 'valid'"), 10);
  await o.goto(B + '/pro/evenements/1/tarifs?edit=' + rid);
  await o.click('.dyn-box summary'); await o.check('[name=dyn_on]'); await o.check('[name=dyn_mode][value=sold]');
  await o.fill('[name="dyn_limit[0]"]', String(Math.max(1, sold - 1))); await o.fill('[name="dyn_price[0]"]', '15');
  await o.fill('[name="dyn_limit[1]"]', String(sold + 3)); await o.fill('[name="dyn_price[1]"]', '19');
  await o.fill('[name="dyn_limit[2]"]', ''); await o.fill('[name="dyn_price[2]"]', '24'); await o.fill('[name="dyn_price[3]"]', ''); await o.fill('[name="dyn_price[4]"]', '');
  await o.screenshot({ path: C + '1-tarif-dynamique.png', fullPage: false });
  await o.locator('.dyn-box').screenshot({ path: C + '1b-paliers.png' });
  await o.click('form:has([name=dyn_on]) button.btn:has-text("Enregistrer")'); await o.waitForLoadState();
  console.log('prix courant :', sql('SELECT price FROM rates WHERE id = ' + rid), '· historique :', sql('SELECT COUNT(*) FROM rate_prices WHERE rate_id = ' + rid));
  const pub = await ctx(1280, 900); await pub.goto(B + '/e/festival-lumieres-d-ete'); const dn = await pub.locator('.dyn-next').first().innerText().catch(() => '(aucun)'); console.log('page publique :', dn);
  await pub.locator('.rate:has(.dyn-next), .rate-row:has(.dyn-next), li:has(.dyn-next)').first().screenshot({ path: C + '2-page-publique.png' }).catch(async () => { await pub.screenshot({ path: C + '2-page-publique.png' }); });
  // 2. vue 360
  await check(o, '/pro/evenements/1/360'); await o.screenshot({ path: C + '3-vue-360.png', fullPage: true });
  const csv = await o.request.get(B + '/pro/evenements/1/360?export=csv'); console.log('export 360 :', csv.status(), (await csv.text()).split('\n').length, 'lignes');
  // 3. fidélité
  await check(o, '/pro/fidelite'); await o.screenshot({ path: C + '4-fidelite.png', fullPage: true });
  const em = sql("SELECT LOWER(email) FROM orders WHERE organizer_id = 1 AND status = 'paid' ORDER BY total DESC LIMIT 1");
  await check(o, '/pro/festivaliers?e=' + encodeURIComponent(em)); await o.screenshot({ path: C + '5-fiche-festivalier.png', fullPage: true });
  for (const sg of ['fideles', 'bar', 'absents', 'nouveaux', 'dormants']) await check(o, '/pro/fidelite?seg=' + sg);
  await o.goto(B + '/pro/fidelite'); await o.selectOption('form:has([value=offer]) [name=event_id]', '1'); await o.fill('form:has([value=offer]) [name=code]', 'MERCI10');
  const [dl] = await Promise.all([o.waitForEvent('download'), o.click('form:has([value=offer]) button.btn:has-text("Créer")')]);
  console.log('offre ciblée :', await dl.suggestedFilename(), '· code créé :', sql("SELECT CONCAT(code, ' ', value, '% max ', max_uses) FROM promos WHERE code = 'MERCI10'"));
  for (const u of ['/pro/participants', '/pro', '/pro/evenements/1', '/pro/evenements/1/tarifs']) await check(o, u);
  // 4. Pay : prévisions et analyse
  const p = await ctx(1440, 1000);
  await p.goto(B + '/pay/connexion'); await p.fill('#email', 'orga@demo.entrevo'); await p.fill('#password', 'demo1234'); await p.click('button.btn-lg'); await p.waitForLoadState();
  await check(p, '/pay/pro/e/1/previsions'); await p.screenshot({ path: C + '6-previsions.png', fullPage: true });
  await check(p, '/pay/pro/e/1/analyse'); await p.screenshot({ path: C + '7-analyse.png', fullPage: true });
  await check(p, '/pay/pro/e/1/analyse?vs=3'); console.log('comparaison :', await p.locator('h2:has-text("Comparaison")').count());
  await check(p, '/pay/pro/e/1'); await check(p, '/pay/pro/e/1/live.json');
  // nettoyage : le tarif redevient fixe
  sql("UPDATE rates SET dyn_json = NULL WHERE id = " + rid + "; DELETE FROM promos WHERE code = 'MERCI10'");
  console.log('KO', bad); console.log('JS', errs); await br.close(); })();
