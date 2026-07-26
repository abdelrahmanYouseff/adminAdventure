<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    CheckCircle2,
    Clock3,
    ExternalLink,
    HardHat,
    LogOut,
    MapPin,
    Package,
    ShieldAlert,
} from 'lucide-vue-next';
import { formatDate, formatInteger } from '@/lib/formatNumber';
import WorkerBottomNav from '../components/WorkerBottomNav.vue';
import WorkerLanguageSwitcher from '../components/WorkerLanguageSwitcher.vue';
import { useI18n } from '../i18n';
import type { MessageKey } from '../i18n/messages';

type ListStatus = 'current' | 'awaiting_approval' | 'completed';
type FilterKey = 'all' | ListStatus;

interface LatePenalty {
    is_late: boolean;
    delay_minutes: number;
    delay_hours: number;
    delay_remainder_minutes: number;
    scheduled_at: string | null;
    installed_at: string | null;
}

interface Installation {
    id: number;
    customer_name: string;
    map_url: string | null;
    customer_phone: string | null;
    installation_date: string | null;
    activity_time: string | null;
    status: 'pending' | 'completed';
    list_status: ListStatus;
    phase: 'installation' | 'pickup' | 'awaiting' | 'done';
    is_approved: boolean;
    late_penalty: LatePenalty | null;
    products_count: number;
    pending_count: number;
    pending_pickup_count: number;
    completed_count: number;
    preview_products: string[];
}

interface Props {
    worker: {
        id: number;
        name: string;
    };
    pendingOrdersCount: number;
    counts: {
        current: number;
        awaiting_approval: number;
        completed: number;
        all: number;
    };
    installations: Installation[];
}

const props = defineProps<Props>();

const { t } = useI18n();
const pageTitle = computed(() => t('installations_table'));
const activeFilter = ref<FilterKey>('all');

const filters: { key: FilterKey; labelKey: MessageKey; count: number }[] = [
    { key: 'all', labelKey: 'filter_all', count: props.counts.all },
    { key: 'current', labelKey: 'filter_current', count: props.counts.current },
    { key: 'awaiting_approval', labelKey: 'filter_awaiting', count: props.counts.awaiting_approval },
    { key: 'completed', labelKey: 'filter_completed', count: props.counts.completed },
];

const filteredInstallations = computed(() => {
    if (activeFilter.value === 'all') {
        return props.installations;
    }

    return props.installations.filter((item) => item.list_status === activeFilter.value);
});

function logout() {
    router.post(route('pwa.logout'));
}

