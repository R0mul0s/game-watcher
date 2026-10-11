<!--
    Jednorázová hláška po akci (přidání hry…) — Inertia flash ze serveru.
    Zobrazí se nad obsahem stránky a jde zavřít.

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const t = useTranslations();
const page = usePage();
const dismissed = ref(false);

/** Hláška k zobrazení — chyba má přednost před úspěchem. */
const message = computed(() => {
    const flash = page.flash ?? {};
    if (flash.error) {
        return { type: 'error', text: flash.error };
    }

    return flash.success ? { type: 'success', text: flash.success } : null;
});

// Nová hláška se zobrazí, i když předchozí uživatel zavřel
watch(message, () => {
    dismissed.value = false;
});
</script>

<template>
    <div
        v-if="message && !dismissed"
        class="flash"
        :class="`flash--${message.type}`"
        :role="message.type === 'error' ? 'alert' : 'status'"
    >
        <p class="flash__text">{{ message.text }}</p>
        <button type="button" class="flash__close" :aria-label="t('flash.dismiss')" @click="dismissed = true">
            <svg class="flash__icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 6l12 12M18 6L6 18" />
            </svg>
        </button>
    </div>
</template>
