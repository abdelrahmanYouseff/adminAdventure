<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { CheckCircle2, Calendar, Hash, Package, User, X } from 'lucide-vue-next';
import { formatAmount } from '@/lib/formatNumber';

export interface PaymentSuccessData {
    order_number: string;
    total_amount: number;
    currency: string;
    customer_name: string;
    activity_date?: string | null;
    items: Array<{
        name: string;
        quantity: number;
        duration: number;
        amount: number;
    }>;
    paid_at: string;
}

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
    data: PaymentSuccessData | null;
}>();

const formattedDate = computed(() => {
    if (!props.data?.activity_date) return null;
    try {
        return new Intl.DateTimeFormat('ar-SA', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        }).format(new Date(props.data.activity_date));
    } catch {
        return props.data.activity_date;
    }
});

function close() {
    open.value = false;
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open && data"
                class="fixed inset-0 z-[100] flex items-end justify-center bg-black/50 p-0 backdrop-blur-sm sm:items-center sm:p-4"
                @click.self="close"
            >
                <Transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="opacity-0 translate-y-8 sm:translate-y-4 sm:scale-95"
                    enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-to-class="opacity-0 translate-y-8 sm:translate-y-4 sm:scale-95"
                >
                    <div
                        v-if="open && data"
                        dir="rtl"
                        class="relative w-full max-w-lg overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl"
                        style="font-family: 'Noto Kufi Arabic', sans-serif"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="payment-success-title"
                    >
                        <div
                            class="relative overflow-hidden px-6 pb-8 pt-10 text-center text-white sm:px-8 sm:pt-12"
                            style="background: linear-gradient(135deg, #3b89d2 0%, #2f6eb0 55%, #1f5a96 100%)"
                        >
                            <button
                                type="button"
                                class="absolute left-4 top-4 flex h-9 w-9 items-center justify-center rounded-full bg-white/15 text-white transition hover:bg-white/25"
                                aria-label="إغلاق"
                                @click="close"
                            >
                                <X class="h-5 w-5" />
                            </button>

                            <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-white/20 ring-4 ring-white/30">
                                <CheckCircle2 class="h-11 w-11 text-white" stroke-width="2.25" />
                            </div>

                            <p class="text-sm font-semibold text-white/85">تمت العملية بنجاح</p>
                            <h2 id="payment-success-title" class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">
                                شكراً لك!
                            </h2>
                            <p class="mt-2 text-sm leading-relaxed text-white/80">
                                تم تأكيد دفع طلبك وسيتم التواصل معك قريباً.
                            </p>
                        </div>

                        <div class="space-y-5 px-5 py-6 sm:px-7 sm:py-7">
                            <div class="rounded-2xl border border-[#3b89d2]/15 bg-[#f4f9fd] p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-start">
                                        <p class="text-xs font-medium text-neutral-500">المبلغ المدفوع</p>
                                        <p class="mt-0.5 text-2xl font-extrabold tabular-nums text-[#3b89d2]">
                                            {{ formatAmount(data.total_amount) }}
                                            <span class="text-sm font-bold">ريال</span>
                                        </p>
                                    </div>
                                    <div class="rounded-xl bg-white px-3 py-2 text-start shadow-sm">
                                        <p class="text-[11px] font-medium text-neutral-400">رقم الطلب</p>
                                        <p class="mt-0.5 text-sm font-bold text-neutral-800" dir="ltr">{{ data.order_number }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="flex items-start gap-3 rounded-xl border border-neutral-100 bg-neutral-50/80 p-3.5">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#3b89d2]/10 text-[#3b89d2]">
                                        <User class="h-4 w-4" />
                                    </div>
                                    <div class="min-w-0 text-start">
                                        <p class="text-xs text-neutral-500">العميل</p>
                                        <p class="mt-0.5 truncate text-sm font-bold text-neutral-900">{{ data.customer_name }}</p>
                                    </div>
                                </div>

                                <div
                                    v-if="formattedDate"
                                    class="flex items-start gap-3 rounded-xl border border-neutral-100 bg-neutral-50/80 p-3.5"
                                >
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#3b89d2]/10 text-[#3b89d2]">
                                        <Calendar class="h-4 w-4" />
                                    </div>
                                    <div class="min-w-0 text-start">
                                        <p class="text-xs text-neutral-500">تاريخ الفعالية</p>
                                        <p class="mt-0.5 text-sm font-bold text-neutral-900">{{ formattedDate }}</p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3 rounded-xl border border-neutral-100 bg-neutral-50/80 p-3.5 sm:col-span-2">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#3b89d2]/10 text-[#3b89d2]">
                                        <Hash class="h-4 w-4" />
                                    </div>
                                    <div class="min-w-0 text-start">
                                        <p class="text-xs text-neutral-500">وقت الدفع</p>
                                        <p class="mt-0.5 text-sm font-bold text-neutral-900" dir="ltr">{{ data.paid_at }}</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="data.items.length > 0" class="rounded-2xl border border-neutral-100">
                                <div class="flex items-center gap-2 border-b border-neutral-100 px-4 py-3">
                                    <Package class="h-4 w-4 text-[#3b89d2]" />
                                    <p class="text-sm font-bold text-neutral-900">تفاصيل الطلب</p>
                                </div>
                                <ul class="max-h-40 divide-y divide-neutral-100 overflow-y-auto px-4">
                                    <li
                                        v-for="(item, index) in data.items"
                                        :key="index"
                                        class="flex items-start justify-between gap-3 py-3"
                                    >
                                        <div class="min-w-0 text-start">
                                            <p class="line-clamp-2 text-sm font-semibold text-neutral-900">{{ item.name }}</p>
                                            <p class="mt-0.5 text-xs text-neutral-500">
                                                {{ item.quantity }} × {{ item.duration }} يوم
                                            </p>
                                        </div>
                                        <p class="shrink-0 text-sm font-bold tabular-nums text-[#3b89d2]">
                                            {{ formatAmount(item.amount) }} ريال
                                        </p>
                                    </li>
                                </ul>
                            </div>

                            <div class="flex flex-col gap-2.5 sm:flex-row">
                                <Link
                                    :href="route('store.orders')"
                                    class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-[#3b89d2] px-4 py-3 text-sm font-bold text-white transition hover:bg-[#2f6eb0]"
                                    @click="close"
                                >
                                    عرض طلباتي
                                </Link>
                                <button
                                    type="button"
                                    class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-neutral-200 bg-white px-4 py-3 text-sm font-bold text-neutral-700 transition hover:bg-neutral-50"
                                    @click="close"
                                >
                                    متابعة التصفح
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
