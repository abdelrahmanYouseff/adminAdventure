<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock3,
    HardHat,
    Package,
    Search,
    ShieldCheck,
} from 'lucide-vue-next';
import { formatDate, formatInteger } from '@/lib/formatNumber';
import MainAppLayout from '../../layouts/MainAppLayout.vue';

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
    activity_time: string | null;
    status: 'pending' | 'completed';
    products_count: number;
    pending_count: number;
    completed_count: number;
    photos_ready?: boolean;
    is_approved?: boolean;
    can_approve?: boolean;
    preview_products: PreviewProduct[];
}

type StatusTab = 'pending' | 'completed' | 'all';

interface Props {
    user: {
        id: number;
        name: string;
        role_label: string;
    };
    workOrders: {
        data: WorkOrderItem[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    stats: {
        pending: number;
        completed: number;
        total: number;
    };
    filters: {
        status: string;
        search?: string;
    };
}

const props = defineProps<Props>();
const page = usePage();
const successMessage = computed(() => (page.props.flash as { success?: string } | undefined)?.success);

const search = ref(props.filters.search ?? '');
const activeStatus = computed<StatusTab>(() => {
    const status = props.filters.status;
    return status === 'completed' || status === 'all' ? status : 'pending';
});

watch(
    () => props.filters.search,
    (value) => {
        search.value = value ?? '';
    },
);

function applyFilters(overrides: Partial<{ status: StatusTab; search: string; page?: number }> = {}) {
    router.get(
        '/main-app',
        {
            status: overrides.status ?? activeStatus.value,
            search: overrides.search ?? search.value,
            page: overrides.page ?? 1,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

let searchTimer: ReturnType<typeof setTimeout> | null = null;
function onSearchInput() {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters({ search: search.value.trim() }), 350);
}

function formatInstallDate(date: string | null): string {
    if (!date) return 'بدون تاريخ';
    return formatDate(date);
}

function formatActivityTime(time: string | null): string {
    if (!time) return '—';
    const [hourStr, minuteStr = '00'] = time.split(':');
    let hour = Number(hourStr);
    if (Number.isNaN(hour)) return time;
    const period = hour >= 12 ? 'م' : 'ص';
    hour = hour % 12 || 12;
    return `${hour}:${minuteStr.padStart(2, '0')} ${period}`;
}

function statusBadge(item: WorkOrderItem): { label: string; className: string } {
    if (item.is_approved) {
        return { label: 'مُعمَّد', className: 'bg-emerald-50 text-emerald-700 ring-emerald-100' };
    }
    if (item.can_approve || item.photos_ready) {
        return { label: 'بانتظار التعميد', className: 'bg-amber-50 text-amber-700 ring-amber-100' };
    }
    if (item.status === 'completed') {
        return { label: 'مكتمل التركيب', className: 'bg-sky-50 text-sky-700 ring-sky-100' };
    }
    return { label: 'قيد التنفيذ', className: 'bg-slate-100 text-slate-600 ring-slate-200' };
}

const tabs = computed(() => [
    { key: 'pending' as const, label: 'جارية', count: props.stats.pending },
    { key: 'completed' as const, label: 'مكتملة', count: props.stats.completed },
    { key: 'all' as const, label: 'الكل', count: props.stats.total },
]);
</script>

<template>
    <Head title="أوامر العمل" />

    <MainAppLayout active-nav="orders">
        <div class="px-4 pt-5" style="padding-top: max(1.25rem, env(safe-area-inset-top))">
            <header class="mb-4">
                <p class="text-xs font-semibold tracking-wide text-teal-700/80">{{ user.role_label }}</p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900">أوامر العمل</h1>
                <p class="mt-1 text-sm text-slate-500">مرحباً {{ user.name }}</p>
            </header>

            <div
                v-if="successMessage"
                class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
            >
                {{ successMessage }}
            </div>

            <div class="mb-4 flex h-12 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 shadow-sm">
                <Search class="h-4 w-4 shrink-0 text-slate-400" />
                <input
                    v-model="search"
                    type="search"
                    placeholder="ابحث بالعميل أو رقم الأمر..."
                    class="w-full bg-transparent text-sm outline-none placeholder:text-slate-400"
                    @input="onSearchInput"
                />
            </div>

            <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3.5 py-2 text-xs font-bold ring-1 transition"
                    :class="activeStatus === tab.key
                        ? 'bg-teal-700 text-white ring-teal-700'
                        : 'bg-white text-slate-600 ring-slate-200'"
                    @click="applyFilters({ status: tab.key })"
                >
                    {{ tab.label }}
                    <span
                        class="rounded-full px-1.5 py-0.5 text-[10px]"
                        :class="activeStatus === tab.key ? 'bg-white/20' : 'bg-slate-100'"
                    >
                        {{ formatInteger(tab.count) }}
                    </span>
                </button>
            </div>

            <div v-if="workOrders.data.length" class="space-y-3">
                <Link
                    v-for="item in workOrders.data"
                    :key="item.id"
                    :href="`/main-app/work-orders/${encodeURIComponent(item.reference_number)}`"
                    class="block overflow-hidden rounded-[1.5rem] border border-white/80 bg-white p-4 shadow-sm transition active:scale-[0.99]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-base font-bold text-slate-900">{{ item.customer_name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500" dir="ltr">{{ item.reference_number }}</p>
                        </div>
                        <span
                            class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1"
                            :class="statusBadge(item).className"
                        >
                            {{ statusBadge(item).label }}
                        </span>
                    </div>

                    <div class="mt-3 space-y-1.5 text-xs text-slate-600">
                        <p class="flex items-center gap-1.5">
                            <CalendarDays class="h-3.5 w-3.5 text-slate-400" />
                            {{ formatInstallDate(item.installation_date) }}
                        </p>
                        <p class="flex items-center gap-1.5">
                            <Clock3 class="h-3.5 w-3.5 text-slate-400" />
                            {{ formatActivityTime(item.activity_time) }}
                        </p>
                        <p class="flex items-center gap-1.5">
                            <Package class="h-3.5 w-3.5 text-slate-400" />
                            تركيب {{ item.completed_count }}/{{ item.products_count }}
                        </p>
                    </div>

                    <div v-if="item.preview_products?.length" class="mt-3 flex -space-x-2 space-x-reverse">
                        <div
                            v-for="(product, idx) in item.preview_products.slice(0, 3)"
                            :key="`${item.id}-${idx}`"
                            class="h-9 w-9 overflow-hidden rounded-xl border-2 border-white bg-slate-100"
                        >
                            <img
                                v-if="product.image_url"
                                :src="product.image_url"
                                :alt="product.name"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex h-full items-center justify-center text-slate-300">
                                <HardHat class="h-4 w-4" />
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between">
                        <span
                            v-if="item.can_approve"
                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700"
                        >
                            <ShieldCheck class="h-3.5 w-3.5" />
                            جاهز للتعميد
                        </span>
                        <span
                            v-else-if="item.is_approved"
                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700"
                        >
                            <CheckCircle2 class="h-3.5 w-3.5" />
                            تم التعميد
                        </span>
                        <span v-else class="text-[11px] text-slate-400">عرض التفاصيل</span>
                        <ChevronLeft class="h-4 w-4 text-slate-300" />
                    </div>
                </Link>
            </div>

            <div
                v-else
                class="rounded-[1.5rem] border border-dashed border-slate-200 bg-white/70 px-5 py-12 text-center"
            >
                <HardHat class="mx-auto h-10 w-10 text-slate-300" />
                <p class="mt-3 text-sm font-semibold text-slate-700">لا توجد أوامر عمل</p>
                <p class="mt-1 text-xs text-slate-500">جرّب تغيير الفلتر أو البحث</p>
            </div>

            <div
                v-if="workOrders.last_page > 1"
                class="mt-5 flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-3 py-2"
            >
                <button
                    type="button"
                    class="inline-flex h-9 items-center gap-1 rounded-xl px-3 text-xs font-semibold text-slate-600 disabled:opacity-40"
                    :disabled="workOrders.current_page <= 1"
                    @click="applyFilters({ page: workOrders.current_page - 1 })"
                >
                    <ChevronRight class="h-4 w-4" />
                    السابق
                </button>
                <span class="text-xs text-slate-500">
                    {{ workOrders.current_page }} / {{ workOrders.last_page }}
                </span>
                <button
                    type="button"
                    class="inline-flex h-9 items-center gap-1 rounded-xl px-3 text-xs font-semibold text-slate-600 disabled:opacity-40"
                    :disabled="workOrders.current_page >= workOrders.last_page"
                    @click="applyFilters({ page: workOrders.current_page + 1 })"
                >
                    التالي
                    <ChevronLeft class="h-4 w-4" />
                </button>
            </div>
        </div>
    </MainAppLayout>
</template>
