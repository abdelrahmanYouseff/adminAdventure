<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDate, formatDateTime } from '@/lib/formatNumber';
import { ChevronDown, MessageSquareText, Pencil, ShieldCheck, Check, X } from 'lucide-vue-next';
import Swal from 'sweetalert2';

interface ApprovalStep {
    key: string;
    label: string;
    completed: boolean;
    approved_at: string | null;
    approved_by_name: string | null;
    is_next: boolean;
}

interface DepositNote {
    id: number;
    body: string;
    user_name: string;
    user_role: string;
    created_at: string | null;
}

interface Deposit {
    id: number;
    order_number: string;
    invoice_number: string | null;
    customer_name: string;
    customer_phone: string | null;
    insurance_amount: number;
    insurance_original_amount: number;
    insurance_status: 'pending' | 'refunded' | 'withheld' | 'none';
    insurance_refunded_at: string | null;
    payment_status: string | null;
    activity_date: string | null;
    created_at: string | null;
    approved_at: string | null;
    approval_progress: ApprovalStep[];
    next_approval_step: string | null;
    next_approval_label: string | null;
    can_approve_next: boolean;
    is_fully_approved: boolean;
    can_refund_or_withhold: boolean;
    can_edit_amount: boolean;
    notes: DepositNote[];
    notes_count: number;
}

interface PaginatedDeposits {
    data: Deposit[];
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    deposits: PaginatedDeposits;
    stats: {
        pending: number;
        refunded: number;
        withheld: number;
        pending_amount: number;
    };
    filters: {
        status: string;
    };
}

defineProps<Props>();
defineOptions({ layout: AppLayout });

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string } | undefined) ?? {});

const editingId = reactive<{ id: number | null; value: string; saving: boolean }>({
    id: null,
    value: '',
    saving: false,
});

const expandedNotesId = ref<number | null>(null);

function toggleNotes(deposit: Deposit, event?: Event) {
    const target = event?.target as HTMLElement | undefined;
    if (target?.closest('a, button, input, textarea, select, label')) {
        return;
    }

    expandedNotesId.value = expandedNotesId.value === deposit.id ? null : deposit.id;
}

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

const statusTabs = [
    { key: 'pending', label: 'بانتظار الاسترداد' },
    { key: 'refunded', label: 'تم الاسترداد' },
    { key: 'withheld', label: 'محجوز' },
    { key: 'all', label: 'الكل' },
];

