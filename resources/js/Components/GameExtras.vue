<!--
    Hra mimo obchody — v Game Passu (R50) a v aktivních bundlech (R51) podle
    IsThereAnyDeal. Odkazy bundlů jsou partnerské odkazy ITAD (neupravovat).

    @author Roman Hlaváček
    @created 2026-10-13
-->
<script setup>
import { formatDateInZone, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { usePage } from '@inertiajs/vue3';

defineProps({
    // { leavesAt } nebo null, když hra v Game Passu není
    gamePass: { type: Object, default: null },
    bundles: { type: Array, required: true },
});

const t = useTranslations();
const page = usePage();
</script>

<template>
    <section v-if="gamePass || bundles.length" class="panel extras">
        <h2 class="panel__title">{{ t('extras.title') }}</h2>
        <p v-if="gamePass" class="extras__game-pass">
            <span class="badge badge--success">{{ t('extras.game_pass') }}</span>
            {{ t('extras.game_pass_text') }}
            <template v-if="gamePass.leavesAt">
                {{ t('extras.leaves', { date: formatDateInZone(gamePass.leavesAt, page.props.locale, page.props.timezone) }) }}
            </template>
        </p>
        <ul v-if="bundles.length" class="extras__list">
            <li v-for="bundle in bundles" :key="bundle.url" class="extras__bundle">
                <span class="extras__name">
                    <span class="extras__title">{{ bundle.title }}</span>
                    <span class="extras__meta">
                        {{ bundle.shop }}
                        <template v-if="bundle.expiresAt">
                            · {{ t('extras.until', { date: formatDateInZone(bundle.expiresAt, page.props.locale, page.props.timezone) }) }}
                        </template>
                    </span>
                </span>
                <span class="extras__price">{{ bundle.price ? formatPrice(bundle.price, page.props.locale) : t('extras.custom') }}</span>
                <a :href="bundle.url" class="button button--small" target="_blank" rel="noopener noreferrer sponsored">{{ t('extras.open') }}</a>
            </li>
        </ul>
        <p class="extras__source">{{ t('extras.source') }}</p>
    </section>
</template>
