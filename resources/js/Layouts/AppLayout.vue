<!--
    Rozvržení stránek přihlášeného uživatele — tlumená kulisa s obzorem (R6),
    hlavička s navigací, zvonkem centra upozornění (R53) a odhlášením, obsah, návrat nahoru (R45).

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import AppBackdrop from '@/Components/AppBackdrop.vue';
import BrandLogo from '@/Components/BrandLogo.vue';
import CurrencySwitch from '@/Components/CurrencySwitch.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import PullToRefresh from '@/Components/PullToRefresh.vue';
import ScrollTopButton from '@/Components/ScrollTopButton.vue';
import { useTranslations } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/vue3';

const t = useTranslations();
const page = usePage();
</script>

<template>
    <AppBackdrop variant="subtle" />
    <PullToRefresh />
    <a href="#main" class="skip-link">{{ t('skip_to_content') }}</a>
    <header class="app-header">
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
                <NotificationBell />
                <CurrencySwitch />
                <span class="app-header__user-name" :title="t('auth.signed_in_as', { name: page.props.auth.name })">
                    {{ page.props.auth.name }}
                </span>
                <!-- Na telefonu jen ikona, ať se uživatel vejde na řádek s logem -->
                <Link
                    :href="page.props.auth.logoutUrl"
                    method="post"
                    as="button"
                    class="button button--ghost button--small app-header__logout"
                    :title="t('auth.logout')"
                >
                    <svg class="app-header__logout-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <path d="M16 17l5-5-5-5" />
                        <path d="M21 12H9" />
                    </svg>
                    <span class="app-header__logout-label">{{ t('auth.logout') }}</span>
                </Link>
            </div>
        </div>
    </header>

    <!-- tabindex -1: po odkazu „Přeskočit na obsah" dostane fokus i hlavní obsah -->
    <main id="main" class="page" tabindex="-1">
        <FlashMessage />
        <slot />
    </main>
    <ScrollTopButton />
</template>
