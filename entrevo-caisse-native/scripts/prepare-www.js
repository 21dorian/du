// Copie l'application web (../entrevo/caisse) dans www/, le dossier embarqué dans l'application native.
const fs = require('fs'), path = require('path');
const src = path.join(__dirname, '..', '..', 'entrevo', 'caisse'), dst = path.join(__dirname, '..', 'www');
fs.rmSync(dst, { recursive: true, force: true });
fs.cpSync(src, dst, { recursive: true, filter: (f) => !/(\.htaccess|sw\.js)$/.test(f) });
console.log('www/ prêt (' + fs.readdirSync(dst).length + ' fichiers).');
