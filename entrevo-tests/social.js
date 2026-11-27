// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const LIVRAISONS = __path.resolve(__dirname, '../livraisons') + '/';
const { chromium } = require('playwright'); const fs = require('fs'); const { execSync } = require('child_process');
const B = BASE, C = CAPT, SP = (process.argv[2] || __os.tmpdir());
(async () => { const br = await chromium.launch(); const errs = [];
  const lead = await (await br.newContext({ viewport: { width: 1280, height: 950 } })).newPage(); lead.on('pageerror', e => errs.push(e.message));
  await lead.goto(B + '/e/festival-lumieres-d-ete'); const before = await lead.evaluate(() => 0);
  await lead.click('.group-buy summary'); await lead.selectOption('.group-buy select[name=qty]', '3'); await lead.fill('.group-buy input[name=name]', 'Sacha'); await lead.fill('.group-buy input[name=email]', 'sacha@exemple.fr');
  await lead.screenshot({ path: C + 'bande-1-form.png' }); await lead.click('.group-buy button.btn'); await lead.waitForLoadState();
  const url = lead.url(); console.log('groupe :', url.replace(B, ''), '|', (await lead.locator('.grp p >> nth=1').innerText()).replace(/\s+/g, ' '));
  // un ami prend sa place et paie (démo)
  const f = await (await br.newContext({ viewport: { width: 390, height: 844 } })).newPage(); f.on('pageerror', e => errs.push(e.message));
  await f.goto(url); await f.click('form button.btn-lg'); await f.waitForLoadState(); console.log('ami → ', f.url().replace(B, ''));
  await f.fill('input[name=first_name]', 'Noa'); await f.fill('input[name=last_name]', 'Ami'); await f.fill('input[name=email]', 'noa@exemple.fr'); if (await f.locator('input[name=email2]').count()) await f.fill('input[name=email2]', 'noa@exemple.fr');
  if (await f.locator('input[name=show_public]').count()) await f.check('input[name=show_public]'); if (await f.locator('input[name=cgv]').count()) await f.check('input[name=cgv]');
  await f.locator('form button.btn-lg, form button[type=submit]').last().click(); await f.waitForLoadState();
  if (f.url().includes('/paiement/')) { const pb = f.locator('button.btn-lg, button[type=submit]').first(); await pb.click(); await f.waitForLoadState(); }
  if (f.url().includes('/paiement/demo')) { await f.locator('button').first().click(); await f.waitForLoadState(); }
  console.log('commande ami :', f.url().replace(B, '').replace(/\?.*/, ''));
  const ref = f.url().match(/commande\/(E[A-Z0-9]+)/)[1], tok = new URL(f.url()).searchParams.get('t');
  await f.screenshot({ path: C + 'bande-3-commande.png', fullPage: true });
  console.log('wallet boutons :', await f.locator('.wallet-btns a').count());
  await lead.goto(url); console.log('groupe après :', (await lead.locator('.grp p >> nth=1').innerText()).replace(/\s+/g, ' ')); await lead.screenshot({ path: C + 'bande-2-groupe.png', fullPage: true });
  const code = await f.locator('.ticket-code').first().innerText();
  const pk = await f.request.get(B + '/commande/' + ref + '/wallet/' + code + '?t=' + tok); const body = await pk.body(); fs.writeFileSync(SP + '/test.pkpass', body); console.log('pkpass :', pk.status(), pk.headers()['content-type'], body.length + ' o');
  const g = await f.request.get(B + '/commande/' + ref + '/google/' + code + '?t=' + tok, { maxRedirects: 0 }); console.log('google :', g.status(), (g.headers()['location'] || '').slice(0, 60));
  fs.writeFileSync(SP + '/glink.txt', g.headers()['location'] || '');
  const ev = await (await br.newContext()).newPage(); await ev.goto(B + '/e/festival-lumieres-d-ete'); console.log('ils y vont :', (await ev.locator('.going').innerText().catch(() => 'absent')).replace(/\s+/g, ' '));
  console.log('JS', errs); await br.close(); })();
