import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { Fragment, createApp, h } from 'vue';
import FloatingWhatsApp from './components/FloatingWhatsApp.vue';
import { ZiggyVue } from 'ziggy-js';
import type { Config } from 'ziggy-js';

declare global {
    interface Window {
        Ziggy?: Config & { url?: string };
    }
}

import { initializeTheme } from './composables/useAppearance';

const _envName = import.meta.env.VITE_APP_NAME || 'منصة عالم المغامرة';
const appName = _envName === 'Laravel' || _envName.includes('Laravel') ? 'منصة عالم المغامرة' : _envName;

createInertiaApp({
    title: (title) => (title && title !== appName ? `${title} - ${appName}` : (title || appName)),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const ziggyLocation = (props.initialPage.props.ziggy as { location: string }).location;

        createApp({
            render: () =>
                h(Fragment, null, [
                    h(App, props),
                    h(FloatingWhatsApp),
                ]),
        })
            .use(plugin)
            .use(ZiggyVue, {
                ...(window.Ziggy ?? {}),
                location: new URL(ziggyLocation),
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
