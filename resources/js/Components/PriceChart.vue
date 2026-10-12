<!--
    Graf vývoje ceny na detailu hry (etapa 7, R28) — denní nejnižší cena:
    nejlevnější obchod pro PC a Steam z historie ITAD, obchody s klíči a PS
    Store z vlastní historie. Data chystá PriceChart na serveru (odložený prop),
    ceny v měně účtu dnešním kurzem. Řady hlídané platformy jsou zapnuté,
    ostatní jde zapnout v legendě.

    @author Roman Hlaváček
    @created 2026-10-12
-->
<script setup>
import '@/lib/echarts';
import { chartAnimation, chartColors } from '@/lib/chartTheme';
import { formatDate, formatDayMonth, formatMonth, formatPrice } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import VChart from 'vue-echarts';

const props = defineProps({
    /** { days, series: [{ key, name, platform, data }], target, currency, itadAvailable } */
    chart: { type: Object, required: true },
    /** Hlídaná platforma (pc, playstation); null = všude. */
    watchPlatform: { type: String, default: null },
});

const t = useTranslations();
const page = usePage();

/** Období grafu: klíč => počet dní (null = celá historie). */
const RANGES = { months: 90, year: 365, all: null };
const DEFAULT_RANGE = 'year';
const GRID = { left: 56, right: 16, top: 56, bottom: 28 };
const LINE_WIDTH = 2;
/** Klíč řady => barva z chartTheme; produkty PS Store mají klíč psn-<id>. */
const SERIES_COLORS = { official: 'official', steam: 'steam', keyshops: 'keyshops', keyshops_low: 'keyshops' };
/** Řady referenčních hodnot (historické minimum klíčů) — čárkovaně, tence, bez bodů. */
const REFERENCE_SERIES = ['keyshops_low'];
const THIN_LINE_WIDTH = 1;
const PLAYSTATION_COLOR = 'playstation';
const DAY_MS = 86400000;
/** Řada s méně body se kreslí i body — jinak by jeden den nebyl vidět (vlastní historie je zatím krátká). */
const SPARSE_POINTS = 30;
const SYMBOL_SIZE = 6;
/** Období, kde osa X ukazuje dny místo měsíců. */
const DAILY_LABEL_RANGES = ['months'];

const range = ref(DEFAULT_RANGE);
const colors = chartColors();

/** Index prvního dne zvoleného období. */
const firstIndex = computed(() => {
    const days = RANGES[range.value];
    if (days === null || props.chart.days.length === 0) {
        return 0;
    }
    const last = new Date(`${props.chart.days.at(-1)}T00:00:00Z`).getTime();
    const from = new Date(last - days * DAY_MS).toISOString().slice(0, 10);
    const index = props.chart.days.findIndex((day) => day >= from);

    return index === -1 ? 0 : index;
});

/** Klíče řad PS Store v pořadí — první plnou čarou, další čárkovaně. */
const playstationKeys = computed(() => props.chart.series.filter((series) => series.platform === 'playstation').map((series) => series.key));

/**
 * Název řady — PS Store podle produktu, ostatní z textů aplikace.
 *
 * @param {{key: string, name: string|null}} series
 * @returns {string}
 */
function seriesName(series) {
    return series.name ?? t(`chart.series.${series.key}`);
}

/**
 * Cena pro popisek osy a tooltip.
 *
 * @param {number|null} value
 * @returns {string}
 */
function price(value) {
    return value === null || value === undefined ? '—' : formatPrice({ amount: value, currency: props.chart.currency }, page.props.locale);
}

