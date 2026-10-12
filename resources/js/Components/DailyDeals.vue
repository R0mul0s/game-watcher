<!--
    Slevy dne nad přehledem (etapa 6, R26) — sledované hry, které zlevnily od
    včerejška, od největšího poklesu. Data chystá DailyDeals na serveru.

    @author Roman Hlaváček
    @created 2026-10-12
-->
<script setup>
import { formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    deals: { type: Array, required: true },
});

const t = useTranslations();
const page = usePage();
</script>

<template>
    <section class="deals" :aria-label="t('deals.title')">
        <h2 class="deals__title">{{ t('deals.title') }}</h2>
        <ul class="deals__list">
            <li v-for="deal in deals" :key="deal.slug" class="deals__item">
                <Link :href="deal.url" class="deal">
                    <img v-if="deal.bannerUrl" :src="deal.bannerUrl" alt="" class="deal__image" loading="lazy" />
                    <span v-else class="deal__image deal__image--empty" aria-hidden="true"></span>
                    <span class="deal__body">
                        <span class="deal__title">{{ deal.title }}</span>
                        <span class="deal__prices">
                            <span class="deal__price">{{ formatPrice(deal.price, page.props.locale) }}</span>
                            <span class="deal__drop">{{ t('deals.drop', { drop: deal.drop }) }}</span>
                        </span>
                        <span class="deal__previous">{{ t('deals.previous', { price: formatPrice(deal.previous, page.props.locale) }) }}</span>
                    </span>
                </Link>
            </li>
        </ul>
    </section>
</template>
