<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    Camera,
    CheckCircle2,
    ExternalLink,
    MapPin,
    MessageSquareText,
    Package,
    Phone,
    ShieldAlert,
    Timer,
    Truck,
    X,
} from 'lucide-vue-next';
import { formatDate, formatDateTime } from '@/lib/formatNumber';
import WorkerLanguageSwitcher from '../components/WorkerLanguageSwitcher.vue';
import { useI18n } from '../i18n';
import type { MessageKey } from '../i18n/messages';

type PickupCondition = 'excellent' | 'good' | 'damaged' | 'broken';
type CaptureMode = 'install' | 'pickup';

interface ProductLine {
    id: number;
    product_name: string;
    product_image_url: string | null;
    status: 'pending' | 'completed';
    installation_photo_url: string | null;
    completed_at: string | null;
    pickup_photo_url: string | null;
    pickup_at: string | null;
    pickup_condition: PickupCondition | null;
}

interface WorkNote {
    id: number;
    body: string;
    user_name: string;
    is_mine: boolean;
    created_at: string | null;
}

interface LatePenalty {
    is_late: boolean;
    delay_minutes: number;
    delay_hours: number;
    delay_remainder_minutes: number;
    scheduled_at: string | null;
    installed_at: string | null;
}

interface Installation {
    id: number;
    customer_name: string;
    customer_phone: string | null;
    map_url: string | null;
    installation_date: string | null;
    activity_time: string | null;
    products_count: number;
    pending_count: number;
    completed_count: number;
    pending_pickup_count: number;
    is_approved: boolean;
    late_penalty: LatePenalty | null;
    status: 'pending' | 'completed';
    products: ProductLine[];
    notes: WorkNote[];
}

interface Props {
    installation: Installation;
}

const props = defineProps<Props>();
const page = usePage();
const { t, isRtl, dir } = useI18n();
const successMessage = computed(() => page.props.flash?.success as string | undefined);

const conditionKeys: { key: PickupCondition; labelKey: MessageKey }[] = [
    { key: 'excellent', labelKey: 'condition_excellent' },
    { key: 'good', labelKey: 'condition_good' },
    { key: 'damaged', labelKey: 'condition_damaged' },
    { key: 'broken', labelKey: 'condition_broken' },
];

const selectedProduct = ref<ProductLine | null>(null);
const captureMode = ref<CaptureMode>('install');
const dialogOpen = ref(false);
const photoPreview = ref<string | null>(null);
const photoError = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

const installForm = useForm({
    installation_photo: null as File | null,
});

const pickupForm = useForm({
    pickup_photo: null as File | null,
    pickup_condition: 'good' as PickupCondition,
});

const noteForm = useForm({
    body: '',
});

const pendingProducts = computed(() =>
    props.installation.products.filter((p) => p.status === 'pending'),
);

const awaitingPickupProducts = computed(() =>
    props.installation.products.filter((p) => p.status === 'completed' && !p.pickup_photo_url),
);

const finishedProducts = computed(() =>
    props.installation.products.filter((p) => p.status === 'completed' && !!p.pickup_photo_url),
);

const notes = computed(() => props.installation.notes ?? []);

const activeForm = computed(() => (captureMode.value === 'install' ? installForm : pickupForm));

const pageTitle = computed(() =>
    t('install_title', { name: props.installation.customer_name }),
);

function conditionLabel(key: PickupCondition): string {
    const map: Record<PickupCondition, MessageKey> = {
        excellent: 'condition_excellent',
        good: 'condition_good',
        damaged: 'condition_damaged',
        broken: 'condition_broken',
    };

    return t(map[key]);
}

function formatInstallDate(date: string | null): string {
    if (!date) return t('date_unset');
    return formatDate(date);
}

function formatActivityTime(time: string | null): string {
    if (!time) return t('date_unset');
    const [hourStr, minuteStr = '00'] = time.split(':');
    let hour = Number(hourStr);
    if (Number.isNaN(hour)) return time;
    const period = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;
    return t('activity_time_value', {
        time: `${hour}:${minuteStr.padStart(2, '0')}`,
        period,
    });
}

