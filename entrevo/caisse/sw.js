/* Entrevo Caisse — service worker : l'application s'ouvre même sans réseau (les ventes carte et espèces sont gardées et envoyées plus tard). */
var CACHE = 'entrevo-caisse-v3';
var SHELL = ['./', 'index.html', 'app.css', 'app.js', 'jsQR.js', 'icon.svg', 'icon-192.png', 'manifest.webmanifest'];
self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(CACHE).then(function (c) { return Promise.all(SHELL.map(function (p) { return c.add(p).catch(function () {}); })); }).then(function () { return self.skipWaiting(); }));
});
self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (k) { return Promise.all(k.filter(function (x) { return x.indexOf('entrevo-caisse-') === 0 && x !== CACHE; }).map(function (x) { return caches.delete(x); })); }).then(function () { return self.clients.claim(); }));
});
self.addEventListener('fetch', function (e) {
  var r = e.request;
  if (r.method !== 'GET' || new URL(r.url).pathname.indexOf('/api/') >= 0) return;
  // réseau d'abord (mises à jour), cache en secours (hors ligne)
  e.respondWith(fetch(r).then(function (res) {
    if (res.ok && new URL(r.url).origin === location.origin) { var copy = res.clone(); caches.open(CACHE).then(function (c) { c.put(r, copy); }); }
    return res;
  }).catch(function () { return caches.match(r, { ignoreSearch: true }).then(function (m) { return m || caches.match('index.html'); }); }));
});
