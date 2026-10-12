<!--
    Hodnocení hry na detailu — kritici (Steam, Metacritic, OpenCritic z ITAD;
    Metascore her mimo ITAD přímo z Metacritic) a hráči na PS Store (R33).
    Hra bez Metascore z ITAD má formulář pro odkaz na Metacritic — když
    stránka odvozená z názvu nesedí (Alan Wake 2 je alan-wake-ii).

    @author Roman Hlaváček
    @created 2026-10-12
-->
<script setup>
import { formatInteger, formatRating, RATING_DIGITS_FULL } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    reviews: { type: Array, required: true },
    playerRatings: { type: Array, default: () => [] },
    /** Odkaz na Metacritic — null u hry s Metascore z ITAD. */
    metacritic: { type: Object, default: null },
});

const t = useTranslations();
const page = usePage();
const form = useForm({ url: '' });

/**
 * Připojí stránku na Metacritic; po úspěchu pole vyprázdní.
 */
function link() {
    form.put(props.metacritic.linkUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset('url'),
    });
}
</script>

<template>
    <section v-if="reviews.length || playerRatings.length || metacritic" class="panel">
        <h2 class="panel__title">{{ t('game.reviews') }}</h2>

        <ul v-if="reviews.length || playerRatings.length" class="reviews">
            <li v-for="review in reviews" :key="review.source" class="reviews__item">
                <span class="reviews__score" :class="`reviews__score--${review.level}`">{{ review.score }}</span>
                <span class="reviews__source">
                    <a v-if="review.url" :href="review.url" target="_blank" rel="noopener noreferrer">{{ review.source }}</a>
                    <template v-else>{{ review.source }}</template>
                    <span v-if="review.count" class="reviews__count">
                        {{ t('game.reviews_count', { count: formatInteger(review.count, page.props.locale) }) }}
                    </span>
                </span>
            </li>
            <!-- Hráči na PS Store — hvězdičky 0–5, ne body 0–100 -->
            <li v-for="rating in playerRatings" :key="rating.name" class="reviews__item">
                <span class="reviews__score reviews__score--stars">★ {{ formatRating(rating.rating, page.props.locale, RATING_DIGITS_FULL) }}</span>
                <span class="reviews__source">
                    {{ t('game.player_rating', { name: rating.name }) }}
                    <span v-if="rating.count" class="reviews__count">
                        {{ t('game.player_rating_count', { count: formatInteger(rating.count, page.props.locale) }) }}
                    </span>
                </span>
            </li>
        </ul>

        <form v-if="metacritic" class="rating-link" @submit.prevent="link">
            <div class="field rating-link__field">
                <label class="field__label" for="metacritic-url">{{ t('game.metacritic_label') }}</label>
                <input
                    id="metacritic-url"
                    v-model="form.url"
                    class="field__input"
                    :class="{ 'field__input--invalid': form.errors.url }"
                    type="url"
                    :placeholder="t('game.metacritic_placeholder')"
                    autocomplete="off"
                    :aria-invalid="form.errors.url ? 'true' : undefined"
                    aria-describedby="metacritic-url-hint"
                />
                <p v-if="form.errors.url" class="field__error">{{ form.errors.url }}</p>
                <p id="metacritic-url-hint" class="field__hint">
                    <template v-if="metacritic.url">
                        {{ t('game.metacritic_linked') }}
                        <a :href="metacritic.url" target="_blank" rel="noopener noreferrer">{{ metacritic.url }}</a>
                    </template>
                    <template v-else-if="metacritic.checked">{{ t('game.metacritic_not_found') }}</template>
                    <template v-else>{{ t('game.metacritic_pending') }}</template>
                </p>
            </div>
            <button type="submit" class="button button--small" :disabled="form.processing">{{ t('game.metacritic_submit') }}</button>
        </form>
    </section>
</template>
