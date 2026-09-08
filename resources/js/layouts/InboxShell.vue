<script setup lang="ts">
import ImpersonationBanner from '@/components/ImpersonationBanner.vue';
import { useAppearance } from '@/composables/useAppearance';
import { useInboxLocale } from '@/composables/useInboxLocale';
import type { Auth } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => (page.props.auth as Auth | undefined)?.user);
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);
const impersonating = computed(() => Boolean(page.props.impersonation?.active));
const { appearance, updateAppearance } = useAppearance();
const { locale, dir, setLocale } = useInboxLocale();

const nav = computed(() => {
    const ar = locale.value === 'ar';
    return {
        team: ar ? 'عالم المغامرة للترفيه' : 'Adventure World',
        groups: [
            {
                title: '',
                items: [
                    { label: ar ? 'Inbox' : 'Inbox', href: '/inbox' },
                    { label: ar ? 'جهات الاتصال' : 'Contacts', href: '/customers' },
                    { label: ar ? 'الشرائح' : 'Segments', href: '/customers' },
                    { label: ar ? 'الخطط' : 'Plans', href: '/packages' },
                ],
            },
            {
                title: ar ? 'التقارير' : 'REPORTS',
                items: [
                    { label: ar ? 'جهات الاتصال حسب الدولة' : 'Contacts by Country', href: '/customers' },
                    { label: ar ? 'المديونية' : 'Debt', href: '/payment-receipts' },
                    { label: ar ? 'التفاعل' : 'Engagement', href: '/reports/qa' },
                    { label: ar ? 'تقرير الجودة' : 'QA Report', href: '/reports/qa' },
                ],
            },
            {
                title: ar ? 'الإعدادات' : 'SETTINGS',
                items: [
                    { label: ar ? 'إعدادات الخطة' : 'Plan settings', href: '/packages' },
                    { label: ar ? 'الوكلاء' : 'Agents', href: '/users' },
                    { label: ar ? 'بوت واتساب' : 'WhatsApp AI', href: '/settings/whatsapp-ai' },
                    { label: ar ? 'إعدادات الجودة' : 'QA settings', href: '/settings/qa' },
                    { label: ar ? 'إعدادات النظام' : 'Node settings', href: '/orders' },
                ],
            },
            {
                title: '',
                items: [{ label: ar ? 'الفوترة' : 'Billing', href: '/payment-receipts' }],
            },
        ],
    };
});

const appearanceOptions = [
    { value: 'light' as const, Icon: Sun },
    { value: 'dark' as const, Icon: Moon },
    { value: 'system' as const, Icon: Monitor },
];

function isActive(href: string): boolean {
    const path = page.url.split('?')[0];
    if (href === '/inbox') {
        return path === '/inbox' || path.startsWith('/inbox/');
    }
    return path === href || path.startsWith(`${href}/`);
}
</script>

<template>
    <div class="inbox-shell flex h-dvh overflow-hidden bg-white dark:bg-zinc-950" :dir="dir">
        <aside class="flex w-60 shrink-0 flex-col gap-6 border-e border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
            <div>
                <Link href="/dashboard" class="text-xs font-semibold tracking-widest uppercase text-teal-700 dark:text-teal-400">Rentox</Link>
                <Link href="/dashboard" class="mt-1 block text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ nav.team }}</Link>
            </div>

            <nav class="flex-1 space-y-5 overflow-y-auto inbox-scroll">
                <div v-for="(group, gi) in nav.groups" :key="gi">
                    <div v-if="group.title" class="mb-1 px-3 text-xs tracking-wide text-zinc-500 uppercase">{{ group.title }}</div>
                    <Link
                        v-for="item in group.items"
                        :key="item.href + item.label"
                        :href="item.href"
                        class="block rounded-md px-3 py-2 text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                        :class="isActive(item.href) ? 'bg-zinc-100 font-medium dark:bg-zinc-800' : ''"
                    >
                        {{ item.label }}
                    </Link>
                </div>
            </nav>

            <div class="space-y-3">
                <div class="flex gap-1 rounded-md bg-zinc-100 p-1 dark:bg-zinc-800">
                    <button
                        v-for="opt in appearanceOptions"
                        :key="opt.value"
                        type="button"
                        class="flex flex-1 items-center justify-center rounded-md px-2 py-1.5 text-zinc-500"
                        :class="appearance === opt.value ? 'bg-white shadow-sm dark:bg-zinc-900' : 'hover:bg-zinc-50 dark:hover:bg-zinc-700'"
                        @click="updateAppearance(opt.value)"
                    >
                        <component :is="opt.Icon" class="size-3.5" />
                    </button>
                </div>
                <div class="flex gap-1 rounded-md bg-zinc-100 p-1 text-xs dark:bg-zinc-800">
                    <button
                        type="button"
                        class="flex-1 rounded-md px-2 py-1.5"
                        :class="locale === 'en' ? 'bg-white font-medium shadow-sm dark:bg-zinc-900' : 'text-zinc-500'"
                        @click="setLocale('en')"
                    >
                        EN
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-md px-2 py-1.5"
                        :class="locale === 'ar' ? 'bg-white font-medium shadow-sm dark:bg-zinc-900' : 'text-zinc-500'"
                        @click="setLocale('ar')"
                    >
                        AR
                    </button>
                </div>
                <div class="text-sm text-zinc-500">{{ user?.name }}</div>
                <Link
                    method="post"
                    href="/logout"
                    as="button"
                    class="text-sm text-teal-700 underline dark:text-teal-400"
                >
                    {{ locale === 'ar' ? 'تسجيل الخروج' : 'Logout' }}
                </Link>
            </div>
        </aside>

        <main class="relative flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
            <ImpersonationBanner v-if="impersonating" />
            <div v-if="flash?.success" class="shrink-0 bg-teal-50 px-4 py-2 text-xs text-teal-800 dark:bg-teal-950 dark:text-teal-200">
                {{ flash.success }}
            </div>
            <div v-if="flash?.error" class="shrink-0 bg-red-50 px-4 py-2 text-xs text-red-800 dark:bg-red-950 dark:text-red-200">
                {{ flash.error }}
            </div>
            <div class="relative min-h-0 flex-1 overflow-hidden">
                <div class="absolute inset-0">
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
