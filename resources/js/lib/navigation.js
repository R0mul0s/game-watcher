/**
 * Předchozí stránka v aplikaci — pro tlačítko Zpět na detailu hry (R38).
 *
 * Zpět jde přes historii prohlížeče: filtry přehledu jsou v adrese (R21)
 * a Inertia při návratu obnoví i pozici posunu stránky, takže se uživatel
 * vrátí na stejně vyfiltrovaný přehled na stejné místo. Historie ale patří
 * aplikaci, jen když na stránku přišel z ní — detail otevřený z upozornění,
 * odkazu nebo záložky předchozí stránku v aplikaci nemá a Zpět vede na přehled.
 *
 * @author Roman Hlaváček
 * @created 2026-10-13
 */
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

/** Adresa (cesta s dotazem) stránky, ze které uživatel na tu současnou přišel; null = z jiného webu. */
export const navigationState = reactive({
    previousUrl: null,
});

/** Adresa současné stránky. */
let currentUrl = null;

/**
 * Začne sledovat přechody Inertie — volá se jednou při startu (resources/js/app.js).
 * Návrat na stejnou adresu (akce na stránce, Inertia ho nahrazuje v historii)
 * předchozí stránku nemění.
 *
 * @param {object} initialPage Stránka Inertie při načtení
 */
export function trackNavigation(initialPage) {
    currentUrl = initialPage.url;
    router.on('navigate', (event) => {
        const nextUrl = event.detail.page.url;
        if (nextUrl !== currentUrl) {
            navigationState.previousUrl = currentUrl;
            currentUrl = nextUrl;
        }
    });
}

/**
 * Cesta adresy bez dotazu („/?sort=price“ → „/“).
 *
 * @param {string|null} url
 * @returns {string|null}
 */
export function pathOf(url) {
    return url === null ? null : new URL(url, window.location.origin).pathname;
}
