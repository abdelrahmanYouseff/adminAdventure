import '../../css/mobile-app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { Fragment, createApp, h, watch, defineAsyncComponent } from 'vue';
import { applyDocumentLocale, t, useI18n } from './i18n';

applyDocumentLocale();

const ImpersonationBanner = defineAsyncComponent(() => import('../components/ImpersonationBanner.vue'));

createInertiaApp({
    title: (title) => {
        const appName = t('app_name');
        return title ? `${title} - ${appName}` : appName;
    },
    resolve: (name) =>
        resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const showImpersonation = Boolean(
            (props.initialPage.props as { impersonation?: { active?: boolean } | null }).impersonation?.active,
        );

        const vueApp = createApp({
            render: () =>
                h(Fragment, null, [
                    showImpersonation ? h(ImpersonationBanner) : null,
                    h(App, props),
                ]),
        }).use(plugin);

        const { locale } = useI18n();
        watch(locale, (next) => applyDocumentLocale(next), { immediate: true });

        vueApp.mount(el);
    },
    progress: {
        color: '#38bdf8',
    },
});
