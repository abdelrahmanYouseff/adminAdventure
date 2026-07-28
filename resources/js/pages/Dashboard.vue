<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { MessageCircle, ArrowLeft, FileSpreadsheet, Smartphone, ArrowUpRight } from 'lucide-vue-next';
import { formatInteger } from '@/lib/formatNumber';
import { computed } from 'vue';

interface AppDownloadStats {
    ios: number;
    android: number;
    ios_today: number;
    android_today: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'لوحة التحكم',
        href: '/dashboard',
    },
];

const page = usePage();
const totalProducts = computed(() => (page.props.totalProducts as number | undefined) ?? 0);
const totalInvoices = computed(() => (page.props.totalInvoices as number | undefined) ?? 0);
const totalPackages = computed(() => (page.props.totalPackages as number | undefined) ?? 0);
const totalQuotations = computed(() => (page.props.totalQuotations as number | undefined) ?? 0);
const appDownloadStats = computed(() => (page.props.appDownloadStats as AppDownloadStats | undefined) ?? {
    ios: 0,
    android: 0,
    ios_today: 0,
    android_today: 0,
});

const stats = computed(() => [
    {
        label: 'إجمالي المنتجات',
        value: totalProducts.value,
        unit: 'منتج',
        href: '/products',
        hint: 'عرض المنتجات',
    },
    {
        label: 'إجمالي الفواتير',
        value: totalInvoices.value,
        unit: 'فاتورة',
        href: '/invoices',
        hint: 'عرض الفواتير',
    },
    {
        label: 'إجمالي الباقات',
        value: totalPackages.value,
        unit: 'باقة',
        href: '/packages',
        hint: 'عرض الباقات',
    },
    {
        label: 'عروض الأسعار',
        value: totalQuotations.value,
        unit: 'عرض',
        href: '/quotations',
        hint: 'عرض الأسعار',
    },
]);
</script>

<template>
    <Head title="عالم المغامرات - لوحة التحكم" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-4 overflow-x-hidden p-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:gap-6 sm:p-6">
            <div class="sm:hidden">
                <h1 class="text-lg font-bold text-gray-900 dark:text-white">لوحة التحكم</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">نظرة سريعة على أهم الأرقام</p>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                <Link
                    v-for="stat in stats"
                    :key="stat.label"
                    :href="stat.href"
                    class="group flex min-w-0 flex-col rounded-2xl border border-[#E0E0E0] bg-white p-5 transition hover:border-gray-300 hover:shadow-sm active:scale-[0.99] dark:border-neutral-700 dark:bg-neutral-900 dark:hover:border-neutral-600 sm:p-6"
                >
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-gray-400 dark:text-neutral-500 sm:text-xs">
                        {{ stat.label }}
                    </p>
                    <p class="mt-3 text-2xl font-extrabold tabular-nums tracking-tight text-gray-900 dark:text-white sm:text-[1.75rem]">
                        {{ formatInteger(stat.value) }}
                        <span class="ms-1 text-base font-bold text-gray-700 dark:text-neutral-300 sm:text-lg">{{ stat.unit }}</span>
                    </p>
                    <p class="mt-4 flex items-center gap-1.5 text-xs font-medium text-[#5B8A72] dark:text-teal-400/90">
                        <ArrowUpRight class="size-3.5 shrink-0 stroke-[2.25]" />
                        <span>{{ stat.hint }}</span>
                    </p>
                </Link>
            </div>

            <div class="min-w-0 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6 sm:shadow-lg">
                <div class="mb-4 flex flex-col gap-3 sm:mb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white sm:text-xl">
                            <Smartphone class="h-5 w-5" />
                            نقرات تحميل التطبيق
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            عدد الضغطات على روابط التحميل من الصفحة الرئيسية
                        </p>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">App Store (آيفون)</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900 dark:text-white">
                            {{ formatInteger(appDownloadStats.ios) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            اليوم: {{ formatInteger(appDownloadStats.ios_today) }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Google Play (أندرويد)</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900 dark:text-white">
                            {{ formatInteger(appDownloadStats.android) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            اليوم: {{ formatInteger(appDownloadStats.android_today) }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="min-w-0 flex-1 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6 sm:shadow-lg">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white sm:text-xl">نظرة عامة</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 sm:mt-0 sm:mb-6">
                    مرحباً بك في لوحة تحكم نظام عالم المغامرات.
                </p>

                <div class="mt-4 space-y-3 sm:mt-0">
                    <Link
                        href="/quotations/create"
                        class="flex items-center justify-between gap-3 rounded-2xl border border-purple-100 bg-purple-50/80 p-4 transition active:scale-[0.99] hover:border-purple-200 hover:bg-purple-50 dark:border-purple-900/40 dark:bg-purple-900/10 dark:hover:bg-purple-900/20 sm:gap-4 sm:p-5"
                    >
                        <div class="flex min-w-0 items-center gap-3 sm:gap-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-purple-100 dark:bg-purple-900/30 sm:h-12 sm:w-12">
                                <FileSpreadsheet class="h-5 w-5 text-purple-600 dark:text-purple-400 sm:h-6 sm:w-6" />
                            </div>
                            <div class="min-w-0 text-start">
                                <p class="font-bold text-gray-900 dark:text-white">إنشاء عرض سعر</p>
                                <p class="mt-0.5 line-clamp-2 text-xs text-gray-600 dark:text-gray-400 sm:text-sm">
                                    إعداد عرض سعر جديد للعميل وتصديره PDF
                                </p>
                            </div>
                        </div>
                        <ArrowLeft class="h-5 w-5 shrink-0 text-purple-600 dark:text-purple-400 rtl:rotate-180" />
                    </Link>

                    <Link
                        href="/settings/whatsapp"
                        class="flex items-center justify-between gap-3 rounded-2xl border border-green-100 bg-green-50/80 p-4 transition active:scale-[0.99] hover:border-green-200 hover:bg-green-50 dark:border-green-900/40 dark:bg-green-900/10 dark:hover:bg-green-900/20 sm:gap-4 sm:p-5"
                    >
                        <div class="flex min-w-0 items-center gap-3 sm:gap-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/30 sm:h-12 sm:w-12">
                                <MessageCircle class="h-5 w-5 text-green-600 dark:text-green-400 sm:h-6 sm:w-6" />
                            </div>
                            <div class="min-w-0 text-start">
                                <p class="font-bold text-gray-900 dark:text-white">إعدادات واتساب الطلبات</p>
                                <p class="mt-0.5 line-clamp-2 text-xs text-gray-600 dark:text-gray-400 sm:text-sm">
                                    إدارة الأرقام التي تستقبل رسالة تفاصيل الطلب عند الدفع
                                </p>
                            </div>
                        </div>
                        <ArrowLeft class="h-5 w-5 shrink-0 text-green-600 dark:text-green-400 rtl:rotate-180" />
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
