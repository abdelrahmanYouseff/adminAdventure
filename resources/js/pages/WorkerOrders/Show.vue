<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    ArrowRight,
    Calendar,
    User,
    Camera,
    CheckCircle2,
    ExternalLink,
    Navigation,
    ImageIcon,
    X,
    FileText,
    Printer,
    MapPin,
    Phone,
    Package,
} from 'lucide-vue-next';
import { formatDate, formatDateTime } from '@/lib/formatNumber';

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
    status: 'pending' | 'completed';
    products_count: number;
    pending_count: number;
    completed_count: number;
    location_slug: string | null;
    lines: WorkOrderLine[];
    delivery_note_url: string;
}

interface Props {
    workOrder: WorkOrder;
}

const props = defineProps<Props>();

defineOptions({ layout: AppLayout });

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);

const selectedLine = ref<WorkOrderLine | null>(null);
const photoPreview = ref<string | null>(null);
const dialogOpen = ref(false);
const photoInputRef = ref<HTMLInputElement | null>(null);
const photoError = ref<string | null>(null);

const completeForm = useForm({
    installation_photo: null as File | null,
    redirect_to_show: true,
});

const displayAddress = computed(() => props.workOrder.address?.trim() || props.workOrder.customer_address?.trim() || null);

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

function completedByName(line: WorkOrderLine): string {
    return line.completed_by_user?.name || '—';
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

function locationLink(): string | null {
    if (props.workOrder.location_slug) {
        return route('store.order.location', props.workOrder.location_slug);
    }

    return locationMapsUrl(displayAddress.value);
}

function openCompleteDialog(line: WorkOrderLine) {
    selectedLine.value = line;
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
    selectedLine.value = null;
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
    if (!selectedLine.value) {
        return;
    }

    if (!completeForm.installation_photo) {
        photoError.value = 'يجب إرفاق صورة للتركيب من أرض الواقع قبل الإرسال.';
        return;
    }

    completeForm.post(`/worker-orders/lines/${selectedLine.value.id}/complete`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            closeCompleteDialog();
        },
    });
}

function printDeliveryNote() {
    window.open(`${props.workOrder.delivery_note_url}?v=${Date.now()}`, '_blank');
}

watch(dialogOpen, (isOpen) => {
    if (!isOpen) {
        closeCompleteDialog();
    }
});
</script>

