// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const LIVRAISONS = __path.resolve(__dirname, '../livraisons') + '/';
const { chromium } = require('playwright'); const { execSync } = require('child_process');
const B = BASE, C = CAPT;
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -N entrevo -e "${q}"`).toString().trim();
const out = (k, v) => console.log(k.padEnd(24), String(v).replace(/\s+/g, ' ').slice(0, 240));
(async () => { const br = await chromium.launch(); const errs = [];
  const np = async (w = 1440, h = 1000) => { const p = await (await br.newContext({ viewport: { width: w, height: h } })).newPage(); p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.accept()); return p; };
  const p = await np(); const toast = async (x = p) => (await x.locator('.toasts, .flash, .pp-flash').first().innerText().catch(() => '')).trim();
  await p.goto(B + '/connexion'); await p.fill('#email', 'orga@demo.entrevo'); await p.fill('#password', 'demo1234'); await p.click('button.btn-lg'); await p.waitForLoadState();
  // click & collect
  await p.goto(B + '/pro/evenements/1/click-collect'); await p.check('input[name=click_collect]', { force: true }).catch(async () => p.click('label.switch span')); await p.click('form:has([name=click_collect]) button.btn'); await p.waitForLoadState(); out('activation', await toast());
  const tok = sql("SELECT token FROM wallets WHERE status='active' AND balance>500 AND event_id=1 LIMIT 1"); const bal0 = sql(`SELECT balance FROM wallets WHERE token='${tok}'`);
  const f = await np(390, 844); await f.goto(B + '/pay/w/' + tok); out('portail', await f.locator('#commander summary').innerText().catch(() => 'absent'));
  if (!(await f.locator('#commander[open]').count())) await f.click('#commander summary'); await f.locator('#commander input[type=number]').nth(0).fill('2'); await f.locator('#commander input[type=number]').nth(1).fill('1').catch(()=>{});
  await f.click('#commander button.pp-btn'); await f.waitForLoadState(); out('commande', await toast(f)); await f.screenshot({ path: C + 'click-collect-portail.png', fullPage: true });
  out('solde', bal0 + ' -> ' + sql(`SELECT balance FROM wallets WHERE token='${tok}'`));
  out('vente', sql("SELECT CONCAT(order_no,' ',prep_status,' ',amount,' ',source) FROM pos_sales WHERE source='collect' ORDER BY id DESC LIMIT 1"));
  out('écran prépa', execSync(`php -r '$_SERVER["HTTP_HOST"]="x";require "${__path.resolve(__dirname, '../entrevo/app/bootstrap.php')}"; echo json_encode(array_slice(Pos::prepList(["event_id"=>1,"bar_id"=>0]),-1), JSON_UNESCAPED_UNICODE);'`).toString().slice(0, 200));
  await p.goto(B + '/pro/evenements/1/click-collect'); await p.screenshot({ path: C + 'click-collect-pro.png', fullPage: true }); out('liste pro', await p.locator('table').innerText().catch(()=>''));
  // équipes
  await p.goto(B + '/pro/evenements/1/equipes'); out('équipes', (await p.locator('.kpis').innerText()));
  await p.click('button[value=auto_meals]'); await p.waitForLoadState(); out('auto repas', await toast());
  const n = await p.locator('input[name$="[lodging]"]').count(); out('lignes', n);
  if (n) { await p.locator('input[name$="[lodging]"]').first().fill('Camping équipe, 2 nuits'); await p.click('button[value=provisions]'); await p.waitForLoadState(); out('dotations', await toast());
    await p.locator('button[value=meal]').first().click(); await p.waitForLoadState(); out('repas servi', await toast()); }
  await p.fill('form:has([value=doc]) input[name=title]', 'Charte bénévole 2026'); await p.fill('form:has([value=doc]) textarea[name=body]', 'Je m\'engage à respecter les consignes de sécurité et les horaires de mes créneaux.'); await p.click('form:has([value=doc]) button.btn'); await p.waitForLoadState(); out('document', await toast());
  await p.fill('form:has([value=message]) input[name=subject]', 'Briefing'); await p.fill('form:has([value=message]) textarea[name=body]', 'Rendez-vous à 15 h.'); await p.click('form:has([value=message]) button.btn'); await p.waitForLoadState(); out('message', await toast());
  await p.screenshot({ path: C + 'equipes.png', fullPage: true });
  const mid = sql("SELECT m.id FROM staff_assignments a JOIN staff_shifts s ON s.id=a.shift_id JOIN staff_members m ON m.id=a.member_id WHERE s.event_id=1 AND a.status='confirmed' LIMIT 1");
  if (mid) { const r = await p.request.get(B + '/pro/evenements/1/equipes/planning/' + mid + '.pdf'); out('planning pdf', r.status() + ' ' + (await r.body()).length);
    const vt = sql(`SELECT CONCAT(token,'|',first_name,' ',last_name) FROM staff_members WHERE id=${mid}`).split('|');
    const v = await np(390, 844); await v.goto(B + '/benevole/' + vt[0]); out('portail bénévole', await v.locator('.vol-doc h2').first().innerText().catch(()=>'pas de doc'));
    await v.check('.vol-doc input[name=agree]'); await v.fill('.vol-doc input[name=sign_name]', vt[1]); await v.click('.vol-doc button.btn'); await v.waitForLoadState(); out('signature', await toast(v)); await v.screenshot({ path: C + 'benevole-portail.png', fullPage: true });
    const r2 = await v.request.get(B + '/benevole/' + vt[0] + '/planning.pdf'); out('planning bénévole', r2.status()); require('fs').writeFileSync('planning.pdf', await r2.body()); }
  const did = sql("SELECT id FROM staff_docs ORDER BY id DESC LIMIT 1"); await p.goto(B + '/pro/evenements/1/equipes/documents/' + did); out('signatures', await p.locator('table').innerText());
  // opérateurs
  await p.goto(B + '/pro/evenements/1/controle/operateurs'); out('opérateurs', await p.locator('table').innerText().catch(() => 'vide')); await p.screenshot({ path: C + 'operateurs.png', fullPage: true });
  out('JS', JSON.stringify(errs)); await br.close(); })();
