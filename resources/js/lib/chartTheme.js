/**
 * Barvy grafů z CSS tokenů (resources/scss/base/_tokens.scss).
 *
 * ECharts kreslí do SVG a barvy potřebuje jako hodnoty, ne CSS proměnné —
 * proto se tokeny přečtou z :root. Vzhled je jen tmavý (R6), takže stačí
 * jednou. Vzor z Počasí.
 *
 * @author Roman Hlaváček
 * @created 2026-10-12
 */

const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

/** Tokeny, které grafy používají: klíč v JS => CSS proměnná. */
const TOKENS = {
    text: '--color-text',
    muted: '--color-text-muted',
    grid: '--chart-grid',
    surface: '--color-surface',
    official: '--chart-official',
    steam: '--chart-steam',
    keyshops: '--chart-keyshops',
    playstation: '--chart-playstation',
    target: '--chart-target',
};

/**
 * Jestli grafy animovat — ne, když si uživatel v systému vyžádal omezený pohyb.
 *
 * @returns {boolean}
 */
export function chartAnimation() {
    return !window.matchMedia(REDUCED_MOTION_QUERY).matches;
}

/**
 * Aktuální hodnoty barev grafu.
 *
 * @returns {Record<string, string>}
 */
export function chartColors() {
    const style = getComputedStyle(document.documentElement);

    return Object.fromEntries(Object.entries(TOKENS).map(([key, name]) => [key, style.getPropertyValue(name).trim()]));
}
