<!--
    Objevit (R43) — slevy her z IsThereAnyDeal, které uživatel ještě nesleduje:
    podle štítků (předvolené pro hraní ve dvou a nejčastější štítky sledovaných
    her), volitelně jen historická minima, řazení. Stav je v adrese, filtruje
    ITAD (server, v cache); hru jde rovnou přidat ke sledování.

    @author Roman Hlaváček
    @created 2026-10-13
-->
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateInZone, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    deals: { type: Array, required: true },
    tags: { type: Array, default: () => [] },
    unavailable: { type: Boolean, default: false },
    filters: { type: Object, required: true },
    discoverUrl: { type: String, required: true },
    watchUrl: { type: String, required: true },
});

const t = useTranslations();
const page = usePage();

/** Řazení (enum DiscoverSort); první je výchozí. */
const SORTS = ['popular', 'trending', 'cut', 'price', 'newest'];
const DEFAULT_SORT = SORTS[0];

/** ITAD ID hry, která se právě přidává. */
const adding = ref(null);

/**
 * Načte stránku s novými filtry — výchozí hodnoty se v adrese vynechají.
 *
 * @param {object} changes
 */
function apply(changes) {
    const filters = { ...props.filters, ...changes };
    const params = {};
    if (filters.tags.length) params.tags = filters.tags;
    if (filters.sort !== DEFAULT_SORT) params.sort = filters.sort;
    if (!filters.lows) params.lows = 0;

    router.get(props.discoverUrl, params, { preserveState: true, preserveScroll: true, replace: true });
}

/**
 * Přepne štítek ve výběru; prázdný výběr = všechny hry.
 *
 * @param {string} name
 */
function toggleTag(name) {
    const tags = props.filters.tags.includes(name) ? props.filters.tags.filter((tag) => tag !== name) : [...props.filters.tags, name];
    apply({ tags });
}

/**
 * Přidá hru ke sledování — server vrátí zpět s hláškou a hra ze stránky zmizí.
 *
 * @param {object} deal
 */
function watch(deal) {
    adding.value = deal.itadId;
    router.post(props.watchUrl, { itad_id: deal.itadId }, {
        preserveScroll: true,
        onFinish: () => {
            adding.value = null;
        },
    });
}
</script>

<template>
    <Head :title="t('discover.title')" />

    <AppLayout>
        <header class="page__header">
            <div>
                <h1 class="page__title">{{ t('discover.title') }}</h1>
                <p class="page__subtitle">{{ t('discover.subtitle') }}</p>
            </div>
        </header>

        <section class="panel discover-filters" :aria-label="t('filters.title')">
            <fieldset class="filters__group">
                <legend class="filters__label">{{ t('discover.tags') }} <span class="filters__hint">{{ t('discover.tags_hint') }}</span></legend>
                <div class="chips">
                    <button
                        v-for="tag in tags"
                        :key="tag.name"
                        type="button"
                        class="chip"
                        :class="{ 'chip--active': filters.tags.includes(tag.name) }"
                        :aria-pressed="filters.tags.includes(tag.name)"
                        @click="toggleTag(tag.name)"
                    >
                        {{ tag.name }}
                        <span v-if="tag.count" class="chip__count">{{ tag.count }}</span>
                    </button>
                </div>
            </fieldset>

            <div class="discover-filters__row">
                <div class="field discover-filters__sort">
                    <label class="filters__label" for="discover-sort">{{ t('filters.sort') }}</label>
                    <select id="discover-sort" class="field__input field__select" :value="filters.sort" @change="apply({ sort: $event.target.value })">
                        <option v-for="sort in SORTS" :key="sort" :value="sort">{{ t(`discover.sorts.${sort}`) }}</option>
                    </select>
                </div>
                <label class="switch">
                    <input class="switch__input" type="checkbox" :checked="filters.lows" @change="apply({ lows: $event.target.checked })" />
                    <span class="switch__track" aria-hidden="true"></span>
                    <span class="switch__label">{{ t('discover.lows') }}</span>
                </label>
            </div>
        </section>

        <p v-if="unavailable" class="notice notice--error" role="alert">{{ t('discover.unavailable') }}</p>
        <p v-else-if="!deals.length" class="panel empty-state empty-state--compact">{{ t('discover.empty') }}</p>

        <ul v-else class="game-grid">
            <li v-for="deal in deals" :key="deal.itadId" class="game-grid__item">
                <article class="game-card">
                    <img v-if="deal.bannerUrl" :src="deal.bannerUrl" alt="" class="game-card__image" loading="lazy" />
                    <span v-else class="game-card__image game-card__image--empty" aria-hidden="true"></span>
                    <span class="game-card__body">
                        <span class="game-card__title">{{ deal.title }}</span>
                        <span v-if="deal.flag" class="game-card__flag" :class="`game-card__flag--${deal.flag}`">{{ t(`discover.flags.${deal.flag}`) }}</span>
                        <span class="game-card__price">
                            <span class="game-card__amount">{{ formatPrice(deal.price, page.props.locale) }}</span>
                            <span v-if="deal.cut" class="game-card__cut">{{ t('prices.cut', { cut: deal.cut }) }}</span>
                            <span class="game-card__shop">{{ deal.shop }}</span>
                        </span>
                        <span class="game-card__regular">
                            <template v-if="deal.regular">{{ t('discover.regular', { price: formatPrice(deal.regular, page.props.locale) }) }}</template>
                            <template v-if="deal.expiresAt"> · {{ t('prices.until', { date: formatDateInZone(deal.expiresAt, page.props.locale, page.props.timezone) }) }}</template>
                        </span>
                        <span class="game-card__actions">
                            <button type="button" class="button button--primary button--small" :disabled="adding !== null" @click="watch(deal)">
                                {{ t('discover.watch') }}
                            </button>
                            <Link v-if="deal.detailUrl" :href="deal.detailUrl" class="button button--ghost button--small">{{ t('discover.detail') }}</Link>
                            <a v-if="deal.url" :href="deal.url" class="button button--ghost button--small" target="_blank" rel="noopener noreferrer">{{ t('prices.open_shop') }}</a>
                        </span>
                    </span>
                </article>
            </li>
        </ul>

        <p class="discover__source">{{ t('discover.source') }}</p>
    </AppLayout>
</template>
