/**
 * Aplikace na ploše (PWA, R27, R37): registrace service workeru při startu
 * (stránka offline, upozornění), přechod po klepnutí na upozornění v otevřené
 * aplikaci, číslo na ikoně aplikace a nové načtení dat po návratu do aplikace.
 * Vzor z Agregátoru letáků (R66 tam).
 *
 * Aplikace z plochy nemá lištu prohlížeče ani tlačítko obnovit a v telefonu
 * běží klidně dny — ceny se mění s každým během cronu, proto se data po
 * návratu do aplikace načtou znovu, když jsou starší.
 *
 * @author Roman Hlaváček
 * @created 2026-10-13
 */
import { SERVICE_WORKER_URL } from '@/lib/push';
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

/** Milisekund v minutě. */
const MINUTE_MS = 60 * 1000;

/** Stav aplikace pro komponenty — běží z plochy (ne v prohlížeči)? */
export const pwaState = reactive({
    standalone: false,
});

/** Za kolik minut jsou data po návratu do aplikace stará (sdílená vlastnost pwa). */
let refreshAfterMinutes = 0;

/** Kdy stránka naposledy dostala data ze serveru. */
let lastLoadedAt = Date.now();

/** Je někdo přihlášený — číslo na ikoně a nové načtení jen pro přihlášeného. */
let signedIn = false;

/**
 * Běží aplikace z plochy? (Android a počítač display-mode, iPhone navigator.standalone.)
 *
 * @returns {boolean}
 */
export function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
}

/**
 * Smaže číslo na ikoně aplikace — uživatel aplikaci otevřel, slevy uvidí.
 */
function clearAppBadge() {
    navigator.clearAppBadge?.().catch(() => undefined);
}

/**
 * Po návratu do aplikace smaže číslo na ikoně a načte data znovu, když jsou starší než nastavení.
 */
function onResume() {
    if (document.visibilityState !== 'visible' || !signedIn) {
        return;
    }

    clearAppBadge();
    if (Date.now() - lastLoadedAt > refreshAfterMinutes * MINUTE_MS && navigator.onLine) {
        router.reload();
    }
}

/**
 * Napojí aplikaci na plochu — volá se jednou při startu (resources/js/app.js).
 *
 * @param {object} initialPage Stránka Inertie (props.pwa, props.auth)
 */
export function initPwa(initialPage) {
    pwaState.standalone = isStandalone();
    refreshAfterMinutes = initialPage.props.pwa?.refreshAfterMinutes ?? 0;
    signedIn = Boolean(initialPage.props.auth);

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(SERVICE_WORKER_URL).catch(() => undefined);
        // Klepnutí na upozornění v otevřené aplikaci: přechod bez nového načtení
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data?.type === 'navigate') {
                router.visit(event.data.url);
            }
        });
    }

    if (signedIn) {
        clearAppBadge();
    }

    router.on('success', (event) => {
        lastLoadedAt = Date.now();
        signedIn = Boolean(event.detail.page.props.auth);
    });
    document.addEventListener('visibilitychange', onResume);
}
