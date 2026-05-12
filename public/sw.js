// Service Worker – JuMa Schiedsrichter-App
// Strategie: Network-First für Seiten & API, Cache-First nur für echte Assets

// VERSION ERHÖHEN bei jedem Deploy (CSS/JS-Änderungen werden sonst nicht übernommen)
const CACHE_NAME = 'juma-v13';

// Nur echte statische Assets voraufladen — KEINE PHP-Seiten
const STATIC_ASSETS = [
    '/assets/css/main.css',
    '/assets/css/judge.css',
    '/assets/js/app.js',
    '/assets/js/qr.js',
    '/assets/js/offline.js',
    '/assets/js/station.js',
    '/manifest.json',
];

// Dateiendungen die Cache-First bekommen (unveränderliche Assets)
const CACHE_FIRST_EXTENSIONS = ['.css', '.js', '.png', '.jpg', '.jpeg', '.svg', '.webp', '.woff2', '.woff'];

// Installation: nur echte Assets voraufladen
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// Aktivierung: ALLE alten Caches löschen
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

// Fetch-Handler
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Nur http/https — chrome-extension etc. ignorieren
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return;

    // GET-Anfragen auf eigene Domain unterscheiden
    if (event.request.method === 'GET') {
        const ext = url.pathname.substring(url.pathname.lastIndexOf('.'));

        // Echte statische Assets → Cache-First
        if (CACHE_FIRST_EXTENSIONS.includes(ext)) {
            event.respondWith(cacheFirst(event.request));
            return;
        }
    }

    // Alles andere (HTML-Seiten, API, POST) → Network-First
    event.respondWith(networkFirst(event.request));
});

/** Cache-First: Cache → Network → Offline-Fallback */
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
        return new Response('Asset nicht verfügbar (offline)', {
            status: 503,
            headers: { 'Content-Type': 'text/plain; charset=utf-8' },
        });
    }
}

/** Network-First: Network → Cache → Offline-Fallback */
async function networkFirst(request) {
    try {
        const response = await fetch(request);
        // Erfolgreiche HTML-Antworten für Offline-Fallback cachen
        if (response.ok && request.method === 'GET') {
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('text/html')) {
                const cache = await caches.open(CACHE_NAME);
                cache.put(request, response.clone());
            }
        }
        return response;
    } catch {
        // Offline: gecachte Seite zurückgeben falls vorhanden
        const cached = await caches.match(request);
        if (cached) return cached;

        const isApi = new URL(request.url).pathname.startsWith('/api/');
        if (isApi) {
            return new Response(
                JSON.stringify({ success: false, error: 'Offline – keine Verbindung' }),
                { status: 503, headers: { 'Content-Type': 'application/json; charset=utf-8' } }
            );
        }
        return new Response('Offline – Seite nicht verfügbar', {
            status: 503,
            headers: { 'Content-Type': 'text/plain; charset=utf-8' },
        });
    }
}

// Hintergrund-Sync für Offline-Bewertungen
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-scores') {
        event.waitUntil(
            self.clients.matchAll({ type: 'window' }).then((clients) =>
                clients.forEach((c) => c.postMessage({ type: 'SW_SYNC' }))
            )
        );
    }
});
