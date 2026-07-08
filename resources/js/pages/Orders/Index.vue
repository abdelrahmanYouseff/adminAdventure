<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    ShoppingCart,
    Search,
    Mail,
    Phone,
    MoreHorizontal,
    Trash2,
    ChevronLeft,
    ChevronRight,
    Eye,
    MapPin,
    ExternalLink,
} from 'lucide-vue-next';
import { formatCurrency, formatDate, formatInteger } from '@/lib/formatNumber';

interface Order {
    id: number;
    order_number: string;
    customer_name: string;
    customer_email: string | null;
    customer_phone: string | null;
    total_amount: number;
    currency: string;
    payment_method: string;
    status: string;
    activity_date: string | null;
    address: string | null;
    created_at: string;
}

interface Props {
    orders: {
        data: Order[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        per_page: number;
    };
    filters: {
        search?: string;
        status?: string;
        payment_method?: string;
        currency?: string;
    };
}

const props = withDefaults(
    defineProps<Props>(),
    {
        filters: () => ({}),
    }
);

defineOptions({ layout: AppLayout });

const searchInput = ref(props.filters?.search ?? '');
const statusFilter = ref(props.filters?.status ?? 'all');
const paymentFilter = ref(props.filters?.payment_method ?? 'all');
const currencyFilter = ref(props.filters?.currency ?? 'all');

function applyFilters(page = 1) {
    router.get(route('orders.index'), {
        search: searchInput.value || undefined,
        status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
        payment_method: paymentFilter.value !== 'all' ? paymentFilter.value : undefined,
        currency: currencyFilter.value !== 'all' ? currencyFilter.value : undefined,
        page: page > 1 ? page : undefined,
    }, { preserveState: true });
}

watch([statusFilter, paymentFilter, currencyFilter], () => {
    applyFilters(1);
});

function onSearchSubmit() {
    applyFilters(1);
}

function goToPage(page: number) {
    if (page >= 1 && page <= props.orders.last_page) applyFilters(page);
}

const deleteOrder = (order: Order) => {
    if (confirm('هل تريد حذف هذا الطلب؟')) {
        router.delete(route('orders.destroy', order.id), {
            preserveScroll: true,
        });
    }
};

const getStatusText = (status: string) => {
    const map: Record<string, string> = {
        pending: 'قيد الانتظار',
        processing: 'قيد المعالجة',
        paid: 'مدفوع',
        cancelled: 'ملغي',
        refunded: 'مسترد',
    };
    return map[status] || status;
};

const getStatusBadgeVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'pending':
        case 'processing':
            return 'secondary';
        case 'paid':
            return 'default';
        case 'cancelled':
        case 'refunded':
            return 'destructive';
        default:
            return 'outline';
    }
};

const getPaymentMethodText = (method: string) => {
    const map: Record<string, string> = {
        credit_card: 'بطاقة ائتمان',
        cash: 'نقدي',
        bank_transfer: 'تحويل بنكي',
        paypal: 'PayPal',
        noon: 'Noon',
    };
    return map[method] || method;
};

const pendingCount = () => props.orders.data.filter((o) => o.status === 'pending').length;
const paidCount = () => props.orders.data.filter((o) => o.status === 'paid').length;

function formatActivityDate(date: string | null): string {
    if (!date) return '—';
    return formatDate(date);
}

