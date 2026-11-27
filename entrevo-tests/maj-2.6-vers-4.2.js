// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const LIVRAISONS = __path.resolve(__dirname, '../livraisons') + '/';
const { chromium } = require('playwright');
const B = 'http://127.0.0.1:8090/t26f';
(async () => { const br = await chromium.launch(); const p = await (await br.newContext()).newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.accept());
  await p.goto(B + '/install.php');
  const fill = async (n, v) => { if (await p.locator('[name=' + n + ']').count()) await p.fill('[name=' + n + ']', v); };
  await fill('db_host', '127.0.0.1'); await fill('db_port', '3306'); await fill('db_name', 'entrevo26f'); await fill('db_user', 'entrevo'); await fill('db_pass', 'entrevo');
  await fill('platform', 'Test 2.6'); await fill('base_url', B); await fill('admin_name', 'Admin'); await fill('admin_email', 'a@b.fr'); await fill('admin_pass', 'admin1234');
  if (await p.locator('[name=demo]').count()) await p.check('[name=demo]');
  await p.click('button[type=submit], button.btn'); await p.waitForLoadState(); await p.waitForTimeout(1500);
  console.log('installation :', (await p.textContent('body')).replace(/\s+/g, ' ').slice(0, 160));
  await p.goto(B + '/connexion'); await p.fill('#email', 'a@b.fr'); await p.fill('#password', 'admin1234'); await p.click('button.btn-lg'); await p.waitForLoadState();
  await p.goto(B + '/admin/mises-a-jour'); console.log('version avant :', (await p.textContent('body')).match(/2\.6\.0/) ? '2.6.0' : '?');
  await p.setInputFiles('input[name=package]', LIVRAISONS + 'maj-4.2.0-depuis-2.6.0.zip'); await p.click('#upload-form button.btn-lg');
  await p.waitForLoadState(); await p.waitForTimeout(2500);
  console.log((await p.textContent('#update-result h2')).trim(), '|', (await p.textContent('#health')).trim());
  const bad = [];
  for (const u of ['/', '/admin', '/admin/reglages?tab=plateforme', '/admin/mises-a-jour', '/pay', '/pay/connexion', '/pay/pro', '/pay/pro/e/1/bilan', '/pro/fidelite', '/aide', '/widget/achat/demo', '/admin/reglages?tab=payments', '/pro/connecteurs', '/pro/automatisations', '/admin/demandes', '/caisse/', '/admin/organisateurs']) { const r = await p.goto(B + u); const t = await p.content(); if (r.status() >= 400 || t.includes('Oups')) bad.push(r.status() + ' ' + u); }
  console.log('KO', bad, 'JS', errs.length ? errs : 'aucune'); await br.close(); })();
