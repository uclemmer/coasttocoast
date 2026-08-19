import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            /*
             * The design handoff specifies these three families and links them
             * from Google Fonts. Self-hosted through Bunny instead: same
             * families, same weights, but the fonts are downloaded at build
             * time and served from our own origin.
             *
             * That is not a cosmetic change. A Google Fonts <link> is a
             * third-party request on every page load of a public site, it
             * leaks visitor IPs to Google, and it is the kind of thing a
             * university's privacy office asks about. Self-hosting also means
             * the site renders correctly with no network to fonts.googleapis.com.
             *
             * Weights are exactly those the design uses — see the tokens table
             * in the handoff README. Caveat is the script accent; Source Sans 3
             * carries body copy and needs its italic.
             */
            fonts: [
                bunny('Montserrat', { weights: [500, 600, 700, 800] }),
                bunny('Caveat', { weights: [600, 700] }),
                bunny('Source Sans 3', { weights: [400, 600], styles: ['normal', 'italic'] }),
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
