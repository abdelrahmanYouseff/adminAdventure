<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Building2,
    Check,
    ChevronLeft,
    ChevronRight,
    Download,
    Eye,
    FileText,
    MoreVertical,
    RefreshCw,
    Search,
    X,
} from 'lucide-vue-next';
import { formatCurrency, formatDate, formatInteger } from '@/lib/formatNumber';

interface InvoiceUser {
    id: number;
    customer_name?: string | null;
    name?: string | null;
    full_name?: string | null;
    email?: string | null;
}

interface InvoiceBrand {
    id: number;
    name: string;
    slug?: string;
}

interface InvoiceOrder {
    id: number;
    amount_paid?: number | string | null;
    total_amount?: number | string | null;
    currency?: string | null;
}

interface InvoiceItem {
    id: number;
    invoice_number: string;
    amount: number | string;
    status: 'pending' | 'paid' | 'cancelled' | 'overdue' | string;
    payment_method?: string | null;
    due_date?: string | null;
    created_at: string;
    user?: InvoiceUser | null;
    brand?: InvoiceBrand | null;
    order?: InvoiceOrder | null;
}

interface BrandOption {
    id: number;
    name: string;
    slug?: string;
    invoices_count: number;
}

interface PaginatedInvoices {
    data: InvoiceItem[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    per_page: number;
    prev_page_url?: string | null;
    next_page_url?: string | null;
}

type StatusTab = 'all' | 'pending' | 'paid' | 'overdue' | 'cancelled';

interface Props {
    invoices: PaginatedInvoices;
    brands: BrandOption[];
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
        pending: 0,
        paid: 0,
        overdue: 0,
        cancelled: 0,
    }),
});

defineOptions({ layout: AppLayout });

const searchQuery = ref(props.filters?.search || '');
const statusFilter = ref<StatusTab>(props.filters?.status || 'all');
const perPage = ref(props.filters?.per_page || 15);
const selectedIds = ref<number[]>([]);

const statusTabs: { key: StatusTab; label: string }[] = [
    { key: 'all', label: 'الكل' },
    { key: 'pending', label: 'مستحقة' },
    { key: 'paid', label: 'مدفوعة' },
    { key: 'overdue', label: 'متأخرة' },
    { key: 'cancelled', label: 'ملغاة' },
];

const selectedBrand = computed(
    () => props.brands.find((brand) => brand.id === props.selectedBrandId) ?? null,
);

