<!--
    Stažení stránky dolů pro nové načtení v aplikaci z plochy (R37) — neonový
    kruh se šipkou vyjede zpod hlavičky, šipka se točí s prstem, za hranicí se
    rozsvítí a po puštění se data stránky načtou znovu (Inertia, posun stránky
    zůstane). V prohlížeči se nevykreslí — tam to umí prohlížeč sám. Gesto řídí
    lib/pullToRefresh.js; převzato z Agregátoru letáků.

    @author Roman Hlaváček
    @created 2026-10-13
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { usePullToRefresh } from '@/lib/pullToRefresh';
import { pwaState } from '@/lib/pwa';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const t = useTranslations();
const indicator = ref(null);

const { distance, ready, refreshing } = usePullToRefresh({
    enabled: () => pwaState.standalone,
    refresh: () => new Promise((resolve) => router.reload({ onFinish: resolve })),
});

// Poloha ukazatele se mění s prstem — jako proměnná CSS, pohyb a otočení řeší stylesheet
watch(distance, (value) => indicator.value?.style.setProperty('--pull-distance', `${value}px`));
</script>

<template>
    <template v-if="pwaState.standalone">
        <div
            ref="indicator"
            class="pull-refresh"
            :class="{ 'pull-refresh--visible': distance > 0 || refreshing, 'pull-refresh--ready': ready, 'pull-refresh--refreshing': refreshing }"
            aria-hidden="true"
        >
            <svg class="pull-refresh__icon" viewBox="0 0 24 24"><path d="M20 12a8 8 0 1 1-2.34-5.66M20 4v5h-5" /></svg>
        </div>
        <!-- Čtečka obrazovky se dozví, že se stránka načítá -->
        <p class="visually-hidden" role="status">{{ refreshing ? t('pwa.refreshing') : '' }}</p>
    </template>
</template>
