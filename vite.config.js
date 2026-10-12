import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Front-end build. `npm run build` compiles resources/scss/app.scss (with Bootstrap) and resources/js/app.js
// into public/build/, which layouts/app.blade.php loads via @vite. `npm run dev` rebuilds on every save.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            // Bootstrap 5.3 still uses @import / global functions internally.
            scss: { quietDeps: true, silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'] },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
