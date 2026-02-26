/**
 * CIVISTROM ID — Service Worker
 *
 * Cache-first pour assets (CSS, JS, images).
 * Network-first pour HTML (pour les mises à jour).
 * Pre-cache tous les fichiers essentiels à l'install.
 */

const CACHE_NAME = 'civistrom-id-v1';

const PRECACHE_URLS = [
    '/',
    '/assets/css/id.css',
    '/assets/js/totp.js',
    '/assets/js/crypto.js',
    '/assets/js/storage.js',
    '/assets/js/scanner.js',
    '/assets/js/app.js',
    '/assets/js/vendor/jsqr.min.js',
];

// ─── Install : pre-cache les assets essentiels ──
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

// ─── Activate : supprimer les anciens caches ────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(
                keys
                    .filter(key => key !== CACHE_NAME)
                    .map(key => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

// ─── Fetch : stratégie par type de ressource ────
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Skip non-GET requests
    if (event.request.method !== 'GET') return;

    // Skip cross-origin requests
    if (url.origin !== location.origin) return;

    // Health check — toujours réseau
    if (url.pathname === '/health') return;

    // Assets (CSS, JS, images) → Cache-first
    if (url.pathname.startsWith('/assets/')) {
        event.respondWith(cacheFirst(event.request));
        return;
    }

    // HTML pages → Network-first (pour les mises à jour)
    event.respondWith(networkFirst(event.request));
});

/**
 * Cache-first : cache → réseau → cache
 */
async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        // Offline et pas en cache — 503
        return new Response('Offline', { status: 503 });
    }
}

/**
 * Network-first : réseau → cache
 */
async function networkFirst(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        return cached || new Response('Offline', {
            status: 503,
            headers: { 'Content-Type': 'text/html' }
        });
    }
}
