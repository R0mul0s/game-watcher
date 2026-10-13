/**
 * Media queries sdílené komponentami — telefon a omezený pohyb.
 *
 * Šířka telefonu odpovídá $breakpoint-md v resources/scss/abstracts/_variables.scss
 * (styly i skripty se přepínají na stejné hranici).
 *
 * @author Roman Hlaváček
 * @created 2026-10-13
 */
import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Telefon — pod $breakpoint-md. */
export const PHONE_QUERY = '(max-width: 767px)';

/** Uživatel si v systému vyžádal omezený pohyb (R6). */
export const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

/**
 * Platí teď media query? Pro jednorázové rozhodnutí (posun stránky, animace grafu).
 *
 * @param {string} query
 * @returns {boolean}
 */
export function matchesMedia(query) {
    return window.matchMedia(query).matches;
}

/**
 * Reaktivní media query — hodnota se změní s velikostí okna nebo nastavením systému.
 *
 * @param {string} query
 * @returns {import('vue').Ref<boolean>}
 */
export function useMediaQuery(query) {
    const matches = ref(false);
    let list = null;

    /** Uloží aktuální stav. */
    const update = () => {
        matches.value = list.matches;
    };

    onMounted(() => {
        list = window.matchMedia(query);
        update();
        list.addEventListener('change', update);
    });
    onBeforeUnmount(() => list?.removeEventListener('change', update));

    return matches;
}
