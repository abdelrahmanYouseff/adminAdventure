<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    ArrowDownRight,
    ArrowUpRight,
    Banknote,
    BarChart3,
    CalendarRange,
    CreditCard,
    Minus,
    Printer,
    ShoppingBag,
    Sparkles,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { formatCurrency, formatInteger } from '@/lib/formatNumber';

interface PeriodMeta {
    label: string;
    start: string;
    end: string;
    is_future: boolean;
}

interface Kpi {
    key: string;
    label: string;
    format: 'money' | 'count';
    value: number;
    previous: number;
    next: number;
    change: number;
    next_change: number;
}

interface SeriesPoint {
    date: string;
    label: string;
    sales: number;
    orders: number;
}

interface NamedSlice {
    key: string;
    label: string;
    count: number;
    amount: number;
    share?: number;
}

interface RankedRow {
    name: string;
    quantity?: number;
    orders?: number;
    amount: number;
    share?: number;
}

interface Highlight {
    tone: string;
    title: string;
    text: string;
}

interface Analytics {
    collection_rate: number;
    cancellation_rate: number;
    paid_orders_count: number;
    cancelled_count: number;
    unique_customers: number;
    avg_daily_sales: number;
    best_day: SeriesPoint | null;
    worst_day: SeriesPoint | null;
    top_product: RankedRow | null;
    top_customer: RankedRow | null;
    top_method: NamedSlice | null;
    previous_collection_rate: number;
    next_collection_rate: number;
    next_is_future: boolean;
}

interface Props {
    filters: {
        preset: 'week' | 'month' | 'last_month' | 'custom';
        month: string;
    };
    available_months: Array<{ value: string; label: string }>;
    period: PeriodMeta;
    previous_period: PeriodMeta;
    next_period: PeriodMeta;
    kpis: Kpi[];
    series: SeriesPoint[];
    by_status: NamedSlice[];
    by_payment_method: NamedSlice[];
    top_products: RankedRow[];
    top_customers: RankedRow[];
    weekday: NamedSlice[];
    analytics: Analytics;
    highlights: Highlight[];
    insight: string;
}

const props = defineProps<Props>();

defineOptions({ layout: AppLayout });

const hoveredBar = ref<string | null>(null);

const presets = [
    { key: 'week' as const, label: 'هذا الأسبوع' },
    { key: 'month' as const, label: 'هذا الشهر' },
    { key: 'last_month' as const, label: 'الشهر الماضي' },
];

const paymentColors = ['#0f766e', '#2563eb', '#d97706', '#7c3aed', '#64748b', '#db2777'];

const maxSeriesSales = computed(() => Math.max(1, ...props.series.map((point) => point.sales)));
const maxWeekday = computed(() => Math.max(1, ...props.weekday.map((row) => row.amount)));
const hoveredPoint = computed(() => props.series.find((point) => point.date === hoveredBar.value) ?? null);

const paymentGradient = computed(() => {
    if (!props.by_payment_method.length) {
        return 'conic-gradient(#e2e8f0 0 100%)';
    }

    let acc = 0;
    const stops = props.by_payment_method.map((row, index) => {
        const start = acc;
        acc += row.share ?? 0;
        return `${paymentColors[index % paymentColors.length]} ${start}% ${acc}%`;
    });

    return `conic-gradient(${stops.join(', ')})`;
});

const salesKpi = computed(() => props.kpis.find((kpi) => kpi.key === 'sales_total'));
const collectionDelta = computed(
    () => Math.round((props.analytics.collection_rate - props.analytics.previous_collection_rate) * 10) / 10,
);

