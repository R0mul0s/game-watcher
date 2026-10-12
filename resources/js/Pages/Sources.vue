<!--
    Stav zdrojů cen (R29) — kdy se který zdroj naposledy povedl, kolik toho
    stáhl a poslední chyba. Data připravuje SourceMonitor, stránka jen zobrazuje.

    @author Roman Hlaváček
    @created 2026-10-12
-->
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateTime, formatInteger } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Head, usePage } from '@inertiajs/vue3';

defineProps({
    sources: { type: Array, required: true },
});

const t = useTranslations();
const page = usePage();

/** Třída štítku stavu podle závažnosti. */
const STATE_BADGES = {
    ok: 'badge--success',
    failing: 'badge--warning',
    stale: 'badge--danger',
    never: 'badge--danger',
};

/**
 * Datum a čas v zóně aplikace; chybějící jako pomlčka.
 *
 * @param {string|null} iso
 * @returns {string}
 */
function dateTime(iso) {
    return formatDateTime(iso, page.props.locale, page.props.timezone);
}

/**
 * Počet zpracovaných položek, s očekávaným počtem, když ho zdroj zná.
 *
 * @param {{processed: number|null, expected: number|null}} source
 * @returns {string}
 */
function processed(source) {
    if (source.expected === null || source.processed === null) {
        return formatInteger(source.processed, page.props.locale);
    }

    return t('sources.processed_of', {
        processed: formatInteger(source.processed, page.props.locale),
        expected: formatInteger(source.expected, page.props.locale),
    });
}
</script>

<template>
    <Head :title="t('sources.title')" />

    <AppLayout>
        <header class="page__header">
            <div>
                <h1 class="page__title">{{ t('sources.title') }}</h1>
                <p class="page__subtitle">{{ t('sources.intro') }}</p>
            </div>
        </header>

        <ul class="sources">
            <li
                v-for="source in sources"
                :key="source.source"
                class="panel sources__item"
                :class="{ 'sources__item--down': source.state === 'stale' || source.state === 'never' }"
            >
                <div class="sources__head">
                    <h2 class="sources__name">{{ t(`sources.names.${source.source}`) }}</h2>
                    <span class="badge" :class="STATE_BADGES[source.state]">{{ t(`sources.states.${source.state}`) }}</span>
                </div>
                <p v-if="!source.critical" class="sources__hint">{{ t('sources.optional') }}</p>

                <dl class="sources__facts">
                    <div class="sources__fact">
                        <dt class="sources__label">{{ t('sources.column_last_success') }}</dt>
                        <dd class="sources__value">{{ dateTime(source.lastSuccessAt) }}</dd>
                    </div>
                    <div class="sources__fact">
                        <dt class="sources__label">{{ t('sources.column_last_run') }}</dt>
                        <dd class="sources__value">{{ dateTime(source.lastRunAt) }}</dd>
                    </div>
                    <div class="sources__fact">
                        <dt class="sources__label">{{ t('sources.column_processed') }}</dt>
                        <dd class="sources__value">{{ processed(source) }}</dd>
                    </div>
                    <div class="sources__fact sources__fact--wide">
                        <dt class="sources__label">{{ t('sources.column_error') }}</dt>
                        <dd v-if="source.lastError" class="sources__error">
                            <span class="sources__value">{{ dateTime(source.lastFailureAt) }}</span>
                            {{ source.lastError }}
                            <span v-if="source.failuresInRow" class="sources__hint">
                                {{ t('sources.failures_in_row', { count: source.failuresInRow }) }}
                            </span>
                        </dd>
                        <dd v-else class="sources__value">{{ t('sources.no_error') }}</dd>
                    </div>
                </dl>
            </li>
        </ul>
    </AppLayout>
</template>
