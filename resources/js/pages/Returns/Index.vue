<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatInteger } from '@/lib/formatNumber';
import { CheckCircle2, ChevronDown, MessageSquareText, PackageCheck, Search, Undo2 } from 'lucide-vue-next';
import Swal from 'sweetalert2';

interface ReturnProduct {
    id: number;
    product_name: string;
    pickup_condition: string | null;
    pickup_at: string | null;
}

interface ReturnNote {
    id: number;
    body: string;
    user_name: string;
    user_role: string;
    created_at: string | null;
}

interface ProductReturn {
    id: number;
    order_number: string;
    customer_name: string;
    customer_phone: string | null;
    products_count: number;
    products: ReturnProduct[];
    condition_summary: Record<string, number>;
    latest_pickup_at: string | null;
    warehouse_returned_at: string | null;
    warehouse_returned_by_name: string | null;
    is_returned: boolean;
    can_confirm: boolean;
    notes: ReturnNote[];
    notes_count: number;
}

interface PaginatedReturns {
    data: ProductReturn[];
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    returns: PaginatedReturns;
    stats: {
        pending: number;
        returned: number;
    };
    filters: {
        status: string;
        search: string;
    };
}

const props = defineProps<Props>();
defineOptions({ layout: AppLayout });

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string } | undefined) ?? {});
const searchQuery = ref(props.filters.search || '');
const confirmingId = ref<number | null>(null);
const expandedId = ref<number | null>(null);
const noteDrafts = reactive<Record<number, string>>({});
const savingNoteId = ref<number | null>(null);

const statusTabs = [
    { key: 'pending', label: 'بانتظار الاسترجاع' },
    { key: 'returned', label: 'تم الاسترجاع' },
    { key: 'all', label: 'الكل' },
] as const;

const conditionLabels: Record<string, string> = {
    good: 'سليم',
    damaged: 'تالف',
    broken: 'مكسور',
};

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

function toggleExpand(item: ProductReturn, event?: Event) {
    const target = event?.target as HTMLElement | undefined;
    if (target?.closest('a, button, input, textarea, select, label')) {
        return;
    }

    expandedId.value = expandedId.value === item.id ? null : item.id;
}

async function confirmReturn(item: ProductReturn) {
    const result = await Swal.fire({
        icon: 'question',
        title: 'تأكيد الاسترجاع للمستودع؟',
        text: `الطلب ${item.order_number} — ${formatInteger(item.products_count)} منتج`,
        showCancelButton: true,
        confirmButtonText: 'تأكيد الاسترجاع',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#2563EB',
    });

    if (!result.isConfirmed) return;

    confirmingId.value = item.id;
    router.post(`/returns/${item.id}/confirm`, {}, {
        preserveScroll: true,
        onFinish: () => {
            confirmingId.value = null;
        },
    });
}

function submitNote(item: ProductReturn) {
    const body = (noteDrafts[item.id] || '').trim();
    if (!body) {
        Swal.fire({
            icon: 'info',
            title: 'الملاحظة فارغة',
            text: 'اكتب ملاحظة قبل الإرسال.',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#2563EB',
        });
        return;
    }

    savingNoteId.value = item.id;
    router.post(
        `/returns/${item.id}/notes`,
        { body },
        {
            preserveScroll: true,
            onSuccess: () => {
                noteDrafts[item.id] = '';
                expandedId.value = item.id;
            },
            onFinish: () => {
                savingNoteId.value = null;
            },
        },
    );
}

