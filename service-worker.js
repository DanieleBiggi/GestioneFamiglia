// Gestionale25/service-worker.js
// Minimal service worker to keep PWA capabilities without caching

self.addEventListener('install', event => {
  // Activate worker immediately
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  // Remove any existing caches from older versions
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.map(k => caches.delete(k))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  // I redirect OAuth verso ChatGPT devono essere navigazioni native del browser.
  // Con respondWith(fetch(...)) un redirect verso un altro dominio può essere
  // intercettato/annullato dal Service Worker.
  const url = new URL(event.request.url);
  if (url.pathname.startsWith('/Gestionale25/api/oauth/')) {
    return;
  }

  // Per tutte le altre richieste, comportamento PWA invariato (nessuna cache).
  event.respondWith(fetch(event.request));
});