const option = computed(() => {
    const locale = page.props.locale;
    const days = props.chart.days.slice(firstIndex.value);

    return {
        animation: chartAnimation(),
        grid: GRID,
        legend: {
            // Jeden řádek s posunem — na mobilu by zalomená legenda překryla graf
            type: 'scroll',
            top: 0,
            pageTextStyle: { color: colors.muted },
            pageIconColor: colors.text,
            pageIconInactiveColor: colors.grid,
            textStyle: { color: colors.muted },
            icon: 'roundRect',
            // Řady jiné platformy, než se hra hlídá, jsou na začátku vypnuté
            selected: Object.fromEntries(
                props.chart.series.map((series) => [seriesName(series), !props.watchPlatform || series.platform === props.watchPlatform]),
            ),
        },
        tooltip: {
            trigger: 'axis',
            backgroundColor: colors.surface,
            borderColor: colors.grid,
            textStyle: { color: colors.text },
            valueFormatter: price,
        },
        xAxis: {
            type: 'category',
            data: days,
            boundaryGap: false,
            axisLabel: {
                color: colors.muted,
                hideOverlap: true,
                formatter: (day) => (DAILY_LABEL_RANGES.includes(range.value) ? formatDayMonth(day, locale) : formatMonth(day, locale)),
            },
            axisPointer: { label: { formatter: ({ value }) => formatDate(value, locale) } },
            axisLine: { lineStyle: { color: colors.grid } },
            axisTick: { show: false },
        },
        yAxis: {
            type: 'value',
            scale: true,
            axisLabel: { color: colors.muted, formatter: price },
            splitLine: { lineStyle: { color: colors.grid } },
        },
        series: props.chart.series.map((series, index) => {
            const color = colors[SERIES_COLORS[series.key] ?? PLAYSTATION_COLOR];
            // Další produkty PS Store (jiná edice, PS4) čárkovaně — barva je stejná
            const extraListing = series.platform === 'playstation' && playstationKeys.value.indexOf(series.key) > 0;
            const reference = REFERENCE_SERIES.includes(series.key);
            const data = series.data.slice(firstIndex.value);
            const points = data.filter((value) => value !== null).length;

            return {
                name: seriesName(series),
                type: 'line',
                // Cena platí od změny do další — schody, ne šikmé čáry
                step: reference ? false : 'end',
                showSymbol: !reference && points < SPARSE_POINTS,
                symbolSize: SYMBOL_SIZE,
                // Den bez záznamu (výpadek cronu) cenu nepřerušuje — platí poslední známá
                connectNulls: true,
                data,
                lineStyle: {
                    width: reference ? THIN_LINE_WIDTH : LINE_WIDTH,
                    color,
                    type: reference || extraListing ? 'dashed' : 'solid',
                },
                itemStyle: { color },
                // Cílová cena u první řady (osa je společná)
                markLine:
                    index === 0 && props.chart.target !== null
                        ? {
                              silent: true,
                              symbol: 'none',
                              lineStyle: { color: colors.target, type: 'dashed' },
                              label: { color: colors.target, formatter: () => t('chart.target', { price: price(props.chart.target) }) },
                              data: [{ yAxis: props.chart.target }],
                          }
                        : undefined,
            };
        }),
    };
});
</script>

<template>
    <section class="panel price-chart">
        <div class="price-chart__header">
            <h2 class="panel__title">{{ t('chart.title') }}</h2>
            <div v-if="chart.days.length" class="segmented" role="group" :aria-label="t('chart.range')">
                <button
                    v-for="(days, key) in RANGES"
                    :key="key"
                    type="button"
                    class="segmented__option"
                    :class="{ 'segmented__option--active': range === key }"
                    :aria-pressed="range === key"
                    @click="range = key"
                >
                    {{ t(`chart.ranges.${key}`) }}
                </button>
            </div>
        </div>

        <p v-if="!chart.series.length" class="panel__text">{{ t('chart.empty') }}</p>
        <VChart v-else class="price-chart__canvas" :option="option" autoresize />

        <p class="price-chart__note">
            {{ t('chart.note') }}
            <template v-if="chart.ownSince"> {{ t('chart.own_since', { date: formatDate(chart.ownSince, page.props.locale) }) }}</template>
            <template v-if="!chart.itadAvailable"> {{ t('chart.itad_unavailable') }}</template>
        </p>
    </section>
</template>