function conditionText(summary: Record<string, number>): string {
    const parts = Object.entries(summary).map(([key, count]) => `${conditionLabels[key] || key}: ${count}`);
    return parts.length ? parts.join(' · ') : '—';
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
                تأكيد استرجاع المنتجات للمستودع بعد الفك والاستلام من العميل — اضغط على السجل لعرض الملاحظات
            </p>
        </div>

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
                <table class="w-full min-w-[900px] border-collapse text-right text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/80 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:border-neutral-800 dark:bg-neutral-950/50 dark:text-neutral-400">
                            <th class="whitespace-nowrap px-4 py-3 w-8"></th>
                            <th class="whitespace-nowrap px-4 py-3">الطلب</th>
                            <th class="whitespace-nowrap px-4 py-3">العميل</th>
                            <th class="whitespace-nowrap px-4 py-3">المنتجات</th>
                            <th class="whitespace-nowrap px-4 py-3">حالة الاستلام</th>
                            <th class="whitespace-nowrap px-4 py-3">آخر استلام</th>
                            <th class="whitespace-nowrap px-4 py-3">الحالة</th>
                            <th class="whitespace-nowrap px-4 py-3">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="item in returns.data" :key="item.id">
                            <tr
                                class="cursor-pointer border-b border-gray-100 align-top transition hover:bg-gray-50/80 dark:border-neutral-800 dark:hover:bg-neutral-800/40"
                                :class="expandedId === item.id ? 'bg-orange-50/40 dark:bg-orange-950/20' : ''"
                                @click="toggleExpand(item, $event)"
                            >
                                <td class="px-3 py-3 text-gray-400">
                                    <ChevronDown
                                        class="size-4 transition"
                                        :class="expandedId === item.id ? 'rotate-180 text-orange-600' : ''"
                                    />
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
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-neutral-300">{{ conditionText(item.condition_summary) }}</td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-neutral-300">
                                    {{ item.latest_pickup_at ? formatDateTime(item.latest_pickup_at) : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="item.is_returned"
                                        class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-100"
                                    >
                                        <CheckCircle2 class="size-3.5" />
                                        تم الاسترجاع
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 rounded-full bg-orange-50 px-2.5 py-1 text-xs font-semibold text-orange-700 ring-1 ring-inset ring-orange-100"
                                    >
                                        <PackageCheck class="size-3.5" />
                                        بانتظار المستودع
                                    </span>
                                    <p v-if="item.warehouse_returned_by_name" class="mt-1 text-[11px] text-gray-400">
                                        بواسطة {{ item.warehouse_returned_by_name }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <Button
                                        v-if="item.can_confirm"
                                        size="sm"
                                        class="gap-1.5"
                                        :disabled="confirmingId === item.id"
                                        @click="confirmReturn(item)"
                                    >
                                        <Undo2 class="size-3.5" />
                                        {{ confirmingId === item.id ? 'جاري التأكيد...' : 'تأكيد الاسترجاع' }}
                                    </Button>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                            </tr>

                            <tr v-if="expandedId === item.id" class="border-b border-gray-100 bg-slate-50/70 dark:border-neutral-800 dark:bg-neutral-950/40">
                                <td colspan="8" class="px-4 py-4">
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 text-right shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                                        <div class="mb-3 flex items-center justify-between gap-2">
                                            <p class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-neutral-100">
                                                <MessageSquareText class="h-4 w-4 text-slate-500" />
                                                الملاحظات ({{ formatInteger(item.notes_count || 0) }})
                                            </p>
                                            <button
                                                type="button"
                                                class="text-xs font-medium text-slate-500 hover:text-slate-700"
                                                @click="expandedId = null"
                                            >
                                                إغلاق
                                            </button>
                                        </div>

                                        <div v-if="item.notes?.length" class="mb-4 max-h-72 space-y-3 overflow-y-auto">
                                            <article
                                                v-for="note in item.notes"
                                                :key="note.id"
                                                class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-950"
                                            >
                                                <div class="mb-1.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                                                    <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ note.user_name }}</span>
                                                    <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-500 ring-1 ring-slate-200 dark:bg-neutral-900 dark:ring-neutral-700">
                                                        {{ note.user_role }}
                                                    </span>
                                                    <span v-if="note.created_at" class="text-[11px] text-slate-400" dir="ltr">
                                                        {{ formatDateTime(note.created_at) }}
                                                    </span>
                                                </div>
                                                <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-700 dark:text-neutral-300">{{ note.body }}</p>
                                            </article>
                                        </div>
                                        <p v-else class="mb-4 py-4 text-center text-sm text-slate-400">
                                            لا توجد ملاحظات على هذا الطلب بعد.
                                        </p>

                                        <div class="space-y-2 border-t border-slate-100 pt-3 dark:border-neutral-800">
                                            <label class="block text-xs font-semibold text-slate-600 dark:text-neutral-300">إضافة ملاحظة</label>
                                            <textarea
                                                v-model="noteDrafts[item.id]"
                                                rows="3"
                                                maxlength="2000"
                                                placeholder="اكتب ملاحظة عن حالة المنتجات أو أي ملاحظات للمستودع..."
                                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition focus:border-orange-300 focus:ring-2 focus:ring-orange-100 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100"
                                            />
                                            <div class="flex justify-end">
                                                <Button
                                                    size="sm"
                                                    class="gap-1.5"
                                                    :disabled="savingNoteId === item.id"
                                                    @click="submitNote(item)"
                                                >
                                                    <MessageSquareText class="size-3.5" />
                                                    {{ savingNoteId === item.id ? 'جاري الحفظ...' : 'حفظ الملاحظة' }}
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
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
</template>
