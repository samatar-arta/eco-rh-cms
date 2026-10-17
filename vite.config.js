import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                quietDeps: true, // 👈 Masque les avertissements venant de node_modules (Bootstrap)
                silenceDeprecations: ['import', 'global-builtin', 'if-function'] // 👈 Coupe les alertes de syntaxe
            }
        }
    }
});

