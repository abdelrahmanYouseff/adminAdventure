<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { CheckCircle2, ChevronLeft, ChevronRight, Clock3, Mail, Search, ShieldAlert, SkipForward } from 'lucide-vue-next';
import { formatDateTime, formatInteger } from '@/lib/formatNumber';

interface EmailLogRow {
    id: number;
    type: string;
    type_label: string;
    status: 'sent' | 'failed' | 'skipped' | string;
    status_label: string;
    subject: string | null;
    order_id: number | null;
    order_number: string | null;
    customer_name: string | null;
    recipients: string[];
    recipients_count: number;
    error_message: string | null;
    meta: Record<string, unknown>;
    sent_at: string | null;
    created_at: string | null;
}

interface PaginatedLogs {
    data: EmailLogRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface Props {
    logs: PaginatedLogs;
    filters: {
        search: string;
        type: string;
        status: string;
    };
    stats: {
        all: number;
        sent: number;
        failed: number;
        skipped: number;
    };
}

const props = defineProps<Props>();
defineOptions({ layout: AppLayout });

const searchQuery = ref(props.filters.search || '');
const typeFilter = ref(props.filters.type || 'all');
const statusFilter = ref(props.filters.status || 'all');

watch(
    () => props.filters,
    (filters) => {
        searchQuery.value = filters.search || '';
        typeFilter.value = filters.type || 'all';
        statusFilter.value = filters.status || 'all';
    },
);

const pageNumbers = computed(() => {
    const total = props.logs.last_page;
    const current = props.logs.current_page;
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
    router.get('/email-logs', {
        search: searchQuery.value.trim() || undefined,
        type: typeFilter.value !== 'all' ? typeFilter.value : undefined,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        page: page > 1 ? page : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function setStatus(status: string) {
    statusFilter.value = status;
    applyFilters(1);
}

function statusClass(status: string): string {
    if (status === 'sent') return 'bg-emerald-50 text-emerald-700';
    if (status === 'failed') return 'bg-rose-50 text-rose-700';
    return 'bg-amber-50 text-amber-700';
}
</script>

<template>
    <Head title="Email Log" />

    <div class="flex min-w-0 flex-1 flex-col gap-6 overflow-x-hidden p-3 pb-8 sm:p-6" dir="rtl">
        <section class="rounded-[2rem] bg-slate-950 px-6 py-8 text-white shadow-xl sm:px-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-[0.18em] text-sky-200/80">MAIL AUDIT</p>
                    <h1 class="mt-2 flex items-center gap-3 text-2xl font-extrabold sm:text-3xl">
                        <Mail class="size-7" />
                        Email Log
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-300">
                        سجل الإيميلات وإشعارات واتساب (إذن التسليم) مع المستلمين وحالة الإرسال ووقت الإرسال.
                    </p>
                </div>
                <p class="text-sm text-slate-400">{{ formatInteger(logs.total) }} سجل</p>
            </div>
        </section>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <button type="button" class="rounded-3xl bg-white p-5 text-right shadow-sm ring-1 ring-slate-200" @click="setStatus('all')">
                <p class="text-xs font-semibold text-slate-400">إجمالي السجلات</p>
                <p class="mt-3 text-2xl font-black text-slate-900">{{ stats.all }}</p>
            </button>
            <button type="button" class="rounded-3xl bg-white p-5 text-right shadow-sm ring-1 ring-emerald-200" @click="setStatus('sent')">
                <p class="text-xs font-semibold text-emerald-600">تم الإرسال</p>
                <p class="mt-3 text-2xl font-black text-emerald-700">{{ stats.sent }}</p>
            </button>
            <button type="button" class="rounded-3xl bg-white p-5 text-right shadow-sm ring-1 ring-rose-200" @click="setStatus('failed')">
                <p class="text-xs font-semibold text-rose-600">فشل</p>
                <p class="mt-3 text-2xl font-black text-rose-700">{{ stats.failed }}</p>
            </button>
            <button type="button" class="rounded-3xl bg-white p-5 text-right shadow-sm ring-1 ring-amber-200" @click="setStatus('skipped')">
                <p class="text-xs font-semibold text-amber-600">تم التجاهل</p>
                <p class="mt-3 text-2xl font-black text-amber-700">{{ stats.skipped }}</p>
            </button>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <form class="w-full max-w-md" @submit.prevent="applyFilters(1)">
                <label class="flex h-11 items-center gap-2 rounded-full border border-slate-200 bg-white px-4 text-slate-400 shadow-sm">
                    <Search class="size-4 shrink-0" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="ابحث برقم الطلب أو الجوال أو الإيميل..."
                        class="w-full bg-transparent text-sm text-slate-800 outline-none placeholder:text-slate-400"
                    />
                </label>
            </form>

            <select v-model="typeFilter" class="h-11 rounded-full border border-slate-200 bg-white px-4 text-sm" @change="applyFilters(1)">
                <option value="all">كل الأنواع</option>
                <option value="installation_photos">صور التركيب</option>
                <option value="dismantling_photos">صور الفك</option>
                <option value="work_order_issued">إصدار أمر العمل</option>
                <option value="delivery_note_whatsapp">إذن تسليم واتساب</option>
                <option value="daily_operations_summary">الملخص اليومي</option>
            </select>

            <select v-model="statusFilter" class="h-11 rounded-full border border-slate-200 bg-white px-4 text-sm" @change="applyFilters(1)">
                <option value="all">كل الحالات</option>
                <option value="sent">تم الإرسال</option>
                <option value="failed">فشل</option>
                <option value="skipped">تم التجاهل</option>
            </select>
        </div>

        <div v-if="logs.data.length === 0" class="rounded-3xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-500">
            لا توجد سجلات مطابقة.
        </div>

        <div v-else class="space-y-4">
            <article
                v-for="log in logs.data"
                :key="log.id"
                class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                {{ log.type_label }}
                            </span>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(log.status)">
                                {{ log.status_label }}
                            </span>
                            <span v-if="log.order_number" class="text-xs text-slate-500" dir="ltr">
                                {{ log.order_number }}
                            </span>
                        </div>

                        <h2 class="mt-3 text-lg font-bold text-slate-900">{{ log.subject || log.type_label }}</h2>

                        <p v-if="log.customer_name" class="mt-1 text-sm text-slate-500">
                            {{ log.customer_name }}
                        </p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="recipient in log.recipients"
                                :key="recipient"
                                class="rounded-full bg-sky-50 px-3 py-1 text-xs font-medium text-sky-700"
                                dir="ltr"
                            >
                                {{ recipient }}
                            </span>
                        </div>

                        <p
                            v-if="typeof log.meta?.delivery_note_url === 'string' && log.meta.delivery_note_url"
                            class="mt-2 text-xs text-slate-500"
                            dir="ltr"
                        >
                            {{ log.meta.delivery_note_url }}
                        </p>

                        <p v-if="log.error_message" class="mt-3 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            {{ log.error_message }}
                        </p>

                        <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-slate-400">
                            <span class="inline-flex items-center gap-1.5">
                                <Clock3 class="size-3.5" />
                                {{ formatDateTime(log.sent_at || log.created_at) }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <CheckCircle2 class="size-3.5" />
                                {{ log.recipients_count }} مستلم
                            </span>
                            <Link
                                v-if="log.order_id"
                                :href="`/orders/${log.order_id}`"
                                class="font-semibold text-sky-700 hover:underline"
                            >
                                فتح الطلب
                            </Link>
                        </div>
                    </div>

                    <div class="shrink-0">
                        <div
                            class="inline-flex size-12 items-center justify-center rounded-2xl"
                            :class="log.status === 'sent'
                                ? 'bg-emerald-50 text-emerald-600'
                                : log.status === 'failed'
                                    ? 'bg-rose-50 text-rose-600'
                                    : 'bg-amber-50 text-amber-600'"
                        >
                            <CheckCircle2 v-if="log.status === 'sent'" class="size-6" />
                            <ShieldAlert v-else-if="log.status === 'failed'" class="size-6" />
                            <SkipForward v-else class="size-6" />
                        </div>
                    </div>
                </div>
            </article>
        </div>

        <div v-if="logs.last_page > 1" class="flex items-center justify-between gap-4 rounded-3xl bg-white px-5 py-4 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm text-slate-500">
                عرض {{ logs.from ?? 0 }} - {{ logs.to ?? 0 }} من {{ logs.total }}
            </p>
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 disabled:opacity-40"
                    :disabled="logs.current_page === 1"
                    @click="applyFilters(logs.current_page - 1)"
                >
                    <ChevronRight class="size-4" />
                </button>
                <template v-for="page in pageNumbers" :key="String(page)">
                    <span v-if="page === 'ellipsis'" class="px-2 text-slate-300">...</span>
                    <button
                        v-else
                        type="button"
                        class="inline-flex h-9 min-w-9 items-center justify-center rounded-full px-3 text-sm font-semibold"
                        :class="page === logs.current_page ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'"
                        @click="applyFilters(page)"
                    >
                        {{ page }}
                    </button>
                </template>
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 disabled:opacity-40"
                    :disabled="logs.current_page === logs.last_page"
                    @click="applyFilters(logs.current_page + 1)"
                >
                    <ChevronLeft class="size-4" />
                </button>
            </div>
        </div>
    </div>
</template>