function formatClock(iso: string | null): string {
    if (!iso) return '—';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '—';
    let hour = date.getHours();
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const period = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;
    return `${hour}:${minutes} ${period}`;
}

function formatDelay(penalty: LatePenalty): string {
    if (penalty.delay_hours > 0 && penalty.delay_remainder_minutes > 0) {
        return t('delay_hours_minutes', {
            hours: penalty.delay_hours,
            minutes: penalty.delay_remainder_minutes,
        });
    }
    if (penalty.delay_hours > 0) {
        return t('delay_hours_only', { hours: penalty.delay_hours });
    }
    return t('delay_minutes_only', { minutes: penalty.delay_minutes });
}

const latePenalty = computed(() =>
    props.installation.late_penalty?.is_late ? props.installation.late_penalty : null,
);

function submitNote() {
    const body = noteForm.body.trim();
    if (!body) {
        noteForm.setError('body', t('note_required'));
        return;
    }

    noteForm.body = body;
    noteForm.post(`/worker-app/installations/${props.installation.id}/notes`, {
        preserveScroll: true,
        onSuccess: () => {
            noteForm.reset();
            noteForm.clearErrors();
        },
    });
}

function clearPreview() {
    if (photoPreview.value) URL.revokeObjectURL(photoPreview.value);
    photoPreview.value = null;
}

function openInstallCapture(product: ProductLine) {
    captureMode.value = 'install';
    selectedProduct.value = product;
    installForm.reset();
    installForm.clearErrors();
    photoError.value = null;
    clearPreview();
    dialogOpen.value = true;
}

function openPickupCapture(product: ProductLine) {
    captureMode.value = 'pickup';
    selectedProduct.value = product;
    pickupForm.reset();
    pickupForm.pickup_condition = 'good';
    pickupForm.clearErrors();
    photoError.value = null;
    clearPreview();
    dialogOpen.value = true;
}

function closeCapture() {
    dialogOpen.value = false;
    selectedProduct.value = null;
    installForm.reset();
    installForm.clearErrors();
    pickupForm.reset();
    pickupForm.pickup_condition = 'good';
    pickupForm.clearErrors();
    photoError.value = null;
    clearPreview();
    if (fileInputRef.value) fileInputRef.value.value = '';
}

function handlePhotoChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    clearPreview();
    photoPreview.value = file ? URL.createObjectURL(file) : null;
    photoError.value = null;

    if (captureMode.value === 'install') {
        installForm.installation_photo = file;
        installForm.clearErrors('installation_photo');
    } else {
        pickupForm.pickup_photo = file;
        pickupForm.clearErrors('pickup_photo');
    }
}

function submitCapture() {
    if (!selectedProduct.value) return;

    if (captureMode.value === 'install') {
        if (!installForm.installation_photo) {
            photoError.value = t('need_install_photo');
            return;
        }

        installForm.post(`/worker-app/installations/lines/${selectedProduct.value.id}/complete`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => closeCapture(),
        });
        return;
    }

    if (!pickupForm.pickup_photo) {
        photoError.value = t('need_pickup_photo');
        return;
    }

    pickupForm.post(`/worker-app/installations/lines/${selectedProduct.value.id}/pickup`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => closeCapture(),
    });
}

onBeforeUnmount(() => {
    clearPreview();
});

const allPhotosDone = computed(() =>
    props.installation.products_count > 0
    && props.installation.completed_count === props.installation.products_count
    && props.installation.pending_pickup_count === 0,
);

const waitApproval = computed(() => allPhotosDone.value && !props.installation.is_approved);

const leaveDismissKey = `aw-can-leave-${props.installation.id}`;

const showLeaveModal = ref(
    allPhotosDone.value
    && props.installation.is_approved
    && !localStorage.getItem(leaveDismissKey),
);

