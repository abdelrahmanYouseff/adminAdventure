<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage, Deferred } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    HardHat,
    Calendar,
    User,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    Navigation,
    ImageIcon,
    ArrowRight,
    FileText,
    Eye,
} from 'lucide-vue-next';
import { formatDate, formatInteger } from '@/lib/formatNumber';

interface PreviewProduct {
    name: string;
    image_url: string | null;
}

interface WorkOrderItem {
    id: number;
    reference_number: string;
    order_number: string;
    invoice_number: string | null;
    customer_name: string;
    customer_address: string | null;
    installation_date: string | null;
    status: 'pending' | 'completed';
    products_count: number;
    pending_count: number;
    completed_count: number;
    location_slug: string | null;
    preview_products: PreviewProduct[];
}

interface Props {
    workOrders?: {
        data: WorkOrderItem[];
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

const workOrders = computed(() => props.workOrders ?? {
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
const mobileListVisible = ref(false);

const mobileListTitle = computed(() => {
    if (statusFilter.value === 'completed') {
        return 'مرفوعة للمراجعة';
    }

    if (statusFilter.value === 'all') {
        return 'كل أوامر العمل';
    }

    return 'قيد التركيب';
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
    if (pageNumber >= 1 && pageNumber <= workOrders.value.last_page) {
        applyFilters(pageNumber);
    }
}

function formatEventDate(date: string | null): string {
    if (!date) {
        return 'غير محدد';
    }

    return formatDate(date);
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

function locationLink(item: WorkOrderItem): string | null {
    if (item.location_slug) {
        return route('store.order.location', item.location_slug);
    }

    return locationMapsUrl(item.customer_address);
}

function workOrderUrl(item: WorkOrderItem): string {
    return `/worker-orders/${encodeURIComponent(item.reference_number)}`;
}

function openWorkOrder(item: WorkOrderItem) {
    router.visit(workOrderUrl(item));
}

function statusLabel(item: WorkOrderItem): string {
    if (item.status === 'completed') {
        return 'مرفوعة للمراجعة';
    }

    if (item.completed_count > 0) {
        return `قيد التركيب (${item.completed_count}/${item.products_count})`;
    }

    return 'قيد التركيب';
}

watch(
    () => props.filters.status,
    (status) => {
        statusFilter.value = status || 'pending';
    },
);
</script>

<template>
    <Head title="أوامر العمل" />

    <Deferred :data="['workOrders', 'stats']">
        <template #fallback>
            <div class="flex min-h-[50vh] items-center justify-center p-6 text-sm text-muted-foreground">
                جاري تحميل أوامر العمل...
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
                <h1 class="text-xl font-bold tracking-tight sm:text-3xl">أوامر العمل</h1>
                <p class="mt-1 text-sm text-muted-foreground sm:text-base">
                    طلب واحد لكل فاتورة — اضغط لعرض المنتجات المطلوب تركيبها
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
                            <span class="hidden md:inline">قائمة أوامر العمل</span>
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
                        v-if="workOrders.data.length === 0"
                        class="rounded-2xl border border-dashed px-4 py-12 text-center text-sm text-muted-foreground"
                    >
                        لا توجد أوامر عمل
                    </div>

                    <div v-else class="overflow-hidden rounded-xl border border-border/70 bg-card shadow-sm">
                        <div class="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow class="bg-muted/40 hover:bg-muted/40">
                                        <TableHead class="w-12 text-center font-semibold">#</TableHead>
                                        <TableHead class="min-w-[9rem] text-right font-semibold">الرقم المرجعي</TableHead>
                                        <TableHead class="min-w-[9rem] text-right font-semibold">اسم العميل</TableHead>
                                        <TableHead class="min-w-[7rem] text-right font-semibold">يوم الفعالية</TableHead>
                                        <TableHead class="min-w-[12rem] text-right font-semibold">الموقع</TableHead>
                                        <TableHead class="min-w-[8rem] text-right font-semibold">المنتجات</TableHead>
                                        <TableHead class="min-w-[7rem] text-center font-semibold">الحالة</TableHead>
                                        <TableHead class="min-w-[7rem] text-center font-semibold">إجراء</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="(item, index) in workOrders.data"
                                        :key="item.id"
                                        class="cursor-pointer transition-colors hover:bg-muted/30"
                                        @click="openWorkOrder(item)"
                                    >
                                        <TableCell class="text-center tabular-nums text-muted-foreground">
                                            {{ formatInteger((workOrders.from ?? 1) + index) }}
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex items-center gap-2">
                                                <FileText class="h-4 w-4 shrink-0 text-primary/70" />
                                                <div>
                                                    <p class="font-semibold tabular-nums" dir="ltr">{{ item.reference_number }}</p>
                                                    <p v-if="item.invoice_number && item.invoice_number !== item.reference_number" class="text-xs text-muted-foreground">
                                                        فاتورة: {{ item.invoice_number }}
                                                    </p>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex items-center gap-2">
                                                <User class="h-4 w-4 shrink-0 text-muted-foreground" />
                                                <span class="font-semibold">{{ item.customer_name }}</span>
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
                                            <template v-if="item.customer_address">
                                                <p class="max-w-[16rem] text-sm leading-relaxed">
                                                    {{ item.customer_address }}
                                                </p>
                                                <a
                                                    v-if="locationLink(item)"
                                                    :href="locationLink(item)!"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="mt-1.5 inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                                                    @click.stop
                                                >
                                                    <Navigation class="h-3.5 w-3.5" />
                                                    فتح على الخريطة
                                                    <ExternalLink class="h-3 w-3 opacity-60" />
                                                </a>
                                            </template>
                                            <span v-else class="text-sm text-muted-foreground">—</span>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex items-center gap-2">
                                                <div class="flex -space-x-2 space-x-reverse">
                                                    <div
                                                        v-for="(product, pIndex) in item.preview_products"
                                                        :key="pIndex"
                                                        class="h-9 w-9 overflow-hidden rounded-lg bg-muted/40 ring-2 ring-background"
                                                    >
                                                        <img
                                                            v-if="product.image_url"
                                                            :src="product.image_url"
                                                            :alt="product.name"
                                                            class="h-full w-full object-cover"
                                                        />
                                                        <div
                                                            v-else
                                                            class="flex h-full w-full items-center justify-center text-muted-foreground"
                                                        >
                                                            <ImageIcon class="h-4 w-4 opacity-40" />
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="text-sm">
                                                    <span class="font-semibold tabular-nums">{{ formatInteger(item.products_count) }}</span>
                                                    <span class="text-muted-foreground"> منتج</span>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-center">
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                                :class="item.status === 'completed'
                                                    ? 'bg-green-100 text-green-800 dark:bg-green-950/40 dark:text-green-300'
                                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'"
                                            >
                                                {{ statusLabel(item) }}
                                            </span>
                                        </TableCell>
                                        <TableCell class="text-center" @click.stop>
                                            <Link
                                                :href="workOrderUrl(item)"
                                                class="inline-flex h-9 min-w-[7rem] items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
                                            >
                                                <Eye class="ms-1.5 h-4 w-4" />
                                                التفاصيل
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>

                    <div
                        v-if="workOrders.last_page > 1"
                        class="flex flex-col gap-3 rounded-2xl border border-border/60 bg-muted/10 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4"
                    >
                        <p class="text-center text-sm text-muted-foreground sm:text-start">
                            عرض
                            <span class="font-medium tabular-nums">{{ formatInteger(workOrders.from ?? 0) }}</span>
                            إلى
                            <span class="font-medium tabular-nums">{{ formatInteger(workOrders.to ?? 0) }}</span>
                            من
                            <span class="font-medium tabular-nums">{{ formatInteger(workOrders.total) }}</span>
                        </p>
                        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                            <Button
                                variant="outline"
                                class="h-11 touch-manipulation"
                                :disabled="workOrders.current_page <= 1"
                                @click="goToPage(workOrders.current_page - 1)"
                            >
                                <ChevronRight class="h-4 w-4" />
                                السابق
                            </Button>
                            <span class="px-1 text-center text-sm font-medium tabular-nums text-muted-foreground">
                                {{ formatInteger(workOrders.current_page) }} / {{ formatInteger(workOrders.last_page) }}
                            </span>
                            <Button
                                variant="outline"
                                class="h-11 touch-manipulation"
                                :disabled="workOrders.current_page >= workOrders.last_page"
                                @click="goToPage(workOrders.current_page + 1)"
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
</template>
