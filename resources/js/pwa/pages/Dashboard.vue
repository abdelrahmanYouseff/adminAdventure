<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, ExternalLink, HardHat, LogOut, MapPin, Package } from 'lucide-vue-next';
import { formatDate, formatInteger } from '@/lib/formatNumber';
import WorkerBottomNav from '../components/WorkerBottomNav.vue';

interface Installation {
    id: number;
    customer_name: string;
    map_url: string | null;
    customer_phone: string | null;
    installation_date: string | null;
    status: 'pending' | 'completed';
    phase: 'installation' | 'pickup';
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
    installations: Installation[];
}

defineProps<Props>();

function logout() {
    router.post(route('pwa.logout'));
}

function formatInstallDate(date: string | null): string {
    if (!date) return 'موعد غير محدد';
    return formatDate(date);
}

function openMap(event: Event, url: string) {
    event.preventDefault();
    event.stopPropagation();
    window.open(url, '_blank', 'noopener,noreferrer');
}
</script>

<template>
    <Head title="التركيبات الحالية" />

    <div class="relative flex min-h-dvh flex-col bg-[#f5f7fb] px-5 pb-[calc(5.5rem+env(safe-area-inset-bottom))] pt-[max(1.25rem,env(safe-area-inset-top))]">
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -left-20 top-16 h-56 w-56 rounded-full bg-sky-200/40 blur-3xl" />
            <div class="absolute -right-10 bottom-10 h-52 w-52 rounded-full bg-emerald-100/50 blur-3xl" />
        </div>

        <header class="relative mx-auto flex w-full max-w-md items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs text-slate-500">مرحباً</p>
                <h1 class="truncate text-lg font-bold text-slate-900">{{ worker.name || 'عامل' }}</h1>
            </div>
            <button
                type="button"
                class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm transition active:scale-[0.98] hover:bg-slate-50"
                @click="logout"
            >
                <LogOut class="h-4 w-4" />
                خروج
            </button>
        </header>

        <main class="relative mx-auto mt-6 flex w-full max-w-md flex-1 flex-col gap-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-50 ring-1 ring-sky-100">
                        <HardHat class="h-6 w-6 text-sky-600" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-slate-500">التركيبات الحالية</p>
                        <p class="text-2xl font-black tabular-nums text-sky-600">
                            {{ formatInteger(pendingOrdersCount) }}
                        </p>
                    </div>
                </div>
            </div>

            <section class="space-y-3">
                <h2 class="px-1 text-sm font-semibold text-slate-700">مفروض تركّبها الآن</h2>

                <div
                    v-if="!installations.length"
                    class="rounded-3xl border border-dashed border-slate-200 bg-white/80 px-5 py-10 text-center"
                >
                    <Package class="mx-auto h-8 w-8 text-slate-300" />
                    <p class="mt-3 text-sm font-medium text-slate-600">لا توجد تركيبات حالية</p>
                    <p class="mt-1 text-xs text-slate-400">ستظهر هنا الأوامر بعد تعيينك من مدير العمال</p>
                </div>

                <Link
                    v-for="item in installations"
                    :key="item.id"
                    :href="`/worker-app/installations/${item.id}`"
                    class="block rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition active:scale-[0.99] hover:border-sky-200 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="min-w-0 truncate text-base font-bold text-slate-900">{{ item.customer_name }}</h3>
                        <span
                            class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1"
                            :class="item.phase === 'pickup'
                                ? 'bg-violet-50 text-violet-700 ring-violet-100'
                                : 'bg-amber-50 text-amber-700 ring-amber-100'"
                        >
                            <template v-if="item.phase === 'pickup'">
                                {{ item.pending_pickup_count }} استلام
                            </template>
                            <template v-else>
                                {{ item.pending_count }}/{{ item.products_count }} متبقي
                            </template>
                        </span>
                    </div>

                    <div class="mt-4 space-y-2 text-sm text-slate-600">
                        <p class="flex items-center gap-2">
                            <CalendarDays class="h-4 w-4 shrink-0 text-slate-400" />
                            {{ formatInstallDate(item.installation_date) }}
                        </p>
                        <button
                            v-if="item.map_url"
                            type="button"
                            class="inline-flex items-center gap-2 font-semibold text-sky-600 transition hover:text-sky-700"
                            @click="openMap($event, item.map_url!)"
                        >
                            <MapPin class="h-4 w-4 shrink-0" />
                            الموقع علي الخريطة
                            <ExternalLink class="h-3.5 w-3.5 shrink-0 opacity-70" />
                        </button>
                    </div>

                    <div v-if="item.preview_products.length" class="mt-4 flex flex-wrap gap-1.5">
                        <span
                            v-for="(product, index) in item.preview_products"
                            :key="`${item.id}-${index}`"
                            class="rounded-lg bg-slate-50 px-2 py-1 text-[11px] font-medium text-slate-600 ring-1 ring-slate-100"
                        >
                            {{ product }}
                        </span>
                        <span
                            v-if="item.products_count > item.preview_products.length"
                            class="rounded-lg bg-slate-50 px-2 py-1 text-[11px] font-medium text-slate-500 ring-1 ring-slate-100"
                        >
                            +{{ item.products_count - item.preview_products.length }}
                        </span>
                    </div>
                </Link>
            </section>
        </main>

        <WorkerBottomNav active="current" :current-count="pendingOrdersCount" />
    </div>
</template>
