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
  // participants : filtres et lien vers la fiche
  await p.goto(BASE + '/pro/evenements/1/participants?type=invitation'); out('filtre invitations', await p.locator('.card-head h2').first().innerText());
  const exp = await p.request.get(BASE + '/pro/evenements/1/participants?csv=1&cols[]=last&cols[]=rate&cols[]=channel'); out('export colonnes', (await exp.text()).split('\n')[0]);
  await p.goto(BASE + '/pro/evenements/1/participants'); await p.locator('table a[href*="/pro/billets/"]:visible').first().click(); await p.waitForLoadState();
  const tid = p.url().split('/').pop(); const code0 = sql(`SELECT code FROM tickets WHERE id=${tid}`);
  out('fiche billet', (await p.locator('.page-head').last().innerText()).slice(0, 120)); await p.screenshot({ path: CAPT + 'fiche-billet.png', fullPage: true });
  // entrée manuelle
  if (await p.locator('button[value=checkin]').count()) { await p.click('button[value=checkin]'); await p.waitForLoadState(); out('entrée manuelle', await toast()); }
  // réédition : l'ancien code est refusé
  await p.locator('details:has([value=reissue]) summary').click(); await p.fill('form:has([value=reissue]) input[name=reason]', 'Billet perdu'); await p.uncheck('form:has([value=reissue]) input[name=send]');
  await p.click('form:has([value=reissue]) button'); await p.waitForLoadState(); out('réédition', await toast());
  const scan = execSync(`php -r '$_SERVER["HTTP_HOST"]="x";require "${__path.resolve(__dirname, '../entrevo/app/bootstrap.php')}"; $r = Tickets::scan(["event" => one("SELECT * FROM events WHERE id = 1"), "code" => "${code0}", "direction" => "in", "user" => null, "uuid" => bin2hex(random_bytes(8)), "device" => "test"]); echo $r["result"], " ", json_encode($r["info"] ?? []);'`).toString();
  out('ancien code au scan', scan);
  // stock : blocage, limite, invitation
  await p.goto(BASE + '/pro/evenements/1/stock'); const before = sql("SELECT quota FROM rates WHERE id=1");
  await p.fill('form:has([value=hold]) input[name=label]', 'Artistes'); await p.fill('form:has([value=hold]) input[name=qty]', '20'); await p.selectOption('form:has([value=hold]) select[name=rate_id]', '1');
  await p.click('form:has([value=hold]) button'); await p.waitForLoadState(); out('blocage', await toast());
  out('disponibles tarif 1', execSync(`php -r '$_SERVER["HTTP_HOST"]="x";require "${__path.resolve(__dirname, '../entrevo/app/bootstrap.php')}"; echo Tickets::remaining(one("SELECT * FROM rates WHERE id = 1"), one("SELECT * FROM events WHERE id = 1")), " (bloqués ", Tickets::blocked(null, 1, null), ")";'`).toString());
  await p.locator('table details summary').first().click(); const inv = p.locator('form:has([value=invite])').first();
  await inv.locator('input[name=name]').fill('Nova Kid'); await inv.locator('input[name=qty]').fill('2'); await inv.locator('button').click(); await p.waitForLoadState(); out('invitations', await toast());
  out('bloqués restants', sql("SELECT COALESCE(SUM(qty),0) FROM place_holds WHERE event_id=1 AND released_at IS NULL"));
  await p.fill('form:has([value=limit]) input[name=max_per_buyer]', '6'); await p.click('form:has([value=limit]) button'); await p.waitForLoadState(); out('limite', await toast());
  await p.screenshot({ path: CAPT + 'stock.png', fullPage: true });
  const lim = execSync(`php -r '$_SERVER["HTTP_HOST"]="x";require "${__path.resolve(__dirname, '../entrevo/app/bootstrap.php')}"; $ev = one("SELECT * FROM events WHERE id = 1"); $r = Tickets::createOrder(["event" => $ev, "org" => one("SELECT * FROM organizers WHERE id = 1"), "quantities" => [1 => 7], "buyer" => ["first_name" => "A", "last_name" => "B", "email" => "limite@test.fr", "phone" => ""], "method" => "online"]); echo implode(" ", $r["errors"] ?? ["acceptée"]); if (!empty($r["order"])) q("DELETE FROM orders WHERE id = ?", [$r["order"]["id"]]);'`).toString();
  out('7 billets en ligne', lim);
  // remise en vente et nettoyage
  await p.locator('table details summary').first().click(); await p.locator('form:has([value=release]) button').first().click(); await p.waitForLoadState(); out('libération', await toast());
  sql("UPDATE events SET max_per_buyer=0 WHERE id=1; UPDATE place_holds SET released_at=NOW() WHERE event_id=1 AND released_at IS NULL");
  out('JS', JSON.stringify(errs)); await br.close(); })();
