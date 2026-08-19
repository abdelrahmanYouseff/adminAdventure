<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    Camera,
    CheckCircle2,
    ExternalLink,
    Images,
    MapPin,
    MessageSquareText,
    Package,
    Phone,
    ShieldAlert,
    X,
} from 'lucide-vue-next';
import { formatDate, formatDateTime } from '@/lib/formatNumber';
import WorkerLanguageSwitcher from '../components/WorkerLanguageSwitcher.vue';
import { useI18n } from '../i18n';

interface ProductLine {
    id: number;
    product_name: string;
    product_image_url: string | null;
    status: 'pending' | 'completed';
    installation_photo_url: string | null;
    completed_at: string | null;
}

interface WorkNote {
    id: number;
    body: string;
    user_name: string;
    is_mine: boolean;
    created_at: string | null;
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
    is_approved: boolean;
    can_replace_photos?: boolean;
    status: 'pending' | 'completed';
    task_type?: 'installation' | 'dismantling' | 'both';
    task_label?: string;
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
const errorMessage = computed(() => page.props.flash?.error as string | undefined);

const selectedProduct = ref<ProductLine | null>(null);
const dialogOpen = ref(false);
const photoPreview = ref<string | null>(null);
const photoError = ref<string | null>(null);
const cameraInputRef = ref<HTMLInputElement | null>(null);
const galleryInputRef = ref<HTMLInputElement | null>(null);
let photoChangeSeq = 0;

const installForm = useForm({
    installation_photo: null as File | null,
});

const noteForm = useForm({
    body: '',
});

const pendingProducts = computed(() =>
    props.installation.products.filter((p) => p.status === 'pending'),
);

const finishedProducts = computed(() =>
    props.installation.products.filter((p) => p.status === 'completed'),
);

const notes = computed(() => props.installation.notes ?? []);

const isDismantling = computed(() => props.installation.task_type === 'dismantling');

const pageTitle = computed(() =>
    isDismantling.value
        ? t('dismantle_title', { name: props.installation.customer_name })
        : t('install_title', { name: props.installation.customer_name }),
);

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
    selectedProduct.value = product;
    installForm.reset();
    installForm.clearErrors();
    photoError.value = null;
    clearPreview();
    resetFileInputs();
    dialogOpen.value = true;
}

const MAX_PHOTO_BYTES = 5 * 1024 * 1024;
const MAX_PHOTO_EDGE = 1920;

function isAllowedImageType(type: string): boolean {
    return ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'].includes(type);
}

async function preparePhotoFile(file: File): Promise<File> {
    if (file.size <= MAX_PHOTO_BYTES && isAllowedImageType(file.type)) {
        return file;
    }

    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, MAX_PHOTO_EDGE / Math.max(bitmap.width, bitmap.height));
        const width = Math.max(1, Math.round(bitmap.width * scale));
        const height = Math.max(1, Math.round(bitmap.height * scale));
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            bitmap.close();
            return file;
        }
        ctx.drawImage(bitmap, 0, 0, width, height);
        bitmap.close();

        const blob = await new Promise<Blob | null>((resolve) => {
            canvas.toBlob(resolve, 'image/jpeg', 0.82);
        });

        if (!blob) return file;

        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', {
            type: 'image/jpeg',
            lastModified: Date.now(),
        });
    } catch {
        return file;
    }
}

function resetFileInputs() {
    if (cameraInputRef.value) cameraInputRef.value.value = '';
    if (galleryInputRef.value) galleryInputRef.value.value = '';
}

function closeCapture() {
    photoChangeSeq += 1;
    dialogOpen.value = false;
    selectedProduct.value = null;
    installForm.reset();
    installForm.clearErrors();
    photoError.value = null;
    clearPreview();
    resetFileInputs();
}

async function handlePhotoChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    const seq = ++photoChangeSeq;
    clearPreview();
    photoError.value = null;
    installForm.clearErrors('installation_photo');

    if (!file) {
        installForm.installation_photo = null;
        return;
    }

    const prepared = await preparePhotoFile(file);
    if (seq !== photoChangeSeq) return;

    photoPreview.value = URL.createObjectURL(prepared);
    installForm.installation_photo = prepared;
}

