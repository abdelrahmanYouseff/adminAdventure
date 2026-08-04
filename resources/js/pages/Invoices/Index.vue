<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Building2,
    ChevronLeft,
    ChevronRight,
    Download,
    Eye,
    FileText,
    MoreVertical,
    Search,
} from 'lucide-vue-next';
import { formatCurrency, formatDate, formatInteger } from '@/lib/formatNumber';

interface InvoiceUser {
    id: number;
    customer_name?: string | null;
    name?: string | null;
    email?: string | null;
}

interface InvoiceBrand {
    id: number;
    name: string;
    slug?: string;
}

interface InvoiceOrder {
    id: number;
    order_number?: string | null;
    customer_name?: string | null;
    currency?: string | null;
}

interface InvoiceItem {
    id: number;
    invoice_number: string;
    amount: number | string;
    customer_name?: string | null;
    order_number?: string | null;
    currency?: string | null;
    payment_method?: string | null;
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
}

interface Props {
    invoices: PaginatedInvoices;
    brands: BrandOption[];
    selectedBrandId?: number | null;
    filters?: {
        search?: string;
        per_page?: number;
    };
    totalCount?: number;
}

const props = withDefaults(defineProps<Props>(), {
    selectedBrandId: null,
    brands: () => [],
    filters: () => ({
        search: '',
        per_page: 15,
    }),
    totalCount: 0,
});

defineOptions({ layout: AppLayout });

const searchQuery = ref(props.filters?.search || '');
const perPage = ref(props.filters?.per_page || 15);

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

watch(
    () => props.filters,
    (filters) => {
        searchQuery.value = filters?.search || '';
        perPage.value = filters?.per_page || 15;
    },
);

function customerName(invoice: InvoiceItem): string {
    return invoice.customer_name
        || invoice.order?.customer_name
        || invoice.user?.customer_name
        || invoice.user?.name
        || '—';
}

function applyFilters(page = 1) {
    router.get(
        route('invoices.index'),
        {
            page: page > 1 ? page : undefined,
            brand: props.selectedBrandId || undefined,
            search: searchQuery.value.trim() || undefined,
            per_page: perPage.value !== 15 ? perPage.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
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
            per_page: perPage.value !== 15 ? perPage.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function goToPage(page: number) {
    if (page < 1 || page > props.invoices.last_page) return;
    applyFilters(page);
}

function viewInvoice(invoice: InvoiceItem) {
    window.open(route('invoices.pdf', invoice.id), '_blank');
}

function openInvoice(invoice: InvoiceItem) {
    router.visit(route('invoices.show', invoice.id));
}

function exportInvoices() {
    const params = new URLSearchParams();
    if (props.selectedBrandId) params.set('brand', String(props.selectedBrandId));
    if (searchQuery.value.trim()) params.set('search', searchQuery.value.trim());
    const query = params.toString();
    window.open(route('invoices.export') + (query ? `?${query}` : ''), '_blank');
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
                    الفواتير الصادرة بعد سداد الطلب بالكامل
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" class="gap-2 rounded-xl" @click="exportInvoices">
                    <Download class="size-4" />
                    تصدير CSV
                </Button>
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
                                placeholder="ابحث برقم الفاتورة أو اسم العميل..."
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
                <table class="w-full min-w-[720px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-start dark:border-neutral-800">
                            <th class="px-4 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الفاتورة</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">اسم العميل</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">المبلغ</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">تاريخ الإصدار</th>
                            <th class="px-4 py-3.5 text-end text-[13px] font-semibold text-gray-700 dark:text-neutral-200" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="invoices.data.length === 0">
                            <td colspan="5" class="px-4 py-16 text-center text-gray-500 dark:text-neutral-400">
                                لا توجد فواتير صادرة بعد. تظهر الفاتورة هنا بعد سداد الطلب بالكامل.
                            </td>
                        </tr>
                        <tr
                            v-for="invoice in invoices.data"
                            :key="invoice.id"
                            class="border-b border-gray-100 transition hover:bg-gray-50/70 dark:border-neutral-800 dark:hover:bg-neutral-800/40"
                        >
                            <td class="px-4 py-4">
                                <button type="button" class="flex flex-col items-start gap-0.5 text-start" @click="openInvoice(invoice)">
                                    <p class="font-semibold tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                        {{ invoice.invoice_number }}
                                    </p>
                                    <p v-if="invoice.order_number || invoice.order?.order_number" class="text-xs text-gray-400" dir="ltr">
                                        {{ invoice.order_number || invoice.order?.order_number }}
                                    </p>
                                </button>
                            </td>
                            <td class="px-3 py-4">
                                <div class="flex min-w-0 flex-col items-start gap-1">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ customerName(invoice) }}</p>
                                    <p
                                        v-if="invoice.brand?.name"
                                        class="flex items-center gap-1 truncate text-xs text-gray-400"
                                    >
                                        <Building2 class="size-3 shrink-0" />
                                        <span>{{ invoice.brand.name }}</span>
                                    </p>
                                </div>
                            </td>
                            <td class="px-3 py-4 font-semibold tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                {{ formatCurrency(Number(invoice.amount) || 0, invoice.currency || invoice.order?.currency || 'SAR') }}
                            </td>
                            <td class="px-3 py-4 text-gray-600 dark:text-neutral-300">
                                <span dir="ltr">{{ formatDate(invoice.created_at) }}</span>
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
