const CACHE_PREFIX = "shoestagram-";
const CACHE_NAME = "shoestagram-v10";
const APP_SCOPE_URL = new URL(self.registration.scope);

function appUrl(path) {
    return new URL(path, APP_SCOPE_URL).toString();
}

const CORE_ASSETS = [
    appUrl("offline.html"),
    appUrl("store-theme.css"),
    appUrl("customer/index.php"),
    appUrl("customer/shop.php"),
    appUrl("customer/collection.php"),
    appUrl("customer/preferences.php"),
    appUrl("customer/about.php"),
    appUrl("customer/contact.php"),
    appUrl("customer/wishlist.php"),
    appUrl("login/login.php"),
];

async function cacheResponse(request, response) {
    if (!response || !response.ok || response.type === "opaque") {
        return;
    }

    const cache = await caches.open(CACHE_NAME);
    await cache.put(request, response.clone());
}

function offlineResponse() {
    const stylesheetUrl = appUrl("store-theme.css");
    const homeUrl = appUrl("customer/index.php");

    return new Response(`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d1018">
    <title>Shoestagram | Offline</title>
    <link rel="stylesheet" href="${stylesheetUrl}">
</head>
<body class="store-body">
    <main class="page-shell main-flow">
        <section class="section" style="padding-top: 120px;">
            <div class="surface-card empty-state">
                <span class="eyebrow">Offline</span>
                <h1 class="display-font">Shoestagram is temporarily offline on this device.</h1>
                <p>Reconnect to continue browsing products, checking reservations, and using the live admin and review features.</p>
                <div class="button-row" style="justify-content:center;">
                    <a href="${homeUrl}" class="button button-dark">Try again</a>
                </div>
            </div>
        </section>
    </main>
</body>
</html>`, {
        status: 503,
        headers: { "Content-Type": "text/html; charset=utf-8" },
    });
}

self.addEventListener("install", (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_NAME);

        /* A temporarily unavailable PHP page must not prevent a fixed worker from installing. */
        await Promise.all(CORE_ASSETS.map(async (asset) => {
            try {
                const response = await fetch(asset, { cache: "reload" });
                await cacheResponse(asset, response);
            } catch (_) {
                // The asset can be refreshed after the local server is available again.
            }
        }));

        await self.skipWaiting();
    })());
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys
                .filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME)
                .map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener("fetch", (event) => {
    if (event.request.method !== "GET") {
        return;
    }

    const requestUrl = new URL(event.request.url);

    if (requestUrl.origin !== self.location.origin || requestUrl.pathname.endsWith("/favicon.ico")) {
        return;
    }

    if (event.request.mode === "navigate") {
        event.respondWith((async () => {
            try {
                const response = await fetch(event.request);
                event.waitUntil(cacheResponse(event.request, response));
                return response;
            } catch (_) {
                const cachedResponse = await caches.match(event.request);
                return cachedResponse || offlineResponse();
            }
        })());
        return;
    }

    event.respondWith((async () => {
        const cachedResponse = await caches.match(event.request);

        if (cachedResponse) {
            event.waitUntil(
                fetch(event.request)
                    .then((response) => cacheResponse(event.request, response))
                    .catch(() => undefined)
            );
            return cachedResponse;
        }

        try {
            const response = await fetch(event.request);
            event.waitUntil(cacheResponse(event.request, response));
            return response;
        } catch (_) {
            return new Response("", { status: 503, statusText: "Offline" });
        }
    })());
});
