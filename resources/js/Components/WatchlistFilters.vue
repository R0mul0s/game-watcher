<!--
    Pokročilé filtry přehledu sledovaných her (R21) — hledání, platforma,
    hráči, žánr (R25), čeština (R47), druh, vydání (R41), zdroj ceny (R30), cena, sleva, PS Plus a řazení. Na telefonu
    jsou volby v okně, které vyjede zespodu (FilterSheet, R45). Stav je v adrese stránky;
    filtruje server, komponenta jen posílá volby (s krátkou prodlevou při psaní).

    @author Roman Hlaváček
    @created 2026-10-12
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/vue3';
import FilterSheet from '@/Components/FilterSheet.vue';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    filters: { type: Object, required: true },
    genres: { type: Array, default: () => [] },
    shown: { type: Number, required: true },
    total: { type: Number, required: true },
    url: { type: String, required: true },
});

const t = useTranslations();
const page = usePage();

/** Prodleva po poslední změně, než se filtr odešle (psaní do hledání a ceny). */
const DEBOUNCE_MS = 300;
/** Nabídka „sleva aspoň“ v procentech. */
const CUT_OPTIONS = [25, 50, 75];
const PLATFORMS = ['pc', 'playstation'];
const TYPES = ['game', 'dlc'];
/** Vydání (enum ReleaseFilter, R41). */
const RELEASES = ['released', 'upcoming'];
/** Řazení (enum WatchlistSort); první je výchozí — od nejlevnější (R32). */
const SORTS = ['price', 'per_hour', 'score', 'cut', 'release', 'title', 'added'];
const DEFAULT_SORT = SORTS[0];
/** Filtr hráčů (enum PlayerFilter). */
const PLAYERS = ['solo', 'duo', 'couch'];
/** Filtr češtiny (enum CzechSupport, R47) — aspoň titulky, nebo dabing. */
const CZECH = ['text', 'voice'];
/** Zdroj ceny pro řazení a „cena do“ (enum PriceBasis, R30); první je výchozí. */
const PRICE_BASES = ['official', 'cheapest', 'keyshops'];
const DEFAULT_PRICE_BASIS = PRICE_BASES[0];

const form = reactive({
    q: props.filters.q,
    platform: props.filters.platform,
    genres: props.filters.genres ?? [],
    players: props.filters.players,
    czech: props.filters.czech,
    type: props.filters.type,
    release: props.filters.release,
    maxPrice: props.filters.maxPrice,
    minCut: props.filters.minCut,
    deals: props.filters.deals,
    plus: props.filters.plus,
    price: props.filters.price,
    sort: props.filters.sort,
});

/** Okno s volbami na telefonu (R45). */
const sheet = ref(null);
let timer = null;

/** Kolik filtrů je zapnutých — číslo na plovoucím tlačítku (řazení a zdroj ceny se nepočítají). */
const activeCount = computed(
    () =>
        [form.q.trim(), form.platform, form.genres.length, form.players, form.czech, form.type, form.release, form.maxPrice, form.minCut, form.deals, form.plus].filter(
            (value) => value !== null && value !== '' && value !== false && value !== 0,
        ).length,
);

/**
 * Parametry adresy — výchozí hodnoty se vynechají, adresa zůstane krátká.
 *
 * @returns {Record<string, string|number|string[]>}
 */
function params() {
    const result = {};
    if (form.q.trim() !== '') result.q = form.q.trim();
    if (form.platform) result.platform = form.platform;
    if (form.genres.length) result.genres = form.genres;
    if (form.players) result.players = form.players;
    if (form.czech) result.czech = form.czech;
    if (form.type) result.type = form.type;
    if (form.release) result.release = form.release;
    if (form.maxPrice !== null && form.maxPrice !== '') result.max_price = form.maxPrice;
    if (form.minCut) result.min_cut = form.minCut;
    if (form.deals) result.deals = 1;
    if (form.plus) result.plus = 1;
    if (form.price !== DEFAULT_PRICE_BASIS) result.price = form.price;
    if (form.sort !== DEFAULT_SORT) result.sort = form.sort;

    return result;
}

/**
 * Pošle filtry serveru — načtou se jen data přehledu, stránka zůstane na místě.
 */
function apply() {
    clearTimeout(timer);
    router.get(props.url, params(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['games', 'total', 'genres', 'filters'],
    });
}

watch(form, () => {
    clearTimeout(timer);
    timer = setTimeout(apply, DEBOUNCE_MS);
});

/**
 * Přepne žánr ve výběru; prázdný výběr = všechny žánry.
 *
 * @param {string} name
 */
function toggleGenre(name) {
    form.genres = form.genres.includes(name) ? form.genres.filter((genre) => genre !== name) : [...form.genres, name];
}

