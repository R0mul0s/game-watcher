<!--
    Přihlašovací stránka — jediná stránka dostupná bez přihlášení (R3).
    Registrace ani obnova hesla nejsou (R4).

    @author Roman Hlaváček
    @created 2026-10-11
-->
<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import { useTranslations } from '@/lib/i18n';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    loginUrl: { type: String, required: true },
});

const t = useTranslations();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

/**
 * Odešle přihlášení; heslo se po odeslání vždy smaže z formuláře.
 */
function submit() {
    form.post(props.loginUrl, {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head :title="t('auth.title')" />

    <div class="auth">
        <!-- Kulisa: obzor a mřížka podlahy (jen dekorace) -->
        <div class="auth__horizon" aria-hidden="true"></div>
        <div class="auth__grid" aria-hidden="true"></div>

        <main class="auth__main">
            <div class="auth__brand">
                <BrandLogo />
                <h1 class="auth__title">{{ t('app_name') }}</h1>
                <p class="auth__tagline">{{ t('tagline') }}</p>
            </div>

            <form class="panel auth__panel" novalidate @submit.prevent="submit">
                <h2 class="panel__title">{{ t('auth.title') }}</h2>

                <div class="field">
                    <label class="field__label" for="email">{{ t('auth.email') }}</label>
                    <input
                        id="email"
                        v-model="form.email"
                        class="field__input"
                        :class="{ 'field__input--invalid': form.errors.email }"
                        type="email"
                        name="email"
                        autocomplete="username"
                        required
                        autofocus
                        :aria-invalid="form.errors.email ? 'true' : undefined"
                        :aria-describedby="form.errors.email ? 'email-error' : undefined"
                    />
                    <p v-if="form.errors.email" id="email-error" class="field__error">{{ form.errors.email }}</p>
                </div>

                <div class="field">
                    <label class="field__label" for="password">{{ t('auth.password') }}</label>
                    <input
                        id="password"
                        v-model="form.password"
                        class="field__input"
                        :class="{ 'field__input--invalid': form.errors.password }"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                        :aria-invalid="form.errors.password ? 'true' : undefined"
                        :aria-describedby="form.errors.password ? 'password-error' : undefined"
                    />
                    <p v-if="form.errors.password" id="password-error" class="field__error">{{ form.errors.password }}</p>
                </div>

                <label class="checkbox">
                    <input v-model="form.remember" class="checkbox__input" type="checkbox" name="remember" />
                    <span class="checkbox__label">{{ t('auth.remember') }}</span>
                </label>

                <button type="submit" class="button button--primary button--block" :disabled="form.processing">
                    {{ t('auth.submit') }}
                </button>
            </form>
        </main>
    </div>
</template>
