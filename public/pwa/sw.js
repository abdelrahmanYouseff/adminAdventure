const CACHE = 'worker-pwa-v2';
const PRECACHE = [
  '/worker-app/login',
  '/assets/logo.png',
  '/pwa/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))),
    ).then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);

  if (url.origin !== self.location.origin) {
    return;
  }

  // Network-first for app navigations; cache fallback for static assets.
  if (request.mode === 'navigate' || url.pathname.startsWith('/worker-app')) {
    event.respondWith(
      fetch(request).catch(() => caches.match('/worker-app/login')),
    );
    return;
  }

  if (url.pathname.startsWith('/assets/') || url.pathname.startsWith('/build/') || url.pathname.startsWith('/pwa/')) {
    event.respondWith(
      caches.match(request).then((cached) =>
        cached || fetch(request).then((response) => {
          const copy = response.clone();
          caches.open(CACHE).then((cache) => cache.put(request, copy));
          return response;
        }),
      ),
    );
  }
});
