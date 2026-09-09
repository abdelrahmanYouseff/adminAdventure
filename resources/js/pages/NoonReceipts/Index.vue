<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    ChevronLeft,
    ChevronRight,
    CreditCard,
    Download,
    Eye,
    Search,
} from 'lucide-vue-next';
import { formatCurrency, formatDate, formatInteger } from '@/lib/formatNumber';

interface NoonReceiptRow {
    key: string;
    receipt_id: number | null;
    order_id: number | null;
    customer_name: string;
    customer_phone?: string | null;
    customer_email?: string | null;
    order_number?: string | null;
    receipt_number?: string | null;
    amount: number;
    currency: string;
    noon_order_id?: string | null;
    paid_at?: string | null;
    pdf_url: string;
    download_url: string;
}

interface PaginatedReceipts {
    data: NoonReceiptRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    per_page: number;
}

interface Props {
    receipts: PaginatedReceipts;
    stats?: {
        count: number;
        amount: number;
    };
    filters?: {
        search?: string | null;
        per_page?: number;
    };
}

const props = withDefaults(defineProps<Props>(), {
    stats: () => ({ count: 0, amount: 0 }),
    filters: () => ({
        search: '',
        per_page: 15,
    }),
});

defineOptions({ layout: AppLayout });

const searchInput = ref(props.filters?.search ?? '');
const perPage = ref(props.filters?.per_page || 15);

