<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage, Deferred } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    HardHat,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    ImageIcon,
    ArrowRight,
    ArrowUpRight,
    Eye,
    ShieldCheck,
    Search,
    Filter,
} from 'lucide-vue-next';
import { formatCurrency, formatDate, formatInteger } from '@/lib/formatNumber';
import Swal from 'sweetalert2';

interface PreviewProduct {
    name: string;
    image_url: string | null;
}

interface WorkOrderItem {
    id: number;
    reference_number: string;
    order_number: string;
    invoice_number: string | null;
    customer_name: string;
    customer_address: string | null;
    installation_date: string | null;
    status: 'pending' | 'completed';
    products_count: number;
    pending_count: number;
    completed_count: number;
    location_slug: string | null;
    photos_ready?: boolean;
    is_approved?: boolean;
    can_approve?: boolean;
    approved_at?: string | null;
    currency?: string;
    remaining_amount?: number;
    preview_products: PreviewProduct[];
}

type StatusTab = 'pending' | 'completed' | 'all';
type DateRange = 'all' | '7' | '30';

interface Props {
    workOrders?: {
        data: WorkOrderItem[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    stats?: {
        pending: number;
        completed: number;
        total: number;
    };
    filters: {
        status: string;
        search?: string;
        date_range?: string;
    };
}

const props = defineProps<Props>();

const workOrders = computed(() => props.workOrders ?? {
    data: [],
    current_page: 1,
    last_page: 1,
    total: 0,
    from: null,
    to: null,
});

const stats = computed(() => props.stats ?? {
    pending: 0,
    completed: 0,
    total: 0,
});

defineOptions({ layout: AppLayout });

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string } | undefined) ?? {});
const authRole = computed(() => (page.props.auth as { user?: { role?: string } } | undefined)?.user?.role ?? null);
const canApproveOrders = computed(() =>
    ['admin', 'manager', 'workers_manager'].includes(authRole.value || ''),
);

watch(
    () => [flash.value.success, flash.value.error] as const,
    ([success, error]) => {
        if (success) {
            Swal.fire({
                icon: 'success',
                title: 'تم بنجاح',
                text: success,
                confirmButtonText: 'حسناً',
                confirmButtonColor: '#2563EB',
                timer: 3200,
                timerProgressBar: true,
            });
            return;
        }

        if (error) {
            Swal.fire({
                icon: 'error',
                title: 'تعذر الإجراء',
                text: error,
                confirmButtonText: 'حسناً',
                confirmButtonColor: '#2563EB',
            });
        }
    },
    { immediate: true },
);

const statusFilter = ref<StatusTab>((props.filters.status as StatusTab) || 'pending');
const searchQuery = ref(props.filters.search || '');
const dateRange = ref<DateRange>((props.filters.date_range as DateRange) || 'all');
const showFilters = ref(false);
const mobileListVisible = ref(false);
const approvingId = ref<number | null>(null);
const selectedIds = ref<number[]>([]);

const statusTabs: { key: StatusTab; label: string }[] = [
    { key: 'pending', label: 'قيد التركيب' },
    { key: 'completed', label: 'مرفوعة للمراجعة' },
    { key: 'all', label: 'الكل' },
];

const summaryCards = computed(() => [
    {
        key: 'pending' as const,
        label: 'قيد التركيب',
        value: stats.value.pending,
        unit: 'طلب',
        hint: 'عرض قيد التركيب',
    },
    {
        key: 'completed' as const,
        label: 'مرفوعة للمراجعة',
        value: stats.value.completed,
        unit: 'طلب',
        hint: 'عرض المرفوعة للمراجعة',
    },
    {
        key: 'all' as const,
        label: 'إجمالي الأوامر',
        value: stats.value.total,
        unit: 'طلب',
        hint: 'عرض كل الأوامر',
    },
]);

const mobileListTitle = computed(() => {
    if (statusFilter.value === 'completed') {
        return 'مرفوعة للمراجعة';
    }

    if (statusFilter.value === 'all') {
        return 'كل أوامر العمل';
    }

    return 'قيد التركيب';
});

const pageNumbers = computed(() => {
    const total = workOrders.value.last_page;
    const current = workOrders.value.current_page;
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }

    const pages: Array<number | 'ellipsis'> = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);

    if (start > 2) {
        pages.push('ellipsis');
    }
    for (let i = start; i <= end; i += 1) {
        pages.push(i);
    }
    if (end < total - 1) {
        pages.push('ellipsis');
    }
    pages.push(total);
    return pages;
});

const allVisibleSelected = computed(
    () => workOrders.value.data.length > 0
        && workOrders.value.data.every((item) => selectedIds.value.includes(item.id)),
);

function tabCount(tab: StatusTab): number {
    if (tab === 'pending') return stats.value.pending;
    if (tab === 'completed') return stats.value.completed;
    return stats.value.total;
}

