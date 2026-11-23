/* Entrevo Scan — service worker : garde l'appli de contrôle disponible sans réseau. */
var CACHE = 'entrevo-scan-v3';
var scope = self.registration.scope; // ex. https://site.fr/  (dossier d'Entrevo)
var SHELL = ['scan', 'assets/js/scan.js', 'assets/js/jsQR.js', 'assets/css/app.css', 'assets/css/scan.css',
  'assets/fonts/barlow-condensed-latin-700-normal.woff2', 'assets/fonts/barlow-condensed-latin-800-normal.woff2',
  'assets/fonts/barlow-latin-400-normal.woff2', 'assets/fonts/barlow-latin-600-normal.woff2', 'assets/img/icon.svg', 'assets/img/icon-192.png', 'assets/fonts/host-grotesk-latin-400-normal.woff2', 'assets/fonts/host-grotesk-latin-500-normal.woff2', 'assets/fonts/host-grotesk-latin-600-normal.woff2', 'assets/fonts/host-grotesk-latin-700-normal.woff2', 'assets/fonts/host-grotesk-latin-800-normal.woff2', 'assets/fonts/space-mono-latin-400-normal.woff2', 'assets/fonts/space-mono-latin-700-normal.woff2'];

self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(CACHE).then(function (c) {
    return Promise.all(SHELL.map(function (p) { return c.add(new Request(scope + p, { credentials: 'same-origin' })).catch(function () { /* facultatif */ }); }));
  }).then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (keys) {
    return Promise.all(keys.filter(function (k) { return k.indexOf('entrevo-scan-') === 0 && k !== CACHE; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') return;
  var url = new URL(req.url);
  if (url.origin !== location.origin || !url.href.startsWith(scope)) return;
  var path = url.href.slice(scope.length).split('?')[0];
  if (path.indexOf('api/') === 0) return; // l'appli gère elle-même le hors ligne des données
  var isShell = path === 'scan' || SHELL.indexOf(path) >= 0;
  if (!isShell) return;
  // réseau d'abord (pour avoir la dernière version), cache en secours
  e.respondWith(fetch(req).then(function (res) {
    if (res.ok) { var copy = res.clone(); caches.open(CACHE).then(function (c) { c.put(path === 'scan' ? scope + 'scan' : req, copy); }); }
    return res;
  }).catch(function () {
    return caches.match(path === 'scan' ? scope + 'scan' : req, { ignoreSearch: true });
  }));
});
