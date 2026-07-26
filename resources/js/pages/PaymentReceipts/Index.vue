<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Receipt,
    Search,
    MoreHorizontal,
    Eye,
    FileText,
    CheckCircle2,
    Clock,
    ChevronLeft,
    ChevronRight,
    ChevronDown,
    Phone,
    Mail,
    MapPin,
    CreditCard,
    UserRound,
} from 'lucide-vue-next';
import { formatCurrency, formatDate, formatInteger } from '@/lib/formatNumber';

interface CustomerProfile {
    name: string | null;
    phone: string | null;
    phone_secondary: string | null;
    email: string | null;
    address: string | null;
    iban: string | null;
    iban_image_url: string | null;
    source: string;
    type: string | null;
}

interface ReceiptRow {
    id: number;
    receipt_number: string;
    amount: number;
    total_amount: number;
    remaining_amount: number;
    payment_method: string | null;
    type: string;
    approval_status: string;
    is_approved: boolean;
    can_approve: boolean;
    approved_at: string | null;
    approved_by_name: string | null;
    created_at: string | null;
    order: {
        id: number;
        order_number: string;
        customer_name: string;
        currency: string;
    } | null;
    customer: CustomerProfile | null;
    recorded_by_name?: string | null;
}

interface Props {
    receipts: {
        data: ReceiptRow[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        per_page: number;
    };
    stats: {
        pending: number;
        approved: number;
    };
    canApprove: boolean;
    filters: {
        search?: string | null;
        status?: string | null;
    };
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({}),
});

defineOptions({ layout: AppLayout });

const searchInput = ref(props.filters?.search ?? '');
const statusFilter = ref(props.filters?.status ?? 'all');
const expandedReceiptId = ref<number | null>(null);

const approveForm = useForm({});
const approvingId = ref<number | null>(null);

function applyFilters(pageNum = 1) {
    router.get(
        route('payment-receipts.index'),
        {
            search: searchInput.value || undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
            page: pageNum > 1 ? pageNum : undefined,
        },
        { preserveState: true },
    );
}

watch(
    () => props.filters?.search,
    (value) => {
        searchInput.value = value ?? '';
    },
);

watch(
    () => props.filters?.status,
    (value) => {
        statusFilter.value = value ?? 'all';
    },
);

watch(statusFilter, () => applyFilters(1));

function onSearchSubmit() {
    applyFilters(1);
}

function goToPage(pageNum: number) {
    if (pageNum >= 1 && pageNum <= props.receipts.last_page) {
        applyFilters(pageNum);
    }
}

function toggleExpand(row: ReceiptRow) {
    expandedReceiptId.value = expandedReceiptId.value === row.id ? null : row.id;
}

function approveReceipt(row: ReceiptRow) {
    if (!row.can_approve || approvingId.value !== null) return;
    approvingId.value = row.id;
    approveForm.post(route('payment-receipts.approve', row.id), {
        preserveScroll: true,
        onFinish: () => {
            approvingId.value = null;
        },
    });
}

function receiptPdfUrl(row: ReceiptRow): string | null {
    if (!row.order || !row.is_approved) return null;
    return `/orders/${row.order.id}/payment-receipts/${row.id}`;
}

function orderUrl(row: ReceiptRow): string | null {
    if (!row.order) return null;
    return `/orders/${row.order.id}`;
}

function paymentMethodLabel(method: string | null): string {
    const map: Record<string, string> = {
        cash: 'نقدي',
        bank_transfer: 'تحويل بنكي',
        credit_card: 'بطاقة ائتمان',
        noon: 'Noon',
        paypal: 'PayPal',
    };
    return method ? map[method] || method : '—';
}

function typeLabel(type: string): string {
    const map: Record<string, string> = {
        initial: 'عند الإنشاء',
        settlement: 'سداد',
        payment: 'سداد',
    };
    return map[type] || type;
}

const statusTabs = computed(() => [
    { value: 'all', label: 'الكل' },
    { value: 'pending', label: `بانتظار الاعتماد (${formatInteger(props.stats.pending)})` },
    { value: 'approved', label: `معتمدة (${formatInteger(props.stats.approved)})` },
]);
</script>

