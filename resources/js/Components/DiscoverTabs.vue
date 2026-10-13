<!--
    Záložky stránky Objevit (R46) — slevy her z IsThereAnyDeal a nadcházející
    hry z IGDB. Každá záložka je vlastní adresa (vlastní filtry v adrese).

    @author Roman Hlaváček
    @created 2026-10-13
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { Link } from '@inertiajs/vue3';

defineProps({
    active: { type: String, required: true },
    discoverUrl: { type: String, required: true },
    upcomingUrl: { type: String, required: true },
});

const t = useTranslations();
</script>

<template>
    <nav class="tabs" :aria-label="t('discover.tabs.label')">
        <Link
            :href="discoverUrl"
            class="tabs__tab"
            :class="{ 'tabs__tab--active': active === 'deals' }"
            :aria-current="active === 'deals' ? 'page' : undefined"
        >
            {{ t('discover.tabs.deals') }}
        </Link>
        <Link
            :href="upcomingUrl"
            class="tabs__tab"
            :class="{ 'tabs__tab--active': active === 'upcoming' }"
            :aria-current="active === 'upcoming' ? 'page' : undefined"
        >
            {{ t('discover.tabs.upcoming') }}
        </Link>
    </nav>
</template>
