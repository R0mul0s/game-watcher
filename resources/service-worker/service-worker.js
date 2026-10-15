/**
 * Service worker aplikace — upozornění na ceny (etapa 6, R13) a stránka bez
 * připojení (R37).
 *
 * Bez cache dat: staré ceny by klamaly (R27). Uloží si jen stránku „Jsi offline“
 * a její styly — bez připojení ji ukáže místo chyby prohlížeče. Server ho vydává
 * na /sw.js (ServiceWorkerController) a před skript doplní `self.SW_CONFIG`
 * (verze, soubory k uložení, ikona upozornění); Vite ho nezpracovává — žádné
 * importy ani texty, ty posílá server. Vzor z Počasí a Agregátoru letáků.
 *
 * @author Roman Hlaváček
 * @created 2026-10-12
 */

const CONFIG = self.SW_CONFIG;

/** Cache stránky bez připojení — jedna na verzi, starší se při aktivaci smažou. */
const CACHE_PREFIX = 'game-watcher-offline-';
const CACHE_NAME = `${CACHE_PREFIX}${CONFIG.version}`;

/** Soubory buildu mají otisk ve jméně — z cache je jde vzít bez ověření. */
const BUILD_PREFIX = '/build/';

self.addEventListener('install', (event) => {
    // Chybějící soubor nesmí zablokovat instalaci (upozornění fungují i bez stránky offline)
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) => Promise.allSettled(CONFIG.precache.map((url) => cache.add(url))))
            .then(() => self.skipWaiting()),
    );
});

// Nová verze převezme stránky hned a staré uložené soubory smaže
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Stránka vždy ze sítě; bez připojení „Jsi offline“
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(CONFIG.offlineUrl)));

        return;
    }

    // Styly stránky offline: uložené z cache, ostatní soubory buildu normálně ze sítě
    if (url.pathname.startsWith(BUILD_PREFIX)) {
        event.respondWith(caches.match(request).then((cached) => cached ?? fetch(request)));
    }
});

// Zpráva ze serveru: { title, body, url, tag, badge } (AlertPresenter, SendPriceAlerts)
self.addEventListener('push', (event) => {
    const message = event.data ? event.data.json() : {};
    const shown = self.registration.showNotification(message.title, {
        body: message.body,
        icon: CONFIG.notificationIcon,
        tag: message.tag,
        // Nová zpráva téže hry nahradí starou, ale znovu upozorní
        renotify: Boolean(message.tag),
        lang: 'cs',
        data: { url: message.url },
    });
    // Počet nepřečtených upozornění na ikoně aplikace (R37, R53) — jen kde to systém umí;
    // aplikace ho po přečtení sníží (lib/pwa.js)
    const badge = message.badge && self.navigator.setAppBadge ? self.navigator.setAppBadge(message.badge).catch(() => undefined) : Promise.resolve();

    event.waitUntil(Promise.all([shown, badge]));
});

// Klepnutí na upozornění: otevřená aplikace přejde na detail sama (Inertia), jinak nové okno
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url ?? '/', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const client = windows.find((window) => new URL(window.url).origin === self.location.origin);
            if (!client) {
                return self.clients.openWindow(url);
            }
            client.postMessage({ type: 'navigate', url });

            return client.focus();
        }),
    );
});