<template>
    <Head title="سندات القبض" />
    <div class="flex min-w-0 flex-1 flex-col gap-4 overflow-x-hidden p-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:gap-6 sm:p-6 sm:py-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight sm:text-3xl">سندات القبض</h1>
                <p class="mt-1 text-sm text-muted-foreground sm:text-base">
                    اعتماد المبالغ المحصّلة من الموظفين؛ عند اعتماد أول مبلغ يصدر أمر العمل
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="secondary" class="w-fit tabular-nums">
                    <Clock class="ms-1 h-3.5 w-3.5" />
                    {{ formatInteger(stats.pending) }} بانتظار الاعتماد
                </Badge>
                <Badge variant="outline" class="w-fit tabular-nums">
                    {{ formatInteger(receipts.total) }} سند
                </Badge>
            </div>
        </div>

        <Card class="shadow-sm">
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center gap-2 text-base sm:text-lg">
                    <Receipt class="h-5 w-5" />
                    قائمة السندات
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex flex-wrap gap-1.5">
                    <Button
                        v-for="tab in statusTabs"
                        :key="tab.value"
                        type="button"
                        size="sm"
                        class="h-8"
                        :variant="statusFilter === tab.value ? 'default' : 'outline'"
                        @click="statusFilter = tab.value"
                    >
                        {{ tab.label }}
                    </Button>
                </div>

                <form class="flex flex-col gap-2 sm:flex-row" @submit.prevent="onSearchSubmit">
                    <div class="relative min-w-0 flex-1">
                        <Search class="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchInput"
                            class="h-10 ps-9"
                            placeholder="بحث برقم السند أو الطلب أو اسم العميل..."
                        />
                    </div>
                    <Button type="submit" class="h-10 shrink-0">بحث</Button>
                </form>

                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead class="bg-muted/40 text-xs text-muted-foreground">
                            <tr>
                                <th class="w-10 px-3 py-3" />
                                <th class="px-3 py-3 text-right font-medium">رقم الطلب</th>
                                <th class="px-3 py-3 text-right font-medium">اسم العميل</th>
                                <th class="px-3 py-3 text-left font-medium">المبلغ</th>
                                <th class="px-3 py-3 text-left font-medium">الإجمالي</th>
                                <th class="px-3 py-3 text-left font-medium">المتبقي</th>
                                <th class="px-3 py-3 text-center font-medium">الحالة</th>
                                <th class="px-3 py-3 text-center font-medium">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="receipts.data.length === 0">
                                <td colspan="8" class="px-3 py-10 text-center text-muted-foreground">
                                    لا توجد سندات قبض حالياً
                                </td>
                            </tr>
                            <template v-for="row in receipts.data" :key="row.id">
                                <tr
                                    class="cursor-pointer border-t hover:bg-muted/20"
                                    :class="expandedReceiptId === row.id ? 'bg-slate-50/80' : ''"
                                    @click="toggleExpand(row)"
                                >
                                    <td class="px-3 py-3 text-center">
                                        <ChevronDown
                                            class="mx-auto h-4 w-4 text-muted-foreground transition-transform"
                                            :class="expandedReceiptId === row.id ? 'rotate-180' : ''"
                                        />
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="font-semibold tabular-nums">
                                            {{ row.order?.order_number || '—' }}
                                        </div>
                                        <div class="mt-0.5 text-xs text-muted-foreground tabular-nums">
                                            {{ row.receipt_number }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="font-medium">{{ row.order?.customer_name || '—' }}</div>
                                        <div class="mt-0.5 text-xs text-muted-foreground">
                                            {{ row.created_at ? formatDate(row.created_at) : '—' }} · {{ typeLabel(row.type) }} · {{ paymentMethodLabel(row.payment_method) }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-left font-semibold tabular-nums" dir="ltr">
                                        {{ formatCurrency(row.amount, row.order?.currency || 'SAR') }}
                                    </td>
                                    <td class="px-3 py-3 text-left tabular-nums text-muted-foreground" dir="ltr">
                                        {{ formatCurrency(row.total_amount, row.order?.currency || 'SAR') }}
                                    </td>
                                    <td
                                        class="px-3 py-3 text-left font-medium tabular-nums"
                                        dir="ltr"
                                        :class="row.remaining_amount > 0 ? 'text-amber-600' : 'text-emerald-600'"
                                    >
                                        {{ formatCurrency(row.remaining_amount, row.order?.currency || 'SAR') }}
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <Badge
                                            v-if="row.is_approved"
                                            class="gap-1 border-emerald-200 bg-emerald-50 text-emerald-700"
                                            variant="outline"
                                        >
                                            <CheckCircle2 class="h-3.5 w-3.5" />
                                            معتمد
                                        </Badge>
                                        <Badge
                                            v-else
                                            class="gap-1 border-amber-200 bg-amber-50 text-amber-700"
                                            variant="outline"
                                        >
                                            <Clock class="h-3.5 w-3.5" />
                                            بانتظار الاعتماد
                                        </Badge>
                                        <div v-if="row.is_approved && row.approved_by_name" class="mt-1 text-[11px] text-muted-foreground">
                                            {{ row.approved_by_name }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-3" @click.stop>
                                        <div class="flex items-center justify-center gap-2">
                                            <Button
                                                v-if="row.can_approve"
                                                type="button"
                                                size="sm"
                                                class="h-8 gap-1"
                                                :disabled="approvingId === row.id"
                                                @click="approveReceipt(row)"
                                            >
                                                <CheckCircle2 class="h-4 w-4" />
                                                {{ approvingId === row.id ? 'جاري الاعتماد...' : 'اعتماد المبلغ' }}
                                            </Button>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <Button variant="ghost" size="icon" class="h-8 w-8">
                                                        <MoreHorizontal class="h-4 w-4" />
                                                        <span class="sr-only">الإجراءات</span>
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" class="w-44">
                                                    <DropdownMenuItem v-if="receiptPdfUrl(row)" as-child>
                                                        <a
                                                            :href="receiptPdfUrl(row)!"
                                                            target="_blank"
                                                            rel="noopener"
                                                            class="cursor-pointer"
                                                        >
                                                            <FileText class="ms-2 h-4 w-4" />
                                                            عرض السند
                                                        </a>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem v-if="orderUrl(row)" as-child>
                                                        <Link :href="orderUrl(row)!" class="cursor-pointer">
                                                            <Eye class="ms-2 h-4 w-4" />
                                                            عرض الطلب
                                                        </Link>
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="expandedReceiptId === row.id" class="border-b bg-slate-50/80">
                                    <td colspan="8" class="p-4">
                                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(260px,1fr)]">
                                            <div class="rounded-xl border bg-background p-4">
                                                <div class="mb-3 flex items-center gap-2">
                                                    <UserRound class="h-4 w-4 text-muted-foreground" />
                                                    <p class="font-semibold">بيانات العميل</p>
                                                    <Badge v-if="row.customer?.type" variant="secondary" class="text-[11px]">
                                                        {{ row.customer.type }}
                                                    </Badge>
                                                </div>
                                                <div class="grid gap-3 sm:grid-cols-2">
                                                    <div>
                                                        <p class="text-xs text-muted-foreground">الاسم</p>
                                                        <p class="mt-0.5 font-medium">{{ row.customer?.name || row.order?.customer_name || '—' }}</p>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-muted-foreground">الجوال</p>
                                                        <p class="mt-0.5 flex items-center gap-1.5 font-medium tabular-nums" dir="ltr">
                                                            <Phone class="h-3.5 w-3.5 text-muted-foreground" />
                                                            {{ row.customer?.phone || '—' }}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-muted-foreground">جوال إضافي</p>
                                                        <p class="mt-0.5 font-medium tabular-nums" dir="ltr">
                                                            {{ row.customer?.phone_secondary || 'غير مسجّل' }}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-muted-foreground">البريد</p>
                                                        <p class="mt-0.5 flex items-center gap-1.5 font-medium">
                                                            <Mail class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                                            <span class="truncate">{{ row.customer?.email || '—' }}</span>
                                                        </p>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <p class="text-xs text-muted-foreground">العنوان</p>
                                                        <p class="mt-0.5 flex items-start gap-1.5 font-medium">
                                                            <MapPin class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                                            <span>{{ row.customer?.address || '—' }}</span>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="rounded-xl border bg-background p-4">
                                                <div class="mb-3 flex items-center gap-2">
                                                    <CreditCard class="h-4 w-4 text-muted-foreground" />
                                                    <p class="font-semibold">بيانات الآيبان</p>
                                                </div>
                                                <div class="space-y-3">
                                                    <div>
                                                        <p class="text-xs text-muted-foreground">رقم الآيبان</p>
                                                        <p
                                                            v-if="row.customer?.iban"
                                                            class="mt-0.5 break-all font-medium tabular-nums"
                                                            dir="ltr"
                                                        >
                                                            {{ row.customer.iban }}
                                                        </p>
                                                        <p v-else class="mt-0.5 text-sm text-muted-foreground">غير مسجّل</p>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1.5 text-xs text-muted-foreground">صورة الآيبان</p>
                                                        <a
                                                            v-if="row.customer?.iban_image_url"
                                                            :href="row.customer.iban_image_url"
                                                            target="_blank"
                                                            rel="noopener"
                                                            class="block overflow-hidden rounded-lg border"
                                                            @click.stop
                                                        >
                                                            <img
                                                                :src="row.customer.iban_image_url"
                                                                alt="صورة الآيبان"
                                                                class="max-h-48 w-full object-contain bg-muted/30"
                                                            />
                                                        </a>
                                                        <p v-else class="text-sm text-muted-foreground">لا توجد صورة مسجّلة</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="receipts.last_page > 1"
                    class="flex flex-col items-center justify-between gap-3 sm:flex-row"
                >
                    <p class="text-sm text-muted-foreground">
                        عرض {{ receipts.from }}–{{ receipts.to }} من {{ formatInteger(receipts.total) }}
                    </p>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8"
                            :disabled="receipts.current_page <= 1"
                            @click="goToPage(receipts.current_page - 1)"
                        >
                            <ChevronRight class="h-4 w-4" />
                            السابق
                        </Button>
                        <span class="text-sm tabular-nums">
                            {{ receipts.current_page }} / {{ receipts.last_page }}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8"
                            :disabled="receipts.current_page >= receipts.last_page"
                            @click="goToPage(receipts.current_page + 1)"
                        >
                            التالي
                            <ChevronLeft class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
