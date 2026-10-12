<!--
    Produkty PlayStation Store připojené ke hře odkazem (R10, R20) — seznam
    s odpojením a formulář pro vložení odkazu. Cena připojeného produktu je
    ve výpisu obchodů v panelu cen.

    @author Roman Hlaváček
    @created 2026-10-12
-->
<script setup>
import { useTranslations } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    listings: { type: Array, required: true },
    connectUrl: { type: String, required: true },
});

const t = useTranslations();
const form = useForm({ url: '' });

/**
 * Připojí produkt podle odkazu; po úspěchu pole vyprázdní.
 */
function connect() {
    form.post(props.connectUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset('url'),
    });
}

/**
 * Odpojí produkt.
 *
 * @param {{removeUrl: string}} listing
 */
function disconnect(listing) {
    router.delete(listing.removeUrl, { preserveScroll: true });
}
</script>

<template>
    <section class="panel listings">
        <h2 class="panel__title">
            <span class="platform platform--playstation">PS</span>
            {{ t('listings.title') }}
        </h2>

        <ul v-if="listings.length" class="listings__list">
            <li v-for="listing in listings" :key="listing.id" class="listings__item">
                <span class="listings__name">
                    {{ listing.name }}
                    <span v-if="listing.platforms" class="listings__platforms">{{ listing.platforms }}</span>
                </span>
                <a :href="listing.url" class="listings__link" target="_blank" rel="noopener noreferrer">{{ t('listings.open') }}</a>
                <button type="button" class="button button--ghost button--small" @click="disconnect(listing)">{{ t('listings.remove') }}</button>
            </li>
        </ul>

        <form class="listings__form" @submit.prevent="connect">
            <div class="field listings__field">
                <label class="field__label" for="listing-url">{{ t('listings.label') }}</label>
                <input
                    id="listing-url"
                    v-model="form.url"
                    class="field__input"
                    :class="{ 'field__input--invalid': form.errors.url }"
                    type="url"
                    :placeholder="t('listings.placeholder')"
                    autocomplete="off"
                    :aria-invalid="form.errors.url ? 'true' : undefined"
                    aria-describedby="listing-url-hint"
                />
                <p v-if="form.errors.url" class="field__error">{{ form.errors.url }}</p>
                <p id="listing-url-hint" class="field__hint">{{ t('listings.hint') }}</p>
            </div>
            <button type="submit" class="button button--small" :disabled="form.processing">{{ t('listings.submit') }}</button>
        </form>
    </section>
</template>
