<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppFooter from '@/components/AppFooter.vue';
import StoreHeader from '@/components/StoreHeader.vue';
import { formatAmount, formatDate } from '@/lib/formatNumber';
import { Box, Package } from 'lucide-vue-next';

interface StoreOrder {
    id: number;
    order_number: string;
    total_amount: number;
    currency: string;
    status: string;
    payment_status: string | null;
    activity_date: string | null;
    created_at: string;
    items_count: number;
    product_names: string[];
}

const props = defineProps<{
    orders: {
        data: StoreOrder[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        payment_status?: string;
    };
}>();

const isPendingFilter = computed(() => props.filters.payment_status === 'pending');

const pageTitle = computed(() =>
    isPendingFilter.value ? 'طلبات بانتظار الدفع' : 'طلباتي',
);

const statusLabels: Record<string, string> = {
    pending: 'قيد الانتظار',
    processing: 'قيد المعالجة',
    paid: 'مكتمل',
    cancelled: 'ملغي',
    refunded: 'مسترجع',
};

const paymentStatusLabels: Record<string, string> = {
    pending: 'بانتظار الدفع',
    paid: 'مدفوع',
    failed: 'فشل الدفع',
};

function statusClass(status: string): string {
    const map: Record<string, string> = {
        pending: 'bg-amber-50 text-amber-700 border-amber-200',
        processing: 'bg-blue-50 text-blue-700 border-blue-200',
        paid: 'bg-green-50 text-green-700 border-green-200',
        cancelled: 'bg-neutral-100 text-neutral-600 border-neutral-200',
        refunded: 'bg-purple-50 text-purple-700 border-purple-200',
    };

    return map[status] ?? 'bg-neutral-100 text-neutral-600 border-neutral-200';
}

function paymentStatusClass(status: string | null): string {
    const map: Record<string, string> = {
        pending: 'bg-orange-50 text-orange-700 border-orange-200',
        paid: 'bg-green-50 text-green-700 border-green-200',
        failed: 'bg-red-50 text-red-700 border-red-200',
    };

    return map[status ?? ''] ?? 'bg-neutral-100 text-neutral-600 border-neutral-200';
}
</script>

<template>
    <Head :title="`${pageTitle} — عالم المغامرة`">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div
        dir="rtl"
        class="min-h-screen bg-[#f4f6f8] pb-[env(safe-area-inset-bottom,0px)]"
        style="font-family: 'Noto Kufi Arabic', sans-serif"
    >
        <StoreHeader :show-store-link="true" :show-login-button="true" />

        <div class="mx-auto max-w-3xl px-3.5 py-8 sm:px-6 sm:py-10">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-neutral-900 sm:text-2xl">{{ pageTitle }}</h1>
                    <p class="mt-1 text-sm text-neutral-500">
                        {{ orders.total > 0 ? `${orders.total} طلب` : 'لا توجد طلبات بعد' }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <Link
                        :href="route('store.orders')"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition sm:text-sm"
                        :class="
                            !isPendingFilter
                                ? 'bg-[#3b89d2] text-white'
                                : 'border border-neutral-200 bg-white text-neutral-600 hover:bg-neutral-50'
                        "
                    >
                        كل الطلبات
                    </Link>
                    <Link
                        :href="route('store.orders', { payment_status: 'pending' })"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition sm:text-sm"
                        :class="
                            isPendingFilter
                                ? 'bg-[#3b89d2] text-white'
                                : 'border border-neutral-200 bg-white text-neutral-600 hover:bg-neutral-50'
                        "
                    >
                        بانتظار الدفع
                    </Link>
                </div>
            </div>

            <div v-if="orders.data.length === 0" class="rounded-2xl border border-neutral-200 bg-white p-10 text-center shadow-sm">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[#3b89d2]/10">
                    <Package class="h-8 w-8 text-[#3b89d2]" />
                </div>
                <h2 class="text-lg font-bold text-neutral-900">لا توجد طلبات</h2>
                <p class="mt-2 text-sm text-neutral-500">
                    {{ isPendingFilter ? 'لا توجد طلبات بانتظار الدفع حالياً.' : 'لم تقم بأي طلب بعد. تصفح الألعاب وابدأ حجزك الآن.' }}
                </p>
                <Link
                    href="/store"
                    class="mt-6 inline-flex min-h-11 items-center justify-center rounded-xl bg-[#3b89d2] px-6 text-sm font-bold text-white transition hover:bg-[#2f6eb0]"
                >
                    تصفح الألعاب
                </Link>
            </div>

            <div v-else class="space-y-4">
                <article
                    v-for="order in orders.data"
                    :key="order.id"
                    class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#b565d8]/10 text-[#b565d8]">
                                <Box class="h-5 w-5" />
                            </div>
                            <div>
                                <p class="font-bold text-neutral-900" dir="ltr">{{ order.order_number }}</p>
                                <p class="mt-0.5 text-xs text-neutral-500">
                                    {{ formatDate(order.created_at) }}
                                    <span v-if="order.activity_date"> · الفعالية: {{ formatDate(order.activity_date) }}</span>
                                </p>
                            </div>
                        </div>
                        <p class="text-lg font-extrabold text-neutral-900">
                            {{ formatAmount(order.total_amount) }}
                            <span class="text-xs font-semibold text-neutral-500">ريال</span>
                        </p>
                    </div>

                    <p v-if="order.product_names.length" class="mt-3 text-sm text-neutral-600">
                        {{ order.product_names.join(' · ') }}
                        <span v-if="order.items_count > order.product_names.length" class="text-neutral-400">
                            (+{{ order.items_count - order.product_names.length }})
                        </span>
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <span
                            class="inline-flex rounded-lg border px-2.5 py-1 text-xs font-semibold"
                            :class="statusClass(order.status)"
                        >
                            {{ statusLabels[order.status] ?? order.status }}
                        </span>
                        <span
                            v-if="order.payment_status"
                            class="inline-flex rounded-lg border px-2.5 py-1 text-xs font-semibold"
                            :class="paymentStatusClass(order.payment_status)"
                        >
                            {{ paymentStatusLabels[order.payment_status] ?? order.payment_status }}
                        </span>
                        <span class="inline-flex rounded-lg border border-neutral-200 bg-neutral-50 px-2.5 py-1 text-xs font-semibold text-neutral-600">
                            {{ order.items_count }} منتج
                        </span>
                    </div>
                </article>
            </div>

            <nav v-if="orders.last_page > 1" class="mt-6 flex justify-center gap-2">
                <Link
                    v-for="link in orders.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="inline-flex min-h-9 items-center rounded-lg px-3 text-sm font-medium transition"
                    :class="
                        link.active
                            ? 'bg-[#3b89d2] text-white'
                            : link.url
                              ? 'border border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50'
                              : 'pointer-events-none border border-neutral-100 text-neutral-300'
                    "
                    v-html="link.label"
                />
            </nav>
        </div>

        <AppFooter />
    </div>
</template>
