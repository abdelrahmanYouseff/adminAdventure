<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage, Deferred } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Label } from '@/components/ui/label';
import {
    HardHat,
    Calendar,
    User,
    Camera,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    Navigation,
    ImageIcon,
    X,
    ArrowRight,
} from 'lucide-vue-next';
import { formatDate, formatDateTime, formatInteger } from '@/lib/formatNumber';

interface CompletedByUser {
    id: number;
    name: string;
    customer_name?: string;
}

interface WorkerOrderItem {
    id: number;
    product_name: string;
    product_image_url: string | null;
    customer_name: string;
    customer_address: string | null;
    installation_date: string | null;
    status: 'pending' | 'completed';
    installation_photo_url: string | null;
    completed_at: string | null;
    completed_by_user?: CompletedByUser | null;
    order?: {
        id: number;
        order_number: string;
        location_slug: string;
        address: string | null;
    };
}

interface Props {
    workerOrders?: {
        data: WorkerOrderItem[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    stats?: {
        pending: number;
        completed: number;
        total: number;
    };
    filters: {
        status: string;
    };
}

const props = defineProps<Props>();

const workerOrders = computed(() => props.workerOrders ?? {
    data: [],
    current_page: 1,
    last_page: 1,
    total: 0,
    from: null,
    to: null,
});

const stats = computed(() => props.stats ?? {
    pending: 0,
    completed: 0,
    total: 0,
});

defineOptions({ layout: AppLayout });

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);
const statusFilter = ref(props.filters.status || 'pending');
const selectedOrder = ref<WorkerOrderItem | null>(null);
const photoPreview = ref<string | null>(null);
const dialogOpen = ref(false);
const photoInputRef = ref<HTMLInputElement | null>(null);
const photoError = ref<string | null>(null);
const mobileListVisible = ref(false);

const mobileListTitle = computed(() => {
    if (statusFilter.value === 'completed') {
        return 'مرفوعة للمراجعة';
    }

    if (statusFilter.value === 'all') {
        return 'كل التركيبات';
    }

    return 'قيد التركيب';
});

const isCompletedView = computed(() => statusFilter.value === 'completed');

const completeForm = useForm({
    installation_photo: null as File | null,
});

function applyFilters(pageNumber = 1) {
    router.get('/worker-orders', {
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        page: pageNumber > 1 ? pageNumber : undefined,
    }, { preserveState: false, preserveScroll: true });
}

function setStatusFilter(status: string) {
    statusFilter.value = status;
    applyFilters(1);
}

function isMobileView(): boolean {
    return typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches;
}

function openMobileList(status: 'pending' | 'completed' | 'all') {
    mobileListVisible.value = true;

    if (statusFilter.value !== status) {
        setStatusFilter(status);
    }
}

function handleStatCardClick(status: 'pending' | 'completed' | 'all') {
    if (isMobileView()) {
        openMobileList(status);
        return;
    }

    setStatusFilter(status);
}

function closeMobileList() {
    mobileListVisible.value = false;
}

function goToPage(pageNumber: number) {
    if (pageNumber >= 1 && pageNumber <= workerOrders.value.last_page) {
        applyFilters(pageNumber);
    }
}

function openCompleteDialog(order: WorkerOrderItem) {
    selectedOrder.value = order;
    completeForm.reset();
    completeForm.clearErrors();
    photoError.value = null;
    photoPreview.value = null;
    if (photoInputRef.value) {
        photoInputRef.value.value = '';
    }
    dialogOpen.value = true;
}

function closeCompleteDialog() {
    dialogOpen.value = false;
    selectedOrder.value = null;
    completeForm.reset();
    completeForm.clearErrors();
    photoError.value = null;
    if (photoPreview.value) {
        URL.revokeObjectURL(photoPreview.value);
    }
    photoPreview.value = null;
    if (photoInputRef.value) {
        photoInputRef.value.value = '';
    }
}

function handlePhotoChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    if (photoPreview.value) {
        URL.revokeObjectURL(photoPreview.value);
    }

