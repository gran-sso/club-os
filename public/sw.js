// Malabar Club Service Worker
// Version: static-v1.0.0
// Policy: Static-only caching. Authenticated financial responses and state-changing mutations MUST NEVER be cached.

const CACHE_NAME = 'malabar-club-static-v2';
const OFFLINE_URL = '/offline.html';

const PRECACHE_ASSETS = [
  OFFLINE_URL,
  '/manifest.json',
  '/manifest-admin.json',
  '/manifest-member.json',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/icons/apple-touch-icon.png',
  '/icons/icon-192.svg',
  '/icons/icon-512.svg'
];

// URLs that MUST NEVER be served from cache or cached
const NEVER_CACHE_PATTERNS = [
  /\/admin/,
  /\/pos/,
  /\/member/,
  /\/api\//,
  /\/livewire/,
  /\/documents\//,
  /\/login/,
  /\/logout/
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(PRECACHE_ASSETS);
    }).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((name) => name !== CACHE_NAME)
          .map((name) => caches.delete(name))
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // 1. Prohibit offline mutations: Only GET requests are handled by service worker
  if (request.method !== 'GET') {
    return;
  }

  // 2. Strict Financial Safeguard: NEVER cache or serve financial/admin/api endpoints from cache
  const isNeverCache = NEVER_CACHE_PATTERNS.some((pattern) => pattern.test(url.pathname));

  if (isNeverCache) {
    event.respondWith(
      fetch(request).catch(() => {
        // If navigation request fails offline, serve the explicit offline guard page
        if (request.mode === 'navigate') {
          return caches.match(OFFLINE_URL);
        }
        return new Response(JSON.stringify({
          error: 'offline_safeguard',
          message: 'Authoritative server connection required. Offline financial requests are prohibited.'
        }), {
          status: 503,
          headers: { 'Content-Type': 'application/json' }
        });
      })
    );
    return;
  }

  // 3. Static assets: Stale-while-revalidate / cache-first for fonts, images, css, js
  event.respondWith(
    caches.match(request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }

      return fetch(request).then((networkResponse) => {
        // Only cache valid static GET responses from same origin
        if (networkResponse && networkResponse.status === 200 && url.origin === location.origin) {
          const contentType = networkResponse.headers.get('content-type') || '';
          if (contentType.includes('text/css') || contentType.includes('application/javascript') || contentType.includes('image/') || contentType.includes('font/')) {
            const responseToCache = networkResponse.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, responseToCache));
          }
        }
        return networkResponse;
      }).catch(() => {
        if (request.mode === 'navigate') {
          return caches.match(OFFLINE_URL);
        }
      });
    })
  );
});