/**
 * Vrátí všechny filtry na výchozí hodnoty (řazení a zdroj ceny zůstávají).
 */
function reset() {
    Object.assign(form, { q: '', platform: null, genres: [], players: null, czech: null, type: null, release: null, maxPrice: null, minCut: null, deals: false, plus: false });
}
</script>

<template>
    <section class="panel filters" :aria-label="t('filters.title')">
        <div class="filters__bar">
            <div class="field filters__search">
                <label class="visually-hidden" for="filter-q">{{ t('filters.search') }}</label>
                <input
                    id="filter-q"
                    v-model="form.q"
                    class="field__input"
                    type="search"
                    :placeholder="t('filters.search_placeholder')"
                    autocomplete="off"
                />
            </div>
            <div class="field filters__sort">
                <label class="visually-hidden" for="filter-sort">{{ t('filters.sort') }}</label>
                <select id="filter-sort" v-model="form.sort" class="field__input field__select">
                    <option v-for="sort in SORTS" :key="sort" :value="sort">{{ t(`filters.sort_${sort}`) }}</option>
                </select>
            </div>
            <!-- Na telefonu otevře okno s volbami (R45) -->
            <button type="button" class="button button--ghost button--small filters__toggle" aria-haspopup="dialog" @click="sheet.open()">
                {{ t('filters.toggle') }}
                <span v-if="activeCount" class="filter-sheet__count">{{ activeCount }}</span>
            </button>
        </div>

        <FilterSheet
            ref="sheet"
            :title="t('filters.title')"
            :active-count="activeCount"
            :result-label="t('filter_sheet.show', { count: shown })"
            :can-reset="filters.active"
            @reset="reset"
        >
            <div class="filters__advanced">
                <fieldset class="filters__group">
                    <legend class="filters__label">{{ t('filters.platform') }}</legend>
                    <div class="segmented segmented--fill">
                        <button type="button" class="segmented__option" :class="{ 'segmented__option--active': !form.platform }" :aria-pressed="!form.platform" @click="form.platform = null">
                            {{ t('filters.all') }}
                        </button>
                        <button
                            v-for="platform in PLATFORMS"
                            :key="platform"
                            type="button"
                            class="segmented__option"
                            :class="{ 'segmented__option--active': form.platform === platform }"
                            :aria-pressed="form.platform === platform"
                            @click="form.platform = platform"
                        >
                            {{ t(`prices.platforms.${platform}`) }}
                        </button>
                    </div>
                </fieldset>

                <fieldset class="filters__group">
                    <legend class="filters__label" :title="t('filters.players_hint')">{{ t('filters.players') }}</legend>
                    <div class="segmented segmented--fill">
                        <button type="button" class="segmented__option" :class="{ 'segmented__option--active': !form.players }" :aria-pressed="!form.players" @click="form.players = null">
                            {{ t('filters.all') }}
                        </button>
                        <button
                            v-for="players in PLAYERS"
                            :key="players"
                            type="button"
                            class="segmented__option"
                            :class="{ 'segmented__option--active': form.players === players }"
                            :aria-pressed="form.players === players"
                            :title="t(`filters.players_titles.${players}`)"
                            :aria-label="t(`filters.players_titles.${players}`)"
                            @click="form.players = players"
                        >
                            {{ t(`filters.players_options.${players}`) }}
                        </button>
                    </div>
                </fieldset>

                <fieldset class="filters__group">
                    <legend class="filters__label" :title="t('filters.czech_hint')">{{ t('filters.czech') }}</legend>
                    <div class="segmented segmented--fill">
                        <button type="button" class="segmented__option" :class="{ 'segmented__option--active': !form.czech }" :aria-pressed="!form.czech" @click="form.czech = null">
                            {{ t('filters.all') }}
                        </button>
                        <button
                            v-for="czech in CZECH"
                            :key="czech"
                            type="button"
                            class="segmented__option"
                            :class="{ 'segmented__option--active': form.czech === czech }"
                            :aria-pressed="form.czech === czech"
                            :title="t(`filters.czech_titles.${czech}`)"
                            :aria-label="t(`filters.czech_titles.${czech}`)"
                            @click="form.czech = czech"
                        >
                            {{ t(`filters.czech_options.${czech}`) }}
                        </button>
                    </div>
                </fieldset>

                <fieldset class="filters__group">
                    <legend class="filters__label">{{ t('filters.type') }}</legend>
                    <div class="segmented segmented--fill">
                        <button type="button" class="segmented__option" :class="{ 'segmented__option--active': !form.type }" :aria-pressed="!form.type" @click="form.type = null">
                            {{ t('filters.all') }}
                        </button>
                        <button
                            v-for="type in TYPES"
                            :key="type"
                            type="button"
                            class="segmented__option"
                            :class="{ 'segmented__option--active': form.type === type }"
                            :aria-pressed="form.type === type"
                            @click="form.type = type"
                        >
                            {{ t(`filters.type_${type}`) }}
                        </button>
                    </div>
                </fieldset>

                <fieldset class="filters__group">
                    <legend class="filters__label" :title="t('filters.release_hint')">{{ t('filters.release') }}</legend>
                    <div class="segmented segmented--fill">
                        <button type="button" class="segmented__option" :class="{ 'segmented__option--active': !form.release }" :aria-pressed="!form.release" @click="form.release = null">
                            {{ t('filters.all') }}
                        </button>
                        <button
                            v-for="release in RELEASES"
                            :key="release"
                            type="button"
                            class="segmented__option"
                            :class="{ 'segmented__option--active': form.release === release }"
                            :aria-pressed="form.release === release"
                            @click="form.release = release"
                        >
                            {{ t(`filters.release_options.${release}`) }}
                        </button>
                    </div>
                </fieldset>

                <fieldset class="filters__group">
                    <legend class="filters__label" :title="t('filters.price_basis_hint')">{{ t('filters.price_basis') }}</legend>
                    <div class="segmented segmented--fill">
                        <button
                            v-for="basis in PRICE_BASES"
                            :key="basis"
                            type="button"
                            class="segmented__option"
                            :class="{ 'segmented__option--active': form.price === basis }"
                            :aria-pressed="form.price === basis"
                            :title="t(`filters.price_basis_titles.${basis}`)"
                            :aria-label="t(`filters.price_basis_titles.${basis}`)"
                            @click="form.price = basis"
                        >
                            {{ t(`filters.price_basis_options.${basis}`) }}
                        </button>
                    </div>
                </fieldset>

                <div class="filters__group">
                    <label class="filters__label" for="filter-max-price">{{ t('filters.max_price') }}</label>
                    <div class="filters__price">
                        <input id="filter-max-price" v-model.number="form.maxPrice" class="field__input filters__number" type="number" min="0" step="1" inputmode="numeric" />
                        <span class="filters__unit">{{ page.props.currency?.current }}</span>
                    </div>
                </div>

                <fieldset class="filters__group">
                    <legend class="filters__label">{{ t('filters.min_cut') }}</legend>
                    <div class="segmented segmented--fill">
                        <button type="button" class="segmented__option" :class="{ 'segmented__option--active': !form.minCut }" :aria-pressed="!form.minCut" @click="form.minCut = null">
                            {{ t('filters.all') }}
                        </button>
                        <button
                            v-for="cut in CUT_OPTIONS"
                            :key="cut"
                            type="button"
                            class="segmented__option"
                            :class="{ 'segmented__option--active': form.minCut === cut }"
                            :aria-pressed="form.minCut === cut"
                            @click="form.minCut = cut"
                        >
                            {{ cut }} %
                        </button>
                    </div>
                </fieldset>

                <div class="filters__group filters__switches">
                    <label class="switch">
                        <input v-model="form.deals" class="switch__input" type="checkbox" />
                        <span class="switch__track" aria-hidden="true"></span>
                        <span class="switch__label">{{ t('filters.deals') }}</span>
                    </label>
                    <label class="switch" :title="t('filters.plus_hint')">
                        <input v-model="form.plus" class="switch__input" type="checkbox" />
                        <span class="switch__track" aria-hidden="true"></span>
                        <span class="switch__label">{{ t('filters.plus') }}</span>
                    </label>
                </div>

                <fieldset v-if="genres.length" class="filters__group filters__group--wide">
                    <legend class="filters__label">{{ t('filters.genres') }} <span class="filters__hint">{{ t('filters.genres_hint') }}</span></legend>
                    <div class="chips">
                        <button
                            v-for="genre in genres"
                            :key="genre.name"
                            type="button"
                            class="chip"
                            :class="{ 'chip--active': form.genres.includes(genre.name) }"
                            :aria-pressed="form.genres.includes(genre.name)"
                            @click="toggleGenre(genre.name)"
                        >
                            {{ genre.name }}
                            <span class="chip__count">{{ genre.count }}</span>
                        </button>
                    </div>
                </fieldset>
            </div>
        </FilterSheet>

        <div class="filters__footer">
            <span class="filters__count">{{ t('filters.shown', { shown, total }) }}</span>
            <button v-if="filters.active" type="button" class="button button--ghost button--small" @click="reset">{{ t('filters.reset') }}</button>
        </div>
    </section>
</template>
