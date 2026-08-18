<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    ChevronLeft,
    ChevronRight,
    Route,
    Search,
    Sparkles,
} from 'lucide-vue-next';
import { formatDateTime, formatInteger } from '@/lib/formatNumber';

interface JourneyRow {
    id: number;
    order_number: string;
    customer_name: string;
    customer_phone: string | null;
    created_at: string | null;
    status: string;
    is_cancelled: boolean;
    current_key: string | null;
    current_title: string | null;
    waiting: string | null;
    percent: number;
    completed_steps: number;
    total_steps: number;
    is_complete: boolean;
}

interface StageOption {
    key: string;
    label: string;
}

interface PaginatedOrders {
    data: JourneyRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface Props {
    orders: PaginatedOrders;
    filters: {
        search: string;
        stage: string;
    };
    stages: StageOption[];
}

const props = defineProps<Props>();
defineOptions({ layout: AppLayout });

const searchQuery = ref(props.filters.search || '');

watch(
    () => props.filters.search,
    (value) => {
        searchQuery.value = value || '';
    },
);

const pageNumbers = computed(() => {
    const total = props.orders.last_page;
    const current = props.orders.current_page;
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }

    const pages: Array<number | 'ellipsis'> = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);
    if (start > 2) pages.push('ellipsis');
    for (let i = start; i <= end; i += 1) pages.push(i);
    if (end < total - 1) pages.push('ellipsis');
    pages.push(total);
    return pages;
});

function applyFilters(pageNumber = 1, stage = props.filters.stage) {
    router.get('/order-journey', {
        search: searchQuery.value.trim() || undefined,
        stage: stage !== 'all' ? stage : undefined,
        page: pageNumber > 1 ? pageNumber : undefined,
    }, { preserveState: true, preserveScroll: true });
}

function setStage(stage: string) {
    applyFilters(1, stage);
}

function goToPage(pageNumber: number) {
    if (pageNumber >= 1 && pageNumber <= props.orders.last_page) {
        applyFilters(pageNumber);
    }
}
</script>

