// Gestionale25/service-worker.js
const CACHE_VERSION = 'v3-2024-08-16';
const APP_SHELL = [
  '/Gestionale25/offline.html'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then(c => c.addAll(APP_SHELL))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_VERSION).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  const p = url.pathname;

  // Bypass totale per root e pagine di autenticazione
  const isAuthOrRoot =
    p === '/Gestionale25/' ||
    p.startsWith('/Gestionale25/login') ||
    p.includes('/Gestionale25/login_passcode') ||
    p.includes('/Gestionale25/verifica_2fa');

  if (isAuthOrRoot) {
    event.respondWith(fetch(event.request));
    return;
  }

  // Cache-first con fallback offline per il resto
  event.respondWith(
    caches.match(event.request, { ignoreSearch: true }).then(resp => {
      return resp || fetch(event.request).then(networkResp => {
        const clone = networkResp.clone();
        caches.open(CACHE_VERSION).then(c => c.put(event.request, clone));
        return networkResp;
      });
    }).catch(() => caches.match('/Gestionale25/offline.html'))
  );
});
