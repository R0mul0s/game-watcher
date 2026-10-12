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
 * Měsíc a rok pro osu grafu („říj 2026") — den Y-m-d jako UTC jako formatDate.
 *
 * @param {string} value Y-m-d
 * @param {string} locale
 * @returns {string}
 */
export function formatMonth(value, locale) {
    return cachedFormatter(Intl.DateTimeFormat, locale, { month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${value}T00:00:00Z`));
}

/**
 * Den a měsíc pro osu grafu krátkého období („5. 9.") — den Y-m-d jako UTC.
 *
 * @param {string} value Y-m-d
 * @param {string} locale
 * @returns {string}
 */
export function formatDayMonth(value, locale) {
    return cachedFormatter(Intl.DateTimeFormat, locale, { day: 'numeric', month: 'numeric', timeZone: 'UTC' }).format(new Date(`${value}T00:00:00Z`));
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
 * Desetinná místa ceny podle měny — koruny bez haléřů (ceny her se v nich
 * neuvádějí), ostatní měny na centy.
 */
const PRICE_FRACTION_DIGITS = { CZK: 0 };
const DEFAULT_PRICE_FRACTION_DIGITS = 2;

/**
 * Cena s měnou („608 Kč", „24,96 €"); chybějící cenu jako pomlčku.
 *
 * @param {{amount: number, currency: string}|null} price
 * @param {string} locale
 * @returns {string}
 */
export function formatPrice(price, locale) {
    if (!price) {
        return EMPTY_VALUE;
    }

    const digits = PRICE_FRACTION_DIGITS[price.currency] ?? DEFAULT_PRICE_FRACTION_DIGITS;

    return cachedFormatter(Intl.NumberFormat, locale, {
        style: 'currency',
        currency: price.currency,
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(price.amount);
}

/**
 * Datum a čas z ISO 8601 v zadané zóně („11. 10. 2026 18:30").
 *
 * @param {string|null} iso
 * @param {string} locale
 * @param {string} timeZone
 * @returns {string}
 */
export function formatDateTime(iso, locale, timeZone) {
    if (!iso) {
        return EMPTY_VALUE;
    }

    return cachedFormatter(Intl.DateTimeFormat, locale, { dateStyle: 'short', timeStyle: 'short', timeZone }).format(new Date(iso));
}

/**
 * Datum z ISO 8601 v zadané zóně („31. 10. 2026") — konec slevy.
 *
 * @param {string|null} iso
 * @param {string} locale
 * @param {string} timeZone
 * @returns {string}
 */
export function formatDateInZone(iso, locale, timeZone) {
    if (!iso) {
        return EMPTY_VALUE;
    }

    return cachedFormatter(Intl.DateTimeFormat, locale, { day: 'numeric', month: 'numeric', year: 'numeric', timeZone }).format(new Date(iso));
}

/** Desetinná místa hodnocení hvězdičkami („4,6" na kartě, „4,55" na detailu). */
export const RATING_DIGITS_SHORT = 1;
export const RATING_DIGITS_FULL = 2;

/**
 * Hodnocení hvězdičkami („4,6"); chybějící jako pomlčku.
 *
 * @param {number|null} value
 * @param {string} locale
 * @param {number} digits desetinná místa
 * @returns {string}
 */
export function formatRating(value, locale, digits = RATING_DIGITS_SHORT) {
    if (value === null || value === undefined) {
        return EMPTY_VALUE;
    }

    return cachedFormatter(Intl.NumberFormat, locale, { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(value);
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