function applyPreset(preset: Props['filters']['preset'], month?: string) {
    router.get(
        route('reports.index'),
        {
            preset,
            month: preset === 'custom' ? month : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onMonthChange(event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    if (value) {
        applyPreset('custom', value);
    }
}

function formatKpi(kpi: Kpi, value: number): string {
    return kpi.format === 'money' ? formatCurrency(value) : formatInteger(value);
}

function changeClass(change: number): string {
    if (change > 0.5) return 'text-emerald-600 dark:text-emerald-300';
    if (change < -0.5) return 'text-rose-600 dark:text-rose-300';
    return 'text-slate-500';
}

function changeBadge(change: number): string {
    if (change > 0.5) return 'bg-emerald-50 text-emerald-700 ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50';
    if (change < -0.5) return 'bg-rose-50 text-rose-700 ring-rose-100 dark:bg-rose-950/40 dark:text-rose-300 dark:ring-rose-900/50';
    return 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-700';
}

function highlightClass(tone: string): string {
    const map: Record<string, string> = {
        emerald: 'border-emerald-200 bg-emerald-50/80 dark:border-emerald-900/50 dark:bg-emerald-950/20',
        rose: 'border-rose-200 bg-rose-50/80 dark:border-rose-900/50 dark:bg-rose-950/20',
        amber: 'border-amber-200 bg-amber-50/80 dark:border-amber-900/50 dark:bg-amber-950/20',
        sky: 'border-sky-200 bg-sky-50/80 dark:border-sky-900/50 dark:bg-sky-950/20',
        violet: 'border-violet-200 bg-violet-50/80 dark:border-violet-900/50 dark:bg-violet-950/20',
        slate: 'border-slate-200 bg-slate-50 dark:border-neutral-700 dark:bg-neutral-900',
    };
    return map[tone] || map.slate;
}

function printReport() {
    window.print();
}

function kpiIcon(key: string) {
    if (key === 'sales_total') return TrendingUp;
    if (key === 'orders_count') return ShoppingBag;
    if (key === 'paid_total') return Banknote;
    if (key === 'remaining_total') return Wallet;
    if (key === 'avg_order') return CreditCard;
    return Users;
}
</script>

<template>
    <Head title="تقارير المبيعات" />

    <div class="flex min-w-0 flex-1 flex-col gap-6 overflow-x-hidden p-3 pb-[max(1.5rem,env(safe-area-inset-bottom))] sm:p-6">
        <section class="relative overflow-hidden rounded-[28px] bg-gradient-to-bl from-slate-950 via-slate-900 to-teal-950 p-5 text-white shadow-xl sm:p-8">
            <div class="pointer-events-none absolute -left-16 top-0 h-56 w-56 rounded-full bg-teal-400/20 blur-3xl" />
            <div class="pointer-events-none absolute -right-10 bottom-0 h-48 w-48 rounded-full bg-blue-500/20 blur-3xl" />

            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[11px] font-semibold tracking-wide text-teal-100 ring-1 ring-white/10">
                        <Sparkles class="size-3.5" />
                        تقرير مبيعات تحليلي
                    </p>
                    <h1 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-4xl">{{ period.label }}</h1>
                    <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-300">
                        مقارنة تفصيلية مع
                        <span class="font-semibold text-white">{{ previous_period.label }}</span>
                        و
                        <span class="font-semibold text-white">{{ next_period.label }}</span>
                        · من {{ period.start }} إلى {{ period.end }}
                    </p>
                    <p v-if="salesKpi" class="mt-5 text-3xl font-black tabular-nums tracking-tight sm:text-5xl">
                        {{ formatCurrency(salesKpi.value) }}
                    </p>
                    <p v-if="salesKpi" class="mt-2 text-sm text-slate-300">
                        إجمالي المبيعات للفترة المختارة
                        <span class="ms-2 inline-flex items-center gap-1 rounded-full bg-white/10 px-2 py-0.5 text-xs font-semibold" :class="changeClass(salesKpi.change)">
                            <ArrowUpRight v-if="salesKpi.change > 0.5" class="size-3.5" />
                            <ArrowDownRight v-else-if="salesKpi.change < -0.5" class="size-3.5" />
                            {{ salesKpi.change > 0 ? '+' : '' }}{{ salesKpi.change }}%
                        </span>
                    </p>
                </div>

                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="preset in presets"
                            :key="preset.key"
                            type="button"
                            class="h-10 rounded-full px-4 text-sm font-semibold transition"
                            :class="
                                filters.preset === preset.key
                                    ? 'bg-white text-slate-900 shadow-lg'
                                    : 'bg-white/10 text-white ring-1 ring-white/10 hover:bg-white/15'
                            "
                            @click="applyPreset(preset.key)"
                        >
                            {{ preset.label }}
                        </button>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="flex h-10 min-w-[180px] items-center gap-2 rounded-full bg-white/10 px-3 text-sm ring-1 ring-white/10">
                            <CalendarRange class="size-4 text-teal-200" />
                            <select
                                class="w-full bg-transparent text-sm font-medium text-white outline-none"
                                :value="filters.preset === 'week' ? '' : filters.month"
                                @change="onMonthChange"
                            >
                                <option value="" disabled class="text-slate-800">اختيار شهر</option>
                                <option
                                    v-for="month in available_months"
                                    :key="month.value"
                                    :value="month.value"
                                    class="text-slate-800"
                                >
                                    {{ month.label }}
                                </option>
                            </select>
                        </label>
                        <button
                            type="button"
                            class="inline-flex h-10 items-center gap-2 rounded-full bg-white/10 px-4 text-sm font-semibold ring-1 ring-white/10 hover:bg-white/15"
                            @click="printReport"
                        >
                            <Printer class="size-4" />
                            طباعة
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-2 gap-3 xl:grid-cols-3">
            <article
                v-for="kpi in kpis"
                :key="kpi.key"
                class="group rounded-3xl border border-slate-200/80 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-neutral-800 dark:bg-neutral-950 sm:p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex size-10 items-center justify-center rounded-2xl bg-slate-50 text-slate-700 dark:bg-neutral-800 dark:text-neutral-200">
                        <component :is="kpiIcon(kpi.key)" class="size-5" />
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold ring-1" :class="changeBadge(kpi.change)">
                        <ArrowUpRight v-if="kpi.change > 0.5" class="size-3" />
                        <ArrowDownRight v-else-if="kpi.change < -0.5" class="size-3" />
                        <Minus v-else class="size-3" />
                        {{ kpi.change > 0 ? '+' : '' }}{{ kpi.change }}%
                    </span>
                </div>
                <p class="mt-4 text-xs font-semibold text-slate-400">{{ kpi.label }}</p>
                <p class="mt-1 text-xl font-black tabular-nums tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                    {{ formatKpi(kpi, kpi.value) }}
                </p>
                <p class="mt-3 text-[11px] leading-relaxed text-slate-500">
                    السابق {{ formatKpi(kpi, kpi.previous) }}
                    · التالي {{ formatKpi(kpi, kpi.next) }}
                </p>
            </article>
        </div>

        <div class="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
            <section class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950 sm:p-6">
                <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <BarChart3 class="size-4 text-teal-700" />
                            <h2 class="font-bold text-slate-900 dark:text-white">حركة المبيعات اليومية</h2>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">متوسط يومي {{ formatCurrency(analytics.avg_daily_sales) }}</p>
                    </div>
                    <div v-if="hoveredPoint" class="rounded-2xl bg-slate-50 px-3 py-2 text-xs dark:bg-neutral-800">
                        <p class="font-semibold text-slate-900 dark:text-white">{{ hoveredPoint.label }}</p>
                        <p class="tabular-nums text-slate-500">{{ formatCurrency(hoveredPoint.sales) }} · {{ formatInteger(hoveredPoint.orders) }} طلب</p>
                    </div>
                </div>
                <div class="flex h-64 items-end gap-1.5 overflow-x-auto pb-1">
                    <button
                        v-for="point in series"
                        :key="point.date"
                        type="button"
                        class="group/bar flex min-w-7 flex-1 flex-col items-center justify-end gap-2"
                        @mouseenter="hoveredBar = point.date"
                        @mouseleave="hoveredBar = null"
                    >
                        <div class="relative flex h-52 w-full items-end justify-center">
                            <div
                                class="w-full max-w-9 rounded-t-xl transition"
                                :class="hoveredBar === point.date ? 'bg-teal-600' : 'bg-gradient-to-t from-slate-800 to-teal-500/90'"
                                :style="{ height: `${Math.max(6, (point.sales / maxSeriesSales) * 100)}%` }"
                            />
                        </div>
                        <span class="text-[10px] font-medium text-slate-400">{{ point.label }}</span>
                    </button>
                </div>
            </section>

            <section class="grid gap-4">
                <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950">
                    <p class="text-xs font-semibold text-slate-400">نسبة التحصيل</p>
                    <div class="mt-4 flex items-center gap-5">
                        <div
                            class="relative size-28 shrink-0 rounded-full"
                            :style="{
                                background: `conic-gradient(#0f766e ${analytics.collection_rate}%, #e2e8f0 ${analytics.collection_rate}% 100%)`,
                            }"
                        >
                            <div class="absolute inset-3 flex items-center justify-center rounded-full bg-white text-center dark:bg-neutral-950">
                                <p class="text-lg font-black tabular-nums text-slate-900 dark:text-white">{{ analytics.collection_rate }}%</p>
                            </div>
                        </div>
                        <div class="space-y-2 text-sm">
                            <p class="font-semibold text-slate-900 dark:text-white">{{ formatInteger(analytics.paid_orders_count) }} طلب مكتمل الدفع</p>
                            <p class="text-slate-500">التغير عن الفترة السابقة {{ collectionDelta > 0 ? '+' : '' }}{{ collectionDelta }} نقطة</p>
                            <p class="text-slate-500">إلغاءات {{ analytics.cancellation_rate }}% · {{ formatInteger(analytics.cancelled_count) }} طلب</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-3xl border border-slate-200/80 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-950">
                        <p class="text-[11px] font-semibold text-slate-400">أقوى يوم</p>
                        <p class="mt-2 font-bold text-slate-900 dark:text-white">{{ analytics.best_day?.label || '—' }}</p>
                        <p class="mt-1 text-xs tabular-nums text-teal-700">{{ formatCurrency(analytics.best_day?.sales || 0) }}</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200/80 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-950">
                        <p class="text-[11px] font-semibold text-slate-400">أضعف يوم نشط</p>
                        <p class="mt-2 font-bold text-slate-900 dark:text-white">{{ analytics.worst_day?.label || '—' }}</p>
                        <p class="mt-1 text-xs tabular-nums text-amber-700">{{ formatCurrency(analytics.worst_day?.sales || 0) }}</p>
                    </div>
                </div>
            </section>
        </div>

        <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-950">
            <div class="border-b border-slate-100 px-5 py-4 dark:border-neutral-800 sm:px-6">
                <h2 class="font-bold text-slate-900 dark:text-white">مصفوفة المقارنة</h2>
                <p class="mt-1 text-xs text-slate-400">الحالي مقابل السابق والتالي في نظرة واحدة</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-start text-xs font-semibold text-slate-500 dark:bg-neutral-900 dark:text-neutral-400">
                            <th class="px-5 py-3 text-start">المؤشر</th>
                            <th class="px-4 py-3 text-start">{{ previous_period.label }}</th>
                            <th class="px-4 py-3 text-start">الفترة الحالية</th>
                            <th class="px-4 py-3 text-start">
                                {{ next_period.label }}
                                <span v-if="next_period.is_future" class="ms-1 text-[10px] font-medium text-amber-600">قادمة</span>
                            </th>
                            <th class="px-4 py-3 text-start">التغير عن السابق</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="kpi in kpis"
                            :key="`cmp-${kpi.key}`"
                            class="border-t border-slate-100 dark:border-neutral-800"
                        >
                            <td class="px-5 py-3.5 font-semibold text-slate-800 dark:text-neutral-100">{{ kpi.label }}</td>
                            <td class="px-4 py-3.5 tabular-nums text-slate-500">{{ formatKpi(kpi, kpi.previous) }}</td>
                            <td class="px-4 py-3.5 font-bold tabular-nums text-slate-900 dark:text-white">{{ formatKpi(kpi, kpi.value) }}</td>
                            <td class="px-4 py-3.5 tabular-nums text-slate-500">{{ formatKpi(kpi, kpi.next) }}</td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold ring-1" :class="changeBadge(kpi.change)">
                                    {{ kpi.change > 0 ? '+' : '' }}{{ kpi.change }}%
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950 sm:p-6">
                <h2 class="mb-5 font-bold text-slate-900 dark:text-white">توزيع طرق الدفع</h2>
                <div class="flex flex-col items-center gap-6 sm:flex-row">
                    <div class="relative size-40 shrink-0 rounded-full" :style="{ background: paymentGradient }">
                        <div class="absolute inset-6 flex items-center justify-center rounded-full bg-white dark:bg-neutral-950">
                            <div class="text-center">
                                <p class="text-[11px] text-slate-400">المزيج</p>
                                <p class="text-sm font-black text-slate-900 dark:text-white">{{ formatInteger(by_payment_method.length) }} طرق</p>
                            </div>
                        </div>
                    </div>
                    <div class="w-full space-y-3">
                        <div v-for="(row, index) in by_payment_method" :key="row.key" class="flex items-center justify-between gap-3 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="size-2.5 rounded-full" :style="{ background: paymentColors[index % paymentColors.length] }" />
                                <span class="text-slate-700 dark:text-neutral-200">{{ row.label }}</span>
                            </div>
                            <div class="text-end">
                                <p class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatCurrency(row.amount) }}</p>
                                <p class="text-[11px] text-slate-400">{{ row.share }}% · {{ formatInteger(row.count) }} طلب</p>
                            </div>
                        </div>
                        <p v-if="!by_payment_method.length" class="text-sm text-slate-400">لا توجد بيانات دفع</p>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950 sm:p-6">
                <h2 class="mb-5 font-bold text-slate-900 dark:text-white">أداء أيام الأسبوع</h2>
                <div class="space-y-3">
                    <div v-for="row in weekday" :key="row.key">
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="font-medium text-slate-700 dark:text-neutral-200">{{ row.label }}</span>
                            <span class="tabular-nums text-slate-500">{{ formatCurrency(row.amount) }} · {{ formatInteger(row.count) }}</span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-neutral-800">
                            <div
                                class="h-full rounded-full bg-gradient-to-l from-teal-600 to-slate-800"
                                :style="{ width: `${(row.amount / maxWeekday) * 100}%` }"
                            />
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950 sm:p-6">
                <div class="mb-5 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <ShoppingBag class="size-4 text-slate-400" />
                        <h2 class="font-bold text-slate-900 dark:text-white">أعلى المنتجات</h2>
                    </div>
                    <span class="text-xs text-slate-400">حصة من المبيعات</span>
                </div>
                <div class="space-y-4">
                    <p v-if="!top_products.length" class="text-sm text-slate-400">لا توجد منتجات في هذه الفترة</p>
                    <div v-for="(row, index) in top_products" :key="row.name">
                        <div class="mb-1.5 flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-800 dark:text-neutral-100">
                                <span class="me-2 text-xs text-slate-400">{{ formatInteger(index + 1) }}</span>
                                {{ row.name }}
                            </p>
                            <p class="text-sm font-bold tabular-nums text-slate-900 dark:text-white">{{ formatCurrency(row.amount) }}</p>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-neutral-800">
                            <div class="h-full rounded-full bg-slate-900 dark:bg-teal-500" :style="{ width: `${row.share || 0}%` }" />
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">{{ row.share }}% · كمية {{ formatInteger(row.quantity || 0) }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950 sm:p-6">
                <div class="mb-5 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Users class="size-4 text-slate-400" />
                        <h2 class="font-bold text-slate-900 dark:text-white">أعلى العملاء</h2>
                    </div>
                    <span class="text-xs text-slate-400">تركيز الإيراد</span>
                </div>
                <div class="space-y-4">
                    <p v-if="!top_customers.length" class="text-sm text-slate-400">لا يوجد عملاء في هذه الفترة</p>
                    <div v-for="(row, index) in top_customers" :key="row.name">
                        <div class="mb-1.5 flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-800 dark:text-neutral-100">
                                <span class="me-2 text-xs text-slate-400">{{ formatInteger(index + 1) }}</span>
                                {{ row.name }}
                            </p>
                            <p class="text-sm font-bold tabular-nums text-slate-900 dark:text-white">{{ formatCurrency(row.amount) }}</p>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-neutral-800">
                            <div class="h-full rounded-full bg-teal-700" :style="{ width: `${row.share || 0}%` }" />
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">{{ row.share }}% · {{ formatInteger(row.orders || 0) }} طلب</p>
                    </div>
                </div>
            </section>
        </div>

        <section class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-950 sm:p-6">
            <h2 class="mb-4 font-bold text-slate-900 dark:text-white">قراءة تحليلية</h2>
            <p class="mb-5 text-sm leading-relaxed text-slate-600 dark:text-neutral-300">{{ insight }}</p>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="item in highlights"
                    :key="item.title"
                    class="rounded-2xl border p-4"
                    :class="highlightClass(item.tone)"
                >
                    <p class="text-sm font-bold text-slate-900 dark:text-white">{{ item.title }}</p>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-neutral-300">{{ item.text }}</p>
                </article>
            </div>
        </section>
    </div>
</template>
