<!--
    Karta sledované hry na přehledu — banner, název, druh, datum vydání,
    hodnocení z Metacritic (R32, R33) a hráčů na PS Store (R33) a nejlevnější
    nabídka podle filtrů. Nabídka PlayStation Store je i
    zvlášť (konzolová verze), obchody s klíči na vlastním řádku (R20, R21) —
    když je volba „Cena“ vybere jako nejlevnější, jsou hlavní cenou a oficiální
    nabídka jde na vedlejší řádek (R30).
    Hra hlídaná jen na jedné platformě má štítek a ceny jen té platformy;
    bez připojeného PS Store vyzve k připojení (R24). Nevydaná hra má odpočet
    do vydání (R41).

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import GameBadges from '@/Components/GameBadges.vue';
import { formatDate, formatHours, formatInteger, formatPrice, formatRating, releaseCountdown } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    game: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();

const best = computed(() => props.game.prices.best);
/** Hlavní cenou jsou obchody s klíči — volba „Cena“ je na ně vybrala (R30). */
const keyshopsRanked = computed(() => props.game.ranked?.keyshops === true);
/** Nejlevnější PS Store, když už není nejlevnější nabídkou celkem. */
const playstation = computed(() => {
    const offer = props.game.prices.playstation;

    return offer && offer.shopKey !== best.value?.shopKey ? offer : null;
});
</script>

<template>
    <Link :href="game.url" class="game-card">
        <!-- Obrázek je vedle názvu dekorativní — alt prázdný -->
        <img v-if="game.bannerUrl" :src="game.bannerUrl" alt="" class="game-card__image" loading="lazy" />
        <span v-else class="game-card__image game-card__image--empty" aria-hidden="true"></span>
        <span class="game-card__body">
            <span class="game-card__title">{{ game.title }}</span>
            <span class="game-card__meta">
                <GameBadges :type="game.type" :mature="game.isMature" />
                <span v-if="game.watchPlatform" class="platform" :class="`platform--${game.watchPlatform}`">
                    {{ t(`game.watch_platforms.${game.watchPlatform}`) }}
                </span>
                <span v-if="game.releaseDate">{{ formatDate(game.releaseDate, page.props.locale) }}</span>
                <span v-else>{{ t('game.unreleased') }}</span>
                <!-- Odpočet do vydání (R41) -->
                <span v-if="game.releaseInDays !== null" class="game-card__release">{{ releaseCountdown(game.releaseInDays, t) }}</span>
                <!-- Hodnocení kritiků z Metacritic (R32) -->
                <span
                    v-if="game.metascore"
                    class="game-card__score"
                    :class="`game-card__score--${game.metascore.level}`"
                    :title="t('dashboard.metascore_title')"
                    :aria-label="t('dashboard.metascore_label', { score: game.metascore.score })"
                >
                    {{ t('dashboard.metascore', { score: game.metascore.score }) }}
                </span>
                <!-- Hlavní příběh podle HowLongToBeat (R44) -->
                <span v-if="game.playtime" :title="t('dashboard.playtime_title')">
                    {{ t('dashboard.playtime', { hours: formatHours(game.playtime, page.props.locale) }) }}
                </span>
                <!-- Hodnocení hráčů na PS Store (R33) -->
                <span
                    v-if="game.playerRating"
                    class="game-card__stars"
                    :title="t('dashboard.player_rating_title', { count: formatInteger(game.playerRating.count, page.props.locale) })"
                >
                    {{ t('dashboard.player_rating', { rating: formatRating(game.playerRating.rating, page.props.locale) }) }}
                </span>
            </span>
            <span class="game-card__price">
                <template v-if="keyshopsRanked">
                    <span class="game-card__amount game-card__amount--keyshops">{{ formatPrice(game.ranked.price, page.props.locale) }}</span>
                    <span class="game-card__shop">{{ t('prices.keyshops') }}</span>
                </template>
                <template v-else-if="best">
                    <span class="game-card__amount">{{ formatPrice(best.price, page.props.locale) }}</span>
                    <span v-if="best.cut" class="game-card__cut">{{ t('prices.cut', { cut: best.cut }) }}</span>
                    <span class="game-card__shop">
                        <span v-if="best.platform === 'playstation'" class="platform platform--playstation">{{ best.drm || t('prices.platforms.playstation') }}</span>
                        {{ best.shop }}
                    </span>
                </template>
                <span v-else-if="game.needsPlayStation" class="game-card__connect">{{ t('dashboard.connect_playstation') }}</span>
                <span v-else class="game-card__shop">{{ t('prices.no_price') }}</span>
            </span>
            <span v-if="keyshopsRanked && best" class="game-card__official">
                {{ t('prices.official_short', { price: formatPrice(best.price, page.props.locale) }) }}
                <template v-if="best.cut"> · {{ t('prices.cut', { cut: best.cut }) }}</template>
            </span>
            <!-- Měsíční hra PS Plus Essential (R35) — u nejlevnější nabídky i u PS Store zvlášť -->
            <span v-if="best?.memberFree || playstation?.memberFree" class="game-card__plus">{{ t('prices.plus_free') }}</span>
            <span v-else-if="!keyshopsRanked && best?.member" class="game-card__plus">{{ t('prices.plus_price', { price: formatPrice(best.member.price, page.props.locale) }) }}</span>
            <span v-if="playstation" class="game-card__playstation">
                {{ t('prices.playstation_short', { price: formatPrice(playstation.price, page.props.locale) }) }}
                <template v-if="playstation.member"> · {{ t('prices.plus_price', { price: formatPrice(playstation.member.price, page.props.locale) }) }}</template>
            </span>
            <span v-if="game.prices.keyshops && !keyshopsRanked" class="game-card__keyshops">
                {{ t('prices.keyshops_short', { price: formatPrice(game.prices.keyshops.price, page.props.locale) }) }}
            </span>
            <!-- Cena za hodinu hlavního příběhu podle volby „Cena“ (R44) -->
            <span v-if="game.pricePerHour" class="game-card__per-hour" :title="t('dashboard.per_hour_title')">
                {{ t('dashboard.per_hour', { price: formatPrice(game.pricePerHour.price, page.props.locale) }) }}
            </span>
            <!-- Cílová cena pro upozornění (R26) — splněná svítí -->
            <span v-if="game.target" class="game-card__target" :class="{ 'game-card__target--reached': game.targetReached }">
                <template v-if="game.targetReached">{{ t('dashboard.target_reached') }} · </template>
                {{ t('dashboard.target', { price: formatPrice(game.target, page.props.locale) }) }}
            </span>
        </span>
    </Link>
</template>
