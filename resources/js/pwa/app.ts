import '../../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h, watch } from 'vue';
import { route as ziggyRoute, ZiggyVue } from 'ziggy-js';
import type { Config } from 'ziggy-js';
import { Ziggy as fallbackZiggy } from '../ziggy';
import { applyDocumentLocale, t, useI18n } from './i18n';

declare global {
    interface Window {
        Ziggy?: Config;
        route: typeof ziggyRoute;
    }
}

applyDocumentLocale();

function buildZiggyConfig(location: string): Config & { location: URL } {
    const routes = (window.Ziggy ?? fallbackZiggy) as Config;

    return {
        ...routes,
        url: window.location.origin,
        location: new URL(location, window.location.origin),
    };
}

createInertiaApp({
    title: (title) => {
        const appName = t('app_name');
        return title ? `${title} - ${appName}` : appName;
    },
    resolve: (name) =>
        resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const ziggyLocation = (props.initialPage.props.ziggy as { location: string }).location;
        const ziggyConfig = buildZiggyConfig(ziggyLocation);

        window.route = ((name?: string, params?: unknown, absolute?: boolean) =>
            ziggyRoute(name as never, params as never, absolute, ziggyConfig)) as typeof ziggyRoute;

        const vueApp = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, ziggyConfig);

        const { locale } = useI18n();
        watch(locale, (next) => applyDocumentLocale(next), { immediate: true });

        vueApp.mount(el);
    },
    progress: {
        color: '#38bdf8',
    },
});
