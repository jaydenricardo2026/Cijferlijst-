const CACHE_NAME = 'cijferlijst-v1';
const ASSETS = [
  './',
  './index.php',
  './manifest.json',
  './deepseek.png'
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS)));
});

self.addEventListener('fetch', (e) => {
  e.respondWith(
    caches.match(e.request).then((response) => {
      return response || fetch(e.request);
    })
  );
});
