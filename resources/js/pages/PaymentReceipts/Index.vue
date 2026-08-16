<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    ArrowUpRight,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    CreditCard,
    Eye,
    FileText,
    Mail,
    MapPin,
    MoreVertical,
    Phone,
    Receipt,
    Search,
    StickyNote,
    UserRound,
    XCircle,
} from 'lucide-vue-next';
import { formatCurrency, formatDate, formatInteger } from '@/lib/formatNumber';

interface CustomerProfile {
    name: string | null;
    phone: string | null;
    phone_secondary: string | null;
    email: string | null;
    address: string | null;
    iban: string | null;
    iban_image_url: string | null;
    source: string;
    type: string | null;
}

interface ReceiptRow {
    id: number;
    receipt_number: string;
    amount: number;
    total_amount: number;
    remaining_amount: number;
    payment_method: string | null;
    type: string;
    approval_status: string;
    is_approved: boolean;
    is_rejected: boolean;
    can_approve: boolean;
    can_reject: boolean;
    approved_at: string | null;
    approved_by_name: string | null;
    rejection_reason: string | null;
    rejected_at: string | null;
    rejected_by_name: string | null;
    created_at: string | null;
    order: {
        id: number;
        order_number: string;
        customer_name: string;
        currency: string;
        notes?: string | null;
    } | null;
    customer: CustomerProfile | null;
    recorded_by_name?: string | null;
    notes?: string | null;
    proof_image_url?: string | null;
    proof_image_urls?: string[];
    account_number?: string | null;
}

type StatusTab = 'all' | 'pending' | 'approved' | 'rejected';

