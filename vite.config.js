import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { bunny } from "laravel-vite-plugin/fonts";
import tailwindcss from "@tailwindcss/vite";
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/js/app.js",
                "resources/js/auth.js",
                "resources/js/chat.js",
            ],
            refresh: true,
            fonts: [
                bunny("Instrument Sans", {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'pwa-sw.js',
            registerType: 'autoUpdate',
            injectRegister: false,
            manifest: {
                name: 'Hydroponic Farm Management',
                short_name: 'Hydro Farm',
                description: 'Sistem manajemen farm hidroponik',
                lang: 'id',
                theme_color: '#f8f6f2',
                background_color: '#f8f6f2',
                display: 'standalone',
                start_url: '/',
                scope: '/',
                icons: [
                    { src: '/icons/icon-192x192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/icons/icon-512x512.png', sizes: '512x512', type: 'image/png' },
                    { src: '/icons/icon-maskable-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
            workbox: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2,woff}'],
            },
            devOptions: {
                enabled: false,
            },
        }),
    ],
    server: {
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
