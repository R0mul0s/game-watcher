<!--
    Přehled po přihlášení — osobní seznam sledovaných her (R11).
    Ceny přibudou s etapou 3 (docs/PLAN.md).

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import GameCard from '@/Components/GameCard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    games: { type: Array, required: true },
    searchUrl: { type: String, required: true },
});

const t = useTranslations();
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AppLayout>
        <header class="page__header">
            <div>
                <h1 class="page__title">{{ t('dashboard.title') }}</h1>
                <p v-if="games.length" class="page__subtitle">{{ t('dashboard.subtitle', { count: games.length }) }}</p>
            </div>
            <Link v-if="games.length" :href="searchUrl" class="button button--primary">{{ t('dashboard.add_game') }}</Link>
        </header>

        <ul v-if="games.length" class="game-grid">
            <li v-for="game in games" :key="game.slug" class="game-grid__item">
                <GameCard :game="game" />
            </li>
        </ul>

        <section v-else class="panel empty-state">
            <!-- Prázdný „radar" — dekorace prázdného seznamu -->
            <div class="empty-state__radar" aria-hidden="true"></div>
            <h2 class="empty-state__title">{{ t('dashboard.empty_title') }}</h2>
            <p class="empty-state__text">{{ t('dashboard.empty_text') }}</p>
            <Link :href="searchUrl" class="button button--primary">{{ t('dashboard.add_game') }}</Link>
        </section>
    </AppLayout>
</template>
