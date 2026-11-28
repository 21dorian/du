// Ajoute au projet Android le pont natif Entrevo Pro (NFC, notifications) et les autorisations nécessaires. Sans effet si c'est déjà fait.
const fs = require('fs'), path = require('path');
const root = path.join(__dirname, '..'), app = path.join(root, 'android', 'app', 'src', 'main');
if (!fs.existsSync(app)) { console.error('Projet Android absent : lance d\'abord « npx cap add android ».'); process.exit(1); }
const pkgDir = path.join(app, 'java', 'fr', 'entrevo', 'pro');
fs.mkdirSync(pkgDir, { recursive: true });
for (const f of fs.readdirSync(path.join(root, 'native', 'android'))) fs.copyFileSync(path.join(root, 'native', 'android', f), path.join(pkgDir, f));
// icônes Entrevo (remplacent l'icône Capacitor par défaut)
const res = path.join(root, 'native', 'res');
if (fs.existsSync(res)) for (const d of fs.readdirSync(res)) for (const f of fs.readdirSync(path.join(res, d))) { fs.mkdirSync(path.join(app, 'res', d), { recursive: true }); fs.copyFileSync(path.join(res, d, f), path.join(app, 'res', d, f)); }
const bg = path.join(app, 'res', 'values', 'ic_launcher_background.xml');
if (fs.existsSync(bg)) fs.writeFileSync(bg, fs.readFileSync(bg, 'utf8').replace(/<color name="ic_launcher_background">[^<]*<\/color>/, '<color name="ic_launcher_background">#1D1D1F</color>'));
const mf = path.join(app, 'AndroidManifest.xml');
let x = fs.readFileSync(mf, 'utf8');
const add = [
  '<uses-permission android:name="android.permission.NFC" />',
  '<uses-feature android:name="android.hardware.nfc" android:required="false" />',
  '<uses-permission android:name="android.permission.CAMERA" />',
  '<uses-feature android:name="android.hardware.camera" android:required="false" />',
  '<uses-permission android:name="android.permission.VIBRATE" />',
  '<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />',
].filter((l) => !x.includes(l));
if (add.length) { x = x.replace(/(\s*)<application/, '$1' + add.join('$1') + '$1<application'); fs.writeFileSync(mf, x); }
console.log('Android : pont Entrevo Pro copié, ' + add.length + ' autorisation(s) ajoutée(s).');