function applyFilters(pageNumber = 1) {
    router.get('/worker-orders', {
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        search: searchQuery.value.trim() || undefined,
        date_range: dateRange.value !== 'all' ? dateRange.value : undefined,
        page: pageNumber > 1 ? pageNumber : undefined,
    }, { preserveState: false, preserveScroll: true });
}

function setStatusFilter(status: StatusTab) {
    statusFilter.value = status;
    applyFilters(1);
}

function submitSearch() {
    applyFilters(1);
}

function isMobileView(): boolean {
    return typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches;
}

function openMobileList(status: StatusTab) {
    mobileListVisible.value = true;

    if (statusFilter.value !== status) {
        setStatusFilter(status);
    }
}

function handleStatCardClick(status: StatusTab) {
    if (isMobileView()) {
        openMobileList(status);
        return;
    }

    setStatusFilter(status);
}

function closeMobileList() {
    mobileListVisible.value = false;
}

function goToPage(pageNumber: number) {
    if (pageNumber >= 1 && pageNumber <= workOrders.value.last_page) {
        applyFilters(pageNumber);
    }
}

function formatEventDate(date: string | null): string {
    if (!date) {
        return 'غير محدد';
    }

    return formatDate(date);
}

function workOrderUrl(item: WorkOrderItem): string {
    return `/worker-orders/${encodeURIComponent(item.reference_number)}`;
}

function openWorkOrder(item: WorkOrderItem) {
    router.visit(workOrderUrl(item));
}

function toggleSelectAll() {
    if (allVisibleSelected.value) {
        const visibleIds = new Set(workOrders.value.data.map((item) => item.id));
        selectedIds.value = selectedIds.value.filter((id) => !visibleIds.has(id));
        return;
    }

    selectedIds.value = Array.from(new Set([
        ...selectedIds.value,
        ...workOrders.value.data.map((item) => item.id),
    ]));
}

function toggleSelect(id: number) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((item) => item !== id);
        return;
    }
    selectedIds.value = [...selectedIds.value, id];
}

function statusBadgeClass(item: WorkOrderItem): string {
    if (item.status === 'completed') {
        return 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50';
    }

    if (item.completed_count > 0) {
        return 'bg-sky-50 text-sky-700 ring-1 ring-inset ring-sky-100 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-900/50';
    }

    return 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900/50';
}

function statusLabel(item: WorkOrderItem): string {
    if (item.status === 'completed') {
        return 'مرفوعة للمراجعة';
    }

    if (item.completed_count > 0) {
        return `قيد التركيب (${item.completed_count}/${item.products_count})`;
    }

    return 'قيد التركيب';
}

function dateFilterLabel(value: DateRange): string {
    if (value === '7') return 'آخر 7 أيام';
    if (value === '30') return 'آخر 30 يوم';
    return 'كل الفترات';
}

async function approveWorkOrder(item: WorkOrderItem) {
    if (item.is_approved) {
        return;
    }

    if (!item.can_approve) {
        await Swal.fire({
            icon: 'info',
            title: 'لا يمكن التعميد الآن',
            text: item.photos_ready
                ? 'تعميد أمر العمل مخصص لمدير العمال فقط.'
                : 'يجب أن يرفع العامل صور التركيب وصور الاستلام لجميع المنتجات أولاً.',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#2563EB',
        });
        return;
    }

    const result = await Swal.fire({
        icon: 'question',
        title: 'تعميد مدير العمال',
        text: `تأكيد تعميد الطلب ${item.reference_number}؟ سيظهر التأمين في صفحة الاسترداد بعد تأكيد أمين المستودع لاسترجاع المنتجات، ثم تعميد المسئول والمدير العام والمحاسب.`,
        showCancelButton: true,
        confirmButtonText: 'تعميد',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#059669',
        cancelButtonColor: '#64748B',
        reverseButtons: true,
    });

    if (!result.isConfirmed) {
        return;
    }

    approvingId.value = item.id;
    router.post(`/worker-orders/${encodeURIComponent(item.reference_number)}/approve`, {}, {
        preserveScroll: true,
        onFinish: () => {
            approvingId.value = null;
        },
    });
}

watch(
    () => props.filters,
    (filters) => {
        statusFilter.value = (filters.status as StatusTab) || 'pending';
        searchQuery.value = filters.search || '';
        dateRange.value = (filters.date_range as DateRange) || 'all';
        selectedIds.value = [];
    },
);
</script>

