// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const { chromium } = require('playwright'); const { execSync } = require('child_process');
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -N entrevo -e "${q}"`).toString().trim();
const out = (k, v) => console.log(k.padEnd(24), String(v).replace(/\s+/g, ' ').slice(0, 230));
(async () => { const br = await chromium.launch(); const errs = [];
  const p = await (await br.newContext({ viewport: { width: 1440, height: 1100 } })).newPage(); p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.accept());
  const toast = async () => (await p.locator('.toasts, .flash').first().innerText().catch(() => '')).trim();
  await p.goto(BASE + '/connexion'); await p.fill('#email', 'orga@demo.entrevo'); await p.fill('#password', 'demo1234'); await p.click('button.btn-lg'); await p.waitForLoadState();
  const paidMulti = () => sql("SELECT o.id FROM orders o WHERE o.event_id=1 AND o.status='paid' AND o.total>0 AND o.method='online' AND (SELECT COUNT(*) FROM tickets t WHERE t.order_id=o.id AND t.status='valid' AND t.entries=0)>=2 AND (SELECT COUNT(*) FROM tickets t WHERE t.order_id=o.id AND t.entries>0)=0 ORDER BY o.id DESC LIMIT 1");
  // 1. annulation d'un seul billet, hors frais
  const o1 = paidMulti(); await p.goto(BASE + '/pro/commandes/' + o1 + '#annuler'); await p.waitForTimeout(300);
  await p.screenshot({ path: CAPT + 'commande-annuler.png', fullPage: true });
  const boxes = p.locator('#annuler input[name="tickets[]"]'); const n = await boxes.count(); for (let i = 1; i < n; i++) await boxes.nth(i).uncheck();
  if (await p.locator('#annuler input[value=nofees]').count()) await p.check('#annuler input[value=nofees]'); else await p.check('#annuler input[value=full]');
  await p.selectOption('#annuler select[name=reason]', 'Demande de l\'acheteur'); await p.fill('#annuler input[name=note]', 'Ne peut plus venir');
  await p.click('#annuler button.btn-danger'); await p.waitForLoadState(); out('1 billet annulé', await toast());
  out('base', sql(`SELECT CONCAT(status,' remb=',refunded_amount,' valides=',(SELECT COUNT(*) FROM tickets WHERE order_id=${o1} AND status='valid'),' motif=',cancel_reason) FROM orders WHERE id=${o1}`));
  // 2. avoir sur toute une commande
  const o2 = paidMulti(); await p.goto(BASE + '/pro/commandes/' + o2 + '#annuler'); await p.check('#annuler input[value=credit]'); await p.selectOption('#annuler select[name=reason]', 'Événement annulé ou reporté');
  await p.click('#annuler button.btn-danger'); await p.waitForLoadState(); out('avoir', await toast());
  out('carte cadeau', sql(`SELECT CONCAT(g.code,' ',g.balance,' ',o.status) FROM orders o JOIN gift_cards g ON g.id=o.credit_gift_id WHERE o.id=${o2}`));
  // 3. commande en attente
  const o3 = sql("SELECT id FROM orders WHERE status='pending' AND event_id=1 ORDER BY id DESC LIMIT 1");
  if (o3) { await p.goto(BASE + '/pro/commandes/' + o3 + '#annuler'); await p.selectOption('#annuler select[name=reason]', 'Doublon'); await p.click('#annuler button.btn-danger'); await p.waitForLoadState(); out('en attente', await toast() + ' · ' + sql(`SELECT status FROM orders WHERE id=${o3}`)); }
  // 4. ajout de billets encaissés
  const o4 = paidMulti(); await p.goto(BASE + '/pro/commandes/' + o4 + '#modifier');
  await p.fill('#modifier input[name=qty]', '2'); await p.selectOption('#modifier select[name=pay]', 'cb'); await p.click('#modifier form:has([value=add_tickets]) button'); await p.waitForLoadState();
  out('ajout', await toast()); out('liée', sql(`SELECT CONCAT(ref,' ',status,' ',total,' billets=',(SELECT COUNT(*) FROM tickets WHERE order_id=o.id)) FROM orders o WHERE parent_order_id=${o4} ORDER BY id DESC LIMIT 1`));
  // 5. changement de tarif (plus cher, encaissé)
  await p.goto(BASE + '/pro/commandes/' + o4 + '#modifier'); const before = sql(`SELECT total FROM orders WHERE id=${o4}`);
  await p.locator('#modifier form:has([value=change_rate]) select[name=rate_id]').selectOption({ index: 5 }); await p.selectOption('#modifier select[name=diff]', 'collect'); await p.click('#modifier form:has([value=change_rate]) button'); await p.waitForLoadState();
  out('tarif', await toast()); out('total', before + ' -> ' + sql(`SELECT total FROM orders WHERE id=${o4}`));
  // 6. changement de date (événement à séances)
  const o6 = sql("SELECT o.id FROM orders o WHERE o.event_id=2 AND o.status='paid' AND (SELECT COUNT(*) FROM tickets t WHERE t.order_id=o.id AND t.status='valid')>0 ORDER BY o.id DESC LIMIT 1");
  await p.goto(BASE + '/pro/commandes/' + o6 + '#modifier'); const s0 = sql(`SELECT session_id FROM orders WHERE id=${o6}`);
  const opt = await p.locator('#modifier form:has([value=change_session]) select option').evaluateAll((os, cur) => os.map(o => o.value).filter(v => v !== cur), s0);
  await p.selectOption('#modifier form:has([value=change_session]) select', opt[opt.length - 1]); await p.click('#modifier form:has([value=change_session]) button'); await p.waitForLoadState();
  out('date', await toast()); out('séance', s0 + ' -> ' + sql(`SELECT session_id FROM orders WHERE id=${o6}`));
  await p.screenshot({ path: CAPT + 'commande-modifiee.png', fullPage: true });
  out('JS', JSON.stringify(errs)); await br.close(); })();
