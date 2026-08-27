<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    ArrowUpRight,
    BadgeCheck,
    Building2,
    Check,
    ChevronLeft,
    ChevronRight,
    Download,
    FileText,
    MoreVertical,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { formatCurrency, formatDate, formatInteger } from '@/lib/formatNumber';

interface Brand {
    id: number;
    name: string;
    slug: string;
    quotations_count: number;
}

interface Quotation {
    id: number;
    quotation_number: string;
    customer_name: string;
    customer_email: string | null;
    customer_phone?: string | null;
    total_amount: number | string;
    status: string;
    approval_stage?: string;
    can_approve?: boolean;
    can_accountant_approve?: boolean;
    order_number?: string | null;
    show_online_payment?: boolean;
    online_payment_status?: 'paid' | 'partial' | 'pending' | 'off';
    valid_until: string | null;
    created_at: string;
    brand?: Brand | null;
    user: {
        name: string;
    } | null;
}

type StatusTab = 'all' | 'draft' | 'sent' | 'accepted' | 'pending_accountant' | 'rejected' | 'expired';

interface Props {
    quotations: {
        data: Quotation[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        per_page: number;
    };
    brands: Brand[];
    selectedBrandId?: number | null;
    filters?: {
        search?: string;
        status?: StatusTab;
        per_page?: number;
    };
    statusCounts?: Record<StatusTab, number>;
}

const props = withDefaults(defineProps<Props>(), {
    selectedBrandId: null,
    brands: () => [],
    filters: () => ({
        search: '',
        status: 'all',
        per_page: 15,
    }),
    statusCounts: () => ({
        all: 0,
        draft: 0,
        sent: 0,
        accepted: 0,
        pending_accountant: 0,
        rejected: 0,
        expired: 0,
    }),
});

defineOptions({ layout: AppLayout });

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);
const errorMessage = computed(() => page.props.flash?.error as string | undefined);

const searchInput = ref(props.filters?.search ?? '');
const statusFilter = ref<StatusTab>(props.filters?.status ?? 'all');
const perPage = ref(props.filters?.per_page || 15);
const selectedIds = ref<number[]>([]);

const statusTabs: { key: StatusTab; label: string }[] = [
    { key: 'all', label: 'الكل' },
    { key: 'draft', label: 'مسودة' },
    { key: 'sent', label: 'مرسل' },
    { key: 'accepted', label: 'مقبول' },
    { key: 'pending_accountant', label: 'في انتظار المحاسب' },
    { key: 'rejected', label: 'مرفوض' },
    { key: 'expired', label: 'منتهي' },
];

const selectedBrand = computed(
    () => props.brands.find((brand) => brand.id === props.selectedBrandId) ?? null,
);

const summaryCards = computed(() => [
    {
        key: 'all' as const,
        label: 'إجمالي العروض',
        value: props.statusCounts.all,
        unit: 'عرض',
        hint: 'عرض كل العروض',
    },
    {
        key: 'draft' as const,
        label: 'مسودة',
        value: props.statusCounts.draft,
        unit: 'عرض',
        hint: 'عرض المسودات',
    },
    {
        key: 'sent' as const,
        label: 'مرسل',
        value: props.statusCounts.sent,
        unit: 'عرض',
        hint: 'عرض المرسلة',
    },
    {
        key: 'accepted' as const,
        label: 'مقبول',
        value: props.statusCounts.accepted,
        unit: 'عرض',
        hint: 'عرض المقبولة',
    },
    {
        key: 'pending_accountant' as const,
        label: 'في انتظار المحاسب',
        value: props.statusCounts.pending_accountant ?? 0,
        unit: 'عرض',
        hint: 'معتمدة وفي انتظار المحاسب',
    },
]);

