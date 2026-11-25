// Ajoute au projet Android les modules natifs Entrevo (NFC, terminal Nepting) et les autorisations nécessaires. Sans effet si c'est déjà fait.
const fs = require('fs'), path = require('path');
const root = path.join(__dirname, '..'), app = path.join(root, 'android', 'app', 'src', 'main');
if (!fs.existsSync(app)) { console.error('Projet Android absent : lance d\'abord « npx cap add android ».'); process.exit(1); }
const pkgDir = path.join(app, 'java', 'fr', 'entrevo', 'caisse');
fs.mkdirSync(pkgDir, { recursive: true });
for (const f of fs.readdirSync(path.join(root, 'native', 'android'))) fs.copyFileSync(path.join(root, 'native', 'android', f), path.join(pkgDir, f));
const mf = path.join(app, 'AndroidManifest.xml');
let x = fs.readFileSync(mf, 'utf8');
const add = [
  '<uses-permission android:name="android.permission.NFC" />',
  '<uses-feature android:name="android.hardware.nfc" android:required="false" />',
  '<uses-permission android:name="android.permission.CAMERA" />',
  '<uses-feature android:name="android.hardware.camera" android:required="false" />',
  '<uses-permission android:name="android.permission.VIBRATE" />',
].filter((l) => !x.includes(l));
if (add.length) { x = x.replace(/(\s*)<application/, '$1' + add.join('$1') + '$1<application'); fs.writeFileSync(mf, x); }
console.log('Android : modules Entrevo copiés, ' + add.length + ' autorisation(s) ajoutée(s).');