const statusMeta: Record<string, { label: string; class: string }> = {
    pending: { label: 'بانتظار الاسترداد', class: 'bg-amber-50 text-amber-700 ring-amber-200' },
    refunded: { label: 'تم الاسترداد', class: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
    withheld: { label: 'محجوز', class: 'bg-rose-50 text-rose-700 ring-rose-200' },
    none: { label: '—', class: 'bg-slate-50 text-slate-600 ring-slate-200' },
};

function setStatus(status: string) {
    router.get('/insurance-deposits', { status }, { preserveState: true, replace: true });
}

function startEditAmount(deposit: Deposit) {
    if (!deposit.can_edit_amount) {
        Swal.fire({
            icon: 'info',
            title: 'غير متاح',
            text: 'تعديل مبلغ الاسترداد متاح للمحاسب فقط عند مرحلة التعميد أو قبل الاسترداد.',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#2563EB',
        });
        return;
    }

    editingId.id = deposit.id;
    editingId.value = String(deposit.insurance_amount);
    editingId.saving = false;
}

function cancelEditAmount() {
    editingId.id = null;
    editingId.value = '';
    editingId.saving = false;
}

function saveEditAmount(deposit: Deposit) {
    const amount = Number(editingId.value);

    if (Number.isNaN(amount) || amount < 0) {
        Swal.fire({
            icon: 'error',
            title: 'مبلغ غير صالح',
            text: 'أدخل مبلغ استرداد صحيحاً (صفر أو أكبر).',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#2563EB',
        });
        return;
    }

    editingId.saving = true;
    router.patch(
        `/insurance-deposits/${deposit.id}/amount`,
        { insurance_amount: amount },
        {
            preserveScroll: true,
            onFinish: () => {
                editingId.saving = false;
                cancelEditAmount();
            },
        },
    );
}

async function approveNext(deposit: Deposit) {
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
        text: `تأكيد تعميد ${deposit.next_approval_label} للطلب ${deposit.order_number}؟`,
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

async function markRefunded(deposit: Deposit) {
    if (!deposit.can_refund_or_withhold) {
        await Swal.fire({
            icon: 'info',
            title: 'لا يمكن الاسترداد الآن',
            text: 'يجب اكتمال سلسلة التعميدات أولاً: مدير العمال ← المسئول ← المدير العام ← المحاسب.',
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

async function markWithheld(deposit: Deposit) {
    if (!deposit.can_refund_or_withhold) {
        await Swal.fire({
            icon: 'info',
            title: 'لا يمكن الحجز الآن',
            text: 'يجب اكتمال سلسلة التعميدات أولاً: مدير العمال ← المسئول ← المدير العام ← المحاسب.',
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
    <Head title="استرداد التأمين" />

    <div class="py-8 sm:py-12" dir="rtl">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">استرداد التأمين</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        يظهر السجل بعد تعميد مدير العمال، ثم المسئول ← المدير العام ← المحاسب قبل الاسترداد
                    </p>
                </div>
                <div class="inline-flex items-center gap-2 rounded-2xl bg-emerald-50 px-4 py-3 text-emerald-800 ring-1 ring-emerald-100">
                    <ShieldCheck class="h-5 w-5" />
                    <div>
                        <p class="text-xs">بانتظار الاسترداد</p>
                        <p class="text-lg font-bold tabular-nums">{{ formatCurrency(stats.pending_amount) }}</p>
                    </div>
                </div>
            </div>

            <div class="mb-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
                    <p class="text-xs text-slate-500">بانتظار الاسترداد</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-amber-600">{{ stats.pending }}</p>
                </div>
                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
                    <p class="text-xs text-slate-500">تم الاسترداد</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-600">{{ stats.refunded }}</p>
                </div>
                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
                    <p class="text-xs text-slate-500">محجوز</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-rose-600">{{ stats.withheld }}</p>
                </div>
            </div>

            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.key"
                    type="button"
                    class="rounded-full px-4 py-2 text-sm font-semibold transition"
                    :class="filters.status === tab.key
                        ? 'bg-slate-900 text-white'
                        : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'"
                    @click="setStatus(tab.key)"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50 text-slate-600">
                                <th class="px-4 py-3 text-right font-semibold">رقم الطلب</th>
                                <th class="px-4 py-3 text-right font-semibold">العميل</th>
                                <th class="px-4 py-3 text-right font-semibold">مبلغ الاسترداد</th>
                                <th class="px-4 py-3 text-right font-semibold">سلسلة التعميدات</th>
                                <th class="px-4 py-3 text-right font-semibold">الحالة</th>
                                <th class="px-4 py-3 text-right font-semibold">
                                    {{ filters.status === 'refunded' ? 'تاريخ الاسترداد' : 'إجراء' }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!deposits.data.length">
                                <td colspan="6" class="px-4 py-12 text-center text-slate-500">
                                    لا توجد مبالغ تأمين بعد تعميد مدير العمال في هذا القسم حالياً.
                                </td>
                            </tr>
                            <template v-for="deposit in deposits.data" :key="deposit.id">
                                <tr
                                    class="cursor-pointer border-t border-slate-100 hover:bg-slate-50/70"
                                    @click="toggleNotes(deposit, $event)"
                                >
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex flex-col items-start gap-0.5 text-right">
                                            <div class="flex w-full flex-wrap items-center gap-2">
                                                <Link
                                                    :href="`/insurance-deposits/${deposit.id}`"
                                                    class="font-semibold text-sky-700 hover:underline"
                                                    dir="ltr"
                                                    @click.stop
                                                >
                                                    {{ deposit.order_number }}
                                                </Link>
                                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                                                    <MessageSquareText class="h-3 w-3" />
                                                    {{ deposit.notes_count || 0 }}
                                                    <ChevronDown
                                                        class="h-3 w-3 transition"
                                                        :class="expandedNotesId === deposit.id ? 'rotate-180' : ''"
                                                    />
                                                </span>
                                            </div>
                                            <p v-if="deposit.invoice_number" class="w-full text-xs text-slate-400" dir="ltr">
                                                {{ deposit.invoice_number }}
                                            </p>
                                            <p class="w-full text-xs text-slate-400">
                                                تعميد العمال: {{ deposit.approved_at ? formatDateTime(deposit.approved_at) : '—' }}
                                            </p>
                                            <Link
                                                :href="`/insurance-deposits/${deposit.id}`"
                                                class="w-full text-xs font-medium text-sky-600 hover:underline"
                                                @click.stop
                                            >
                                                مراجعة الصور ←
                                            </Link>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex flex-col items-start gap-0.5 text-right">
                                            <p class="w-full font-medium text-slate-900">{{ deposit.customer_name }}</p>
                                            <p v-if="deposit.customer_phone" class="w-full text-xs text-slate-500" dir="ltr">
                                                {{ deposit.customer_phone }}
                                            </p>
                                            <p v-if="deposit.activity_date" class="w-full text-xs text-slate-400">
                                                فعالية: {{ formatDate(deposit.activity_date) }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3" @click.stop>
                                        <div v-if="editingId.id === deposit.id" class="flex flex-wrap items-center gap-2">
                                            <input
                                                v-model="editingId.value"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                dir="ltr"
                                                class="h-9 w-28 rounded-lg border border-slate-200 px-2 text-sm tabular-nums outline-none ring-sky-400/40 focus:border-sky-400 focus:ring-2"
                                                :disabled="editingId.saving"
                                                @keyup.enter="saveEditAmount(deposit)"
                                                @keyup.escape="cancelEditAmount"
                                            />
                                            <button
                                                type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50"
                                                :disabled="editingId.saving"
                                                title="حفظ"
                                                @click="saveEditAmount(deposit)"
                                            >
                                                <Check class="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200"
                                                :disabled="editingId.saving"
                                                title="إلغاء"
                                                @click="cancelEditAmount"
                                            >
                                                <X class="h-4 w-4" />
                                            </button>
                                        </div>
                                        <div v-else class="flex flex-wrap items-center gap-2">
                                            <span class="font-bold tabular-nums text-slate-900">
                                                {{ formatCurrency(deposit.insurance_amount) }}
                                            </span>
                                            <button
                                                v-if="deposit.can_edit_amount"
                                                type="button"
                                                class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-200"
                                                @click="startEditAmount(deposit)"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                                تعديل
                                            </button>
                                            <p
                                                v-if="deposit.insurance_original_amount !== deposit.insurance_amount"
                                                class="w-full text-[11px] text-slate-400"
                                            >
                                                الأصلي: {{ formatCurrency(deposit.insurance_original_amount) }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap gap-1.5">
                                            <span
                                                v-for="step in deposit.approval_progress"
                                                :key="step.key"
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1"
                                                :class="step.completed
                                                    ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                                    : step.is_next
                                                        ? 'bg-amber-50 text-amber-700 ring-amber-200'
                                                        : 'bg-slate-50 text-slate-500 ring-slate-200'"
                                                :title="step.approved_by_name || undefined"
                                            >
                                                {{ step.label }}
                                                <span v-if="step.completed"> ✓</span>
                                            </span>
                                        </div>
                                        <p v-if="!deposit.is_fully_approved && deposit.next_approval_label" class="mt-1.5 text-xs text-amber-600">
                                            التالي: {{ deposit.next_approval_label }}
                                        </p>
                                        <p v-else-if="deposit.is_fully_approved" class="mt-1.5 text-xs text-emerald-600">
                                            اكتملت سلسلة التعميدات
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1"
                                            :class="statusMeta[deposit.insurance_status]?.class"
                                        >
                                            {{ statusMeta[deposit.insurance_status]?.label || deposit.insurance_status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right" @click.stop>
                                        <div v-if="deposit.insurance_status === 'pending'" class="flex flex-wrap gap-2">
                                            <Button
                                                v-if="!deposit.is_fully_approved"
                                                size="sm"
                                                class="h-9 rounded-xl"
                                                :class="deposit.can_approve_next
                                                    ? 'bg-sky-600 hover:bg-sky-700'
                                                    : 'bg-slate-300 text-slate-600 hover:bg-slate-300'"
                                                @click="approveNext(deposit)"
                                            >
                                                {{ deposit.can_approve_next ? `تعميد ${deposit.next_approval_label}` : 'تعميد' }}
                                            </Button>
                                            <template v-if="deposit.is_fully_approved">
                                                <Button size="sm" class="h-9 rounded-xl bg-emerald-600 hover:bg-emerald-700" @click="markRefunded(deposit)">
                                                    استرداد
                                                </Button>
                                                <Button size="sm" variant="outline" class="h-9 rounded-xl" @click="markWithheld(deposit)">
                                                    حجز
                                                </Button>
                                            </template>
                                        </div>
                                        <p
                                            v-else-if="deposit.insurance_status === 'refunded'"
                                            class="text-sm font-medium text-slate-700"
                                        >
                                            {{ deposit.insurance_refunded_at ? formatDateTime(deposit.insurance_refunded_at) : '—' }}
                                        </p>
                                        <span v-else class="text-xs text-slate-400">—</span>
                                    </td>
                                </tr>
                                <tr v-if="expandedNotesId === deposit.id" class="border-t border-slate-100 bg-slate-50/60">
                                    <td colspan="6" class="px-4 py-4">
                                        <div class="rounded-2xl border border-slate-200 bg-white p-4 text-right shadow-sm">
                                            <div class="mb-3 flex items-center justify-between gap-2">
                                                <p class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                                                    <MessageSquareText class="h-4 w-4 text-slate-500" />
                                                    الملاحظات ({{ deposit.notes?.length || 0 }})
                                                </p>
                                                <button
                                                    type="button"
                                                    class="text-xs font-medium text-slate-500 hover:text-slate-700"
                                                    @click="expandedNotesId = null"
                                                >
                                                    إغلاق
                                                </button>
                                            </div>

                                            <div v-if="deposit.notes?.length" class="max-h-72 space-y-3 overflow-y-auto">
                                                <article
                                                    v-for="note in deposit.notes"
                                                    :key="note.id"
                                                    class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3"
                                                >
                                                    <div class="mb-1.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                                                        <span class="text-sm font-semibold text-slate-900">{{ note.user_name }}</span>
                                                        <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-500 ring-1 ring-slate-200">
                                                            {{ note.user_role }}
                                                        </span>
                                                        <span v-if="note.created_at" class="text-[11px] text-slate-400" dir="ltr">
                                                            {{ formatDateTime(note.created_at) }}
                                                        </span>
                                                    </div>
                                                    <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ note.body }}</p>
                                                </article>
                                            </div>
                                            <p v-else class="py-6 text-center text-sm text-slate-400">
                                                لا توجد ملاحظات على أمر العمل هذا.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div v-if="deposits.links?.length > 3" class="flex flex-wrap gap-2 border-t border-slate-100 px-4 py-3">
                    <Link
                        v-for="(link, index) in deposits.links"
                        :key="index"
                        :href="link.url || '#'"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium"
                        :class="link.active ? 'bg-slate-900 text-white' : 'bg-slate-50 text-slate-600'"
                        v-html="link.label"
                        :preserve-scroll="true"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
