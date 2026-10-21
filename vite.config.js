// TODO: upgrade to Vite 6 — bump vite ^6.0, @vitejs/plugin-vue ^6.0, run npm update laravel-vite-plugin (needs ≥1.1.0); requires Node ≥20.19
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
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
});