function locationMapsUrl(address: string | null): string | null {
    if (!address?.trim()) return null;
    const trimmed = address.trim();
    const coordMatch = trimmed.match(/^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/);
    if (coordMatch) {
        return `https://www.google.com/maps?q=${coordMatch[1]},${coordMatch[2]}`;
    }
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(trimmed)}`;
}
</script>

<template>
    <Head title="إدارة الطلبات" />
    <div class="flex min-w-0 flex-1 flex-col gap-4 overflow-x-hidden p-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:gap-6 sm:p-6 sm:py-6">
        <!-- Header -->
        <div class="flex flex-col gap-1 sm:gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight sm:text-3xl">إدارة الطلبات</h1>
                <p class="mt-1 text-sm text-muted-foreground sm:text-base">
                    عرض وبحث وفلترة الطلبات
                </p>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <Card class="min-w-0 shadow-sm">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 p-4 pb-2 sm:p-6">
                    <CardTitle class="text-xs font-medium leading-snug sm:text-sm">إجمالي الطلبات</CardTitle>
                    <ShoppingCart class="h-4 w-4 shrink-0 text-muted-foreground" />
                </CardHeader>
                <CardContent class="p-4 pt-0 sm:p-6 sm:pt-0">
                    <div class="text-xl font-bold tabular-nums sm:text-2xl">{{ formatInteger(orders.total) }}</div>
                </CardContent>
            </Card>
            <Card class="min-w-0 shadow-sm">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 p-4 pb-2 sm:p-6">
                    <CardTitle class="text-xs font-medium leading-snug sm:text-sm">قيد الانتظار</CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0 sm:p-6 sm:pt-0">
                    <div class="text-xl font-bold tabular-nums sm:text-2xl">{{ formatInteger(pendingCount()) }}</div>
                    <p class="mt-0.5 text-[10px] text-muted-foreground sm:text-xs">في هذه الصفحة</p>
                </CardContent>
            </Card>
            <Card class="min-w-0 shadow-sm">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 p-4 pb-2 sm:p-6">
                    <CardTitle class="text-xs font-medium leading-snug sm:text-sm">مدفوع</CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0 sm:p-6 sm:pt-0">
                    <div class="text-xl font-bold tabular-nums sm:text-2xl">{{ formatInteger(paidCount()) }}</div>
                    <p class="mt-0.5 text-[10px] text-muted-foreground sm:text-xs">في هذه الصفحة</p>
                </CardContent>
            </Card>
            <Card class="min-w-0 col-span-2 shadow-sm lg:col-span-1">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 p-4 pb-2 sm:p-6">
                    <CardTitle class="text-xs font-medium leading-snug sm:text-sm">معروض الآن</CardTitle>
                </CardHeader>
                <CardContent class="p-4 pt-0 sm:p-6 sm:pt-0">
                    <div class="text-xl font-bold tabular-nums sm:text-2xl">{{ formatInteger(orders.data.length) }}</div>
                    <p class="mt-0.5 text-[10px] text-muted-foreground sm:text-xs">في الصفحة الحالية</p>
                </CardContent>
            </Card>
        </div>

        <!-- Filters & List -->
        <Card class="min-w-0 shadow-sm">
            <CardHeader class="p-4 sm:p-6">
                <CardTitle class="text-lg sm:text-xl">قائمة الطلبات</CardTitle>
                <CardDescription class="text-sm">
                    استخدم البحث والفلترة لتضييق النتائج
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4 p-4 pt-0 sm:p-6 sm:pt-0">
                <!-- Filters -->
                <div class="space-y-3 rounded-2xl border border-border/60 bg-muted/20 p-3 sm:space-y-4 sm:p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="min-w-0 flex-1">
                            <Label for="search" class="mb-1.5 block text-xs text-muted-foreground">بحث</Label>
                            <div class="relative">
                                <Search class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    id="search"
                                    v-model="searchInput"
                                    type="search"
                                    placeholder="رقم الطلب، اسم العميل، البريد..."
                                    class="h-11 pr-10"
                                    @keydown.enter.prevent="onSearchSubmit"
                                />
                            </div>
                        </div>
                        <Button class="h-11 w-full touch-manipulation sm:w-auto" @click="onSearchSubmit" type="button">
                            بحث
                        </Button>
                    </div>

                    <div class="grid grid-cols-1 gap-3 min-[480px]:grid-cols-2 sm:grid-cols-3">
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <Label for="status" class="text-xs text-muted-foreground">الحالة</Label>
                            <select
                                id="status"
                                v-model="statusFilter"
                                class="flex h-11 w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                            >
                                <option value="all">الكل</option>
                                <option value="pending">قيد الانتظار</option>
                                <option value="processing">قيد المعالجة</option>
                                <option value="paid">مدفوع</option>
                                <option value="cancelled">ملغي</option>
                                <option value="refunded">مسترد</option>
                            </select>
                        </div>
                        <div class="flex min-w-0 flex-col gap-1.5">
                            <Label for="payment" class="text-xs text-muted-foreground">طريقة الدفع</Label>
                            <select
                                id="payment"
                                v-model="paymentFilter"
                                class="flex h-11 w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                            >
                                <option value="all">الكل</option>
                                <option value="credit_card">بطاقة ائتمان</option>
                                <option value="cash">نقدي</option>
                                <option value="bank_transfer">تحويل بنكي</option>
                                <option value="paypal">PayPal</option>
                                <option value="noon">Noon</option>
                            </select>
                        </div>
                        <div class="flex min-w-0 flex-col gap-1.5 min-[480px]:col-span-2 sm:col-span-1">
                            <Label for="currency" class="text-xs text-muted-foreground">العملة</Label>
                            <select
                                id="currency"
                                v-model="currencyFilter"
                                class="flex h-11 w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                            >
                                <option value="all">الكل</option>
                                <option value="SAR">SAR</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Mobile cards -->
                <div class="space-y-3 md:hidden">
                    <div
                        v-if="orders.data.length === 0"
                        class="rounded-2xl border border-dashed px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        لا توجد طلبات
                    </div>

                    <article
                        v-for="order in orders.data"
                        :key="`mobile-${order.id}`"
                        class="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-sm"
                    >
                        <div class="border-b border-border/60 bg-muted/20 px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-mono text-sm font-bold">{{ order.order_number }}</p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">{{ formatDate(order.created_at) }}</p>
                                </div>
                                <Badge :variant="getStatusBadgeVariant(order.status)" class="shrink-0">
                                    {{ getStatusText(order.status) }}
                                </Badge>
                            </div>
                        </div>

                        <div class="space-y-3 px-4 py-3">
                            <div>
                                <p class="text-xs text-muted-foreground">العميل</p>
                                <p class="mt-0.5 font-semibold">{{ order.customer_name }}</p>
                            </div>

                            <div class="flex items-center justify-between gap-3 rounded-xl bg-muted/30 px-3 py-2.5">
                                <div>
                                    <p class="text-xs text-muted-foreground">المبلغ</p>
                                    <p class="mt-0.5 text-lg font-bold text-green-600 dark:text-green-400">
                                        {{ formatCurrency(Number(order.total_amount), order.currency) }}
                                    </p>
                                </div>
                                <div class="text-end">
                                    <p class="text-xs text-muted-foreground">طريقة الدفع</p>
                                    <p class="mt-0.5 text-sm font-medium">{{ getPaymentMethodText(order.payment_method) }}</p>
                                </div>
                            </div>

                            <div v-if="order.customer_email || order.customer_phone" class="space-y-1.5 text-sm">
                                <p class="text-xs text-muted-foreground">التواصل</p>
                                <a
                                    v-if="order.customer_phone"
                                    :href="`tel:${order.customer_phone}`"
                                    class="flex min-h-10 items-center gap-2 rounded-lg border border-border/60 px-3 py-2 text-foreground transition hover:bg-muted/40"
                                >
                                    <Phone class="h-4 w-4 shrink-0 text-muted-foreground" />
                                    <span dir="ltr" class="truncate">{{ order.customer_phone }}</span>
                                </a>
                                <a
                                    v-if="order.customer_email"
                                    :href="`mailto:${order.customer_email}`"
                                    class="flex min-h-10 items-center gap-2 rounded-lg border border-border/60 px-3 py-2 text-foreground transition hover:bg-muted/40"
                                >
                                    <Mail class="h-4 w-4 shrink-0 text-muted-foreground" />
                                    <span class="truncate">{{ order.customer_email }}</span>
                                </a>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div class="rounded-lg border border-border/60 px-3 py-2">
                                    <p class="text-xs text-muted-foreground">تاريخ الفعالية</p>
                                    <p class="mt-0.5 font-medium">{{ formatActivityDate(order.activity_date) }}</p>
                                </div>
                                <div class="rounded-lg border border-border/60 px-3 py-2">
                                    <p class="text-xs text-muted-foreground">العملة</p>
                                    <p class="mt-0.5 font-medium" dir="ltr">{{ order.currency }}</p>
                                </div>
                            </div>

                            <div v-if="order.address && locationMapsUrl(order.address)">
                                <p class="mb-1.5 text-xs text-muted-foreground">الموقع</p>
                                <a
                                    :href="locationMapsUrl(order.address)!"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex min-h-10 items-start gap-2 rounded-lg border border-border/60 px-3 py-2 text-sm text-primary transition hover:bg-muted/40"
                                >
                                    <MapPin class="mt-0.5 h-4 w-4 shrink-0" />
                                    <span class="line-clamp-2 min-w-0 flex-1">{{ order.address }}</span>
                                    <ExternalLink class="mt-0.5 h-3.5 w-3.5 shrink-0 opacity-60" />
                                </a>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 border-t border-border/60 bg-muted/10 p-3">
                            <Button as-child variant="outline" class="h-11 touch-manipulation">
                                <Link :href="route('orders.show', order.id)">
                                    <Eye class="ms-2 h-4 w-4" />
                                    عرض التفاصيل
                                </Link>
                            </Button>
                            <Button
                                variant="destructive"
                                class="h-11 touch-manipulation"
                                @click="deleteOrder(order)"
                            >
                                <Trash2 class="ms-2 h-4 w-4" />
                                حذف
                            </Button>
                        </div>
                    </article>
                </div>

                <!-- Desktop table -->
                <div class="hidden rounded-md border md:block">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>رقم الطلب</TableHead>
                                <TableHead>العميل</TableHead>
                                <TableHead>التواصل</TableHead>
                                <TableHead>تاريخ الفعالية</TableHead>
                                <TableHead>الموقع</TableHead>
                                <TableHead>المبلغ</TableHead>
                                <TableHead>طريقة الدفع</TableHead>
                                <TableHead>الحالة</TableHead>
                                <TableHead>التاريخ</TableHead>
                                <TableHead class="w-[50px]">إجراءات</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="orders.data.length === 0">
                                <TableCell colspan="10" class="h-24 text-center text-muted-foreground">
                                    لا توجد طلبات
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="order in orders.data"
                                :key="order.id"
                                class="hover:bg-muted/50"
                            >
                                <TableCell class="font-mono text-sm font-medium">
                                    {{ order.order_number }}
                                </TableCell>
                                <TableCell>
                                    {{ order.customer_name }}
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-col gap-0.5 text-sm text-muted-foreground">
                                        <span v-if="order.customer_email" class="flex items-center gap-1">
                                            <Mail class="h-3 w-3 shrink-0" />
                                            {{ order.customer_email }}
                                        </span>
                                        <span v-if="order.customer_phone" class="flex items-center gap-1">
                                            <Phone class="h-3 w-3 shrink-0" />
                                            {{ order.customer_phone }}
                                        </span>
                                        <span v-if="!order.customer_email && !order.customer_phone">—</span>
                                    </div>
                                </TableCell>
                                <TableCell class="whitespace-nowrap text-muted-foreground">
                                    {{ formatActivityDate(order.activity_date) }}
                                </TableCell>
                                <TableCell class="max-w-[220px]">
                                    <template v-if="order.address && locationMapsUrl(order.address)">
                                        <a
                                            :href="locationMapsUrl(order.address)!"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex max-w-full items-start gap-1.5 text-sm text-primary hover:underline"
                                            :title="order.address"
                                        >
                                            <MapPin class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                            <span class="line-clamp-2 min-w-0">{{ order.address }}</span>
                                            <ExternalLink class="mt-0.5 h-3 w-3 shrink-0 opacity-60" />
                                        </a>
                                    </template>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell>
                                    <span class="font-semibold text-green-600 dark:text-green-400">
                                        {{ formatCurrency(Number(order.total_amount), order.currency) }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    {{ getPaymentMethodText(order.payment_method) }}
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="getStatusBadgeVariant(order.status)">
                                        {{ getStatusText(order.status) }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    {{ formatDate(order.created_at) }}
                                </TableCell>
                                <TableCell>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" class="h-8 w-8 p-0">
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem as-child>
                                                <Link :href="route('orders.show', order.id)">
                                                    <Eye class="mr-2 h-4 w-4" />
                                                    عرض التفاصيل
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                @click="deleteOrder(order)"
                                                class="text-destructive focus:text-destructive"
                                            >
                                                <Trash2 class="mr-2 h-4 w-4" />
                                                حذف
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="orders.last_page > 1"
                    class="flex flex-col gap-3 rounded-2xl border border-border/60 bg-muted/10 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4"
                >
                    <p class="text-center text-sm text-muted-foreground sm:text-start">
                        عرض
                        <span class="font-medium tabular-nums">{{ formatInteger(orders.from ?? 0) }}</span>
                        إلى
                        <span class="font-medium tabular-nums">{{ formatInteger(orders.to ?? 0) }}</span>
                        من
                        <span class="font-medium tabular-nums">{{ formatInteger(orders.total) }}</span>
                        طلب
                    </p>
                    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                        <Button
                            variant="outline"
                            class="h-11 touch-manipulation"
                            :disabled="orders.current_page <= 1"
                            @click="goToPage(orders.current_page - 1)"
                        >
                            <ChevronRight class="h-4 w-4" />
                            السابق
                        </Button>
                        <span class="px-1 text-center text-sm font-medium tabular-nums text-muted-foreground">
                            {{ formatInteger(orders.current_page) }} / {{ formatInteger(orders.last_page) }}
                        </span>
                        <Button
                            variant="outline"
                            class="h-11 touch-manipulation"
                            :disabled="orders.current_page >= orders.last_page"
                            @click="goToPage(orders.current_page + 1)"
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
