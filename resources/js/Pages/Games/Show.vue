<!--
    Detail hry z katalogu — údaje z IsThereAnyDeal (R10), sledování a odkazy.
    Ceny v měně účtu (R14) — panel PricePanel. U sledované hry jde zvolit,
    kde se hlídá (R24) — ceny ostatních platforem se ztlumí — a cílovou
    cenu pro upozornění (etapa 6, R26). Graf vývoje ceny se dočte po zobrazení
    stránky (odložený prop, etapa 7, R28).

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import BackLink from '@/Components/BackLink.vue';
import GameBadges from '@/Components/GameBadges.vue';
import GameRatings from '@/Components/GameRatings.vue';
import PricePanel from '@/Components/PricePanel.vue';
import StoreListings from '@/Components/StoreListings.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate, releaseCountdown } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Deferred, Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, ref } from 'vue';

/** Graf s ECharts až když je potřeba — knihovna je velká, stránka se zobrazí bez ní. */
const PriceChart = defineAsyncComponent(() => import('@/Components/PriceChart.vue'));

const props = defineProps({
    game: { type: Object, required: true },
    watchUrl: { type: String, required: true },
    /** Graf vývoje ceny — odložený prop (PriceChart na serveru). */
    chart: { type: Object, default: null },
});

const t = useTranslations();
const page = usePage();
const processing = ref(false);

/** Telefon — pod $breakpoint-md (abstracts/_variables.scss) je místo obalu na výšku široký banner. */
const PHONE_MEDIA = '(max-width: 767px)';
/** Hra má vlastní banner (bannerUrl jinak obsahuje obal). */
const hasBanner = computed(() => props.game.bannerUrl !== null && props.game.bannerUrl !== props.game.boxartUrl);

/** Volby hlídané platformy — null = všude (enum Platform). */
const WATCH_PLATFORMS = [null, 'pc', 'playstation'];

/** Cílová cena v měně zobrazení; prázdná = upozornit na historické minimum. */
const targetForm = useForm({ target_price: props.game.target?.amount ?? '' });

/**
 * Uloží cílovou cenu.
 */
function saveTarget() {
    targetForm.put(props.game.targetUrl, { preserveScroll: true });
}

/**
 * Zruší cílovou cenu.
 */
function clearTarget() {
    targetForm.target_price = '';
    saveTarget();
}

/** Volby požadavku — tlačítko se do odpovědi zablokuje. */
const requestOptions = {
    preserveScroll: true,
    onStart: () => {
        processing.value = true;
    },
    onFinish: () => {
        processing.value = false;
    },
};

/**
 * Uloží, kde se hra hlídá.
 *
 * @param {string|null} platform
 */
function setWatchPlatform(platform) {
    router.put(props.game.watchPlatformUrl, { platform }, requestOptions);
}

/**
 * Přidá hru na seznam, nebo ji z něj odebere.
 */
function toggleWatch() {
    if (props.game.watching) {
        router.delete(props.game.unwatchUrl, requestOptions);
    } else {
        router.post(props.watchUrl, { itad_id: props.game.itadId }, requestOptions);
    }
}
</script>

