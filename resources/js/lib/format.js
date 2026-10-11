/**
 * Formátování hodnot pro zobrazení — vždy přes Intl, nikdy ruční skládání.
 *
 * @author Roman Hlaváček
 * @created 2026-10-11
 */

/** Zobrazení chybějící hodnoty. */
export const EMPTY_VALUE = '—';

/** Vytvořené formattery Intl — konstrukce je drahá a seznamy ji volají opakovaně. */
const formatters = new Map();

/**
 * Formatter Intl pro jazyk a volby, vytvořený jen jednou.
 *
 * @template T
 * @param {new (locale: string, options: object) => T} Formatter Intl.NumberFormat nebo Intl.DateTimeFormat
 * @param {string} locale
 * @param {object} options
 * @returns {T}
 */
function cachedFormatter(Formatter, locale, options) {
    const key = `${Formatter.name}|${locale}|${JSON.stringify(options)}`;
    if (!formatters.has(key)) {
        formatters.set(key, new Formatter(locale, options));
    }

    return formatters.get(key);
}

/**
 * Datum bez času („24. 9. 2026"); chybějící hodnotu jako pomlčku.
 *
 * Datum ze serveru je kalendářní den (Y-m-d) bez zóny — čte se i formátuje
 * jako UTC, aby ho prohlížeč neposunul do vlastní zóny.
 *
 * @param {string|null} value Y-m-d
 * @param {string} locale
 * @returns {string}
 */
export function formatDate(value, locale) {
    if (!value) {
        return EMPTY_VALUE;
    }

    return cachedFormatter(Intl.DateTimeFormat, locale, { day: 'numeric', month: 'numeric', year: 'numeric', timeZone: 'UTC' }).format(
        new Date(`${value}T00:00:00Z`),
    );
}

/**
 * Celé číslo s oddělením tisíců („8 194").
 *
 * @param {number|null} value
 * @param {string} locale
 * @returns {string}
 */
export function formatInteger(value, locale) {
    if (value === null || value === undefined) {
        return EMPTY_VALUE;
    }

    return cachedFormatter(Intl.NumberFormat, locale, { maximumFractionDigits: 0 }).format(value);
}
