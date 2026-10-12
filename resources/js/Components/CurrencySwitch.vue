<!--
    Přepínač měny cen v hlavičce (CZK / EUR, R14) — volba se ukládá u účtu.

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { router, usePage } from '@inertiajs/vue3';

const t = useTranslations();
const page = usePage();

/**
 * Uloží měnu; stránka se obnoví s cenami v nové měně a na stejném místě.
 *
 * @param {string} currency
 */
function select(currency) {
    if (currency === page.props.currency.current) {
        return;
    }

    router.put(page.props.currency.updateUrl, { currency }, { preserveScroll: true });
}
</script>

<template>
    <div v-if="page.props.currency" class="segmented" role="group" :aria-label="t('currency.label')">
        <button
            v-for="option in page.props.currency.options"
            :key="option"
            type="button"
            class="segmented__option"
            :class="{ 'segmented__option--active': option === page.props.currency.current }"
            :aria-pressed="option === page.props.currency.current"
            @click="select(option)"
        >
            {{ option }}
        </button>
    </div>
</template>
