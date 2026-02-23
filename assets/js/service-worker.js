const CACHE_NAME = 'ek-survey-v1';

// Parse query params to get assets
const params = new URL(self.location).searchParams;
const ASSETS_TO_CACHE = [
    params.get('css'),
    params.get('js'),
    params.get('offline'),
].filter(Boolean);

self.addEventListener('install', (event) => {
    self.skipWaiting(); // Force waiting service worker to become active
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
});

self.addEventListener('fetch', (event) => {
    // Only handle GET requests
    if (event.request.method !== 'GET') return;

    // Strategy: Network first, falling back to cache
    // This ensures we get the latest survey structure if online
    event.respondWith(
        fetch(event.request)
            .then((response) => {
                // If successful response, clone and cache it (dynamic caching)
                // We mainly want to cache the survey page itself so it opens offline next time
                if (response && response.status === 200 && (response.type === 'basic' || response.type === 'cors' || response.type === 'default')) {
                    const responseToCache = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseToCache);
                    });
                }
                return response;
            })
            .catch(() => {
                // If offline, try cache
                return caches.match(event.request).then((response) => {
                    if (response) {
                        return response;
                    }
                    // Optional: Return a fallback offline page if main page not in cache
                    // return caches.match('/offline.html');
                });
            })
    );
});

self.addEventListener('activate', (event) => {
    const cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheWhitelist.indexOf(cacheName) === -1) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim()) // Take control of all clients immediately
    );
});