    completeForm.installation_photo = file;
    photoPreview.value = file ? URL.createObjectURL(file) : null;
    photoError.value = null;
    completeForm.clearErrors('installation_photo');
}

function submitCompletion() {
    if (!selectedOrder.value) {
        return;
    }

    if (!completeForm.installation_photo) {
        photoError.value = 'يجب إرفاق صورة للتركيب من أرض الواقع قبل الإرسال.';
        return;
    }

    completeForm.post(`/worker-orders/${selectedOrder.value.id}/complete`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            closeCompleteDialog();
            statusFilter.value = 'completed';
        },
    });
}

function formatEventDate(date: string | null): string {
    if (!date) {
        return 'غير محدد';
    }

    return formatDate(date);
}

function formatCompletedAt(date: string | null): string {
    if (!date) {
        return '—';
    }

    return formatDateTime(date);
}

function completedByName(item: WorkerOrderItem): string {
    const user = item.completed_by_user;

    if (!user) {
        return '—';
    }

    return user.name || user.customer_name || '—';
}

function customerAddress(item: WorkerOrderItem): string | null {
    const address = item.customer_address?.trim() || item.order?.address?.trim() || null;

    return address || null;
}

function locationMapsUrl(address: string | null): string | null {
    if (!address?.trim()) {
        return null;
    }

    const trimmed = address.trim();
    const coordMatch = trimmed.match(/^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/);

    if (coordMatch) {
        return `https://www.google.com/maps?q=${coordMatch[1]},${coordMatch[2]}`;
    }

    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(trimmed)}`;
}

function locationLink(item: WorkerOrderItem): string | null {
    const address = customerAddress(item);

    if (item.order?.location_slug) {
        return route('store.order.location', item.order.location_slug);
    }

    return locationMapsUrl(address);
}

watch(dialogOpen, (isOpen) => {
    if (!isOpen) {
        selectedOrder.value = null;
        completeForm.reset();
        completeForm.clearErrors();
        photoError.value = null;
        if (photoPreview.value) {
            URL.revokeObjectURL(photoPreview.value);
        }
        photoPreview.value = null;
        if (photoInputRef.value) {
            photoInputRef.value.value = '';
        }
    }
});

watch(
    () => props.filters.status,
    (status) => {
        statusFilter.value = status || 'pending';
    },
);
</script>

<template>
    <Head title="طلبات العمال" />

    <Deferred :data="['workerOrders', 'stats']">
        <template #fallback>
            <div class="flex min-h-[50vh] items-center justify-center p-6 text-sm text-muted-foreground">
                جاري تحميل طلبات العمال...
            </div>
        </template>

    <div class="flex min-w-0 flex-1 flex-col gap-4 overflow-x-hidden p-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:gap-6 sm:p-6">
        <p
            v-if="successMessage"
            class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300"
        >
            {{ successMessage }}
        </p>

        <div>
            <h1 class="text-xl font-bold tracking-tight sm:text-3xl">طلبات العمال</h1>
            <p class="mt-1 text-sm text-muted-foreground sm:text-base">
                مهام التركيب بدون تفاصيل مالية — للمشرفين والفنيين
            </p>
        </div>

        <div class="grid grid-cols-3 gap-3 sm:gap-4">
            <button
                type="button"
                class="min-w-0 text-start transition active:scale-[0.98] max-md:touch-manipulation md:cursor-default"
                @click="handleStatCardClick('pending')"
            >
                <Card
                    class="h-full shadow-sm transition max-md:hover:border-primary/40 max-md:active:border-primary"
                    :class="mobileListVisible && statusFilter === 'pending' ? 'max-md:border-primary max-md:ring-1 max-md:ring-primary/20' : ''"
                >
                    <CardHeader class="p-4 pb-2 sm:p-6">
                        <CardTitle class="text-xs sm:text-sm">قيد التركيب</CardTitle>
                    </CardHeader>
                    <CardContent class="p-4 pt-0 sm:p-6 sm:pt-0">
                        <div class="text-xl font-bold tabular-nums sm:text-2xl">{{ formatInteger(stats.pending) }}</div>
                        <p class="mt-1 text-[10px] text-muted-foreground md:hidden">اضغط للعرض</p>
                        <p class="mt-1 hidden text-[10px] text-muted-foreground md:block">اضغط للتصفية</p>
                    </CardContent>
                </Card>
            </button>
            <button
                type="button"
                class="min-w-0 text-start transition active:scale-[0.98] max-md:touch-manipulation md:cursor-default"
                @click="handleStatCardClick('completed')"
            >
                <Card
                    class="h-full shadow-sm transition max-md:hover:border-primary/40 max-md:active:border-primary"
                    :class="mobileListVisible && statusFilter === 'completed' ? 'max-md:border-primary max-md:ring-1 max-md:ring-primary/20' : ''"
                >
                    <CardHeader class="p-4 pb-2 sm:p-6">
                        <CardTitle class="text-xs sm:text-sm">مرفوعة للمراجعة</CardTitle>
                    </CardHeader>
                    <CardContent class="p-4 pt-0 sm:p-6 sm:pt-0">
                        <div class="text-xl font-bold tabular-nums sm:text-2xl">{{ formatInteger(stats.completed) }}</div>
                        <p class="mt-1 text-[10px] text-muted-foreground md:hidden">اضغط للعرض</p>
                        <p class="mt-1 hidden text-[10px] text-muted-foreground md:block">اضغط للتصفية</p>
                    </CardContent>
                </Card>
            </button>
            <button
                type="button"
                class="min-w-0 text-start transition active:scale-[0.98] max-md:touch-manipulation md:cursor-default"
                @click="handleStatCardClick('all')"
            >
                <Card
                    class="h-full shadow-sm transition max-md:hover:border-primary/40 max-md:active:border-primary"
                    :class="mobileListVisible && statusFilter === 'all' ? 'max-md:border-primary max-md:ring-1 max-md:ring-primary/20' : ''"
                >
                    <CardHeader class="p-4 pb-2 sm:p-6">
                        <CardTitle class="text-xs sm:text-sm">الإجمالي</CardTitle>
                    </CardHeader>
                    <CardContent class="p-4 pt-0 sm:p-6 sm:pt-0">
                        <div class="text-xl font-bold tabular-nums sm:text-2xl">{{ formatInteger(stats.total) }}</div>
                        <p class="mt-1 text-[10px] text-muted-foreground md:hidden">اضغط للعرض</p>
                        <p class="mt-1 hidden text-[10px] text-muted-foreground md:block">اضغط للتصفية</p>
                    </CardContent>
                </Card>
            </button>
        </div>

        <Card class="min-w-0 shadow-sm" :class="mobileListVisible ? 'block' : 'hidden md:block'">
            <CardHeader class="p-4 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <CardTitle class="flex items-center gap-2 text-lg sm:text-xl">
                        <HardHat class="h-5 w-5" />
                        <span class="md:hidden">{{ mobileListTitle }}</span>
                        <span class="hidden md:inline">قائمة التركيب</span>
                    </CardTitle>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 touch-manipulation md:hidden"
                        @click="closeMobileList"
                    >
                        <ArrowRight class="ms-1 h-4 w-4" />
                        رجوع
                    </Button>
                </div>
            </CardHeader>
            <CardContent class="space-y-4 p-4 pt-0 sm:p-6 sm:pt-0">
                <div class="hidden grid-cols-3 gap-2 md:grid">
                    <Button
                        variant="outline"
                        class="h-10 touch-manipulation"
                        :class="statusFilter === 'pending' ? 'border-primary bg-primary/5' : ''"
                        @click="setStatusFilter('pending')"
                    >
                        قيد التركيب
                    </Button>
                    <Button
                        variant="outline"
                        class="h-10 touch-manipulation"
                        :class="statusFilter === 'completed' ? 'border-primary bg-primary/5' : ''"
                        @click="setStatusFilter('completed')"
                    >
                        مرفوعة للمراجعة
                    </Button>
                    <Button
                        variant="outline"
                        class="h-10 touch-manipulation"
                        :class="statusFilter === 'all' ? 'border-primary bg-primary/5' : ''"
                        @click="setStatusFilter('all')"
                    >
                        الكل
                    </Button>
                </div>

                <div
                    v-if="workerOrders.data.length === 0"
                    class="rounded-2xl border border-dashed px-4 py-12 text-center text-sm text-muted-foreground"
                >
                    لا توجد طلبات تركيب
                </div>

                <div v-else class="overflow-hidden rounded-xl border border-border/70 bg-card shadow-sm">
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow class="bg-muted/40 hover:bg-muted/40">
                                    <TableHead class="w-12 text-center font-semibold">#</TableHead>
                                    <TableHead class="min-w-[11rem] text-right font-semibold">المنتج</TableHead>
                                    <TableHead class="min-w-[7rem] text-right font-semibold">يوم الفعالية</TableHead>
                                    <TableHead class="min-w-[9rem] text-right font-semibold">اسم العميل</TableHead>
                                    <TableHead class="min-w-[14rem] text-right font-semibold">الموقع</TableHead>
                                    <TableHead
                                        v-if="statusFilter === 'all'"
                                        class="min-w-[6rem] text-center font-semibold"
                                    >
                                        الحالة
                                    </TableHead>
                                    <TableHead
                                        v-if="isCompletedView || statusFilter === 'all'"
                                        class="min-w-[7rem] text-right font-semibold"
                                    >
                                        العامل
                                    </TableHead>
                                    <TableHead
                                        v-if="isCompletedView || statusFilter === 'all'"
                                        class="min-w-[9rem] text-right font-semibold"
                                    >
                                        وقت التركيب
                                    </TableHead>
                                    <TableHead
                                        v-if="isCompletedView || statusFilter === 'all'"
                                        class="w-28 text-center font-semibold"
                                    >
                                        صورة التركيب
                                    </TableHead>
                                    <TableHead
                                        v-if="!isCompletedView"
                                        class="min-w-[8rem] text-center font-semibold"
                                    >
                                        إجراء
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="(item, index) in workerOrders.data"
                                    :key="item.id"
                                    class="transition-colors hover:bg-muted/20"
                                >
                                    <TableCell class="text-center tabular-nums text-muted-foreground">
                                        {{ formatInteger((workerOrders.from ?? 1) + index) }}
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-3">
                                            <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-muted/40 ring-1 ring-border/60">
                                                <img
                                                    v-if="item.product_image_url"
                                                    :src="item.product_image_url"
                                                    :alt="item.product_name"
                                                    class="h-full w-full object-cover"
                                                />
                                                <div
                                                    v-else
                                                    class="flex h-full w-full items-center justify-center text-muted-foreground"
                                                >
                                                    <ImageIcon class="h-5 w-5 opacity-40" />
                                                </div>
                                            </div>
                                            <span class="min-w-[7rem] text-sm font-semibold leading-snug">
                                                {{ item.product_name }}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <Calendar class="h-4 w-4 shrink-0 text-primary/70" />
                                            <span class="whitespace-nowrap font-medium tabular-nums">
                                                {{ formatEventDate(item.installation_date) }}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <User class="h-4 w-4 shrink-0 text-muted-foreground" />
                                            <span class="font-semibold">{{ item.customer_name }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <template v-if="customerAddress(item)">
                                            <p class="max-w-[18rem] text-sm leading-relaxed">
                                                {{ customerAddress(item) }}
                                            </p>
                                            <a
                                                v-if="locationLink(item)"
                                                :href="locationLink(item)!"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="mt-1.5 inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                                            >
                                                <Navigation class="h-3.5 w-3.5" />
                                                فتح على الخريطة
                                                <ExternalLink class="h-3 w-3 opacity-60" />
                                            </a>
                                        </template>
                                        <span v-else class="text-sm text-muted-foreground">—</span>
                                    </TableCell>
                                    <TableCell v-if="statusFilter === 'all'" class="text-center">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                            :class="item.status === 'completed'
                                                ? 'bg-green-100 text-green-800 dark:bg-green-950/40 dark:text-green-300'
                                                : 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'"
                                        >
                                            {{ item.status === 'completed' ? 'مرفوعة للمراجعة' : 'قيد التركيب' }}
                                        </span>
                                    </TableCell>
                                    <TableCell
                                        v-if="isCompletedView || statusFilter === 'all'"
                                        class="font-medium"
                                    >
                                        {{ item.status === 'completed' ? completedByName(item) : '—' }}
                                    </TableCell>
                                    <TableCell
                                        v-if="isCompletedView || statusFilter === 'all'"
                                        class="whitespace-nowrap tabular-nums text-sm"
                                        dir="ltr"
                                    >
                                        {{ item.status === 'completed' ? formatCompletedAt(item.completed_at) : '—' }}
                                    </TableCell>
                                    <TableCell v-if="isCompletedView || statusFilter === 'all'" class="text-center">
                                        <a
                                            v-if="item.installation_photo_url"
                                            :href="item.installation_photo_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex flex-col items-center gap-1"
                                            title="عرض صورة التركيب"
                                        >
                                            <img
                                                :src="item.installation_photo_url"
                                                alt="صورة التركيب"
                                                class="mx-auto h-14 w-14 rounded-lg object-cover ring-1 ring-border/60 transition hover:opacity-90"
                                            />
                                            <span class="text-[10px] font-medium text-primary">مراجعة</span>
                                        </a>
                                        <span v-else class="text-sm text-muted-foreground">—</span>
                                    </TableCell>
                                    <TableCell v-if="!isCompletedView" class="text-center">
                                        <Button
                                            v-if="item.status === 'pending'"
                                            size="sm"
                                            class="h-9 min-w-[7.5rem] touch-manipulation"
                                            @click="openCompleteDialog(item)"
                                        >
                                            <CheckCircle2 class="ms-1.5 h-4 w-4" />
                                            رفع صورة التركيب
                                        </Button>
                                        <span v-else class="text-xs text-muted-foreground">—</span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <div
                    v-if="workerOrders.last_page > 1"
                    class="flex flex-col gap-3 rounded-2xl border border-border/60 bg-muted/10 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4"
                >
                    <p class="text-center text-sm text-muted-foreground sm:text-start">
                        عرض
                        <span class="font-medium tabular-nums">{{ formatInteger(workerOrders.from ?? 0) }}</span>
                        إلى
                        <span class="font-medium tabular-nums">{{ formatInteger(workerOrders.to ?? 0) }}</span>
                        من
                        <span class="font-medium tabular-nums">{{ formatInteger(workerOrders.total) }}</span>
                    </p>
                    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                        <Button
                            variant="outline"
                            class="h-11 touch-manipulation"
                            :disabled="workerOrders.current_page <= 1"
                            @click="goToPage(workerOrders.current_page - 1)"
                        >
                            <ChevronRight class="h-4 w-4" />
                            السابق
                        </Button>
                        <span class="px-1 text-center text-sm font-medium tabular-nums text-muted-foreground">
                            {{ formatInteger(workerOrders.current_page) }} / {{ formatInteger(workerOrders.last_page) }}
                        </span>
                        <Button
                            variant="outline"
                            class="h-11 touch-manipulation"
                            :disabled="workerOrders.current_page >= workerOrders.last_page"
                            @click="goToPage(workerOrders.current_page + 1)"
                        >
                            التالي
                            <ChevronLeft class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    </Deferred>

    <Teleport to="body">
        <div
            v-if="dialogOpen && selectedOrder"
            class="fixed inset-0 z-[200] flex items-end justify-center p-0 sm:items-center sm:p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="worker-complete-title"
        >
            <button
                type="button"
                class="absolute inset-0 bg-black/60"
                aria-label="إغلاق"
                @click="closeCompleteDialog"
            />

            <div
                class="relative z-10 flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl bg-background shadow-2xl sm:max-h-[90vh] sm:rounded-2xl"
                dir="rtl"
            >
                <div class="flex items-start justify-between gap-3 border-b px-4 py-4 sm:px-6">
                    <div class="min-w-0 text-start">
                        <h2 id="worker-complete-title" class="text-lg font-bold">رفع صورة التركيب</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            ارفع صورة من أرض الواقع بعد التركيب. سيتم إرسال الطلب للمسؤول للمراجعة وليس اعتماده نهائياً.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-border/60 text-muted-foreground transition hover:bg-muted"
                        aria-label="إغلاق"
                        @click="closeCompleteDialog"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="space-y-4 overflow-y-auto px-4 py-4 sm:px-6">
                    <div class="rounded-xl border border-border/60 bg-muted/20 p-3 text-sm">
                        <p class="font-semibold">{{ selectedOrder.product_name }}</p>
                        <p class="mt-1 text-muted-foreground">العميل: {{ selectedOrder.customer_name }}</p>
                        <p class="mt-1 text-muted-foreground">
                            يوم الفعالية: {{ formatEventDate(selectedOrder.installation_date) }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label class="flex items-center gap-2">
                            <Camera class="h-4 w-4" />
                            صورة التركيب *
                        </Label>

                        <input
                            ref="photoInputRef"
                            id="installation-photo"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="handlePhotoChange"
                        />

                        <button
                            type="button"
                            class="flex min-h-32 w-full flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-primary/30 bg-primary/5 px-4 py-6 text-center transition hover:border-primary/50 hover:bg-primary/10"
                            @click="photoInputRef?.click()"
                        >
                            <Camera class="h-8 w-8 text-primary" />
                            <div>
                                <p class="font-semibold text-foreground">اضغط لرفع صورة التركيب</p>
                                <p class="mt-1 text-xs text-muted-foreground">JPG أو PNG — بحد أقصى 5 ميجابايت</p>
                            </div>
                        </button>

                        <p v-if="completeForm.installation_photo" class="text-sm font-medium text-green-700 dark:text-green-400">
                            تم اختيار الصورة: {{ completeForm.installation_photo.name }}
                        </p>

                        <p v-if="photoError" class="text-sm text-destructive">
                            {{ photoError }}
                        </p>
                        <p v-else-if="completeForm.errors.installation_photo" class="text-sm text-destructive">
                            {{ completeForm.errors.installation_photo }}
                        </p>
                    </div>

                    <div v-if="photoPreview" class="overflow-hidden rounded-xl border border-border/60">
                        <p class="border-b border-border/60 bg-muted/20 px-3 py-2 text-xs text-muted-foreground">معاينة الصورة</p>
                        <img :src="photoPreview" alt="معاينة صورة التركيب" class="max-h-56 w-full object-cover" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 border-t px-4 py-4 sm:px-6">
                    <Button variant="outline" type="button" class="h-11 touch-manipulation" @click="closeCompleteDialog">
                        إلغاء
                    </Button>
                    <Button
                        type="button"
                        class="h-11 touch-manipulation"
                        :disabled="completeForm.processing"
                        @click="submitCompletion"
                    >
                        {{ completeForm.processing ? 'جاري الإرسال...' : 'إرسال للمراجعة' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
