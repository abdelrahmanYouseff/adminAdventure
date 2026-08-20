<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowUpRight,
    FileSpreadsheet,
    FileText,
    Layers,
    MessageCircle,
    Package,
    RefreshCw,
    Settings,
    ShoppingBag,
    ShoppingCart,
    Smartphone,
    Sparkles,
    TrendingDown,
    TrendingUp,
    Users,
} from 'lucide-vue-next';
import { formatInteger } from '@/lib/formatNumber';
import { computed } from 'vue';

interface SparkPoint {
    label: string;
    count: number;
}

interface AppDownloadStats {
    ios: number;
    android: number;
    ios_today: number;
    android_today: number;
    ios_change?: number;
    android_change?: number;
}

interface AlertItem {
    title: string;
    time: string;
    tone: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
];

const page = usePage();

const userName = computed(() => (page.props.userName as string | undefined) ?? 'مستخدم');
const updatedAt = computed(() => (page.props.updatedAt as string | undefined) ?? '');
const totalProducts = computed(() => (page.props.totalProducts as number | undefined) ?? 0);
const totalInvoices = computed(() => (page.props.totalInvoices as number | undefined) ?? 0);
const totalPackages = computed(() => (page.props.totalPackages as number | undefined) ?? 0);
const totalQuotations = computed(() => (page.props.totalQuotations as number | undefined) ?? 0);
const totalOrders = computed(() => (page.props.totalOrders as number | undefined) ?? 0);

const statChanges = computed(() => (page.props.statChanges as Record<string, number> | undefined) ?? {
    quotations: 0,
    packages: 0,
    invoices: 0,
    products: 0,
});

const sparklines = computed(() => (page.props.sparklines as Record<string, SparkPoint[]> | undefined) ?? {
    quotations: [],
    packages: [],
    invoices: [],
    products: [],
});

const performanceSeries = computed(() => (page.props.performanceSeries as {
    labels: string[];
    values: number[];
} | undefined) ?? { labels: [], values: [] });

const alerts = computed(() => (page.props.alerts as AlertItem[] | undefined) ?? []);

const appDownloadStats = computed(() => (page.props.appDownloadStats as AppDownloadStats | undefined) ?? {
    ios: 0,
    android: 0,
    ios_today: 0,
    android_today: 0,
    ios_change: 0,
    android_change: 0,
});

const stats = computed(() => [
    {
        key: 'quotations',
        label: 'عروض الأسعار',
        value: totalQuotations.value,
        change: statChanges.value.quotations ?? 0,
        series: sparklines.value.quotations ?? [],
        href: '/quotations',
        icon: FileText,
        iconWrap: 'bg-sky-100 text-sky-600',
        spark: '#0ea5e9',
    },
    {
        key: 'packages',
        label: 'إجمالي الباقات',
        value: totalPackages.value,
        change: statChanges.value.packages ?? 0,
        series: sparklines.value.packages ?? [],
        href: '/packages',
        icon: Package,
        iconWrap: 'bg-orange-100 text-orange-600',
        spark: '#f97316',
    },
    {
        key: 'invoices',
        label: 'إجمالي الفواتير',
        value: totalInvoices.value,
        change: statChanges.value.invoices ?? 0,
        series: sparklines.value.invoices ?? [],
        href: '/invoices',
        icon: Layers,
        iconWrap: 'bg-emerald-100 text-emerald-600',
        spark: '#10b981',
    },
    {
        key: 'products',
        label: 'إجمالي المنتجات',
        value: totalProducts.value,
        change: statChanges.value.products ?? 0,
        series: sparklines.value.products ?? [],
        href: '/products',
        icon: ShoppingBag,
        iconWrap: 'bg-violet-100 text-violet-600',
        spark: '#8b5cf6',
    },
]);

const quickLinks = computed(() => [
    {
        label: 'إجمالي الطلبات',
        hint: formatInteger(totalOrders.value) + ' طلب',
        href: '/orders',
        icon: ShoppingCart,
        tone: 'bg-violet-100 text-violet-600',
    },
    {
        label: 'قائمة العملاء',
        hint: 'عرض وإدارة العملاء',
        href: '/customers',
        icon: Users,
        tone: 'bg-sky-100 text-sky-600',
    },
    {
        label: 'تقارير الأداء',
        hint: 'مبيعات وتحليلات',
        href: '/reports',
        icon: FileSpreadsheet,
        tone: 'bg-amber-100 text-amber-600',
    },
    {
        label: 'إعدادات النظام',
        hint: 'الملف الشخصي والمظهر',
        href: '/settings/profile',
        icon: Settings,
        tone: 'bg-rose-100 text-rose-600',
    },
]);

