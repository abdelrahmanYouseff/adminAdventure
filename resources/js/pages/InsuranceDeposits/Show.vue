<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDate, formatDateTime } from '@/lib/formatNumber';
import { ArrowRight, Camera, ImageIcon, ShieldCheck, X } from 'lucide-vue-next';
import Swal from 'sweetalert2';

interface ApprovalStep {
    key: string;
    label: string;
    completed: boolean;
    approved_at: string | null;
    approved_by_name: string | null;
    is_next: boolean;
}

interface WorkerLine {
    id: number;
    product_name: string;
    product_image_url: string | null;
    status: string;
    installation_photo_url: string | null;
    completed_at: string | null;
    completed_by_name: string | null;
    pickup_photo_url: string | null;
    pickup_at: string | null;
    pickup_by_name: string | null;
    pickup_condition: string | null;
}

interface Deposit {
    id: number;
    order_number: string;
    invoice_number: string | null;
    customer_name: string;
    customer_phone: string | null;
    address?: string | null;
    insurance_amount: number;
    insurance_status: 'pending' | 'refunded' | 'withheld' | 'none';
    insurance_refunded_at: string | null;
    activity_date: string | null;
    approved_at: string | null;
    approval_progress: ApprovalStep[];
    next_approval_step: string | null;
    next_approval_label: string | null;
    can_approve_next: boolean;
    is_fully_approved: boolean;
    can_refund_or_withhold: boolean;
    worker_lines: WorkerLine[];
}

interface Props {
    deposit: Deposit;
}

const props = defineProps<Props>();
defineOptions({ layout: AppLayout });

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string } | undefined) ?? {});
const lightboxUrl = ref<string | null>(null);
const lightboxLabel = ref('');

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

