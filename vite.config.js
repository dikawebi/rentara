import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/images/brand/rentara-logo-07a-primary.svg',
                'resources/images/brand/rentara-logo-07a-mark.svg',
            ],
            refresh: true,
        }),
    ],
});
