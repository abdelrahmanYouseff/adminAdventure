import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { Fragment, createApp, h } from 'vue';
import FloatingWhatsApp from './components/FloatingWhatsApp.vue';
import { ZiggyVue } from 'ziggy-js';
import type { Config } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';

const _envName = import.meta.env.VITE_APP_NAME || 'منصة عالم المغامرة';
const appName = _envName === 'Laravel' || _envName.includes('Laravel') ? 'منصة عالم المغامرة' : _envName;

createInertiaApp({
    title: (title) => (title && title !== appName ? `${title} - ${appName}` : (title || appName)),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const ziggy = props.initialPage.props.ziggy as Config & { location: string };

        createApp({
            render: () =>
                h(Fragment, null, [
                    h(App, props),
                    h(FloatingWhatsApp),
                ]),
        })
            .use(plugin)
            .use(ZiggyVue, {
                ...ziggy,
                location: new URL(ziggy.location),
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