const statusMeta: Record<string, { label: string; class: string }> = {
    pending: { label: 'بانتظار الاسترداد', class: 'bg-amber-50 text-amber-700 ring-amber-200' },
    refunded: { label: 'تم الاسترداد', class: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
    withheld: { label: 'محجوز', class: 'bg-rose-50 text-rose-700 ring-rose-200' },
    none: { label: '—', class: 'bg-slate-50 text-slate-600 ring-slate-200' },
};

const conditionLabels: Record<string, string> = {
    excellent: 'ممتاز',
    good: 'جيد',
    damaged: 'تالف جزئياً',
    broken: 'مكسور',
};

function openLightbox(url: string, label: string) {
    lightboxUrl.value = url;
    lightboxLabel.value = label;
}

function closeLightbox() {
    lightboxUrl.value = null;
    lightboxLabel.value = '';
}

async function approveNext() {
    const deposit = props.deposit;

    if (!deposit.can_approve_next) {
        const waiting = deposit.next_approval_label
            ? `بانتظار تعميد ${deposit.next_approval_label}. يجب أن تتم التعميدات بالترتيب: مدير العمال ← المسئول ← المدير العام ← المحاسب.`
            : 'اكتملت سلسلة التعميدات مسبقاً.';

        await Swal.fire({
            icon: 'info',
            title: 'لا يمكن التعميد الآن',
            text: waiting,
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#2563EB',
        });
        return;
    }

    const result = await Swal.fire({
        icon: 'question',
        title: `تعميد ${deposit.next_approval_label}`,
        text: `بعد مراجعة الصور، تأكيد تعميد ${deposit.next_approval_label} للطلب ${deposit.order_number}؟`,
        showCancelButton: true,
        confirmButtonText: 'تعميد',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#059669',
        cancelButtonColor: '#64748B',
        reverseButtons: true,
    });

    if (result.isConfirmed) {
        router.post(`/insurance-deposits/${deposit.id}/approve`, {}, { preserveScroll: true });
    }
}

async function markRefunded() {
    const deposit = props.deposit;

    if (!deposit.can_refund_or_withhold) {
        await Swal.fire({
            icon: 'info',
            title: 'لا يمكن الاسترداد الآن',
            text: 'يجب اكتمال سلسلة التعميدات أولاً.',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#2563EB',
        });
        return;
    }

    const result = await Swal.fire({
        icon: 'question',
        title: 'تأكيد استرداد التأمين',
        text: `استرداد ${formatCurrency(deposit.insurance_amount)} للطلب ${deposit.order_number}؟`,
        showCancelButton: true,
        confirmButtonText: 'نعم، استرداد',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#059669',
        cancelButtonColor: '#64748B',
        reverseButtons: true,
    });

    if (result.isConfirmed) {
        router.post(`/insurance-deposits/${deposit.id}/refund`, {}, { preserveScroll: true });
    }
}

async function markWithheld() {
    const deposit = props.deposit;

    if (!deposit.can_refund_or_withhold) {
        await Swal.fire({
            icon: 'info',
            title: 'لا يمكن الحجز الآن',
            text: 'يجب اكتمال سلسلة التعميدات أولاً.',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#2563EB',
        });
        return;
    }

    const result = await Swal.fire({
        icon: 'warning',
        title: 'حجز مبلغ التأمين',
        text: `حجز التأمين للطلب ${deposit.order_number} بدون استرداد؟`,
        showCancelButton: true,
        confirmButtonText: 'تأكيد الحجز',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#64748B',
        reverseButtons: true,
    });

    if (result.isConfirmed) {
        router.post(`/insurance-deposits/${deposit.id}/withhold`, {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="`مراجعة التأمين · ${deposit.order_number}`" />

    <div class="py-8 sm:py-12" dir="rtl">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link
                        href="/insurance-deposits"
                        class="mb-2 inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-800"
                    >
                        <ArrowRight class="h-4 w-4" />
                        العودة لاسترداد التأمين
                    </Link>
                    <h1 class="text-2xl font-bold text-slate-900">مراجعة التركيب والاستلام</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        راجع صور المنتجات قبل التعميد
                    </p>
                </div>
                <span
                    class="inline-flex rounded-full px-3 py-1.5 text-xs font-semibold ring-1"
                    :class="statusMeta[deposit.insurance_status]?.class"
                >
                    {{ statusMeta[deposit.insurance_status]?.label || deposit.insurance_status }}
                </span>
            </div>

            <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-xs text-slate-400">رقم الطلب</p>
                        <p class="mt-1 font-semibold tabular-nums text-slate-900" dir="ltr">{{ deposit.order_number }}</p>
                        <p v-if="deposit.invoice_number" class="text-xs text-slate-400" dir="ltr">{{ deposit.invoice_number }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">العميل</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ deposit.customer_name }}</p>
                        <p v-if="deposit.customer_phone" class="text-xs text-slate-500" dir="ltr">{{ deposit.customer_phone }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">مبلغ التأمين</p>
                        <p class="mt-1 text-lg font-bold tabular-nums text-slate-900">{{ formatCurrency(deposit.insurance_amount) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">تاريخ الفعالية</p>
                        <p class="mt-1 font-medium text-slate-800">
                            {{ deposit.activity_date ? formatDate(deposit.activity_date) : '—' }}
                        </p>
                        <p v-if="deposit.address" class="mt-1 text-xs text-slate-500">{{ deposit.address }}</p>
                    </div>
                </div>

                <div class="mt-5 border-t border-slate-100 pt-4">
                    <p class="mb-2 text-xs font-semibold text-slate-500">سلسلة التعميدات</p>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="step in deposit.approval_progress"
                            :key="step.key"
                            class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1"
                            :class="step.completed
                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                : step.is_next
                                    ? 'bg-amber-50 text-amber-700 ring-amber-200'
                                    : 'bg-slate-50 text-slate-500 ring-slate-200'"
                        >
                            {{ step.label }}
                            <span v-if="step.completed"> ✓</span>
                        </span>
                    </div>
                </div>

                <div v-if="deposit.insurance_status === 'pending'" class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                    <Button
                        v-if="!deposit.is_fully_approved"
                        class="h-10 rounded-xl"
                        :class="deposit.can_approve_next ? 'bg-sky-600 hover:bg-sky-700' : 'bg-slate-300 text-slate-600 hover:bg-slate-300'"
                        @click="approveNext"
                    >
                        <ShieldCheck class="ms-1.5 h-4 w-4" />
                        {{ deposit.can_approve_next ? `تعميد ${deposit.next_approval_label}` : 'تعميد' }}
                    </Button>
                    <template v-if="deposit.is_fully_approved">
                        <Button class="h-10 rounded-xl bg-emerald-600 hover:bg-emerald-700" @click="markRefunded">
                            استرداد التأمين
                        </Button>
                        <Button variant="outline" class="h-10 rounded-xl" @click="markWithheld">
                            حجز التأمين
                        </Button>
                    </template>
                </div>
            </div>

            <div class="mb-3 flex items-center gap-2">
                <Camera class="h-5 w-5 text-slate-500" />
                <h2 class="text-lg font-bold text-slate-900">المنتجات والصور</h2>
                <span class="text-sm text-slate-400">({{ deposit.worker_lines.length }})</span>
            </div>

            <div v-if="!deposit.worker_lines.length" class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-12 text-center text-sm text-slate-500">
                لا توجد منتجات مرتبطة بأمر العمل لهذا الطلب.
            </div>

            <div v-else class="space-y-4">
                <article
                    v-for="line in deposit.worker_lines"
                    :key="line.id"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 sm:px-5">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100">
                            <img
                                v-if="line.product_image_url"
                                :src="line.product_image_url"
                                :alt="line.product_name"
                                class="h-full w-full object-cover"
                            />
                            <ImageIcon v-else class="h-5 w-5 text-slate-400" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-semibold text-slate-900">{{ line.product_name }}</h3>
                            <p v-if="line.pickup_condition" class="text-xs text-slate-500">
                                حالة الاستلام: {{ conditionLabels[line.pickup_condition] || line.pickup_condition }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-5">
                        <div>
                            <p class="mb-2 text-xs font-semibold text-slate-500">صورة التركيب</p>
                            <button
                                v-if="line.installation_photo_url"
                                type="button"
                                class="group relative block w-full overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200"
                                @click="openLightbox(line.installation_photo_url!, `تركيب · ${line.product_name}`)"
                            >
                                <img
                                    :src="line.installation_photo_url"
                                    :alt="`تركيب ${line.product_name}`"
                                    class="aspect-[4/3] w-full object-cover transition group-hover:scale-[1.02]"
                                />
                            </button>
                            <div
                                v-else
                                class="flex aspect-[4/3] items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 text-sm text-slate-400"
                            >
                                لا توجد صورة
                            </div>
                            <p v-if="line.completed_at" class="mt-2 text-xs text-slate-400">
                                {{ formatDateTime(line.completed_at) }}
                                <span v-if="line.completed_by_name"> · {{ line.completed_by_name }}</span>
                            </p>
                        </div>

                        <div>
                            <p class="mb-2 text-xs font-semibold text-slate-500">صورة الاستلام والفك</p>
                            <button
                                v-if="line.pickup_photo_url"
                                type="button"
                                class="group relative block w-full overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200"
                                @click="openLightbox(line.pickup_photo_url!, `استلام · ${line.product_name}`)"
                            >
                                <img
                                    :src="line.pickup_photo_url"
                                    :alt="`استلام ${line.product_name}`"
                                    class="aspect-[4/3] w-full object-cover transition group-hover:scale-[1.02]"
                                />
                            </button>
                            <div
                                v-else
                                class="flex aspect-[4/3] items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 text-sm text-slate-400"
                            >
                                لا توجد صورة
                            </div>
                            <p v-if="line.pickup_at" class="mt-2 text-xs text-slate-400">
                                {{ formatDateTime(line.pickup_at) }}
                                <span v-if="line.pickup_by_name"> · {{ line.pickup_by_name }}</span>
                            </p>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="lightboxUrl"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            @click.self="closeLightbox"
        >
            <div class="relative max-h-[90vh] w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <button
                    type="button"
                    class="absolute left-3 top-3 z-10 rounded-full bg-white/90 p-2 text-slate-600 shadow hover:bg-white"
                    @click="closeLightbox"
                >
                    <X class="h-5 w-5" />
                </button>
                <img :src="lightboxUrl" :alt="lightboxLabel" class="max-h-[75vh] w-full object-contain" />
                <p class="border-t border-slate-100 px-4 py-3 text-center text-sm font-medium text-slate-700">
                    {{ lightboxLabel }}
                </p>
            </div>
        </div>
    </Teleport>
</template>
