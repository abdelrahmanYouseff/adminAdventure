<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
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
    tax_number?: string | null;
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
    recorded_by_name?: string | null;
    notes?: string | null;
    proof_image_url?: string | null;
    proof_image_urls?: string[];
    account_number?: string | null;
}

interface OrderGroup {
    id: number | null;
    order_number: string | null;
    customer_name: string | null;
    currency: string;
    notes: string | null;
    total_amount: number;
    amount_paid: number;
    remaining_amount: number;
    receipts: ReceiptRow[];
}

interface CustomerGroup {
    key: string;
    customer: CustomerProfile;
    customer_name?: string | null;
    orders_count: number;
    receipts_count: number;
    pending_count: number;
    approved_count: number;
    rejected_count: number;
    pending_amount: number;
    amount_paid: number;
    total_amount: number;
    remaining_amount: number;
    currency: string;
    latest_receipt_id: number;
    latest_at: string | null;
    has_notes: boolean;
    orders: OrderGroup[];
}

type StatusTab = 'all' | 'pending' | 'approved' | 'rejected';

interface Props {
    groups: {
        data: CustomerGroup[];
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
const expandedGroupKey = ref<string | null>(null);
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
        hint: 'عرض كل العملاء',
    },
    {
        key: 'pending' as const,
        label: 'بانتظار الاعتماد',
        value: props.statusCounts.pending,
        unit: 'سند',
        hint: 'عملاء لديهم سند معلّق',
    },
    {
        key: 'approved' as const,
        label: 'معتمدة',
        value: props.statusCounts.approved,
        unit: 'سند',
        hint: 'عملاء لديهم سند معتمد',
    },
    {
        key: 'rejected' as const,
        label: 'مرفوضة',
        value: props.statusCounts.rejected ?? 0,
        unit: 'سند',
        hint: 'عملاء لديهم سند مرفوض',
    },
]);

