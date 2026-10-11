<!--
    Přehled po přihlášení. Sledované hry a jejich ceny přibudou s etapami 2 a 3
    (docs/PLAN.md) — zatím prázdný stav.

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head } from '@inertiajs/vue3';

const t = useTranslations();
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AppLayout>
        <header class="page__header">
            <h1 class="page__title">{{ t('dashboard.title') }}</h1>
        </header>

        <section class="panel empty-state">
            <!-- Prázdný „radar" — dekorace prázdného seznamu -->
            <div class="empty-state__radar" aria-hidden="true"></div>
            <h2 class="empty-state__title">{{ t('dashboard.empty_title') }}</h2>
            <p class="empty-state__text">{{ t('dashboard.empty_text') }}</p>
        </section>
    </AppLayout>
</template>
