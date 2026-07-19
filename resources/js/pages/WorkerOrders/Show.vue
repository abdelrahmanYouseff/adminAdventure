<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    ArrowRight,
    User,
    Camera,
    CheckCircle2,
    ExternalLink,
    Navigation,
    ImageIcon,
    X,
    Printer,
    MapPin,
    Phone,
    Plus,
    Trash2,
    Users,
    MessageSquareText,
    PackageOpen,
    CircleDot,
    LayoutDashboard,
    Wrench,
    Truck,
    Gamepad2,
    History,
    MoreHorizontal,
    ShieldCheck,
} from 'lucide-vue-next';
import { formatDate, formatDateTime } from '@/lib/formatNumber';
import Swal from 'sweetalert2';

type TabKey = 'overview' | 'installation' | 'pickup' | 'games' | 'notes' | 'timeline';
type EventStatus = 'pending' | 'in_progress' | 'pickup' | 'completed';
type PickupCondition = 'excellent' | 'good' | 'damaged' | 'broken';

interface CompletedByUser {
    id: number;
    name: string;
}

interface WorkOrderLine {
    id: number;
    product_name: string;
    product_image_url: string | null;
    status: 'pending' | 'completed';
    installation_photo_url: string | null;
    completed_at: string | null;
    completed_by_user?: CompletedByUser | null;
    pickup_photo_url?: string | null;
    pickup_at?: string | null;
    pickup_by_user?: CompletedByUser | null;
    pickup_condition?: PickupCondition | null;
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

interface Progress {
    done: number;
    total: number;
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
    created_at?: string | null;
    status: 'pending' | 'completed';
    event_status?: EventStatus;
    products_count: number;
    pending_count: number;
    completed_count: number;
    location_slug: string | null;
    lines: WorkOrderLine[];
    assemblers?: WorkOrderAssembler[];
    assigned_workers?: string[];
    notes?: WorkOrderNote[];
    timeline?: TimelineItem[];
    installation_progress?: Progress;
    pickup_progress?: Progress;
    photo_stats?: { installation: number; pickup: number };
    delivery_note_url: string;
    photos_ready?: boolean;
    is_approved?: boolean;
    can_approve?: boolean;
    approved_at?: string | null;
    approved_by_name?: string | null;
}

interface Props {
    workOrder: WorkOrder;
    availableWorkers?: AvailableWorker[];
}

const props = withDefaults(defineProps<Props>(), {
    availableWorkers: () => [],
});

defineOptions({ layout: AppLayout });

const page = usePage();
const flash = computed(() => (page.props.flash as { success?: string; error?: string } | undefined) ?? {});
const authRole = computed(() => (page.props.auth as { user?: { role?: string } } | undefined)?.user?.role ?? null);
const canAssignWorkers = computed(() =>
    ['admin', 'general_manager', 'manager', 'workers_manager'].includes(authRole.value || ''),
);
const canApproveOrder = computed(() =>
    ['admin', 'workers_manager'].includes(authRole.value || ''),
);
/** مدير العمال يعتمد فقط — رفع الصور من تطبيق العامل */
const canUploadPhotos = computed(() => authRole.value !== 'workers_manager');
const canDeleteNotes = computed(() =>
    ['admin', 'general_manager', 'manager'].includes(authRole.value || ''),
);
/** مدير العمال يراجع الصور فقط قبل التعميد */
const isPhotoReviewer = computed(() => authRole.value === 'workers_manager');
const approving = ref(false);

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

const activeTab = ref<TabKey>('overview');
const lightboxUrl = ref<string | null>(null);
const lightboxLabel = ref('');

const assemblers = computed(() => props.workOrder.assemblers ?? []);
const notes = computed(() => props.workOrder.notes ?? []);
const timeline = computed(() => props.workOrder.timeline ?? []);
const assignedWorkers = computed(() => props.workOrder.assigned_workers ?? assemblers.value.map((a) => a.worker_name));
const installedLines = computed(() => props.workOrder.lines.filter((line) => line.status === 'completed'));
const installProgress = computed(() => props.workOrder.installation_progress ?? {
    done: props.workOrder.completed_count,
    total: props.workOrder.products_count,
});
const pickupProgress = computed(() => props.workOrder.pickup_progress ?? {
    done: installedLines.value.filter((l) => l.pickup_photo_url).length,
    total: installedLines.value.length,
});
const photoStats = computed(() => props.workOrder.photo_stats ?? {
    installation: props.workOrder.lines.filter((l) => l.installation_photo_url).length,
    pickup: installedLines.value.filter((l) => l.pickup_photo_url).length,
});
const eventStatus = computed<EventStatus>(() => props.workOrder.event_status ?? (props.workOrder.status === 'completed' ? 'pickup' : 'pending'));
const displayAddress = computed(() => props.workOrder.address?.trim() || props.workOrder.customer_address?.trim() || null);
const primaryWorker = computed(() => assignedWorkers.value[0] || 'غير معيّن');

const tabs: { key: TabKey; label: string; icon: typeof LayoutDashboard }[] = [
    { key: 'overview', label: 'نظرة عامة', icon: LayoutDashboard },
    { key: 'installation', label: 'التركيب', icon: Wrench },
    { key: 'pickup', label: 'الاستلام', icon: Truck },
    { key: 'games', label: 'الألعاب', icon: Gamepad2 },
    { key: 'notes', label: 'الملاحظات', icon: MessageSquareText },
    { key: 'timeline', label: 'الجدول الزمني', icon: History },
];

const statusMeta = computed(() => {
    switch (eventStatus.value) {
        case 'completed':
            return { label: 'مكتمل', class: 'bg-[#22C55E]/10 text-[#15803D] ring-[#22C55E]/20' };
        case 'pickup':
            return { label: 'بانتظار الاستلام', class: 'bg-[#F59E0B]/10 text-[#B45309] ring-[#F59E0B]/20' };
        case 'in_progress':
            return { label: 'جاري التركيب', class: 'bg-[#2563EB]/10 text-[#1D4ED8] ring-[#2563EB]/20' };
        default:
            return { label: 'قيد الانتظار', class: 'bg-slate-100 text-slate-600 ring-slate-200' };
    }
});

const conditionLabels: Record<PickupCondition, { label: string; class: string }> = {
    excellent: { label: 'ممتاز', class: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
    good: { label: 'جيد', class: 'bg-sky-50 text-sky-700 ring-sky-200' },
    damaged: { label: 'تالف', class: 'bg-amber-50 text-amber-700 ring-amber-200' },
    broken: { label: 'مكسور', class: 'bg-rose-50 text-rose-700 ring-rose-200' },
};

const selectedLine = ref<WorkOrderLine | null>(null);
const photoPreview = ref<string | null>(null);
const dialogOpen = ref(false);
const photoInputRef = ref<HTMLInputElement | null>(null);
const photoError = ref<string | null>(null);
const completeForm = useForm({
    installation_photo: null as File | null,
    redirect_to_show: true,
});

const selectedPickupLine = ref<WorkOrderLine | null>(null);
const pickupPhotoPreview = ref<string | null>(null);
const pickupDialogOpen = ref(false);
const pickupPhotoInputRef = ref<HTMLInputElement | null>(null);
const pickupPhotoError = ref<string | null>(null);
const pickupForm = useForm({
    pickup_photo: null as File | null,
    pickup_condition: 'good' as PickupCondition,
});

const assemblerFormOpen = ref(false);
const deletingAssemblerId = ref<number | null>(null);
const assemblerForm = useForm({ user_id: '' as string | number });
const deletingNoteId = ref<number | null>(null);
const noteForm = useForm({ body: '' });

const assemblerStoreUrl = computed(
    () => `/worker-orders/${encodeURIComponent(props.workOrder.reference_number)}/assemblers`,
);
const noteStoreUrl = computed(
    () => `/worker-orders/${encodeURIComponent(props.workOrder.reference_number)}/notes`,
);

const selectableWorkers = computed(() => {
    const assignedIds = new Set(
        assemblers.value
            .map((a) => a.user_id)
            .filter((id): id is number => typeof id === 'number'),
    );
    const assignedNames = new Set(assemblers.value.map((a) => a.worker_name));

    return props.availableWorkers.filter(
        (worker) => !assignedIds.has(worker.id) && !assignedNames.has(worker.name),
    );
});

function percent(done: number, total: number): number {
    if (!total) return 0;
    return Math.round((done / total) * 100);
}

function formatEventDate(date: string | null | undefined): string {
    if (!date) return 'غير محدد';
    return formatDate(date);
}

function formatWhen(date: string | null | undefined): string {
    if (!date) return '—';
    return formatDateTime(date);
}

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function locationMapsUrl(address: string | null): string | null {
    if (!address?.trim()) return null;
    const trimmed = address.trim();
    const coordMatch = trimmed.match(/^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/);
    if (coordMatch) return `https://www.google.com/maps?q=${coordMatch[1]},${coordMatch[2]}`;
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(trimmed)}`;
}

function locationLink(): string | null {
    if (props.workOrder.location_slug) {
        return route('store.order.location', props.workOrder.location_slug);
    }
    return locationMapsUrl(displayAddress.value);
}

function openLightbox(url: string, label: string) {
    lightboxUrl.value = url;
    lightboxLabel.value = label;
}

function closeLightbox() {
    lightboxUrl.value = null;
    lightboxLabel.value = '';
}

function printDeliveryNote() {
    window.open(`${props.workOrder.delivery_note_url}?v=${Date.now()}`, '_blank');
}

async function approveWorkOrder() {
    if (props.workOrder.is_approved) {
        return;
    }

    if (!props.workOrder.can_approve) {
        await Swal.fire({
            icon: 'info',
            title: 'لا يمكن التعميد الآن',
            text: props.workOrder.photos_ready
                ? 'تعميد أمر العمل مخصص لمدير العمال فقط.'
                : 'يجب أن يرفع العامل صور التركيب وصور الاستلام لجميع المنتجات أولاً.',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#2563EB',
        });
        return;
    }

    const result = await Swal.fire({
        icon: 'question',
        title: 'تعميد مدير العمال',
        text: `تأكيد تعميد الطلب ${props.workOrder.reference_number}؟ سيظهر التأمين في صفحة الاسترداد وبانتظار تعميد المسئول ثم المدير العام ثم المحاسب.`,
        showCancelButton: true,
        confirmButtonText: 'تعميد',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#059669',
        cancelButtonColor: '#64748B',
        reverseButtons: true,
    });

    if (!result.isConfirmed) {
        return;
    }

    approving.value = true;
    router.post(
        `/worker-orders/${encodeURIComponent(props.workOrder.reference_number)}/approve`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                approving.value = false;
            },
        },
    );
}

function openCompleteDialog(line: WorkOrderLine) {
    selectedLine.value = line;
    completeForm.reset();
    completeForm.clearErrors();
    photoError.value = null;
    photoPreview.value = null;
    if (photoInputRef.value) photoInputRef.value.value = '';
    dialogOpen.value = true;
}

function closeCompleteDialog() {
    dialogOpen.value = false;
    selectedLine.value = null;
    completeForm.reset();
    completeForm.clearErrors();
    photoError.value = null;
    if (photoPreview.value) URL.revokeObjectURL(photoPreview.value);
    photoPreview.value = null;
    if (photoInputRef.value) photoInputRef.value.value = '';
}

function handlePhotoChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (photoPreview.value) URL.revokeObjectURL(photoPreview.value);
    completeForm.installation_photo = file;
    photoPreview.value = file ? URL.createObjectURL(file) : null;
    photoError.value = null;
    completeForm.clearErrors('installation_photo');
}

function submitCompletion() {
    if (!selectedLine.value) return;
    if (!completeForm.installation_photo) {
        photoError.value = 'يجب إرفاق صورة للتركيب من أرض الواقع قبل الإرسال.';
        return;
    }
    completeForm.post(`/worker-orders/lines/${selectedLine.value.id}/complete`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => closeCompleteDialog(),
    });
}

function openPickupDialog(line: WorkOrderLine) {
    selectedPickupLine.value = line;
    pickupForm.reset();
    pickupForm.pickup_condition = 'good';
    pickupForm.clearErrors();
    pickupPhotoError.value = null;
    pickupPhotoPreview.value = null;
    if (pickupPhotoInputRef.value) pickupPhotoInputRef.value.value = '';
    pickupDialogOpen.value = true;
}

function closePickupDialog() {
    pickupDialogOpen.value = false;
    selectedPickupLine.value = null;
    pickupForm.reset();
    pickupForm.clearErrors();
    pickupPhotoError.value = null;
    if (pickupPhotoPreview.value) URL.revokeObjectURL(pickupPhotoPreview.value);
    pickupPhotoPreview.value = null;
    if (pickupPhotoInputRef.value) pickupPhotoInputRef.value.value = '';
}

function handlePickupPhotoChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (pickupPhotoPreview.value) URL.revokeObjectURL(pickupPhotoPreview.value);
    pickupForm.pickup_photo = file;
    pickupPhotoPreview.value = file ? URL.createObjectURL(file) : null;
    pickupPhotoError.value = null;
    pickupForm.clearErrors('pickup_photo');
}

function submitPickup() {
    if (!selectedPickupLine.value) return;
    if (!pickupForm.pickup_photo) {
        pickupPhotoError.value = 'يجب إرفاق صورة عند الاستلام والفك قبل الإرسال.';
        return;
    }
    pickupForm.post(`/worker-orders/lines/${selectedPickupLine.value.id}/pickup`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => closePickupDialog(),
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
    assemblerForm.post(assemblerStoreUrl.value, {
        preserveScroll: true,
        onSuccess: () => closeAssemblerForm(),
    });
}

function deleteAssembler(assembler: WorkOrderAssembler) {
    if (!confirm(`حذف العامل «${assembler.worker_name}»؟`)) return;
    deletingAssemblerId.value = assembler.id;
    router.delete(
        `/worker-orders/${encodeURIComponent(props.workOrder.reference_number)}/assemblers/${assembler.id}`,
        { preserveScroll: true, onFinish: () => { deletingAssemblerId.value = null; } },
    );
}

function submitNote() {
    noteForm.post(noteStoreUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            noteForm.reset();
            noteForm.clearErrors();
            activeTab.value = 'notes';
        },
    });
}

function deleteNote(note: WorkOrderNote) {
    if (!confirm('حذف هذه الملاحظة؟')) return;
    deletingNoteId.value = note.id;
    router.delete(
        `/worker-orders/${encodeURIComponent(props.workOrder.reference_number)}/notes/${note.id}`,
        { preserveScroll: true, onFinish: () => { deletingNoteId.value = null; } },
    );
}

watch(dialogOpen, (isOpen) => { if (!isOpen) closeCompleteDialog(); });
watch(pickupDialogOpen, (isOpen) => { if (!isOpen) closePickupDialog(); });
</script>

<template>
    <Head :title="`فعالية ${workOrder.reference_number}`" />

    <div class="min-h-full bg-[#F8FAFC]">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-3 py-4 pb-[max(1.5rem,env(safe-area-inset-bottom))] sm:gap-8 sm:px-6 sm:py-8">
            <!-- Header -->
            <header class="rounded-2xl bg-white p-5 shadow-[0_8px_30px_rgb(15,23,42,0.06)] sm:p-7">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 space-y-3">
                        <Link
                            href="/worker-orders"
                            class="inline-flex h-9 items-center gap-1.5 rounded-xl px-2 text-sm font-medium text-slate-500 transition hover:bg-slate-50 hover:text-slate-800"
                        >
                            <ArrowRight class="h-4 w-4" />
                            رجوع لأوامر العمل
                        </Link>
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                                فعالية {{ workOrder.customer_name }}
                            </h1>
                            <span
                                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
                                :class="statusMeta.class"
                            >
                                {{ statusMeta.label }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500">
                            <span class="font-medium tabular-nums text-slate-700" dir="ltr">{{ workOrder.reference_number }}</span>
                            <span class="hidden h-1 w-1 rounded-full bg-slate-300 sm:inline-block" />
                            <span>{{ workOrder.products_count }} منتج</span>
                            <span class="hidden h-1 w-1 rounded-full bg-slate-300 sm:inline-block" />
                            <span>{{ formatEventDate(workOrder.installation_date) }}</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-if="canApproveOrder"
                            class="h-11 rounded-xl shadow-sm"
                            :class="workOrder.is_approved
                                ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-50'
                                : workOrder.can_approve
                                    ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                    : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                            :disabled="Boolean(workOrder.is_approved) || approving"
                            :title="workOrder.is_approved
                                ? 'تم التعميد'
                                : workOrder.can_approve
                                    ? 'تعميد أمر العمل'
                                    : 'يلزم رفع صور التركيب والاستلام أولاً'"
                            @click="approveWorkOrder"
                        >
                            <ShieldCheck class="ms-1.5 h-4 w-4" />
                            {{ workOrder.is_approved ? 'معتمد' : approving ? 'جاري التعميد...' : 'تعميد' }}
                        </Button>
                        <Button
                            variant="outline"
                            class="h-11 rounded-xl border-slate-200 bg-white shadow-sm"
                            @click="printDeliveryNote"
                        >
                            <Printer class="ms-1.5 h-4 w-4" />
                            إذن التسليم
                        </Button>
                        <Button
                            class="h-11 rounded-xl bg-[#2563EB] shadow-sm hover:bg-[#1D4ED8]"
                            @click="activeTab = 'installation'"
                        >
                            <Camera class="ms-1.5 h-4 w-4" />
                            {{ isPhotoReviewer ? 'مراجعة الصور' : 'إدارة التركيب' }}
                        </Button>
                        <Button variant="ghost" size="icon" class="h-11 w-11 rounded-xl text-slate-500">
                            <MoreHorizontal class="h-5 w-5" />
                        </Button>
                    </div>
                </div>
            </header>

            <!-- Summary cards -->
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="group rounded-2xl bg-white p-5 shadow-[0_8px_30px_rgb(15,23,42,0.05)] transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_12px_40px_rgb(15,23,42,0.08)]">
                    <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-2xl bg-[#2563EB]/10 text-[#2563EB]">
                        <User class="h-5 w-5" />
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">العميل</p>
                    <p class="mt-1 truncate text-lg font-bold text-slate-900">{{ workOrder.customer_name }}</p>
                    <p class="mt-1 flex items-center gap-1.5 text-sm text-slate-500" dir="ltr">
                        <Phone class="h-3.5 w-3.5" />
                        {{ workOrder.customer_phone || 'لا يوجد جوال' }}
                    </p>
                </article>

                <article class="group rounded-2xl bg-white p-5 shadow-[0_8px_30px_rgb(15,23,42,0.05)] transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_12px_40px_rgb(15,23,42,0.08)]">
                    <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-500/10 text-violet-600">
                        <Users class="h-5 w-5" />
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">العامل المعيّن</p>
                    <p class="mt-1 truncate text-lg font-bold text-slate-900">{{ primaryWorker }}</p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ assignedWorkers.length > 1 ? `${assignedWorkers.length} عمال مسجلين` : 'مسؤول التركيب الميداني' }}
                    </p>
                </article>

                <article class="group rounded-2xl bg-white p-5 shadow-[0_8px_30px_rgb(15,23,42,0.05)] transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_12px_40px_rgb(15,23,42,0.08)]">
                    <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600">
                        <Wrench class="h-5 w-5" />
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">موعد التركيب</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ formatEventDate(workOrder.installation_date) }}</p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ installProgress.done }}/{{ installProgress.total }} تم تركيبها
                    </p>
                </article>

                <article class="group rounded-2xl bg-white p-5 shadow-[0_8px_30px_rgb(15,23,42,0.05)] transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_12px_40px_rgb(15,23,42,0.08)]">
                    <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600">
                        <Truck class="h-5 w-5" />
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">موعد الاستلام</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">
                        {{ pickupProgress.done ? formatWhen(installedLines.find((l) => l.pickup_at)?.pickup_at) : 'بعد انتهاء الفعالية' }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ pickupProgress.done }}/{{ pickupProgress.total || installProgress.total }} تم استلامها
                    </p>
                </article>
            </section>

            <!-- Assign installation worker (workers_manager / admin / manager) -->
            <section
                v-if="canAssignWorkers"
                class="rounded-2xl bg-white p-5 shadow-[0_8px_30px_rgb(15,23,42,0.05)] sm:p-6"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">تعيين عامل التركيب</h2>
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
                    class="mt-4 flex flex-col gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200 sm:flex-row sm:items-end"
                >
                    <div class="flex-1 space-y-2">
                        <Label for="assign-worker">العامل</Label>
                        <select
                            id="assign-worker"
                            v-model="assemblerForm.user_id"
                            class="flex h-11 w-full rounded-xl border border-input bg-white px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
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
                        class="inline-flex items-center gap-2 rounded-full bg-violet-50 px-3 py-1.5 text-sm font-medium text-violet-800 ring-1 ring-violet-200"
                    >
                        <Users class="h-3.5 w-3.5" />
                        {{ assembler.worker_name }}
                        <button
                            type="button"
                            class="text-violet-400 hover:text-rose-500"
                            :disabled="deletingAssemblerId === assembler.id"
                            @click="deleteAssembler(assembler)"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </span>
                </div>
                <p v-else class="mt-4 text-sm text-slate-500">لم يُعيَّن عامل تركيب بعد.</p>
            </section>

            <!-- Photo review for workers manager (approve after viewing install + pickup photos) -->
            <section
                v-if="isPhotoReviewer"
                class="rounded-2xl bg-white p-5 shadow-[0_8px_30px_rgb(15,23,42,0.05)] sm:p-6"
            >
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900">
                            <Camera class="h-5 w-5 text-slate-500" />
                            مراجعة صور التركيب والاستلام
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            اطّلع على الصور قبل التعميد. اضغط على أي صورة لتكبيرها.
                        </p>
                    </div>
                    <span
                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1"
                        :class="workOrder.photos_ready
                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                            : 'bg-amber-50 text-amber-700 ring-amber-200'"
                    >
                        {{ workOrder.photos_ready ? 'الصور مكتملة — جاهز للتعميد' : 'بانتظار اكتمال الصور من العامل' }}
                    </span>
                </div>

                <div v-if="!workOrder.lines.length" class="rounded-2xl border border-dashed border-slate-200 px-4 py-12 text-center text-sm text-slate-500">
                    لا توجد منتجات في أمر العمل.
                </div>

                <div v-else class="space-y-4">
                    <article
                        v-for="line in workOrder.lines"
                        :key="`review-${line.id}`"
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50/50"
                    >
                        <div class="flex items-center gap-3 border-b border-slate-100 bg-white px-4 py-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100">
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
                                    حالة الاستلام: {{ conditionLabels[line.pickup_condition].label }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 p-4 sm:grid-cols-2">
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
                                    class="flex aspect-[4/3] items-center justify-center rounded-xl border border-dashed border-slate-200 bg-white text-sm text-slate-400"
                                >
                                    لا توجد صورة
                                </div>
                                <p v-if="line.completed_at" class="mt-2 text-xs text-slate-400">
                                    {{ formatWhen(line.completed_at) }}
                                    <span v-if="line.completed_by_user?.name"> · {{ line.completed_by_user.name }}</span>
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
                                    class="flex aspect-[4/3] items-center justify-center rounded-xl border border-dashed border-slate-200 bg-white text-sm text-slate-400"
                                >
                                    لا توجد صورة
                                </div>
                                <p v-if="line.pickup_at" class="mt-2 text-xs text-slate-400">
                                    {{ formatWhen(line.pickup_at) }}
                                    <span v-if="line.pickup_by_user?.name"> · {{ line.pickup_by_user.name }}</span>
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <!-- Tabs -->
            <div class="overflow-x-auto rounded-2xl bg-white p-1.5 shadow-[0_8px_30px_rgb(15,23,42,0.05)]">
                <div class="flex min-w-max gap-1">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition duration-200"
                        :class="activeTab === tab.key
                            ? 'bg-[#2563EB] text-white shadow-md shadow-blue-500/25'
                            : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                        @click="activeTab = tab.key"
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <!-- OVERVIEW -->
            <section v-if="activeTab === 'overview'" class="grid gap-4 lg:grid-cols-3">
                <article class="rounded-2xl bg-white p-6 shadow-[0_8px_30px_rgb(15,23,42,0.05)] lg:col-span-1">
                    <p class="text-sm font-semibold text-slate-900">حالة الفعالية</p>
                    <div class="mt-4 flex items-center gap-3">
                        <span
                            class="inline-flex rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-inset"
                            :class="statusMeta.class"
                        >
                            {{ statusMeta.label }}
                        </span>
                    </div>
                    <div class="mt-6 space-y-4">
                        <div>
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="text-slate-500">تقدم التركيب</span>
                                <span class="font-semibold tabular-nums text-slate-800">{{ percent(installProgress.done, installProgress.total) }}%</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full bg-[#2563EB] transition-all duration-500"
                                    :style="{ width: `${percent(installProgress.done, installProgress.total)}%` }"
                                />
                            </div>
                        </div>
                        <div>
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="text-slate-500">تقدم الاستلام</span>
                                <span class="font-semibold tabular-nums text-slate-800">{{ percent(pickupProgress.done, pickupProgress.total || 1) }}%</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full bg-[#22C55E] transition-all duration-500"
                                    :style="{ width: `${percent(pickupProgress.done, pickupProgress.total || 1)}%` }"
                                />
                            </div>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl bg-white p-6 shadow-[0_8px_30px_rgb(15,23,42,0.05)] lg:col-span-2">
                    <p class="text-sm font-semibold text-slate-900">بيانات العميل والموقع</p>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div class="space-y-3 text-sm">
                            <div>
                                <p class="text-slate-400">الاسم</p>
                                <p class="font-semibold text-slate-900">{{ workOrder.customer_name }}</p>
                            </div>
                            <div v-if="workOrder.customer_phone">
                                <p class="text-slate-400">الجوال</p>
                                <p class="font-medium text-slate-800" dir="ltr">{{ workOrder.customer_phone }}</p>
                            </div>
                            <div>
                                <p class="text-slate-400">تاريخ الفعالية</p>
                                <p class="font-semibold text-slate-900">{{ formatEventDate(workOrder.installation_date) }}</p>
                            </div>
                        </div>
                        <div class="space-y-3 text-sm">
                            <div>
                                <p class="flex items-center gap-1.5 text-slate-400">
                                    <MapPin class="h-3.5 w-3.5" />
                                    موقع التركيب
                                </p>
                                <p class="mt-1 leading-relaxed font-medium text-slate-800">
                                    {{ displayAddress || 'لم يُحدد موقع' }}
                                </p>
                            </div>
                            <a
                                v-if="locationLink()"
                                :href="locationLink()!"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#2563EB] hover:underline"
                            >
                                <Navigation class="h-4 w-4" />
                                فتح على الخريطة
                                <ExternalLink class="h-3.5 w-3.5 opacity-60" />
                            </a>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl bg-white p-6 shadow-[0_8px_30px_rgb(15,23,42,0.05)]">
                    <p class="text-sm font-semibold text-slate-900">المعدات</p>
                    <p class="mt-4 text-4xl font-bold tracking-tight text-slate-900">{{ workOrder.products_count }}</p>
                    <p class="mt-1 text-sm text-slate-500">ألعاب / منتجات للإيجار</p>
                </article>

                <article class="rounded-2xl bg-white p-6 shadow-[0_8px_30px_rgb(15,23,42,0.05)]">
                    <p class="text-sm font-semibold text-slate-900">إحصائيات الصور</p>
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <Camera class="h-4 w-4 text-[#2563EB]" />
                            <p class="mt-3 text-2xl font-bold text-slate-900">{{ photoStats.installation }}</p>
                            <p class="text-xs text-slate-500">صور تركيب</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <PackageOpen class="h-4 w-4 text-emerald-600" />
                            <p class="mt-3 text-2xl font-bold text-slate-900">{{ photoStats.pickup }}</p>
                            <p class="text-xs text-slate-500">صور استلام</p>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl bg-white p-6 shadow-[0_8px_30px_rgb(15,23,42,0.05)]">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900">آخر الملاحظات</p>
                        <button type="button" class="text-xs font-semibold text-[#2563EB]" @click="activeTab = 'notes'">
                            عرض الكل
                        </button>
                    </div>
                    <div v-if="notes[0]" class="mt-4 rounded-2xl bg-slate-50 p-4">
                        <p class="line-clamp-3 text-sm leading-relaxed text-slate-700">{{ notes[0].body }}</p>
                        <p class="mt-3 text-xs text-slate-400">
                            {{ notes[0].user_name }} · {{ formatWhen(notes[0].created_at) }}
                        </p>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-400">لا توجد ملاحظات بعد</p>
                </article>
            </section>

            <!-- INSTALLATION -->
            <section v-else-if="activeTab === 'installation'" class="space-y-4">
                <article class="rounded-2xl bg-white p-6 shadow-[0_8px_30px_rgb(15,23,42,0.05)]">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">مرحلة التركيب</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ isPhotoReviewer
                                    ? 'راجع صور التركيب المرفوعة من العامل قبل التعميد.'
                                    : 'ارفع صور التركيب من موقع العميل بعد انتهاء التثبيت.' }}
                            </p>
                        </div>
                        <span
                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
                            :class="statusMeta.class"
                        >
                            {{ installProgress.done }}/{{ installProgress.total }} مكتمل
                        </span>
                    </div>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-400">الموقع</p>
                            <p class="mt-1 line-clamp-2 text-sm font-semibold text-slate-800">{{ displayAddress || '—' }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-400">العامل</p>
                            <p class="mt-1 text-sm font-semibold text-slate-800">{{ primaryWorker }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-400">موعد التركيب</p>
                            <p class="mt-1 text-sm font-semibold text-slate-800">{{ formatEventDate(workOrder.installation_date) }}</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-400">صور مرفوعة</p>
                            <p class="mt-1 text-sm font-semibold text-slate-800">{{ photoStats.installation }} صورة</p>
                        </div>
                    </div>
                </article>

                <div class="grid gap-4 md:grid-cols-2">
                    <article
                        v-for="line in workOrder.lines"
                        :key="`install-${line.id}`"
                        class="rounded-2xl bg-white p-5 shadow-[0_8px_30px_rgb(15,23,42,0.05)] transition hover:-translate-y-0.5 hover:shadow-[0_12px_40px_rgb(15,23,42,0.08)]"
                    >
                        <div class="flex gap-4">
                            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-slate-100 ring-1 ring-slate-100">
                                <img
                                    v-if="line.product_image_url"
                                    :src="line.product_image_url"
                                    :alt="line.product_name"
                                    class="h-full w-full object-cover"
                                />
                                <div v-else class="flex h-full w-full items-center justify-center text-slate-300">
                                    <ImageIcon class="h-7 w-7" />
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-bold text-slate-900">{{ line.product_name }}</h3>
                                    <span
                                        class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                        :class="line.status === 'completed'
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-amber-50 text-amber-700'"
                                    >
                                        {{ line.status === 'completed' ? 'تم التركيب' : 'قيد التركيب' }}
                                    </span>
                                </div>
                                <p class="mt-2 text-xs text-slate-500">
                                    {{ line.completed_by_user?.name || '—' }} · {{ formatWhen(line.completed_at) }}
                                </p>
                                <div class="mt-4 flex flex-wrap items-center gap-2">
                                    <button
                                        v-if="line.installation_photo_url"
                                        type="button"
                                        class="group relative overflow-hidden rounded-xl ring-1 ring-slate-200"
                                        :class="isPhotoReviewer ? 'w-full max-w-xs' : 'h-16 w-16'"
                                        @click="openLightbox(line.installation_photo_url!, `تركيب · ${line.product_name}`)"
                                    >
                                        <img
                                            :src="line.installation_photo_url"
                                            alt="صورة التركيب"
                                            class="object-cover transition duration-300 group-hover:scale-110"
                                            :class="isPhotoReviewer ? 'aspect-[4/3] w-full' : 'h-full w-full'"
                                        />
                                    </button>
                                    <Button
                                        v-if="canUploadPhotos && line.status === 'pending'"
                                        size="sm"
                                        class="h-9 rounded-xl bg-[#2563EB] hover:bg-[#1D4ED8]"
                                        @click="openCompleteDialog(line)"
                                    >
                                        <Camera class="ms-1.5 h-4 w-4" />
                                        رفع صورة التركيب
                                    </Button>
                                    <span v-else-if="line.installation_photo_url" class="text-xs font-medium text-emerald-600">
                                        صورة مرفوعة ✓ — اضغط للتكبير
                                    </span>
                                    <span v-else-if="!canUploadPhotos" class="text-xs font-medium text-amber-600">
                                        بانتظار رفع العامل للصورة
                                    </span>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <!-- PICKUP -->
            <section v-else-if="activeTab === 'pickup'" class="space-y-4">
                <article class="rounded-2xl bg-white p-6 shadow-[0_8px_30px_rgb(15,23,42,0.05)]">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">الاستلام والفك</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ isPhotoReviewer
                                    ? 'راجع صور الاستلام وحالة المنتج قبل التعميد.'
                                    : 'وثّق حالة المنتج عند الاستلام من العميل (كسر / تلف / سليم).' }}
                            </p>
                        </div>
                        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-200">
                            {{ pickupProgress.done }}/{{ installedLines.length }} مستلم
                        </span>
                    </div>
                </article>

                <div
                    v-if="!installedLines.length"
                    class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center shadow-sm"
                >
                    <PackageOpen class="mx-auto h-10 w-10 text-slate-300" />
                    <p class="mt-4 font-semibold text-slate-700">لا توجد منتجات مركّبة بعد</p>
                    <p class="mt-1 text-sm text-slate-400">ارفع صور التركيب أولاً لتظهر هنا مرحلة الاستلام.</p>
                    <Button class="mt-5 rounded-xl bg-[#2563EB]" @click="activeTab = 'installation'">
                        الذهاب للتركيب
                    </Button>
                </div>

                <div v-else class="grid gap-4 md:grid-cols-2">
                    <article
                        v-for="line in installedLines"
                        :key="`pickup-${line.id}`"
                        class="rounded-2xl bg-white p-5 shadow-[0_8px_30px_rgb(15,23,42,0.05)] transition hover:-translate-y-0.5 hover:shadow-[0_12px_40px_rgb(15,23,42,0.08)]"
                    >
                        <div class="flex gap-4">
                            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-slate-100">
                                <img
                                    v-if="line.product_image_url"
                                    :src="line.product_image_url"
                                    :alt="line.product_name"
                                    class="h-full w-full object-cover"
                                />
                                <div v-else class="flex h-full w-full items-center justify-center text-slate-300">
                                    <ImageIcon class="h-7 w-7" />
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-bold text-slate-900">{{ line.product_name }}</h3>
                                    <span
                                        v-if="line.pickup_condition"
                                        class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
                                        :class="conditionLabels[line.pickup_condition].class"
                                    >
                                        {{ conditionLabels[line.pickup_condition].label }}
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700"
                                    >
                                        بانتظار الفك
                                    </span>
                                </div>
                                <p class="mt-2 text-xs text-slate-500">
                                    {{ line.pickup_by_user?.name || '—' }} · {{ formatWhen(line.pickup_at) }}
                                </p>
                                <div class="mt-4 flex flex-wrap items-center gap-2">
                                    <button
                                        v-if="line.pickup_photo_url"
                                        type="button"
                                        class="group relative overflow-hidden rounded-xl ring-1 ring-slate-200"
                                        :class="isPhotoReviewer ? 'w-full max-w-xs' : 'h-16 w-16'"
                                        @click="openLightbox(line.pickup_photo_url!, `فك · ${line.product_name}`)"
                                    >
                                        <img
                                            :src="line.pickup_photo_url"
                                            alt="صورة الفك"
                                            class="object-cover transition duration-300 group-hover:scale-110"
                                            :class="isPhotoReviewer ? 'aspect-[4/3] w-full' : 'h-full w-full'"
                                        />
                                    </button>
                                    <Button
                                        v-if="canUploadPhotos && !line.pickup_photo_url"
                                        size="sm"
                                        class="h-9 rounded-xl bg-[#2563EB] hover:bg-[#1D4ED8]"
                                        @click="openPickupDialog(line)"
                                    >
                                        <Camera class="ms-1.5 h-4 w-4" />
                                        رفع صورة الفك
                                    </Button>
                                    <span v-else-if="line.pickup_photo_url" class="text-xs font-medium text-emerald-600">
                                        صورة مرفوعة ✓ — اضغط للتكبير
                                    </span>
                                    <span v-else-if="!canUploadPhotos" class="text-xs font-medium text-amber-600">
                                        بانتظار رفع العامل للصورة
                                    </span>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <!-- GAMES -->
            <section v-else-if="activeTab === 'games'" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">الألعاب والمعدات</h2>
                        <p class="text-sm text-slate-500">جميع المنتجات المستأجرة لهذه الفعالية</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="line in workOrder.lines"
                        :key="`game-${line.id}`"
                        class="overflow-hidden rounded-2xl bg-white shadow-[0_8px_30px_rgb(15,23,42,0.05)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_16px_40px_rgb(15,23,42,0.1)]"
                    >
                        <div class="relative h-40 bg-slate-100">
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
                        </div>
                        <div class="space-y-3 p-5">
                            <h3 class="font-bold text-slate-900">{{ line.product_name }}</h3>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                    :class="line.status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                                >
                                    تركيب: {{ line.status === 'completed' ? 'مكتمل' : 'قيد الانتظار' }}
                                </span>
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                    :class="line.pickup_photo_url ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-500'"
                                >
                                    استلام: {{ line.pickup_photo_url ? 'مكتمل' : '—' }}
                                </span>
                                <span
                                    v-if="line.pickup_condition"
                                    class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
                                    :class="conditionLabels[line.pickup_condition].class"
                                >
                                    {{ conditionLabels[line.pickup_condition].label }}
                                </span>
                            </div>
                            <div class="flex gap-2 pt-1">
                                <Button
                                    v-if="canUploadPhotos && line.status === 'pending'"
                                    size="sm"
                                    class="h-9 flex-1 rounded-xl bg-[#2563EB]"
                                    @click="openCompleteDialog(line)"
                                >
                                    رفع تركيب
                                </Button>
                                <Button
                                    v-else-if="canUploadPhotos && !line.pickup_photo_url"
                                    size="sm"
                                    class="h-9 flex-1 rounded-xl bg-[#2563EB]"
                                    @click="openPickupDialog(line)"
                                >
                                    رفع استلام
                                </Button>
                                <Button
                                    v-else
                                    size="sm"
                                    variant="outline"
                                    class="h-9 flex-1 rounded-xl"
                                    @click="activeTab = 'timeline'"
                                >
                                    عرض التفاصيل
                                </Button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <!-- NOTES -->
            <section v-else-if="activeTab === 'notes'" class="grid gap-4 lg:grid-cols-5">
                <article class="rounded-2xl bg-white p-5 shadow-[0_8px_30px_rgb(15,23,42,0.05)] lg:col-span-2">
                    <h2 class="text-lg font-bold text-slate-900">إضافة ملاحظة</h2>
                    <p class="mt-1 text-sm text-slate-500">ستظهر في سجل النشاط مع اسمك والتوقيت.</p>
                    <div class="mt-5 space-y-3">
                        <Textarea
                            v-model="noteForm.body"
                            rows="5"
                            class="min-h-32 rounded-2xl border-slate-200"
                            placeholder="اكتب ملاحظة للفريق..."
                        />
                        <p v-if="noteForm.errors.body" class="text-sm text-rose-600">{{ noteForm.errors.body }}</p>
                        <Button
                            class="h-11 w-full rounded-xl bg-[#2563EB]"
                            :disabled="noteForm.processing || !noteForm.body.trim()"
                            @click="submitNote"
                        >
                            {{ noteForm.processing ? 'جاري الحفظ...' : 'نشر الملاحظة' }}
                        </Button>
                    </div>
                </article>

                <article class="rounded-2xl bg-white p-5 shadow-[0_8px_30px_rgb(15,23,42,0.05)] lg:col-span-3">
                    <h2 class="mb-5 text-lg font-bold text-slate-900">سجل النشاط</h2>
                    <div v-if="notes.length" class="space-y-4">
                        <div
                            v-for="note in notes"
                            :key="note.id"
                            class="flex gap-3 rounded-2xl bg-slate-50/80 p-4 transition hover:bg-slate-50"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#2563EB] text-sm font-bold text-white">
                                {{ initials(note.user_name) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <p class="font-semibold text-slate-900">{{ note.user_name }}</p>
                                    <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-500 ring-1 ring-slate-200">
                                        {{ note.user_role || 'مستخدم' }}
                                    </span>
                                    <span class="text-xs text-slate-400 tabular-nums" dir="ltr">{{ formatWhen(note.created_at) }}</span>
                                </div>
                                <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ note.body }}</p>
                                <button
                                    v-if="canDeleteNotes"
                                    type="button"
                                    class="mt-3 inline-flex items-center gap-1 text-xs font-medium text-rose-500 hover:text-rose-600"
                                    :disabled="deletingNoteId === note.id"
                                    @click="deleteNote(note)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                    حذف
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="rounded-2xl border border-dashed border-slate-200 px-6 py-14 text-center">
                        <MessageSquareText class="mx-auto h-8 w-8 text-slate-300" />
                        <p class="mt-3 font-semibold text-slate-600">لا توجد ملاحظات بعد</p>
                        <p class="mt-1 text-sm text-slate-400">ابدأ المحادثة مع الفريق من النموذج الجانبي</p>
                    </div>
                </article>
            </section>

            <!-- TIMELINE -->
            <section v-else-if="activeTab === 'timeline'">
                <article class="rounded-2xl bg-white p-6 shadow-[0_8px_30px_rgb(15,23,42,0.05)] sm:p-8">
                    <h2 class="text-lg font-bold text-slate-900">الجدول الزمني للفعالية</h2>
                    <p class="mt-1 text-sm text-slate-500">تتبع كل مرحلة من الإنشاء حتى اكتمال الاستلام</p>

                    <ol class="relative mt-8 space-y-0 border-s-2 border-slate-100 ms-3">
                        <li
                            v-for="item in timeline"
                            :key="item.key"
                            class="relative ms-8 pb-8 last:pb-0"
                        >
                            <span
                                class="absolute -start-[2.35rem] top-1 flex h-8 w-8 items-center justify-center rounded-full ring-4 ring-white"
                                :class="item.completed ? 'bg-[#2563EB] text-white' : 'bg-slate-200 text-slate-500'"
                            >
                                <CheckCircle2 v-if="item.completed" class="h-4 w-4" />
                                <CircleDot v-else class="h-4 w-4" />
                            </span>
                            <div
                                class="rounded-2xl p-4 transition"
                                :class="item.completed ? 'bg-slate-50' : 'bg-white ring-1 ring-dashed ring-slate-200'"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-bold text-slate-900">{{ item.title }}</p>
                                    <span class="text-xs tabular-nums text-slate-400" dir="ltr">
                                        {{ item.timestamp ? formatWhen(item.timestamp) : '—' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">{{ item.description }}</p>
                                <p v-if="item.user_name" class="mt-2 text-xs font-medium text-slate-400">
                                    بواسطة {{ item.user_name }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </article>
            </section>
        </div>
    </div>

    <!-- Install dialog -->
    <Teleport to="body">
        <div
            v-if="dialogOpen && selectedLine"
            class="fixed inset-0 z-[200] flex items-end justify-center p-0 sm:items-center sm:p-4"
            role="dialog"
            aria-modal="true"
        >
            <button type="button" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[2px]" aria-label="إغلاق" @click="closeCompleteDialog" />
            <div class="relative z-10 flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl" dir="rtl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">رفع صورة التركيب</h2>
                        <p class="mt-1 text-sm text-slate-500">صورة من أرض الواقع بعد التركيب</p>
                    </div>
                    <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-50 text-slate-500" @click="closeCompleteDialog">
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <div class="space-y-4 overflow-y-auto px-5 py-4">
                    <div class="rounded-2xl bg-slate-50 p-3 text-sm font-semibold text-slate-800">{{ selectedLine.product_name }}</div>
                    <input ref="photoInputRef" type="file" accept="image/*" class="hidden" @change="handlePhotoChange" />
                    <button
                        type="button"
                        class="flex min-h-32 w-full flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-[#2563EB]/30 bg-[#2563EB]/5 px-4 py-6 transition hover:border-[#2563EB]/50"
                        @click="photoInputRef?.click()"
                    >
                        <Camera class="h-8 w-8 text-[#2563EB]" />
                        <p class="font-semibold text-slate-800">اضغط لرفع صورة التركيب</p>
                        <p class="text-xs text-slate-400">JPG أو PNG — بحد أقصى 5 ميجابايت</p>
                    </button>
                    <p v-if="photoError || completeForm.errors.installation_photo" class="text-sm text-rose-600">
                        {{ photoError || completeForm.errors.installation_photo }}
                    </p>
                    <img v-if="photoPreview" :src="photoPreview" alt="معاينة" class="max-h-56 w-full rounded-2xl object-cover" />
                </div>
                <div class="grid grid-cols-2 gap-2 border-t border-slate-100 px-5 py-4">
                    <Button variant="outline" class="h-11 rounded-xl" @click="closeCompleteDialog">إلغاء</Button>
                    <Button class="h-11 rounded-xl bg-[#2563EB]" :disabled="completeForm.processing" @click="submitCompletion">
                        {{ completeForm.processing ? 'جاري الإرسال...' : 'إرسال' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Pickup dialog -->
    <Teleport to="body">
        <div
            v-if="pickupDialogOpen && selectedPickupLine"
            class="fixed inset-0 z-[200] flex items-end justify-center p-0 sm:items-center sm:p-4"
            role="dialog"
            aria-modal="true"
        >
            <button type="button" class="absolute inset-0 bg-slate-900/50 backdrop-blur-[2px]" aria-label="إغلاق" @click="closePickupDialog" />
            <div class="relative z-10 flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl" dir="rtl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">رفع صورة الاستلام والفك</h2>
                        <p class="mt-1 text-sm text-slate-500">وثّق حالة المنتج عند الاستلام</p>
                    </div>
                    <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-50 text-slate-500" @click="closePickupDialog">
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <div class="space-y-4 overflow-y-auto px-5 py-4">
                    <div class="rounded-2xl bg-slate-50 p-3 text-sm font-semibold text-slate-800">{{ selectedPickupLine.product_name }}</div>

                    <div class="space-y-2">
                        <Label>حالة المنتج</Label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="(meta, key) in conditionLabels"
                                :key="key"
                                type="button"
                                class="rounded-xl px-3 py-2.5 text-sm font-semibold ring-1 transition"
                                :class="pickupForm.pickup_condition === key
                                    ? meta.class + ' ring-2'
                                    : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50'"
                                @click="pickupForm.pickup_condition = key as PickupCondition"
                            >
                                {{ meta.label }}
                            </button>
                        </div>
                        <p v-if="pickupForm.errors.pickup_condition" class="text-sm text-rose-600">
                            {{ pickupForm.errors.pickup_condition }}
                        </p>
                    </div>

                    <input ref="pickupPhotoInputRef" type="file" accept="image/*" class="hidden" @change="handlePickupPhotoChange" />
                    <button
                        type="button"
                        class="flex min-h-32 w-full flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-[#2563EB]/30 bg-[#2563EB]/5 px-4 py-6 transition hover:border-[#2563EB]/50"
                        @click="pickupPhotoInputRef?.click()"
                    >
                        <Camera class="h-8 w-8 text-[#2563EB]" />
                        <p class="font-semibold text-slate-800">اضغط لرفع صورة الفك</p>
                    </button>
                    <p v-if="pickupPhotoError || pickupForm.errors.pickup_photo" class="text-sm text-rose-600">
                        {{ pickupPhotoError || pickupForm.errors.pickup_photo }}
                    </p>
                    <img v-if="pickupPhotoPreview" :src="pickupPhotoPreview" alt="معاينة" class="max-h-56 w-full rounded-2xl object-cover" />
                </div>
                <div class="grid grid-cols-2 gap-2 border-t border-slate-100 px-5 py-4">
                    <Button variant="outline" class="h-11 rounded-xl" @click="closePickupDialog">إلغاء</Button>
                    <Button class="h-11 rounded-xl bg-[#2563EB]" :disabled="pickupForm.processing" @click="submitPickup">
                        {{ pickupForm.processing ? 'جاري الإرسال...' : 'حفظ' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Lightbox -->
    <Teleport to="body">
        <div
            v-if="lightboxUrl"
            class="fixed inset-0 z-[210] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
            @click.self="closeLightbox"
        >
            <button
                type="button"
                class="absolute end-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20"
                @click="closeLightbox"
            >
                <X class="h-5 w-5" />
            </button>
            <div class="max-h-[85vh] w-full max-w-3xl overflow-hidden rounded-3xl bg-white shadow-2xl">
                <img :src="lightboxUrl" :alt="lightboxLabel" class="max-h-[75vh] w-full object-contain" />
                <p class="border-t border-slate-100 px-4 py-3 text-center text-sm font-medium text-slate-700">{{ lightboxLabel }}</p>
            </div>
        </div>
    </Teleport>
</template>
