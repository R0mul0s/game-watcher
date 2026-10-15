<!--
    Centrum upozornění (R53) — záznamy po dnech, od nejnovějšího: upozornění na sledované
    hry, týdenní souhrny a výpadky zdrojů (ikona podle druhu). Nepřečtené jsou zvýrazněné
    a zůstanou nepřečtené, dokud je uživatel neotevře (otevření záznamu ho označí) — samotné
    zobrazení seznamu číslo u zvonku nemaže. Všechny najednou označí tlačítko „Označit vše
    jako přečtené“. Převzato z Agregátoru letáků.

    @author Roman Hlaváček
    @created 2026-10-15
-->
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatTimeInZone, formatWeekdayDay, localDayKey } from '@/lib/format';
import { useTranslations } from '@/lib/i18n';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    /** Záznamy od nejnovějšího [{ id, kind, title, text, createdAt, unread, url }] (NotificationPresenter). */
    notifications: { type: Array, required: true },
    readUrl: { type: String, required: true },
    dashboardUrl: { type: String, required: true },
    retentionDays: { type: Number, required: true },
});

/** Milisekund ve dni — pro „Včera“. */
const DAY_MS = 24 * 60 * 60 * 1000;

/** Ikony druhů záznamu (cesty SVG, viewBox 24 × 24, tah bez výplně); neznámý druh bez ikony. */
const KIND_ICONS = {
    // Cenovka — upozornění na sledovanou hru
    game_alert: ['M3 12V4h8l10 10-8 8L3 12z', 'M7.5 8.5h.01'],
    // Kalendář — týdenní souhrn slev
    weekly_digest: ['M4 6h16v14H4z', 'M4 10h16', 'M8 3v4M16 3v4'],
    // Výstražný trojúhelník — výpadek nebo obnova zdrojů cen
    sources: ['M12 3l10 18H2L12 3z', 'M12 10v5', 'M12 18h.01'],
};

const t = useTranslations();
const page = usePage();

/** ID nepřečtených záznamů — po označení přijdou ze serveru znovu (už bez nich). */
const unreadIds = computed(() => new Set(props.notifications.filter((item) => item.unread).map((item) => item.id)));

const markingAll = ref(false);

/** Označí všechny nepřečtené záznamy na stránce jako přečtené (zvonek i číslo na ikoně aplikace zmizí). */
function markAllRead() {
    router.post(
        props.readUrl,
        { ids: [...unreadIds.value] },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (markingAll.value = true),
            onFinish: () => (markingAll.value = false),
        },
    );
}

/**
 * Nadpis dne: Dnes, Včera, jinak den v týdnu s datem.
 *
 * @param {string} day Y-m-d
 * @returns {string}
 */
function dayLabel(day) {
    const now = Date.now();
    if (day === localDayKey(now, page.props.timezone)) {
        return t('notifications.today');
    }
    if (day === localDayKey(now - DAY_MS, page.props.timezone)) {
        return t('notifications.yesterday');
    }

    return formatWeekdayDay(day, page.props.locale);
}

/** Záznamy po dnech [{ day, label, items }]. */
const days = computed(() => {
    const groups = new Map();
    for (const item of props.notifications) {
        const day = localDayKey(item.createdAt, page.props.timezone);
        groups.set(day, [...(groups.get(day) ?? []), item]);
    }

    return [...groups].map(([day, items]) => ({ day, label: dayLabel(day), items }));
});
</script>

<template>
    <Head :title="t('notifications.title')" />

    <AppLayout>
        <header class="page__header">
            <div>
                <h1 class="page__title">{{ t('notifications.title') }}</h1>
                <p class="page__subtitle">{{ t('notifications.intro', { days: retentionDays }) }}</p>
            </div>
        </header>

        <!-- Přečtený je záznam až po otevření — všechny najednou tímhle -->
        <p v-if="unreadIds.size" class="notifications__actions">
            <button type="button" class="button button--ghost button--small" :disabled="markingAll" @click="markAllRead">
                {{ t('notifications.mark_all_read') }}
            </button>
        </p>

        <section v-if="!notifications.length" class="panel empty-state">
            <div class="empty-state__radar" aria-hidden="true"></div>
            <h2 class="empty-state__title">{{ t('notifications.empty_title') }}</h2>
            <p class="empty-state__text">{{ t('notifications.empty') }}</p>
            <Link :href="dashboardUrl" class="button">{{ t('notifications.empty_link') }}</Link>
        </section>

        <section v-for="group in days" :key="group.day" class="notifications__day">
            <h2 class="notifications__day-title">{{ group.label }}</h2>
            <ul class="panel notifications__list">
                <li v-for="item in group.items" :key="item.id" class="notification-item" :class="{ 'notification-item--unread': unreadIds.has(item.id) }">
                    <Link :href="item.url" class="notification-item__link">
                        <span class="notification-item__icon" :class="`notification-item__icon--${item.kind}`" aria-hidden="true">
                            <svg v-if="KIND_ICONS[item.kind]" viewBox="0 0 24 24">
                                <path v-for="path in KIND_ICONS[item.kind]" :key="path" :d="path" />
                            </svg>
                        </span>
                        <span class="notification-item__body">
                            <span class="notification-item__title">
                                {{ item.title }}
                                <span v-if="unreadIds.has(item.id)" class="badge badge--accent">{{ t('notifications.unread') }}</span>
                            </span>
                            <span class="notification-item__text">{{ item.text }}</span>
                        </span>
                        <time class="notification-item__time" :datetime="item.createdAt">{{ formatTimeInZone(item.createdAt, page.props.locale, page.props.timezone) }}</time>
                    </Link>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>