interface Props {
    receipts: {
        data: ReceiptRow[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        per_page: number;
    };
    stats?: {
        pending: number;
        approved: number;
        rejected?: number;
    };
    statusCounts?: Record<StatusTab, number>;
    canApprove: boolean;
    filters?: {
        search?: string | null;
        status?: StatusTab | string | null;
        per_page?: number;
    };
}

const props = withDefaults(defineProps<Props>(), {
    stats: () => ({ pending: 0, approved: 0, rejected: 0 }),
    statusCounts: () => ({ all: 0, pending: 0, approved: 0, rejected: 0 }),
    filters: () => ({
        search: '',
        status: 'all',
        per_page: 15,
    }),
});

defineOptions({ layout: AppLayout });

const searchInput = ref(props.filters?.search ?? '');
const statusFilter = ref<StatusTab>((props.filters?.status as StatusTab) || 'all');
const perPage = ref(props.filters?.per_page || 15);
const selectedIds = ref<number[]>([]);
const expandedReceiptId = ref<number | null>(null);
const approveForm = useForm({});
const approvingId = ref<number | null>(null);
const rejectDialogOpen = ref(false);
const rejectTarget = ref<ReceiptRow | null>(null);
const rejectForm = useForm({
    rejection_reason: '',
});

const statusTabs: { key: StatusTab; label: string }[] = [
    { key: 'all', label: 'الكل' },
    { key: 'pending', label: 'بانتظار الاعتماد' },
    { key: 'approved', label: 'معتمدة' },
    { key: 'rejected', label: 'مرفوضة' },
];

const summaryCards = computed(() => [
    {
        key: 'all' as const,
        label: 'إجمالي السندات',
        value: props.statusCounts.all,
        unit: 'سند',
        hint: 'عرض كل السندات',
    },
    {
        key: 'pending' as const,
        label: 'بانتظار الاعتماد',
        value: props.statusCounts.pending,
        unit: 'سند',
        hint: 'عرض المعلقة',
    },
    {
        key: 'approved' as const,
        label: 'معتمدة',
        value: props.statusCounts.approved,
        unit: 'سند',
        hint: 'عرض المعتمدة',
    },
    {
        key: 'rejected' as const,
        label: 'مرفوضة',
        value: props.statusCounts.rejected ?? 0,
        unit: 'سند',
        hint: 'عرض المرفوضة',
    },
]);

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

const allVisibleSelected = computed(
    () =>
        props.receipts.data.length > 0
        && props.receipts.data.every((row) => selectedIds.value.includes(row.id)),
);

watch(
    () => props.filters,
    (filters) => {
        searchInput.value = filters?.search ?? '';
        statusFilter.value = (filters?.status as StatusTab) || 'all';
        perPage.value = filters?.per_page || 15;
        selectedIds.value = [];
        expandedReceiptId.value = null;
    },
);

function applyFilters(pageNum = 1) {
    router.get(
        route('payment-receipts.index'),
        {
            search: searchInput.value.trim() || undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
            per_page: perPage.value !== 15 ? perPage.value : undefined,
            page: pageNum > 1 ? pageNum : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function setStatusFilter(status: StatusTab) {
    statusFilter.value = status;
    applyFilters(1);
}

function onSearchSubmit() {
    applyFilters(1);
}

function goToPage(pageNum: number) {
    if (pageNum >= 1 && pageNum <= props.receipts.last_page) {
        applyFilters(pageNum);
    }
}

function tabCount(tab: StatusTab): number {
    return props.statusCounts?.[tab] ?? 0;
}

function toggleSelectAll() {
    if (allVisibleSelected.value) {
        const visible = new Set(props.receipts.data.map((row) => row.id));
        selectedIds.value = selectedIds.value.filter((id) => !visible.has(id));
        return;
    }

    selectedIds.value = Array.from(new Set([
        ...selectedIds.value,
        ...props.receipts.data.map((row) => row.id),
    ]));
}

function toggleSelect(id: number) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((item) => item !== id);
        return;
    }
    selectedIds.value = [...selectedIds.value, id];
}

function toggleExpand(row: ReceiptRow) {
    expandedReceiptId.value = expandedReceiptId.value === row.id ? null : row.id;
}

function approveReceipt(row: ReceiptRow) {
    if (!row.can_approve || approvingId.value !== null) return;
    approvingId.value = row.id;
    approveForm.post(route('payment-receipts.approve', row.id), {
        preserveScroll: true,
        onFinish: () => {
            approvingId.value = null;
        },
    });
}

function openRejectDialog(row: ReceiptRow) {
    if (!row.can_reject || rejectForm.processing) return;
    rejectTarget.value = row;
    rejectForm.clearErrors();
    rejectForm.rejection_reason = '';
    rejectDialogOpen.value = true;
}

function closeRejectDialog() {
    rejectDialogOpen.value = false;
    rejectTarget.value = null;
    rejectForm.reset();
    rejectForm.clearErrors();
}

function submitReject() {
    const target = rejectTarget.value;
    if (!target) return;

    rejectForm.post(`/payment-receipts/${target.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => closeRejectDialog(),
    });
}

function receiptPdfUrl(row: ReceiptRow): string | null {
    if (!row.order || !row.is_approved) return null;
    return `/orders/${row.order.id}/payment-receipts/${row.id}`;
}

function orderUrl(row: ReceiptRow): string | null {
    if (!row.order) return null;
    return `/orders/${row.order.id}`;
}

function paymentMethodLabel(method: string | null): string {
    const map: Record<string, string> = {
        cash: 'نقدي',
        bank_transfer: 'تحويل بنكي',
        credit_card: 'بطاقة ائتمان',
        noon: 'Noon',
        paypal: 'PayPal',
    };
    return method ? map[method] || method : '—';
}

function typeLabel(type: string): string {
    const map: Record<string, string> = {
        initial: 'عند الإنشاء',
        settlement: 'سداد',
        payment: 'سداد',
    };
    return map[type] || type;
}

function statusLabel(row: ReceiptRow): string {
    if (row.is_approved) return 'معتمد';
    if (row.is_rejected) return 'مرفوض';
    return 'بانتظار الاعتماد';
}

const SYSTEM_RECEIPT_NOTES = [
    'سند قبض عند إنشاء الطلب — بانتظار اعتماد المحاسب',
    'سداد من تعديل الطلب — بانتظار اعتماد المحاسب',
    'سداد من قائمة الطلبات — بانتظار اعتماد المحاسب',
    'سند قبض عند إنشاء الطلب',
];

function orderNotes(row: ReceiptRow): string | null {
    const notes = row.order?.notes?.trim();
    return notes || null;
}

function receiptNotes(row: ReceiptRow): string | null {
    const notes = row.notes?.trim();
    if (!notes || SYSTEM_RECEIPT_NOTES.includes(notes)) {
        return null;
    }

    const fromOrder = orderNotes(row);
    if (fromOrder && notes === fromOrder) {
        return null;
    }

    return notes;
}

function hasNotes(row: ReceiptRow): boolean {
    return Boolean(orderNotes(row) || receiptNotes(row));
}

function statusBadgeClass(row: ReceiptRow): string {
    if (row.is_approved) {
        return 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50';
    }
    if (row.is_rejected) {
        return 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-100 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900/50';
    }
    return 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900/50';
}
</script>

<template>
    <Head title="سندات القبض" />

    <div class="flex min-w-0 flex-1 flex-col gap-5 overflow-x-hidden p-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-white sm:text-2xl">
                    <Receipt class="size-6 text-blue-600" />
                    سندات القبض
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">
                    اعتماد المبالغ المحصّلة؛ عند اعتماد أول مبلغ يصدر أمر العمل
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <button
                v-for="card in summaryCards"
                :key="card.key"
                type="button"
                class="group flex min-w-0 flex-col rounded-2xl border bg-white p-5 text-start transition hover:border-gray-300 hover:shadow-sm active:scale-[0.99] dark:bg-neutral-900 dark:hover:border-neutral-600 sm:p-6"
                :class="
                    statusFilter === card.key
                        ? 'border-blue-300 ring-1 ring-blue-100 dark:border-blue-800 dark:ring-blue-950'
                        : 'border-[#E0E0E0] dark:border-neutral-700'
                "
                @click="setStatusFilter(card.key)"
            >
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-gray-400 dark:text-neutral-500 sm:text-xs">
                    {{ card.label }}
                </p>
                <p class="mt-3 text-2xl font-extrabold tabular-nums tracking-tight text-gray-900 dark:text-white sm:text-[1.75rem]">
                    {{ formatInteger(card.value) }}
                    <span class="ms-1 text-base font-bold text-gray-700 dark:text-neutral-300 sm:text-lg">{{ card.unit }}</span>
                </p>
                <p class="mt-4 flex items-center gap-1.5 text-xs font-medium text-[#5B8A72] dark:text-teal-400/90">
                    <ArrowUpRight class="size-3.5 shrink-0 stroke-[2.25]" />
                    <span>{{ card.hint }}</span>
                </p>
            </button>
        </div>

        <div class="overflow-x-auto">
            <div class="flex min-w-max items-center gap-1 border-b border-gray-200 dark:border-neutral-700">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.key"
                    type="button"
                    class="relative px-3 py-2.5 text-sm font-medium transition-colors sm:px-4"
                    :class="
                        statusFilter === tab.key
                            ? 'text-blue-700 dark:text-blue-300'
                            : 'text-gray-500 hover:text-gray-800 dark:text-neutral-400 dark:hover:text-neutral-200'
                    "
                    @click="setStatusFilter(tab.key)"
                >
                    {{ tab.label }}
                    <span class="ms-1.5 text-xs tabular-nums text-gray-400">({{ formatInteger(tabCount(tab.key)) }})</span>
                    <span
                        v-if="statusFilter === tab.key"
                        class="absolute inset-x-0 -bottom-px h-0.5 rounded-full bg-blue-600"
                    />
                </button>
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
                            placeholder="ابحث برقم السند أو الطلب أو العميل..."
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
                <table class="w-full min-w-[1100px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-start dark:border-neutral-800">
                            <th class="w-12 px-4 py-3.5">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    :checked="allVisibleSelected"
                                    @change="toggleSelectAll"
                                />
                            </th>
                            <th class="w-10 px-2 py-3.5" />
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">السند</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">العميل</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">المبلغ</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الإجمالي</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">المتبقي</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الحالة</th>
                            <th class="px-4 py-3.5 text-end text-[13px] font-semibold text-gray-700 dark:text-neutral-200" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="receipts.data.length === 0">
                            <td colspan="9" class="px-4 py-16 text-center text-gray-500 dark:text-neutral-400">
                                لا توجد سندات مطابقة للبحث أو الفلتر الحالي.
                            </td>
                        </tr>
                        <template v-for="row in receipts.data" :key="row.id">
                            <tr
                                class="cursor-pointer border-b border-gray-100 transition hover:bg-gray-50/70 dark:border-neutral-800 dark:hover:bg-neutral-800/40"
                                :class="expandedReceiptId === row.id ? 'bg-gray-50/80 dark:bg-neutral-800/30' : ''"
                                @click="toggleExpand(row)"
                            >
                                <td class="px-4 py-4" @click.stop>
                                    <input
                                        type="checkbox"
                                        class="size-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        :checked="selectedIds.includes(row.id)"
                                        @change="toggleSelect(row.id)"
                                    />
                                </td>
                                <td class="px-2 py-4 text-center">
                                    <ChevronDown
                                        class="mx-auto size-4 text-gray-400 transition-transform"
                                        :class="expandedReceiptId === row.id ? 'rotate-180' : ''"
                                    />
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex flex-col items-start gap-0.5">
                                        <p class="font-semibold tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                            {{ row.receipt_number }}
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            طلب:
                                            <span class="tabular-nums" dir="ltr">{{ row.order?.order_number || '—' }}</span>
                                            ·
                                            <span v-if="row.created_at" dir="ltr">{{ formatDate(row.created_at) }}</span>
                                            <span v-else>—</span>
                                        </p>
                                        <span
                                            v-if="hasNotes(row)"
                                            class="mt-1 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800 ring-1 ring-inset ring-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900/50"
                                        >
                                            <StickyNote class="size-3" />
                                            ملاحظات
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex min-w-0 flex-col items-start gap-0.5">
                                        <p class="font-semibold text-gray-900 dark:text-white">
                                            {{ row.order?.customer_name || '—' }}
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            {{ typeLabel(row.type) }} · {{ paymentMethodLabel(row.payment_method) }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-3 py-4 font-semibold tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                    {{ formatCurrency(row.amount, row.order?.currency || 'SAR') }}
                                </td>
                                <td class="px-3 py-4 tabular-nums text-gray-600 dark:text-neutral-300" dir="ltr">
                                    {{ formatCurrency(row.total_amount, row.order?.currency || 'SAR') }}
                                </td>
                                <td class="px-3 py-4 font-semibold tabular-nums" dir="ltr">
                                    <span :class="row.remaining_amount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'">
                                        {{ formatCurrency(row.remaining_amount, row.order?.currency || 'SAR') }}
                                    </span>
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex flex-col items-start gap-1">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="statusBadgeClass(row)"
                                        >
                                            <CheckCircle2 v-if="row.is_approved" class="size-3.5" />
                                            <XCircle v-else-if="row.is_rejected" class="size-3.5" />
                                            {{ statusLabel(row) }}
                                        </span>
                                        <span
                                            v-if="row.is_approved && row.approved_by_name"
                                            class="text-[11px] text-gray-400"
                                        >
                                            {{ row.approved_by_name }}
                                        </span>
                                        <span
                                            v-else-if="row.is_rejected && row.rejected_by_name"
                                            class="text-[11px] text-gray-400"
                                        >
                                            {{ row.rejected_by_name }}
                                        </span>
                                        <span
                                            v-if="row.is_rejected && row.rejection_reason"
                                            class="max-w-[220px] text-[11px] leading-snug text-red-600/90 dark:text-red-400"
                                        >
                                            السبب: {{ row.rejection_reason }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4" @click.stop>
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            v-if="row.can_approve"
                                            type="button"
                                            class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-blue-600 px-3 text-xs font-medium text-white transition hover:bg-blue-700 disabled:opacity-60"
                                            :disabled="approvingId === row.id || rejectForm.processing"
                                            @click="approveReceipt(row)"
                                        >
                                            <CheckCircle2 class="size-3.5" />
                                            {{ approvingId === row.id ? 'جاري...' : 'اعتماد' }}
                                        </button>
                                        <button
                                            v-if="row.can_reject"
                                            type="button"
                                            class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 text-xs font-medium text-red-700 transition hover:bg-red-50 disabled:opacity-60 dark:border-red-900/60 dark:bg-neutral-900 dark:text-red-300 dark:hover:bg-red-950/40"
                                            :disabled="approvingId === row.id || rejectForm.processing"
                                            @click="openRejectDialog(row)"
                                        >
                                            <XCircle class="size-3.5" />
                                            رفض
                                        </button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <button
                                                    type="button"
                                                    class="inline-flex size-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                                                >
                                                    <MoreVertical class="size-4" />
                                                </button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" class="min-w-44">
                                                <DropdownMenuItem v-if="receiptPdfUrl(row)" as-child>
                                                    <a
                                                        :href="receiptPdfUrl(row)!"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="gap-2"
                                                    >
                                                        <FileText class="size-4" />
                                                        عرض السند
                                                    </a>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem v-if="orderUrl(row)" as-child>
                                                    <Link :href="orderUrl(row)!" class="gap-2">
                                                        <Eye class="size-4" />
                                                        عرض الطلب
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator v-if="row.can_approve || row.can_reject" />
                                                <DropdownMenuItem
                                                    v-if="row.can_approve"
                                                    class="gap-2"
                                                    :disabled="approvingId === row.id"
                                                    @click="approveReceipt(row)"
                                                >
                                                    <CheckCircle2 class="size-4" />
                                                    اعتماد المبلغ
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    v-if="row.can_reject"
                                                    class="gap-2 text-red-600 focus:text-red-700"
                                                    :disabled="rejectForm.processing"
                                                    @click="openRejectDialog(row)"
                                                >
                                                    <XCircle class="size-4" />
                                                    رفض السند
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="expandedReceiptId === row.id" class="border-b border-gray-100 bg-gray-50/70 dark:border-neutral-800 dark:bg-neutral-800/20">
                                <td colspan="9" class="p-4">
                                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(260px,1fr)]">
                                        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900">
                                            <div class="mb-3 flex items-center gap-2">
                                                <UserRound class="size-4 text-gray-400" />
                                                <p class="font-semibold text-gray-900 dark:text-white">بيانات العميل</p>
                                                <span
                                                    v-if="row.customer?.type"
                                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-neutral-800 dark:text-neutral-300"
                                                >
                                                    {{ row.customer.type }}
                                                </span>
                                            </div>
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <div>
                                                    <p class="text-xs text-gray-400">الاسم</p>
                                                    <p class="mt-0.5 font-medium text-gray-900 dark:text-white">
                                                        {{ row.customer?.name || row.order?.customer_name || '—' }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-400">الجوال</p>
                                                    <p class="mt-0.5 flex items-center gap-1.5 font-medium tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                                        <Phone class="size-3.5 text-gray-400" />
                                                        {{ row.customer?.phone || '—' }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-400">جوال إضافي</p>
                                                    <p class="mt-0.5 font-medium tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                                        {{ row.customer?.phone_secondary || 'غير مسجّل' }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-400">البريد</p>
                                                    <p class="mt-0.5 flex items-center gap-1.5 font-medium text-gray-900 dark:text-white">
                                                        <Mail class="size-3.5 shrink-0 text-gray-400" />
                                                        <span class="truncate">{{ row.customer?.email || '—' }}</span>
                                                    </p>
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <p class="text-xs text-gray-400">العنوان</p>
                                                    <p class="mt-0.5 flex items-start gap-1.5 font-medium text-gray-900 dark:text-white">
                                                        <MapPin class="mt-0.5 size-3.5 shrink-0 text-gray-400" />
                                                        <span>{{ row.customer?.address || '—' }}</span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900">
                                            <div class="mb-3 flex items-center gap-2">
                                                <CreditCard class="size-4 text-gray-400" />
                                                <p class="font-semibold text-gray-900 dark:text-white">بيانات الآيبان</p>
                                            </div>
                                            <div class="space-y-3">
                                                <div>
                                                    <p class="text-xs text-gray-400">رقم الآيبان</p>
                                                    <p
                                                        v-if="row.customer?.iban"
                                                        class="mt-0.5 break-all font-medium tabular-nums text-gray-900 dark:text-white"
                                                        dir="ltr"
                                                    >
                                                        {{ row.customer.iban }}
                                                    </p>
                                                    <p v-else class="mt-0.5 text-sm text-gray-400">غير مسجّل</p>
                                                </div>
                                                <div>
                                                    <p class="mb-1.5 text-xs text-gray-400">صورة الآيبان</p>
                                                    <a
                                                        v-if="row.customer?.iban_image_url"
                                                        :href="row.customer.iban_image_url"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="block overflow-hidden rounded-lg border border-gray-200 dark:border-neutral-700"
                                                        @click.stop
                                                    >
                                                        <img
                                                            :src="row.customer.iban_image_url"
                                                            alt="صورة الآيبان"
                                                            class="max-h-48 w-full bg-gray-50 object-contain dark:bg-neutral-800"
                                                        />
                                                    </a>
                                                    <p v-else class="text-sm text-gray-400">لا توجد صورة مسجّلة</p>
                                                </div>
                                                <div>
                                                    <p class="mb-1.5 text-xs text-gray-400">رقم الحساب</p>
                                                    <p
                                                        v-if="row.account_number"
                                                        class="mt-0.5 break-all font-medium tabular-nums text-gray-900 dark:text-white"
                                                        dir="ltr"
                                                    >
                                                        {{ row.account_number }}
                                                    </p>
                                                    <p v-else class="mt-0.5 text-sm text-gray-400">غير مسجّل</p>
                                                </div>
                                                <div>
                                                    <p class="mb-1.5 text-xs text-gray-400">صور التحويل / إيصال الدفع</p>
                                                    <div
                                                        v-if="(row.proof_image_urls?.length || (row.proof_image_url ? 1 : 0))"
                                                        class="grid grid-cols-2 gap-2"
                                                    >
                                                        <a
                                                            v-for="(url, imgIndex) in (row.proof_image_urls?.length ? row.proof_image_urls : [row.proof_image_url!])"
                                                            :key="`${row.id}-proof-${imgIndex}`"
                                                            :href="url"
                                                            target="_blank"
                                                            rel="noopener"
                                                            class="block overflow-hidden rounded-lg border border-gray-200 dark:border-neutral-700"
                                                            @click.stop
                                                        >
                                                            <img
                                                                :src="url"
                                                                :alt="`صورة إيصال الدفع ${imgIndex + 1}`"
                                                                class="aspect-square w-full bg-gray-50 object-cover dark:bg-neutral-800"
                                                            />
                                                        </a>
                                                    </div>
                                                    <p v-else class="text-sm text-gray-400">لا توجد صور مرفقة</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="hasNotes(row)"
                                        class="mt-4 rounded-xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-900/50 dark:bg-amber-950/20"
                                    >
                                        <div class="mb-3 flex items-center gap-2">
                                            <StickyNote class="size-4 text-amber-700 dark:text-amber-300" />
                                            <p class="font-semibold text-gray-900 dark:text-white">الملاحظات</p>
                                        </div>
                                        <div class="space-y-3">
                                            <div v-if="orderNotes(row)">
                                                <p class="text-xs text-amber-800/80 dark:text-amber-300/80">ملاحظات الطلب</p>
                                                <p class="mt-1 whitespace-pre-wrap text-sm font-medium leading-relaxed text-gray-900 dark:text-white">
                                                    {{ orderNotes(row) }}
                                                </p>
                                            </div>
                                            <div v-if="receiptNotes(row)">
                                                <p class="text-xs text-amber-800/80 dark:text-amber-300/80">ملاحظات السند</p>
                                                <p class="mt-1 whitespace-pre-wrap text-sm font-medium leading-relaxed text-gray-900 dark:text-white">
                                                    {{ receiptNotes(row) }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-100 px-4 py-4 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-500 dark:text-neutral-400">
                    عرض {{ formatInteger(receipts.from ?? 0) }} - {{ formatInteger(receipts.to ?? 0) }} من {{ formatInteger(receipts.total) }}
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

        <Dialog :open="rejectDialogOpen" @update:open="(open) => !open && closeRejectDialog()">
            <DialogContent class="max-w-md sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>رفض سند القبض</DialogTitle>
                    <DialogDescription v-if="rejectTarget">
                        لن يُطبَّق المبلغ على الطلب ولن يصدر أمر عمل.
                        السند
                        <span class="font-semibold tabular-nums" dir="ltr">{{ rejectTarget.receipt_number }}</span>
                        —
                        مبلغ
                        <span class="font-semibold tabular-nums" dir="ltr">
                            {{ formatCurrency(rejectTarget.amount, rejectTarget.order?.currency || 'SAR') }}
                        </span>
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitReject">
                    <div class="space-y-2">
                        <Label for="rejection-reason">سبب الرفض</Label>
                        <textarea
                            id="rejection-reason"
                            v-model="rejectForm.rejection_reason"
                            rows="4"
                            class="flex min-h-[100px] w-full rounded-xl border border-input bg-background px-3 py-2 text-sm outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                            placeholder="اكتب سبب رفض السند..."
                            required
                        />
                        <p v-if="rejectForm.errors.rejection_reason" class="text-xs text-red-600">
                            {{ rejectForm.errors.rejection_reason }}
                        </p>
                    </div>

                    <DialogFooter class="gap-2 sm:justify-start">
                        <Button
                            type="submit"
                            variant="destructive"
                            class="h-10 gap-2 rounded-xl"
                            :disabled="rejectForm.processing || !rejectForm.rejection_reason.trim()"
                        >
                            <XCircle class="size-4" />
                            {{ rejectForm.processing ? 'جاري الرفض...' : 'تأكيد الرفض' }}
                        </Button>
                        <Button type="button" variant="outline" class="h-10 rounded-xl" @click="closeRejectDialog">
                            إلغاء
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
