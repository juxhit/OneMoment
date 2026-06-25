const SW_VERSION = '1';
const CACHE_NAME = 'onemoment-static-' + SW_VERSION;

function staticBase() {
  return new URL('./', self.location).href;
}

function isStaticAsset(url) {
  return url.pathname.includes('/assets/');
}

function mustBypassCache(url) {
  if (url.pathname.includes('/api/')) return true;
  if (url.pathname.includes('/i/u/') || url.pathname.includes('/i/t/')) return true;
  if (url.pathname.endsWith('/serve.php')) return true;
  return false;
}

self.addEventListener('install', (event) => {
  const base = staticBase();
  const assets = [
    'assets/css/app.css',
    'assets/js/app.js',
    'assets/img/icons/icon-192.png',
    'assets/img/icons/icon-512.png',
    'assets/img/icons/apple-touch-icon.png',
  ].map((path) => base + path);

  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(assets))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => key.startsWith('onemoment-static-') && key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') {
    return;
  }

  const url = new URL(event.request.url);

  if (mustBypassCache(url) || !isStaticAsset(url)) {
    return;
  }

  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) {
        return cached;
      }
      return fetch(event.request).then((response) => {
        if (!response || response.status !== 200 || response.type === 'opaque') {
          return response;
        }
        const copy = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
        return response;
      });
    })
  );
});