function sparkPath(series: SparkPoint[], width = 88, height = 36): string {
    const values = series.map((p) => p.count);
    if (values.length < 2) {
        return `M0 ${height / 2} L${width} ${height / 2}`;
    }
    const max = Math.max(...values, 1);
    const min = Math.min(...values, 0);
    const range = Math.max(max - min, 1);
    const step = width / (values.length - 1);

    return values
        .map((v, i) => {
            const x = i * step;
            const y = height - ((v - min) / range) * (height - 4) - 2;
            return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)} ${y.toFixed(1)}`;
        })
        .join(' ');
}

function areaPath(values: number[], width = 320, height = 140): string {
    if (values.length < 2) {
        return '';
    }
    const max = Math.max(...values, 1);
    const min = Math.min(...values, 0);
    const range = Math.max(max - min, 1);
    const step = width / (values.length - 1);
    const points = values.map((v, i) => {
        const x = i * step;
        const y = height - ((v - min) / range) * (height - 16) - 8;
        return [x, y] as const;
    });
    const line = points.map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x.toFixed(1)} ${y.toFixed(1)}`).join(' ');
    return `${line} L${width} ${height} L0 ${height} Z`;
}

function linePath(values: number[], width = 320, height = 140): string {
    if (values.length < 2) {
        return '';
    }
    const max = Math.max(...values, 1);
    const min = Math.min(...values, 0);
    const range = Math.max(max - min, 1);
    const step = width / (values.length - 1);

    return values
        .map((v, i) => {
            const x = i * step;
            const y = height - ((v - min) / range) * (height - 16) - 8;
            return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)} ${y.toFixed(1)}`;
        })
        .join(' ');
}

function changeLabel(change: number): string {
    const abs = Math.abs(change);
    if (change > 0) return `+${abs}%`;
    if (change < 0) return `-${abs}%`;
    return '0%';
}

function alertDot(tone: string): string {
    const map: Record<string, string> = {
        blue: 'bg-sky-500',
        amber: 'bg-amber-500',
        emerald: 'bg-emerald-500',
        violet: 'bg-violet-500',
    };
    return map[tone] || 'bg-slate-400';
}

const chartWidth = 320;
const chartHeight = 140;
</script>

<template>
    <Head title="عالم المغامرة - لوحة التحكم" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex min-w-0 flex-1 flex-col gap-5 overflow-x-hidden bg-[#F7F8FC] p-3 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:gap-6 sm:p-6 dark:bg-neutral-950">
            <!-- Welcome -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-3xl">
                        مرحباً بعودتك، {{ userName }} 👋
                    </h1>
                    <p class="mt-1.5 text-sm text-slate-500 dark:text-neutral-400">
                        نظرة سريعة على أداء النظام اليوم
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-if="updatedAt"
                        class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-slate-500 shadow-sm ring-1 ring-slate-200/80 dark:bg-neutral-900 dark:text-neutral-400 dark:ring-neutral-700"
                        dir="ltr"
                    >
                        <RefreshCw class="size-3.5" />
                        آخر تحديث: {{ updatedAt }}
                    </span>
                    <Link
                        href="/settings/appearance"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-violet-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700"
                    >
                        <Sparkles class="size-4" />
                        تخصيص
                    </Link>
                </div>
            </div>

            <!-- Stat cards -->
            <div class="grid grid-cols-2 gap-3 xl:grid-cols-4 sm:gap-4">
                <Link
                    v-for="stat in stats"
                    :key="stat.key"
                    :href="stat.href"
                    class="group relative overflow-hidden rounded-2xl border border-white bg-white p-4 shadow-[0_8px_30px_rgba(15,23,42,0.04)] transition hover:-translate-y-0.5 hover:shadow-[0_12px_34px_rgba(15,23,42,0.08)] dark:border-neutral-800 dark:bg-neutral-900 sm:p-5"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div
                            class="flex size-11 items-center justify-center rounded-2xl"
                            :class="stat.iconWrap"
                        >
                            <component :is="stat.icon" class="size-5" />
                        </div>
                        <svg
                            class="mt-1 opacity-80"
                            :width="88"
                            :height="36"
                            viewBox="0 0 88 36"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                :d="sparkPath(stat.series)"
                                :stroke="stat.spark"
                                stroke-width="2.25"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </div>
                    <p class="mt-4 text-sm font-medium text-slate-500 dark:text-neutral-400">
                        {{ stat.label }}
                    </p>
                    <p class="mt-1 text-3xl font-extrabold tabular-nums tracking-tight text-slate-900 dark:text-white">
                        {{ formatInteger(stat.value) }}
                    </p>
                    <p
                        class="mt-2 inline-flex items-center gap-1 text-xs font-semibold"
                        :class="stat.change >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                    >
                        <TrendingUp v-if="stat.change >= 0" class="size-3.5" />
                        <TrendingDown v-else class="size-3.5" />
                        {{ changeLabel(stat.change) }}
                        <span class="font-medium text-slate-400">مقارنة بالأمس</span>
                    </p>
                </Link>
            </div>

            <!-- App downloads banner -->
            <section class="relative overflow-hidden rounded-3xl border border-sky-100 bg-gradient-to-l from-sky-50 via-blue-50 to-indigo-50 p-5 shadow-sm dark:border-sky-900/40 dark:from-sky-950/40 dark:via-blue-950/30 dark:to-indigo-950/40 sm:p-7">
                <div class="mb-4 flex items-center gap-2">
                    <div class="flex size-10 items-center justify-center rounded-2xl bg-sky-100 text-sky-600 dark:bg-sky-900/50 dark:text-sky-300">
                        <Smartphone class="size-5" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">نقرات تحميل التطبيق</h2>
                        <p class="text-sm text-slate-500 dark:text-neutral-400">ضغطات روابط التحميل من الصفحة الرئيسية</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-white/80 bg-white/80 p-4 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                        <p class="text-sm font-medium text-slate-500">Google Play</p>
                        <p class="mt-1 text-3xl font-extrabold tabular-nums text-slate-900 dark:text-white">
                            {{ formatInteger(appDownloadStats.android_today) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            اليوم · الإجمالي {{ formatInteger(appDownloadStats.android) }}
                            ·
                            <span :class="(appDownloadStats.android_change ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                {{ changeLabel(appDownloadStats.android_change ?? 0) }}
                            </span>
                        </p>
                    </div>
                    <div class="rounded-2xl border border-white/80 bg-white/80 p-4 backdrop-blur dark:border-neutral-700 dark:bg-neutral-900/70">
                        <p class="text-sm font-medium text-slate-500">App Store</p>
                        <p class="mt-1 text-3xl font-extrabold tabular-nums text-slate-900 dark:text-white">
                            {{ formatInteger(appDownloadStats.ios_today) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            اليوم · الإجمالي {{ formatInteger(appDownloadStats.ios) }}
                            ·
                            <span :class="(appDownloadStats.ios_change ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                {{ changeLabel(appDownloadStats.ios_change ?? 0) }}
                            </span>
                        </p>
                    </div>
                </div>
            </section>

            <!-- Bottom widgets -->
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3 sm:gap-5">
                <!-- Overview actions -->
                <section class="rounded-3xl border border-white bg-white p-5 shadow-[0_8px_30px_rgba(15,23,42,0.04)] dark:border-neutral-800 dark:bg-neutral-900 sm:p-6">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">نظرة عامة</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-neutral-400">اختصارات سريعة للعمل اليومي</p>

                    <div class="mt-5 space-y-3">
                        <Link
                            href="/quotations/create"
                            class="flex items-center justify-between gap-3 rounded-2xl border border-violet-100 bg-violet-50/80 p-4 transition hover:border-violet-200 hover:bg-violet-50 dark:border-violet-900/40 dark:bg-violet-950/30"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-violet-100 text-violet-600 dark:bg-violet-900/50 dark:text-violet-300">
                                    <FileSpreadsheet class="size-5" />
                                </div>
                                <div class="min-w-0 text-start">
                                    <p class="font-bold text-slate-900 dark:text-white">إنشاء عرض سعر</p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-neutral-400">عرض جديد وتصدير PDF</p>
                                </div>
                            </div>
                            <ArrowLeft class="size-5 shrink-0 text-violet-600 rtl:rotate-180" />
                        </Link>

                        <Link
                            href="/settings/whatsapp"
                            class="flex items-center justify-between gap-3 rounded-2xl border border-emerald-100 bg-emerald-50/80 p-4 transition hover:border-emerald-200 hover:bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/30"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-300">
                                    <MessageCircle class="size-5" />
                                </div>
                                <div class="min-w-0 text-start">
                                    <p class="font-bold text-slate-900 dark:text-white">إعدادات واتساب</p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-neutral-400">أرقام إشعارات الطلبات</p>
                                </div>
                            </div>
                            <ArrowLeft class="size-5 shrink-0 text-emerald-600 rtl:rotate-180" />
                        </Link>
                    </div>
                </section>

                <!-- Quick activity -->
                <section class="rounded-3xl border border-white bg-white p-5 shadow-[0_8px_30px_rgba(15,23,42,0.04)] dark:border-neutral-800 dark:bg-neutral-900 sm:p-6">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">نشاط سريع</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-neutral-400">الوصول المباشر للأقسام المهمة</p>

                    <div class="mt-5 space-y-2">
                        <Link
                            v-for="item in quickLinks"
                            :key="item.href"
                            :href="item.href"
                            class="flex items-center gap-3 rounded-2xl px-2 py-2.5 transition hover:bg-slate-50 dark:hover:bg-neutral-800/70"
                        >
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                                :class="item.tone"
                            >
                                <component :is="item.icon" class="size-4" />
                            </div>
                            <div class="min-w-0 flex-1 text-start">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ item.label }}</p>
                                <p class="text-xs text-slate-500 dark:text-neutral-400">{{ item.hint }}</p>
                            </div>
                            <ArrowUpRight class="size-4 shrink-0 text-slate-300" />
                        </Link>
                    </div>
                </section>

                <!-- Performance + alerts -->
                <section class="rounded-3xl border border-white bg-white p-5 shadow-[0_8px_30px_rgba(15,23,42,0.04)] dark:border-neutral-800 dark:bg-neutral-900 sm:p-6">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <h2 class="text-base font-bold text-slate-900 dark:text-white">ملخص الأداء</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-neutral-400">الفواتير · آخر 7 أيام</p>
                        </div>
                        <span class="rounded-full bg-violet-50 px-2.5 py-1 text-[11px] font-semibold text-violet-700 dark:bg-violet-950/50 dark:text-violet-300">
                            {{ formatInteger(totalInvoices) }} فاتورة
                        </span>
                    </div>

                    <div class="mt-4 overflow-hidden rounded-2xl bg-gradient-to-b from-violet-50/80 to-white p-3 dark:from-violet-950/20 dark:to-neutral-900">
                        <svg
                            class="h-36 w-full"
                            :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                            preserveAspectRatio="none"
                            aria-hidden="true"
                        >
                            <defs>
                                <linearGradient id="perfFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#8b5cf6" stop-opacity="0.35" />
                                    <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0.02" />
                                </linearGradient>
                            </defs>
                            <path
                                :d="areaPath(performanceSeries.values, chartWidth, chartHeight)"
                                fill="url(#perfFill)"
                            />
                            <path
                                :d="linePath(performanceSeries.values, chartWidth, chartHeight)"
                                fill="none"
                                stroke="#7c3aed"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                        <div class="mt-1 flex justify-between px-1 text-[10px] font-medium text-slate-400">
                            <span v-for="(label, i) in performanceSeries.labels" :key="`${label}-${i}`">
                                {{ label }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-5">
                        <h3 class="mb-3 text-sm font-bold text-slate-900 dark:text-white">تنبيهات وإشعارات</h3>
                        <ul class="space-y-3">
                            <li
                                v-for="(alert, index) in alerts"
                                :key="`${alert.title}-${index}`"
                                class="flex items-start gap-3"
                            >
                                <span
                                    class="mt-1.5 size-2 shrink-0 rounded-full"
                                    :class="alertDot(alert.tone)"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-slate-800 dark:text-neutral-200">{{ alert.title }}</p>
                                    <p class="text-xs text-slate-400">{{ alert.time }}</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </section>
            </div>

            <p class="pb-2 text-center text-xs text-slate-400 dark:text-neutral-500">
                © عالم المغامرة. جميع الحقوق محفوظة.
            </p>
        </div>
    </AppLayout>
</template>
