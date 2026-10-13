<!--
    Objevit → Nadcházející (R46) — hry z IGDB, které vyjdou do roka na PC
    nebo PS5 a uživatel je ještě nesleduje: platforma, žánry, jen co-op a řazení.
    Stav je v adrese, filtruje server nad vlastní tabulkou; hru jde rovnou
    začít sledovat (upozornění na vydání a první slevu, R41). Na telefonu jsou
    žánry v okně, které vyjede zespodu (FilterSheet, R45).

    @author Roman Hlaváček
    @created 2026-10-13
-->
<script setup>
import DiscoverTabs from '@/Components/DiscoverTabs.vue';
import FilterSheet from '@/Components/FilterSheet.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateTime, formatReleaseDate } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    releases: { type: Array, required: true },
    genres: { type: Array, default: () => [] },
    updatedAt: { type: String, default: null },
    filters: { type: Object, required: true },
    upcomingUrl: { type: String, required: true },
    discoverUrl: { type: String, required: true },
    igdbUrl: { type: String, required: true },
});

const t = useTranslations();
const page = usePage();

/** Řazení (enum UpcomingSort); první je výchozí. */
const SORTS = ['date', 'popular'];
const DEFAULT_SORT = SORTS[0];

/** Platformy filtru (enum Platform). */
const PLATFORMS = ['pc', 'playstation'];

/** Okno s filtry na telefonu (R45). */
const sheet = ref(null);

/** Kolik filtrů je zapnutých — platforma, žánry a co-op. */
const activeCount = computed(() => (props.filters.platform ? 1 : 0) + props.filters.genres.length + (props.filters.coop ? 1 : 0));

/** ID hry, která se právě přidává. */
const adding = ref(null);

/**
 * Načte stránku s novými filtry — výchozí hodnoty se v adrese vynechají.
 *
 * @param {object} changes
 */
function apply(changes) {
    const filters = { ...props.filters, ...changes };
    const params = {};
    if (filters.platform) params.platform = filters.platform;
    if (filters.genres.length) params.genres = filters.genres;
    if (filters.coop) params.coop = 1;
    if (filters.sort !== DEFAULT_SORT) params.sort = filters.sort;

    router.get(props.upcomingUrl, params, { preserveState: true, preserveScroll: true, replace: true });
}

/**
 * Přepne žánr ve výběru; prázdný výběr = všechny hry.
 *
 * @param {string} name
 */
function toggleGenre(name) {
    const genres = props.filters.genres.includes(name) ? props.filters.genres.filter((genre) => genre !== name) : [...props.filters.genres, name];
    apply({ genres });
}

/**
 * Začne hru sledovat — server vrátí zpět s hláškou a hra ze stránky zmizí
 * (hra jen na PS5 přejde na detail k připojení PS Store).
 *
 * @param {object} release
 */
function watch(release) {
    adding.value = release.id;
    router.post(release.watchUrl, {}, {
        preserveScroll: true,
        onFinish: () => {
            adding.value = null;
        },
    });
}

/**
 * Platformy vydání pro kartu („PC · PS5“).
 *
 * @param {string[]} platforms
 * @returns {string}
 */
function platformLabel(platforms) {
    return platforms.map((platform) => t(`upcoming.platforms.${platform}`)).join(' · ');
}
</script>

