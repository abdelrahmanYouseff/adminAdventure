<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    ArrowUpRight,
    Camera,
    Check,
    ChevronLeft,
    ChevronRight,
    Copy,
    ExternalLink,
    Eye,
    MapPin,
    MoreVertical,
    Pencil,
    Plus,
    Search,
    ShoppingCart,
    Trash2,
    UploadCloud,
    Wallet,
    Wrench,
    X,
} from 'lucide-vue-next';
import { formatCurrency, formatDate, formatDateTime, formatInteger } from '@/lib/formatNumber';

interface InstallationPhoto {
    product_name: string;
    url: string;
}

interface InstallationMeta {
    status: string;
    label: string;
    progress_done: number;
    progress_total: number;
    approved_at?: string | null;
    has_photos: boolean;
    can_review_photos: boolean;
    photos: InstallationPhoto[];
}

interface DismantlingMeta {
    status: string;
    label: string;
    scheduled_at?: string | null;
    progress_done: number;
    progress_total: number;
}

interface Order {
    id: number;
    order_number: string;
    customer_name: string;
    customer_email: string | null;
    customer_phone: string | null;
    total_amount: number;
    amount_paid?: number | string | null;
    remaining_amount?: number | string | null;
    settle_available?: number | string | null;
    due_amount?: number | string | null;
    vat_amount?: number | string | null;
    tax_amount?: number | string | null;
    can_settle?: boolean;
    can_edit?: boolean;
    can_delete?: boolean;
    payment_url?: string | null;
    currency: string;
    payment_method: string;
    status: string;
    activity_date: string | null;
    activity_time: string | null;
    can_edit_activity_time?: boolean;
    address: string | null;
    created_at: string;
    installation?: InstallationMeta | null;
    dismantling?: DismantlingMeta | null;
}

type StatusTab = 'all' | 'pending' | 'processing' | 'paid' | 'cancelled' | 'refunded';

interface Props {
    orders: {
        data: Order[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        per_page: number;
    };
    filters: {
        search?: string;
        status?: string;
        currency?: string;
        per_page?: number;
    };
    statusCounts?: Record<StatusTab, number>;
    canManageActivityTime?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({}),
    statusCounts: () => ({
        all: 0,
        pending: 0,
        processing: 0,
        paid: 0,
        cancelled: 0,
        refunded: 0,
    }),
    canManageActivityTime: false,
});

defineOptions({ layout: AppLayout });

const page = usePage();
const userRole = computed(() => (page.props.auth as { user?: { role?: string } } | undefined)?.user?.role ?? null);
const canCreateOrders = computed(() =>
    ['admin', 'general_manager', 'manager'].includes(userRole.value ?? ''),
);
const canDeleteOrders = computed(() => canCreateOrders.value);

const searchInput = ref(props.filters?.search ?? '');
const statusFilter = ref<StatusTab>((props.filters?.status as StatusTab) ?? 'all');
const currencyFilter = ref(props.filters?.currency ?? 'all');
const perPage = ref(props.filters?.per_page || 15);
const selectedIds = ref<number[]>([]);

const settleDialogOpen = ref(false);
const settleOrder = ref<Order | null>(null);
const paymentProofPreviews = ref<string[]>([]);
const paymentProofInput = ref<HTMLInputElement | null>(null);
const photosDialogOpen = ref(false);
const photosDialogOrder = ref<Order | null>(null);
const photosLightbox = ref<string | null>(null);

const settleForm = useForm({
    amount: '' as number | string,
    payment_method: 'bank_transfer',
    payment_proof: [] as File[],
    notes: '',
});

function clearPaymentProofPreviews() {
    paymentProofPreviews.value.forEach((url) => URL.revokeObjectURL(url));
    paymentProofPreviews.value = [];
}

function openSettleDialog(order: Order) {
    settleOrder.value = order;
    settleForm.reset();
    settleForm.clearErrors();
    settleForm.amount = Number(order.settle_available ?? dueAmount(order)) || '';
    settleForm.payment_method = order.payment_method || 'bank_transfer';
    settleForm.payment_proof = [];
    settleForm.notes = '';
    clearPaymentProofPreviews();
    if (paymentProofInput.value) {
        paymentProofInput.value.value = '';
    }
    settleDialogOpen.value = true;
}

function closeSettleDialog() {
    settleDialogOpen.value = false;
    settleOrder.value = null;
    settleForm.reset();
    settleForm.clearErrors();
    clearPaymentProofPreviews();
    if (paymentProofInput.value) {
        paymentProofInput.value.value = '';
    }
}

function handleSettleProofChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    if (!files.length) return;

    const nextFiles = [...settleForm.payment_proof, ...files].slice(0, 10);
    clearPaymentProofPreviews();
    settleForm.payment_proof = nextFiles;
    paymentProofPreviews.value = nextFiles.map((file) => URL.createObjectURL(file));
    settleForm.clearErrors('payment_proof');
    input.value = '';
}

function removeSettleProof(index: number) {
    const nextFiles = settleForm.payment_proof.filter((_, i) => i !== index);
    clearPaymentProofPreviews();
    settleForm.payment_proof = nextFiles;
    paymentProofPreviews.value = nextFiles.map((file) => URL.createObjectURL(file));
    if (paymentProofInput.value) {
        paymentProofInput.value.value = '';
    }
}

function clearAllSettleProofs() {
    clearPaymentProofPreviews();
    settleForm.payment_proof = [];
    if (paymentProofInput.value) {
        paymentProofInput.value.value = '';
    }
}

function fillSettleRemaining() {
    if (!settleOrder.value) return;
    settleForm.amount = Number(settleOrder.value.settle_available ?? dueAmount(settleOrder.value)) || 0;
    settleForm.clearErrors('amount');
}

function submitSettle() {
    if (!settleOrder.value) return;

    const available = Number(settleOrder.value.settle_available ?? 0) || 0;
    if (available <= 0.009) {
        settleForm.setError(
            'amount',
            'يوجد سند قبض بانتظار اعتماد المحاسب يغطي المتبقي. انتظر الاعتماد قبل تسجيل سداد جديد.',
        );
        return;
    }

    const amount = Number(settleForm.amount);
    if (!amount || amount <= 0) {
        settleForm.setError('amount', 'أدخل مبلغ سداد أكبر من صفر.');
        return;
    }

    if (amount > available + 0.009) {
        settleForm.setError('amount', `المبلغ أكبر من المتبقي المتاح للتسجيل (${available}).`);
        return;
    }

    settleForm.clearErrors('amount');
    settleForm.amount = amount;

    settleForm.post(route('orders.settle-payment', settleOrder.value.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => closeSettleDialog(),
    });
}

onBeforeUnmount(() => {
    clearPaymentProofPreviews();
    window.clearTimeout(copiedPaymentLinkTimer);
});

const hourOptions = Array.from({ length: 12 }, (_, i) => String(i + 1));
const minuteOptions = Array.from({ length: 12 }, (_, i) => String(i * 5).padStart(2, '0'));

const editingTime = ref<{
    id: number | null;
    hour: string;
    minute: string;
    period: 'AM' | 'PM';
    saving: boolean;
}>({
    id: null,
    hour: '12',
    minute: '00',
    period: 'PM',
    saving: false,
});

const statusTabs: { key: StatusTab; label: string }[] = [
    { key: 'all', label: 'الكل' },
    { key: 'pending', label: 'قيد الانتظار' },
    { key: 'processing', label: 'قيد المعالجة' },
    { key: 'paid', label: 'مدفوع' },
    { key: 'cancelled', label: 'ملغي' },
    { key: 'refunded', label: 'مسترد' },
];

const summaryCards = computed(() => [
    {
        key: 'all' as const,
        label: 'إجمالي الطلبات',
        value: props.statusCounts.all,
        unit: 'طلب',
        hint: 'عرض كل الطلبات',
    },
    {
        key: 'pending' as const,
        label: 'قيد الانتظار',
        value: props.statusCounts.pending,
        unit: 'طلب',
        hint: 'عرض قيد الانتظار',
    },
    {
        key: 'paid' as const,
        label: 'مدفوع',
        value: props.statusCounts.paid,
        unit: 'طلب',
        hint: 'عرض المدفوع',
    },
    {
        key: 'processing' as const,
        label: 'قيد المعالجة',
        value: props.statusCounts.processing,
        unit: 'طلب',
        hint: 'عرض قيد المعالجة',
    },
]);

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

const allVisibleSelected = computed(
    () =>
        props.orders.data.length > 0
        && props.orders.data.every((order) => selectedIds.value.includes(order.id)),
);

watch(
    () => props.filters,
    (filters) => {
        searchInput.value = filters?.search ?? '';
        statusFilter.value = (filters?.status as StatusTab) ?? 'all';
        currencyFilter.value = filters?.currency ?? 'all';
        perPage.value = filters?.per_page || 15;
        selectedIds.value = [];
    },
);