<template>
    <Head title="أوامر العمل" />

    <Deferred :data="['workOrders', 'stats']">
        <template #fallback>
            <div class="flex min-h-[50vh] items-center justify-center p-6 text-sm text-gray-500">
                جاري تحميل أوامر العمل...
            </div>
        </template>

        <div class="flex min-w-0 flex-1 flex-col gap-5 overflow-x-hidden p-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:gap-6 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-white sm:text-2xl">
                        <HardHat class="size-6 text-blue-600" />
                        أوامر العمل
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">
                        طلب واحد لكل فاتورة — اضغط لعرض المنتجات المطلوب تركيبها
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
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
                    @click="handleStatCardClick(card.key)"
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

            <div :class="mobileListVisible ? 'block' : 'hidden md:block'" class="space-y-4">
                <div class="flex items-center justify-between md:hidden">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ mobileListTitle }}</h2>
                    <Button variant="outline" size="sm" class="h-9 touch-manipulation" @click="closeMobileList">
                        <ArrowRight class="ms-1 h-4 w-4" />
                        رجوع
                    </Button>
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
                                <label class="flex h-10 items-center gap-2 rounded-full border border-gray-200 bg-white px-3.5 text-gray-400 transition focus-within:border-blue-300 focus-within:ring-2 focus-within:ring-blue-100 dark:border-neutral-700 dark:bg-neutral-950 dark:focus-within:border-blue-700 dark:focus-within:ring-blue-950">
                                    <Search class="size-4 shrink-0 stroke-[1.75]" />
                                    <input
                                        v-model="searchQuery"
                                        type="search"
                                        placeholder="ابحث هنا..."
                                        class="w-full bg-transparent text-sm text-gray-800 outline-none placeholder:text-gray-400 dark:text-neutral-100"
                                    />
                                </label>
                            </form>

                            <button
                                type="button"
                                class="inline-flex h-10 items-center gap-2 rounded-full border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                :class="showFilters ? 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300' : ''"
                                @click="showFilters = !showFilters"
                            >
                                <Filter class="size-4 stroke-[1.75]" />
                                فلاتر
                            </button>

                            <div class="relative">
                                <select
                                    v-model="dateRange"
                                    class="h-10 appearance-none rounded-full border border-gray-200 bg-white pe-9 ps-10 text-sm font-medium text-gray-600 outline-none transition hover:bg-gray-50 focus:border-blue-300 focus:ring-2 focus:ring-blue-100 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                    @change="applyFilters(1)"
                                >
                                    <option value="all">كل الفترات</option>
                                    <option value="7">آخر 7 أيام</option>
                                    <option value="30">آخر 30 يوم</option>
                                </select>
                                <CalendarDays class="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                            </div>
                        </div>

                        <p class="text-xs text-gray-400 sm:text-sm">
                            {{ dateFilterLabel(dateRange) }} · {{ formatInteger(workOrders.total) }} نتيجة
                        </p>
                    </div>

                    <div v-if="showFilters" class="border-b border-gray-100 px-4 py-3 dark:border-neutral-800">
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="tab in statusTabs"
                                :key="`filter-${tab.key}`"
                                type="button"
                                class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                                :class="
                                    statusFilter === tab.key
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700'
                                "
                                @click="setStatusFilter(tab.key)"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[980px] border-collapse text-sm">
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
                                    <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الرقم المرجعي</th>
                                    <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">العميل</th>
                                    <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">يوم الفعالية</th>
                                    <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">المنتجات</th>
                                    <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700 dark:text-neutral-200">الحالة</th>
                                    <th class="px-4 py-3.5 text-end text-[13px] font-semibold text-gray-700 dark:text-neutral-200">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="workOrders.data.length === 0">
                                    <td colspan="7" class="px-4 py-16 text-center text-gray-500 dark:text-neutral-400">
                                        لا توجد أوامر عمل مطابقة للبحث أو الفلتر الحالي.
                                    </td>
                                </tr>
                                <tr
                                    v-for="item in workOrders.data"
                                    :key="item.id"
                                    class="cursor-pointer border-b border-gray-100 transition hover:bg-gray-50/70 dark:border-neutral-800 dark:hover:bg-neutral-800/40"
                                    @click="openWorkOrder(item)"
                                >
                                    <td class="px-4 py-4" @click.stop>
                                        <input
                                            type="checkbox"
                                            class="size-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            :checked="selectedIds.includes(item.id)"
                                            @change="toggleSelect(item.id)"
                                        />
                                    </td>
                                    <td class="px-3 py-4">
                                        <div class="flex flex-col items-start gap-0.5">
                                            <p class="font-semibold tabular-nums text-gray-900 dark:text-white" dir="ltr">
                                                {{ item.order_number }}
                                            </p>
                                            <p
                                                v-if="item.invoice_number"
                                                class="text-xs tabular-nums text-gray-400"
                                                dir="ltr"
                                            >
                                                #{{ item.invoice_number }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-3 py-4">
                                        <div class="flex min-w-0 flex-col items-start gap-1">
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ item.customer_name }}</p>
                                            <p
                                                v-if="(item.remaining_amount || 0) > 0"
                                                class="text-xs font-semibold text-amber-600"
                                            >
                                                متبقي <span dir="ltr" class="tabular-nums">{{ formatCurrency(item.remaining_amount || 0, item.currency || 'SAR') }}</span>
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-3 py-4 tabular-nums text-gray-600 dark:text-neutral-300">
                                        {{ formatEventDate(item.installation_date) }}
                                    </td>
                                    <td class="px-3 py-4">
                                        <div class="flex items-center gap-2.5">
                                            <div class="flex -space-x-2 space-x-reverse">
                                                <div
                                                    v-for="(product, pIndex) in item.preview_products"
                                                    :key="pIndex"
                                                    class="size-8 overflow-hidden rounded-full bg-gray-100 ring-2 ring-white dark:bg-neutral-800 dark:ring-neutral-900"
                                                >
                                                    <img
                                                        v-if="product.image_url"
                                                        :src="product.image_url"
                                                        :alt="product.name"
                                                        class="h-full w-full object-cover"
                                                    />
                                                    <div
                                                        v-else
                                                        class="flex h-full w-full items-center justify-center text-gray-400"
                                                    >
                                                        <ImageIcon class="size-3.5 opacity-50" />
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="text-sm text-gray-600 dark:text-neutral-300">
                                                <span class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ formatInteger(item.products_count) }}</span>
                                                منتج
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-4">
                                        <div class="flex flex-col items-start gap-1.5">
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                                :class="statusBadgeClass(item)"
                                            >
                                                {{ statusLabel(item) }}
                                            </span>
                                            <span
                                                v-if="item.is_approved"
                                                class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900/50"
                                            >
                                                <ShieldCheck class="size-3" />
                                                معتمد
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4" @click.stop>
                                        <div class="flex items-center justify-end gap-1.5">
                                            <Link
                                                :href="workOrderUrl(item)"
                                                class="inline-flex size-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-600 dark:border-neutral-700 dark:hover:border-blue-900 dark:hover:bg-blue-950/40 dark:hover:text-blue-300"
                                                title="التفاصيل"
                                            >
                                                <Eye class="size-3.5 stroke-[1.75]" />
                                            </Link>
                                            <button
                                                v-if="canApproveOrders"
                                                type="button"
                                                class="inline-flex size-8 items-center justify-center rounded-lg border transition"
                                                :class="item.is_approved
                                                    ? 'cursor-default border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                    : item.can_approve
                                                        ? 'border-emerald-200 text-emerald-600 hover:bg-emerald-50 dark:border-emerald-900 dark:text-emerald-300 dark:hover:bg-emerald-950/40'
                                                        : 'border-gray-200 text-gray-400 hover:bg-gray-50 dark:border-neutral-700 dark:hover:bg-neutral-800'"
                                                :disabled="Boolean(item.is_approved) || approvingId === item.id"
                                                :title="item.is_approved
                                                    ? 'تم التعميد'
                                                    : item.can_approve
                                                        ? 'تعميد أمر العمل'
                                                        : 'يلزم رفع صور التركيب والاستلام أولاً'"
                                                @click="approveWorkOrder(item)"
                                            >
                                                <ShieldCheck class="size-3.5 stroke-[1.75]" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-gray-100 px-4 py-4 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-500 dark:text-neutral-400">
                            عرض {{ formatInteger(workOrders.from ?? 0) }} - {{ formatInteger(workOrders.to ?? 0) }} من {{ formatInteger(workOrders.total) }}
                        </p>

                        <div v-if="workOrders.last_page > 1" class="flex items-center gap-1.5">
                            <button
                                type="button"
                                class="inline-flex size-8 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200 disabled:opacity-40 dark:bg-neutral-800 dark:hover:bg-neutral-700"
                                :disabled="workOrders.current_page <= 1"
                                @click="goToPage(workOrders.current_page - 1)"
                            >
                                <ChevronRight class="size-4" />
                            </button>

                            <template v-for="(item, index) in pageNumbers" :key="`${item}-${index}`">
                                <span v-if="item === 'ellipsis'" class="px-1 text-gray-400">...</span>
                                <button
                                    v-else
                                    type="button"
                                    class="inline-flex size-8 items-center justify-center rounded-full text-sm font-medium transition"
                                    :class="
                                        workOrders.current_page === item
                                            ? 'bg-blue-600 text-white'
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700'
                                    "
                                    @click="goToPage(item)"
                                >
                                    {{ item }}
                                </button>
                            </template>

                            <button
                                type="button"
                                class="inline-flex size-8 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200 disabled:opacity-40 dark:bg-neutral-800 dark:hover:bg-neutral-700"
                                :disabled="workOrders.current_page >= workOrders.last_page"
                                @click="goToPage(workOrders.current_page + 1)"
                            >
                                <ChevronLeft class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Deferred>
</template>
