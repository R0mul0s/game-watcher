<!--
    Přehled po přihlášení — osobní seznam sledovaných her (R11) s pokročilými
    filtry (R21). U každé hry nejlevnější cena v měně účtu (R14); nad filtry
    slevy dne (R26), při výpadku zdroje cen upozornění (R29).

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import DailyDeals from '@/Components/DailyDeals.vue';
import GameCard from '@/Components/GameCard.vue';
import WatchlistFilters from '@/Components/WatchlistFilters.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    games: { type: Array, required: true },
    total: { type: Number, required: true },
    genres: { type: Array, default: () => [] },
    dailyDeals: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
    dashboardUrl: { type: String, required: true },
    searchUrl: { type: String, required: true },
    sourcesDown: { type: Array, default: () => [] },
    sourcesUrl: { type: String, required: true },
});

const t = useTranslations();

/** Oddělovač názvů zdrojů ve výpadku. */
const SOURCE_SEPARATOR = ', ';

/** Názvy zdrojů ve výpadku pro upozornění nad přehledem. */
const downSources = computed(() => props.sourcesDown.map((source) => t(`sources.names.${source}`)).join(SOURCE_SEPARATOR));
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AppLayout>
        <header class="page__header">
            <div>
                <h1 class="page__title">{{ t('dashboard.title') }}</h1>
                <p v-if="total" class="page__subtitle">{{ t('dashboard.subtitle', { count: total }) }}</p>
            </div>
            <Link v-if="total" :href="searchUrl" class="button button--primary">{{ t('dashboard.add_game') }}</Link>
        </header>

        <p v-if="sourcesDown.length" class="notice notice--error" role="status">
            {{ t('sources.dashboard_down', { sources: downSources }) }}
            <Link :href="sourcesUrl">{{ t('sources.dashboard_link') }}</Link>
        </p>

        <template v-if="total">
            <DailyDeals v-if="dailyDeals.length" :deals="dailyDeals" />
            <WatchlistFilters :filters="filters" :genres="genres" :shown="games.length" :total="total" :url="dashboardUrl" />

            <ul v-if="games.length" class="game-grid">
                <li v-for="game in games" :key="game.slug" class="game-grid__item">
                    <GameCard :game="game" />
                </li>
            </ul>
            <p v-else class="panel empty-state empty-state--compact">{{ t('filters.empty') }}</p>
        </template>

        <section v-else class="panel empty-state">
            <!-- Prázdný „radar" — dekorace prázdného seznamu -->
            <div class="empty-state__radar" aria-hidden="true"></div>
            <h2 class="empty-state__title">{{ t('dashboard.empty_title') }}</h2>
            <p class="empty-state__text">{{ t('dashboard.empty_text') }}</p>
            <Link :href="searchUrl" class="button button--primary">{{ t('dashboard.add_game') }}</Link>
        </section>
    </AppLayout>
</template>