function applyFilters(pageNum = 1) {
    router.get(route('orders.index'), {
        search: searchInput.value.trim() || undefined,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        currency: currencyFilter.value !== 'all' ? currencyFilter.value : undefined,
        per_page: perPage.value !== 15 ? perPage.value : undefined,
        page: pageNum > 1 ? pageNum : undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function setStatusFilter(status: StatusTab) {
    statusFilter.value = status;
    applyFilters(1);
}

function onSearchSubmit() {
    applyFilters(1);
}

function goToPage(pageNum: number) {
    if (pageNum >= 1 && pageNum <= props.orders.last_page) {
        applyFilters(pageNum);
    }
}

function tabCount(tab: StatusTab): number {
    return props.statusCounts?.[tab] ?? 0;
}

function toggleSelectAll() {
    if (allVisibleSelected.value) {
        const visible = new Set(props.orders.data.map((order) => order.id));
        selectedIds.value = selectedIds.value.filter((id) => !visible.has(id));
        return;
    }

    selectedIds.value = Array.from(new Set([
        ...selectedIds.value,
        ...props.orders.data.map((order) => order.id),
    ]));
}

function toggleSelect(id: number) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((item) => item !== id);
        return;
    }
    selectedIds.value = [...selectedIds.value, id];
}

function canDeleteOrder(order: Order): boolean {
    if (order.can_delete === true) return true;
    if (order.can_delete === false) return false;
    return canDeleteOrders.value;
}

function deleteOrder(order: Order) {
    if (!canDeleteOrder(order)) return;
    if (
        confirm(
            `هل تريد حذف الطلب ${order.order_number}؟\nلا يمكن التراجع عن هذا الإجراء.`,
        )
    ) {
        router.delete(route('orders.destroy', order.id), {
            preserveScroll: true,
        });
    }
}

const copiedPaymentLinkId = ref<number | null>(null);
let copiedPaymentLinkTimer: number | undefined;

async function copyPaymentLink(order: Order) {
    if (!order.payment_url) return;

    try {
        await navigator.clipboard.writeText(order.payment_url);
    } catch {
        const input = document.createElement('textarea');
        input.value = order.payment_url;
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        input.remove();
    }

    copiedPaymentLinkId.value = order.id;
    window.clearTimeout(copiedPaymentLinkTimer);
    copiedPaymentLinkTimer = window.setTimeout(() => {
        copiedPaymentLinkId.value = null;
    }, 2500);
}

function getStatusText(status: string): string {
    const map: Record<string, string> = {
        pending: 'قيد الانتظار',
        processing: 'قيد المعالجة',
        paid: 'مدفوع',
        cancelled: 'ملغي',
        refunded: 'مسترد',
    };
    return map[status] || status;
}

function statusBadgeClass(status: string): string {
    const map: Record<string, string> = {
        paid: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50',
        pending: 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900/50',
        processing: 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-100 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-900/50',
        cancelled: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-100 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900/50',
        refunded: 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-100 dark:bg-violet-950/40 dark:text-violet-300 dark:ring-violet-900/50',
    };
    return map[status] || 'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-200';
}

function installBadgeClass(status: string): string {
    const map: Record<string, string> = {
        completed: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50',
        in_progress: 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-100 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-900/50',
        pending: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900/50',
        none: 'bg-gray-50 text-gray-500 ring-1 ring-inset ring-gray-200 dark:bg-neutral-800 dark:text-neutral-400 dark:ring-neutral-700',
    };
    return map[status] || map.none;
}

function dismantleBadgeClass(status: string): string {
    const map: Record<string, string> = {
        returned: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50',
        rejected: 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-100 dark:bg-rose-950/40 dark:text-rose-300 dark:ring-rose-900/50',
        completed: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50',
        in_progress: 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-100 dark:bg-violet-950/40 dark:text-violet-300 dark:ring-violet-900/50',
        pending: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900/50',
        waiting_install: 'bg-slate-50 text-slate-500 ring-1 ring-inset ring-slate-200 dark:bg-neutral-800 dark:text-neutral-400 dark:ring-neutral-700',
        none: 'bg-gray-50 text-gray-500 ring-1 ring-inset ring-gray-200 dark:bg-neutral-800 dark:text-neutral-400 dark:ring-neutral-700',
    };
    return map[status] || map.none;
}

function openPhotosDialog(order: Order) {
    if (!order.installation?.can_review_photos) return;
    photosDialogOrder.value = order;
    photosDialogOpen.value = true;
}

function closePhotosDialog() {
    photosDialogOpen.value = false;
    photosDialogOrder.value = null;
    photosLightbox.value = null;
}

function paidAmount(order: Order): number {
    if (order.status === 'paid') {
        return Number(order.total_amount) || 0;
    }
    return Number(order.amount_paid ?? 0) || 0;
}

function dueAmount(order: Order): number {
    if (order.status === 'paid' || order.status === 'cancelled' || order.status === 'refunded') {
        return 0;
    }
    const due = Number(order.due_amount);
    if (!Number.isNaN(due) && due >= 0) {
        return due;
    }
    const remaining = Number(order.remaining_amount);
    if (!Number.isNaN(remaining) && remaining >= 0) {
        return remaining;
    }
    return Math.max(0, (Number(order.total_amount) || 0) - paidAmount(order));
}

function vatAmount(order: Order): number {
    const vat = Number(order.vat_amount ?? order.tax_amount ?? 0);
    if (!Number.isNaN(vat) && vat > 0) {
        return vat;
    }
    return 0;
}

function canSettleOrder(order: Order): boolean {
    if (order.can_settle === true) return true;
    if (order.can_settle === false) return false;
    return dueAmount(order) > 0.009;
}

function formatActivityTime(time: string | null): string {
    if (!time) return '—';
    const [hourStr, minuteStr = '00'] = time.split(':');
    let hour = Number(hourStr);
    if (Number.isNaN(hour)) return time;
    const period = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;
    return `${hour}:${minuteStr.padStart(2, '0')} ${period}`;
}

function toTwentyFourHour(hour: string, minute: string, period: 'AM' | 'PM'): string {
    let h = Number(hour);
    if (period === 'AM') {
        h = h === 12 ? 0 : h;
    } else {
        h = h === 12 ? 12 : h + 12;
    }
    return `${String(h).padStart(2, '0')}:${minute.padStart(2, '0')}`;
}

function startEditTime(order: Order) {
    if (!order.can_edit_activity_time) return;
    editingTime.value = {
        id: order.id,
        hour: '12',
        minute: '00',
        period: 'PM',
        saving: false,
    };
}

function cancelEditTime() {
    editingTime.value = {
        id: null,
        hour: '12',
        minute: '00',
        period: 'PM',
        saving: false,
    };
}

function saveEditTime(order: Order) {
    const activityTime = toTwentyFourHour(
        editingTime.value.hour,
        editingTime.value.minute,
        editingTime.value.period,
    );

    editingTime.value.saving = true;
    router.patch(
        route('orders.update-activity-time', order.id),
        { activity_time: activityTime },
        {
            preserveScroll: true,
            onFinish: () => {
                editingTime.value.saving = false;
                cancelEditTime();
            },
        },
    );
}

function formatActivityDate(date: string | null): string {
    if (!date) return '—';
    return formatDate(date);
}

function locationMapsUrl(address: string | null): string | null {
    if (!address?.trim()) return null;
    const trimmed = address.trim();
    const coordMatch = trimmed.match(/^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/);
    if (coordMatch) {
        return `https://www.google.com/maps?q=${coordMatch[1]},${coordMatch[2]}`;
    }
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(trimmed)}`;
}
</script>

<template>
    <Head title="إدارة الطلبات" />

    <div class="flex min-w-0 flex-1 flex-col gap-5 overflow-x-hidden p-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-white sm:text-2xl">
                    <ShoppingCart class="size-6 text-blue-600" />
                    إدارة الطلبات
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">
                    عرض وبحث وفلترة الطلبات، أو إضافة طلب جديد من النظام
                </p>
            </div>
            <Link
                v-if="canCreateOrders"
                href="/orders/create"
                class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 sm:h-11"
            >
                <Plus class="size-4" />
                إضافة طلب
            </Link>
        </div>

        <div class="grid grid-cols-2 gap-3 xl:grid-cols-4 sm:gap-4">
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
                <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    <form class="w-full max-w-sm" @submit.prevent="onSearchSubmit">
                        <label class="flex h-10 items-center gap-2 rounded-full border border-transparent bg-gray-100 px-3.5 text-gray-400 transition focus-within:border-blue-300 focus-within:bg-white focus-within:ring-2 focus-within:ring-blue-100 dark:bg-neutral-800 dark:focus-within:border-blue-700 dark:focus-within:bg-neutral-950 dark:focus-within:ring-blue-950">
                            <Search class="size-4 shrink-0 stroke-[1.75]" />
                            <input
                                v-model="searchInput"
                                type="search"
                                placeholder="ابحث عن طلب..."
                                class="w-full bg-transparent text-sm text-gray-800 outline-none placeholder:text-gray-400 dark:text-neutral-100"
                            />
                        </label>
                    </form>

                    <select
                        v-model="currencyFilter"
                        class="h-10 rounded-full border border-gray-200 bg-white px-3 text-sm font-medium text-gray-600 outline-none transition hover:bg-gray-50 focus:border-blue-300 focus:ring-2 focus:ring-blue-100 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-300"
                        @change="applyFilters(1)"
                    >
                        <option value="all">كل العملات</option>
                        <option value="SAR">SAR</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                    </select>
                </div>

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
                    <span>من {{ formatInteger(orders.total) }} نتيجة</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1380px] border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-start dark:border-neutral-800">
                            <th class="w-12 px-3 py-2.5">
                                <input
                                    type="checkbox"
                                    class="size-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    :checked="allVisibleSelected"
                                    @change="toggleSelectAll"
                                />
                            </th>
                            <th class="px-2.5 py-2.5 text-start text-[11px] font-semibold text-gray-700 dark:text-neutral-200">الطلب</th>
                            <th class="px-2.5 py-2.5 text-start text-[11px] font-semibold text-gray-700 dark:text-neutral-200">العميل</th>
                            <th class="px-2.5 py-2.5 text-start text-[11px] font-semibold text-gray-700 dark:text-neutral-200">الإجمالي</th>
                            <th class="px-2.5 py-2.5 text-start text-[11px] font-semibold text-gray-700 dark:text-neutral-200">الضريبة 15%</th>
                            <th class="px-2.5 py-2.5 text-start text-[11px] font-semibold text-gray-700 dark:text-neutral-200">المدفوع</th>
                            <th class="px-2.5 py-2.5 text-start text-[11px] font-semibold text-gray-700 dark:text-neutral-200">المستحق</th>
                            <th class="px-2.5 py-2.5 text-start text-[11px] font-semibold text-gray-700 dark:text-neutral-200">تاريخ الفعالية</th>
                            <th class="px-2.5 py-2.5 text-start text-[11px] font-semibold text-gray-700 dark:text-neutral-200">وقت الفعالية</th>
                            <th class="px-2.5 py-2.5 text-start text-[11px] font-semibold text-gray-700 dark:text-neutral-200">التركيب</th>
                            <th class="px-2.5 py-2.5 text-start text-[11px] font-semibold text-gray-700 dark:text-neutral-200">الفك</th>
                            <th class="px-2.5 py-2.5 text-start text-[11px] font-semibold text-gray-700 dark:text-neutral-200">الحالة</th>
                            <th class="px-3 py-2.5 text-end text-[11px] font-semibold text-gray-700 dark:text-neutral-200" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="orders.data.length === 0">
                            <td colspan="13" class="px-4 py-16 text-center text-gray-500 dark:text-neutral-400">
                                لا توجد طلبات مطابقة للبحث أو الفلتر الحالي.
                            </td>
                        </tr>
                        <tr
                            v-for="order in orders.data"
                            :key="order.id"
                            class="border-b border-gray-100 transition hover:bg-gray-50/70 dark:border-neutral-800 dark:hover:bg-neutral-800/40"
                        >
                            <td class="px-3 py-2.5">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    :checked="selectedIds.includes(order.id)"
                                    @change="toggleSelect(order.id)"
                                />
                            </td>
                            <td class="px-2.5 py-2.5">
                                <Link :href="route('orders.show', order.id)" class="flex flex-col items-start gap-0.5">
                                    <p class="font-semibold tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                        {{ order.order_number }}
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        أُنشئ في: <span dir="ltr">{{ formatDate(order.created_at) }}</span>
                                    </p>
                                </Link>
                            </td>
                            <td class="px-2.5 py-2.5">
                                <div class="flex min-w-0 flex-col items-start gap-1">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ order.customer_name }}</p>
                                    <a
                                        v-if="order.address && locationMapsUrl(order.address)"
                                        :href="locationMapsUrl(order.address)!"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex max-w-full items-center gap-1 text-xs text-blue-600 hover:underline dark:text-blue-400"
                                        @click.stop
                                    >
                                        <MapPin class="size-3 shrink-0" />
                                        <span class="truncate">الموقع</span>
                                        <ExternalLink class="size-3 shrink-0 opacity-60" />
                                    </a>
                                </div>
                            </td>
                            <td class="px-2.5 py-2.5 font-semibold tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                {{ formatCurrency(Number(order.total_amount) || 0, order.currency) }}
                            </td>
                            <td class="px-2.5 py-2.5 tabular-nums text-gray-700 dark:text-neutral-200" dir="ltr">
                                <span v-if="vatAmount(order) > 0">
                                    {{ formatCurrency(vatAmount(order), order.currency) }}
                                </span>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-2.5 py-2.5 tabular-nums" dir="ltr">
                                <span
                                    v-if="paidAmount(order) > 0"
                                    class="font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    {{ formatCurrency(paidAmount(order), order.currency) }}
                                </span>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-2.5 py-2.5 tabular-nums" dir="ltr">
                                <span
                                    v-if="dueAmount(order) > 0"
                                    class="font-semibold text-red-600 dark:text-red-400"
                                >
                                    {{ formatCurrency(dueAmount(order), order.currency) }}
                                </span>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-2.5 py-2.5 text-gray-600 dark:text-neutral-300">
                                {{ formatActivityDate(order.activity_date) }}
                            </td>
                            <td class="px-2.5 py-2.5" @click.stop>
                                <div v-if="editingTime.id === order.id" class="flex items-center gap-1.5" dir="ltr">
                                    <div class="inline-flex h-8 items-center gap-0.5 rounded-md border border-gray-200 bg-white px-1 dark:border-neutral-700 dark:bg-neutral-950">
                                        <select
                                            v-model="editingTime.hour"
                                            class="h-7 w-8 cursor-pointer appearance-none bg-transparent text-center text-xs font-medium tabular-nums outline-none"
                                            :disabled="editingTime.saving"
                                        >
                                            <option v-for="hour in hourOptions" :key="`h-${hour}`" :value="hour">{{ hour }}</option>
                                        </select>
                                        <span class="text-xs text-gray-400">:</span>
                                        <select
                                            v-model="editingTime.minute"
                                            class="h-7 w-8 cursor-pointer appearance-none bg-transparent text-center text-xs font-medium tabular-nums outline-none"
                                            :disabled="editingTime.saving"
                                        >
                                            <option v-for="minute in minuteOptions" :key="`m-${minute}`" :value="minute">{{ minute }}</option>
                                        </select>
                                        <span class="mx-0.5 h-4 w-px bg-gray-200 dark:bg-neutral-700" />
                                        <select
                                            v-model="editingTime.period"
                                            class="h-7 w-9 cursor-pointer appearance-none bg-transparent text-center text-xs font-medium outline-none"
                                            :disabled="editingTime.saving"
                                        >
                                            <option value="AM">AM</option>
                                            <option value="PM">PM</option>
                                        </select>
                                    </div>
                                    <Button
                                        type="button"
                                        size="icon"
                                        class="h-8 w-8 shrink-0"
                                        :disabled="editingTime.saving"
                                        @click="saveEditTime(order)"
                                    >
                                        <Check class="size-3.5" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        class="h-8 w-8 shrink-0"
                                        :disabled="editingTime.saving"
                                        @click="cancelEditTime"
                                    >
                                        <X class="size-3.5" />
                                    </Button>
                                </div>
                                <div v-else class="flex items-center gap-2">
                                    <span class="text-sm font-medium tabular-nums text-gray-700 dark:text-neutral-200" dir="ltr">
                                        {{ formatActivityTime(order.activity_time) }}
                                    </span>
                                    <button
                                        v-if="order.can_edit_activity_time"
                                        type="button"
                                        class="inline-flex size-7 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                                        title="تحديد وقت الفعالية"
                                        @click="startEditTime(order)"
                                    >
                                        <Pencil class="size-3.5" />
                                    </button>
                                </div>
                            </td>
                            <td class="px-2.5 py-2.5">
                                <div class="flex min-w-[8.5rem] flex-col items-start gap-1.5">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                        :class="installBadgeClass(order.installation?.status || 'none')"
                                    >
                                        {{ order.installation?.label || '—' }}
                                    </span>
                                    <button
                                        v-if="order.installation?.can_review_photos"
                                        type="button"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-sky-700 hover:underline dark:text-sky-400"
                                        @click="openPhotosDialog(order)"
                                    >
                                        <Camera class="size-3" />
                                        مراجعة الصور
                                    </button>
                                </div>
                            </td>
                            <td class="px-2.5 py-2.5">
                                <div class="flex min-w-[7.5rem] flex-col items-start gap-1">
                                    <span
                                        class="inline-flex max-w-[220px] rounded-full px-2 py-0.5 text-[10px] font-semibold leading-snug"
                                        :class="dismantleBadgeClass(order.dismantling?.status || 'none')"
                                    >
                                        {{ order.dismantling?.label || '—' }}
                                    </span>
                                    <span
                                        v-if="order.dismantling?.scheduled_at"
                                        class="text-[11px] text-gray-400"
                                        dir="ltr"
                                    >
                                        {{ formatDateTime(order.dismantling.scheduled_at) }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-2.5 py-2.5">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                    :class="statusBadgeClass(order.status)"
                                >
                                    {{ getStatusText(order.status) }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        v-if="order.payment_url"
                                        type="button"
                                        :title="copiedPaymentLinkId === order.id ? 'تم نسخ رابط الدفع' : 'نسخ رابط الدفع'"
                                        class="inline-flex size-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                                        @click="copyPaymentLink(order)"
                                    >
                                        <Check
                                            v-if="copiedPaymentLinkId === order.id"
                                            class="size-4 text-emerald-600"
                                        />
                                        <Copy v-else class="size-4" />
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
                                            <DropdownMenuItem as-child>
                                                <Link :href="route('orders.show', order.id)" class="flex items-center gap-2">
                                                    <Eye class="size-4" />
                                                    عرض التفاصيل
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="order.can_edit"
                                                as-child
                                            >
                                                <Link :href="route('orders.edit', order.id)" class="flex items-center gap-2">
                                                    <Pencil class="size-4" />
                                                    تعديل الطلب
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="order.payment_url"
                                                class="gap-2"
                                                @select.prevent="copyPaymentLink(order)"
                                            >
                                                <Check
                                                    v-if="copiedPaymentLinkId === order.id"
                                                    class="size-4 text-emerald-600"
                                                />
                                                <Copy v-else class="size-4" />
                                                {{ copiedPaymentLinkId === order.id ? 'تم نسخ الرابط' : 'نسخ رابط الدفع' }}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="canSettleOrder(order)"
                                                class="gap-2"
                                                @click="openSettleDialog(order)"
                                            >
                                                <Wallet class="size-4" />
                                                سداد
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="order.can_edit_activity_time"
                                                class="gap-2"
                                                @click="startEditTime(order)"
                                            >
                                                <Pencil class="size-4" />
                                                تحديد وقت الفعالية
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator v-if="canDeleteOrder(order)" />
                                            <DropdownMenuItem
                                                v-if="canDeleteOrder(order)"
                                                class="gap-2 text-red-600 focus:text-red-600"
                                                @click="deleteOrder(order)"
                                            >
                                                <Trash2 class="size-4" />
                                                حذف الطلب
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-100 px-4 py-4 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-500 dark:text-neutral-400">
                    عرض {{ formatInteger(orders.from ?? 0) }} - {{ formatInteger(orders.to ?? 0) }} من {{ formatInteger(orders.total) }}
                </p>

                <div v-if="orders.last_page > 1" class="flex items-center justify-center gap-1.5 sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 disabled:opacity-40 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800"
                        :disabled="orders.current_page <= 1"
                        @click="goToPage(orders.current_page - 1)"
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
                                orders.current_page === item
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
                        :disabled="orders.current_page >= orders.last_page"
                        @click="goToPage(orders.current_page + 1)"
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                </div>
            </div>
        </div>

        <Dialog :open="settleDialogOpen" @update:open="(open) => !open && closeSettleDialog()">
            <DialogContent class="max-w-md sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>سداد الطلب</DialogTitle>
                    <DialogDescription v-if="settleOrder">
                        سجل دفعة للطلب
                        <span class="font-semibold tabular-nums" dir="ltr">{{ settleOrder.order_number }}</span>
                        —
                        المتبقي المتاح
                        <span class="font-semibold tabular-nums" dir="ltr">
                            {{ formatCurrency(Number(settleOrder.settle_available ?? dueAmount(settleOrder)) || 0, settleOrder.currency) }}
                        </span>
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitSettle">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <Label for="settle-amount">المبلغ المدفوع</Label>
                            <button
                                type="button"
                                class="text-xs text-blue-600 hover:underline dark:text-blue-400"
                                @click="fillSettleRemaining"
                            >
                                سداد المتبقي بالكامل
                            </button>
                        </div>
                        <Input
                            id="settle-amount"
                            v-model="settleForm.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            class="h-11 rounded-xl tabular-nums"
                            dir="ltr"
                            placeholder="0.00"
                        />
                        <p v-if="settleForm.errors.amount" class="text-xs text-red-600">{{ settleForm.errors.amount }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="settle-method">طريقة الدفع</Label>
                        <select
                            id="settle-method"
                            v-model="settleForm.payment_method"
                            class="flex h-11 w-full rounded-xl border border-input bg-background px-3 text-sm"
                        >
                            <option value="cash">نقدي</option>
                            <option value="bank_transfer">تحويل بنكي</option>
                            <option value="credit_card">بطاقة ائتمان</option>
                            <option value="noon">Noon</option>
                            <option value="paypal">PayPal</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <Label for="settle-proof">صور التحويل / إيصال الدفع</Label>
                        <label
                            for="settle-proof"
                            class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border bg-muted/30 px-4 py-5 text-center transition hover:bg-muted/50"
                        >
                            <UploadCloud class="h-5 w-5 text-muted-foreground" />
                            <span class="text-sm font-medium">
                                {{ settleForm.payment_proof.length
                                    ? `${settleForm.payment_proof.length} صورة محددة — اضغط لإضافة المزيد`
                                    : 'اختر صور الإيصال' }}
                            </span>
                            <span class="text-xs text-muted-foreground">jpg, png, webp — حتى 5 ميجابايت لكل صورة · بحد أقصى 10</span>
                            <input
                                id="settle-proof"
                                ref="paymentProofInput"
                                type="file"
                                multiple
                                accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                                class="hidden"
                                @change="handleSettleProofChange"
                            />
                        </label>
                        <div v-if="paymentProofPreviews.length" class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <div
                                v-for="(preview, index) in paymentProofPreviews"
                                :key="`${preview}-${index}`"
                                class="relative overflow-hidden rounded-xl border border-border/60"
                            >
                                <img
                                    :src="preview"
                                    :alt="`معاينة إيصال ${index + 1}`"
                                    class="aspect-square w-full bg-muted/20 object-cover"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="absolute left-1.5 top-1.5 h-7 rounded-lg px-2 text-[11px]"
                                    @click="removeSettleProof(index)"
                                >
                                    إزالة
                                </Button>
                            </div>
                        </div>
                        <button
                            v-if="settleForm.payment_proof.length"
                            type="button"
                            class="text-xs text-rose-600 hover:underline"
                            @click="clearAllSettleProofs"
                        >
                            إزالة كل الصور
                        </button>
                        <p v-if="settleForm.errors.payment_proof" class="text-xs text-red-600">
                            {{ settleForm.errors.payment_proof }}
                        </p>
                        <p
                            v-for="(error, key) in settleForm.errors"
                            v-show="String(key).startsWith('payment_proof.')"
                            :key="key"
                            class="text-xs text-red-600"
                        >
                            {{ error }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="settle-notes">ملاحظات</Label>
                        <Input
                            id="settle-notes"
                            v-model="settleForm.notes"
                            class="h-11 rounded-xl"
                            placeholder="اختياري"
                        />
                    </div>

                    <DialogFooter class="gap-2 sm:justify-start">
                        <Button type="submit" class="h-10 gap-2 rounded-xl" :disabled="settleForm.processing">
                            <Wallet class="size-4" />
                            {{ settleForm.processing ? 'جاري التسجيل...' : 'تأكيد السداد' }}
                        </Button>
                        <Button type="button" variant="outline" class="h-10 rounded-xl" @click="closeSettleDialog">
                            إلغاء
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="photosDialogOpen" @update:open="(open) => !open && closePhotosDialog()">
            <DialogContent class="max-h-[90vh] max-w-3xl overflow-y-auto sm:rounded-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Wrench class="size-5 text-sky-600" />
                        مراجعة صور التركيب
                    </DialogTitle>
                    <DialogDescription v-if="photosDialogOrder">
                        الطلب
                        <span class="font-semibold tabular-nums" dir="ltr">{{ photosDialogOrder.order_number }}</span>
                        —
                        {{ photosDialogOrder.customer_name }}
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="photosDialogOrder?.installation?.photos?.length"
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                >
                    <button
                        v-for="(photo, index) in photosDialogOrder.installation.photos"
                        :key="`${photo.url}-${index}`"
                        type="button"
                        class="overflow-hidden rounded-2xl border border-border/70 bg-muted/20 text-start transition hover:ring-2 hover:ring-sky-200"
                        @click="photosLightbox = photo.url"
                    >
                        <img
                            :src="photo.url"
                            :alt="photo.product_name"
                            class="aspect-[4/3] w-full object-cover"
                        />
                        <p class="truncate px-3 py-2 text-sm font-medium text-slate-800 dark:text-neutral-100">
                            {{ photo.product_name }}
                        </p>
                    </button>
                </div>
                <p v-else class="py-8 text-center text-sm text-muted-foreground">
                    لا توجد صور تركيب مرفوعة لهذا الطلب.
                </p>

                <DialogFooter>
                    <Button type="button" variant="outline" class="h-10 rounded-xl" @click="closePhotosDialog">
                        إغلاق
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Teleport to="body">
            <div
                v-if="photosLightbox"
                class="fixed inset-0 z-[300] flex items-center justify-center bg-black/85 p-4"
                role="dialog"
                aria-modal="true"
                @click.self="photosLightbox = null"
            >
                <button
                    type="button"
                    class="absolute end-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white"
                    @click="photosLightbox = null"
                >
                    <X class="h-5 w-5" />
                </button>
                <img :src="photosLightbox" alt="معاينة صورة التركيب" class="max-h-[85vh] max-w-full rounded-2xl object-contain" />
            </div>
        </Teleport>
    </div>
</template>
