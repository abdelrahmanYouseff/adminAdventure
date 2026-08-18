<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    ArrowRight,
    Camera,
    Check,
    FileSpreadsheet,
    HardHat,
    PackageCheck,
    Receipt,
    Route,
    Shield,
    ShieldCheck,
    ShoppingCart,
    Undo2,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { formatCurrency, formatDateTime } from '@/lib/formatNumber';
import type { Component } from 'vue';

interface JourneyStep {
    key: string;
    icon: string;
    title: string;
    description: string;
    completed: boolean;
    status: 'completed' | 'current' | 'upcoming' | 'skipped';
    at: string | null;
    actor: string | null;
    waiting: string | null;
    href: string | null;
}

interface Journey {
    id: number;
    order_number: string;
    customer_name: string;
    customer_phone: string | null;
    created_at: string | null;
    status: string;
    is_cancelled: boolean;
    current_key: string | null;
    current_title: string | null;
    waiting: string | null;
    percent: number;
    completed_steps: number;
    total_steps: number;
    is_complete: boolean;
    quotation_number: string | null;
    invoice_number: string | null;
    activity_date: string | null;
    total_amount: number;
    amount_paid: number;
    currency: string;
    hrefs: {
        order: string;
        quotation: string | null;
        work_order: string | null;
        returns: string;
        payment_receipts: string;
    };
    steps: JourneyStep[];
}

interface Props {
    journey: Journey;
}

const props = defineProps<Props>();
defineOptions({ layout: AppLayout });

const visibleSteps = computed(() =>
    props.journey.steps.filter((step) => step.status !== 'skipped'),
);

const iconMap: Record<string, Component> = {
    'file-spreadsheet': FileSpreadsheet,
    'shopping-cart': ShoppingCart,
    receipt: Receipt,
    wallet: Wallet,
    'hard-hat': HardHat,
    users: Users,
    camera: Camera,
    'shield-check': ShieldCheck,
    'undo-2': Undo2,
    'package-check': PackageCheck,
    shield: Shield,
};

function stepIcon(name: string): Component {
    return iconMap[name] ?? Route;
}
</script>