const pageNumbers = computed(() => {
    const total = props.receipts.last_page;
    const current = props.receipts.current_page;
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

watch(
    () => props.filters,
    (filters) => {
        searchInput.value = filters?.search ?? '';
        perPage.value = filters?.per_page || 15;
    },
);

function applyFilters(pageNum = 1) {
    router.get(
        route('noon-receipts.index'),
        {
            search: searchInput.value.trim() || undefined,
            per_page: perPage.value !== 15 ? perPage.value : undefined,
            page: pageNum > 1 ? pageNum : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onSearchSubmit() {
    applyFilters(1);
}

function goToPage(pageNum: number) {
    if (pageNum >= 1 && pageNum <= props.receipts.last_page) {
        applyFilters(pageNum);
    }
}

function viewReceipt(row: NoonReceiptRow) {
    window.open(row.pdf_url, '_blank', 'noopener');
}

function downloadReceipt(row: NoonReceiptRow) {
    window.open(row.download_url, '_blank', 'noopener');
}
</script>

<template>
    <Head title="إيصالات نون" />

    <div class="flex min-w-0 flex-1 flex-col gap-5 overflow-x-hidden p-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-white sm:text-2xl">
                    <CreditCard class="size-6 text-blue-600" />
                    إيصالات نون
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">
                    معاملات بوابة الدفع الناجحة فقط — اسم العميل وإيصال الدفع للعرض أو التحميل
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:gap-4">
            <div class="rounded-2xl border border-[#E0E0E0] bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900 sm:p-6">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-gray-400 dark:text-neutral-500 sm:text-xs">
                    إيصالات ناجحة
                </p>
                <p class="mt-3 text-2xl font-extrabold tabular-nums tracking-tight text-gray-900 dark:text-white sm:text-[1.75rem]">
                    {{ formatInteger(stats.count) }}
                    <span class="ms-1 text-base font-bold text-gray-700 dark:text-neutral-300 sm:text-lg">إيصال</span>
                </p>
            </div>
            <div class="rounded-2xl border border-[#E0E0E0] bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900 sm:p-6">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-gray-400 dark:text-neutral-500 sm:text-xs">
                    إجمالي المبالغ
                </p>
                <p class="mt-3 text-2xl font-extrabold tabular-nums tracking-tight text-gray-900 dark:text-white sm:text-[1.75rem]" dir="ltr">
                    {{ formatCurrency(stats.amount) }}
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
            <div class="flex flex-col gap-3 border-b border-gray-100 p-4 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between">
                <form class="w-full max-w-sm" @submit.prevent="onSearchSubmit">
                    <label class="flex h-10 items-center gap-2 rounded-full border border-transparent bg-gray-100 px-3.5 text-gray-400 transition focus-within:border-blue-300 focus-within:bg-white focus-within:ring-2 focus-within:ring-blue-100 dark:bg-neutral-800 dark:focus-within:border-blue-700 dark:focus-within:bg-neutral-950 dark:focus-within:ring-blue-950">
                        <Search class="size-4 shrink-0 stroke-[1.75]" />
                        <input
                            v-model="searchInput"
                            type="search"
                            placeholder="ابحث بالعميل أو رقم الطلب أو الإيصال..."
                            class="w-full bg-transparent text-sm text-gray-800 outline-none placeholder:text-gray-400 dark:text-neutral-100"
                        />
                    </label>
                </form>

                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-neutral-400">
                    <span>عرض</span>
                    <select
                        v-model.number="perPage"
                        class="h-8 rounded-md border border-gray-200 bg-white px-2 text-sm font-semibold text-gray-800 outline-none dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100"
                        @change="applyFilters(1)"
                    >
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                    <span>من {{ formatInteger(receipts.total) }} نتيجة</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-neutral-800">
                            <th class="px-4 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">اسم العميل</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">إيصال الدفع</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الطلب</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">المبلغ</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">التاريخ</th>
                            <th class="px-4 py-3.5 text-end text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الإيصال</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="receipts.data.length === 0">
                            <td colspan="6" class="px-4 py-16 text-center text-gray-500 dark:text-neutral-400">
                                لا توجد دفعات نون ناجحة مطابقة للبحث الحالي.
                            </td>
                        </tr>
                        <tr
                            v-for="row in receipts.data"
                            :key="row.key"
                            class="border-b border-gray-100 transition hover:bg-gray-50/70 dark:border-neutral-800 dark:hover:bg-neutral-800/40"
                        >
                            <td class="px-4 py-4">
                                <div class="flex min-w-0 flex-col items-start gap-0.5">
                                    <p class="font-semibold text-gray-900 dark:text-white">
                                        {{ row.customer_name }}
                                    </p>
                                    <p v-if="row.customer_phone" class="text-xs tabular-nums text-gray-400" dir="ltr">
                                        {{ row.customer_phone }}
                                    </p>
                                </div>
                            </td>
                            <td class="px-3 py-4">
                                <div class="flex min-w-0 flex-col items-start gap-1">
                                    <span
                                        v-if="row.receipt_number"
                                        class="inline-flex rounded-md bg-gray-50 px-2 py-0.5 font-semibold tabular-nums text-gray-800 ring-1 ring-inset ring-gray-100 dark:bg-neutral-800 dark:text-neutral-100 dark:ring-neutral-700"
                                        dir="ltr"
                                    >
                                        {{ row.receipt_number }}
                                    </span>
                                    <span v-else class="text-gray-400">—</span>
                                    <span
                                        class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50"
                                    >
                                        ناجحة
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-4">
                                <p class="font-medium tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                    {{ row.order_number || '—' }}
                                </p>
                                <p v-if="row.noon_order_id" class="mt-0.5 text-xs tabular-nums text-gray-400" dir="ltr">
                                    Noon: {{ row.noon_order_id }}
                                </p>
                            </td>
                            <td class="px-3 py-4 font-semibold tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                {{ formatCurrency(row.amount, row.currency) }}
                            </td>
                            <td class="px-3 py-4 text-gray-600 dark:text-neutral-300">
                                <span v-if="row.paid_at" dir="ltr">{{ formatDate(row.paid_at) }}</span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:bg-neutral-800"
                                        @click="viewReceipt(row)"
                                    >
                                        <Eye class="size-3.5" />
                                        عرض
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-blue-600 px-3 text-xs font-medium text-white transition hover:bg-blue-700"
                                        @click="downloadReceipt(row)"
                                    >
                                        <Download class="size-3.5" />
                                        تحميل
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-100 px-4 py-4 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-500 dark:text-neutral-400">
                    عرض {{ formatInteger(receipts.from ?? 0) }} - {{ formatInteger(receipts.to ?? 0) }} من {{ formatInteger(receipts.total) }} إيصال
                </p>

                <div v-if="receipts.last_page > 1" class="flex items-center justify-center gap-1.5 sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 disabled:opacity-40 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800"
                        :disabled="receipts.current_page <= 1"
                        @click="goToPage(receipts.current_page - 1)"
                    >
                        <ChevronRight class="size-4" />
                    </button>

                    <template v-for="(item, index) in pageNumbers" :key="`${item}-${index}`">
                        <span v-if="item === 'ellipsis'" class="px-1 text-gray-400">...</span>
                        <button
                            v-else
                            type="button"
                            class="inline-flex size-8 items-center justify-center rounded-lg text-sm font-medium transition"
                            :class="
                                receipts.current_page === item
                                    ? 'bg-gray-100 text-gray-900 dark:bg-neutral-700 dark:text-white'
                                    : 'text-gray-500 hover:bg-gray-50 dark:text-neutral-300 dark:hover:bg-neutral-800'
                            "
                            @click="goToPage(item)"
                        >
                            {{ item }}
                        </button>
                    </template>

                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 disabled:opacity-40 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800"
                        :disabled="receipts.current_page >= receipts.last_page"
                        @click="goToPage(receipts.current_page + 1)"
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