const pageNumbers = computed(() => {
    const total = props.groups.last_page;
    const current = props.groups.current_page;
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
        statusFilter.value = (filters?.status as StatusTab) || 'all';
        perPage.value = filters?.per_page || 15;
        expandedGroupKey.value = null;
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
    if (pageNum >= 1 && pageNum <= props.groups.last_page) {
        applyFilters(pageNum);
    }
}

function tabCount(tab: StatusTab): number {
    return props.statusCounts?.[tab] ?? 0;
}

function toggleExpand(group: CustomerGroup) {
    expandedGroupKey.value = expandedGroupKey.value === group.key ? null : group.key;
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

function orderUrl(order: OrderGroup): string | null {
    if (!order.id) return null;
    return `/orders/${order.id}`;
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

function displayReceiptNotes(row: ReceiptRow, orderNotes: string | null): string | null {
    const notes = row.notes?.trim();
    if (!notes || SYSTEM_RECEIPT_NOTES.includes(notes)) {
        return null;
    }

    if (orderNotes?.trim() && notes === orderNotes.trim()) {
        return null;
    }

    return notes;
}

function groupNotes(group: CustomerGroup): string[] {
    const notes: string[] = [];
    for (const order of group.orders) {
        const orderNote = order.notes?.trim();
        if (orderNote && !notes.includes(orderNote)) {
            notes.push(orderNote);
        }
        for (const receipt of order.receipts) {
            const extra = displayReceiptNotes(receipt, order.notes);
            if (extra && !notes.includes(extra)) {
                notes.push(extra);
            }
        }
    }
    return notes;
}

function proofUrls(row: ReceiptRow): string[] {
    if (row.proof_image_urls?.length) {
        return row.proof_image_urls;
    }
    return row.proof_image_url ? [row.proof_image_url] : [];
}

function customerDisplayName(group: CustomerGroup): string {
    return group.customer_name
        || group.orders[0]?.customer_name
        || group.customer?.name
        || '—';
}

function firstPendingReceipt(group: CustomerGroup): ReceiptRow | null {
    for (const order of group.orders) {
        const pending = order.receipts.find((receipt) => receipt.can_approve);
        if (pending) return pending;
    }
    return null;
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
                    كل عميل يظهر مرة واحدة، مع كل طلباته وإيصالات الدفع السابقة مرتّبة
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
                            placeholder="ابحث بالعميل أو رقم الطلب أو السند..."
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
                    <span>من {{ formatInteger(groups.total) }} عميل</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-neutral-800">
                            <th class="w-10 px-2 py-3.5" />
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">اسم العميل</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">المبلغ المدفوع</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">المتبقي</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الإجمالي</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الحالة</th>
                            <th class="w-40 px-4 py-3.5" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="groups.data.length === 0">
                            <td colspan="7" class="px-4 py-16 text-center text-gray-500 dark:text-neutral-400">
                                لا توجد سندات مطابقة للبحث أو الفلتر الحالي.
                            </td>
                        </tr>
                        <template v-for="group in groups.data" :key="group.key">
                            <tr
                                class="cursor-pointer border-b border-gray-100 transition hover:bg-gray-50/70 dark:border-neutral-800 dark:hover:bg-neutral-800/40"
                                :class="expandedGroupKey === group.key ? 'bg-gray-50/80 dark:bg-neutral-800/30' : ''"
                                @click="toggleExpand(group)"
                            >
                                <td class="px-2 py-4 text-center">
                                    <ChevronDown
                                        class="mx-auto size-4 text-gray-400 transition-transform"
                                        :class="expandedGroupKey === group.key ? 'rotate-180' : ''"
                                    />
                                </td>
                                <td class="px-3 py-4 text-start">
                                    <div class="flex min-w-0 flex-col items-start gap-0.5">
                                        <p class="font-semibold text-gray-900 dark:text-white">
                                            {{ customerDisplayName(group) }}
                                        </p>
                                        <p class="text-xs tabular-nums text-gray-400" dir="ltr">
                                            {{ group.customer?.phone || '—' }}
                                        </p>
                                        <span
                                            v-if="group.has_notes"
                                            class="mt-1 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800 ring-1 ring-inset ring-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900/50"
                                        >
                                            <StickyNote class="size-3" />
                                            ملاحظات
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-4 text-start font-semibold tabular-nums text-gray-900 dark:text-white">
                                    <span dir="ltr">{{ formatCurrency(group.amount_paid, group.currency) }}</span>
                                </td>
                                <td class="px-3 py-4 text-start font-semibold tabular-nums">
                                    <span
                                        dir="ltr"
                                        :class="group.remaining_amount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'"
                                    >
                                        {{ formatCurrency(group.remaining_amount, group.currency) }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 text-start font-semibold tabular-nums text-gray-900 dark:text-white">
                                    <span dir="ltr">{{ formatCurrency(group.total_amount, group.currency) }}</span>
                                </td>
                                <td class="px-3 py-4 text-start">
                                    <div class="flex flex-wrap items-center justify-start gap-1.5">
                                        <span
                                            v-if="group.pending_count"
                                            class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900/50"
                                        >
                                            {{ formatInteger(group.pending_count) }} معلّق
                                        </span>
                                        <span
                                            v-if="group.approved_count"
                                            class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50"
                                        >
                                            {{ formatInteger(group.approved_count) }} معتمد
                                        </span>
                                        <span
                                            v-if="group.rejected_count"
                                            class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-100 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900/50"
                                        >
                                            {{ formatInteger(group.rejected_count) }} مرفوض
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4" @click.stop>
                                    <div v-if="group.pending_count === 1 && firstPendingReceipt(group)" class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-blue-600 px-3 text-xs font-medium text-white transition hover:bg-blue-700 disabled:opacity-60"
                                            :disabled="approvingId === firstPendingReceipt(group)!.id || rejectForm.processing"
                                            @click="approveReceipt(firstPendingReceipt(group)!)"
                                        >
                                            <CheckCircle2 class="size-3.5" />
                                            {{ approvingId === firstPendingReceipt(group)!.id ? 'جاري...' : 'اعتماد' }}
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 text-xs font-medium text-red-700 transition hover:bg-red-50 disabled:opacity-60 dark:border-red-900/60 dark:bg-neutral-900 dark:text-red-300 dark:hover:bg-red-950/40"
                                            :disabled="approvingId !== null || rejectForm.processing"
                                            @click="openRejectDialog(firstPendingReceipt(group)!)"
                                        >
                                            <XCircle class="size-3.5" />
                                            رفض
                                        </button>
                                    </div>
                                    <p v-else-if="group.pending_count > 1" class="text-end text-xs text-gray-400">
                                        افتح التفاصيل لاعتماد السندات
                                    </p>
                                </td>
                            </tr>

                            <tr v-if="expandedGroupKey === group.key" class="border-b border-gray-100 bg-gray-50/70 dark:border-neutral-800 dark:bg-neutral-800/20">
                                <td colspan="7" class="p-4">
                                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(260px,1fr)]">
                                        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900">
                                            <div class="mb-3 flex items-center gap-2">
                                                <UserRound class="size-4 text-gray-400" />
                                                <p class="font-semibold text-gray-900 dark:text-white">بيانات العميل</p>
                                                <span
                                                    v-if="group.customer?.type"
                                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-neutral-800 dark:text-neutral-300"
                                                >
                                                    {{ group.customer.type }}
                                                </span>
                                            </div>
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <div>
                                                    <p class="text-xs text-gray-400">الاسم</p>
                                                    <p class="mt-0.5 font-medium text-gray-900 dark:text-white">
                                                        {{ customerDisplayName(group) }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-400">الجوال</p>
                                                    <p class="mt-0.5 flex items-center gap-1.5 font-medium tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                                        <Phone class="size-3.5 text-gray-400" />
                                                        {{ group.customer?.phone || '—' }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-400">جوال إضافي</p>
                                                    <p class="mt-0.5 font-medium tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                                        {{ group.customer?.phone_secondary || 'غير مسجّل' }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-400">البريد</p>
                                                    <p class="mt-0.5 flex items-center gap-1.5 font-medium text-gray-900 dark:text-white">
                                                        <Mail class="size-3.5 shrink-0 text-gray-400" />
                                                        <span class="truncate">{{ group.customer?.email || '—' }}</span>
                                                    </p>
                                                </div>
                                                <div v-if="group.customer?.tax_number" class="sm:col-span-2">
                                                    <p class="text-xs text-gray-400">الرقم الضريبي</p>
                                                    <p class="mt-0.5 font-medium tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                                        {{ group.customer.tax_number }}
                                                    </p>
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <p class="text-xs text-gray-400">العنوان</p>
                                                    <p class="mt-0.5 flex items-start gap-1.5 font-medium text-gray-900 dark:text-white">
                                                        <MapPin class="mt-0.5 size-3.5 shrink-0 text-gray-400" />
                                                        <span>{{ group.customer?.address || '—' }}</span>
                                                    </p>
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <p class="flex items-center gap-1.5 text-xs text-gray-400">
                                                        <StickyNote class="size-3.5" />
                                                        الملاحظات
                                                    </p>
                                                    <div
                                                        v-if="groupNotes(group).length"
                                                        class="mt-1 space-y-2 rounded-lg bg-amber-50 px-3 py-2 dark:bg-amber-950/30"
                                                    >
                                                        <p
                                                            v-for="note in groupNotes(group)"
                                                            :key="note"
                                                            class="whitespace-pre-wrap text-sm font-medium leading-relaxed text-gray-900 dark:text-white"
                                                        >
                                                            {{ note }}
                                                        </p>
                                                    </div>
                                                    <p v-else class="mt-0.5 text-sm text-gray-400">لا توجد ملاحظات على الطلب</p>
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
                                                        v-if="group.customer?.iban"
                                                        class="mt-0.5 break-all font-medium tabular-nums text-gray-900 dark:text-white"
                                                        dir="ltr"
                                                    >
                                                        {{ group.customer.iban }}
                                                    </p>
                                                    <p v-else class="mt-0.5 text-sm text-gray-400">غير مسجّل</p>
                                                </div>
                                                <div>
                                                    <p class="mb-1.5 text-xs text-gray-400">صورة الآيبان</p>
                                                    <a
                                                        v-if="group.customer?.iban_image_url"
                                                        :href="group.customer.iban_image_url"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="block overflow-hidden rounded-lg border border-gray-200 dark:border-neutral-700"
                                                        @click.stop
                                                    >
                                                        <img
                                                            :src="group.customer.iban_image_url"
                                                            alt="صورة الآيبان"
                                                            class="max-h-48 w-full bg-gray-50 object-contain dark:bg-neutral-800"
                                                        />
                                                    </a>
                                                    <p v-else class="text-sm text-gray-400">لا توجد صورة مسجّلة</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4 space-y-4">
                                        <div
                                            v-for="order in group.orders"
                                            :key="order.id || order.order_number"
                                            class="rounded-xl border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900"
                                        >
                                            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <p class="font-semibold text-gray-900 dark:text-white" dir="ltr">
                                                        {{ order.order_number || '—' }}
                                                    </p>
                                                    <p class="mt-0.5 text-xs text-gray-400">
                                                        إجمالي
                                                        <span class="tabular-nums" dir="ltr">{{ formatCurrency(order.total_amount, order.currency) }}</span>
                                                        · مدفوع
                                                        <span class="tabular-nums" dir="ltr">{{ formatCurrency(order.amount_paid, order.currency) }}</span>
                                                        · متبقي
                                                        <span class="tabular-nums" dir="ltr">{{ formatCurrency(order.remaining_amount, order.currency) }}</span>
                                                    </p>
                                                </div>
                                                <Link
                                                    v-if="orderUrl(order)"
                                                    :href="orderUrl(order)!"
                                                    class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-gray-200 px-3 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                                                    @click.stop
                                                >
                                                    <Eye class="size-3.5" />
                                                    عرض الطلب
                                                </Link>
                                            </div>

                                            <p class="mb-2 text-xs font-semibold text-gray-500">سجل الإيصالات حسب الدفعات</p>
                                            <div class="space-y-3">
                                                <div
                                                    v-for="receipt in order.receipts"
                                                    :key="receipt.id"
                                                    class="rounded-xl border border-gray-100 p-3 dark:border-neutral-800"
                                                >
                                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                                        <div>
                                                            <p class="font-semibold tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                                                {{ receipt.receipt_number }}
                                                            </p>
                                                            <p class="mt-0.5 text-xs text-gray-400">
                                                                {{ typeLabel(receipt.type) }}
                                                                · {{ paymentMethodLabel(receipt.payment_method) }}
                                                                <span v-if="receipt.created_at"> · {{ formatDate(receipt.created_at) }}</span>
                                                                <span v-if="receipt.recorded_by_name"> · {{ receipt.recorded_by_name }}</span>
                                                            </p>
                                                        </div>
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span
                                                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold"
                                                                :class="statusBadgeClass(receipt)"
                                                            >
                                                                <CheckCircle2 v-if="receipt.is_approved" class="size-3.5" />
                                                                <XCircle v-else-if="receipt.is_rejected" class="size-3.5" />
                                                                {{ statusLabel(receipt) }}
                                                            </span>
                                                            <span class="text-sm font-semibold tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                                                {{ formatCurrency(receipt.amount, order.currency) }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <p
                                                        v-if="receipt.is_rejected && receipt.rejection_reason"
                                                        class="mt-2 text-xs leading-snug text-red-600/90 dark:text-red-400"
                                                    >
                                                        السبب: {{ receipt.rejection_reason }}
                                                        <span v-if="receipt.rejected_by_name"> — {{ receipt.rejected_by_name }}</span>
                                                    </p>
                                                    <p
                                                        v-else-if="receipt.is_approved && receipt.approved_by_name"
                                                        class="mt-2 text-xs text-gray-400"
                                                    >
                                                        اعتمد بواسطة {{ receipt.approved_by_name }}
                                                    </p>

                                                    <p
                                                        v-if="displayReceiptNotes(receipt, order.notes)"
                                                        class="mt-2 whitespace-pre-wrap text-xs text-gray-600 dark:text-neutral-300"
                                                    >
                                                        {{ displayReceiptNotes(receipt, order.notes) }}
                                                    </p>

                                                    <div class="mt-3">
                                                        <p class="mb-1.5 text-xs text-gray-400">صور التحويل / إيصال الدفع</p>
                                                        <div v-if="proofUrls(receipt).length" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                                            <a
                                                                v-for="(url, imgIndex) in proofUrls(receipt)"
                                                                :key="`${receipt.id}-proof-${imgIndex}`"
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

                                                    <div v-if="receipt.account_number" class="mt-2">
                                                        <p class="text-xs text-gray-400">رقم الحساب</p>
                                                        <p class="mt-0.5 break-all text-sm font-medium tabular-nums" dir="ltr">
                                                            {{ receipt.account_number }}
                                                        </p>
                                                    </div>

                                                    <div class="mt-3 flex flex-wrap items-center gap-2" @click.stop>
                                                        <button
                                                            v-if="receipt.can_approve"
                                                            type="button"
                                                            class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-blue-600 px-3 text-xs font-medium text-white transition hover:bg-blue-700 disabled:opacity-60"
                                                            :disabled="approvingId === receipt.id || rejectForm.processing"
                                                            @click="approveReceipt(receipt)"
                                                        >
                                                            <CheckCircle2 class="size-3.5" />
                                                            {{ approvingId === receipt.id ? 'جاري...' : 'اعتماد' }}
                                                        </button>
                                                        <button
                                                            v-if="receipt.can_reject"
                                                            type="button"
                                                            class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 text-xs font-medium text-red-700 transition hover:bg-red-50 disabled:opacity-60 dark:border-red-900/60 dark:bg-neutral-900 dark:text-red-300 dark:hover:bg-red-950/40"
                                                            :disabled="approvingId !== null || rejectForm.processing"
                                                            @click="openRejectDialog(receipt)"
                                                        >
                                                            <XCircle class="size-3.5" />
                                                            رفض
                                                        </button>
                                                        <a
                                                            v-if="receiptPdfUrl(receipt)"
                                                            :href="receiptPdfUrl(receipt)!"
                                                            target="_blank"
                                                            rel="noopener"
                                                            class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-gray-200 px-3 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                                                        >
                                                            <FileText class="size-3.5" />
                                                            عرض السند
                                                        </a>
                                                    </div>
                                                </div>
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
                    عرض {{ formatInteger(groups.from ?? 0) }} - {{ formatInteger(groups.to ?? 0) }} من {{ formatInteger(groups.total) }} عميل
                </p>

                <div v-if="groups.last_page > 1" class="flex items-center justify-center gap-1.5 sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 disabled:opacity-40 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800"
                        :disabled="groups.current_page <= 1"
                        @click="goToPage(groups.current_page - 1)"
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
                                groups.current_page === item
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
                        :disabled="groups.current_page >= groups.last_page"
                        @click="goToPage(groups.current_page + 1)"
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