function submitCapture() {
    if (!selectedProduct.value) return;

    if (!installForm.installation_photo) {
        photoError.value = isDismantling.value ? t('need_dismantle_photo') : t('need_install_photo');
        return;
    }

    installForm.post(`/worker-app/installations/lines/${selectedProduct.value.id}/complete`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => closeCapture(),
    });
}

onBeforeUnmount(() => {
    clearLeaveGuards();
    clearPreview();
});

const allPhotosDone = computed(() =>
    props.installation.products_count > 0
    && props.installation.completed_count === props.installation.products_count,
);

const waitApproval = computed(() => allPhotosDone.value && !props.installation.is_approved);

const waitApprovalBody = computed(() =>
    isDismantling.value ? t('wait_approval_body_dismantling') : t('wait_approval_body'),
);

const canLeaveBody = computed(() =>
    isDismantling.value ? t('can_leave_body_dismantling') : t('can_leave_body'),
);

const leaveDismissKey = `aw-can-leave-${props.installation.id}`;

const showLeaveModal = ref(false);

let pollTimer: ReturnType<typeof setInterval> | null = null;
let removeRouterHook: (() => void) | null = null;
let popStateHandler: ((event: PopStateEvent) => void) | null = null;

function clearLeaveGuards() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }

    removeRouterHook?.();
    removeRouterHook = null;

    if (popStateHandler) {
        window.removeEventListener('popstate', popStateHandler);
        popStateHandler = null;
    }
}

function syncLeaveGuards() {
    clearLeaveGuards();

    if (!waitApproval.value) {
        return;
    }

    pollTimer = setInterval(() => {
        router.reload({
            only: ['installation'],
            preserveScroll: true,
            preserveState: true,
        });
    }, 10000);

    removeRouterHook = router.on('before', (event) => {
        const visit = event.detail.visit;
        const method = visit.method?.toLowerCase() ?? 'get';

        if (method !== 'get') {
            return;
        }

        const url = typeof visit.url === 'string' ? visit.url : visit.url?.href ?? '';
        const installationPath = `/worker-app/installations/${props.installation.id}`;

        if (!url.includes(installationPath)) {
            event.preventDefault();
        }
    });

    history.pushState({ workerStay: props.installation.id }, '');
    popStateHandler = () => {
        if (waitApproval.value) {
            history.pushState({ workerStay: props.installation.id }, '');
        }
    };
    window.addEventListener('popstate', popStateHandler);
}

watch(waitApproval, () => syncLeaveGuards(), { immediate: true });

watch(
    () => props.installation.is_approved,
    (approved) => {
        if (allPhotosDone.value && approved && !localStorage.getItem(leaveDismissKey)) {
            showLeaveModal.value = true;
        }
    },
    { immediate: true },
);

function confirmLeave() {
    localStorage.setItem(leaveDismissKey, '1');
    showLeaveModal.value = false;
}

