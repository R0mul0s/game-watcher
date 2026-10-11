/**
 * Vstupní bod frontendu — Inertia + Vue 3.
 *
 * @author Roman Hlaváček
 * @created 2026-10-11
 */
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';

// Písma retrofuturistického vzhledu (R6) — z vlastní domény přes Vite, CSP cizí
// domény nepovoluje. Všechna mají latin-ext kvůli české diakritice.
import '@fontsource-variable/oxanium';
import '@fontsource/chakra-petch/400.css';
import '@fontsource/chakra-petch/600.css';
import '@fontsource-variable/jetbrains-mono';

/** Název aplikace ze šablony (meta application-name) — text z lang/cs/app.php. */
const APP_NAME = document.querySelector('meta[name="application-name"]')?.content ?? '';

createInertiaApp({
    // „Přehled · Game Watcher"; stránka bez titulku dostane jen název aplikace
    title: (title) => (title && APP_NAME ? `${title} · ${APP_NAME}` : title || APP_NAME),
    // Stránky se načítají líně — každá má vlastní chunk.
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');

        return pages[`./Pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
