// Scénario de test Entrevo (Playwright). Voir README.md. Variables : BASE (URL du site), CAPTURES (dossier des captures).
const __os = require('os'), __path = require('path'), __fs = require('fs');
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const CAPT = (process.env.CAPTURES || __path.join(__os.tmpdir(), 'entrevo-captures')) + '/'; __fs.mkdirSync(CAPT, { recursive: true });
const LIVRAISONS = __path.resolve(__dirname, '../livraisons') + '/';
const { chromium } = require('playwright');
const B = BASE;
(async () => { const br = await chromium.launch(); const p = await (await br.newContext()).newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  await p.goto(B + '/connexion'); await p.fill('#email', 'orga@demo.entrevo'); await p.fill('#password', 'demo1234'); await p.click('button.btn-lg'); await p.waitForLoadState();
  let tot = 0, bad = 0; const worst = [];
  for (const u of ['/pro/evenements/1/tarifs?edit=1', '/pro/evenements/1/modifier', '/pro/organisation', '/pro/evenements/1/formulaire', '/pro/evenements/1/promos', '/pro/evenements/1/stocks', '/pro/evenements/1/equipes', '/pro/abonnements', '/pro/campagnes', '/e/festival-lumieres-d-ete', '/pro/evenements/1/guichet/classique']) {
    await p.goto(B + u);
    const r = await p.evaluate(() => { const f = [...document.querySelectorAll('input:not([type=hidden]):not([type=submit]):not([type=button]),select,textarea')].filter(e => e.offsetParent !== null || e.closest('details'));
      const no = f.filter(e => !(e.labels && e.labels.length) && !e.getAttribute('aria-label') && !e.getAttribute('aria-labelledby') && !e.title);
      return [f.length, no.length, no.slice(0, 3).map(e => e.name + '|' + (e.placeholder || ''))]; });
    tot += r[0]; bad += r[1]; if (r[1]) worst.push(u + ' ' + r[1] + ' ' + r[2].join(', '));
  }
  console.log('champs', tot, 'sans nom accessible', bad); console.log(worst.join('\n')); console.log('JS', JSON.stringify(errs)); await br.close(); })();
