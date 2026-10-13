<!--
    Nastavení účtu — upozornění na ceny (etapa 6, R13, R26): e-mail, PS Plus
    a upozornění na tomto zařízení (Web Push); odkaz na stav zdrojů (R29). Odběr zařízení si stránka
    zapíná sama přes resources/js/lib/push.js.

    @author Roman Hlaváček
    @created 2026-10-12
-->
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTranslations } from '@/lib/i18n';
import { currentSubscription, isPushSupported, needsHomeScreen, sendTest, subscribe, unsubscribe } from '@/lib/push';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
    push: { type: Object, default: null },
    updateUrl: { type: String, required: true },
    sourcesUrl: { type: String, required: true },
});

const t = useTranslations();
const form = useForm({
    notify_email: props.settings.notifyEmail,
    notify_weekly: props.settings.notifyWeekly,
    has_ps_plus: props.settings.hasPsPlus,
    has_game_pass: props.settings.hasGamePass,
});

/** Umí prohlížeč upozornění (zjišťuje se až v prohlížeči). */
const supported = ref(false);
/** iPhone mimo aplikaci na ploše — upozornění tam nejsou. */
const homeScreenHint = ref(false);
/** Odběr tohoto zařízení; null = vypnuto. */
const subscription = ref(null);
const busy = ref(false);
/** Hláška k upozorněním na zařízení: { type: 'success'|'error', text }. */
const message = ref(null);

onMounted(async () => {
    supported.value = isPushSupported();
    homeScreenHint.value = needsHomeScreen();
    if (supported.value && props.push) {
        subscription.value = await currentSubscription();
    }
});

/**
 * Uloží volby upozornění.
 */
function save() {
    form.put(props.updateUrl, { preserveScroll: true });
}

/**
 * Provede akci se zařízením — tlačítka se do konce zablokují, chyba se ukáže.
 *
 * @param {() => Promise<void>} action
 */
async function withDevice(action) {
    busy.value = true;
    message.value = null;
    try {
        await action();
    } catch {
        message.value = { type: 'error', text: t('settings.push_error') };
    } finally {
        busy.value = false;
    }
}

/**
 * Zapne upozornění na tomto zařízení (musí jít z klepnutí — kvůli povolení).
 */
function enable() {
    withDevice(async () => {
        const created = await subscribe(props.push.publicKey, props.push.subscribeUrl);
        if (!created) {
            message.value = { type: 'error', text: t('settings.push_denied') };

            return;
        }
        subscription.value = created;
        router.reload({ only: ['settings'] });
    });
}

/**
 * Vypne upozornění na tomto zařízení.
 */
function disable() {
    withDevice(async () => {
        await unsubscribe(subscription.value, props.push.subscribeUrl);
        subscription.value = null;
        router.reload({ only: ['settings'] });
    });
}

/**
 * Pošle zkušební upozornění na toto zařízení.
 */
function test() {
    withDevice(async () => {
        const result = await sendTest(props.push.testUrl, subscription.value);
        message.value =
            result === 'sent'
                ? { type: 'success', text: t('settings.push_test_sent') }
                : { type: 'error', text: t('settings.push_test_failed') };
    });
}
</script>

<template>
    <Head :title="t('settings.title')" />

    <AppLayout>
        <header class="page__header">
            <h1 class="page__title">{{ t('settings.title') }}</h1>
        </header>

        <div class="settings">
            <form class="panel settings__panel" @submit.prevent="save">
                <h2 class="panel__title">{{ t('settings.alerts_title') }}</h2>
                <p class="panel__text">{{ t('settings.alerts_text') }}</p>

                <label class="switch">
                    <input v-model="form.notify_email" class="switch__input" type="checkbox" />
                    <span class="switch__track" aria-hidden="true"></span>
                    <span class="switch__label">{{ t('settings.email', { email: settings.email }) }}</span>
                </label>
                <!-- Týdenní souhrn jde jen e-mailem (R36) -->
                <label class="switch">
                    <input v-model="form.notify_weekly" class="switch__input" type="checkbox" :disabled="!form.notify_email" />
                    <span class="switch__track" aria-hidden="true"></span>
                    <span class="switch__label">{{ t('settings.weekly') }}</span>
                </label>
                <label class="switch">
                    <input v-model="form.has_ps_plus" class="switch__input" type="checkbox" />
                    <span class="switch__track" aria-hidden="true"></span>
                    <span class="switch__label">{{ t('settings.ps_plus') }}</span>
                </label>
                <p class="settings__hint">{{ t('settings.ps_plus_hint') }}</p>
                <!-- Game Pass (R50) -->
                <label class="switch">
                    <input v-model="form.has_game_pass" class="switch__input" type="checkbox" />
                    <span class="switch__track" aria-hidden="true"></span>
                    <span class="switch__label">{{ t('settings.game_pass') }}</span>
                </label>
                <p class="settings__hint">{{ t('settings.game_pass_hint') }}</p>

                <div class="settings__actions">
                    <button type="submit" class="button button--primary" :disabled="form.processing || !form.isDirty">
                        {{ t('settings.save') }}
                    </button>
                </div>
            </form>

            <section class="panel settings__panel">
                <h2 class="panel__title">{{ t('settings.push_title') }}</h2>
                <p class="panel__text">{{ t('settings.push_text') }}</p>

                <p v-if="!push" class="settings__hint">{{ t('settings.push_disabled_server') }}</p>
                <p v-else-if="homeScreenHint" class="settings__hint">{{ t('settings.push_ios') }}</p>
                <p v-else-if="!supported" class="settings__hint">{{ t('settings.push_unsupported') }}</p>
                <template v-else>
                    <p class="settings__status" :class="{ 'settings__status--on': subscription }">
                        {{ subscription ? t('settings.push_on') : t('settings.push_off') }}
                    </p>
                    <div class="settings__actions">
                        <button v-if="!subscription" type="button" class="button button--primary" :disabled="busy" @click="enable">
                            {{ t('settings.push_enable') }}
                        </button>
                        <template v-else>
                            <button type="button" class="button" :disabled="busy" @click="test">{{ t('settings.push_test') }}</button>
                            <button type="button" class="button button--ghost" :disabled="busy" @click="disable">{{ t('settings.push_disable') }}</button>
                        </template>
                    </div>
                </template>

                <p v-if="message" class="notice settings__message" :class="{ 'notice--error': message.type === 'error' }" role="status">
                    {{ message.text }}
                </p>
                <p v-if="settings.devices" class="settings__hint">{{ t('settings.devices', { count: settings.devices }) }}</p>
            </section>

            <section class="panel settings__panel">
                <h2 class="panel__title">{{ t('settings.sources_title') }}</h2>
                <p class="panel__text">{{ t('settings.sources_text') }}</p>
                <div class="settings__actions">
                    <Link :href="sourcesUrl" class="button">{{ t('settings.sources_link') }}</Link>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