function attemptBack() {
    // زر الرجوع معطّل أثناء انتظار التعميد — الشريط السفلي يوضح السبب.
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
            <button
                v-if="waitApproval"
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-amber-200 bg-amber-50 text-amber-600 shadow-sm"
                :aria-label="t('back')"
                @click="attemptBack"
            >
                <ArrowRight v-if="isRtl" class="h-5 w-5" />
                <ArrowLeft v-else class="h-5 w-5" />
            </button>
            <Link
                v-else
                href="/worker-app"
                class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm"
            >
                <ArrowRight v-if="isRtl" class="h-5 w-5" />
                <ArrowLeft v-else class="h-5 w-5" />
            </Link>
            <div class="min-w-0 flex-1">
                <p class="text-xs text-slate-500">
                    {{ isDismantling ? t('dismantle_details') : t('install_details') }}
                </p>
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
                v-if="errorMessage"
                class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"
            >
                {{ errorMessage }}
            </div>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-3">
                        <div>
                            <p class="text-sm text-slate-500">
                                {{ isDismantling ? t('dismantle_date') : t('install_date') }}
                            </p>
                            <p class="mt-1 font-semibold text-slate-900">{{ formatInstallDate(installation.installation_date) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-slate-500">{{ t('activity_time') }}</p>
                            <p class="mt-1 font-semibold text-slate-900" dir="ltr">{{ formatActivityTime(installation.activity_time) }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <span
                            class="rounded-full px-2.5 py-1 text-[11px] font-bold ring-1"
                            :class="isDismantling ? 'bg-orange-50 text-orange-700 ring-orange-200' : 'bg-sky-50 text-sky-700 ring-sky-100'"
                        >
                            {{ isDismantling ? t('task_dismantle') : t('task_install') }}
                        </span>
                        <span class="rounded-full bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-700 ring-1 ring-slate-200">
                            {{ isDismantling
                                ? t('dismantle_progress', { done: installation.completed_count, total: installation.products_count })
                                : t('install_progress', { done: installation.completed_count, total: installation.products_count }) }}
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
                        :placeholder="isDismantling ? t('note_placeholder_dismantle') : t('note_placeholder')"
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
                <h2 class="px-1 text-sm font-semibold text-slate-700">
                    {{ isDismantling ? t('products_to_dismantle') : t('products_to_install') }}
                </h2>

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
                            {{ isDismantling ? t('photo_after_dismantle') : t('photo_after_install') }}
                        </button>
                    </div>
                </article>

                <div
                    v-if="!pendingProducts.length"
                    class="rounded-3xl border border-dashed border-emerald-200 bg-emerald-50/60 px-5 py-8 text-center"
                >
                    <CheckCircle2 class="mx-auto h-8 w-8 text-emerald-500" />
                    <p class="mt-3 text-sm font-semibold text-emerald-800">
                        {{ isDismantling ? t('all_dismantled') : t('all_installed') }}
                    </p>
                </div>
            </section>

            <section v-if="finishedProducts.length" class="space-y-3 pb-4">
                <h2 class="px-1 text-sm font-semibold text-slate-700">
                    {{ isDismantling ? t('completed_dismantles') : t('completed_installs') }}
                </h2>
                <article
                    v-for="product in finishedProducts"
                    :key="`done-${product.id}`"
                    class="overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-sm"
                >
                    <div class="aspect-[16/10] bg-slate-100">
                        <img
                            v-if="product.installation_photo_url"
                            :src="product.installation_photo_url"
                            :alt="t('install_photo_alt')"
                            class="h-full w-full object-cover"
                        />
                    </div>
                    <div class="space-y-3 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-900">{{ product.product_name }}</p>
                                <p v-if="product.completed_at" class="mt-0.5 text-xs text-slate-500">
                                    {{ formatDateTime(product.completed_at) }}
                                </p>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-100">
                                <CheckCircle2 class="h-3.5 w-3.5" />
                                {{ t('completed') }}
                            </span>
                        </div>
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
                                {{ isDismantling ? t('capture_dismantle_title') : t('capture_install_title') }}
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
                        <input
                            ref="cameraInputRef"
                            type="file"
                            accept="image/*"
                            capture="environment"
                            class="hidden"
                            @change="handlePhotoChange"
                        />
                        <input
                            ref="galleryInputRef"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="handlePhotoChange"
                        />
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                class="flex flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-sky-200 bg-sky-50/50 px-3 py-6 text-sky-700"
                                @click="cameraInputRef?.click()"
                            >
                                <Camera class="h-7 w-7" />
                                <p class="text-center text-sm font-semibold">{{ t('open_camera') }}</p>
                            </button>
                            <button
                                type="button"
                                class="flex flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-emerald-200 bg-emerald-50/50 px-3 py-6 text-emerald-700"
                                @click="galleryInputRef?.click()"
                            >
                                <Images class="h-7 w-7" />
                                <p class="text-center text-sm font-semibold">{{ t('choose_gallery') }}</p>
                            </button>
                        </div>

                        <img
                            v-if="photoPreview"
                            :src="photoPreview"
                            :alt="t('preview')"
                            class="max-h-64 w-full rounded-2xl object-cover"
                        />

                        <p
                            v-if="photoError || installForm.errors.installation_photo"
                            class="text-sm text-rose-600"
                        >
                            {{ photoError || installForm.errors.installation_photo }}
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
                            class="h-12 rounded-2xl bg-sky-600 text-sm font-semibold text-white disabled:opacity-60"
                            :disabled="installForm.processing"
                            @click="submitCapture"
                        >
                            {{ installForm.processing ? t('saving') : t('save_record') }}
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
                        <p class="mt-0.5 text-sm leading-relaxed text-amber-800">{{ waitApprovalBody }}</p>
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
                        <p class="text-sm leading-relaxed text-slate-600">{{ canLeaveBody }}</p>
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
