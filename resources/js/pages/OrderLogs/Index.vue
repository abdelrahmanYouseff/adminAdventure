<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ChevronLeft, ChevronRight, ClipboardList, Search } from 'lucide-vue-next';
import { formatDateTime, formatInteger } from '@/lib/formatNumber';

interface Actor {
    id: number | null;
    name: string;
    role: string | null;
    role_label: string | null;
}

interface UpdateRow {
    id: number;
    user: Actor;
    created_at: string | null;
}

interface OrderLogRow {
    id: number;
    order_number: string | null;
    customer_name: string | null;
    created_by: Actor;
    created_at: string | null;
    updated_by: Actor | null;
    updated_at: string | null;
    updates: UpdateRow[];
}

interface PaginatedOrders {
    data: OrderLogRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface Props {
    orders: PaginatedOrders;
    filters: {
        search: string;
        filter: string;
    };
    stats: {
        all: number;
        edited: number;
        events: number;
    };
}

const props = defineProps<Props>();
defineOptions({ layout: AppLayout });

const searchQuery = ref(props.filters.search || '');
const filter = ref(props.filters.filter || 'all');

watch(
    () => props.filters,
    (filters) => {
        searchQuery.value = filters.search || '';
        filter.value = filters.filter || 'all';
    },
);

const pageNumbers = computed(() => {
    const total = props.orders.last_page;
    const current = props.orders.current_page;
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);

    const pages: Array<number | 'ellipsis'> = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);
    if (start > 2) pages.push('ellipsis');
    for (let i = start; i <= end; i += 1) pages.push(i);
    if (end < total - 1) pages.push('ellipsis');
    pages.push(total);
    return pages;
});

