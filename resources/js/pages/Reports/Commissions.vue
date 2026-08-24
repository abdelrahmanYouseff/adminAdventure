<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    CalendarRange,
    Download,
    Gamepad2,
    Percent,
    ShoppingCart,
    Wallet,
} from 'lucide-vue-next';
import { formatCurrency, formatDate, formatInteger } from '@/lib/formatNumber';

interface CommissionRow {
    id: number;
    order_date: string | null;
    order_number: string;
    customer_name: string | null;
    games_count: number;
    total_amount: number;
    currency: string;
}

interface Props {
    filters: {
        month: string;
    };
    available_months: Array<{ value: string; label: string }>;
    period: {
        label: string;
        start: string;
        end: string;
    };
    summary: {
        orders_count: number;
        games_count: number;
        total_amount: number;
    };
    rows: CommissionRow[];
}

const props = defineProps<Props>();
defineOptions({ layout: AppLayout });

const selectedMonth = ref(props.filters.month);

watch(
    () => props.filters.month,
    (value) => {
        selectedMonth.value = value;
    },
);

const summaryCards = computed(() => [
    {
        label: 'عدد الطلبات',
        value: formatInteger(props.summary.orders_count),
        icon: ShoppingCart,
        tone: 'bg-sky-50 text-sky-700',
    },
    {
        label: 'عدد الألعاب',
        value: formatInteger(props.summary.games_count),
        icon: Gamepad2,
        tone: 'bg-violet-50 text-violet-700',
    },
    {
        label: 'إجمالي المبالغ',
        value: formatCurrency(props.summary.total_amount),
        icon: Wallet,
        tone: 'bg-emerald-50 text-emerald-700',
    },
]);

function applyMonth() {
    router.get(
        route('reports.commissions'),
        { month: selectedMonth.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function exportExcel() {
    const query = selectedMonth.value ? `?month=${encodeURIComponent(selectedMonth.value)}` : '';
    window.open(route('reports.commissions.export') + query, '_blank');
}
</script>

<template>
    <Head title="عمولات" />

    <div class="flex min-w-0 flex-1 flex-col gap-6 overflow-x-hidden p-3 pb-8 sm:p-6" dir="rtl">
        <section class="relative overflow-hidden rounded-[28px] bg-gradient-to-bl from-slate-950 via-slate-900 to-violet-950 p-5 text-white shadow-xl sm:p-8">
            <div class="pointer-events-none absolute -left-16 top-0 h-56 w-56 rounded-full bg-violet-400/20 blur-3xl" />
            <div class="pointer-events-none absolute -right-10 bottom-0 h-48 w-48 rounded-full bg-sky-500/20 blur-3xl" />

            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-[11px] font-semibold tracking-wide text-violet-100 ring-1 ring-white/10">
                        <Percent class="size-3.5" />
                        تقرير العمولات
                    </p>
                    <h1 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-4xl">{{ period.label }}</h1>
                    <p class="mt-2 text-sm text-slate-300">
                        من {{ period.start }} إلى {{ period.end }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <label class="flex h-11 min-w-[200px] items-center gap-2 rounded-full bg-white/10 px-4 text-sm ring-1 ring-white/10">
                        <CalendarRange class="size-4 text-violet-200" />
                        <select
                            v-model="selectedMonth"
                            class="w-full bg-transparent text-sm font-medium text-white outline-none"
                            @change="applyMonth"
                        >
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
                    <Button
                        type="button"
                        class="h-11 rounded-full bg-white px-4 text-slate-900 hover:bg-slate-100"
                        @click="exportExcel"
                    >
                        <Download class="ms-1.5 size-4" />
                        تنزيل Excel
                    </Button>
                </div>
            </div>
        </section>

        <div class="flex flex-wrap gap-2">
            <Link
                :href="route('reports.index')"
                class="rounded-full px-4 py-2 text-sm font-semibold text-slate-600 ring-1 ring-slate-200 transition hover:bg-slate-50"
            >
                المبيعات
            </Link>
            <span class="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                عمولات
            </span>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <article
                v-for="card in summaryCards"
                :key="card.label"
                class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold text-slate-400">{{ card.label }}</p>
                        <p class="mt-2 text-2xl font-black tabular-nums text-slate-900">{{ card.value }}</p>
                    </div>
                    <span class="inline-flex size-11 items-center justify-center rounded-2xl" :class="card.tone">
                        <component :is="card.icon" class="size-5" />
                    </span>
                </div>
            </article>
        </div>

        <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 class="text-base font-bold text-slate-900">طلبات الشهر</h2>
                <p class="text-sm text-slate-400">{{ formatInteger(rows.length) }} طلب</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/80 text-start">
                            <th class="px-4 py-3.5 text-[13px] font-semibold text-slate-600">تاريخ الطلب</th>
                            <th class="px-4 py-3.5 text-[13px] font-semibold text-slate-600">رقم الطلب</th>
                            <th class="px-4 py-3.5 text-[13px] font-semibold text-slate-600">عدد الألعاب</th>
                            <th class="px-4 py-3.5 text-[13px] font-semibold text-slate-600">إجمالي سعر الطلب</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td colspan="4" class="px-4 py-16 text-center text-slate-500">
                                لا توجد طلبات في هذا الشهر.
                            </td>
                        </tr>
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="border-b border-slate-100 transition hover:bg-slate-50/70"
                        >
                            <td class="px-4 py-3.5 tabular-nums text-slate-700">
                                {{ row.order_date ? formatDate(row.order_date) : '—' }}
                            </td>
                            <td class="px-4 py-3.5">
                                <Link
                                    :href="`/orders/${row.id}`"
                                    class="font-semibold tabular-nums text-sky-700 hover:underline"
                                    dir="ltr"
                                >
                                    {{ row.order_number }}
                                </Link>
                            </td>
                            <td class="px-4 py-3.5 tabular-nums font-semibold text-slate-900">
                                {{ formatInteger(row.games_count) }}
                            </td>
                            <td class="px-4 py-3.5 tabular-nums font-bold text-slate-900">
                                {{ formatCurrency(row.total_amount, row.currency) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
