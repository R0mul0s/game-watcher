<!--
    Délka hraní na detailu hry (R44) — průměry z HowLongToBeat (hlavní příběh,
    s vedlejšími úkoly, na 100 %) a cena za hodinu hlavního příběhu. Hru, kterou
    hledání podle názvu nenašlo nebo našlo jinou, jde připojit odkazem.

    @author Roman Hlaváček
    @created 2026-10-13
-->
<script setup>
import { formatHours, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    playtime: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();
const form = useForm({ url: '' });

/** Délky v pořadí HLTB — jen ty, které HLTB má. */
const ROWS = ['main', 'extra', 'complete'];
const rows = computed(() => ROWS.filter((row) => props.playtime[row]));

/**
 * Připojí hru odkazem na HowLongToBeat.
 */
function link() {
    form.put(props.playtime.linkUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset('url'),
    });
}
</script>

<template>
    <section class="panel playtime">
        <h2 class="panel__title">{{ t('playtime.title') }}</h2>

        <dl v-if="rows.length" class="facts">
            <div v-for="row in rows" :key="row" class="facts__item">
                <dt class="facts__label">{{ t(`playtime.${row}`) }}</dt>
                <dd class="facts__value">{{ t('playtime.hours', { hours: formatHours(playtime[row], page.props.locale) }) }}</dd>
            </div>
            <div v-if="playtime.pricePerHour" class="facts__item">
                <dt class="facts__label">{{ t('playtime.per_hour') }}</dt>
                <dd class="facts__value playtime__per-hour">{{ t('playtime.per_hour_value', { price: formatPrice(playtime.pricePerHour.price, page.props.locale) }) }}</dd>
            </div>
        </dl>
        <p v-else class="panel__text">{{ playtime.checked ? t('playtime.not_found') : t('playtime.pending') }}</p>

        <form class="rating-link" @submit.prevent="link">
            <div class="field rating-link__field">
                <label class="field__label" for="hltb-url">{{ t('playtime.link_label') }}</label>
                <input
                    id="hltb-url"
                    v-model="form.url"
                    class="field__input"
                    :class="{ 'field__input--invalid': form.errors.url }"
                    type="url"
                    :placeholder="t('playtime.link_placeholder')"
                    autocomplete="off"
                    :aria-invalid="form.errors.url ? 'true' : undefined"
                    aria-describedby="hltb-url-hint"
                />
                <p v-if="form.errors.url" class="field__error">{{ form.errors.url }}</p>
                <p id="hltb-url-hint" class="field__hint">
                    <template v-if="playtime.url">
                        {{ t('playtime.linked') }}
                        <a :href="playtime.url" target="_blank" rel="noopener noreferrer">{{ playtime.url }}</a>
                    </template>
                    <template v-else>{{ t('playtime.link_hint') }}</template>
                </p>
            </div>
            <button type="submit" class="button button--small" :disabled="form.processing">{{ t('playtime.link_submit') }}</button>
        </form>
    </section>
</template>
