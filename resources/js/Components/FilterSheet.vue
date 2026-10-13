<!--
    Filtry na telefonu v okně (R45) — obsah slotu je na širší obrazovce na místě,
    na telefonu se přesune do okna, které vyjede zespodu (nativní <dialog>:
    fokus, Esc a zavření klepnutím vedle). Okno otevře tlačítko na stránce
    (open() přes ref) nebo plovoucí tlačítko vlevo dole, které se ukáže, až
    filtry odjedou z obrazovky — s počtem zapnutých filtrů. Filtry se použijí
    hned (stránka za oknem se mění), tlačítko dole okno jen zavře.

    @author Roman Hlaváček
    @created 2026-10-13
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { PHONE_QUERY, useMediaQuery } from '@/lib/media';
import { onBeforeUnmount, onMounted, ref, useId } from 'vue';

defineProps({
    /** Nadpis okna. */
    title: { type: String, required: true },
    /** Kolik filtrů je zapnutých — číslo na plovoucím tlačítku. */
    activeCount: { type: Number, default: 0 },
    /** Text hlavního tlačítka okna („Zobrazit 5 her“). */
    resultLabel: { type: String, required: true },
    /** Nabídnout „Zrušit filtry“. */
    canReset: { type: Boolean, default: false },
});

const emit = defineEmits(['reset']);

const t = useTranslations();
const isPhone = useMediaQuery(PHONE_QUERY);
const id = useId();
const bodyId = `filter-sheet-body-${id}`;
const titleId = `filter-sheet-title-${id}`;

const dialog = ref(null);
const anchor = ref(null);
/** Místo filtrů na stránce je mimo obrazovku — ukázat plovoucí tlačítko. */
const offscreen = ref(false);
let observer = null;

/**
 * Otevře okno s filtry.
 */
function open() {
    dialog.value?.showModal();
}

/**
 * Zavře okno s filtry.
 */
function close() {
    dialog.value?.close();
}

/**
 * Klepnutí vedle okna (na pozadí <dialog>) okno zavře.
 *
 * @param {MouseEvent} event
 */
function onDialogClick(event) {
    if (event.target === dialog.value) {
        close();
    }
}

onMounted(() => {
    observer = new IntersectionObserver(([entry]) => {
        offscreen.value = !entry.isIntersecting;
    });
    observer.observe(anchor.value);
});
onBeforeUnmount(() => observer?.disconnect());

defineExpose({ open });
</script>

<template>
    <!-- Kde filtry na stránce jsou — podle něj se ukazuje plovoucí tlačítko -->
    <span ref="anchor" class="filter-sheet__anchor" aria-hidden="true"></span>

    <Teleport defer :to="`#${bodyId}`" :disabled="!isPhone">
        <slot />
    </Teleport>

    <!-- Do <body>: panel filtrů má backdrop-filter, uvnitř by se fixed vztahovalo k panelu -->
    <Teleport to="body">
        <button
            v-if="isPhone"
            type="button"
            class="filter-sheet__fab"
            :class="{ 'filter-sheet__fab--visible': offscreen }"
            :tabindex="offscreen ? 0 : -1"
            :aria-hidden="!offscreen"
            @click="open"
        >
            <svg class="filter-sheet__fab-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8z" /></svg>
            {{ t('filter_sheet.open') }}
            <span v-if="activeCount" class="filter-sheet__count">{{ activeCount }}</span>
        </button>
    </Teleport>

    <dialog ref="dialog" class="filter-sheet" :aria-labelledby="titleId" @click="onDialogClick">
        <div class="filter-sheet__panel">
            <header class="filter-sheet__header">
                <h2 :id="titleId" class="filter-sheet__title">{{ title }}</h2>
                <button type="button" class="filter-sheet__close" :aria-label="t('filter_sheet.close')" @click="close">×</button>
            </header>
            <div :id="bodyId" class="filter-sheet__body"></div>
            <footer class="filter-sheet__footer">
                <button v-if="canReset" type="button" class="button button--ghost" @click="emit('reset')">{{ t('filters.reset') }}</button>
                <button type="button" class="button button--primary filter-sheet__done" @click="close">{{ resultLabel }}</button>
            </footer>
        </div>
    </dialog>
</template>