<template>
    <Head :title="`أمر عمل ${workOrder.reference_number}`" />

    <div class="flex min-w-0 flex-1 flex-col gap-4 overflow-x-hidden p-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:gap-6 sm:p-6">
        <p
            v-if="successMessage"
            class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300"
        >
            {{ successMessage }}
        </p>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <Link
                    href="/worker-orders"
                    class="mb-3 inline-flex h-9 items-center rounded-md border border-input bg-background px-3 text-sm font-medium transition hover:bg-accent"
                >
                    <ArrowRight class="ms-1.5 h-4 w-4" />
                    رجوع لأوامر العمل
                </Link>
                <h1 class="text-xl font-bold tracking-tight sm:text-3xl">تفاصيل أمر العمل</h1>
                <p class="mt-1 text-sm text-muted-foreground sm:text-base">
                    الرقم المرجعي:
                    <span class="font-semibold tabular-nums" dir="ltr">{{ workOrder.reference_number }}</span>
                </p>
            </div>
            <Button class="h-10 touch-manipulation" @click="printDeliveryNote">
                <Printer class="ms-1.5 h-4 w-4" />
                طباعة إذن التسليم
            </Button>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card class="shadow-sm">
                <CardHeader class="p-4 sm:p-6">
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <User class="h-5 w-5" />
                        بيانات العميل
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 p-4 pt-0 text-sm sm:p-6 sm:pt-0">
                    <div>
                        <p class="text-muted-foreground">اسم العميل</p>
                        <p class="font-semibold">{{ workOrder.customer_name }}</p>
                    </div>
                    <div v-if="workOrder.customer_phone">
                        <p class="text-muted-foreground">الجوال</p>
                        <p class="flex items-center gap-2 font-medium" dir="ltr">
                            <Phone class="h-4 w-4 text-muted-foreground" />
                            {{ workOrder.customer_phone }}
                        </p>
                    </div>
                    <div>
                        <p class="text-muted-foreground">تاريخ الفعالية</p>
                        <p class="flex items-center gap-2 font-semibold tabular-nums">
                            <Calendar class="h-4 w-4 text-primary/70" />
                            {{ formatEventDate(workOrder.installation_date) }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="shadow-sm">
                <CardHeader class="p-4 sm:p-6">
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <MapPin class="h-5 w-5" />
                        موقع التركيب
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 p-4 pt-0 text-sm sm:p-6 sm:pt-0">
                    <p v-if="displayAddress" class="leading-relaxed">{{ displayAddress }}</p>
                    <p v-else class="text-muted-foreground">لم يُحدد موقع</p>
                    <a
                        v-if="locationLink()"
                        :href="locationLink()!"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"
                    >
                        <Navigation class="h-4 w-4" />
                        فتح على الخريطة
                        <ExternalLink class="h-3.5 w-3.5 opacity-60" />
                    </a>
                </CardContent>
            </Card>
        </div>

        <Card class="shadow-sm">
            <CardHeader class="p-4 sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <Package class="h-5 w-5" />
                        المنتجات المطلوب تركيبها
                        <span class="text-sm font-normal text-muted-foreground">
                            ({{ workOrder.completed_count }}/{{ workOrder.products_count }} مرفوعة)
                        </span>
                    </CardTitle>
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <FileText class="h-4 w-4" />
                        <span dir="ltr">{{ workOrder.reference_number }}</span>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="p-4 pt-0 sm:p-6 sm:pt-0">
                <div class="overflow-hidden rounded-xl border border-border/70">
                    <Table>
                        <TableHeader>
                            <TableRow class="bg-muted/40 hover:bg-muted/40">
                                <TableHead class="text-right font-semibold">المنتج</TableHead>
                                <TableHead class="text-center font-semibold">الحالة</TableHead>
                                <TableHead class="text-right font-semibold">العامل</TableHead>
                                <TableHead class="text-right font-semibold">وقت الرفع</TableHead>
                                <TableHead class="text-center font-semibold">الصورة</TableHead>
                                <TableHead class="text-center font-semibold">إجراء</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="line in workOrder.lines" :key="line.id">
                                <TableCell>
                                    <div class="flex items-center gap-3">
                                        <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-muted/40 ring-1 ring-border/60">
                                            <img
                                                v-if="line.product_image_url"
                                                :src="line.product_image_url"
                                                :alt="line.product_name"
                                                class="h-full w-full object-cover"
                                            />
                                            <div
                                                v-else
                                                class="flex h-full w-full items-center justify-center text-muted-foreground"
                                            >
                                                <ImageIcon class="h-5 w-5 opacity-40" />
                                            </div>
                                        </div>
                                        <span class="font-semibold">{{ line.product_name }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-center">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="line.status === 'completed'
                                            ? 'bg-green-100 text-green-800 dark:bg-green-950/40 dark:text-green-300'
                                            : 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'"
                                    >
                                        {{ line.status === 'completed' ? 'مرفوعة للمراجعة' : 'قيد التركيب' }}
                                    </span>
                                </TableCell>
                                <TableCell>{{ line.status === 'completed' ? completedByName(line) : '—' }}</TableCell>
                                <TableCell class="whitespace-nowrap tabular-nums" dir="ltr">
                                    {{ line.status === 'completed' ? formatCompletedAt(line.completed_at) : '—' }}
                                </TableCell>
                                <TableCell class="text-center">
                                    <a
                                        v-if="line.installation_photo_url"
                                        :href="line.installation_photo_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <img
                                            :src="line.installation_photo_url"
                                            alt="صورة التركيب"
                                            class="mx-auto h-12 w-12 rounded-lg object-cover ring-1 ring-border/60"
                                        />
                                    </a>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell class="text-center">
                                    <Button
                                        v-if="line.status === 'pending'"
                                        size="sm"
                                        class="h-9 touch-manipulation"
                                        @click="openCompleteDialog(line)"
                                    >
                                        <CheckCircle2 class="ms-1.5 h-4 w-4" />
                                        رفع صورة
                                    </Button>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    </div>

    <Teleport to="body">
        <div
            v-if="dialogOpen && selectedLine"
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
                            ارفع صورة من أرض الواقع بعد التركيب. سيتم إرسال المنتج للمسؤول للمراجعة.
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
                        <p class="font-semibold">{{ selectedLine.product_name }}</p>
                        <p class="mt-1 text-muted-foreground">العميل: {{ workOrder.customer_name }}</p>
                        <p class="mt-1 text-muted-foreground">
                            يوم الفعالية: {{ formatEventDate(workOrder.installation_date) }}
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
