<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDateTime, formatInteger } from '@/lib/formatNumber';
import {
    CalendarClock,
    CheckCircle2,
    ChevronDown,
    MessageSquareText,
    PackageCheck,
    Search,
    Undo2,
    UserCheck,
    Users,
    UserX,
    Wrench,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';

interface ReturnProduct {
    id: number;
    product_name: string;
}

interface ProductReturn {
    id: number;
    order_number: string;
    customer_name: string;
    customer_phone: string | null;
    products_count: number;
    products: ReturnProduct[];
    dismantling_at: string | null;
    days_until_dismantling: number | null;
    dismantling_label: string;
    dismantling_tone: 'ok' | 'warn' | 'due' | 'overdue' | 'muted';
    warehouse_returned_at: string | null;
    warehouse_returned_by_name: string | null;
    is_returned: boolean;
    is_assigned: boolean;
    assigned_workers: string[];
    can_confirm: boolean;
    notes_count: number;
}

interface WorkerAppointment {
    order_id: number;
    order_number: string;
    customer_name: string;
    at: string | null;
    label: string;
}

interface WorkerBoardRow {
    id: number;
    name: string;
    phone: string | null;
    is_online: boolean;
    connection_label: string;
    is_active: boolean;
    has_installation: boolean;
    has_dismantling: boolean;
    installation: WorkerAppointment | null;
    dismantling: WorkerAppointment | null;
    status_key: 'both' | 'dismantling' | 'installation' | 'active' | 'offline';
    status_label: string;
    last_seen_at: string | null;
}

interface WorkersBoard {
    workers: WorkerBoardRow[];
    counts: {
        online: number;
        installation: number;
        dismantling: number;
        offline: number;
        total: number;
    };
}

interface PaginatedReturns {
    data: ProductReturn[];
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    returns: PaginatedReturns;
    workersBoard: WorkersBoard;
    stats: {
        pending: number;
        returned: number;
    };
    filters: {
        status: string;
        search: string;
    };
}

const props = withDefaults(defineProps<Props>(), {
    workersBoard: () => ({
        workers: [],
        counts: { online: 0, installation: 0, dismantling: 0, offline: 0, total: 0 },
    }),
});
defineOptions({ layout: AppLayout });

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string } | undefined) ?? {});
const searchQuery = ref(props.filters.search || '');
const confirmDialogOpen = ref(false);
const confirmTarget = ref<ProductReturn | null>(null);
const confirmForm = useForm({
    note: '',
});
const workerFilter = ref<'all' | 'online' | 'installation' | 'dismantling' | 'offline'>('all');

let presenceTimer: ReturnType<typeof setInterval> | null = null;

const statusTabs = [
    { key: 'pending', label: 'بانتظار الاسترجاع' },
    { key: 'returned', label: 'تم الاسترجاع' },
    { key: 'all', label: 'الكل' },
] as const;

const filteredWorkers = computed(() => {
    const list = props.workersBoard.workers ?? [];
    if (workerFilter.value === 'all') return list;
    if (workerFilter.value === 'online') return list.filter((w) => w.is_online);
    if (workerFilter.value === 'offline') return list.filter((w) => !w.is_online);
    if (workerFilter.value === 'installation') return list.filter((w) => w.has_installation);
    return list.filter((w) => w.has_dismantling);
});

onMounted(() => {
    presenceTimer = setInterval(() => {
        router.reload({ only: ['workersBoard'], preserveScroll: true, preserveState: true });
    }, 45000);
});

onBeforeUnmount(() => {
    if (presenceTimer) clearInterval(presenceTimer);
});

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

