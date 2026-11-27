// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const LIVRAISONS = __path.resolve(__dirname, '../livraisons') + '/';
const { chromium } = require('playwright'); const { execSync } = require('child_process');
const B = BASE, C = CAPT; 
const sql = (q) => execSync(`mysql -N entrevo -e "${q}"`).toString().trim();
const php = (code) => execSync(`php -r '$_SERVER["HTTP_HOST"]="127.0.0.1:8080";$_SERVER["SCRIPT_NAME"]="/index.php";require "${__path.resolve(__dirname, '../entrevo/app/bootstrap.php')}";${code}' 2>&1`).toString().trim();
const out = (k, v) => console.log(k.padEnd(26), typeof v === 'string' ? v.replace(/\s+/g, ' ').slice(0, 170) : JSON.stringify(v));
(async () => { const br = await chromium.launch(); const errs = [];
  const ctx = async (w, h) => { const p = await (await br.newContext({ viewport: { width: w, height: h } })).newPage(); p.on('pageerror', e => errs.push(p.url() + ' ' + e.message)); p.on('dialog', d => d.accept()); return p; };
  const toast = async (p) => (await p.locator('.toasts, .flash').first().innerText().catch(() => '')).trim();
  const pin = async (p, sel, v) => { for (const k of v) await p.click(sel + ' [data-k="' + k + '"]'); };

  const p = await ctx(1440, 1000);
  await p.goto(B + '/connexion'); await p.fill('#email', 'orga@demo.entrevo'); await p.fill('#password', 'demo1234'); await p.click('button.btn-lg'); await p.waitForLoadState();
  const anon = await ctx(1200, 900);
  // automatisations
  await p.goto(B + '/pro/automatisations'); for (const n of ['auto_thanks', 'referral', 'auto_birthday']) if (!(await p.locator(`input[name=${n}]`).isChecked())) await p.click(`label.switch:has(input[name=${n}]) span`); await p.fill('input[name=referral_n]', '1');
  await p.click('form:has([name=referral_n]) > div > button.btn'); await p.waitForLoadState(); out('automatisations', await toast(p));
  const tk = sql("SELECT o.id FROM orders o WHERE o.organizer_id=1 AND o.status='paid' AND o.email<>'' ORDER BY o.id DESC LIMIT 1");
  sql(`UPDATE orders SET birth_date=DATE_SUB(CURDATE(), INTERVAL 30 YEAR), marketing_optin=1 WHERE id=${tk}; UPDATE orders SET referrer_order_id=${tk} WHERE id=(SELECT id FROM (SELECT id FROM orders WHERE organizer_id=1 AND status='paid' AND id<>${tk} ORDER BY id DESC LIMIT 1) x); DELETE FROM settings WHERE k LIKE 'extras_%'`);
  out('tâche auto', php('echo Extras::autoCodes() . " anniversaire/retour/merci, " . Extras::referrals() . " parrainage";'));
  await p.goto(B + '/pro/automatisations'); await p.screenshot({ path: C + '9-automatisations.png', fullPage: true }); out('codes envoyés', sql("SELECT GROUP_CONCAT(CONCAT(scenario,'=',code)) FROM auto_codes"));
  // PMR + contrôle d'identité
  sql("UPDATE rates SET companion=1 WHERE id=1; UPDATE events SET id_check=1 WHERE id=1");
  const oid = php(`$id = insert("orders", ["ref" => "TPMR" . rand(1000,9999), "token" => bin2hex(random_bytes(8)), "event_id" => 1, "organizer_id" => 1, "email" => "pmr@exemple.fr", "first_name" => "Léa", "last_name" => "Martin", "subtotal" => 6900, "total" => 6900, "method" => "card", "status" => "pending", "created_at" => now()]); insert("order_items", ["order_id" => $id, "kind" => "rate", "rate_id" => 1, "name" => "Pass", "qty" => 1, "unit_price" => 6900]); Tickets::markPaid($id); echo $id;`);
  out('billets PMR', sql(`SELECT GROUP_CONCAT(CONCAT(rate_name,' ',first_name,' ',price/100,'€')) FROM tickets WHERE order_id=${oid.split('\n').pop()}`));
  sql("UPDATE rates SET companion=0 WHERE id=1");
  const sd = await p.request.get(B + '/api/scan/event/1/data'); const sj = await sd.json(); out('consigne de scan', JSON.stringify(Object.values(sj.rules || {})[0] || {}).slice(0, 140));
  sql("UPDATE events SET id_check=0 WHERE id=1");
  // vestiaire
  const code = sql("SELECT code FROM tickets WHERE event_id=1 AND status='valid' AND type='ticket' LIMIT 1");
  await p.goto(B + '/pro/evenements/1/vestiaire'); await p.fill('input[name=ticket_code]', code); await p.fill('input[name=items]', 'Manteau noir'); await p.fill('input[name=fee]', '2'); await p.click('form:has([value=in]) button.btn'); await p.waitForLoadState();
  out('vestiaire dépôt', await p.locator('.card.center').innerText()); await p.screenshot({ path: C + '10-vestiaire.png', fullPage: true });
  await p.fill('input[name=q]', code); await p.click('form:has([name=q]) button.btn'); await p.waitForLoadState(); out('vestiaire rendu', await toast(p));
  // badges
  await p.goto(B + '/pro/evenements/1/badges-nominatifs'); await p.screenshot({ path: C + '11-badges.png' });
  const bp = await ctx(1000, 900); await bp.context().addCookies(await p.context().cookies()); await bp.goto(B + '/pro/evenements/1/badges-nominatifs?print=1&format=a4'); out('badges', (await bp.locator('.b').count()) + ' badges, QR : ' + (await bp.locator('.b svg').count())); await bp.screenshot({ path: C + '12-badges-print.png' });
  // contacts : import et doublons
  await p.goto(B + '/pro/contacts'); await p.fill('textarea[name=paste]', 'alice@exemple.fr;Alice Durand\nbob@exemple.fr,Bob Leroy\nnimportequoi'); await p.check('input[name=consent]'); await p.click('form:has([value=import]) button.btn'); await p.waitForLoadState(); out('import contacts', await toast(p));
  sql("UPDATE orders SET first_name='Jean', last_name='Dupuis', email='jean.dupuis@gmail.com' WHERE id=(SELECT id FROM (SELECT id FROM orders WHERE organizer_id=1 AND status='paid' ORDER BY id LIMIT 1) x); UPDATE orders SET first_name='Jean', last_name='Dupuis', email='jeandupuis@gmail.com' WHERE id=(SELECT id FROM (SELECT id FROM orders WHERE organizer_id=1 AND status='paid' ORDER BY id LIMIT 1 OFFSET 1) x)");
  await p.goto(B + '/pro/contacts'); out('doublons détectés', await p.locator('h2:has-text("Doublons")').innerText()); await p.screenshot({ path: C + '13-contacts.png', fullPage: true });
  await p.locator('form:has([value=merge])').first().locator('button').click(); await p.waitForLoadState(); out('fusion', await toast(p));
  // rôles personnalisés
  await p.goto(B + '/pro/equipe'); await p.fill('input[name=crole_name]', 'Billetterie seulement'); await p.click('form:has([value=crole_save]) button.btn'); await p.waitForLoadState(); out('rôle', await toast(p));
  sql("DELETE FROM users WHERE email='billet@demo.entrevo'");
  await p.fill('input[name=name]', 'Agent billetterie'); await p.fill('input[name=email]', 'billet@demo.entrevo'); await p.selectOption('form:has([value=add]) select[name=role]', { label: 'Billetterie seulement (rôle perso)' }); await p.fill('input[name=password]', 'billet1234'); await p.click('form:has([value=add]) button.btn'); await p.waitForLoadState();
  await p.screenshot({ path: C + '14-roles.png', fullPage: true });
  const ag = await ctx(1280, 900); await ag.goto(B + '/connexion'); await ag.fill('#email', 'billet@demo.entrevo'); await ag.fill('#password', 'billet1234'); await ag.click('button.btn-lg'); await ag.waitForLoadState();
  const res = []; for (const u of ['/pro/commandes', '/pro/evenements/1/tarifs', '/pro/finances', '/pro/campagnes']) res.push(u + '=' + (await ag.goto(B + u)).status()); out('droits du rôle', res.join(' '));
  // appli mobile
  const m = await ctx(390, 844); await m.context().addCookies(await p.context().cookies()); await m.goto(B + '/pro/mobile'); out('appli orga', await m.locator('.big').innerText()); await m.screenshot({ path: C + '15-appli-orga.png', fullPage: true });
  // borne
  const k = await ctx(1024, 768); await k.goto(B + '/connexion'); await k.fill('#email', 'orga@demo.entrevo'); await k.fill('#password', 'demo1234'); await k.click('button.btn-lg'); await k.waitForLoadState();
  await k.goto(B + '/pro/evenements/1/controle'); await k.fill('input[name=pin]', '4321'); await k.click('form[action$="/borne/festival-lumieres-d-ete"] button'); await k.waitForLoadState();
  out('borne', k.url() + ' | ' + await k.locator('.kiosk-bar').innerText()); await k.screenshot({ path: C + '16-borne.png' });
  out('borne sans session', String((await k.goto(B + '/pro')).url().includes('connexion')));
  await k.goto(B + '/borne/sortie'); await k.fill('input[name=pin]', '4321'); await k.click('button.btn'); await k.waitForLoadState(); out('sortie borne', k.url());
  // public : favoris, suivre, covoiturage, aide, démo
  const u = await ctx(390, 844);
  await u.goto(B + '/e/festival-lumieres-d-ete'); await u.click('[data-fav]'); out('favori', await u.locator('[data-fav]').innerText()); out('recos', String(await u.locator('.reco .agenda-row').count()));
  await u.goto(B + '/favoris'); await u.waitForLoadState(); out('page favoris', String(await u.locator('[data-fav-row]').count())); await u.screenshot({ path: C + '17-favoris.png', fullPage: true });
  await u.goto(B + '/o/arc-en-scene'); await u.click('.follow-pop summary'); await u.fill('.follow-pop input[name=email]', 'fan@exemple.fr'); await u.click('.follow-pop button'); await u.waitForLoadState(); out('suivre', await toast(u));
  sql("UPDATE events SET followers_notified_at=NULL, created_at=NOW() WHERE id=(SELECT MAX(id) FROM (SELECT id FROM events WHERE organizer_id=(SELECT id FROM organizers WHERE slug='arc-en-scene') AND status='published' AND starts_at>NOW()) x)");
  sql("DELETE FROM settings WHERE k='extras_followers'"); out('abonnés prévenus', php('echo Extras::notifyFollowers();'));
  await u.goto(B + '/e/festival-lumieres-d-ete/covoiturage'); await u.fill('form:has([value=add]) input[name=name]', 'Max'); await u.fill('input[name=city]', 'Lyon'); await u.fill('input[name=seats]', '3'); await u.fill('form:has([value=add]) input[name=email]', 'max@exemple.fr'); await u.click('form:has([value=add]) button'); await u.waitForLoadState(); out('covoiturage', await toast(u));
  await u.click('.cp-item summary'); await u.fill('.cp-item input[name=name]', 'Zoé'); await u.fill('.cp-item input[name=email]', 'zoe@exemple.fr'); await u.fill('.cp-item textarea', 'Je pars aussi de Lyon !'); await u.click('.cp-item button'); await u.waitForLoadState(); out('message covoit', await toast(u)); await u.screenshot({ path: C + '18-covoiturage.png', fullPage: true });
  await u.goto(B + '/aide?q=rembours'); out('aide', String(await u.locator('.faq-item').count()) + ' réponses'); await u.screenshot({ path: C + '19-aide.png', fullPage: true });
  await u.goto(B + '/demo'); await u.fill('input[name=name]', 'Paul'); await u.fill('input[name=email]', 'paul@asso.fr'); await u.fill('input[name=org_name]', 'Asso Jazz'); await u.click('button.btn'); await u.waitForLoadState(); out('démo', await toast(u));
  await p.goto(B + '/pro/modules'); out('modules « bientôt »', String(await p.locator('text=Bientôt').count())); await p.screenshot({ path: C + '20-modules.png', fullPage: true });
  console.log('JS', errs); await br.close(); })();
