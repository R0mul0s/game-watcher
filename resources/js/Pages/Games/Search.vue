<!--
    Přidání hry — hledání v katalogu IsThereAnyDeal podle názvu, Steam App ID
    nebo odkazu na Steam (etapa 2, R10). Hledá se až po odeslání formuláře
    (limit API, viz games.catalog).

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import GameBadges from '@/Components/GameBadges.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    query: { type: String, default: '' },
    searched: { type: Boolean, default: false },
    results: { type: Array, default: () => [] },
    unavailable: { type: Boolean, default: false },
    searchUrl: { type: String, required: true },
    watchUrl: { type: String, required: true },
});

const t = useTranslations();
const form = useForm({ q: props.query });

/** ITAD ID hry, která se právě přidává — tlačítko se do odpovědi zablokuje. */
const adding = ref(null);

/**
 * Odešle hledání — výsledek je v adrese, jde ho sdílet i vrátit se k němu.
 */
function search() {
    form.get(props.searchUrl, { preserveState: true });
}

/**
 * Přidá hru na seznam; server vrátí zpět na výsledky s hláškou.
 *
 * @param {string} itadId
 */
function watch(itadId) {
    adding.value = itadId;
    router.post(
        props.watchUrl,
        { itad_id: itadId },
        {
            preserveScroll: true,
            onFinish: () => {
                adding.value = null;
            },
        },
    );
}
</script>

<template>
    <Head :title="t('search.title')" />

    <AppLayout>
        <header class="page__header">
            <h1 class="page__title">{{ t('search.title') }}</h1>
        </header>

        <form class="panel search" role="search" @submit.prevent="search">
            <div class="field">
                <label class="field__label" for="q">{{ t('search.label') }}</label>
                <div class="search__row">
                    <input
                        id="q"
                        v-model="form.q"
                        class="field__input search__input"
                        :class="{ 'field__input--invalid': form.errors.q }"
                        type="search"
                        name="q"
                        :placeholder="t('search.placeholder')"
                        autocomplete="off"
                        autofocus
                        :aria-invalid="form.errors.q ? 'true' : undefined"
                        aria-describedby="q-hint"
                    />
                    <button type="submit" class="button button--primary" :disabled="form.processing">
                        {{ t('search.submit') }}
                    </button>
                </div>
                <p v-if="form.errors.q" class="field__error">{{ form.errors.q }}</p>
                <p id="q-hint" class="field__hint">{{ t('search.hint') }}</p>
            </div>
        </form>

        <p v-if="unavailable" class="notice notice--error" role="alert">{{ t('search.unavailable') }}</p>

        <section v-else-if="searched" class="search-results" :aria-label="t('search.results')">
            <p v-if="!results.length" class="search-results__empty">{{ t('search.no_results') }}</p>

            <ul v-else class="search-results__list">
                <li v-for="game in results" :key="game.itadId" class="search-result">
                    <img v-if="game.thumbnailUrl" :src="game.thumbnailUrl" alt="" class="search-result__image" loading="lazy" />
                    <span v-else class="search-result__image search-result__image--empty" aria-hidden="true"></span>
                    <span class="search-result__body">
                        <span class="search-result__title">{{ game.title }}</span>
                        <GameBadges :type="game.type" :mature="game.isMature" />
                    </span>
                    <span class="search-result__actions">
                        <Link v-if="game.detailUrl" :href="game.detailUrl" class="button button--ghost button--small">
                            {{ t('search.detail') }}
                        </Link>
                        <span v-if="game.watching" class="search-result__status">{{ t('search.watching') }}</span>
                        <button
                            v-else
                            type="button"
                            class="button button--small"
                            :disabled="adding !== null"
                            @click="watch(game.itadId)"
                        >
                            {{ t('search.watch') }}
                        </button>
                    </span>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>
