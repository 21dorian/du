// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const { chromium } = require('playwright'); const { execSync } = require('child_process');
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -N entrevo -e "${q}"`).toString().trim();
const out = (k, v) => console.log(k.padEnd(24), String(v).replace(/\s+/g, ' ').slice(0, 220));
(async () => { const br = await chromium.launch(); const errs = [];
  const p = await (await br.newContext({ viewport: { width: 1440, height: 1000 } })).newPage(); p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.accept());
  const toast = async () => (await p.locator('.toasts, .flash').first().innerText().catch(() => '')).trim();
  await p.goto(BASE + '/connexion'); await p.fill('#email', 'orga@demo.entrevo'); await p.fill('#password', 'demo1234'); await p.click('button.btn-lg'); await p.waitForLoadState();
  // vente cashless ancienne (plus de 10 minutes) : annulation complète depuis le journal des ventes Pay
  const s1 = sql("SELECT s.id FROM pos_sales s WHERE s.event_id=1 AND s.kind='sale' AND s.undone=0 AND s.method='cashless' AND s.wallet_id IS NOT NULL AND s.created_at < NOW() - INTERVAL 10 MINUTE AND NOT EXISTS (SELECT 1 FROM pos_sales r WHERE r.refund_of=s.id) ORDER BY s.id DESC LIMIT 1");
  const w1 = sql(`SELECT CONCAT(w.id,' ',w.balance,' ',s.amount) FROM pos_sales s JOIN wallets w ON w.id=s.wallet_id WHERE s.id=${s1}`).split(' ');
  await p.goto(BASE + '/pay/pro/e/1/ventes');
  const row = p.locator(`form:has(input[name=sale_id][value="${s1}"])`); await row.locator('xpath=ancestor::details').locator('summary').click();
  await row.locator('input[name=reason]').fill('Erreur de saisie'); await row.locator('button.btn').click(); await p.waitForLoadState();
  out('vente cashless', await toast());
  out('solde', w1[1] + ' -> ' + sql(`SELECT balance FROM wallets WHERE id=${w1[0]}`) + ' (vente ' + w1[2] + ')');
  out('trace', sql(`SELECT CONCAT(kind,' ',amount,' ',offer_reason) FROM pos_sales WHERE refund_of=${s1}`));
  await p.screenshot({ path: CAPT + 'pay-annulation.png', fullPage: false });
  // annulation partielle d'une vente en espèces à plusieurs articles
  const s2 = sql("SELECT s.id FROM pos_sales s WHERE s.event_id=1 AND s.kind='sale' AND s.undone=0 AND s.method='cash' AND JSON_LENGTH(s.items) > 1 AND NOT EXISTS (SELECT 1 FROM pos_sales r WHERE r.refund_of=s.id) ORDER BY s.id DESC LIMIT 1");
  if (s2) { await p.goto(BASE + '/pay/pro/e/1/ventes'); const r2 = p.locator(`form:has(input[name=sale_id][value="${s2}"])`); await r2.locator('xpath=ancestor::details').locator('summary').click();
    await r2.locator('input[value=lines]').check(); await r2.locator('input[type=number]').first().fill('1'); await r2.locator('input[name=reason]').fill('Produit manquant'); await r2.locator('button.btn').click(); await p.waitForLoadState();
    out('partielle espèces', await toast()); }
  // annulation d'une recharge (crédit offert / caisse)
  const t1 = sql("SELECT t.id FROM wallet_tx t JOIN wallets w ON w.id=t.wallet_id WHERE t.event_id=1 AND t.kind IN ('cash','card','gift') AND t.undone=0 AND w.balance >= t.amount ORDER BY t.id DESC LIMIT 1");
  await p.goto(BASE + '/pay/pro/e/1/remboursements'); const r3 = p.locator(`form:has(input[name=tx_id][value="${t1}"])`); await r3.locator('xpath=ancestor::details').locator('summary').click();
  await r3.locator('input[name=reason]').fill('Doublon'); await r3.locator('button.btn').click(); await p.waitForLoadState(); out('recharge', await toast());
  out('trace recharge', sql(`SELECT CONCAT(t.undone, ' · annulation ', u.amount) FROM wallet_tx t JOIN wallet_tx u ON u.kind='undo' AND u.wallet_id=t.wallet_id AND u.id > t.id WHERE t.id=${t1} ORDER BY u.id DESC LIMIT 1`));
  // click & collect
  const c1 = sql("SELECT id FROM pos_sales WHERE source='collect' AND undone=0 AND prep_status IN ('new','ready') ORDER BY id DESC LIMIT 1");
  if (c1) { await p.goto(BASE + '/pro/evenements/1/click-collect'); const f = p.locator(`form:has(input[name=sale_id][value="${c1}"])`); await f.locator('input[name=reason]').fill('Rupture'); await f.locator('button').click(); await p.waitForLoadState();
    out('click & collect', await toast() + ' · ' + sql(`SELECT prep_status FROM pos_sales WHERE id=${c1}`)); }
  // refus : motif manquant
  const s3 = sql("SELECT id FROM pos_sales WHERE event_id=1 AND kind='sale' AND undone=0 AND method='card' ORDER BY id DESC LIMIT 1");
  const r = await p.request.post(BASE + '/pay/pro/e/1/ventes', { form: { _csrf: await p.locator('input[name=_csrf]').first().inputValue(), action: 'cancel_sale', sale_id: s3, scope: 'all', reason: '' } }); out('sans motif', r.status());
  out('JS', JSON.stringify(errs)); await br.close(); })();
