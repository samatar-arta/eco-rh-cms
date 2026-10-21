import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                // Accent choisi par l'utilisateur (variables définies dans resources/css/app.css).
                accent: Object.fromEntries(
                    [50, 100, 200, 400, 500, 600, 700].map((n) => [n, `rgb(var(--accent-${n}) / <alpha-value>)`]),
                ),
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