<template>
    <Head :title="game.title" />

    <AppLayout>
        <!-- Zpět na stejně vyfiltrovaný přehled na stejné místo (R38) -->
        <BackLink />
        <article class="game-detail">
            <header class="game-detail__hero">
                <!-- Na telefonu široký banner přes celou šířku, jinak obal na výšku (R39) -->
                <picture v-if="game.boxartUrl && hasBanner" class="game-detail__cover">
                    <source :media="PHONE_MEDIA" :srcset="game.bannerUrl" />
                    <img :src="game.boxartUrl" alt="" class="game-detail__boxart game-detail__boxart--with-banner" />
                </picture>
                <img v-else-if="game.boxartUrl" :src="game.boxartUrl" alt="" class="game-detail__boxart" />
                <img v-else-if="game.bannerUrl" :src="game.bannerUrl" alt="" class="game-detail__banner" />

                <div class="game-detail__heading">
                    <GameBadges :type="game.type" :mature="game.isMature" />
                    <h1 class="game-detail__title">{{ game.title }}</h1>

                    <dl class="facts">
                        <div class="facts__item">
                            <dt class="facts__label">{{ t('game.release_date') }}</dt>
                            <dd class="facts__value">
                                {{ game.releaseDate ? formatDate(game.releaseDate, page.props.locale) : t('game.unreleased') }}
                                <span v-if="game.releaseInDays !== null" class="game-detail__release">{{ releaseCountdown(game.releaseInDays, t) }}</span>
                            </dd>
                        </div>
                        <div v-if="game.developers.length" class="facts__item">
                            <dt class="facts__label">{{ t('game.developers') }}</dt>
                            <dd class="facts__value">{{ game.developers.join(', ') }}</dd>
                        </div>
                        <div v-if="game.publishers.length" class="facts__item">
                            <dt class="facts__label">{{ t('game.publishers') }}</dt>
                            <dd class="facts__value">{{ game.publishers.join(', ') }}</dd>
                        </div>
                        <div v-if="game.genres.length" class="facts__item">
                            <dt class="facts__label">{{ t('game.genres') }}</dt>
                            <dd class="facts__value">{{ game.genres.join(', ') }}</dd>
                        </div>
                        <div v-if="game.modes.length" class="facts__item">
                            <dt class="facts__label">{{ t('game.modes') }}</dt>
                            <dd class="facts__value">{{ game.modes.map((mode) => t(`game.mode_names.${mode}`)).join(', ') }}</dd>
                        </div>
                    </dl>

                    <div class="game-detail__actions">
                        <span v-if="game.watching" class="game-detail__status">{{ t('game.watching') }}</span>
                        <button
                            type="button"
                            class="button"
                            :class="game.watching ? 'button--ghost' : 'button--primary'"
                            :disabled="processing"
                            @click="toggleWatch"
                        >
                            {{ game.watching ? t('game.unwatch') : t('game.watch') }}
                        </button>
                    </div>

                    <div v-if="game.watching" class="game-detail__watch-platform">
                        <span id="watch-platform" class="game-detail__watch-label">{{ t('game.watch_platform') }}</span>
                        <div class="segmented" role="group" aria-labelledby="watch-platform">
                            <button
                                v-for="platform in WATCH_PLATFORMS"
                                :key="platform ?? 'all'"
                                type="button"
                                class="segmented__option"
                                :class="{ 'segmented__option--active': game.watchPlatform === platform }"
                                :aria-pressed="game.watchPlatform === platform"
                                :disabled="processing"
                                @click="setWatchPlatform(platform)"
                            >
                                {{ t(`game.watch_platforms.${platform ?? 'all'}`) }}
                            </button>
                        </div>
                    </div>

                    <form v-if="game.watching" class="game-detail__target" @submit.prevent="saveTarget">
                        <label class="game-detail__watch-label" for="target-price">{{ t('game.target_label') }}</label>
                        <div class="game-detail__target-row">
                            <input
                                id="target-price"
                                v-model="targetForm.target_price"
                                class="field__input game-detail__target-input"
                                :class="{ 'field__input--invalid': targetForm.errors.target_price }"
                                type="number"
                                min="0.01"
                                step="0.01"
                                inputmode="decimal"
                                aria-describedby="target-hint"
                            />
                            <span class="game-detail__target-unit">{{ page.props.currency?.current }}</span>
                            <button type="submit" class="button button--small" :disabled="targetForm.processing || !targetForm.isDirty">
                                {{ t('game.target_save') }}
                            </button>
                            <button v-if="game.target" type="button" class="button button--ghost button--small" :disabled="targetForm.processing" @click="clearTarget">
                                {{ t('game.target_clear') }}
                            </button>
                        </div>
                        <p v-if="targetForm.errors.target_price" class="field__error">{{ targetForm.errors.target_price }}</p>
                        <p id="target-hint" class="field__hint game-detail__target-hint">{{ t('game.target_hint') }}</p>
                    </form>
                </div>
            </header>

            <div class="game-detail__panels">
                <PricePanel :prices="game.prices" :focus-platform="game.watchPlatform" class="game-detail__prices" />
                <Deferred data="chart">
                    <template #fallback>
                        <section class="panel price-chart price-chart--loading game-detail__prices" aria-busy="true">
                            <h2 class="panel__title">{{ t('chart.title') }}</h2>
                            <p class="panel__text">{{ t('chart.loading') }}</p>
                        </section>
                    </template>
                    <PriceChart v-if="chart" :chart="chart" :watch-platform="game.watchPlatform" class="game-detail__prices" />
                </Deferred>
                <StoreListings :listings="game.listings" :connect-url="game.connectUrl" class="game-detail__prices" />

                <GameRatings :reviews="game.reviews" :player-ratings="game.playerRatings" :metacritic="game.metacritic" />

                <section v-if="game.tags.length" class="panel">
                    <h2 class="panel__title">{{ t('game.tags') }}</h2>
                    <ul class="tags">
                        <li v-for="tag in game.tags" :key="tag" class="tags__item">{{ tag }}</li>
                    </ul>
                </section>

                <section v-if="game.steamUrl || game.itadUrl" class="panel">
                    <h2 class="panel__title">{{ t('game.links') }}</h2>
                    <ul class="links">
                        <li v-if="game.steamUrl">
                            <a :href="game.steamUrl" target="_blank" rel="noopener noreferrer">{{ t('game.steam') }}</a>
                        </li>
                        <li v-if="game.itadUrl">
                            <a :href="game.itadUrl" target="_blank" rel="noopener noreferrer">{{ t('game.itad') }}</a>
                        </li>
                    </ul>
                </section>
            </div>

            <p class="game-detail__source">
                {{ t('game.source', { source: t('game.itad') }) }}
            </p>
        </article>
    </AppLayout>
</template>
