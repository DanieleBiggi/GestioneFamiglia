self.addEventListener('install', event => {
  event.waitUntil(
    caches.open('gf-cache').then(cache => {
      return cache.addAll([
        '/Gestionale25/',
        '/Gestionale25/login.php',
        '/Gestionale25/manifest.webmanifest'
      ]);
    })
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(resp => {
      return resp || fetch(event.request);
    })
  );
});
