<!--
    Detail hry z katalogu — údaje z IsThereAnyDeal (R10), sledování a odkazy.
    Ceny v měně účtu (R14) — panel PricePanel. U sledované hry jde zvolit,
    kde se hlídá (R24) — ceny ostatních platforem se ztlumí.

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import GameBadges from '@/Components/GameBadges.vue';
import PricePanel from '@/Components/PricePanel.vue';
import StoreListings from '@/Components/StoreListings.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate, formatInteger } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    game: { type: Object, required: true },
    watchUrl: { type: String, required: true },
});

const t = useTranslations();
const page = usePage();
const processing = ref(false);

/** Volby hlídané platformy — null = všude (enum Platform). */
const WATCH_PLATFORMS = [null, 'pc', 'playstation'];

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
        <article class="game-detail">
            <header class="game-detail__hero">
                <img v-if="game.boxartUrl" :src="game.boxartUrl" alt="" class="game-detail__boxart" />
                <img v-else-if="game.bannerUrl" :src="game.bannerUrl" alt="" class="game-detail__banner" />

                <div class="game-detail__heading">
                    <GameBadges :type="game.type" :mature="game.isMature" />
                    <h1 class="game-detail__title">{{ game.title }}</h1>

                    <dl class="facts">
                        <div class="facts__item">
                            <dt class="facts__label">{{ t('game.release_date') }}</dt>
                            <dd class="facts__value">
                                {{ game.releaseDate ? formatDate(game.releaseDate, page.props.locale) : t('game.unreleased') }}
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
                </div>
            </header>

            <div class="game-detail__panels">
                <PricePanel :prices="game.prices" :focus-platform="game.watchPlatform" class="game-detail__prices" />
                <StoreListings :listings="game.listings" :connect-url="game.connectUrl" class="game-detail__prices" />

                <section v-if="game.reviews.length" class="panel">
                    <h2 class="panel__title">{{ t('game.reviews') }}</h2>
                    <ul class="reviews">
                        <li v-for="review in game.reviews" :key="review.source" class="reviews__item">
                            <span class="reviews__score" :class="`reviews__score--${review.level}`">{{ review.score }}</span>
                            <span class="reviews__source">
                                <a v-if="review.url" :href="review.url" target="_blank" rel="noopener noreferrer">{{ review.source }}</a>
                                <template v-else>{{ review.source }}</template>
                                <span v-if="review.count" class="reviews__count">
                                    {{ t('game.reviews_count', { count: formatInteger(review.count, page.props.locale) }) }}
                                </span>
                            </span>
                        </li>
                    </ul>
                </section>

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
