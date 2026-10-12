/**
 * Konfigurace Vite — Vue 3 + SCSS.
 *
 * Dev server běží v kontejneru `app` a je z hostitele dostupný na portu 54732
 * (viz compose.yaml), proto poslouchá na všech rozhraních.
 *
 * @author Roman Hlaváček
 * @created 2026-10-11
 */
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

const DEV_SERVER_PORT = 5173;
const DEV_SERVER_PUBLIC_PORT = 54732;
/**
 * Mez varování o velikosti bloku [kB]. Graf ceny (ECharts, ~550 kB, gzip ~190 kB)
 * je samostatný blok načítaný jen na detailu hry (R28) — varování by jen šumělo.
 */
const CHUNK_SIZE_WARNING_KB = 600;

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        chunkSizeWarningLimit: CHUNK_SIZE_WARNING_KB,
    },
    server: {
        host: '0.0.0.0',
        port: DEV_SERVER_PORT,
        strictPort: true,
        origin: `http://localhost:${DEV_SERVER_PUBLIC_PORT}`,
        cors: true,
        hmr: {
            host: 'localhost',
            clientPort: DEV_SERVER_PUBLIC_PORT,
        },
        watch: {
            ignored: ['**/storage/framework/views/**', '**/vendor/**'],
        },
    },
});