<template>
    <Head :title="`رحلة الطلب ${journey.order_number}`" />

    <div class="relative flex min-w-0 flex-1 flex-col overflow-x-hidden p-3 pb-10 sm:p-6" dir="rtl">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.16),_transparent_58%)]" />

        <div class="relative mx-auto w-full max-w-3xl space-y-6">
            <div class="flex items-center justify-between gap-3">
                <Link
                    href="/order-journey"
                    class="inline-flex h-10 items-center gap-2 rounded-full border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300"
                >
                    <ArrowRight class="size-4" />
                    كل الرحلات
                </Link>
                <Link
                    :href="journey.hrefs.order"
                    class="text-sm font-semibold text-sky-700 hover:underline dark:text-sky-400"
                >
                    فتح الطلب
                </Link>
            </div>

            <section class="overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-2xl sm:p-8">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold tracking-[0.16em] text-sky-200/80">رحلة الطلب</p>
                        <h1 class="mt-2 truncate text-2xl font-extrabold sm:text-3xl">{{ journey.customer_name }}</h1>
                        <p class="mt-1 font-mono text-sm text-slate-300" dir="ltr">{{ journey.order_number }}</p>
                        <p class="mt-3 text-sm text-slate-400">
                            {{ formatCurrency(journey.total_amount, journey.currency) }}
                            <span class="mx-2 text-slate-600">·</span>
                            مدفوع {{ formatCurrency(journey.amount_paid, journey.currency) }}
                        </p>
                    </div>

                    <div class="relative mx-auto flex size-28 items-center justify-center sm:mx-0">
                        <svg class="journey-ring size-28 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                            <circle cx="18" cy="18" r="15.2" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="3.5" />
                            <circle
                                cx="18"
                                cy="18"
                                r="15.2"
                                fill="none"
                                stroke="#38bdf8"
                                stroke-width="3.5"
                                stroke-linecap="round"
                                :stroke-dasharray="`${journey.percent * 0.955} 100`"
                            />
                        </svg>
                        <div class="absolute text-center">
                            <p class="text-xl font-black tabular-nums">{{ journey.percent }}%</p>
                            <p class="text-[10px] text-slate-400">التقدم</p>
                        </div>
                    </div>
                </div>

                <div
                    class="mt-6 rounded-2xl px-4 py-3"
                    :class="journey.is_complete
                        ? 'bg-emerald-400/15 text-emerald-100'
                        : journey.is_cancelled
                            ? 'bg-rose-400/15 text-rose-100'
                            : 'bg-amber-400/15 text-amber-50'"
                >
                    <p class="text-xs font-semibold opacity-80">
                        {{ journey.is_complete ? 'اكتملت الدورة' : journey.is_cancelled ? 'الطلب متوقف' : 'الآن ينتظر' }}
                    </p>
                    <p class="mt-1 text-sm font-bold leading-relaxed">
                        <span v-if="!journey.is_complete && !journey.is_cancelled" class="journey-waiting-dots me-1 inline-flex">
                            <span /><span /><span />
                        </span>
                        {{ journey.waiting }}
                    </p>
                </div>
            </section>

            <ol class="journey-rail relative space-y-4 ps-2">
                <li
                    v-for="(step, index) in visibleSteps"
                    :key="step.key"
                    class="journey-step relative flex gap-4"
                    :style="{ animationDelay: `${index * 90}ms` }"
                >
                    <div class="relative z-10 flex flex-col items-center">
                        <span
                            class="flex size-11 items-center justify-center rounded-2xl ring-4 ring-slate-50 dark:ring-neutral-950"
                            :class="{
                                'bg-emerald-500 text-white shadow-lg shadow-emerald-500/30': step.status === 'completed',
                                'journey-pulse bg-amber-500 text-white': step.status === 'current',
                                'bg-slate-200 text-slate-400 dark:bg-neutral-800 dark:text-neutral-500': step.status === 'upcoming',
                            }"
                        >
                            <Check v-if="step.status === 'completed'" class="size-5" />
                            <component :is="stepIcon(step.icon)" v-else class="size-5" />
                        </span>
                    </div>

                    <article
                        class="min-w-0 flex-1 rounded-3xl border p-4 shadow-sm transition sm:p-5"
                        :class="{
                            'border-emerald-100 bg-white dark:border-emerald-900/40 dark:bg-neutral-900': step.status === 'completed',
                            'border-amber-200 bg-amber-50/80 shadow-amber-100 dark:border-amber-900/50 dark:bg-amber-950/30': step.status === 'current',
                            'border-slate-200 bg-slate-50/80 dark:border-neutral-800 dark:bg-neutral-900/50': step.status === 'upcoming',
                        }"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                    الخطوة {{ index + 1 }}
                                </p>
                                <h2 class="mt-0.5 text-base font-bold text-slate-900 dark:text-white">{{ step.title }}</h2>
                            </div>
                            <span
                                class="rounded-full px-2.5 py-1 text-[11px] font-bold"
                                :class="{
                                    'bg-emerald-50 text-emerald-700': step.status === 'completed',
                                    'bg-amber-100 text-amber-800': step.status === 'current',
                                    'bg-slate-200 text-slate-500': step.status === 'upcoming',
                                }"
                            >
                                {{ step.status === 'completed' ? 'تمت' : step.status === 'current' ? 'الجارية' : 'قادمة' }}
                            </span>
                        </div>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-neutral-300">
                            {{ step.description }}
                        </p>
                        <p v-if="step.status === 'current' && step.waiting" class="mt-2 text-sm font-semibold text-amber-800 dark:text-amber-300">
                            {{ step.waiting }}
                        </p>
                        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-slate-400">
                            <span v-if="step.at">{{ formatDateTime(step.at) }}</span>
                            <span v-if="step.actor">{{ step.actor }}</span>
                            <Link
                                v-if="step.href"
                                :href="step.href"
                                class="font-semibold text-sky-700 hover:underline dark:text-sky-400"
                            >
                                فتح التفاصيل
                            </Link>
                        </div>
                    </article>
                </li>
            </ol>
        </div>
    </div>
</template>

<style scoped>
.journey-step {
    animation: journey-enter 0.55s ease-out both;
}

.journey-rail::before {
    content: '';
    position: absolute;
    top: 1.4rem;
    bottom: 1.4rem;
    right: 1.85rem;
    width: 2px;
    background: linear-gradient(to bottom, #34d399, #38bdf8 45%, #e2e8f0);
    transform-origin: top;
    animation: journey-line 1.1s ease-out both;
}

.journey-pulse {
    animation: journey-pulse 1.8s ease-out infinite;
}

.journey-ring circle:last-child {
    animation: journey-draw 1.1s ease-out both;
}

.journey-waiting-dots span {
    display: inline-block;
    width: 0.35rem;
    height: 0.35rem;
    margin-inline: 0.1rem;
    border-radius: 999px;
    background: currentColor;
    animation: journey-dot 1.1s ease-in-out infinite;
}

.journey-waiting-dots span:nth-child(2) {
    animation-delay: 0.15s;
}

.journey-waiting-dots span:nth-child(3) {
    animation-delay: 0.3s;
}

@keyframes journey-enter {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes journey-line {
    from {
        transform: scaleY(0);
    }
    to {
        transform: scaleY(1);
    }
}

@keyframes journey-pulse {
    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.55);
    }
    70% {
        box-shadow: 0 0 0 14px rgba(245, 158, 11, 0);
    }
}

@keyframes journey-draw {
    from {
        stroke-dashoffset: 100;
    }
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes journey-dot {
    0%,
    80%,
    100% {
        opacity: 0.25;
        transform: translateY(0);
    }
    40% {
        opacity: 1;
        transform: translateY(-2px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .journey-step,
    .journey-rail::before,
    .journey-pulse,
    .journey-ring circle:last-child,
    .journey-waiting-dots span {
        animation: none !important;
    }
}
</style>