function formatInstallDate(date: string | null): string {
    if (!date) return t('date_unset');
    return formatDate(date);
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

function openMap(event: Event, url: string) {
    event.preventDefault();
    event.stopPropagation();
    window.open(url, '_blank', 'noopener,noreferrer');
}

function statusLabel(item: Installation): string {
    if (item.list_status === 'awaiting_approval') return t('status_awaiting');
    if (item.list_status === 'completed') return t('status_approved');
    if (item.phase === 'pickup') return t('pickup_count', { count: item.pending_pickup_count });
    return t('remaining', { pending: item.pending_count, total: item.products_count });
}

function statusClass(item: Installation): string {
    if (item.list_status === 'awaiting_approval') return 'bg-amber-50 text-amber-800 ring-amber-200';
    if (item.list_status === 'completed') return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
    if (item.phase === 'pickup') return 'bg-violet-50 text-violet-700 ring-violet-100';
    return 'bg-sky-50 text-sky-700 ring-sky-100';
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="relative flex min-h-dvh flex-col bg-[#f5f7fb] px-5 pb-[calc(5.5rem+env(safe-area-inset-bottom))] pt-[max(1.25rem,env(safe-area-inset-top))]">
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -left-20 top-16 h-56 w-56 rounded-full bg-sky-200/40 blur-3xl" />
            <div class="absolute -right-10 bottom-10 h-52 w-52 rounded-full bg-emerald-100/50 blur-3xl" />
        </div>

        <header class="relative mx-auto flex w-full max-w-md items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs text-slate-500">{{ t('hello') }}</p>
                <h1 class="truncate text-lg font-bold text-slate-900">{{ worker.name || t('worker') }}</h1>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <WorkerLanguageSwitcher />
                <button
                    type="button"
                    class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm transition active:scale-[0.98] hover:bg-slate-50"
                    @click="logout"
                >
                    <LogOut class="h-4 w-4" />
                    {{ t('logout') }}
                </button>
            </div>
        </header>

        <main class="relative mx-auto mt-6 flex w-full max-w-md flex-1 flex-col gap-4">
            <div class="grid grid-cols-3 gap-2">
                <div class="rounded-2xl border border-sky-100 bg-white p-3 shadow-sm">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                        <HardHat class="h-4 w-4" />
                    </div>
                    <p class="mt-2 text-[11px] text-slate-500">{{ t('filter_current') }}</p>
                    <p class="text-xl font-black tabular-nums text-sky-600">{{ formatInteger(counts.current) }}</p>
                </div>
                <div class="rounded-2xl border border-amber-100 bg-white p-3 shadow-sm">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <ShieldAlert class="h-4 w-4" />
                    </div>
                    <p class="mt-2 text-[11px] text-slate-500">{{ t('filter_awaiting') }}</p>
                    <p class="text-xl font-black tabular-nums text-amber-600">{{ formatInteger(counts.awaiting_approval) }}</p>
                </div>
                <div class="rounded-2xl border border-emerald-100 bg-white p-3 shadow-sm">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <CheckCircle2 class="h-4 w-4" />
                    </div>
                    <p class="mt-2 text-[11px] text-slate-500">{{ t('filter_completed') }}</p>
                    <p class="text-xl font-black tabular-nums text-emerald-600">{{ formatInteger(counts.completed) }}</p>
                </div>
            </div>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-800">{{ t('installations_table') }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500">{{ t('installations_table_hint') }}</p>
                </div>

                <div class="flex gap-1.5 overflow-x-auto px-3 py-3">
                    <button
                        v-for="filter in filters"
                        :key="filter.key"
                        type="button"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition"
                        :class="activeFilter === filter.key
                            ? 'bg-slate-900 text-white'
                            : 'bg-slate-50 text-slate-600 ring-1 ring-slate-200'"
                        @click="activeFilter = filter.key"
                    >
                        {{ t(filter.labelKey) }}
                        <span
                            class="rounded-full px-1.5 py-0.5 text-[10px] tabular-nums"
                            :class="activeFilter === filter.key ? 'bg-white/20 text-white' : 'bg-white text-slate-500'"
                        >
                            {{ formatInteger(filter.count) }}
                        </span>
                    </button>
                </div>

                <div
                    v-if="!filteredInstallations.length"
                    class="px-5 py-10 text-center"
                >
                    <Package class="mx-auto h-8 w-8 text-slate-300" />
                    <p class="mt-3 text-sm font-medium text-slate-600">{{ t('no_rows') }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ t('no_current_hint') }}</p>
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <div
                        class="hidden grid-cols-[1.4fr_1fr_auto] gap-2 bg-slate-50 px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 sm:grid"
                    >
                        <span>{{ t('col_customer') }}</span>
                        <span>{{ t('col_date') }}</span>
                        <span>{{ t('col_status') }}</span>
                    </div>

                    <Link
                        v-for="item in filteredInstallations"
                        :key="item.id"
                        :href="`/worker-app/installations/${item.id}`"
                        class="block px-4 py-3.5 transition hover:bg-sky-50/50 active:bg-sky-50"
                    >
                        <div class="grid gap-2 sm:grid-cols-[1.4fr_1fr_auto] sm:items-center sm:gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-900">{{ item.customer_name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ formatInteger(item.products_count) }} {{ t('products_short') }}
                                </p>
                            </div>

                            <div class="space-y-1 text-xs text-slate-600">
                                <p class="flex items-center gap-1.5">
                                    <CalendarDays class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                                    {{ formatInstallDate(item.installation_date) }}
                                </p>
                                <p class="flex items-center gap-1.5">
                                    <Clock3 class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                                    <span dir="ltr">{{ formatActivityTime(item.activity_time) }}</span>
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                <span
                                    v-if="item.late_penalty?.is_late"
                                    class="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-700 ring-1 ring-rose-200"
                                >
                                    {{ t('late_penalty_badge') }}
                                </span>
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1"
                                    :class="statusClass(item)"
                                >
                                    {{ statusLabel(item) }}
                                </span>
                                <button
                                    v-if="item.map_url"
                                    type="button"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-sky-600"
                                    @click="openMap($event, item.map_url!)"
                                >
                                    <MapPin class="h-3.5 w-3.5" />
                                    <ExternalLink class="h-3 w-3 opacity-70" />
                                </button>
                            </div>
                        </div>
                    </Link>
                </div>
            </section>
        </main>

        <WorkerBottomNav active="current" :current-count="pendingOrdersCount" />
    </div>
</template>
