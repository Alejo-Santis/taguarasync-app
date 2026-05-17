import './bootstrap';
import '../css/app.css';
import 'sweetalert2/dist/sweetalert2.min.css';

import { createInertiaApp } from '@inertiajs/svelte';
import { mount } from 'svelte';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

createInertiaApp({
    title: (title) => (title ? `${title} - Taguara Sync` : 'Taguara Sync'),
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.svelte`,
        import.meta.glob('./Pages/**/*.svelte'),
    ),
    setup({ el, App, props }) {
        mount(App, { target: el, props });
    },
    progress: {
        color: '#146c43',
    },
});
