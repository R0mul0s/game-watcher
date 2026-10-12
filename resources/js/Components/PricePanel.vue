<!--
    Ceny hry na detailu — nabídky oficiálních obchodů od nejlevnější (ITAD),
    nejnižší cena obchodů s klíči (gg.deals) a historická minima (R9).
    Ceny přicházejí ze serveru už převedené do měny účtu (R14). Odkazy do
    obchodů jsou partnerské odkazy ITAD a gg.deals vyžaduje uvedení zdroje.
    U hry hlídané jen na jedné platformě (R24) se ostatní nabídky ztlumí
    a „nejlevněji“ je nejlevnější nabídka hlídané platformy.

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import { formatDateInZone, formatDateTime, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    prices: { type: Object, required: true },
    /** Hlídaná platforma (pc, playstation); null = všude. */
    focusPlatform: { type: String, default: null },
});

const t = useTranslations();
const page = usePage();

/** Stránky zdrojů pro povinné uvedení původu dat. */
const ITAD_URL = 'https://isthereanydeal.com';
const GGDEALS_URL = 'https://gg.deals';

/** Platforma klíčů z obchodů s klíči (gg.deals) — klíče pro PC. */
const KEYSHOPS_PLATFORM = 'pc';

/**
 * Nabídka mimo hlídanou platformu — ztlumí se.
 *
 * @param {string} platform
 */
function isMuted(platform) {
    return props.focusPlatform !== null && platform !== props.focusPlatform;
}

/** Nejlevnější nabídka hlídané platformy (nabídky jsou od nejlevnější). */
const bestKey = computed(() => props.prices.offers.find((offer) => !isMuted(offer.platform))?.shopKey ?? null);
</script>

<template>
    <section class="panel prices">
        <h2 class="panel__title">{{ t('prices.title') }}</h2>

        <p v-if="!prices.offers.length && !prices.keyshops" class="panel__text">{{ t('prices.none') }}</p>

        <a v-if="prices.keyshops" :href="prices.keyshops.url" class="prices__keyshops" :class="{ 'prices__keyshops--muted': isMuted(KEYSHOPS_PLATFORM) }" target="_blank" rel="noopener noreferrer">
            <span class="prices__keyshops-label">
                {{ t('prices.keyshops') }}
                <span class="prices__hint">{{ t('prices.keyshops_hint') }}</span>
            </span>
            <span class="prices__amount prices__amount--keyshops">{{ formatPrice(prices.keyshops.price, page.props.locale) }}</span>
        </a>

        <ol v-if="prices.offers.length" class="offers">
            <li
                v-for="offer in prices.offers"
                :key="offer.shopKey"
                class="offer"
                :class="{ 'offer--best': offer.shopKey === bestKey, 'offer--muted': isMuted(offer.platform), [`offer--${offer.platform}`]: true }"
            >
                <span class="offer__shop">
                    <span class="offer__name">
                        <span v-if="offer.platform === 'playstation'" class="platform platform--playstation">{{ offer.drm || t('prices.platforms.playstation') }}</span>
                        {{ offer.shop }}
                    </span>
                    <span v-if="offer.edition" class="offer__edition">{{ offer.edition }}</span>
                    <span class="offer__details">
                        <span v-if="offer.drm && offer.platform !== 'playstation'">{{ t('prices.drm', { drm: offer.drm }) }}</span>
                        <span v-if="offer.expiresAt">{{ t('prices.until', { date: formatDateInZone(offer.expiresAt, page.props.locale, page.props.timezone) }) }}</span>
                        <span v-if="offer.original">{{ t('prices.original', { price: formatPrice(offer.original, page.props.locale) }) }}</span>
                    </span>
                </span>
                <span class="offer__price">
                    <span class="prices__amount">{{ formatPrice(offer.price, page.props.locale) }}</span>
                    <span v-if="offer.cut" class="offer__cut">{{ t('prices.cut', { cut: offer.cut }) }}</span>
                    <span v-if="offer.cut && offer.regular" class="offer__regular">
                        {{ t('prices.regular', { price: formatPrice(offer.regular, page.props.locale) }) }}
                    </span>
                    <span v-if="offer.member" class="offer__plus">{{ t('prices.plus_price', { price: formatPrice(offer.member.price, page.props.locale) }) }}</span>
                    <span v-if="offer.memberIncluded" class="offer__plus">{{ t('prices.plus_catalog') }}</span>
                </span>
                <a v-if="offer.url" :href="offer.url" class="button button--small offer__link" target="_blank" rel="noopener noreferrer sponsored">
                    {{ t('prices.open_shop') }}
                </a>
            </li>
        </ol>

        <div v-if="prices.lows.official || prices.lows.keyshops" class="prices__lows">
            <h3 class="prices__subtitle">{{ t('prices.lows') }}</h3>
            <dl class="facts facts--wide">
                <div v-if="prices.lows.official" class="facts__item">
                    <dt class="facts__label">{{ t('prices.low_official') }}</dt>
                    <dd class="facts__value">{{ formatPrice(prices.lows.official.price, page.props.locale) }}</dd>
                </div>
                <div v-if="prices.lows.keyshops" class="facts__item">
                    <dt class="facts__label">{{ t('prices.low_keyshops') }}</dt>
                    <dd class="facts__value">{{ formatPrice(prices.lows.keyshops.price, page.props.locale) }}</dd>
                </div>
            </dl>
        </div>

        <p class="prices__footer">
            <span v-if="prices.fetchedAt">
                {{ t('prices.updated', { time: formatDateTime(prices.fetchedAt, page.props.locale, page.props.timezone) }) }} ·
            </span>
            <!-- Uvedení zdrojů — gg.deals ho u svých dat vyžaduje -->
            {{ t('prices.sources') }}
            <a :href="ITAD_URL" target="_blank" rel="noopener noreferrer">IsThereAnyDeal</a>,
            <a :href="GGDEALS_URL" target="_blank" rel="noopener noreferrer">gg.deals</a>
        </p>
    </section>
</template>
