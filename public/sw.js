// Service worker: an offline page for navigations and a cache for static assets.
// Signed-in pages are never cached, so private content does not outlive a logout.
const VERSION = 'sc-v1';
const SCOPE = new URL(self.registration.scope).pathname;
const OFFLINE = SCOPE + 'offline.html';
const PRECACHE = [
  OFFLINE,
  SCOPE + 'assets/app.css',
  SCOPE + 'assets/theme.js',
  SCOPE + 'assets/icons.svg',
  SCOPE + 'assets/img/favicon.svg',
  SCOPE + 'assets/fonts/Inter-Variable.woff2',
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== location.origin) return;

  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match(OFFLINE)));
    return;
  }

  if (url.pathname.startsWith(SCOPE + 'assets/')) {
    // Stale-while-revalidate: answer from the cache, refresh it in the background.
    event.respondWith(
      caches.open(VERSION).then(async (cache) => {
        const cached = await cache.match(request, { ignoreSearch: true });
        const fresh = fetch(request)
          .then((response) => {
            if (response.ok) cache.put(request, response.clone());
            return response;
          })
          .catch(() => cached);
        return cached || fresh;
      })
    );
  }
});
