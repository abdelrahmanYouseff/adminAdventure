<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatDateTime, formatInteger } from '@/lib/formatNumber';
import {
    ArrowRight,
    CalendarClock,
    CheckCircle2,
    MessageSquareText,
    PackageCheck,
    Phone,
    Plus,
    Undo2,
    Users,
    X,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';

interface ReturnProduct {
    id: number;
    product_name: string;
    product_image_url?: string | null;
    status?: string | null;
    installation_photo_url?: string | null;
    pickup_photo_url?: string | null;
    pickup_at?: string | null;
    pickup_condition?: string | null;
    completed_at?: string | null;
}

interface ReturnNote {
    id: number;
    body: string;
    user_name: string;
    user_role: string;
    created_at: string | null;
}

interface Assembler {
    id: number;
    worker_name: string;
    user_id: number | null;
    task_type: string;
    created_at: string | null;
}

interface AvailableWorker {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
}

interface ReturnOrder {
    id: number;
    order_number: string;
    customer_name: string;
    customer_phone: string | null;
    address?: string | null;
    activity_date?: string | null;
    activity_time?: string | null;
    products_count: number;
    products: ReturnProduct[];
    dismantling_at: string | null;
    days_until_dismantling: number | null;
    dismantling_label: string;
    dismantling_tone: 'ok' | 'warn' | 'due' | 'overdue' | 'muted';
    warehouse_returned_at: string | null;
    warehouse_returned_by_name: string | null;
    is_returned: boolean;
    can_confirm: boolean;
    notes: ReturnNote[];
    notes_count: number;
    assemblers: Assembler[];
    assigned_workers: string[];
}

interface Props {
    returnOrder: ReturnOrder;
    availableWorkers: AvailableWorker[];
    canAssignWorkers: boolean;
    canConfirm: boolean;
}

const props = defineProps<Props>();
defineOptions({ layout: AppLayout });

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string } | undefined) ?? {});

const confirming = ref(false);
const assemblerFormOpen = ref(false);
const deletingAssemblerId = ref<number | null>(null);
const assemblerForm = useForm({ user_id: '' as string | number });
const noteForm = useForm({ body: '' });

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

const assemblers = computed(() => props.returnOrder.assemblers ?? []);

const selectableWorkers = computed(() => {
    const assignedIds = new Set(
        assemblers.value.map((a) => a.user_id).filter((id): id is number => typeof id === 'number'),
    );
    const assignedNames = new Set(assemblers.value.map((a) => a.worker_name));

    return props.availableWorkers.filter(
        (worker) => !assignedIds.has(worker.id) && !assignedNames.has(worker.name),
    );
});

function dismantlingToneClass(tone: ReturnOrder['dismantling_tone']): string {
    if (tone === 'overdue') return 'bg-rose-50 text-rose-700 ring-rose-100';
    if (tone === 'due') return 'bg-amber-50 text-amber-800 ring-amber-100';
    if (tone === 'warn') return 'bg-orange-50 text-orange-700 ring-orange-100';
    if (tone === 'ok') return 'bg-sky-50 text-sky-700 ring-sky-100';
    return 'bg-slate-50 text-slate-500 ring-slate-100';
}

async function confirmReturn() {
    const result = await Swal.fire({
        icon: 'question',
        title: 'تأكيد الاسترجاع للمستودع؟',
        text: `الطلب ${props.returnOrder.order_number} — ${formatInteger(props.returnOrder.products_count)} منتج`,
        showCancelButton: true,
        confirmButtonText: 'تأكيد الاسترجاع',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#2563EB',
    });

    if (!result.isConfirmed) return;

    confirming.value = true;
    router.post(`/returns/${props.returnOrder.id}/confirm`, {}, {
        preserveScroll: true,
        onFinish: () => {
            confirming.value = false;
        },
    });
}

function openAssemblerForm() {
    assemblerForm.reset();
    assemblerForm.clearErrors();
    assemblerFormOpen.value = true;
}

function closeAssemblerForm() {
    assemblerFormOpen.value = false;
    assemblerForm.reset();
    assemblerForm.clearErrors();
}

function submitAssembler() {
    assemblerForm.post(`/returns/${props.returnOrder.id}/assemblers`, {
        preserveScroll: true,
        onSuccess: () => closeAssemblerForm(),
    });
}