const pageNumbers = computed(() => {
    const total = props.invoices.last_page;
    const current = props.invoices.current_page;
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
        props.invoices.data.length > 0
        && props.invoices.data.every((invoice) => selectedIds.value.includes(invoice.id)),
);

watch(
    () => props.filters,
    (filters) => {
        searchQuery.value = filters?.search || '';
        statusFilter.value = filters?.status || 'all';
        perPage.value = filters?.per_page || 15;
        selectedIds.value = [];
    },
);

function customerName(invoice: InvoiceItem): string {
    return invoice.user?.full_name
        || invoice.user?.name
        || invoice.user?.customer_name
        || '—';
}

function paidAmount(invoice: InvoiceItem): number {
    if (invoice.status === 'paid') {
        return Number(invoice.amount) || 0;
    }

    if (invoice.status === 'cancelled') {
        return 0;
    }

    return Number(invoice.order?.amount_paid ?? 0) || 0;
}

function dueAmount(invoice: InvoiceItem): number {
    if (invoice.status === 'paid' || invoice.status === 'cancelled') {
        return 0;
    }

    return Math.max(0, (Number(invoice.amount) || 0) - paidAmount(invoice));
}

function statusLabel(status: string): string {
    const map: Record<string, string> = {
        pending: 'مستحقة',
        paid: 'مدفوعة',
        cancelled: 'ملغاة',
        overdue: 'متأخرة',
    };
    return map[status] || status;
}

function statusBadgeClass(status: string): string {
    const map: Record<string, string> = {
        paid: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50',
        pending: 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900/50',
        overdue: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-100 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900/50',
        cancelled: 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-200 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-700',
    };
    return map[status] || 'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-200';
}

function applyFilters(page = 1) {
    router.get(
        route('invoices.index'),
        {
            page: page > 1 ? page : undefined,
            brand: props.selectedBrandId || undefined,
            search: searchQuery.value.trim() || undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
            per_page: perPage.value !== 15 ? perPage.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function setStatusFilter(status: StatusTab) {
    statusFilter.value = status;
    applyFilters(1);
}

function submitSearch() {
    applyFilters(1);
}

function applyBrandFilter(brandId: string) {
    router.get(
        route('invoices.index'),
        {
            brand: brandId || undefined,
            search: searchQuery.value.trim() || undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
            per_page: perPage.value !== 15 ? perPage.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function goToPage(page: number) {
    if (page < 1 || page > props.invoices.last_page) return;
    applyFilters(page);
}

function toggleSelectAll() {
    if (allVisibleSelected.value) {
        const visible = new Set(props.invoices.data.map((invoice) => invoice.id));
        selectedIds.value = selectedIds.value.filter((id) => !visible.has(id));
        return;
    }

    selectedIds.value = Array.from(new Set([
        ...selectedIds.value,
        ...props.invoices.data.map((invoice) => invoice.id),
    ]));
}

function toggleSelect(id: number) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((item) => item !== id);
        return;
    }
    selectedIds.value = [...selectedIds.value, id];
}

function viewInvoice(invoice: InvoiceItem) {
    window.open(route('invoices.pdf', invoice.id), '_blank');
}

function openInvoice(invoice: InvoiceItem) {
    router.visit(route('invoices.show', invoice.id));
}

function updateStatus(invoice: InvoiceItem, status: string) {
    router.patch(route('invoices.update-status', invoice.id), { status });
}

function exportInvoices() {
    const params = new URLSearchParams();
    if (props.selectedBrandId) params.set('brand', String(props.selectedBrandId));
    if (searchQuery.value.trim()) params.set('search', searchQuery.value.trim());
    if (statusFilter.value !== 'all') params.set('status', statusFilter.value);
    const query = params.toString();
    window.open(route('invoices.export') + (query ? `?${query}` : ''), '_blank');
}

function updateOverdueInvoices() {
    router.patch(route('invoices.update-overdue'), {}, {
        onSuccess: () => router.reload(),
    });
}

function tabCount(tab: StatusTab): number {
    return props.statusCounts?.[tab] ?? 0;
}
</script>

<template>
    <Head title="الفواتير" />

    <div class="flex min-w-0 flex-1 flex-col gap-5 overflow-x-hidden p-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-white sm:text-2xl">
                    <FileText class="size-6 text-blue-600" />
                    {{ selectedBrand ? `فواتير ${selectedBrand.name}` : 'الفواتير' }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">
                    {{ selectedBrand
                        ? `عرض فواتير براند ${selectedBrand.name}`
                        : 'قائمة بجميع الفواتير في النظام' }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" class="gap-2 rounded-xl" @click="updateOverdueInvoices">
                    <RefreshCw class="size-4" />
                    تحديث المتأخرة
                </Button>
                <Button variant="outline" size="sm" class="gap-2 rounded-xl" @click="exportInvoices">
                    <Download class="size-4" />
                    تصدير CSV
                </Button>
            </div>
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
                    <form class="w-full max-w-sm" @submit.prevent="submitSearch">
                        <label class="flex h-10 items-center gap-2 rounded-full border border-transparent bg-gray-100 px-3.5 text-gray-400 transition focus-within:border-blue-300 focus-within:bg-white focus-within:ring-2 focus-within:ring-blue-100 dark:bg-neutral-800 dark:focus-within:border-blue-700 dark:focus-within:bg-neutral-950 dark:focus-within:ring-blue-950">
                            <Search class="size-4 shrink-0 stroke-[1.75]" />
                            <input
                                v-model="searchQuery"
                                type="search"
                                placeholder="ابحث عن فاتورة..."
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
                            {{ brand.name }} ({{ formatInteger(brand.invoices_count) }})
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
                    <span>من {{ formatInteger(invoices.total) }} نتيجة</span>
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
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الفاتورة</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">العميل</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الإجمالي</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">المدفوع</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">المستحق</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">تاريخ الاستحقاق</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الحالة</th>
                            <th class="px-4 py-3.5 text-end text-[13px] font-semibold text-gray-700 dark:text-neutral-200" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="invoices.data.length === 0">
                            <td colspan="9" class="px-4 py-16 text-center text-gray-500 dark:text-neutral-400">
                                {{ selectedBrand ? 'لا توجد فواتير لهذا البراند.' : 'لا توجد فواتير مطابقة للبحث أو الفلتر الحالي.' }}
                            </td>
                        </tr>
                        <tr
                            v-for="invoice in invoices.data"
                            :key="invoice.id"
                            class="border-b border-gray-100 transition hover:bg-gray-50/70 dark:border-neutral-800 dark:hover:bg-neutral-800/40"
                        >
                            <td class="px-4 py-4">
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    :checked="selectedIds.includes(invoice.id)"
                                    @change="toggleSelect(invoice.id)"
                                />
                            </td>
                            <td class="px-3 py-4">
                                <button type="button" class="flex flex-col items-start gap-0.5 text-start" @click="openInvoice(invoice)">
                                    <p class="font-semibold tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                        {{ invoice.invoice_number }}
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        أُنشئت في: <span dir="ltr">{{ formatDate(invoice.created_at) }}</span>
                                    </p>
                                </button>
                            </td>
                            <td class="px-3 py-4">
                                <div class="flex min-w-0 flex-col items-start gap-1">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ customerName(invoice) }}</p>
                                    <p
                                        v-if="invoice.brand?.name || invoice.user?.email"
                                        class="flex items-center gap-1 truncate text-xs text-gray-400"
                                    >
                                        <Building2 v-if="invoice.brand?.name" class="size-3 shrink-0" />
                                        <span :dir="invoice.brand?.name ? undefined : 'ltr'">{{ invoice.brand?.name || invoice.user?.email }}</span>
                                    </p>
                                </div>
                            </td>
                            <td class="px-3 py-4 font-semibold tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                {{ formatCurrency(Number(invoice.amount) || 0) }}
                            </td>
                            <td class="px-3 py-4 tabular-nums" dir="ltr">
                                <span
                                    v-if="paidAmount(invoice) > 0"
                                    class="font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    {{ formatCurrency(paidAmount(invoice)) }}
                                </span>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-3 py-4 tabular-nums" dir="ltr">
                                <span
                                    v-if="dueAmount(invoice) > 0"
                                    class="font-semibold text-red-600 dark:text-red-400"
                                >
                                    {{ formatCurrency(dueAmount(invoice)) }}
                                </span>
                                <span v-else class="text-gray-400">-</span>
                            </td>
                            <td class="px-3 py-4 text-gray-600 dark:text-neutral-300">
                                {{ invoice.due_date ? formatDate(invoice.due_date) : '—' }}
                            </td>
                            <td class="px-3 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="statusBadgeClass(invoice.status)"
                                >
                                    {{ statusLabel(invoice.status) }}
                                </span>
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
                                        <DropdownMenuContent align="end" class="min-w-44">
                                            <DropdownMenuItem class="gap-2" @click="openInvoice(invoice)">
                                                <Eye class="size-4" />
                                                عرض الفاتورة
                                            </DropdownMenuItem>
                                            <DropdownMenuItem class="gap-2" @click="viewInvoice(invoice)">
                                                <FileText class="size-4" />
                                                عرض PDF
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                v-if="invoice.status !== 'paid'"
                                                class="gap-2"
                                                @click="updateStatus(invoice, 'paid')"
                                            >
                                                <Check class="size-4" />
                                                تعيين كمدفوعة
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="invoice.status !== 'cancelled'"
                                                class="gap-2 text-red-600 focus:text-red-600"
                                                @click="updateStatus(invoice, 'cancelled')"
                                            >
                                                <X class="size-4" />
                                                إلغاء الفاتورة
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
                    عرض {{ formatInteger(invoices.from ?? 0) }} - {{ formatInteger(invoices.to ?? 0) }} من {{ formatInteger(invoices.total) }}
                </p>

                <div v-if="invoices.last_page > 1" class="flex items-center justify-center gap-1.5 sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-50 disabled:opacity-40 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800"
                        :disabled="invoices.current_page <= 1"
                        @click="goToPage(invoices.current_page - 1)"
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
                                invoices.current_page === item
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
                        :disabled="invoices.current_page >= invoices.last_page"
                        @click="goToPage(invoices.current_page + 1)"
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