const pageNumbers = computed(() => {
    const total = props.quotations.last_page;
    const current = props.quotations.current_page;
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
        props.quotations.data.length > 0
        && props.quotations.data.every((quotation) => selectedIds.value.includes(quotation.id)),
);

watch(
    () => props.filters,
    (filters) => {
        searchInput.value = filters?.search ?? '';
        statusFilter.value = filters?.status ?? 'all';
        perPage.value = filters?.per_page || 15;
        selectedIds.value = [];
    },
);

onMounted(() => {
    const pdfId = page.props.flash?.open_pdf as number | undefined;
    if (pdfId) {
        window.open(quotationPdfUrl(pdfId), '_blank');
    }
});

function applyFilters(pageNum = 1) {
    router.get(
        route('quotations.index'),
        {
            brand: props.selectedBrandId || undefined,
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

function applyBrandFilter(brandId: string) {
    router.get(
        route('quotations.index'),
        {
            brand: brandId || undefined,
            search: searchInput.value.trim() || undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
            per_page: perPage.value !== 15 ? perPage.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function goToPage(pageNum: number) {
    if (pageNum >= 1 && pageNum <= props.quotations.last_page) {
        applyFilters(pageNum);
    }
}

function tabCount(tab: StatusTab): number {
    return props.statusCounts?.[tab] ?? 0;
}

function toggleSelectAll() {
    if (allVisibleSelected.value) {
        const visible = new Set(props.quotations.data.map((quotation) => quotation.id));
        selectedIds.value = selectedIds.value.filter((id) => !visible.has(id));
        return;
    }

    selectedIds.value = Array.from(new Set([
        ...selectedIds.value,
        ...props.quotations.data.map((quotation) => quotation.id),
    ]));
}

function toggleSelect(id: number) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((item) => item !== id);
        return;
    }
    selectedIds.value = [...selectedIds.value, id];
}

function getStatusText(quotation: Quotation): string {
    if (quotation.approval_stage === 'pending_accountant') {
        return 'في انتظار المحاسب';
    }
    if (quotation.approval_stage === 'released') {
        return 'في الطلبات';
    }
    const map: Record<string, string> = {
        draft: 'مسودة',
        sent: 'مرسل',
        accepted: 'مقبول',
        rejected: 'مرفوض',
        expired: 'منتهي',
    };
    return map[quotation.status] || quotation.status;
}

function statusBadgeClass(quotation: Quotation): string {
    const stage = quotation.approval_stage || quotation.status;
    const map: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-700',
        sent: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-100 dark:bg-blue-950/40 dark:text-blue-300 dark:ring-blue-900/50',
        accepted: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50',
        pending_accountant: 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900/50',
        released: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50',
        rejected: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-100 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900/50',
        expired: 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900/50',
    };
    return map[stage] || 'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-200';
}

function onlinePaymentLabel(quotation: Quotation): string {
    switch (quotation.online_payment_status) {
        case 'paid':
            return 'تم الدفع أونلاين';
        case 'partial':
            return 'دفع جزئي أونلاين';
        case 'pending':
            return 'بانتظار الدفع';
        default:
            return 'غير مفعّل';
    }
}

function onlinePaymentBadgeClass(quotation: Quotation): string {
    switch (quotation.online_payment_status) {
        case 'paid':
            return 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50';
        case 'partial':
            return 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900/50';
        case 'pending':
            return 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-100 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-900/50';
        default:
            return 'bg-gray-100 text-gray-500 ring-1 ring-inset ring-gray-200 dark:bg-neutral-800 dark:text-neutral-400 dark:ring-neutral-700';
    }
}

function approveQuotation(quotation: Quotation) {
    if (!confirm('اعتماد عرض السعر؟ بدون مدفوعات يتحول فوراً إلى طلب. أمر العمل يصدر بعد سداد أي مبلغ واعتماد المحاسب.')) {
        return;
    }
    router.post(route('quotations.approve', quotation.id), {}, { preserveScroll: true });
}

function accountantApprove(quotation: Quotation) {
    if (!confirm('اعتماد المحاسب؟ سيظهر الطلب في الطلبات. أمر العمل يصدر فقط بعد اعتماد سند القبض.')) {
        return;
    }
    router.post(route('quotations.accountant-approve', quotation.id), {}, { preserveScroll: true });
}

function deleteQuotation(quotation: Quotation) {
    if (confirm('هل أنت متأكد من حذف هذا العرض؟')) {
        router.delete(route('quotations.destroy', quotation.id), {
            preserveScroll: true,
        });
    }
}

function quotationPdfUrl(id: number): string {
    return `/quotations/${id}/pdf?v=${Date.now()}`;
}

/** First + second name only; strip tax numbers. */
function displayCustomerName(name: string | null | undefined): string {
    if (!name) {
        return '—';
    }

    const cleaned = String(name)
        .replace(/الرقم\s*الضريبي\s*[:：]?\s*\S*/gi, ' ')
        .replace(/\b\d{10,15}\b/g, ' ')
        .replace(/\s*[||/]\s*/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    if (!cleaned) {
        return '—';
    }

    return cleaned.split(/\s+/).slice(0, 2).join(' ');
}
</script>

<template>
    <Head title="عروض الأسعار" />

    <div class="flex min-w-0 flex-1 flex-col gap-5 overflow-x-hidden p-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-white sm:text-2xl">
                    <FileText class="size-6 text-blue-600" />
                    {{ selectedBrand ? `عروض ${selectedBrand.name}` : 'عروض الأسعار' }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">
                    {{ selectedBrand
                        ? `عرض عروض الأسعار الخاصة ببراند ${selectedBrand.name}`
                        : 'اعتماد العرض يحوّله لطلب. بدون مدفوعات يظهر فوراً؛ مع مدفوعات ينتظر المحاسب. أمر العمل بعد اعتماد سند القبض' }}
                </p>
            </div>
            <Link
                :href="route('quotations.create')"
                class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 sm:h-11"
            >
                <Plus class="size-4" />
                عرض جديد
            </Link>
        </div>

        <p
            v-if="successMessage"
            class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300"
        >
            {{ successMessage }}
        </p>
        <p
            v-if="errorMessage"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300"
        >
            {{ errorMessage }}
        </p>

        <div class="grid grid-cols-2 gap-3 xl:grid-cols-5 sm:gap-4">
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
                                placeholder="ابحث عن عرض سعر..."
                                class="w-full bg-transparent text-sm text-gray-800 outline-none placeholder:text-gray-400 dark:text-neutral-100"
                            />
                        </label>
                    </form>

                    <select
                        class="h-10 rounded-full border border-gray-200 bg-white px-3 text-sm font-medium text-gray-600 outline-none transition hover:bg-gray-50 focus:border-blue-300 focus:ring-2 focus:ring-blue-100 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-300"
                        :value="selectedBrandId ?? ''"
                        @change="applyBrandFilter(($event.target as HTMLSelectElement).value)"
                    >
                        <option value="">كل البراندات</option>
                        <option v-for="brand in brands" :key="brand.id" :value="brand.id">
                            {{ brand.name }} ({{ formatInteger(brand.quotations_count) }})
                        </option>
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
                    <span>من {{ formatInteger(quotations.total) }} نتيجة</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1220px] border-collapse text-sm">
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
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">العرض</th>
                            <th class="w-36 px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">العميل</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">المبلغ</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الدفع أونلاين</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">صالح حتى</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">أنشأه</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الحالة</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الاعتماد</th>
                            <th class="px-4 py-3.5 text-end text-[13px] font-semibold text-gray-700 dark:text-neutral-200" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="quotations.data.length === 0">
                            <td colspan="10" class="px-4 py-16 text-center text-gray-500 dark:text-neutral-400">
                                {{ selectedBrand ? 'لا توجد عروض أسعار لهذا البراند.' : 'لا توجد عروض مطابقة للبحث أو الفلتر الحالي.' }}
                            </td>
                        </tr>
                        <tr
                            v-for="quotation in quotations.data"
                            :key="quotation.id"
                            class="border-b border-gray-100 transition hover:bg-gray-50/70 dark:border-neutral-800 dark:hover:bg-neutral-800/40"
                        >
                            <td class="px-4 py-4">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    :checked="selectedIds.includes(quotation.id)"
                                    @change="toggleSelect(quotation.id)"
                                />
                            </td>
                            <td class="px-3 py-4">
                                <Link :href="route('quotations.edit', quotation.id)" class="flex flex-col items-start gap-0.5">
                                    <p class="font-semibold tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                        {{ quotation.quotation_number }}
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        أُنشئ في: <span dir="ltr">{{ formatDate(quotation.created_at) }}</span>
                                    </p>
                                </Link>
                            </td>
                            <td class="w-36 max-w-[9rem] px-3 py-4">
                                <div class="flex min-w-0 flex-col items-start gap-1">
                                    <p class="truncate font-semibold text-gray-900 dark:text-white" :title="quotation.customer_name">
                                        {{ displayCustomerName(quotation.customer_name) }}
                                    </p>
                                    <p
                                        v-if="quotation.brand?.name"
                                        class="flex max-w-full items-center gap-1 truncate text-xs text-gray-400"
                                    >
                                        <Building2 class="size-3 shrink-0" />
                                        <span class="truncate">{{ quotation.brand.name }}</span>
                                    </p>
                                </div>
                            </td>
                            <td class="px-3 py-4 font-semibold tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                {{ formatCurrency(Number(quotation.total_amount) || 0) }}
                            </td>
                            <td class="px-3 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="onlinePaymentBadgeClass(quotation)"
                                >
                                    {{ onlinePaymentLabel(quotation) }}
                                </span>
                            </td>
                            <td class="px-3 py-4 text-gray-600 dark:text-neutral-300">
                                {{ quotation.valid_until ? formatDate(quotation.valid_until) : '—' }}
                            </td>
                            <td class="px-3 py-4 text-gray-600 dark:text-neutral-300">
                                {{ quotation.user?.name || '—' }}
                            </td>
                            <td class="px-3 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="statusBadgeClass(quotation)"
                                >
                                    {{ getStatusText(quotation) }}
                                </span>
                            </td>
                            <td class="px-3 py-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        v-if="quotation.can_approve"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700"
                                        @click="approveQuotation(quotation)"
                                    >
                                        <Check class="size-3.5" />
                                        اعتماد
                                    </button>
                                    <button
                                        v-else-if="quotation.can_accountant_approve"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-amber-700"
                                        @click="accountantApprove(quotation)"
                                    >
                                        <BadgeCheck class="size-3.5" />
                                        اعتماد المحاسب
                                    </button>
                                    <span
                                        v-else-if="quotation.approval_stage === 'released'"
                                        class="text-xs text-emerald-700 dark:text-emerald-300"
                                    >
                                        {{ quotation.order_number || 'تم التحويل' }}
                                    </span>
                                    <span
                                        v-else-if="quotation.approval_stage === 'pending_accountant'"
                                        class="text-xs text-amber-700 dark:text-amber-300"
                                    >
                                        في انتظار المحاسب
                                    </span>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex justify-end">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button
                                                type="button"
                                                class="inline-flex size-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                                            >
                                                <MoreVertical class="size-4" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="min-w-52">
                                            <DropdownMenuItem as-child>
                                                <a
                                                    :href="quotationPdfUrl(quotation.id)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="gap-2"
                                                >
                                                    <Download class="size-4" />
                                                    عرض السعر
                                                </a>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="gap-2"
                                                @click="router.visit(route('quotations.edit', quotation.id))"
                                            >
                                                <Pencil class="size-4" />
                                                تعديل
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="quotation.can_approve"
                                                class="gap-2"
                                                @click="approveQuotation(quotation)"
                                            >
                                                <Check class="size-4" />
                                                اعتماد عرض السعر
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="quotation.can_accountant_approve"
                                                class="gap-2"
                                                @click="accountantApprove(quotation)"
                                            >
                                                <BadgeCheck class="size-4" />
                                                اعتماد المحاسب
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator v-if="quotation.can_approve || quotation.can_accountant_approve" />
                                            <DropdownMenuItem
                                                class="gap-2 text-red-600 focus:text-red-600"
                                                @click="deleteQuotation(quotation)"
                                            >
                                                <Trash2 class="size-4" />
                                                حذف
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
                    عرض {{ formatInteger(quotations.from ?? 0) }} - {{ formatInteger(quotations.to ?? 0) }} من {{ formatInteger(quotations.total) }}
                </p>

                <div v-if="quotations.last_page > 1" class="flex items-center justify-center gap-1.5 sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 disabled:opacity-40 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800"
                        :disabled="quotations.current_page <= 1"
                        @click="goToPage(quotations.current_page - 1)"
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
                                quotations.current_page === item
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
                        :disabled="quotations.current_page >= quotations.last_page"
                        @click="goToPage(quotations.current_page + 1)"
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
