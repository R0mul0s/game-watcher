<!--
    Rozvržení stránek přihlášeného uživatele — tlumená kulisa s obzorem (R6),
    plovoucí hlavička s navigací a odhlášením (R39), obsah.

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import AppBackdrop from '@/Components/AppBackdrop.vue';
import BrandLogo from '@/Components/BrandLogo.vue';
import CurrencySwitch from '@/Components/CurrencySwitch.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import PullToRefresh from '@/Components/PullToRefresh.vue';
import { useTranslations } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const t = useTranslations();
const page = usePage();
const header = ref(null);

/** Hlídá výšku plovoucí hlavičky — mění se se šířkou okna (na telefonu tři řádky). */
let headerObserver = null;

// Výška hlavičky jako proměnná CSS — plovoucí tlačítko Zpět jede těsně pod ní (R39)
onMounted(() => {
    headerObserver = new ResizeObserver(([entry]) => {
        document.documentElement.style.setProperty('--app-header-height', `${Math.ceil(entry.target.getBoundingClientRect().height)}px`);
    });
    headerObserver.observe(header.value);
});
onBeforeUnmount(() => headerObserver?.disconnect());
</script>

<template>
    <AppBackdrop variant="subtle" />
    <PullToRefresh />
    <a href="#main" class="skip-link">{{ t('skip_to_content') }}</a>
    <header ref="header" class="app-header">
        <div class="app-header__inner">
            <Link :href="page.props.homeUrl" class="brand">
                <BrandLogo />
                <span class="brand__name">{{ t('app_name') }}</span>
            </Link>
            <nav v-if="page.props.navigation.length" class="app-header__nav" :aria-label="t('nav.label')">
                <Link
                    v-for="item in page.props.navigation"
                    :key="item.url"
                    :href="item.url"
                    class="app-header__link"
                    :class="{ 'app-header__link--active': item.active }"
                    :aria-current="item.active ? 'page' : undefined"
                >
                    {{ t(item.label) }}
                </Link>
            </nav>
            <div v-if="page.props.auth" class="app-header__user">
                <CurrencySwitch />
                <span class="app-header__user-name" :title="t('auth.signed_in_as', { name: page.props.auth.name })">
                    {{ page.props.auth.name }}
                </span>
                <Link :href="page.props.auth.logoutUrl" method="post" as="button" class="button button--ghost button--small">
                    {{ t('auth.logout') }}
                </Link>
            </div>
        </div>
    </header>

    <!-- tabindex -1: po odkazu „Přeskočit na obsah" dostane fokus i hlavní obsah -->
    <main id="main" class="page" tabindex="-1">
        <FlashMessage />
        <slot />
    </main>
</template>
