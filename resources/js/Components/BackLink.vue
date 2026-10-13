<!--
    Tlačítko Zpět (R38) — když uživatel přišel z aplikace, vrátí ho historií
    prohlížeče: na přehled se stejnými filtry (jsou v adrese, R21) a na stejné
    místo stránky (posun obnoví Inertia). Bez předchozí stránky v aplikaci
    (detail z upozornění nebo odkazu) vede odkazem na přehled. Popisek podle
    stránky, kam vede — stránky hlavní navigace mají vlastní („Zpět na přehled“).

    @author Roman Hlaváček
    @created 2026-10-13
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { navigationState, pathOf } from '@/lib/navigation';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const t = useTranslations();
const page = usePage();

/** Vrátit se historií — předchozí stránka je z aplikace. */
const inApp = computed(() => navigationState.previousUrl !== null);

/** Popisek podle cílové stránky: položka hlavní navigace „nav.dashboard“ → „back.dashboard“. */
const label = computed(() => {
    const target = pathOf(inApp.value ? navigationState.previousUrl : page.props.homeUrl);
    const item = page.props.navigation.find((navigationItem) => pathOf(navigationItem.url) === target);

    return t(item ? item.label.replace(/^nav\./, 'back.') : 'back.default');
});

/**
 * Krok zpět v historii — Inertia obnoví stránku i posun bez nového stahování.
 */
function goBack() {
    window.history.back();
}
</script>

<template>
    <button v-if="inApp" type="button" class="back-link" @click="goBack">
        <span class="back-link__arrow" aria-hidden="true">←</span>
        {{ label }}
    </button>
    <Link v-else :href="page.props.homeUrl" class="back-link">
        <span class="back-link__arrow" aria-hidden="true">←</span>
        {{ label }}
    </Link>
</template>