function confirmLeave() {
    localStorage.setItem(leaveDismissKey, '1');
    showLeaveModal.value = false;
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="relative flex min-h-dvh flex-col bg-[#f5f7fb] px-5 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-[max(1.25rem,env(safe-area-inset-top))]">
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -left-20 top-16 h-56 w-56 rounded-full bg-sky-200/40 blur-3xl" />
            <div class="absolute -right-10 bottom-10 h-52 w-52 rounded-full bg-emerald-100/50 blur-3xl" />
        </div>

        <header class="relative mx-auto flex w-full max-w-md items-center gap-3">
            <Link
                href="/worker-app"
                class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm"
            >
                <ArrowRight v-if="isRtl" class="h-5 w-5" />
                <ArrowLeft v-else class="h-5 w-5" />
            </Link>
            <div class="min-w-0 flex-1">
                <p class="text-xs text-slate-500">{{ t('install_details') }}</p>
                <h1 class="truncate text-lg font-bold text-slate-900">{{ installation.customer_name }}</h1>
            </div>
            <WorkerLanguageSwitcher />
        </header>

        <main
            class="relative mx-auto mt-5 flex w-full max-w-md flex-1 flex-col gap-4"
            :class="waitApproval ? 'pb-36' : ''"
        >
            <div
                v-if="successMessage"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
            >
                {{ successMessage }}
            </div>

            <div
                v-if="latePenalty"
                class="rounded-3xl border border-rose-200 bg-gradient-to-br from-rose-50 to-orange-50 p-4 shadow-sm"
            >
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-rose-500 text-white shadow-md shadow-rose-500/30">
                        <Timer class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-bold text-rose-900">{{ t('late_penalty_title') }}</p>
                        <p class="mt-1 text-sm leading-relaxed text-rose-800">
                            {{ t('late_penalty_body', {
                                scheduled: formatClock(latePenalty.scheduled_at),
                                installed: formatClock(latePenalty.installed_at),
                                delay: formatDelay(latePenalty),
                            }) }}
                        </p>
                    </div>
                </div>
            </div>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-3">
                        <div>
                            <p class="text-sm text-slate-500">{{ t('install_date') }}</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ formatInstallDate(installation.installation_date) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">{{ t('activity_time') }}</p>
                            <p class="mt-1 font-semibold text-slate-900" dir="ltr">{{ formatActivityTime(installation.activity_time) }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <span class="rounded-full bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-700 ring-1 ring-sky-100">
                            {{ t('install_progress', { done: installation.completed_count, total: installation.products_count }) }}
                        </span>
                        <span class="rounded-full bg-violet-50 px-2.5 py-1 text-[11px] font-semibold text-violet-700 ring-1 ring-violet-100">
                            {{ t('pickup_progress', {
                                done: installation.products_count - installation.pending_pickup_count,
                                total: installation.products_count,
                            }) }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 space-y-3 text-sm">
                    <a
                        v-if="installation.customer_phone"
                        :href="`tel:${installation.customer_phone}`"
                        class="flex items-center gap-2 font-medium text-slate-700"
                        dir="ltr"
                    >
                        <Phone class="h-4 w-4 text-slate-400" />
                        {{ installation.customer_phone }}
                    </a>
                    <a
                        v-if="installation.map_url"
                        :href="installation.map_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 font-semibold text-sky-600"
                    >
                        <MapPin class="h-4 w-4" />
                        {{ t('map_location') }}
                        <ExternalLink class="h-3.5 w-3.5 opacity-70" />
                    </a>
                </div>
            </section>

            <section class="space-y-3">
                <h2 class="flex items-center gap-2 px-1 text-sm font-semibold text-slate-700">
                    <MessageSquareText class="h-4 w-4 text-slate-400" />
                    {{ t('notes') }}
                </h2>

                <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                    <label for="worker-note" class="sr-only">{{ t('write_note') }}</label>
                    <textarea
                        id="worker-note"
                        v-model="noteForm.body"
                        rows="3"
                        maxlength="2000"
                        :placeholder="t('note_placeholder')"
                        class="w-full resize-none rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none ring-sky-400/30 placeholder:text-slate-400 focus:border-sky-400 focus:bg-white focus:ring-2"
                        :disabled="noteForm.processing"
                    />
                    <p v-if="noteForm.errors.body" class="mt-2 text-sm text-rose-600">
                        {{ noteForm.errors.body }}
                    </p>
                    <button
                        type="button"
                        class="mt-3 inline-flex h-11 w-full items-center justify-center rounded-2xl bg-slate-900 text-sm font-semibold text-white transition active:scale-[0.99] hover:bg-slate-800 disabled:opacity-60"
                        :disabled="noteForm.processing || !noteForm.body.trim()"
                        @click="submitNote"
                    >
                        {{ noteForm.processing ? t('saving') : t('save_note') }}
                    </button>
                </div>

                <div v-if="notes.length" class="space-y-2">
                    <article
                        v-for="note in notes"
                        :key="note.id"
                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm"
                    >
                        <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-800">{{ note.body }}</p>
                        <p class="mt-2 text-[11px] text-slate-400">
                            {{ note.is_mine ? t('you') : note.user_name }}
                            <span v-if="note.created_at"> · {{ formatDateTime(note.created_at) }}</span>
                        </p>
                    </article>
                </div>
                <p v-else class="px-1 text-xs text-slate-400">{{ t('no_notes') }}</p>
            </section>

            <section class="space-y-3">
                <h2 class="px-1 text-sm font-semibold text-slate-700">{{ t('products_to_install') }}</h2>

                <article
                    v-for="product in pendingProducts"
                    :key="product.id"
                    class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="aspect-[16/10] bg-slate-100">
                        <img
                            v-if="product.product_image_url"
                            :src="product.product_image_url"
                            :alt="product.product_name"
                            class="h-full w-full object-cover"
                        />
                        <div v-else class="flex h-full w-full items-center justify-center text-slate-300">
                            <Package class="h-10 w-10" />
                        </div>
                    </div>
                    <div class="space-y-3 p-4">
                        <h3 class="font-bold text-slate-900">{{ product.product_name }}</h3>
                        <button
                            type="button"
                            class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-sky-600 text-sm font-semibold text-white shadow-sm transition active:scale-[0.99] hover:bg-sky-700"
                            @click="openInstallCapture(product)"
                        >
                            <Camera class="h-5 w-5" />
                            {{ t('photo_after_install') }}
                        </button>
                    </div>
                </article>

                <div
                    v-if="!pendingProducts.length"
                    class="rounded-3xl border border-dashed border-emerald-200 bg-emerald-50/60 px-5 py-8 text-center"
                >
                    <CheckCircle2 class="mx-auto h-8 w-8 text-emerald-500" />
                    <p class="mt-3 text-sm font-semibold text-emerald-800">{{ t('all_installed') }}</p>
                </div>
            </section>

            <section v-if="awaitingPickupProducts.length" class="space-y-3">
                <h2 class="px-1 text-sm font-semibold text-slate-700">{{ t('pickup_before_dismantle') }}</h2>
                <p class="px-1 text-xs text-slate-500">{{ t('pickup_before_hint') }}</p>

                <article
                    v-for="product in awaitingPickupProducts"
                    :key="`pickup-${product.id}`"
                    class="overflow-hidden rounded-3xl border border-violet-100 bg-white shadow-sm"
                >
                    <div class="grid grid-cols-2 gap-0">
                        <div class="aspect-square bg-slate-100">
                            <img
                                v-if="product.product_image_url"
                                :src="product.product_image_url"
                                :alt="product.product_name"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex h-full items-center justify-center text-slate-300">
                                <Package class="h-8 w-8" />
                            </div>
                        </div>
                        <div class="aspect-square bg-slate-100">
                            <img
                                v-if="product.installation_photo_url"
                                :src="product.installation_photo_url"
                                :alt="t('install_photo_alt')"
                                class="h-full w-full object-cover"
                            />
                        </div>
                    </div>
                    <div class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-900">{{ product.product_name }}</p>
                                <p class="mt-0.5 text-xs text-emerald-600">{{ t('installed_ok') }}</p>
                            </div>
                            <Truck class="h-5 w-5 shrink-0 text-violet-500" />
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-violet-600 text-sm font-semibold text-white shadow-sm transition active:scale-[0.99] hover:bg-violet-700"
                            @click="openPickupCapture(product)"
                        >
                            <Camera class="h-5 w-5" />
                            {{ t('photo_on_pickup') }}
                        </button>
                    </div>
                </article>
            </section>

            <section v-if="finishedProducts.length" class="space-y-3 pb-4">
                <h2 class="px-1 text-sm font-semibold text-slate-700">{{ t('completed_both') }}</h2>
                <article
                    v-for="product in finishedProducts"
                    :key="`done-${product.id}`"
                    class="overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-sm"
                >
                    <div class="grid grid-cols-2 gap-0">
                        <div class="relative aspect-square bg-slate-100">
                            <img
                                v-if="product.installation_photo_url"
                                :src="product.installation_photo_url"
                                :alt="t('install_photo_alt')"
                                class="h-full w-full object-cover"
                            />
                            <span class="absolute bottom-2 start-2 rounded-md bg-black/55 px-1.5 py-0.5 text-[10px] font-medium text-white">
                                {{ t('install') }}
                            </span>
                        </div>
                        <div class="relative aspect-square bg-slate-100">
                            <img
                                v-if="product.pickup_photo_url"
                                :src="product.pickup_photo_url"
                                :alt="t('pickup_photo_alt')"
                                class="h-full w-full object-cover"
                            />
                            <span class="absolute bottom-2 start-2 rounded-md bg-black/55 px-1.5 py-0.5 text-[10px] font-medium text-white">
                                {{ t('pickup') }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2 p-4">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-900">{{ product.product_name }}</p>
                            <p v-if="product.pickup_condition" class="mt-0.5 text-xs text-slate-500">
                                {{ t('condition') }}: {{ conditionLabel(product.pickup_condition) }}
                                <span v-if="product.pickup_at"> — {{ formatDateTime(product.pickup_at) }}</span>
                            </p>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100">
                            <CheckCircle2 class="h-3.5 w-3.5" />
                            {{ t('completed') }}
                        </span>
                    </div>
                </article>
            </section>
        </main>

        <Teleport to="body">
            <div
                v-if="dialogOpen && selectedProduct"
                class="fixed inset-0 z-[200] flex items-end justify-center sm:items-center sm:p-4"
                role="dialog"
                aria-modal="true"
            >
                <button type="button" class="absolute inset-0 bg-slate-900/50" :aria-label="t('close')" @click="closeCapture" />
                <div
                    class="relative z-10 flex max-h-[92vh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl"
                    :dir="dir"
                >
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div class="min-w-0">
                            <h2 class="text-lg font-bold text-slate-900">
                                {{ captureMode === 'install' ? t('capture_install_title') : t('capture_pickup_title') }}
                            </h2>
                            <p class="mt-1 truncate text-sm text-slate-500">{{ selectedProduct.product_name }}</p>
                        </div>
                        <button
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-50 text-slate-500"
                            @click="closeCapture"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="space-y-4 overflow-y-auto px-5 py-4">
                        <div v-if="captureMode === 'pickup'" class="space-y-2">
                            <p class="text-sm font-medium text-slate-700">{{ t('product_condition') }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="option in conditionKeys"
                                    :key="option.key"
                                    type="button"
                                    class="h-11 rounded-xl text-sm font-semibold ring-1 transition"
                                    :class="pickupForm.pickup_condition === option.key
                                        ? 'bg-violet-600 text-white ring-violet-600'
                                        : 'bg-slate-50 text-slate-700 ring-slate-200'"
                                    @click="pickupForm.pickup_condition = option.key"
                                >
                                    {{ t(option.labelKey) }}
                                </button>
                            </div>
                            <p v-if="pickupForm.errors.pickup_condition" class="text-sm text-rose-600">
                                {{ pickupForm.errors.pickup_condition }}
                            </p>
                        </div>

                        <input
                            ref="fileInputRef"
                            type="file"
                            accept="image/*"
                            capture="environment"
                            class="hidden"
                            @change="handlePhotoChange"
                        />
                        <button
                            type="button"
                            class="flex w-full flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed px-4 py-8"
                            :class="captureMode === 'install'
                                ? 'border-sky-200 bg-sky-50/50 text-sky-700'
                                : 'border-violet-200 bg-violet-50/50 text-violet-700'"
                            @click="fileInputRef?.click()"
                        >
                            <Camera class="h-8 w-8" />
                            <p class="font-semibold">
                                {{ captureMode === 'install' ? t('open_camera_install') : t('open_camera_pickup') }}
                            </p>
                            <p class="text-xs opacity-80">{{ t('or_gallery') }}</p>
                        </button>

                        <img
                            v-if="photoPreview"
                            :src="photoPreview"
                            :alt="t('preview')"
                            class="max-h-64 w-full rounded-2xl object-cover"
                        />

                        <p
                            v-if="photoError || installForm.errors.installation_photo || pickupForm.errors.pickup_photo"
                            class="text-sm text-rose-600"
                        >
                            {{ photoError || installForm.errors.installation_photo || pickupForm.errors.pickup_photo }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-2 border-t border-slate-100 px-5 py-4">
                        <button
                            type="button"
                            class="h-12 rounded-2xl border border-slate-200 bg-white text-sm font-semibold text-slate-700"
                            @click="closeCapture"
                        >
                            {{ t('cancel') }}
                        </button>
                        <button
                            type="button"
                            class="h-12 rounded-2xl text-sm font-semibold text-white disabled:opacity-60"
                            :class="captureMode === 'install' ? 'bg-sky-600' : 'bg-violet-600'"
                            :disabled="activeForm.processing"
                            @click="submitCapture"
                        >
                            {{ activeForm.processing ? t('saving') : t('save_record') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="waitApproval"
                class="fixed inset-x-0 bottom-0 z-[150] px-4 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
                :dir="dir"
            >
                <div class="mx-auto flex max-w-md items-start gap-3 rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 p-4 shadow-2xl shadow-amber-300/50">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-500 text-white shadow-md shadow-amber-500/40">
                        <ShieldAlert class="h-6 w-6 animate-pulse" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-bold text-amber-900">{{ t('wait_approval_title') }}</p>
                        <p class="mt-0.5 text-sm leading-relaxed text-amber-800">{{ t('wait_approval_body') }}</p>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="showLeaveModal"
                class="fixed inset-0 z-[220] flex items-center justify-center p-5"
                role="dialog"
                aria-modal="true"
                :dir="dir"
            >
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" />
                <div class="relative z-10 w-full max-w-sm overflow-hidden rounded-3xl bg-white text-center shadow-2xl">
                    <div class="bg-gradient-to-b from-emerald-500 to-teal-600 px-6 pb-8 pt-9 text-white">
                        <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-white/20 ring-8 ring-white/10">
                            <BadgeCheck class="h-11 w-11" />
                        </span>
                        <h2 class="mt-4 text-2xl font-extrabold tracking-tight">{{ t('can_leave_title') }}</h2>
                    </div>
                    <div class="px-6 py-6">
                        <p class="text-sm leading-relaxed text-slate-600">{{ t('can_leave_body') }}</p>
                        <button
                            type="button"
                            class="mt-5 inline-flex h-12 w-full items-center justify-center rounded-2xl bg-emerald-600 text-sm font-bold text-white shadow-lg shadow-emerald-600/30 transition hover:bg-emerald-700 active:scale-[0.98]"
                            @click="confirmLeave"
                        >
                            {{ t('confirm_ok') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
