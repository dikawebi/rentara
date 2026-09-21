import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
                display: ['Manrope', 'Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                rentara: {
                    navy: '#16324F', blue: '#2563EB', teal: '#0F766E',
                    surface: '#FFFFFF', background: '#F8FAFC', foreground: '#172033',
                    success: '#15803D', warning: '#B45309', danger: '#B91C1C',
                },
            },
        },
    },

    plugins: [forms],
};
