<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    Camera,
    CheckCircle2,
    Clock3,
    ExternalLink,
    Gamepad2,
    History,
    MapPin,
    MessageSquareText,
    Phone,
    Plus,
    Trash2,
    Users,
    Wrench,
    X,
} from 'lucide-vue-next';
import { formatCurrency, formatDate, formatDateTime } from '@/lib/formatNumber';
import MainAppLayout from '../../layouts/MainAppLayout.vue';

type EventStatus = 'pending' | 'in_progress' | 'completed';
type PickupCondition = 'excellent' | 'good' | 'damaged' | 'broken';

interface WorkOrderLine {
    id: number;
    product_name: string;
    product_image_url: string | null;
    status: 'pending' | 'completed';
    installation_photo_url: string | null;
    completed_at: string | null;
    completed_by_user?: { id: number; name: string } | null;
    pickup_photo_url: string | null;
    pickup_at: string | null;
    pickup_condition: PickupCondition | null;
}

interface WorkOrderAssembler {
    id: number;
    worker_name: string;
    user_id?: number | null;
    created_at: string | null;
}

interface AvailableWorker {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
}

interface WorkOrderNote {
    id: number;
    body: string;
    user_name: string;
    user_role?: string;
    created_at: string | null;
}

interface TimelineItem {
    key: string;
    title: string;
    description: string;
    timestamp: string | null;
    user_name: string | null;
    completed: boolean;
}

interface WorkOrder {
    id: number;
    reference_number: string;
    order_number: string;
    invoice_number: string | null;
    customer_name: string;
    customer_phone: string | null;
    customer_email: string | null;
    customer_address: string | null;
    address: string | null;
    installation_date: string | null;
    activity_time: string | null;
    status: 'pending' | 'completed';
    event_status?: EventStatus;
    products_count: number;
    pending_count: number;
    completed_count: number;
    lines: WorkOrderLine[];
    assemblers?: WorkOrderAssembler[];
    assigned_workers?: string[];
    notes?: WorkOrderNote[];
    timeline?: TimelineItem[];
    installation_progress?: { done: number; total: number };
    photo_stats?: { installation: number; pickup?: number };
    delivery_note_url: string;
    photos_ready?: boolean;
    is_approved?: boolean;
    can_approve?: boolean;
    approved_at?: string | null;
    approved_by_name?: string | null;
    currency?: string;
    total_amount?: number;
    amount_paid?: number;
    remaining_amount?: number;
}

interface Props {
    workOrder: WorkOrder;
    availableWorkers?: AvailableWorker[];
}

const props = withDefaults(defineProps<Props>(), {
    availableWorkers: () => [],
});

const page = usePage();
const successMessage = computed(() => (page.props.flash as { success?: string } | undefined)?.success);
const errorMessage = computed(() => (page.props.flash as { error?: string } | undefined)?.error);

const returnTo = computed(
    () => `/main-app/work-orders/${encodeURIComponent(props.workOrder.reference_number)}`,
);

const assignForm = useForm({
    user_id: '' as string | number,
    return_to: returnTo.value,
});

const noteForm = useForm({
    body: '',
    return_to: returnTo.value,
});

const approveForm = useForm({
    return_to: returnTo.value,
});

const photoPreview = ref<string | null>(null);
const processingAssignId = ref<number | null>(null);

const assemblers = computed(() => props.workOrder.assemblers ?? []);
const notes = computed(() => props.workOrder.notes ?? []);
const timeline = computed(() => props.workOrder.timeline ?? []);
const progress = computed(() => props.workOrder.installation_progress ?? {
    done: props.workOrder.completed_count,
    total: props.workOrder.products_count,
});

const installButtonLabel = computed(() => {
    if (props.workOrder.is_approved) return 'تم التركيب ✓';
    if (approveForm.processing) return 'جاري التأكيد...';
    if (props.workOrder.can_approve) return 'تم التركيب';
    return 'بانتظار اكتمال التركيب';
});

const canClickInstallDone = computed(
    () => Boolean(props.workOrder.can_approve) && !approveForm.processing,
);

const unassignedWorkers = computed(() => {
    const assignedIds = new Set(
        assemblers.value.map((a) => a.user_id).filter((id): id is number => id != null),
    );
    const assignedNames = new Set(assemblers.value.map((a) => a.worker_name));

    return props.availableWorkers.filter(
        (w) => !assignedIds.has(w.id) && !assignedNames.has(w.name),
    );
});