<template>
    <Head :title="t('upcoming.title')" />

    <AppLayout>
        <header class="page__header">
            <div>
                <h1 class="page__title">{{ t('discover.title') }}</h1>
                <p class="page__subtitle">{{ t('upcoming.subtitle') }}</p>
            </div>
        </header>

        <DiscoverTabs active="upcoming" :discover-url="discoverUrl" :upcoming-url="upcomingUrl" />

        <section class="panel discover-filters" :aria-label="t('filters.title')">
            <div class="discover-filters__row">
                <fieldset class="filters__group discover-filters__platform">
                    <legend class="filters__label">{{ t('upcoming.platform') }}</legend>
                    <div class="segmented segmented--fill">
                        <button
                            type="button"
                            class="segmented__option"
                            :class="{ 'segmented__option--active': !filters.platform }"
                            :aria-pressed="!filters.platform"
                            @click="apply({ platform: null })"
                        >
                            {{ t('upcoming.platforms.all') }}
                        </button>
                        <button
                            v-for="platform in PLATFORMS"
                            :key="platform"
                            type="button"
                            class="segmented__option"
                            :class="{ 'segmented__option--active': filters.platform === platform }"
                            :aria-pressed="filters.platform === platform"
                            @click="apply({ platform })"
                        >
                            {{ t(`upcoming.platforms.${platform}`) }}
                        </button>
                    </div>
                </fieldset>
                <div class="field discover-filters__sort">
                    <label class="filters__label" for="upcoming-sort">{{ t('filters.sort') }}</label>
                    <select id="upcoming-sort" class="field__input field__select" :value="filters.sort" @change="apply({ sort: $event.target.value })">
                        <option v-for="sort in SORTS" :key="sort" :value="sort">{{ t(`upcoming.sorts.${sort}`) }}</option>
                    </select>
                </div>
                <!-- Na telefonu otevře okno se žánry (R45) -->
                <button type="button" class="button button--ghost button--small filters__toggle" aria-haspopup="dialog" @click="sheet.open()">
                    {{ t('filters.toggle') }}
                    <span v-if="activeCount" class="filter-sheet__count">{{ activeCount }}</span>
                </button>
            </div>

            <FilterSheet
                ref="sheet"
                :title="t('filters.title')"
                :active-count="activeCount"
                :result-label="t('filter_sheet.show_upcoming', { count: releases.length })"
                :can-reset="activeCount > 0"
                @reset="apply({ platform: null, genres: [], coop: false })"
            >
                <div class="discover-filters__options">
                    <fieldset v-if="genres.length" class="filters__group">
                        <legend class="filters__label">{{ t('upcoming.genres') }} <span class="filters__hint">{{ t('upcoming.genres_hint') }}</span></legend>
                        <div class="chips">
                            <button
                                v-for="genre in genres"
                                :key="genre.name"
                                type="button"
                                class="chip"
                                :class="{ 'chip--active': filters.genres.includes(genre.name) }"
                                :aria-pressed="filters.genres.includes(genre.name)"
                                @click="toggleGenre(genre.name)"
                            >
                                {{ genre.name }}
                                <span v-if="genre.count" class="chip__count">{{ genre.count }}</span>
                            </button>
                        </div>
                    </fieldset>
                    <label class="switch">
                        <input class="switch__input" type="checkbox" :checked="filters.coop" @change="apply({ coop: $event.target.checked })" />
                        <span class="switch__track" aria-hidden="true"></span>
                        <span class="switch__label">{{ t('upcoming.coop') }}</span>
                    </label>
                </div>
            </FilterSheet>
        </section>

        <p v-if="!updatedAt" class="panel empty-state empty-state--compact">{{ t('upcoming.not_loaded') }}</p>
        <p v-else-if="!releases.length" class="panel empty-state empty-state--compact">{{ t('upcoming.empty') }}</p>

        <ul v-else class="game-grid">
            <li v-for="release in releases" :key="release.id" class="game-grid__item">
                <article class="game-card">
                    <img v-if="release.imageUrl" :src="release.imageUrl" alt="" class="game-card__image" loading="lazy" />
                    <span v-else class="game-card__image game-card__image--empty" aria-hidden="true"></span>
                    <span class="game-card__body">
                        <span class="game-card__title">{{ release.title }}</span>
                        <span class="upcoming-card__releases">
                            <span v-for="date in release.releases" :key="date.platforms.join()" class="upcoming-card__release">
                                <span class="upcoming-card__platforms">{{ platformLabel(date.platforms) }}</span>
                                <span class="game-card__release">{{ formatReleaseDate(date.date, date.precision, page.props.locale, t) }}</span>
                                <span v-if="date.earlyAccess" class="upcoming-card__early-access">{{ t('upcoming.early_access') }}</span>
                            </span>
                        </span>
                        <span v-if="release.genres.length || release.coop" class="game-card__meta">
                            <span v-if="release.coop" class="badge">{{ t('upcoming.coop_badge') }}</span>
                            <span>{{ release.genres.join(', ') }}</span>
                        </span>
                        <span class="game-card__actions">
                            <button type="button" class="button button--primary button--small" :disabled="adding !== null" @click="watch(release)">
                                {{ t('upcoming.watch') }}
                            </button>
                            <Link v-if="release.detailUrl" :href="release.detailUrl" class="button button--ghost button--small">{{ t('upcoming.detail') }}</Link>
                            <a v-if="release.igdbUrl" :href="release.igdbUrl" class="button button--ghost button--small" target="_blank" rel="noopener noreferrer">{{ t('upcoming.igdb') }}</a>
                        </span>
                    </span>
                </article>
            </li>
        </ul>

        <p class="discover__source">
            <a :href="igdbUrl" target="_blank" rel="noopener noreferrer">{{ t('upcoming.source') }}</a>
            <template v-if="updatedAt"> {{ t('upcoming.updated', { time: formatDateTime(updatedAt, page.props.locale, page.props.timezone) }) }}</template>
        </p>
    </AppLayout>
</template>
