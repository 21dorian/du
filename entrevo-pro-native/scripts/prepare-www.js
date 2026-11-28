// Copie l'écran de démarrage (www-src) dans www/, le dossier embarqué dans l'application native.
const fs = require('fs'), path = require('path');
const src = path.join(__dirname, '..', 'www-src'), dst = path.join(__dirname, '..', 'www');
fs.rmSync(dst, { recursive: true, force: true });
fs.cpSync(src, dst, { recursive: true });
const v = require('../package.json').version;
const f = path.join(dst, 'index.html'); fs.writeFileSync(f, fs.readFileSync(f, 'utf8').replace(/<span id="v">[^<]*<\/span>/, '<span id="v">' + v + '</span>'));
console.log('www/ prêt (' + fs.readdirSync(dst).length + ' fichier(s)), version ' + v + '.');
