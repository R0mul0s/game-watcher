<!--
    „Kup, nebo počkej?“ na detailu hry (R42) — doporučení a čísla, ze kterých
    vychází: v kolika procentech dní za rok byla hra levněji, roční minimum,
    kdy byla naposledy u minima a nejbližší výprodej z kalendáře. Vše počítá
    server (SaleOutlook), komponenta jen skládá texty.

    @author Roman Hlaváček
    @created 2026-10-13
-->
<script setup>
import { formatDate, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    outlook: { type: Object, required: true },
});

const t = useTranslations();
const page = usePage();

/** Počet dní slovy („46 dní“). */
const days = (count) => t('outlook.days', { count });
/** Před kolika dny — „před“ je v textu („64 dny“). */
const daysAgo = (count) => t('outlook.days_ago', { count });
/** Název výprodeje z kalendáře. */
const eventName = (key) => t(`outlook.events.${key}`);
/** Cena v měně účtu. */
const price = (value) => formatPrice(value, page.props.locale);

/** Věta doporučení podle důvodu ze serveru. */
const reason = computed(() => {
    const { reason: key, cheaperShare: share, upcoming, nearLow } = props.outlook;

    switch (key) {
        case 'event':
            return t(upcoming.daysUntil === 1 ? 'outlook.reasons.event_tomorrow' : 'outlook.reasons.event', {
                event: eventName(upcoming.key),
                days: days(upcoming.daysUntil),
                price: price(upcoming.lastYear.price),
            });
        case 'pattern':
            return nearLow.daysAgo === null
                ? t('outlook.reasons.pattern_short', { share })
                : t('outlook.reasons.pattern', { share, ago: daysAgo(nearLow.daysAgo) });
        default:
            return t(`outlook.reasons.${key}`, { share });
    }
});

/** Kdy byla hra u minima — teď, nebo před kolika dny, a jak často. */
const nearLowText = computed(() => {
    const { nearLow } = props.outlook;
    const parts = [];
    if (nearLow.now) {
        parts.push(t('outlook.near_low_now'));
    } else if (nearLow.daysAgo !== null) {
        parts.push(t('outlook.near_low_ago', { ago: daysAgo(nearLow.daysAgo) }));
    }
    if (nearLow.intervalDays) {
        parts.push(t('outlook.near_low_interval', { days: nearLow.intervalDays }));
    }

    return parts.join(' · ');
});
</script>

<template>
    <section class="panel outlook">
        <h2 class="panel__title">{{ t('outlook.title') }}</h2>

        <p class="outlook__verdict">
            <span class="outlook__badge" :class="`outlook__badge--${outlook.verdict}`">{{ t(`outlook.verdicts.${outlook.verdict}`) }}</span>
            <span class="outlook__reason">{{ reason }}</span>
        </p>

        <dl class="facts">
            <div class="facts__item">
                <dt class="facts__label">{{ t('outlook.cheaper_share') }}</dt>
                <dd class="facts__value">{{ t('outlook.cheaper_share_value', { share: outlook.cheaperShare }) }}</dd>
            </div>
            <div class="facts__item">
                <dt class="facts__label">{{ t('outlook.year_low') }}</dt>
                <dd class="facts__value">
                    {{
                        t(outlook.yearLow.cut ? 'outlook.year_low_value' : 'outlook.year_low_value_regular', {
                            price: price(outlook.yearLow.price),
                            cut: outlook.yearLow.cut,
                            date: formatDate(outlook.yearLow.date, page.props.locale),
                        })
                    }}
                </dd>
            </div>
            <div v-if="nearLowText" class="facts__item">
                <dt class="facts__label">{{ t('outlook.near_low') }}</dt>
                <dd class="facts__value">{{ nearLowText }}</dd>
            </div>
            <div v-if="outlook.upcoming" class="facts__item">
                <dt class="facts__label">{{ t('outlook.upcoming') }}</dt>
                <dd class="facts__value">
                    {{
                        t('outlook.upcoming_value', {
                            event: eventName(outlook.upcoming.key),
                            start: formatDate(outlook.upcoming.start, page.props.locale),
                            end: formatDate(outlook.upcoming.end, page.props.locale),
                        })
                    }}
                    <template v-if="outlook.upcoming.lastYear">
                        · {{ t('outlook.upcoming_last_year', { price: price(outlook.upcoming.lastYear.price), cut: outlook.upcoming.lastYear.cut }) }}
                    </template>
                </dd>
            </div>
        </dl>

        <p class="outlook__note">{{ t('outlook.note', { since: formatDate(outlook.since, page.props.locale), percent: outlook.nearLowPercent }) }}</p>
    </section>
</template>
