<!--
    Návrat na začátek stránky (R45) — vpravo dole neonový šestiúhelník jako
    logo, místo trojúhelníku „play“ šipka nahoru. Objeví se, až uživatel sjede
    o víc než výšku okna; po kliknutí stránka plynule vyjede nahoru (s omezeným
    pohybem skokem) a fokus jde na začátek obsahu.

    @author Roman Hlaváček
    @created 2026-10-13
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { matchesMedia, REDUCED_MOTION_QUERY } from '@/lib/media';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const t = useTranslations();

/** Po kolika výškách okna se tlačítko ukáže. */
const SHOW_AFTER_VIEWPORTS = 1;

const visible = ref(false);
let frame = null;

/**
 * Zjistí posun stránky — nejvýš jednou za snímek, posouvání se nezdrží.
 */
function onScroll() {
    if (frame !== null) {
        return;
    }

    frame = requestAnimationFrame(() => {
        frame = null;
        visible.value = window.scrollY > window.innerHeight * SHOW_AFTER_VIEWPORTS;
    });
}

/**
 * Vyjede na začátek stránky a fokus dá hlavnímu obsahu (čtečka obrazovky začne odtud).
 */
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: matchesMedia(REDUCED_MOTION_QUERY) ? 'auto' : 'smooth' });
    document.getElementById('main')?.focus({ preventScroll: true });
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});
onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    if (frame !== null) {
        cancelAnimationFrame(frame);
    }
});
</script>

<template>
    <button
        type="button"
        class="scroll-top"
        :class="{ 'scroll-top--visible': visible }"
        :aria-label="t('scroll_top')"
        :title="t('scroll_top')"
        :tabindex="visible ? 0 : -1"
        :aria-hidden="!visible"
        @click="scrollToTop"
    >
        <!-- Stejný šestiúhelník jako logo (BrandLogo), uvnitř šipka -->
        <svg class="scroll-top__icon" viewBox="0 0 48 48" aria-hidden="true">
            <polygon class="scroll-top__frame" points="24,3 42,13.5 42,34.5 24,45 6,34.5 6,13.5" />
            <polygon class="scroll-top__arrow" points="24,15 33,28 15,28" />
            <path class="scroll-top__stem" d="M24 28v6" />
        </svg>
    </button>
</template>