function setStatusFilter(status: string) {
    router.get(
        '/returns',
        {
            status: status === 'pending' ? undefined : status,
            search: searchQuery.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function submitSearch() {
    router.get(
        '/returns',
        {
            status: props.filters.status === 'pending' ? undefined : props.filters.status,
            search: searchQuery.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function tabCount(key: string): number {
    if (key === 'pending') return props.stats.pending;
    if (key === 'returned') return props.stats.returned;
    return props.stats.pending + props.stats.returned;
}

function openDetails(item: ProductReturn, event?: Event) {
    const target = event?.target as HTMLElement | undefined;
    if (target?.closest('a, button, input, textarea, select, label')) {
        return;
    }

    router.visit(`/returns/${item.id}`);
}

function openConfirmDialog(item: ProductReturn) {
    if (!item.can_confirm || confirmForm.processing) return;
    confirmTarget.value = item;
    confirmForm.clearErrors();
    confirmForm.note = '';
    confirmDialogOpen.value = true;
}

function closeConfirmDialog() {
    confirmDialogOpen.value = false;
    confirmTarget.value = null;
    confirmForm.reset();
    confirmForm.clearErrors();
}

function submitConfirm() {
    const target = confirmTarget.value;
    if (!target) return;

    confirmForm.post(`/returns/${target.id}/confirm`, {
        preserveScroll: true,
        onSuccess: () => closeConfirmDialog(),
    });
}

function dismantlingToneClass(tone: ProductReturn['dismantling_tone']): string {
    if (tone === 'overdue') return 'bg-rose-50 text-rose-700 ring-rose-100';
    if (tone === 'due') return 'bg-amber-50 text-amber-800 ring-amber-100';
    if (tone === 'warn') return 'bg-orange-50 text-orange-700 ring-orange-100';
    if (tone === 'ok') return 'bg-sky-50 text-sky-700 ring-sky-100';
    return 'bg-slate-50 text-slate-500 ring-slate-100';
}

function workerStatusClass(key: WorkerBoardRow['status_key']): string {
    if (key === 'dismantling') return 'bg-orange-50 text-orange-700 ring-orange-100';
    if (key === 'installation') return 'bg-sky-50 text-sky-700 ring-sky-100';
    if (key === 'both') return 'bg-violet-50 text-violet-700 ring-violet-100';
    if (key === 'active') return 'bg-emerald-50 text-emerald-700 ring-emerald-100';
    return 'bg-slate-100 text-slate-500 ring-slate-200';
}
</script>

<template>
    <Head title="الاسترجاع" />

    <div class="flex min-w-0 flex-1 flex-col gap-5 overflow-x-hidden p-3 sm:gap-6 sm:p-6" dir="rtl">
        <div>
            <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-white sm:text-2xl">
                <Undo2 class="size-6 text-orange-600" />
                الاسترجاع
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">
                كل الطلبات تظهر هنا حسب تاريخ الفك والأيام المتبقية — اضغط على السجل لفتح التفاصيل وتعيين عامل الفك
            </p>
        </div>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
            <div class="min-w-0 space-y-5">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4">
                    <button
                        type="button"
                        class="rounded-2xl border bg-white p-5 text-start transition hover:shadow-sm dark:bg-neutral-900"
                        :class="filters.status === 'pending' ? 'border-orange-300 ring-1 ring-orange-100' : 'border-gray-200 dark:border-neutral-700'"
                        @click="setStatusFilter('pending')"
                    >
                        <p class="text-xs font-bold uppercase tracking-wide text-gray-400">بانتظار الاسترجاع</p>
                        <p class="mt-2 text-2xl font-extrabold tabular-nums text-gray-900 dark:text-white">{{ formatInteger(stats.pending) }}</p>
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl border bg-white p-5 text-start transition hover:shadow-sm dark:bg-neutral-900"
                        :class="filters.status === 'returned' ? 'border-emerald-300 ring-1 ring-emerald-100' : 'border-gray-200 dark:border-neutral-700'"
                        @click="setStatusFilter('returned')"
                    >
                        <p class="text-xs font-bold uppercase tracking-wide text-gray-400">تم الاسترجاع</p>
                        <p class="mt-2 text-2xl font-extrabold tabular-nums text-gray-900 dark:text-white">{{ formatInteger(stats.returned) }}</p>
                    </button>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="flex flex-col gap-3 border-b border-gray-100 p-4 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between">
                        <div class="overflow-x-auto">
                            <div class="flex min-w-max items-center gap-1">
                                <button
                                    v-for="tab in statusTabs"
                                    :key="tab.key"
                                    type="button"
                                    class="relative px-3 py-2 text-sm font-medium transition-colors"
                                    :class="
                                        filters.status === tab.key
                                            ? 'text-blue-700 dark:text-blue-300'
                                            : 'text-gray-500 hover:text-gray-800 dark:text-neutral-400'
                                    "
                                    @click="setStatusFilter(tab.key)"
                                >
                                    {{ tab.label }}
                                    <span class="ms-1.5 text-xs tabular-nums text-gray-400">({{ formatInteger(tabCount(tab.key)) }})</span>
                                    <span
                                        v-if="filters.status === tab.key"
                                        class="absolute inset-x-0 -bottom-px h-0.5 rounded-full bg-blue-600"
                                    />
                                </button>
                            </div>
                        </div>

                        <form class="w-full max-w-sm" @submit.prevent="submitSearch">
                            <label class="flex h-10 items-center gap-2 rounded-full border border-gray-200 bg-white px-3.5 text-gray-400 dark:border-neutral-700 dark:bg-neutral-950">
                                <Search class="size-4 shrink-0" />
                                <input
                                    v-model="searchQuery"
                                    type="search"
                                    placeholder="ابحث برقم الطلب أو العميل..."
                                    class="w-full bg-transparent text-sm text-gray-800 outline-none placeholder:text-gray-400 dark:text-neutral-100"
                                />
                            </label>
                        </form>
                    </div>

                    <div v-if="returns.data.length === 0" class="px-4 py-16 text-center text-sm text-gray-500">
                        لا توجد طلبات في هذه القائمة حالياً.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[1020px] border-collapse text-right text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 bg-gray-50/80 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:border-neutral-800 dark:bg-neutral-950/50 dark:text-neutral-400">
                                    <th class="w-8 whitespace-nowrap px-4 py-3"></th>
                                    <th class="whitespace-nowrap px-4 py-3">الطلب</th>
                                    <th class="whitespace-nowrap px-4 py-3">العميل</th>
                                    <th class="whitespace-nowrap px-4 py-3">المنتجات</th>
                                    <th class="whitespace-nowrap px-4 py-3">تاريخ الفك</th>
                                    <th class="whitespace-nowrap px-4 py-3">التعيين</th>
                                    <th class="whitespace-nowrap px-4 py-3">باقي عليه</th>
                                    <th class="whitespace-nowrap px-4 py-3">الحالة</th>
                                    <th class="whitespace-nowrap px-4 py-3">إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="item in returns.data" :key="item.id">
                                    <tr
                                        class="cursor-pointer border-b border-gray-100 align-top transition hover:bg-orange-50/50 dark:border-neutral-800 dark:hover:bg-orange-950/20"
                                        @click="openDetails(item, $event)"
                                    >
                                        <td class="px-3 py-3 text-gray-400">
                                            <ChevronDown class="size-4 -rotate-90" />
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span>{{ item.order_number }}</span>
                                                <span
                                                    v-if="item.notes_count > 0"
                                                    class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600"
                                                >
                                                    <MessageSquareText class="size-3" />
                                                    {{ formatInteger(item.notes_count) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-gray-900 dark:text-white">{{ item.customer_name }}</p>
                                            <p v-if="item.customer_phone" class="mt-0.5 text-xs text-gray-500" dir="ltr">{{ item.customer_phone }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-gray-800 dark:text-neutral-200">{{ formatInteger(item.products_count) }} منتج</p>
                                            <ul class="mt-1 space-y-0.5 text-xs text-gray-500">
                                                <li v-for="product in item.products.slice(0, 3)" :key="product.id">{{ product.product_name }}</li>
                                                <li v-if="item.products.length > 3">+{{ item.products.length - 3 }} أخرى</li>
                                            </ul>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div v-if="item.dismantling_at" class="flex items-start gap-1.5 text-gray-800 dark:text-neutral-200">
                                                <CalendarClock class="mt-0.5 size-3.5 shrink-0 text-gray-400" />
                                                <span class="text-sm font-medium tabular-nums" dir="ltr">{{ formatDateTime(item.dismantling_at) }}</span>
                                            </div>
                                            <span v-else class="text-xs text-gray-400">—</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                v-if="item.is_assigned"
                                                class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 ring-1 ring-inset ring-sky-100"
                                            >
                                                <UserCheck class="size-3.5" />
                                                تم التعيين
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1 rounded-full bg-gray-50 px-2.5 py-1 text-xs font-semibold text-gray-500 ring-1 ring-inset ring-gray-200"
                                            >
                                                <UserX class="size-3.5" />
                                                لم يُعيَّن
                                            </span>
                                            <p v-if="item.is_assigned && item.assigned_workers.length" class="mt-1 text-[11px] text-gray-500">
                                                {{ item.assigned_workers.join('، ') }}
                                            </p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                                :class="dismantlingToneClass(item.dismantling_tone)"
                                            >
                                                {{ item.dismantling_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                v-if="item.is_returned"
                                                class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-100"
                                            >
                                                <CheckCircle2 class="size-3.5" />
                                                تم التعميد
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex items-center gap-1 rounded-full bg-orange-50 px-2.5 py-1 text-xs font-semibold text-orange-700 ring-1 ring-inset ring-orange-100"
                                            >
                                                <PackageCheck class="size-3.5" />
                                                بانتظار التعميد
                                            </span>
                                            <p v-if="item.is_returned && item.warehouse_returned_by_name" class="mt-1 text-[11px] text-gray-400">
                                                بواسطة {{ item.warehouse_returned_by_name }}
                                            </p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <Button
                                                v-if="item.can_confirm"
                                                size="sm"
                                                class="gap-1.5"
                                                :disabled="confirmForm.processing"
                                                @click.stop="openConfirmDialog(item)"
                                            >
                                                <Undo2 class="size-3.5" />
                                                تعميد
                                            </Button>
                                            <Link
                                                v-else-if="!item.is_returned"
                                                :href="`/returns/${item.id}`"
                                                class="text-xs font-semibold text-orange-600 hover:underline"
                                                @click.stop
                                            >
                                                التفاصيل
                                            </Link>
                                            <span v-else class="text-xs text-gray-400">—</span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="returns.links.length > 3"
                        class="flex flex-wrap items-center justify-center gap-1 border-t border-gray-100 p-3 dark:border-neutral-800"
                    >
                        <template v-for="(link, index) in returns.links" :key="index">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="rounded-lg px-3 py-1.5 text-sm"
                                :class="link.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-neutral-300'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="rounded-lg px-3 py-1.5 text-sm text-gray-300"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>

            <aside class="xl:sticky xl:top-4 xl:self-start">
                <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="border-b border-gray-100 px-4 py-3 dark:border-neutral-800">
                        <h2 class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-white">
                            <Users class="size-4 text-orange-600" />
                            العمال
                        </h2>
                        <p class="mt-1 text-[11px] text-gray-500">متصل · تركيب · فك · غير متصل</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2 border-b border-gray-100 p-3 dark:border-neutral-800">
                        <button
                            type="button"
                            class="rounded-xl px-2.5 py-2 text-start ring-1 transition"
                            :class="workerFilter === 'online' ? 'bg-emerald-50 ring-emerald-200' : 'bg-slate-50 ring-transparent hover:bg-slate-100'"
                            @click="workerFilter = workerFilter === 'online' ? 'all' : 'online'"
                        >
                            <p class="text-[10px] font-semibold text-emerald-700">نشط / متصل</p>
                            <p class="mt-0.5 text-lg font-bold tabular-nums text-emerald-800">{{ formatInteger(workersBoard.counts.online) }}</p>
                        </button>
                        <button
                            type="button"
                            class="rounded-xl px-2.5 py-2 text-start ring-1 transition"
                            :class="workerFilter === 'offline' ? 'bg-slate-200 ring-slate-300' : 'bg-slate-50 ring-transparent hover:bg-slate-100'"
                            @click="workerFilter = workerFilter === 'offline' ? 'all' : 'offline'"
                        >
                            <p class="text-[10px] font-semibold text-slate-600">غير متصل</p>
                            <p class="mt-0.5 text-lg font-bold tabular-nums text-slate-800">{{ formatInteger(workersBoard.counts.offline) }}</p>
                        </button>
                        <button
                            type="button"
                            class="rounded-xl px-2.5 py-2 text-start ring-1 transition"
                            :class="workerFilter === 'installation' ? 'bg-sky-50 ring-sky-200' : 'bg-slate-50 ring-transparent hover:bg-slate-100'"
                            @click="workerFilter = workerFilter === 'installation' ? 'all' : 'installation'"
                        >
                            <p class="text-[10px] font-semibold text-sky-700">ميعاد تركيب</p>
                            <p class="mt-0.5 text-lg font-bold tabular-nums text-sky-800">{{ formatInteger(workersBoard.counts.installation) }}</p>
                        </button>
                        <button
                            type="button"
                            class="rounded-xl px-2.5 py-2 text-start ring-1 transition"
                            :class="workerFilter === 'dismantling' ? 'bg-orange-50 ring-orange-200' : 'bg-slate-50 ring-transparent hover:bg-slate-100'"
                            @click="workerFilter = workerFilter === 'dismantling' ? 'all' : 'dismantling'"
                        >
                            <p class="text-[10px] font-semibold text-orange-700">ميعاد فك</p>
                            <p class="mt-0.5 text-lg font-bold tabular-nums text-orange-800">{{ formatInteger(workersBoard.counts.dismantling) }}</p>
                        </button>
                    </div>

                    <div class="max-h-[70vh] space-y-2 overflow-y-auto p-3">
                        <article
                            v-for="worker in filteredWorkers"
                            :key="worker.id"
                            class="rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2.5 dark:border-neutral-800 dark:bg-neutral-950/50"
                        >
                            <div class="flex items-start gap-2.5">
                                <span
                                    class="mt-1.5 size-2.5 shrink-0 rounded-full"
                                    :class="worker.is_online ? 'bg-emerald-500 shadow-[0_0_0_3px_rgba(16,185,129,0.2)]' : 'bg-slate-300'"
                                    :title="worker.connection_label"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ worker.name }}</p>
                                        <span
                                            class="inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset"
                                            :class="workerStatusClass(worker.status_key)"
                                        >
                                            {{ worker.status_label }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-[11px]" :class="worker.is_online ? 'text-emerald-600' : 'text-slate-400'">
                                        {{ worker.connection_label }}
                                    </p>

                                    <div v-if="worker.installation" class="mt-2 rounded-lg bg-sky-50 px-2 py-1.5 text-[11px] text-sky-800 ring-1 ring-sky-100">
                                        <p class="flex items-center gap-1 font-semibold">
                                            <Wrench class="size-3" />
                                            ميعاد تركيب
                                        </p>
                                        <p class="mt-1 truncate font-medium">{{ worker.installation.customer_name }}</p>
                                        <p class="mt-0.5 tabular-nums text-sky-700" dir="ltr">
                                            {{ worker.installation.at ? formatDateTime(worker.installation.at) : 'الموعد غير محدد' }}
                                        </p>
                                    </div>

                                    <div v-if="worker.dismantling" class="mt-1.5 rounded-lg bg-orange-50 px-2 py-1.5 text-[11px] text-orange-800 ring-1 ring-orange-100">
                                        <p class="flex items-center gap-1 font-semibold">
                                            <PackageCheck class="size-3" />
                                            ميعاد فك
                                        </p>
                                        <p class="mt-1 truncate font-medium">{{ worker.dismantling.customer_name }}</p>
                                        <p class="mt-0.5 tabular-nums text-orange-700" dir="ltr">
                                            {{ worker.dismantling.at ? formatDateTime(worker.dismantling.at) : 'الموعد غير محدد' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <p v-if="!filteredWorkers.length" class="py-8 text-center text-xs text-slate-400">
                            لا يوجد عمال في هذا التصنيف.
                        </p>
                    </div>
                </div>
            </aside>
        </div>

        <Dialog :open="confirmDialogOpen" @update:open="(open) => !open && closeConfirmDialog()">
            <DialogContent class="max-w-md sm:max-w-lg" dir="rtl">
                <DialogHeader>
                    <DialogTitle>تعميد الاسترجاع</DialogTitle>
                    <DialogDescription v-if="confirmTarget">
                        الطلب
                        <span class="font-semibold tabular-nums" dir="ltr">{{ confirmTarget.order_number }}</span>
                        —
                        {{ confirmTarget.customer_name }}
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitConfirm">
                    <div class="space-y-2">
                        <Label for="confirm-note">ملاحظة التعميد</Label>
                        <textarea
                            id="confirm-note"
                            v-model="confirmForm.note"
                            rows="4"
                            class="flex min-h-[100px] w-full rounded-xl border border-input bg-background px-3 py-2 text-sm outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring"
                            placeholder="اكتب ملاحظة التعميد..."
                            required
                        />
                        <p v-if="confirmForm.errors.note" class="text-xs text-red-600">
                            {{ confirmForm.errors.note }}
                        </p>
                    </div>

                    <DialogFooter class="gap-2 sm:justify-start">
                        <Button
                            type="submit"
                            class="h-10 gap-2 rounded-xl"
                            :disabled="confirmForm.processing || !confirmForm.note.trim()"
                        >
                            <Undo2 class="size-4" />
                            {{ confirmForm.processing ? 'جاري التعميد...' : 'تعميد' }}
                        </Button>
                        <Button type="button" variant="outline" class="h-10 rounded-xl" @click="closeConfirmDialog">
                            إلغاء
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
