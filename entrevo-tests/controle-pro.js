// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const { chromium, devices } = require('playwright'); const { execSync } = require('child_process');
const sql = (q) => execSync(`mysql --default-character-set=utf8mb4 -N entrevo -e "${q}"`).toString().trim();
const out = (k, v) => console.log(k.padEnd(22), String(v).replace(/\s+/g, ' ').slice(0, 220));
(async () => { const br = await chromium.launch(); const errs = [];
  const p = await (await br.newContext({ ...devices['Pixel 7'] })).newPage(); p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.accept());
  await p.goto(BASE + '/connexion'); await p.fill('#email', 'orga@demo.entrevo'); await p.fill('#password', 'demo1234'); await p.click('button.btn-lg'); await p.waitForLoadState();
  sql("UPDATE tickets SET nfc_uid=NULL WHERE nfc_uid='04A1B2C3D4E5'");
  const code = sql("SELECT code FROM tickets WHERE event_id=1 AND status='valid' AND entries=0 AND type='ticket' ORDER BY id DESC LIMIT 1");
  await p.goto(BASE + '/scan'); await p.waitForTimeout(1500);
  await p.locator('#s-events button').filter({ hasText: 'Lumières' }).first().click(); await p.waitForTimeout(2500);
  await p.click('#start-btn'); await p.waitForTimeout(800);
  if (await p.locator('#pc-msg').isVisible()) await p.click('#pc-msg-ok');
  out('jauge', await p.locator('#gauge').innerText());
  // association d'un bracelet
  await p.click('#s-scan [data-go=menu]'); await p.click('#m-link');
  await p.fill('#manual-input', code); await p.press('#manual-input', 'Enter'); await p.waitForTimeout(300);
  out('étape 1', await p.locator('#nfc-text').innerText());
  await p.evaluate(() => window.dispatchEvent(new CustomEvent('entrevo-nfc', { detail: '04:a1:b2:c3:d4:e5' }))); await p.waitForTimeout(1200);
  out('étape 2', await p.locator('#nfc-text').innerText()); out('base', sql(`SELECT nfc_uid FROM tickets WHERE code='${code}'`));
  await p.screenshot({ path: CAPT + 'controle-association.png' });
  // entrée avec le bracelet
  await p.click('#nfc-stop');
  await p.evaluate(() => window.dispatchEvent(new CustomEvent('entrevo-nfc', { detail: '04A1B2C3D4E5' }))); await p.waitForTimeout(1500);
  out('entrée bracelet', (await p.locator('#r-label').innerText()) + ' · ' + await p.locator('#r-name').innerText());
  out('base entrée', sql(`SELECT CONCAT(entries,' ',inside) FROM tickets WHERE code='${code}'`));
  // bracelet inconnu en direct (résolution serveur)
  await p.evaluate(() => window.dispatchEvent(new CustomEvent('entrevo-nfc', { detail: 'DEADBEEF99' }))); await p.waitForTimeout(1500);
  out('bracelet inconnu', await p.locator('#r-label').innerText());
  // liste des invités
  await p.click('#s-scan [data-go=menu]'); await p.click('#m-guests'); await p.waitForTimeout(300);
  out('invités', await p.locator('#guests-count').innerText()); await p.screenshot({ path: CAPT + 'controle-invites.png' });
  await p.click('[data-close=guests]');
  // file d'attente
  await p.click('#s-scan [data-go=menu]'); await p.click('#m-stats'); await p.waitForTimeout(800);
  await p.locator('.queue-ctl button').nth(1).click(); await p.locator('.queue-ctl button').nth(1).click(); await p.waitForTimeout(2500);
  out('file en base', sql("SELECT GROUP_CONCAT(queue) FROM scan_devices WHERE event_id=1 AND queue IS NOT NULL AND last_seen >= NOW() - INTERVAL 1 MINUTE"));
  await p.screenshot({ path: CAPT + 'controle-stats.png' }); await p.click('[data-close=stats]');
  // message urgent du PC avec accusé de lecture
  const mid = sql("INSERT INTO scan_messages (event_id, body, level, must_ack, user_id, created_at) VALUES (1, 'Fermez la porte B, foule trop dense', 'alert', 1, 1, NOW()); SELECT LAST_INSERT_ID();");
  await p.click('#s-scan [data-go=menu]'); await p.click('#m-stats'); await p.waitForTimeout(2000); await p.click('[data-close=stats]');
  out('message', (await p.locator('#pc-msg').getAttribute('class')) + ' · ' + await p.locator('#pc-msg-ok').innerText()); await p.screenshot({ path: CAPT + 'controle-urgent.png' });
  await p.click('#pc-msg-ok'); await p.waitForTimeout(800); out('accusé', sql(`SELECT COUNT(*) FROM scan_acks WHERE message_id=${mid}`));
  sql(`DELETE FROM scan_acks WHERE message_id=${mid}; DELETE FROM scan_messages WHERE id=${mid}`);
  out('JS', JSON.stringify(errs)); await br.close(); })();
