// Gestionale25/service-worker.js
const CACHE_VERSION = 'v4-2025-08-16';
const RUNTIME = CACHE_VERSION;
const APP_SHELL = ['/Gestionale25/offline.html'];

// Install
self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(RUNTIME).then(c => c.addAll(APP_SHELL)));
  self.skipWaiting();
});

// Activate: pulizia vecchie cache
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== RUNTIME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Fetch
self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // 1) Ignora TUTTO ciò che non è GET (POST/PUT/DELETE ecc.)
  if (req.method !== 'GET') {
    event.respondWith(fetch(req));
    return;
  }

  // 2) Bypass per root e pagine di autenticazione
  const p = url.pathname;
  const isAuthOrRoot =
    p === '/Gestionale25/' ||
    p.startsWith('/Gestionale25/login') ||
    p.includes('/Gestionale25/login_passcode') ||
    p.includes('/Gestionale25/verifica_2fa');

  if (isAuthOrRoot) {
    event.respondWith(fetch(req));
    return;
  }

  // 3) Solo stesso dominio in cache
  const sameOrigin = url.origin === self.location.origin;

  // 4) Navigazioni HTML: network-first con fallback offline
  if (sameOrigin && req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => caches.match('/Gestionale25/offline.html'))
    );
    return;
  }

  // 5) GET statici: cache-first + aggiornamento in background
  event.respondWith(
    caches.match(req, { ignoreSearch: true }).then((cached) => {
      const fetchPromise = fetch(req).then((netRes) => {
        if (sameOrigin && netRes && netRes.status === 200 && netRes.type === 'basic') {
          const clone = netRes.clone();
          caches.open(RUNTIME).then((c) => c.put(req, clone));
        }
        return netRes;
      }).catch(() => cached || caches.match('/Gestionale25/offline.html'));

      return cached || fetchPromise;
    })
  );
});