function formatInstallDate(date: string | null): string {
    if (!date) return 'بدون تاريخ';
    return formatDate(date);
}

function formatActivityTime(time: string | null): string {
    if (!time) return '—';
    const [hourStr, minuteStr = '00'] = time.split(':');
    let hour = Number(hourStr);
    if (Number.isNaN(hour)) return time;
    const period = hour >= 12 ? 'م' : 'ص';
    hour = hour % 12 || 12;
    return `${hour}:${minuteStr.padStart(2, '0')} ${period}`;
}

function conditionLabel(key: PickupCondition | null): string {
    const map: Record<PickupCondition, string> = {
        excellent: 'ممتازة',
        good: 'جيدة',
        damaged: 'تالفة',
        broken: 'مكسورة',
    };
    return key ? map[key] : '—';
}

function mapUrl(address: string | null): string | null {
    if (!address || !address.trim()) return null;
    const trimmed = address.trim();
    if (/^https?:\/\//i.test(trimmed)) return trimmed;
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(trimmed)}`;
}

function assignWorker() {
    if (!assignForm.user_id) {
        assignForm.setError('user_id', 'يجب اختيار العامل.');
        return;
    }
    assignForm.return_to = returnTo.value;
    assignForm.post(`/worker-orders/${encodeURIComponent(props.workOrder.reference_number)}/assemblers`, {
        preserveScroll: true,
        onSuccess: () => {
            assignForm.reset('user_id');
            assignForm.clearErrors();
            assignForm.return_to = returnTo.value;
        },
    });
}

function removeAssembler(assembler: WorkOrderAssembler) {
    if (!confirm(`حذف تعيين ${assembler.worker_name}؟`)) return;
    processingAssignId.value = assembler.id;
    router.delete(
        `/worker-orders/${encodeURIComponent(props.workOrder.reference_number)}/assemblers/${assembler.id}?return_to=${encodeURIComponent(returnTo.value)}`,
        {
            preserveScroll: true,
            onFinish: () => {
                processingAssignId.value = null;
            },
        },
    );
}

function approveOrder() {
    if (!props.workOrder.can_approve) return;
    if (!confirm('تأكيد أن التركيب اكتمل؟ سيتم تعميد أمر العمل.')) return;
    approveForm.return_to = returnTo.value;
    approveForm.post(`/worker-orders/${encodeURIComponent(props.workOrder.reference_number)}/approve`, {
        preserveScroll: true,
    });
}

function submitNote() {
    const body = noteForm.body.trim();
    if (!body) {
        noteForm.setError('body', 'يجب كتابة الملاحظة.');
        return;
    }
    noteForm.body = body;
    noteForm.return_to = returnTo.value;
    noteForm.post(`/worker-orders/${encodeURIComponent(props.workOrder.reference_number)}/notes`, {
        preserveScroll: true,
        onSuccess: () => {
            noteForm.reset('body');
            noteForm.clearErrors();
            noteForm.return_to = returnTo.value;
        },
    });
}

function openPhoto(url: string) {
    photoPreview.value = url;
}
</script>

<template>
    <Head :title="`أمر عمل — ${workOrder.customer_name}`" />

    <MainAppLayout :show-nav="false">
        <div class="px-4 pt-5" style="padding-top: max(1.25rem, env(safe-area-inset-top))">
            <header class="mb-4 flex items-center gap-3">
                <Link
                    href="/main-app"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm"
                >
                    <ArrowRight class="h-5 w-5" />
                </Link>
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-slate-500">تفاصيل أمر العمل</p>
                    <h1 class="truncate text-lg font-bold text-slate-900">{{ workOrder.customer_name }}</h1>
                </div>
            </header>

            <div
                v-if="successMessage"
                class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
            >
                {{ successMessage }}
            </div>
            <div
                v-if="errorMessage"
                class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"
            >
                {{ errorMessage }}
            </div>

            <!-- Overview -->
            <section class="mb-4 rounded-[1.5rem] border border-white/80 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1"
                        :class="workOrder.is_approved
                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-100'
                            : workOrder.can_approve
                                ? 'bg-amber-50 text-amber-700 ring-amber-100'
                                : 'bg-slate-100 text-slate-600 ring-slate-200'"
                    >
                        {{ workOrder.is_approved ? 'مُعمَّد' : workOrder.can_approve ? 'جاهز للتعميد' : 'قيد المتابعة' }}
                    </span>
                    <span class="rounded-full bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-700 ring-1 ring-sky-100">
                        تركيب {{ progress.done }}/{{ progress.total }}
                    </span>
                </div>

                <p class="mt-3 text-xs text-slate-500" dir="ltr">{{ workOrder.reference_number }}</p>

                <div class="mt-3 space-y-2 text-sm text-slate-700">
                    <p class="flex items-center gap-2">
                        <CalendarDays class="h-4 w-4 text-slate-400" />
                        {{ formatInstallDate(workOrder.installation_date) }}
                    </p>
                    <p class="flex items-center gap-2">
                        <Clock3 class="h-4 w-4 text-slate-400" />
                        {{ formatActivityTime(workOrder.activity_time) }}
                    </p>
                    <a
                        v-if="workOrder.customer_phone"
                        :href="`tel:${workOrder.customer_phone}`"
                        class="flex items-center gap-2 font-medium"
                        dir="ltr"
                    >
                        <Phone class="h-4 w-4 text-slate-400" />
                        {{ workOrder.customer_phone }}
                    </a>
                    <a
                        v-if="mapUrl(workOrder.address || workOrder.customer_address)"
                        :href="mapUrl(workOrder.address || workOrder.customer_address)!"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 font-semibold text-teal-700"
                    >
                        <MapPin class="h-4 w-4" />
                        موقع العميل
                        <ExternalLink class="h-3.5 w-3.5 opacity-70" />
                    </a>
                    <p v-else-if="workOrder.address || workOrder.customer_address" class="flex items-start gap-2">
                        <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                        <span>{{ workOrder.address || workOrder.customer_address }}</span>
                    </p>
                </div>

                <div
                    v-if="workOrder.total_amount != null"
                    class="mt-4 grid grid-cols-3 gap-2 rounded-2xl bg-slate-50 p-3 text-center"
                >
                    <div>
                        <p class="text-[10px] text-slate-500">الإجمالي</p>
                        <p class="mt-0.5 text-xs font-bold text-slate-800">
                            {{ formatCurrency(workOrder.total_amount ?? 0, workOrder.currency || 'SAR') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-500">المدفوع</p>
                        <p class="mt-0.5 text-xs font-bold text-emerald-700">
                            {{ formatCurrency(workOrder.amount_paid ?? 0, workOrder.currency || 'SAR') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-500">المتبقي</p>
                        <p class="mt-0.5 text-xs font-bold text-amber-700">
                            {{ formatCurrency(workOrder.remaining_amount ?? 0, workOrder.currency || 'SAR') }}
                        </p>
                    </div>
                </div>

                <a
                    v-if="workOrder.delivery_note_url"
                    :href="workOrder.delivery_note_url"
                    target="_blank"
                    class="mt-3 inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700"
                >
                    إذن التسليم
                    <ExternalLink class="h-3.5 w-3.5" />
                </a>
            </section>

            <!-- تم التركيب -->
            <section class="mb-4 rounded-[1.5rem] border p-4 shadow-sm"
                :class="workOrder.is_approved
                    ? 'border-emerald-100 bg-emerald-50/80'
                    : workOrder.can_approve
                        ? 'border-emerald-200 bg-gradient-to-br from-emerald-50 to-teal-50'
                        : 'border-slate-200 bg-white'"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-white shadow-md"
                        :class="workOrder.is_approved || workOrder.can_approve ? 'bg-emerald-500' : 'bg-slate-400'"
                    >
                        <Wrench class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-slate-900">
                            {{ workOrder.is_approved ? 'اكتمل التركيب وتم التعميد' : 'تأكيد اكتمال التركيب' }}
                        </p>
                        <p class="mt-1 text-sm text-slate-600">
                            <template v-if="workOrder.is_approved">
                                اعتمد بواسطة {{ workOrder.approved_by_name || 'مدير العمال' }}
                                <span v-if="workOrder.approved_at"> — {{ formatDateTime(workOrder.approved_at) }}</span>
                            </template>
                            <template v-else-if="workOrder.can_approve">
                                كل الألعاب مكتملة الصور. اضغط «تم التركيب» لتعميد أمر العمل.
                            </template>
                            <template v-else>
                                التقدم: {{ progress.done }}/{{ progress.total }} —
                                بانتظار رفع العامل لصور التركيب.
                            </template>
                        </p>
                        <button
                            type="button"
                            class="mt-3 inline-flex h-12 w-full items-center justify-center gap-2 rounded-2xl text-sm font-bold text-white shadow-sm transition disabled:opacity-60"
                            :class="workOrder.is_approved
                                ? 'bg-emerald-600'
                                : canClickInstallDone
                                    ? 'bg-emerald-600 active:scale-[0.99] hover:bg-emerald-700'
                                    : 'bg-slate-300 text-slate-600'"
                            :disabled="!canClickInstallDone && !workOrder.is_approved"
                            @click="canClickInstallDone && approveOrder()"
                        >
                            <CheckCircle2 class="h-5 w-5" />
                            {{ installButtonLabel }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- الألعاب المطلوب تركيبها -->
            <section class="mb-4 space-y-3">
                <div class="flex items-center justify-between gap-2 px-1">
                    <h2 class="flex items-center gap-2 text-sm font-bold text-slate-800">
                        <Gamepad2 class="h-4 w-4 text-teal-600" />
                        الألعاب المطلوب تركيبها
                    </h2>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                        {{ progress.done }}/{{ progress.total }}
                    </span>
                </div>
                <p class="px-1 text-xs text-slate-500">تفاصيل الألعاب والمعدات المفترض تركيبها لهذه الفعالية</p>

                <article
                    v-for="line in workOrder.lines"
                    :key="`game-${line.id}`"
                    class="overflow-hidden rounded-[1.5rem] border border-white/80 bg-white shadow-sm"
                >
                    <div class="relative aspect-[16/9] bg-slate-100">
                        <img
                            v-if="line.product_image_url"
                            :src="line.product_image_url"
                            :alt="line.product_name"
                            class="h-full w-full object-cover"
                        />
                        <div v-else class="flex h-full w-full items-center justify-center text-slate-300">
                            <Gamepad2 class="h-10 w-10" />
                        </div>
                        <span class="absolute start-3 top-3 rounded-full bg-white/95 px-2.5 py-1 text-[11px] font-semibold text-slate-700 shadow-sm">
                            كمية: 1
                        </span>
                        <span
                            class="absolute end-3 top-3 rounded-full px-2.5 py-1 text-[11px] font-semibold shadow-sm"
                            :class="line.status === 'completed'
                                ? 'bg-emerald-600 text-white'
                                : 'bg-amber-500 text-white'"
                        >
                            {{ line.status === 'completed' ? 'تم التركيب' : 'قيد الانتظار' }}
                        </span>
                    </div>
                    <div class="space-y-2 p-4">
                        <h3 class="font-bold text-slate-900">{{ line.product_name }}</h3>
                        <div class="flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                            <span
                                class="rounded-full px-2.5 py-0.5 font-semibold"
                                :class="line.status === 'completed'
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-amber-50 text-amber-700'"
                            >
                                تركيب: {{ line.status === 'completed' ? 'مكتمل' : 'لم يكتمل' }}
                            </span>
                            <span
                                class="rounded-full px-2.5 py-0.5 font-semibold"
                                :class="line.installation_photo_url
                                    ? 'bg-sky-50 text-sky-700'
                                    : 'bg-slate-100 text-slate-500'"
                            >
                                {{ line.installation_photo_url ? 'صورة مرفوعة' : 'بدون صورة' }}
                            </span>
                        </div>
                        <p v-if="line.completed_by_user" class="text-[11px] text-slate-400">
                            ركّبها {{ line.completed_by_user.name }}
                            <span v-if="line.completed_at"> — {{ formatDateTime(line.completed_at) }}</span>
                        </p>
                        <button
                            v-if="line.installation_photo_url"
                            type="button"
                            class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700"
                            @click="openPhoto(line.installation_photo_url!)"
                        >
                            <Camera class="h-3.5 w-3.5" />
                            عرض صورة التركيب
                        </button>
                    </div>
                </article>

                <div
                    v-if="!workOrder.lines.length"
                    class="rounded-[1.5rem] border border-dashed border-slate-200 bg-white/70 px-5 py-10 text-center"
                >
                    <Gamepad2 class="mx-auto h-8 w-8 text-slate-300" />
                    <p class="mt-2 text-sm font-semibold text-slate-600">لا توجد ألعاب مسجّلة</p>
                </div>
            </section>

            <!-- Assign workers -->
            <section class="mb-4 rounded-[1.5rem] border border-white/80 bg-white p-4 shadow-sm">
                <h2 class="flex items-center gap-2 text-sm font-bold text-slate-800">
                    <Users class="h-4 w-4 text-teal-600" />
                    تعيين العمال
                </h2>

                <div class="mt-3 space-y-2">
                    <select
                        v-model="assignForm.user_id"
                        class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm outline-none focus:border-teal-400 focus:ring-2 focus:ring-teal-100"
                        :disabled="assignForm.processing || !unassignedWorkers.length"
                    >
                        <option value="">اختر عامل التركيب...</option>
                        <option v-for="worker in unassignedWorkers" :key="worker.id" :value="worker.id">
                            {{ worker.name }}
                        </option>
                    </select>
                    <p v-if="assignForm.errors.user_id" class="text-xs text-rose-600">{{ assignForm.errors.user_id }}</p>
                    <button
                        type="button"
                        class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-teal-700 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="assignForm.processing || !assignForm.user_id"
                        @click="assignWorker"
                    >
                        <Plus class="h-4 w-4" />
                        {{ assignForm.processing ? 'جاري التعيين...' : 'تعيين العامل' }}
                    </button>
                </div>

                <div v-if="assemblers.length" class="mt-4 space-y-2">
                    <article
                        v-for="assembler in assemblers"
                        :key="assembler.id"
                        class="flex items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2.5"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-800">{{ assembler.worker_name }}</p>
                            <p v-if="assembler.created_at" class="text-[11px] text-slate-400">
                                {{ formatDateTime(assembler.created_at) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-rose-600 hover:bg-rose-50 disabled:opacity-50"
                            :disabled="processingAssignId === assembler.id"
                            @click="removeAssembler(assembler)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </article>
                </div>
                <p v-else class="mt-3 text-xs text-slate-400">لم يتم تعيين عمال بعد</p>
            </section>

            <!-- Photos gallery -->
            <section class="mb-4 space-y-3">
                <h2 class="flex items-center gap-2 px-1 text-sm font-bold text-slate-800">
                    <Camera class="h-4 w-4 text-teal-600" />
                    صور التركيب والاستلام
                </h2>

                <article
                    v-for="line in workOrder.lines"
                    :key="`photo-${line.id}`"
                    class="overflow-hidden rounded-[1.5rem] border border-white/80 bg-white shadow-sm"
                >
                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ line.product_name }}</p>
                        <span
                            class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                            :class="line.status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                        >
                            {{ line.status === 'completed' ? 'تم التركيب' : 'قيد التركيب' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-px bg-slate-100">
                        <button
                            type="button"
                            class="relative aspect-square bg-white disabled:cursor-default"
                            :disabled="!line.installation_photo_url"
                            @click="line.installation_photo_url && openPhoto(line.installation_photo_url)"
                        >
                            <img
                                v-if="line.installation_photo_url"
                                :src="line.installation_photo_url"
                                alt="صورة التركيب"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex h-full flex-col items-center justify-center gap-1 text-slate-300">
                                <Camera class="h-6 w-6" />
                                <span class="text-[10px]">لا توجد صورة تركيب</span>
                            </div>
                            <span class="absolute bottom-2 start-2 rounded-md bg-black/55 px-1.5 py-0.5 text-[10px] font-medium text-white">
                                تركيب
                            </span>
                        </button>
                        <button
                            type="button"
                            class="relative aspect-square bg-white disabled:cursor-default"
                            :disabled="!line.pickup_photo_url"
                            @click="line.pickup_photo_url && openPhoto(line.pickup_photo_url)"
                        >
                            <img
                                v-if="line.pickup_photo_url"
                                :src="line.pickup_photo_url"
                                alt="صورة الاستلام"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex h-full flex-col items-center justify-center gap-1 text-slate-300">
                                <Camera class="h-6 w-6" />
                                <span class="text-[10px]">لا توجد صورة استلام</span>
                            </div>
                            <span class="absolute bottom-2 start-2 rounded-md bg-black/55 px-1.5 py-0.5 text-[10px] font-medium text-white">
                                استلام
                            </span>
                        </button>
                    </div>
                    <p v-if="line.pickup_condition" class="px-3 py-2 text-[11px] text-slate-500">
                        حالة الاستلام: {{ conditionLabel(line.pickup_condition) }}
                        <span v-if="line.pickup_at"> — {{ formatDateTime(line.pickup_at) }}</span>
                    </p>
                </article>
            </section>

            <!-- Notes -->
            <section class="mb-4 rounded-[1.5rem] border border-white/80 bg-white p-4 shadow-sm">
                <h2 class="flex items-center gap-2 text-sm font-bold text-slate-800">
                    <MessageSquareText class="h-4 w-4 text-teal-600" />
                    الملاحظات
                </h2>

                <textarea
                    v-model="noteForm.body"
                    rows="3"
                    maxlength="2000"
                    placeholder="اكتب ملاحظة..."
                    class="mt-3 w-full resize-none rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none focus:border-teal-400 focus:bg-white focus:ring-2 focus:ring-teal-100"
                    :disabled="noteForm.processing"
                />
                <p v-if="noteForm.errors.body" class="mt-1 text-xs text-rose-600">{{ noteForm.errors.body }}</p>
                <button
                    type="button"
                    class="mt-2 inline-flex h-11 w-full items-center justify-center rounded-xl bg-slate-900 text-sm font-semibold text-white disabled:opacity-50"
                    :disabled="noteForm.processing || !noteForm.body.trim()"
                    @click="submitNote"
                >
                    {{ noteForm.processing ? 'جاري الحفظ...' : 'حفظ الملاحظة' }}
                </button>

                <div v-if="notes.length" class="mt-4 space-y-2">
                    <article
                        v-for="note in notes"
                        :key="note.id"
                        class="rounded-xl bg-slate-50 px-3 py-2.5"
                    >
                        <p class="whitespace-pre-wrap text-sm text-slate-800">{{ note.body }}</p>
                        <p class="mt-1.5 text-[11px] text-slate-400">
                            {{ note.user_name }}
                            <span v-if="note.user_role"> · {{ note.user_role }}</span>
                            <span v-if="note.created_at"> · {{ formatDateTime(note.created_at) }}</span>
                        </p>
                    </article>
                </div>
            </section>

            <!-- Timeline -->
            <section class="mb-8 rounded-[1.5rem] border border-white/80 bg-white p-4 shadow-sm">
                <h2 class="flex items-center gap-2 text-sm font-bold text-slate-800">
                    <History class="h-4 w-4 text-teal-600" />
                    الجدول الزمني
                </h2>
                <ol class="mt-4 space-y-3">
                    <li
                        v-for="item in timeline"
                        :key="item.key"
                        class="flex gap-3"
                    >
                        <span
                            class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full"
                            :class="item.completed ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'"
                        >
                            <CheckCircle2 class="h-4 w-4" />
                        </span>
                        <div class="min-w-0 flex-1 pb-2">
                            <p class="text-sm font-semibold text-slate-800">{{ item.title }}</p>
                            <p class="text-xs text-slate-500">{{ item.description }}</p>
                            <p v-if="item.timestamp" class="mt-0.5 text-[11px] text-slate-400">
                                {{ formatDateTime(item.timestamp) }}
                                <span v-if="item.user_name"> · {{ item.user_name }}</span>
                            </p>
                        </div>
                    </li>
                </ol>
            </section>
        </div>

        <Teleport to="body">
            <div
                v-if="photoPreview"
                class="fixed inset-0 z-[300] flex items-center justify-center bg-black/85 p-4"
                role="dialog"
                aria-modal="true"
                @click.self="photoPreview = null"
            >
                <button
                    type="button"
                    class="absolute end-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white"
                    style="top: max(1rem, env(safe-area-inset-top))"
                    @click="photoPreview = null"
                >
                    <X class="h-5 w-5" />
                </button>
                <img :src="photoPreview" alt="معاينة الصورة" class="max-h-[85vh] max-w-full rounded-2xl object-contain" />
            </div>
        </Teleport>
    </MainAppLayout>
</template>