function deleteAssembler(assembler: Assembler) {
    if (!confirm(`حذف العامل «${assembler.worker_name}»؟`)) return;
    deletingAssemblerId.value = assembler.id;
    router.delete(`/returns/${props.returnOrder.id}/assemblers/${assembler.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingAssemblerId.value = null;
        },
    });
}

function submitNote() {
    const body = noteForm.body.trim();
    if (!body) {
        noteForm.setError('body', 'يجب كتابة الملاحظة.');
        return;
    }

    noteForm.body = body;
    noteForm.post(`/returns/${props.returnOrder.id}/notes`, {
        preserveScroll: true,
        onSuccess: () => noteForm.reset(),
    });
}
</script>

<template>
    <Head :title="`استرجاع ${returnOrder.order_number}`" />

    <div class="flex min-w-0 flex-1 flex-col gap-5 overflow-x-hidden p-3 sm:gap-6 sm:p-6" dir="rtl">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <Link
                    href="/returns"
                    class="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800"
                >
                    <ArrowRight class="size-4" />
                    العودة للاسترجاع
                </Link>
                <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-white sm:text-2xl">
                    <Undo2 class="size-6 text-orange-600" />
                    تفاصيل الاسترجاع
                </h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ returnOrder.order_number }} — {{ returnOrder.customer_name }}
                </p>
            </div>

            <Button
                v-if="canConfirm && returnOrder.can_confirm"
                class="gap-1.5"
                :disabled="confirming"
                @click="confirmReturn"
            >
                <Undo2 class="size-3.5" />
                {{ confirming ? 'جاري التأكيد...' : 'تأكيد الاسترجاع' }}
            </Button>
        </div>

        <section class="grid gap-4 lg:grid-cols-3">
            <article class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900 lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">العميل</p>
                        <h2 class="mt-1 text-lg font-bold text-gray-900 dark:text-white">{{ returnOrder.customer_name }}</h2>
                        <p v-if="returnOrder.customer_phone" class="mt-1 inline-flex items-center gap-1.5 text-sm text-gray-600" dir="ltr">
                            <Phone class="size-3.5 text-gray-400" />
                            {{ returnOrder.customer_phone }}
                        </p>
                        <p v-if="returnOrder.address" class="mt-2 text-sm text-gray-500">{{ returnOrder.address }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <span
                            v-if="returnOrder.is_returned"
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
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                            :class="dismantlingToneClass(returnOrder.dismantling_tone)"
                        >
                            {{ returnOrder.dismantling_label }}
                        </span>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-slate-50 px-4 py-3 dark:bg-neutral-950">
                        <p class="text-xs text-gray-400">تاريخ الفك</p>
                        <p v-if="returnOrder.dismantling_at" class="mt-1 flex items-center gap-1.5 font-medium tabular-nums" dir="ltr">
                            <CalendarClock class="size-4 text-gray-400" />
                            {{ formatDateTime(returnOrder.dismantling_at) }}
                        </p>
                        <p v-else class="mt-1 text-sm text-gray-400">—</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-4 py-3 dark:bg-neutral-950">
                        <p class="text-xs text-gray-400">المنتجات</p>
                        <p class="mt-1 text-lg font-bold tabular-nums">{{ formatInteger(returnOrder.products_count) }}</p>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-violet-100 bg-violet-50/40 p-5 dark:border-violet-900/40 dark:bg-violet-950/20">
                <p class="text-xs font-semibold uppercase tracking-wide text-violet-500">نوع المهمة للعامل</p>
                <p class="mt-2 text-2xl font-black text-violet-800 dark:text-violet-200">فك</p>
                <p class="mt-2 text-sm leading-relaxed text-violet-700/80 dark:text-violet-300/80">
                    عند تعيين عامل من هنا سيظهر الطلب عنده كـ <strong>فك</strong> وليس تركيب.
                </p>
            </article>
        </section>

        <section
            v-if="canAssignWorkers"
            class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900 sm:p-6"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">تعيين عامل الفك</h2>
                    <p class="mt-1 text-sm text-slate-500">اختر العامل المطلوب من قائمة العمال المسجلين</p>
                </div>
                <Button
                    v-if="!assemblerFormOpen"
                    size="sm"
                    class="h-10 rounded-xl bg-[#2563EB] hover:bg-[#1D4ED8]"
                    @click="openAssemblerForm"
                >
                    <Plus class="ms-1.5 h-4 w-4" />
                    تعيين عامل
                </Button>
            </div>

            <div
                v-if="assemblerFormOpen"
                class="mt-4 flex flex-col gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200 dark:bg-neutral-950 dark:ring-neutral-700 sm:flex-row sm:items-end"
            >
                <div class="flex-1 space-y-2">
                    <Label for="assign-dismantling-worker">العامل</Label>
                    <select
                        id="assign-dismantling-worker"
                        v-model="assemblerForm.user_id"
                        class="flex h-11 w-full rounded-xl border border-input bg-white px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-neutral-900"
                    >
                        <option value="">اختر العامل…</option>
                        <option
                            v-for="worker in selectableWorkers"
                            :key="worker.id"
                            :value="worker.id"
                        >
                            {{ worker.name }}{{ worker.phone ? ` — ${worker.phone}` : '' }}
                        </option>
                    </select>
                    <p v-if="assemblerForm.errors.user_id" class="text-sm text-rose-600">
                        {{ assemblerForm.errors.user_id }}
                    </p>
                    <p v-else-if="!selectableWorkers.length" class="text-sm text-amber-600">
                        لا يوجد عمال متاحون للتعيين (أو تم تعيينهم جميعاً).
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" class="h-11 rounded-xl" @click="closeAssemblerForm">إلغاء</Button>
                    <Button
                        class="h-11 rounded-xl bg-[#2563EB]"
                        :disabled="assemblerForm.processing || !assemblerForm.user_id"
                        @click="submitAssembler"
                    >
                        حفظ التعيين
                    </Button>
                </div>
            </div>

            <div v-if="assemblers.length" class="mt-4 flex flex-wrap gap-2">
                <span
                    v-for="assembler in assemblers"
                    :key="assembler.id"
                    class="inline-flex items-center gap-2 rounded-full bg-orange-50 px-3 py-1.5 text-sm font-medium text-orange-800 ring-1 ring-orange-200"
                >
                    <Users class="h-3.5 w-3.5" />
                    {{ assembler.worker_name }}
                    <span class="rounded-full bg-white px-1.5 py-0.5 text-[10px] font-bold text-orange-600">فك</span>
                    <button
                        type="button"
                        class="text-orange-400 hover:text-rose-500"
                        :disabled="deletingAssemblerId === assembler.id"
                        @click="deleteAssembler(assembler)"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </span>
            </div>
            <p v-else class="mt-4 text-sm text-slate-500">لم يُعيَّن عامل فك بعد.</p>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900 sm:p-6">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">المنتجات</h2>
            <ul class="mt-4 divide-y divide-slate-100 dark:divide-neutral-800">
                <li
                    v-for="product in returnOrder.products"
                    :key="product.id"
                    class="flex items-center gap-3 py-3"
                >
                    <img
                        v-if="product.product_image_url"
                        :src="product.product_image_url"
                        :alt="product.product_name"
                        class="size-12 rounded-xl object-cover ring-1 ring-slate-200"
                    >
                    <div
                        v-else
                        class="flex size-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400"
                    >
                        <PackageCheck class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-slate-900 dark:text-white">{{ product.product_name }}</p>
                        <p v-if="product.pickup_at" class="mt-0.5 text-xs text-emerald-600">
                            تم الفك {{ formatDateTime(product.pickup_at) }}
                        </p>
                    </div>
                </li>
            </ul>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-900 sm:p-6">
            <h2 class="mb-4 flex items-center gap-2 text-lg font-bold text-slate-900 dark:text-white">
                <MessageSquareText class="h-5 w-5 text-slate-500" />
                الملاحظات ({{ formatInteger(returnOrder.notes_count || 0) }})
            </h2>

            <div v-if="returnOrder.notes?.length" class="mb-4 max-h-80 space-y-3 overflow-y-auto">
                <article
                    v-for="note in returnOrder.notes"
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
                    v-model="noteForm.body"
                    rows="3"
                    maxlength="2000"
                    placeholder="اكتب ملاحظة عن حالة المنتجات أو أي ملاحظات للمستودع..."
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none transition focus:border-orange-300 focus:ring-2 focus:ring-orange-100 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100"
                />
                <p v-if="noteForm.errors.body" class="text-sm text-rose-600">{{ noteForm.errors.body }}</p>
                <div class="flex justify-end">
                    <Button
                        size="sm"
                        class="gap-1.5"
                        :disabled="noteForm.processing"
                        @click="submitNote"
                    >
                        <MessageSquareText class="size-3.5" />
                        {{ noteForm.processing ? 'جاري الحفظ...' : 'حفظ الملاحظة' }}
                    </Button>
                </div>
            </div>
        </section>
    </div>
</template>