<template>
    <Head title="رحلة الطلب" />

    <div class="flex min-w-0 flex-1 flex-col gap-6 overflow-x-hidden p-3 pb-8 sm:p-6" dir="rtl">
        <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 px-6 py-8 text-white shadow-xl sm:px-8">
            <div class="pointer-events-none absolute -left-10 top-0 h-48 w-48 rounded-full bg-sky-500/30 blur-3xl" />
            <div class="pointer-events-none absolute -right-6 bottom-0 h-40 w-40 rounded-full bg-emerald-400/20 blur-3xl" />
            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="inline-flex items-center gap-2 text-xs font-semibold tracking-[0.18em] text-sky-200/80">
                        <Sparkles class="size-3.5" />
                        تتبع تشغيلي
                    </p>
                    <h1 class="mt-2 flex items-center gap-3 text-2xl font-extrabold sm:text-3xl">
                        <span class="flex size-11 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15">
                            <Route class="size-6" />
                        </span>
                        رحلة الطلب
                    </h1>
                    <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-300">
                        تابع أين وصل كل طلب، وما الخطوة التالية، ومن ينتظر التنفيذ — من عرض السعر حتى إغلاق الدورة.
                    </p>
                </div>
                <p class="text-sm text-slate-400">
                    {{ formatInteger(orders.total) }} طلب
                </p>
            </div>
        </section>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <form class="w-full max-w-md" @submit.prevent="applyFilters(1)">
                <label class="flex h-11 items-center gap-2 rounded-full border border-slate-200 bg-white px-4 text-slate-400 shadow-sm transition focus-within:border-sky-300 focus-within:ring-2 focus-within:ring-sky-100 dark:border-neutral-700 dark:bg-neutral-900">
                    <Search class="size-4 shrink-0" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="ابحث برقم الطلب أو اسم العميل..."
                        class="w-full bg-transparent text-sm text-slate-800 outline-none placeholder:text-slate-400 dark:text-neutral-100"
                    />
                </label>
            </form>

            <div class="flex gap-1.5 overflow-x-auto pb-1">
                <button
                    v-for="stage in stages"
                    :key="stage.key"
                    type="button"
                    class="shrink-0 rounded-full px-3.5 py-2 text-xs font-semibold transition"
                    :class="filters.stage === stage.key
                        ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                        : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 dark:bg-neutral-900 dark:text-neutral-300 dark:ring-neutral-700'"
                    @click="setStage(stage.key)"
                >
                    {{ stage.label }}
                </button>
            </div>
        </div>

        <div v-if="orders.data.length === 0" class="rounded-3xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-500 dark:border-neutral-700 dark:bg-neutral-900">
            لا توجد طلبات مطابقة للبحث أو المرحلة الحالية.
        </div>

        <div v-else class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <Link
                v-for="item in orders.data"
                :key="item.id"
                :href="`/order-journey/${item.id}`"
                class="group relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-sky-200 hover:shadow-lg dark:border-neutral-700 dark:bg-neutral-900"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs text-slate-400">الطلب</p>
                        <p class="mt-0.5 font-bold tabular-nums text-slate-900 dark:text-white" dir="ltr">
                            {{ item.order_number }}
                        </p>
                        <p class="mt-2 truncate font-semibold text-slate-800 dark:text-neutral-100">
                            {{ item.customer_name }}
                        </p>
                    </div>
                    <div class="relative flex size-14 shrink-0 items-center justify-center">
                        <svg class="size-14 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                            <circle cx="18" cy="18" r="15.5" fill="none" class="stroke-slate-100 dark:stroke-neutral-800" stroke-width="4" />
                            <circle
                                cx="18"
                                cy="18"
                                r="15.5"
                                fill="none"
                                class="stroke-sky-500"
                                stroke-width="4"
                                stroke-linecap="round"
                                :stroke-dasharray="`${item.percent * 0.97} 100`"
                            />
                        </svg>
                        <span class="absolute text-[11px] font-bold tabular-nums text-slate-700 dark:text-neutral-200">
                            {{ item.percent }}%
                        </span>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl bg-slate-50 px-4 py-3 dark:bg-neutral-800/70">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                        {{ item.is_complete ? 'الحالة' : 'الخطوة الحالية' }}
                    </p>
                    <p class="mt-1 font-bold text-slate-900 dark:text-white">
                        {{ item.is_complete ? 'اكتملت الرحلة' : item.current_title }}
                    </p>
                    <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-neutral-400">
                        {{ item.waiting }}
                    </p>
                </div>

                <div class="mt-4 flex items-center justify-between text-xs text-slate-400">
                    <span>{{ formatInteger(item.completed_steps) }} / {{ formatInteger(item.total_steps) }} خطوات</span>
                    <span v-if="item.created_at">{{ formatDateTime(item.created_at) }}</span>
                </div>
                <span
                    v-if="item.is_cancelled"
                    class="absolute start-4 top-4 rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-600 ring-1 ring-rose-100"
                >
                    ملغي
                </span>
            </Link>
        </div>

        <div v-if="orders.last_page > 1" class="flex items-center justify-between">
            <p class="text-sm text-slate-500">
                عرض {{ formatInteger(orders.from ?? 0) }} - {{ formatInteger(orders.to ?? 0) }} من {{ formatInteger(orders.total) }}
            </p>
            <div class="flex items-center gap-1.5">
                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-full bg-white text-slate-500 ring-1 ring-slate-200 disabled:opacity-40"
                    :disabled="orders.current_page <= 1"
                    @click="goToPage(orders.current_page - 1)"
                >
                    <ChevronRight class="size-4" />
                </button>
                <template v-for="(item, index) in pageNumbers" :key="`${item}-${index}`">
                    <span v-if="item === 'ellipsis'" class="px-1 text-slate-400">...</span>
                    <button
                        v-else
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-full text-sm font-medium"
                        :class="orders.current_page === item ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'"
                        @click="goToPage(item)"
                    >
                        {{ item }}
                    </button>
                </template>
                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-full bg-white text-slate-500 ring-1 ring-slate-200 disabled:opacity-40"
                    :disabled="orders.current_page >= orders.last_page"
                    @click="goToPage(orders.current_page + 1)"
                >
                    <ChevronLeft class="size-4" />
                </button>
            </div>
        </div>
    </div>
</template>
