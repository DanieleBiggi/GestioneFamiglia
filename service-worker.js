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
  // Always go to the network, no caching
  event.respondWith(fetch(event.request));
});
