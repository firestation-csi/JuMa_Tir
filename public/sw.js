// Service Worker – JuMa Tirol Schiedsrichter-App
// Cache-First für Assets, Network-First für API-Calls

const CACHE_NAME = 'juma-tir-v1';
const CACHE_VERSION = 1;

// Statische Assets die gecacht werden
const STATIC_ASSETS = [
    '/judge',
    '/judge/station',
    '/assets/css/main.css',
    '/assets/css/judge.css',
    '/assets/js/app.js',
    '/assets/js/qr.js',
    '/assets/js/offline.js',
    '/manifest.json',
];

// Installation: Assets voraufladen
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        }).then(() => {
            return self.skipWaiting();
        })
    );
});

// Aktivierung: alte Caches löschen
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch-Handler
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Nur http/https cachen — chrome-extension:// und andere Schemes ignorieren
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return;

    // API-Anfragen: Network-First
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(networkFirst(event.request));
        return;
    }

    // Statische Assets: Cache-First
    event.respondWith(cacheFirst(event.request));
});

/** Cache-First: zuerst Cache, bei Fehler Network */
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
        return new Response('Offline – Seite nicht verfügbar', {
            status: 503,
            headers: { 'Content-Type': 'text/plain; charset=utf-8' },
        });
    }
}

/** Network-First: zuerst Network, bei Fehler Cache */
async function networkFirst(request) {
    try {
        return await fetch(request);
    } catch {
        const cached = await caches.match(request);
        if (cached) return cached;
        return new Response(
            JSON.stringify({ success: false, error: 'Offline – keine Verbindung' }),
            {
                status: 503,
                headers: { 'Content-Type': 'application/json; charset=utf-8' },
            }
        );
    }
}

// Sync-Event für Hintergrund-Synchronisation
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-scores') {
        event.waitUntil(syncOfflineScores());
    }
});

async function syncOfflineScores() {
    // Clients benachrichtigen, damit offline.js die IndexedDB leert
    const clients = await self.clients.matchAll({ type: 'window' });
    clients.forEach((client) => client.postMessage({ type: 'SW_SYNC' }));
}
