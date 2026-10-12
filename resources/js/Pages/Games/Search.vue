<!--
    Přidání hry — hledání v katalogu IsThereAnyDeal podle názvu, Steam App ID
    nebo odkazu na Steam (etapa 2, R10). Hledá se až po odeslání formuláře
    (limit API, viz games.catalog). U výsledků ukazuje obchody a nejnižší
    cenu (R23). Hru jde sledovat všude, nebo jen na PlayStationu; s odkazem
    z PS Store se hledá podle názvu produktu a produkt se při přidání
    připojí (R24).

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import GameBadges from '@/Components/GameBadges.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    query: { type: String, default: '' },
    searched: { type: Boolean, default: false },
    results: { type: Array, default: () => [] },
    unavailable: { type: Boolean, default: false },
    playstation: { type: Object, default: null },
    playstationError: { type: String, default: null },
    searchUrl: { type: String, required: true },
    watchUrl: { type: String, required: true },
});

const t = useTranslations();
const page = usePage();
const form = useForm({ q: props.query });

/** ITAD ID hry, která se právě přidává — tlačítka se do odpovědi zablokují. */
const adding = ref(null);

/** Hodnota platformy „jen PlayStation“ (enum Platform). */
const PLAYSTATION = 'playstation';

/**
 * Odešle hledání — výsledek je v adrese, jde ho sdílet i vrátit se k němu.
 */
function search() {
    form.get(props.searchUrl, { preserveState: true });
}

/**
 * Přidá hru na seznam; server vrátí zpět na výsledky s hláškou. Při hledání
 * podle odkazu z PS Store se produkt připojí a hra se hlídá jen na PlayStationu.
 * Hru jen na PlayStationu bez odkazu server pošle na detail k připojení.
 *
 * @param {object} game výsledek hledání
 * @param {string|null} platform
 */
function watch(game, platform = null) {
    adding.value = game.itadId;
    let data = { itad_id: game.itadId, platform };
    if (props.playstation) {
        data = { itad_id: game.itadId, platform: PLAYSTATION, listing_url: props.playstation.url };
    } else if (platform === PLAYSTATION && game.playstationUrl) {
        // Stránka hry na playstation.com — produkt se připojí rovnou (R24)
        data.listing_url = game.playstationUrl;
    }

    router.post(
        props.watchUrl,
        data,
        {
            preserveScroll: true,
            onFinish: () => {
                adding.value = null;
            },
        },
    );
}
</script>

<template>
    <Head :title="t('search.title')" />

    <AppLayout>
        <header class="page__header">
            <h1 class="page__title">{{ t('search.title') }}</h1>
        </header>

        <form class="panel search" role="search" @submit.prevent="search">
            <div class="field">
                <label class="field__label" for="q">{{ t('search.label') }}</label>
                <div class="search__row">
                    <input
                        id="q"
                        v-model="form.q"
                        class="field__input search__input"
                        :class="{ 'field__input--invalid': form.errors.q }"
                        type="search"
                        name="q"
                        :placeholder="t('search.placeholder')"
                        autocomplete="off"
                        autofocus
                        :aria-invalid="form.errors.q ? 'true' : undefined"
                        aria-describedby="q-hint"
                    />
                    <button type="submit" class="button button--primary" :disabled="form.processing">
                        {{ t('search.submit') }}
                    </button>
                </div>
                <p v-if="form.errors.q" class="field__error">{{ form.errors.q }}</p>
                <p id="q-hint" class="field__hint">{{ t('search.hint') }}</p>
            </div>
        </form>

        <p v-if="unavailable" class="notice notice--error" role="alert">{{ t('search.unavailable') }}</p>
        <p v-else-if="playstationError" class="notice notice--error" role="alert">{{ playstationError }}</p>

        <section v-else-if="searched" class="search-results" :aria-label="t('search.results')">
            <div v-if="playstation" class="search-results__source">
                <span class="search-results__source-label">{{ t('search.playstation_product') }}</span>
                <a :href="playstation.url" class="search-results__source-name" target="_blank" rel="noopener noreferrer">{{ playstation.name }}</a>
                <span class="search-results__source-hint">{{ t('search.playstation_connect') }}</span>
            </div>

            <p v-if="!results.length" class="search-results__empty">{{ t('search.no_results') }}</p>

            <ul v-else class="search-results__list">
                <li v-for="game in results" :key="game.itadId" class="search-result">
                    <img v-if="game.thumbnailUrl" :src="game.thumbnailUrl" alt="" class="search-result__image" loading="lazy" />
                    <span v-else class="search-result__image search-result__image--empty" aria-hidden="true"></span>
                    <span class="search-result__body">
                        <span class="search-result__title">{{ game.title }}</span>
                        <GameBadges :type="game.type" :mature="game.isMature" />
                        <span v-if="game.availabilityKnown" class="search-result__availability">
                            <span
                                v-if="game.shops.length || game.onPlayStation"
                                class="search-result__shops"
                                :aria-label="t('search.shops')"
                            >
                                <span v-if="game.onPlayStation" class="shop-chip shop-chip--playstation">
                                    {{ t('search.playstation') }}
                                </span>
                                <!-- ITAD zná jen obchody pro PC — štítky nejsou konkrétní verze hry -->
                                <span v-if="game.shops.length" class="search-result__platform">{{ t('search.pc') }}</span>
                                <span v-for="shop in game.shops" :key="shop" class="shop-chip">{{ shop }}</span>
                                <span v-if="game.moreShops" class="shop-chip shop-chip--more">
                                    {{ t('search.more_shops', { count: game.moreShops }) }}
                                </span>
                            </span>
                            <span v-else class="search-result__no-shops">{{ t('search.no_pc_shops') }}</span>
                            <span v-if="game.free" class="search-result__price">{{ t('search.free') }}</span>
                            <span v-else-if="game.bestPrice" class="search-result__price">
                                {{ t('search.from_price', { price: formatPrice(game.bestPrice, page.props.locale) }) }}
                            </span>
                        </span>
                    </span>
                    <span class="search-result__actions">
                        <Link v-if="game.detailUrl" :href="game.detailUrl" class="button button--ghost button--small">
                            {{ t('search.detail') }}
                        </Link>
                        <span v-if="game.watching" class="search-result__status">{{ t('search.watching') }}</span>
                        <template v-else>
                            <button
                                v-if="!playstation && game.offerPlayStation"
                                type="button"
                                class="button button--small button--playstation"
                                :title="t('search.watch_playstation_title')"
                                :disabled="adding !== null"
                                @click="watch(game, PLAYSTATION)"
                            >
                                {{ t('search.watch_playstation') }}
                            </button>
                            <button type="button" class="button button--small" :disabled="adding !== null" @click="watch(game)">
                                {{ t('search.watch') }}
                            </button>
                        </template>
                    </span>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>
