/**
 * Upozornění na zařízení (Web Push, etapa 6, R13) — odběr v prohlížeči
 * a jeho uložení u přihlášeného účtu. Vzor z Počasí (tam jsou odběry anonymní
 * a s nastavením po stanicích, tady patří účtu).
 *
 * @author Roman Hlaváček
 * @created 2026-10-12
 */

/** Adresa service workeru (ServiceWorkerController) — v kořeni, rozsah celý web. */
export const SERVICE_WORKER_URL = '/sw.js';
/** Cookie s CSRF tokenem, kterou Laravel posílá ke každé stránce. */
const XSRF_COOKIE = 'XSRF-TOKEN';
/** Kódování obsahu zprávy, když ho prohlížeč nehlásí (starší Safari). */
const DEFAULT_CONTENT_ENCODING = 'aes128gcm';
/** Délka bloku base64 — doplnění znaky „=“. */
const BASE64_BLOCK = 4;

/**
 * Jestli prohlížeč upozornění umí (na iPhonu jen aplikace přidaná na plochu).
 *
 * @returns {boolean}
 */
export function isPushSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

/**
 * Jestli jde o iPhone nebo iPad mimo aplikaci na ploše — tam upozornění nejsou.
 *
 * @returns {boolean}
 */
export function needsHomeScreen() {
    const ios = /iPhone|iPad|iPod/.test(navigator.userAgent);
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

    return ios && !standalone;
}

/**
 * Stávající odběr zařízení, bez něj null.
 *
 * @returns {Promise<PushSubscription|null>}
 */
export async function currentSubscription() {
    const registration = await navigator.serviceWorker.getRegistration(SERVICE_WORKER_URL);

    return registration ? registration.pushManager.getSubscription() : null;
}

/**
 * Požádá o povolení, zaregistruje service worker, přihlásí odběr a uloží ho
 * u účtu. Musí se volat z akce uživatele (klepnutí) — jinak prohlížeč
 * povolení odmítne.
 *
 * @param {string} publicKey Veřejný klíč VAPID (base64url)
 * @param {string} url Adresa routy push.store
 * @returns {Promise<PushSubscription|null>} null = uživatel upozornění nepovolil
 */
export async function subscribe(publicKey, url) {
    if ((await Notification.requestPermission()) !== 'granted') {
        return null;
    }

    await navigator.serviceWorker.register(SERVICE_WORKER_URL);
    const registration = await navigator.serviceWorker.ready;
    const subscription =
        (await registration.pushManager.getSubscription()) ??
        (await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: base64UrlToBytes(publicKey) }));

    const { endpoint, keys } = subscription.toJSON();
    const encodings = window.PushManager.supportedContentEncodings ?? [DEFAULT_CONTENT_ENCODING];
    await request('POST', url, {
        endpoint,
        keys,
        content_encoding: encodings.includes(DEFAULT_CONTENT_ENCODING) ? DEFAULT_CONTENT_ENCODING : encodings[0],
    });

    return subscription;
}

/**
 * Zruší odběr v prohlížeči i u účtu.
 *
 * @param {PushSubscription} subscription
 * @param {string} url Adresa routy push.destroy
 */
export async function unsubscribe(subscription, url) {
    await request('DELETE', url, { endpoint: subscription.endpoint });
    await subscription.unsubscribe();
}

/**
 * Požádá server o zkušební upozornění na toto zařízení.
 *
 * @param {string} url Adresa routy push.test
 * @param {PushSubscription} subscription
 * @returns {Promise<'sent'|'expired'|'failed'>}
 */
export async function sendTest(url, subscription) {
    const response = await send('POST', url, { endpoint: subscription.endpoint });
    if (!response.ok && response.status !== 404) {
        return 'failed';
    }

    return (await response.json()).result;
}

/**
 * JSON požadavek na server; chybový stav vyhodí výjimku.
 *
 * @param {string} method
 * @param {string} url
 * @param {object} body
 * @returns {Promise<object>}
 */
async function request(method, url, body) {
    const response = await send(method, url, body);
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
}

/**
 * Odešle JSON na server s CSRF tokenem z cookie a vrátí odpověď.
 *
 * @param {string} method
 * @param {string} url
 * @param {object} body
 * @returns {Promise<Response>}
 */
function send(method, url, body) {
    return fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });
}

/**
 * CSRF token z cookie XSRF-TOKEN (Laravel ho posílá zašifrovaný a URL-kódovaný).
 *
 * @returns {string}
 */
function xsrfToken() {
    const cookie = document.cookie.split('; ').find((item) => item.startsWith(`${XSRF_COOKIE}=`));

    return cookie ? decodeURIComponent(cookie.slice(XSRF_COOKIE.length + 1)) : '';
}

/**
 * Převede klíč z base64url na bajty pro PushManager.subscribe().
 *
 * @param {string} value
 * @returns {Uint8Array}
 */
function base64UrlToBytes(value) {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(value.length / BASE64_BLOCK) * BASE64_BLOCK, '=');

    return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
}
