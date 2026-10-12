/**
 * Service worker aplikace — jen upozornění na ceny (etapa 6, R13).
 *
 * Bez cache a offline režimu: staré ceny by klamaly. Server ho vydává na
 * /sw.js (ServiceWorkerController), Vite ho nezpracovává — žádné importy ani
 * texty, ty posílá server ve zprávě. Vzor z Počasí.
 *
 * @author Roman Hlaváček
 * @created 2026-10-12
 */

/** Ikona upozornění — ikona aplikace. */
const NOTIFICATION_ICON = '/icon-192.png';

// Nová verze service workeru převezme stránky hned, ne až po zavření všech oken
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// Zpráva ze serveru: { title, body, url, tag } (AlertPresenter)
self.addEventListener('push', (event) => {
    const message = event.data ? event.data.json() : {};

    event.waitUntil(
        self.registration.showNotification(message.title, {
            body: message.body,
            icon: NOTIFICATION_ICON,
            tag: message.tag,
            // Nová zpráva téže hry nahradí starou, ale znovu upozorní
            renotify: Boolean(message.tag),
            data: { url: message.url },
        }),
    );
});

// Klepnutí na upozornění otevře detail hry — v okně aplikace, pokud už běží
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url ?? '/', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const open = windows.find((client) => 'focus' in client);
            if (open) {
                return open.navigate(url).then((client) => (client ?? open).focus());
            }

            return self.clients.openWindow(url);
        }),
    );
});
