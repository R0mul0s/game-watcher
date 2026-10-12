<!--
    Pokročilé filtry přehledu sledovaných her (R21) — hledání, platforma,
    hráči, žánr (R25), druh, cena, sleva, PS Plus a řazení. Stav je v adrese stránky;
    filtruje server, komponenta jen posílá volby (s krátkou prodlevou při psaní).

    @author Roman Hlaváček
    @created 2026-10-12
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

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
const SORTS = ['added', 'title', 'price', 'cut', 'release'];
/** Filtr hráčů (enum PlayerFilter). */
const PLAYERS = ['solo', 'duo', 'couch'];

const form = reactive({
    q: props.filters.q,
    platform: props.filters.platform,
    genres: props.filters.genres ?? [],
    players: props.filters.players,
    type: props.filters.type,
    maxPrice: props.filters.maxPrice,
    minCut: props.filters.minCut,
    deals: props.filters.deals,
    plus: props.filters.plus,
    sort: props.filters.sort,
});

/** Na mobilu jsou pokročilé volby sbalené, dokud je uživatel neotevře nebo nejsou zapnuté. */
const open = ref(props.filters.active);
let timer = null;

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
    if (form.type) result.type = form.type;
    if (form.maxPrice !== null && form.maxPrice !== '') result.max_price = form.maxPrice;
    if (form.minCut) result.min_cut = form.minCut;
    if (form.deals) result.deals = 1;
    if (form.plus) result.plus = 1;
    if (form.sort !== 'added') result.sort = form.sort;

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
 * Vrátí všechny filtry na výchozí hodnoty (řazení zůstává).
 */
function reset() {
    Object.assign(form, { q: '', platform: null, genres: [], players: null, type: null, maxPrice: null, minCut: null, deals: false, plus: false });
}
</script>

<template>
    <section class="panel filters" :class="{ 'filters--open': open }" :aria-label="t('filters.title')">
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
            <button type="button" class="button button--ghost button--small filters__toggle" :aria-expanded="open" aria-controls="filters-advanced" @click="open = !open">
                {{ t('filters.toggle') }}
            </button>
        </div>

        <div id="filters-advanced" class="filters__advanced">
            <fieldset class="filters__group">
                <legend class="filters__label">{{ t('filters.platform') }}</legend>
                <div class="segmented">
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
                <div class="segmented">
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
                <legend class="filters__label">{{ t('filters.type') }}</legend>
                <div class="segmented">
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

            <div class="filters__group">
                <label class="filters__label" for="filter-max-price">{{ t('filters.max_price') }}</label>
                <div class="filters__price">
                    <input id="filter-max-price" v-model.number="form.maxPrice" class="field__input filters__number" type="number" min="0" step="1" inputmode="numeric" />
                    <span class="filters__unit">{{ page.props.currency?.current }}</span>
                </div>
            </div>

            <fieldset class="filters__group">
                <legend class="filters__label">{{ t('filters.min_cut') }}</legend>
                <div class="segmented">
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

        <div class="filters__footer">
            <span class="filters__count">{{ t('filters.shown', { shown, total }) }}</span>
            <button v-if="filters.active" type="button" class="button button--ghost button--small" @click="reset">{{ t('filters.reset') }}</button>
        </div>
    </section>
</template>