function applyFilters(page = 1) {
    router.get('/order-logs', {
        search: searchQuery.value.trim() || undefined,
        filter: filter.value !== 'all' ? filter.value : undefined,
        page: page > 1 ? page : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function setFilter(value: string) {
    filter.value = value;
    applyFilters(1);
}

function actorLabel(actor: Actor): string {
    return actor.role_label ? `${actor.name} (${actor.role_label})` : actor.name;
}
</script>

<template>
    <Head title="Order Logs" />

    <div class="flex min-w-0 flex-1 flex-col gap-6 overflow-x-hidden p-3 pb-8 sm:p-6" dir="rtl">
        <section class="rounded-[2rem] bg-slate-950 px-6 py-8 text-white shadow-xl sm:px-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-[0.18em] text-sky-200/80">ORDER AUDIT</p>
                    <h1 class="mt-2 flex items-center gap-3 text-2xl font-extrabold sm:text-3xl">
                        <ClipboardList class="size-7" />
                        Order Logs
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-300">
                        كل الطلبات الحالية. المنشئ والمعدّل يظهران للاستخدام من بعد تفعيل السجل، والطلبات الأقدم تظهر بدون اسم المستخدم لأنه لم يكن يُسجَّل سابقاً.
                    </p>
                </div>
                <p class="text-sm text-slate-400">{{ formatInteger(orders.total) }} طلب</p>
            </div>
        </section>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <button type="button" class="rounded-3xl bg-white p-5 text-right shadow-sm ring-1 ring-slate-200" @click="setFilter('all')">
                <p class="text-xs font-semibold text-slate-400">كل الطلبات</p>
                <p class="mt-3 text-2xl font-black text-slate-900">{{ stats.all }}</p>
            </button>
            <button type="button" class="rounded-3xl bg-white p-5 text-right shadow-sm ring-1 ring-amber-200" @click="setFilter('edited')">
                <p class="text-xs font-semibold text-amber-600">تم تعديلها</p>
                <p class="mt-3 text-2xl font-black text-amber-700">{{ stats.edited }}</p>
            </button>
            <div class="rounded-3xl bg-white p-5 text-right shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-semibold text-slate-400">إجمالي الأحداث</p>
                <p class="mt-3 text-2xl font-black text-slate-900">{{ stats.events }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
            <div class="border-b border-gray-100 p-4">
                <form class="w-full max-w-md" @submit.prevent="applyFilters(1)">
                    <label class="flex h-10 items-center gap-2 rounded-full border border-transparent bg-gray-100 px-3.5 text-gray-400 transition focus-within:border-blue-300 focus-within:bg-white focus-within:ring-2 focus-within:ring-blue-100">
                        <Search class="size-4 shrink-0" />
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="ابحث برقم الطلب أو العميل أو اسم المستخدم..."
                            class="w-full bg-transparent text-sm text-gray-800 outline-none placeholder:text-gray-400"
                        />
                    </label>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/80">
                            <th class="px-4 py-3.5 text-start text-[13px] font-semibold text-gray-700">رقم الطلب</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700">العميل</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700">أنشأه</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700">تاريخ الإنشاء</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700">آخر تعديل بواسطة</th>
                            <th class="px-3 py-3.5 text-start text-[13px] font-semibold text-gray-700">تاريخ التعديل</th>
                            <th class="px-4 py-3.5 text-end text-[13px] font-semibold text-gray-700">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="orders.data.length === 0">
                            <td colspan="7" class="px-4 py-16 text-center text-gray-500">
                                لا توجد طلبات.
                            </td>
                        </tr>
                        <tr
                            v-for="order in orders.data"
                            :key="order.id"
                            class="border-b border-gray-100 transition hover:bg-gray-50/70"
                        >
                            <td class="px-4 py-3.5">
                                <span class="font-semibold tabular-nums text-gray-900" dir="ltr">
                                    {{ order.order_number || '—' }}
                                </span>
                            </td>
                            <td class="px-3 py-3.5 font-medium text-gray-900">
                                {{ order.customer_name || 'بدون اسم عميل' }}
                            </td>
                            <td class="px-3 py-3.5 text-gray-700">
                                {{ actorLabel(order.created_by) }}
                            </td>
                            <td class="px-3 py-3.5 tabular-nums text-gray-600" dir="ltr">
                                {{ order.created_at ? formatDateTime(order.created_at) : '—' }}
                            </td>
                            <td class="px-3 py-3.5 text-gray-700">
                                <template v-if="order.updated_by">
                                    <p>{{ actorLabel(order.updated_by) }}</p>
                                    <p v-if="order.updates.length > 1" class="mt-0.5 text-xs text-amber-700">
                                        {{ order.updates.length }} تعديلات
                                    </p>
                                </template>
                                <span v-else class="text-gray-400">لا يوجد تعديل</span>
                            </td>
                            <td class="px-3 py-3.5 tabular-nums text-gray-600" dir="ltr">
                                {{ order.updated_at ? formatDateTime(order.updated_at) : '—' }}
                            </td>
                            <td class="px-4 py-3.5 text-end">
                                <Link
                                    :href="`/orders/${order.id}`"
                                    class="inline-flex h-8 items-center rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium text-gray-700 transition hover:bg-gray-50"
                                >
                                    فتح الطلب
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="orders.last_page > 1" class="flex items-center justify-between gap-4 rounded-3xl bg-white px-5 py-4 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm text-slate-500">
                عرض {{ orders.from ?? 0 }} - {{ orders.to ?? 0 }} من {{ orders.total }}
            </p>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 disabled:opacity-40"
                    :disabled="orders.current_page === 1"
                    @click="applyFilters(orders.current_page - 1)"
                >
                    <ChevronRight class="size-4" />
                </button>
                <template v-for="page in pageNumbers" :key="String(page)">
                    <span v-if="page === 'ellipsis'" class="px-2 text-slate-300">...</span>
                    <button
                        v-else
                        type="button"
                        class="inline-flex h-9 min-w-9 items-center justify-center rounded-full px-3 text-sm font-semibold"
                        :class="page === orders.current_page ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'"
                        @click="applyFilters(page)"
                    >
                        {{ page }}
                    </button>
                </template>
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 disabled:opacity-40"
                    :disabled="orders.current_page === orders.last_page"
                    @click="applyFilters(orders.current_page + 1)"
                >
                    <ChevronLeft class="size-4" />
                </button>
            </div>
        </div>
    </div>
</template>
