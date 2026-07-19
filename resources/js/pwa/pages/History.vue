<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, CheckCircle2, History, LogOut, Package } from 'lucide-vue-next';
import { formatDate, formatDateTime } from '@/lib/formatNumber';
import WorkerBottomNav from '../components/WorkerBottomNav.vue';

interface HistoryProduct {
    id: number;
    product_name: string;
    product_image_url: string | null;
    installation_photo_url: string | null;
    pickup_photo_url: string | null;
    completed_at: string | null;
    pickup_at: string | null;
    pickup_condition: string | null;
}

interface HistoryItem {
    id: number;
    customer_name: string;
    map_url: string | null;
    installation_date: string | null;
    completed_at: string | null;
    products_count: number;
    products: HistoryProduct[];
}

interface Props {
    worker: {
        id: number;
        name: string;
    };
    currentCount: number;
    history: HistoryItem[];
}

defineProps<Props>();

function logout() {
    router.post(route('pwa.logout'));
}

function formatInstallDate(date: string | null): string {
    if (!date) return 'موعد غير محدد';
    return formatDate(date);
}
</script>

<template>
    <Head title="التركيبات السابقة" />

    <div class="relative flex min-h-dvh flex-col bg-[#f5f7fb] px-5 pb-[calc(5.5rem+env(safe-area-inset-bottom))] pt-[max(1.25rem,env(safe-area-inset-top))]">
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -left-16 top-20 h-52 w-52 rounded-full bg-slate-200/50 blur-3xl" />
            <div class="absolute -right-10 bottom-24 h-48 w-48 rounded-full bg-emerald-100/40 blur-3xl" />
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
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900/5 ring-1 ring-slate-200">
                        <History class="h-6 w-6 text-slate-700" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-slate-500">التركيبات السابقة</p>
                        <p class="text-2xl font-black tabular-nums text-slate-900">{{ history.length }}</p>
                    </div>
                </div>
            </div>

            <section class="space-y-3">
                <h2 class="px-1 text-sm font-semibold text-slate-700">ما تم إنجازه سابقاً</h2>

                <div
                    v-if="!history.length"
                    class="rounded-3xl border border-dashed border-slate-200 bg-white/80 px-5 py-10 text-center"
                >
                    <Package class="mx-auto h-8 w-8 text-slate-300" />
                    <p class="mt-3 text-sm font-medium text-slate-600">لا توجد تركيبات سابقة بعد</p>
                    <p class="mt-1 text-xs text-slate-400">بعد إتمام التركيب والاستلام ستظهر هنا</p>
                </div>

                <article
                    v-for="item in history"
                    :key="item.id"
                    class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
                >
                    <Link :href="`/worker-app/installations/${item.id}`" class="block p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-bold text-slate-900">{{ item.customer_name }}</h3>
                                <p class="mt-1 flex items-center gap-1.5 text-sm text-slate-500">
                                    <CalendarDays class="h-4 w-4 shrink-0" />
                                    {{ formatInstallDate(item.installation_date) }}
                                </p>
                                <p v-if="item.completed_at" class="mt-1 text-xs text-slate-400">
                                    اكتمل: {{ formatDateTime(item.completed_at) }}
                                </p>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100">
                                <CheckCircle2 class="h-3.5 w-3.5" />
                                مكتمل
                            </span>
                        </div>
                    </Link>

                    <div class="space-y-3 border-t border-slate-100 px-5 py-4">
                        <div
                            v-for="product in item.products"
                            :key="product.id"
                            class="rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-100"
                        >
                            <p class="mb-2 truncate text-sm font-semibold text-slate-800">{{ product.product_name }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
                                    <div class="aspect-square bg-slate-100">
                                        <img
                                            v-if="product.installation_photo_url"
                                            :src="product.installation_photo_url"
                                            alt="صورة التركيب"
                                            class="h-full w-full object-cover"
                                        />
                                        <div v-else class="flex h-full items-center justify-center text-slate-300">
                                            <Package class="h-6 w-6" />
                                        </div>
                                    </div>
                                    <p class="px-2 py-1.5 text-center text-[10px] font-medium text-slate-500">تركيب</p>
                                </div>
                                <div class="overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
                                    <div class="aspect-square bg-slate-100">
                                        <img
                                            v-if="product.pickup_photo_url"
                                            :src="product.pickup_photo_url"
                                            alt="صورة الاستلام"
                                            class="h-full w-full object-cover"
                                        />
                                        <div v-else class="flex h-full items-center justify-center text-slate-300">
                                            <Package class="h-6 w-6" />
                                        </div>
                                    </div>
                                    <p class="px-2 py-1.5 text-center text-[10px] font-medium text-slate-500">استلام</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </section>
        </main>

        <WorkerBottomNav active="history" :current-count="currentCount" />
    </div>
</template>
