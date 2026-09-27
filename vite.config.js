import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Fontlar yerelden (public/build) sunulur; display: swap ile metin font gelmeden görünür.
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                    display: 'swap',
                }),
                bunny('Cinzel', {
                    weights: [500, 600, 700],
                    display: 'swap',
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
